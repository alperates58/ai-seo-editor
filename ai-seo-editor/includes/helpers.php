<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aiseo_strip_html( string $html ): string {
	$text = wp_strip_all_tags( $html );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	return trim( $text );
}

function aiseo_count_words( string $text ): int {
	$text = aiseo_strip_html( $text );
	if ( empty( $text ) ) {
		return 0;
	}
	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $words ) ? count( $words ) : 0;
}

function aiseo_keyword_in_text( string $text, string $keyword ): bool {
	if ( empty( $keyword ) ) {
		return false;
	}
	return mb_stripos( $text, $keyword ) !== false;
}

function aiseo_keyword_density( string $text, string $keyword ): float {
	if ( empty( $keyword ) || empty( $text ) ) {
		return 0.0;
	}
	$word_count = aiseo_count_words( $text );
	if ( $word_count === 0 ) {
		return 0.0;
	}
	$keyword_words = aiseo_count_words( $keyword );
	$pattern       = '/' . preg_quote( $keyword, '/' ) . '/iu';
	$occurrences   = preg_match_all( $pattern, $text );
	if ( $occurrences === false || $occurrences === 0 ) {
		return 0.0;
	}
	return round( ( $occurrences * $keyword_words / $word_count ) * 100, 2 );
}

function aiseo_get_score_color( int $score ): string {
	if ( $score >= 80 ) {
		return 'green';
	}
	if ( $score >= 60 ) {
		return 'orange';
	}
	return 'red';
}

function aiseo_get_score_label( int $score ): string {
	if ( $score >= 80 ) {
		return __( 'Iyi', 'ai-seo-editor' );
	}
	if ( $score >= 60 ) {
		return __( 'Gelistirilebilir', 'ai-seo-editor' );
	}
	return __( 'Zayif', 'ai-seo-editor' );
}

function aiseo_score_badge( int $score ): string {
	$color = aiseo_get_score_color( $score );
	$label = aiseo_get_score_label( $score );
	return sprintf(
		'<span class="aiseo-badge aiseo-badge--%s">%d - %s</span>',
		esc_attr( $color ),
		$score,
		esc_html( $label )
	);
}

function aiseo_get_first_paragraph( string $html ): string {
	if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $matches ) ) {
		return wp_strip_all_tags( $matches[1] );
	}
	$text  = aiseo_strip_html( $html );
	$paras = preg_split( '/\n\s*\n/', $text, 2 );
	return $paras[0] ?? '';
}

function aiseo_extract_headings( string $html ): array {
	$headings = [];
	preg_match_all( '/<(h[23456])[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$headings[] = [
			'level' => strtolower( $match[1] ),
			'text'  => wp_strip_all_tags( $match[2] ),
		];
	}
	return $headings;
}

function aiseo_extract_links( string $html, string $site_url = '' ): array {
	if ( empty( $site_url ) ) {
		$site_url = home_url();
	}
	$site_host = wp_parse_url( $site_url, PHP_URL_HOST );

	$internal = [];
	$external = [];

	preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$href = $match[1];
		$text = wp_strip_all_tags( $match[2] );

		if ( strpos( $href, '#' ) === 0 || strpos( $href, 'mailto:' ) === 0 ) {
			continue;
		}

		$href_host = wp_parse_url( $href, PHP_URL_HOST );
		if ( empty( $href_host ) || $href_host === $site_host ) {
			$internal[] = [ 'href' => $href, 'text' => $text ];
		} else {
			$external[] = [ 'href' => $href, 'text' => $text ];
		}
	}

	return [ 'internal' => $internal, 'external' => $external ];
}

function aiseo_extract_images( string $html ): array {
	$images = [];
	preg_match_all( '/<img[^>]+>/i', $html, $matches );
	foreach ( $matches[0] as $img_tag ) {
		$alt = '';
		if ( preg_match( '/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match ) ) {
			$alt = $alt_match[1];
		}
		$src = '';
		if ( preg_match( '/src=["\']([^"\']+)["\']/', $img_tag, $src_match ) ) {
			$src = $src_match[1];
		}
		$images[] = [ 'src' => $src, 'alt' => $alt ];
	}
	return $images;
}

function aiseo_truncate( string $text, int $length = 160 ): string {
	if ( mb_strlen( $text ) <= $length ) {
		return $text;
	}
	return mb_substr( $text, 0, $length - 3 ) . '...';
}

function aiseo_normalize_seo_title( string $title, int $max_length = 58 ): string {
	$title = sanitize_text_field( $title );
	$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? $title );

	if ( $title === '' ) {
		return '';
	}

	if ( mb_strlen( $title ) <= $max_length ) {
		return $title;
	}

	$trimmed = trim( mb_substr( $title, 0, $max_length + 1 ) );
	$last_space = mb_strrpos( $trimmed, ' ' );

	if ( false !== $last_space && $last_space >= (int) floor( $max_length * 0.65 ) ) {
		$trimmed = trim( mb_substr( $trimmed, 0, $last_space ) );
	} else {
		$trimmed = trim( mb_substr( $trimmed, 0, $max_length ) );
	}

	return rtrim( $trimmed, " \t\n\r\0\x0B-:|,;/\\" );
}

function aiseo_preserve_bracket_blocks( string $source, string $target ): string {
	if ( ! preg_match_all( '/\[[^\[\]\r\n]{1,800}\]/u', $source, $matches ) ) {
		return $target;
	}

	$missing = [];
	foreach ( array_unique( $matches[0] ) as $block ) {
		if ( strpos( $target, $block ) === false ) {
			$missing[] = $block;
		}
	}

	if ( empty( $missing ) ) {
		return $target;
	}

	return implode( "\n\n", $missing ) . "\n\n" . $target;
}

function aiseo_get_criterion_fix_action( array $criterion ): array {
	$id = (string) ( $criterion['id'] ?? '' );

	$operation_map = [
		'keyword_in_title'            => 'optimize_title',
		'keyword_in_meta_description' => 'optimize_meta',
		'meta_description_length'     => 'optimize_meta',
		'keyword_in_first_paragraph'  => 'improve_intro',
		'keyword_density'             => 'improve_keyword_density',
		'headings_structure'          => 'improve_structure',
		'keyword_in_headings'         => 'improve_structure',
		'image_alt_text'              => 'optimize_image_alts',
		'sentence_length'             => 'improve_readability',
		'paragraph_length'            => 'improve_readability',
		'passive_voice'               => 'improve_readability',
		'transition_words'            => 'improve_readability',
		'consecutive_sentences'       => 'improve_readability',
		'subheading_distribution'     => 'improve_structure',
		'flesch_reading_ease'         => 'improve_readability',
		'text_complexity'             => 'improve_readability',
	];

	if ( isset( $operation_map[ $id ] ) ) {
		return [
			'action'    => 'operation',
			'operation' => $operation_map[ $id ],
			'label'     => __( 'Duzelt', 'ai-seo-editor' ),
		];
	}

	if ( in_array( $id, [ 'internal_links', 'content_length', 'focus_keyword_present' ], true ) ) {
		return [
			'action' => 'full',
			'label'  => __( 'Duzelt', 'ai-seo-editor' ),
		];
	}

	if ( in_array( $id, [ 'images_present', 'external_links', 'outbound_link_quality', 'keyword_in_url', 'schema_markup', 'canonical_tag', 'og_tags' ], true ) ) {
		return [
			'action' => 'unsupported',
			'label'  => __( 'Duzelt', 'ai-seo-editor' ),
			'reason' => __( 'Bu uyari icerik yerine eklenti ayari veya manuel baglanti gerektiriyor.', 'ai-seo-editor' ),
		];
	}

	return [];
}

function aiseo_render_criterion( array $criterion ): string {
	$status  = esc_attr( $criterion['status'] ?? 'error' );
	$id      = esc_attr( $criterion['id'] ?? '' );
	$label   = esc_html( $criterion['label'] ?? '' );
	$message = esc_html( $criterion['message'] ?? '' );
	$icons   = [ 'good' => 'OK', 'warning' => '!', 'error' => 'x' ];
	$icon    = $icons[ $criterion['status'] ?? 'error' ] ?? '?';
	$fix     = aiseo_get_criterion_fix_action( $criterion );
	$button  = '';

	if ( ( $criterion['status'] ?? 'error' ) !== 'good' && ! empty( $fix['label'] ) ) {
		$button = sprintf(
			'<button type="button" class="button button-secondary button-small aiseo-criterion__fix" data-criterion-id="%1$s" data-fix-action="%2$s" data-operation="%3$s" data-reason="%4$s">%5$s</button>',
			$id,
			esc_attr( $fix['action'] ?? '' ),
			esc_attr( $fix['operation'] ?? '' ),
			esc_attr( $fix['reason'] ?? '' ),
			esc_html( $fix['label'] )
		);
	}

	return sprintf(
		'<div class="aiseo-criterion aiseo-criterion--%s">
			<span class="aiseo-criterion__icon">%s</span>
			<div class="aiseo-criterion__body">
				<strong class="aiseo-criterion__label">%s</strong>
				<p class="aiseo-criterion__message">%s</p>
				%s
			</div>
		</div>',
		$status,
		esc_html( $icon ),
		$label,
		$message,
		$button
	);
}
