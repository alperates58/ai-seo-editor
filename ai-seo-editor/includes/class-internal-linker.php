<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Internal_Linker {

	private AISEO_OpenAI_Client $client;
	private AISEO_Logger $logger;
	private AISEO_Yoast_Integration $yoast;

	public function __construct( AISEO_OpenAI_Client $client, AISEO_Logger $logger ) {
		$this->client = $client;
		$this->logger = $logger;
		$this->yoast  = new AISEO_Yoast_Integration();
	}

	public function find_suggestions( int $post_id ): array {
		$cached = $this->get_cached( $post_id );
		if ( ! empty( $cached ) ) {
			return $cached;
		}

		$this->clear_pending_suggestions( $post_id );

		$posts_index = $this->build_posts_index( $post_id );
		if ( empty( $posts_index ) ) {
			return [];
		}

		$source_post = get_post( $post_id );
		if ( ! $source_post instanceof WP_Post ) {
			return [];
		}

		$source_content = aiseo_strip_html( apply_filters( 'the_content', $source_post->post_content ) );
		$source_keyword = $this->yoast->get_focus_keyword( $post_id );

		$scored = [];
		foreach ( $posts_index as $candidate ) {
			$score = $this->calculate_similarity( $source_content, $candidate['content'] );

			if ( ! empty( $source_keyword ) && mb_stripos( $candidate['content'], $source_keyword ) !== false ) {
				$score = min( 1.0, $score + 0.15 );
			}

			if ( $score >= 0.1 ) {
				$scored[] = array_merge( $candidate, [ 'similarity_score' => $score ] );
			}
		}

		usort( $scored, fn( $a, $b ) => $b['similarity_score'] <=> $a['similarity_score'] );
		$top_candidates = array_slice( $scored, 0, 10 );

		$suggestions = [];
		foreach ( $top_candidates as $candidate ) {
			$anchor = $candidate['keyword'] ?: $candidate['title'];
			$snippet = $this->find_context_sentence( $source_content, $anchor );

			$suggestions[] = [
				'source_post_id'   => $post_id,
				'target_post_id'   => $candidate['id'],
				'target_url'       => $candidate['url'],
				'target_title'     => $candidate['title'],
				'anchor_text'      => $anchor,
				'context_snippet'  => $snippet,
				'similarity_score' => $candidate['similarity_score'],
				'status'           => 'pending',
			];
		}

		$this->save_suggestions( $post_id, $suggestions );

		return $this->get_cached( $post_id );
	}

	public function get_cached( int $post_id ): array {
		global $wpdb;
		$source_categories = wp_get_post_categories( $post_id );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiseo_internal_link_suggestions WHERE source_post_id = %d AND status = 'pending' ORDER BY similarity_score DESC",
				$post_id
			),
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$target_post = get_post( (int) $row['target_post_id'] );
			if ( ! empty( $source_categories ) ) {
				$target_categories = $target_post instanceof WP_Post ? wp_get_post_categories( $target_post->ID ) : [];
				if ( empty( array_intersect( $source_categories, $target_categories ) ) ) {
					$row = null;
					continue;
				}
			}
			$row['target_title'] = $target_post instanceof WP_Post ? $target_post->post_title : '';
			$row['target_url']   = $target_post instanceof WP_Post ? get_permalink( $target_post ) : '';
		}
		unset( $row );

		return array_values( array_filter( $rows ) );
	}

	public function apply_suggestions( int $post_id, array $suggestion_ids, ?string $source_content = null ): string {
		if ( empty( $suggestion_ids ) ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		global $wpdb;
		$content = $source_content ?? $post->post_content;
		$appended_links = [];

		foreach ( $suggestion_ids as $id ) {
			$id  = absint( $id );
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}aiseo_internal_link_suggestions WHERE id = %d AND source_post_id = %d",
					$id,
					$post_id
				),
				ARRAY_A
			);

			if ( ! $row ) {
				continue;
			}

			$target_post = get_post( (int) $row['target_post_id'] );
			if ( ! $target_post instanceof WP_Post ) {
				continue;
			}

			$anchor  = trim( wp_strip_all_tags( (string) $row['anchor_text'] ) );
			$url     = get_permalink( $target_post );
			$title   = get_the_title( $target_post );
			$link    = '<a href="' . esc_url( $url ) . '">' . esc_html( $anchor ?: $title ) . '</a>';
			$replaced = false;

			foreach ( $this->get_anchor_variants( $anchor, $title ) as $variant ) {
				if ( mb_stripos( aiseo_strip_html( $content ), $variant ) === false ) {
					continue;
				}

				$new_content = $this->replace_first_unlinked_text( $content, $variant, $link );
				if ( $new_content !== $content ) {
					$content  = $new_content;
					$replaced = true;
					break;
				}
			}

			if ( ! $replaced ) {
				$appended_links[ (int) $row['target_post_id'] ] = [
					'url'    => $url,
					'anchor' => $anchor ?: $title,
				];
				$replaced = true;
			}

			if ( $replaced ) {
				$wpdb->update(
					$wpdb->prefix . 'aiseo_internal_link_suggestions',
					[
						'status'      => 'approved',
						'reviewed_at' => current_time( 'mysql' ),
						'reviewed_by' => get_current_user_id(),
					],
					[ 'id' => $id ],
					[ '%s', '%s', '%d' ],
					[ '%d' ]
				);
			}
		}

		if ( ! empty( $appended_links ) ) {
			$content = $this->append_related_links_section( $content, $appended_links );
		}

		return $content;
	}

	private function get_anchor_variants( string $anchor, string $title ): array {
		$variants = array_filter( array_unique( [
			$anchor,
			$title,
			mb_strtolower( $anchor ),
			mb_strtolower( $title ),
		] ) );

		return array_values( array_filter( $variants, fn( $variant ) => mb_strlen( trim( $variant ) ) >= 4 ) );
	}

	private function replace_first_unlinked_text( string $html, string $needle, string $replacement ): string {
		$parts = preg_split( '/(<a\b[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $html;
		}

		foreach ( $parts as $index => $part ) {
			if ( preg_match( '/^<a\b/i', $part ) ) {
				continue;
			}

			$updated = preg_replace( '/' . preg_quote( $needle, '/' ) . '/iu', $replacement, $part, 1 );
			if ( is_string( $updated ) && $updated !== $part ) {
				$parts[ $index ] = $updated;
				return implode( '', $parts );
			}
		}

		return $html;
	}

	private function append_related_links_section( string $content, array $links ): string {
		$html = "\n\n<h2>" . esc_html__( 'İlgili Hesaplama Araçları', 'ai-seo-editor' ) . "</h2>\n<ul>";
		foreach ( $links as $link ) {
			$html .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['anchor'] ) . '</a></li>';
		}
		$html .= '</ul>';

		return rtrim( $content ) . $html;
	}

	private function save_suggestions( int $post_id, array $suggestions ): void {
		global $wpdb;

		$this->clear_pending_suggestions( $post_id );

		foreach ( $suggestions as $s ) {
			$wpdb->insert(
				$wpdb->prefix . 'aiseo_internal_link_suggestions',
				[
					'source_post_id'  => $post_id,
					'target_post_id'  => $s['target_post_id'],
					'anchor_text'     => mb_substr( $s['anchor_text'], 0, 500 ),
					'context_snippet' => mb_substr( $s['context_snippet'], 0, 1000 ),
					'similarity_score' => $s['similarity_score'],
					'status'          => 'pending',
					'computed_at'     => current_time( 'mysql' ),
				],
				[ '%d', '%d', '%s', '%s', '%f', '%s', '%s' ]
			);
		}
	}

	private function clear_pending_suggestions( int $post_id ): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}aiseo_internal_link_suggestions WHERE source_post_id = %d AND status = 'pending'",
				$post_id
			)
		);
	}

	private function build_posts_index( int $exclude_post_id ): array {
		$source_categories = wp_get_post_categories( $exclude_post_id );
		$query = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'exclude'        => [ $exclude_post_id ],
		];

		if ( ! empty( $source_categories ) ) {
			$query['category__in'] = $source_categories;
		}

		$posts = get_posts( $query );

		$index = [];
		foreach ( $posts as $post ) {
			$content  = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ) );
			$keyword  = $this->yoast->get_focus_keyword( $post->ID );
			$index[] = [
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'url'     => get_permalink( $post->ID ),
				'keyword' => $keyword,
				'content' => aiseo_truncate( $content, 500 ),
			];
		}

		return $index;
	}

	private function calculate_similarity( string $text_a, string $text_b ): float {
		if ( empty( $text_a ) || empty( $text_b ) ) {
			return 0.0;
		}

		$tokens_a = $this->tokenize( $text_a );
		$tokens_b = $this->tokenize( $text_b );

		if ( empty( $tokens_a ) || empty( $tokens_b ) ) {
			return 0.0;
		}

		$all_tokens = array_unique( array_merge( $tokens_a, $tokens_b ) );
		$freq_a     = array_count_values( $tokens_a );
		$freq_b     = array_count_values( $tokens_b );

		$vec_a = [];
		$vec_b = [];
		foreach ( $all_tokens as $token ) {
			$vec_a[] = $freq_a[ $token ] ?? 0;
			$vec_b[] = $freq_b[ $token ] ?? 0;
		}

		return $this->cosine_similarity( $vec_a, $vec_b );
	}

	private function tokenize( string $text ): array {
		$text       = mb_strtolower( aiseo_strip_html( $text ) );
		$stop_words = [ 'bir', 've', 'ile', 'bu', 'da', 'de', 'ki', 'mi', 'mu', 'mü', 'için', 'gibi', 'the', 'a', 'an', 'and', 'or', 'is', 'in', 'on', 'at', 'to', 'of', 'with' ];
		$words      = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		return array_filter( $words, function ( $w ) use ( $stop_words ) {
			return mb_strlen( $w ) > 3 && ! in_array( $w, $stop_words, true );
		} );
	}

	private function cosine_similarity( array $vec_a, array $vec_b ): float {
		$dot     = 0;
		$norm_a  = 0;
		$norm_b  = 0;
		$count   = count( $vec_a );

		for ( $i = 0; $i < $count; $i++ ) {
			$dot    += $vec_a[ $i ] * $vec_b[ $i ];
			$norm_a += $vec_a[ $i ] ** 2;
			$norm_b += $vec_b[ $i ] ** 2;
		}

		$denom = sqrt( $norm_a ) * sqrt( $norm_b );
		if ( $denom === 0.0 ) {
			return 0.0;
		}

		return round( $dot / $denom, 4 );
	}

	private function find_context_sentence( string $text, string $anchor ): string {
		if ( empty( $anchor ) ) {
			return '';
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		foreach ( $sentences as $sentence ) {
			if ( mb_stripos( $sentence, $anchor ) !== false ) {
				return aiseo_truncate( $sentence, 200 );
			}
		}

		return '';
	}
}
