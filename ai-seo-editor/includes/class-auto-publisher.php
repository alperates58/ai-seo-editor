<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Auto_Publisher {

	private const OPTION_KEY = 'aiseo_auto_publisher_settings';
	private const CRON_HOOK  = 'aiseo_auto_publish_cron';

	private AISEO_Settings $settings;
	private AISEO_Logger   $logger;

	public function __construct( AISEO_Settings $settings, AISEO_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function init(): void {
		add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
	}

	public function register_schedules( array $schedules ): array {
		foreach ( [ 1, 2, 4, 6, 12, 24, 48, 72, 168 ] as $hours ) {
			$key               = 'aiseo_every_' . $hours . 'h';
			$schedules[ $key ] = [
				'interval' => $hours * HOUR_IN_SECONDS,
				'display'  => sprintf( 'Her %d saatte bir', $hours ),
			];
		}
		return $schedules;
	}

	public function get_settings(): array {
		$defaults = [
			'enabled'                => false,
			'interval_hours'         => 24,
			'min_seo_score'          => 70,
			'min_readability_score'  => 60,
			'category_ids'           => [],
			'internal_links_count'   => 3,
			'target_words'           => 1000,
			'tone'                   => 'professional',
			'include_faq'            => true,
			'auto_generate'          => true,
			'optimize_before_publish' => true,
		];
		return wp_parse_args( (array) get_option( self::OPTION_KEY, [] ), $defaults );
	}

	public function save_settings( array $data ): void {
		$current = $this->get_settings();

		$new = [
			'enabled'                => ! empty( $data['enabled'] ),
			'interval_hours'         => in_array( (int) ( $data['interval_hours'] ?? 24 ), [ 1, 2, 4, 6, 12, 24, 48, 72, 168 ], true )
									   ? (int) $data['interval_hours'] : 24,
			'min_seo_score'          => max( 0, min( 100, (int) ( $data['min_seo_score'] ?? 70 ) ) ),
			'min_readability_score'  => max( 0, min( 100, (int) ( $data['min_readability_score'] ?? 60 ) ) ),
			'category_ids'           => array_map( 'absint', (array) ( $data['category_ids'] ?? [] ) ),
			'internal_links_count'   => max( 0, min( 10, (int) ( $data['internal_links_count'] ?? 3 ) ) ),
			'target_words'           => max( 300, min( 5000, (int) ( $data['target_words'] ?? 1000 ) ) ),
			'tone'                   => sanitize_key( $data['tone'] ?? 'professional' ),
			'include_faq'            => ! empty( $data['include_faq'] ),
			'auto_generate'          => ! empty( $data['auto_generate'] ),
			'optimize_before_publish' => ! empty( $data['optimize_before_publish'] ),
		];

		update_option( self::OPTION_KEY, $new, false );

		if ( $new['enabled'] !== $current['enabled'] || $new['interval_hours'] !== $current['interval_hours'] ) {
			$this->reschedule( $new );
		}
	}

	private function reschedule( array $settings ): void {
		$this->unschedule();
		if ( $settings['enabled'] ) {
			$this->schedule( $settings['interval_hours'] );
		}
	}

	private function schedule( int $hours ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( $hours * HOUR_IN_SECONDS ), 'aiseo_every_' . $hours . 'h', self::CRON_HOOK );
		}
	}

	public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function get_next_scheduled(): ?string {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		return $ts ? date_i18n( 'd.m.Y H:i', $ts ) : null;
	}

	public function run(): void {
		$settings = $this->get_settings();
		if ( ! $settings['enabled'] ) {
			return;
		}

		$post = $this->get_next_draft( $settings );
		if ( ! $post ) {
			$this->logger->log_ai_operation( 0, 'auto_publish_cron', 'system', 0, 0, 'success', 'Kuyrukta işlenecek taslak yok.' );
			return;
		}

		$this->process_post( $post->ID, $settings );
	}

	public function run_manual(): array {
		$settings = $this->get_settings();
		$post     = $this->get_next_draft( $settings );
		if ( ! $post ) {
			return [ 'success' => false, 'message' => 'Kuyrukta işlenecek taslak yok.' ];
		}
		return $this->process_post( $post->ID, $settings );
	}

	private function get_next_draft( array $settings ): ?WP_Post {
		$args = [
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => '_aiseo_auto_publish_skip',
					'compare' => 'NOT EXISTS',
				],
			],
		];

		if ( ! empty( $settings['category_ids'] ) ) {
			$args['category__in'] = $settings['category_ids'];
		}

		$posts = get_posts( $args );
		return ! empty( $posts ) ? $posts[0] : null;
	}

	public function process_post( int $post_id, ?array $settings = null ): array {
		if ( $settings === null ) {
			$settings = $this->get_settings();
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [ 'success' => false, 'message' => 'Yazı bulunamadı.' ];
		}

		$attempts = (int) get_post_meta( $post_id, '_aiseo_auto_publish_attempt', true );
		update_post_meta( $post_id, '_aiseo_auto_publish_attempt', $attempts + 1 );
		update_post_meta( $post_id, '_aiseo_auto_publish_last_attempt', current_time( 'mysql' ) );

		try {
			$content = $post->post_content;
			$title   = $post->post_title;
			$original_content = (string) $content;

			// Step 1: Generate content if post body is empty/short
			if ( $settings['auto_generate'] && mb_strlen( wp_strip_all_tags( $content ) ) < 200 ) {
				$gen = $this->generate_content( $post_id, $settings );
				if ( ! $gen['success'] ) {
					return $gen;
				}
				$title   = $gen['title'];
				$content = aiseo_preserve_bracket_blocks( $original_content, (string) $gen['content'] );

				wp_update_post( [ 'ID' => $post_id, 'post_title' => $title, 'post_content' => $content ] );

				$yoast = new AISEO_Yoast_Integration();
				if ( ! empty( $gen['focus_keyword'] ) ) {
					update_post_meta( $post_id, '_aiseo_focus_keyword', $gen['focus_keyword'] );
					if ( $yoast->is_yoast_active() ) {
						$yoast->set_focus_keyword( $post_id, $gen['focus_keyword'] );
					}
				}
				if ( ! empty( $gen['meta_description'] ) ) {
					update_post_meta( $post_id, '_aiseo_meta_description', $gen['meta_description'] );
					if ( $yoast->is_yoast_active() ) {
						$yoast->set_meta_description( $post_id, $gen['meta_description'] );
					}
				}
				if ( ! empty( $gen['suggested_tags'] ) ) {
					wp_set_post_tags( $post_id, $gen['suggested_tags'] );
				}

				$post    = get_post( $post_id );
				$content = $post->post_content;
			}

			// Step 2: Full optimization
			if ( $settings['optimize_before_publish'] ) {
				$opt = $this->optimize_post( $post_id, $settings, $content, $title );
				if ( $opt['success'] ) {
					$title   = $opt['title'];
					$content = aiseo_preserve_bracket_blocks( $original_content, (string) $opt['content'] );
				}
			}

			// Step 3: Internal links from same category
			if ( $settings['internal_links_count'] > 0 ) {
				$linked = $this->add_internal_links( $post_id, $settings['internal_links_count'], $content );
				if ( $linked ) {
					$content = aiseo_preserve_bracket_blocks( $original_content, $linked );
				}
			}

			$content = aiseo_preserve_bracket_blocks( $original_content, $content );

			wp_update_post( [ 'ID' => $post_id, 'post_title' => $title, 'post_content' => $content ] );
			$this->logger->invalidate_cache( $post_id );

			// Step 4: Analyze
			$analysis   = ( new AISEO_Analyzer() )->analyze( $post_id, true );
			$seo_score  = (int) ( $analysis['seo_score'] ?? 0 );
			$read_score = (int) ( $analysis['readability_score'] ?? 0 );

			// Step 5: Score check
			if ( $seo_score < $settings['min_seo_score'] || $read_score < $settings['min_readability_score'] ) {
				$reason = sprintf(
					'Puan yetersiz: SEO=%d (min:%d), Okunabilirlik=%d (min:%d)',
					$seo_score, $settings['min_seo_score'],
					$read_score, $settings['min_readability_score']
				);
				update_post_meta( $post_id, '_aiseo_auto_publish_score_fail', $reason );
				$this->logger->log_ai_operation( $post_id, 'auto_publish_skip', (string) $this->settings->get( 'openai_model' ), 0, 0, 'error', $reason );
				return [ 'success' => false, 'message' => $reason, 'seo_score' => $seo_score, 'readability_score' => $read_score ];
			}

			// Step 6: Publish
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
			update_post_meta( $post_id, '_aiseo_auto_published', current_time( 'mysql' ) );
			update_post_meta( $post_id, '_aiseo_auto_publish_scores', wp_json_encode( [ 'seo' => $seo_score, 'read' => $read_score ] ) );
			delete_post_meta( $post_id, '_aiseo_auto_publish_score_fail' );

			$this->logger->log_ai_operation( $post_id, 'auto_publish', (string) $this->settings->get( 'openai_model' ), 0, 0, 'success' );

			return [
				'success'           => true,
				'post_id'           => $post_id,
				'title'             => get_the_title( $post_id ),
				'seo_score'         => $seo_score,
				'readability_score' => $read_score,
				'url'               => get_permalink( $post_id ),
				'message'           => 'Yazı başarıyla yayınlandı.',
			];

		} catch ( Throwable $e ) {
			$this->logger->log_ai_operation( $post_id, 'auto_publish', (string) $this->settings->get( 'openai_model' ), 0, 0, 'error', $e->getMessage() );
			return [ 'success' => false, 'message' => $e->getMessage() ];
		}
	}

	private function generate_content( int $post_id, array $settings ): array {
		$post       = get_post( $post_id );
		$title      = $post instanceof WP_Post ? $post->post_title : '';
		$categories = wp_get_post_categories( $post_id );

		// Kategori önceliği: en alttaki alt kategori (child-first)
		$category = 0;
		if ( ! empty( $categories ) ) {
			usort( $categories, static function ( $a, $b ) {
				$depth_a = count( get_ancestors( $a, 'category' ) );
				$depth_b = count( get_ancestors( $b, 'category' ) );
				return $depth_b <=> $depth_a;
			} );
			$category = (int) $categories[0];
		}

		// Yoast keyword → yoksa başlıktan türet
		$yoast   = new AISEO_Yoast_Integration();
		$keyword = $yoast->get_focus_keyword( $post_id );
		if ( empty( $keyword ) ) {
			$keyword = (string) get_post_meta( $post_id, '_aiseo_focus_keyword', true );
		}
		if ( empty( $keyword ) ) {
			$keyword = $title;
		}

		$params = [
			'keyword'      => $keyword,
			'title'        => $title,
			'tone'         => $settings['tone'],
			'language'     => (string) $this->settings->get( 'default_language' ),
			'target_words' => $settings['target_words'],
			'include_faq'  => $settings['include_faq'],
			'category'     => $category,
		];

		$client    = new AISEO_OpenAI_Client( $this->settings );
		$generator = new AISEO_Article_Generator( $client, $this->logger );
		return $generator->generate( $params );
	}

	private function optimize_post( int $post_id, array $settings, string $content, string $title ): array {
		$yoast              = new AISEO_Yoast_Integration();
		$keyword            = $yoast->get_focus_keyword( $post_id );
		$keyword_from_title = empty( $keyword );
		if ( empty( $keyword ) ) {
			$keyword = $title;
		}
		if ( empty( $keyword ) ) {
			return [ 'success' => false, 'message' => 'Anahtar kelime bulunamadı.' ];
		}

		try {
			$client       = new AISEO_OpenAI_Client( $this->settings );
			$meta         = $yoast->get_meta_description( $post_id );
			$current_tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
			$result       = $client->optimize_full_post( $post_id, $keyword, $settings['tone'], $content, $title, $meta, $current_tags );

			if ( empty( $result['content'] ) ) {
				return [ 'success' => false, 'message' => 'Optimizasyon içerik döndürmedi.' ];
			}

			$new_title   = aiseo_normalize_seo_title( (string) ( $result['title'] ?? $title ) );
			$new_content = wp_kses_post( $result['content'] );
			$new_meta    = sanitize_textarea_field( $result['meta_description'] ?? $meta );
			$tokens      = (int) ( $result['tokens_used'] ?? 0 );

			// Keyword Yoast'a kaydet (title'dan türetilmişse de mutlaka kaydet)
			if ( $keyword_from_title || ! $yoast->get_focus_keyword( $post_id ) ) {
				update_post_meta( $post_id, '_aiseo_focus_keyword', $keyword );
				if ( $yoast->is_yoast_active() ) {
					$yoast->set_focus_keyword( $post_id, $keyword );
				}
			}

			if ( $new_meta !== '' ) {
				update_post_meta( $post_id, '_aiseo_meta_description', $new_meta );
				if ( $yoast->is_yoast_active() ) {
					$yoast->set_meta_description( $post_id, $new_meta );
				}
			}

			// Ayrı optimize_tags çağrısı — "Etiketleri Düzelt" ile aynı davranış
			$tag_result = $client->optimize_tags( $post_id, $keyword, $new_content, (array) $current_tags );
			$new_tags   = array_map(
				'sanitize_text_field',
				is_array( $tag_result['tags'] ?? null ) ? $tag_result['tags'] : ( is_array( $result['suggested_tags'] ?? null ) ? $result['suggested_tags'] : [] )
			);
			$tokens += (int) ( $tag_result['tokens_used'] ?? 0 );

			if ( ! empty( $new_tags ) ) {
				wp_set_post_tags( $post_id, $new_tags );
			}

			$this->logger->log_ai_operation(
				$post_id, 'auto_publish_optimize',
				(string) $this->settings->get( 'openai_model' ),
				0, $tokens, 'success'
			);

			return [ 'success' => true, 'title' => $new_title, 'content' => $new_content ];

		} catch ( Throwable $e ) {
			$this->logger->log_ai_operation( $post_id, 'auto_publish_optimize', (string) $this->settings->get( 'openai_model' ), 0, 0, 'error', $e->getMessage() );
			return [ 'success' => false, 'message' => $e->getMessage() ];
		}
	}

	private function add_internal_links( int $post_id, int $limit, string $content ): ?string {
		try {
			$client      = new AISEO_OpenAI_Client( $this->settings );
			$linker      = new AISEO_Internal_Linker( $client, $this->logger );
			$suggestions = $linker->find_suggestions( $post_id );

			$ids = [];
			foreach ( array_slice( $suggestions, 0, $limit ) as $s ) {
				$id = absint( $s['id'] ?? 0 );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}

			if ( empty( $ids ) ) {
				return null;
			}

			$new_content = $linker->apply_suggestions( $post_id, $ids, $content );
			return ( $new_content && $new_content !== $content ) ? $new_content : null;

		} catch ( Throwable $e ) {
			return null;
		}
	}

	public function get_queue( int $limit = 10 ): array {
		$settings = $this->get_settings();
		$args     = [
			'post_type'      => 'post',
			'post_status'    => 'draft',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => [
				[
					'key'     => '_aiseo_auto_publish_skip',
					'compare' => 'NOT EXISTS',
				],
			],
		];

		if ( ! empty( $settings['category_ids'] ) ) {
			$args['category__in'] = $settings['category_ids'];
		}

		$queue = [];
		foreach ( get_posts( $args ) as $post ) {
			$categories = get_the_category( $post->ID );
			$queue[]    = [
				'id'         => $post->ID,
				'title'      => $post->post_title ?: '(Başlıksız)',
				'date'       => $post->post_date,
				'categories' => array_map( static fn( $c ) => $c->name, $categories ),
				'attempts'   => (int) get_post_meta( $post->ID, '_aiseo_auto_publish_attempt', true ),
				'score_fail' => (string) get_post_meta( $post->ID, '_aiseo_auto_publish_score_fail', true ),
				'seo_score'  => (int) get_post_meta( $post->ID, '_aiseo_seo_score', true ),
				'read_score' => (int) get_post_meta( $post->ID, '_aiseo_readability_score', true ),
				'edit_url'   => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			];
		}
		return $queue;
	}

	public function get_history( int $limit = 10 ): array {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'meta_value',
			'meta_key'       => '_aiseo_auto_published',
			'order'          => 'DESC',
			'meta_query'     => [ [ 'key' => '_aiseo_auto_published', 'compare' => 'EXISTS' ] ],
		] );

		$history = [];
		foreach ( $posts as $post ) {
			$scores_raw  = (string) get_post_meta( $post->ID, '_aiseo_auto_publish_scores', true );
			$scores      = $scores_raw ? (array) json_decode( $scores_raw, true ) : [];
			$categories  = get_the_category( $post->ID );
			$history[]   = [
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'published_at' => (string) get_post_meta( $post->ID, '_aiseo_auto_published', true ),
				'categories'   => array_map( static fn( $c ) => $c->name, $categories ),
				'seo_score'    => (int) ( $scores['seo'] ?? 0 ),
				'read_score'   => (int) ( $scores['read'] ?? 0 ),
				'url'          => get_permalink( $post->ID ),
				'edit_url'     => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			];
		}
		return $history;
	}

	public function skip_post( int $post_id ): void {
		update_post_meta( $post_id, '_aiseo_auto_publish_skip', '1' );
	}

	public function unskip_post( int $post_id ): void {
		delete_post_meta( $post_id, '_aiseo_auto_publish_skip' );
	}
}
