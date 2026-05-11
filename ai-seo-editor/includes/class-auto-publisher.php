<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Auto_Publisher {

	private const OPTION_KEY = 'aiseo_auto_publisher_settings';
	private const CRON_HOOK  = 'aiseo_auto_publish_cron';
	private const QUEUE_ORDER_META = '_aiseo_auto_publish_queue_order';
	private const PROCESSING_TRANSIENT = 'aiseo_auto_publish_processing_post';
	private const LAST_REPORT_OPTION = '_aiseo_last_round_robin_queue_report';

	private AISEO_Settings $settings;
	private AISEO_Logger   $logger;

	public function __construct( AISEO_Settings $settings, AISEO_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function init(): void {
		add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );
		add_action( self::CRON_HOOK, [ $this, 'run' ] );
		add_action( 'init', [ $this, 'maybe_run_due_fallback' ], 20 );
	}

	public function register_schedules( array $schedules ): array {
		foreach ( $this->get_allowed_intervals() as $hours ) {
			$key               = $this->get_schedule_key( $hours );
			$schedules[ $key ] = [
				'interval' => $this->get_interval_seconds( $hours ),
				'display'  => 0.5 === (float) $hours ? 'Her 30 dakikada bir' : sprintf( 'Her %d saatte bir', $hours ),
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
		$interval = (float) ( $data['interval_hours'] ?? 24 );

		$new = [
			'enabled'                => ! empty( $data['enabled'] ),
			'interval_hours'         => in_array( $interval, $this->get_allowed_intervals(), true ) ? $interval : 24,
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

		if ( $new['enabled'] !== $current['enabled'] || (float) $new['interval_hours'] !== (float) $current['interval_hours'] || ( $new['enabled'] && ! wp_next_scheduled( self::CRON_HOOK ) ) ) {
			$this->reschedule( $new );
		}
	}

	private function reschedule( array $settings ): void {
		$this->unschedule();
		if ( $settings['enabled'] ) {
			$this->schedule( (float) $settings['interval_hours'] );
		}
	}

	private function schedule( float $hours ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + $this->get_interval_seconds( $hours ), $this->get_schedule_key( $hours ), self::CRON_HOOK );
		}
	}

	public function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function stop_cron(): int {
		$result = wp_clear_scheduled_hook( self::CRON_HOOK );
		if ( is_wp_error( $result ) || false === $result ) {
			return 0;
		}

		return max( 0, (int) $result );
	}

	public function get_next_scheduled(): ?string {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		return $ts ? wp_date( 'd.m.Y H:i', $ts ) : null;
	}

	public function get_cron_status(): array {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		return [
			'is_scheduled' => false !== $timestamp,
			'timestamp'    => false !== $timestamp ? (int) $timestamp : null,
			'next_run'     => false !== $timestamp ? wp_date( 'd.m.Y H:i', $timestamp ) : null,
		];
	}

	public function maybe_run_due_fallback(): void {
		if ( wp_doing_cron() ) {
			return;
		}

		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$next_run = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $next_run ) {
			$this->schedule( (float) $settings['interval_hours'] );
			return;
		}

		// Stuck transient detection: işlem 15+ dk sürüyorsa transient'ı temizle
		$processing_id = get_transient( self::PROCESSING_TRANSIENT );
		if ( $processing_id && $processing_id !== -1 ) {
			$last_attempt = get_post_meta( (int) $processing_id, '_aiseo_auto_publish_last_attempt', true );
			if ( $last_attempt && ( time() - strtotime( $last_attempt ) ) > 900 ) {
				delete_transient( self::PROCESSING_TRANSIENT );
			}
		}
	}

	public function run(): void {
		$settings = $this->get_settings();
		if ( ! $settings['enabled'] ) {
			return;
		}
		if ( get_transient( self::PROCESSING_TRANSIENT ) ) {
			return;
		}

		set_transient( self::PROCESSING_TRANSIENT, -1, HOUR_IN_SECONDS );

		try {
			$post = $this->get_next_draft( $settings );
			if ( ! $post ) {
				$this->logger->log_ai_operation( 0, 'auto_publish_cron', 'system', 0, 0, 'success', 'Kuyrukta işlenecek taslak yok.' );
				return;
			}

			$this->process_post( $post->ID, $settings );
		} finally {
			delete_transient( self::PROCESSING_TRANSIENT );
		}
	}

	public function run_manual( int $post_id = 0 ): array {
		if ( get_transient( self::PROCESSING_TRANSIENT ) ) {
			return [ 'success' => false, 'message' => 'Şu anda başka bir yazı işleniyor, lütfen bekleyin.' ];
		}
		$settings = $this->get_settings();
		$post     = $post_id > 0 ? get_post( $post_id ) : $this->get_next_draft( $settings );
		if ( ! $post ) {
			return [ 'success' => false, 'message' => 'Kuyrukta işlenecek taslak yok.' ];
		}
		if ( 'draft' !== $post->post_status || 'post' !== $post->post_type || get_post_meta( $post->ID, '_aiseo_auto_publish_skip', true ) ) {
			return [ 'success' => false, 'message' => 'Seçilen yazı otomatik yayın kuyruğunda değil.' ];
		}
		if ( ! empty( $settings['category_ids'] ) && empty( array_intersect( array_map( 'intval', $settings['category_ids'] ), wp_get_post_categories( $post->ID ) ) ) ) {
			return [ 'success' => false, 'message' => 'Seçilen yazı aktif kategori filtresinde değil.' ];
		}
		return $this->process_post( $post->ID, $settings );
	}

	private function get_next_draft( array $settings ): ?WP_Post {
		$ordered_posts = $this->get_real_queue_posts( 1, $settings );
		if ( ! empty( $ordered_posts ) ) {
			return $ordered_posts[0];
		}

		$posts = $this->get_draft_posts( $settings, 50 );
		if ( empty( $posts ) ) {
			return null;
		}
		$queue = $this->diversify_posts_by_category( $posts, 1, $this->get_last_published_category_ids() );
		return ! empty( $queue ) ? $queue[0] : null;
	}

	private function get_draft_posts( array $settings, int $limit, bool $use_queue_order = true ): array {
		$args = [
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

		$posts = get_posts( $args );
		if ( $use_queue_order ) {
			$this->sort_posts_by_queue_order( $posts );
		}

		return array_slice( $posts, 0, $limit );
	}

	private function get_all_eligible_draft_posts( array $settings ): array {
		$args = [
			'post_type'              => 'post',
			'post_status'            => 'draft',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'meta_query'             => [
				[
					'key'     => '_aiseo_auto_publish_skip',
					'compare' => 'NOT EXISTS',
				],
			],
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		];

		if ( ! empty( $settings['category_ids'] ) ) {
			$args['category__in'] = $settings['category_ids'];
		}

		return get_posts( $args );
	}

	private function get_real_queue_posts( int $limit, array $settings ): array {
		if ( $limit <= 0 ) {
			return [];
		}

		$args = [
			'post_type'              => 'post',
			'post_status'            => 'draft',
			'posts_per_page'         => $limit,
			'orderby'                => 'meta_value_num',
			'order'                  => 'ASC',
			'meta_key'               => self::QUEUE_ORDER_META,
			'meta_query'             => [
				[
					'key'     => '_aiseo_auto_publish_skip',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::QUEUE_ORDER_META,
					'compare' => 'EXISTS',
				],
			],
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		];

		if ( ! empty( $settings['category_ids'] ) ) {
			$args['category__in'] = $settings['category_ids'];
		}

		return get_posts( $args );
	}

	public function process_post( int $post_id, ?array $settings = null ): array {
		if ( $settings === null ) {
			$settings = $this->get_settings();
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [ 'success' => false, 'message' => 'Yazı bulunamadı.' ];
		}

		set_transient( self::PROCESSING_TRANSIENT, $post_id, HOUR_IN_SECONDS );

		$attempts = (int) get_post_meta( $post_id, '_aiseo_auto_publish_attempt', true );
		update_post_meta( $post_id, '_aiseo_auto_publish_attempt', $attempts + 1 );
		update_post_meta( $post_id, '_aiseo_auto_publish_last_attempt', current_time( 'mysql' ) );

		try {
			$content = $post->post_content;
			$title   = $post->post_title;
			$draft_title = $title;
			$original_content = (string) $content;

			// Step 1: Generate content if post body is empty/short
			if ( $settings['auto_generate'] && mb_strlen( wp_strip_all_tags( $content ) ) < 200 ) {
				$gen = $this->generate_content( $post_id, $settings );
				if ( ! $gen['success'] ) {
					update_post_meta( $post_id, '_aiseo_auto_publish_score_fail', 'İçerik üretimi başarısız: ' . ( $gen['message'] ?? 'AI hatası' ) );
					delete_post_meta( $post_id, self::QUEUE_ORDER_META );
					$this->logger->log_ai_operation( $post_id, 'auto_publish_skip', (string) $this->settings->get( 'openai_model' ), 0, 0, 'error', $gen['message'] ?? 'generate failed' );
					return $gen;
				}
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
					$gen_meta = aiseo_normalize_meta_description( (string) $gen['meta_description'] );
					update_post_meta( $post_id, '_aiseo_meta_description', $gen_meta );
					if ( $yoast->is_yoast_active() ) {
						$yoast->set_meta_description( $post_id, $gen_meta );
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
					$title   = $draft_title;
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
			$content = aiseo_demote_content_h1_to_h2( $content );

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
				delete_post_meta( $post_id, self::QUEUE_ORDER_META );
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
		} finally {
			delete_transient( self::PROCESSING_TRANSIENT );
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
		$client->set_timeout( 60 );
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
			$client->set_timeout( 60 );
			$meta         = $yoast->get_meta_description( $post_id );
			$current_tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
			$result       = $client->optimize_full_post( $post_id, $keyword, $settings['tone'], $content, $title, $meta, $current_tags );

			if ( empty( $result['content'] ) ) {
				return [ 'success' => false, 'message' => 'Optimizasyon içerik döndürmedi.' ];
			}

			$new_title   = $title;
			$new_content = wp_kses_post( aiseo_demote_content_h1_to_h2( $result['content'] ) );
			$new_meta    = aiseo_normalize_meta_description( (string) ( $result['meta_description'] ?? $meta ) );
			$tokens      = (int) ( $result['tokens_used'] ?? 0 );

			// AI tarafından üretilen SEO başlığını Yoast'a kaydet (post_title değişmez)
			// Fallback zinciri: birden fazla alanı dene, post_title ile aynıysa geç
			$seo_title_candidates = [
				$result['seo_title'] ?? '',
				$result['seoTitle'] ?? '',
				$result['optimized_title'] ?? '',
				$result['meta_title'] ?? '',
				$result['title'] ?? '',
			];
			$ai_seo_title = '';
			foreach ( $seo_title_candidates as $candidate ) {
				$candidate = aiseo_normalize_seo_title( (string) $candidate );
				if ( $candidate !== '' && $candidate !== $title ) {
					$ai_seo_title = $candidate;
					break;
				}
			}
			if ( $ai_seo_title === '' ) {
				$ai_seo_title = $client->generate_seo_title( $post_id, $keyword, $new_content, $title );
				if ( $ai_seo_title === $title ) {
					$ai_seo_title = '';
				}
			}
			if ( $ai_seo_title !== '' ) {
				update_post_meta( $post_id, '_aiseo_seo_title', $ai_seo_title );
				if ( $yoast->is_yoast_active() ) {
					$yoast->set_seo_title( $post_id, $ai_seo_title );
				}
			}

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
			$client->set_timeout( 60 );
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
		$queue = [];
		$posts = $this->get_real_queue_posts( $limit, $settings );

		if ( empty( $posts ) ) {
			$posts = $this->get_draft_posts( $settings, max( $limit * 4, 50 ), false );
			$posts = $this->diversify_posts_by_category( $posts, $limit, $this->get_last_published_category_ids() );
		}

		foreach ( $posts as $post ) {
			$context    = $this->resolve_post_queue_category_context( (int) $post->ID );
			$categories = [ $context['bucket_name'] ];
			if ( $context['main_category_name'] !== $context['bucket_name'] ) {
				array_unshift( $categories, $context['main_category_name'] );
			}
			$queue[]    = [
				'id'         => $post->ID,
				'title'      => $post->post_title ?: '(Başlıksız)',
				'date'       => $post->post_date,
				'categories' => array_values( array_unique( array_filter( $categories ) ) ),
				'attempts'   => (int) get_post_meta( $post->ID, '_aiseo_auto_publish_attempt', true ),
				'score_fail' => (string) get_post_meta( $post->ID, '_aiseo_auto_publish_score_fail', true ),
				'seo_score'  => (int) get_post_meta( $post->ID, '_aiseo_seo_score', true ),
				'read_score' => (int) get_post_meta( $post->ID, '_aiseo_readability_score', true ),
				'edit_url'   => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			];
		}
		return $queue;
	}

	public function refresh_queue_order( int $limit = 200 ): array {
		$settings           = $this->get_settings();
		$processing_post_id = absint( get_transient( self::PROCESSING_TRANSIENT ) );
		$posts              = $this->get_all_eligible_draft_posts( $settings );
		$posts_to_sort      = [];

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( $processing_post_id > 0 && (int) $post->ID === $processing_post_id ) {
				continue;
			}
			$posts_to_sort[] = $post;
		}

		$ordered = $this->build_queue_order_by_subcategory_coverage( $posts_to_sort, $this->get_last_published_category_ids() );
		$stamp   = time();
		foreach ( $ordered as $index => $post ) {
			update_post_meta( $post->ID, self::QUEUE_ORDER_META, $stamp + $index );
		}

		return $this->get_queue( min( 50, max( 1, $limit ) ) );
	}

	public function rebuild_round_robin_queue(): array {
		$settings           = $this->get_settings();
		$processing_post_id = absint( get_transient( self::PROCESSING_TRANSIENT ) );
		$draft_posts        = $this->get_all_eligible_draft_posts( $settings );
		$eligible_posts     = [];

		foreach ( $draft_posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( $processing_post_id > 0 && $processing_post_id === (int) $post->ID ) {
				continue;
			}

			$eligible_posts[] = $post;
		}

		$total_drafts = count( $eligible_posts );
		$backup_path  = $this->backup_queue_order_to_csv( $eligible_posts );

		if ( 0 === $total_drafts ) {
			$empty_report = [
				'updated_count'                      => 0,
				'total_drafts'                       => 0,
				'bucket_count'                       => 0,
				'first_20_items'                     => [],
				'first_160_unique_child_count'       => 0,
				'first_160_adjacent_duplicate_count' => 0,
				'backup_path'                        => $backup_path,
				'next_post_id'                       => 0,
				'next_post_title'                    => '',
				'report_saved'                       => false,
			];
			$empty_report['report_saved'] = update_option( self::LAST_REPORT_OPTION, $empty_report, false );

			return $empty_report;
		}

		$buckets         = $this->build_round_robin_buckets( $eligible_posts );
		$ordered_entries = $this->build_round_robin_entries( $buckets );
		$applied_at      = current_time( 'mysql' );
		$base            = time();
		$step            = 2;
		$updated_count   = 0;
		$first_20_items  = [];

		foreach ( $ordered_entries as $index => $entry ) {
			$post_id     = (int) $entry['post']->ID;
			$queue_order = $base + ( $index * $step );

			update_post_meta( $post_id, self::QUEUE_ORDER_META, $queue_order );
			update_post_meta( $post_id, '_aiseo_queue_round_robin_applied', $applied_at );
			update_post_meta( $post_id, '_aiseo_queue_round', (int) $entry['round'] );
			clean_post_cache( $post_id );
			$updated_count++;

			if ( $index < 20 ) {
				$first_20_items[] = [
					'post_id'            => $post_id,
					'title'              => (string) $entry['post']->post_title,
					'bucket_slug'        => (string) $entry['context']['bucket_slug'],
					'bucket_name'        => (string) $entry['context']['bucket_name'],
					'main_category_slug' => (string) $entry['context']['main_category_slug'],
					'main_category_name' => (string) $entry['context']['main_category_name'],
					'round'              => (int) $entry['round'],
					'queue_order'        => $queue_order,
				];
			}
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		$report = $this->build_queue_rebuild_report( $ordered_entries, $first_20_items, $updated_count, $total_drafts, $backup_path );
		$report['report_saved'] = update_option( self::LAST_REPORT_OPTION, $report, false );

		return $report;
	}

	public function clear_queue_order(): int {
		global $wpdb;

		if ( ! isset( $wpdb->postmeta ) ) {
			return 0;
		}

		$result = $wpdb->delete(
			$wpdb->postmeta,
			[ 'meta_key' => self::QUEUE_ORDER_META ],
			[ '%s' ]
		);

		if ( false === $result ) {
			return 0;
		}

		return max( 0, (int) $result );
	}

	private function has_queue_order( array $posts ): bool {
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && metadata_exists( 'post', $post->ID, self::QUEUE_ORDER_META ) ) {
				return true;
			}
		}
		return false;
	}

	private function sort_posts_by_queue_order( array &$posts ): void {
		usort( $posts, static function ( $a, $b ) {
			$a_has_order = $a instanceof WP_Post && metadata_exists( 'post', $a->ID, self::QUEUE_ORDER_META );
			$b_has_order = $b instanceof WP_Post && metadata_exists( 'post', $b->ID, self::QUEUE_ORDER_META );

			if ( $a_has_order !== $b_has_order ) {
				return $a_has_order ? -1 : 1;
			}

			if ( $a_has_order && $b_has_order ) {
				$a_order = (int) get_post_meta( $a->ID, self::QUEUE_ORDER_META, true );
				$b_order = (int) get_post_meta( $b->ID, self::QUEUE_ORDER_META, true );
				if ( $a_order !== $b_order ) {
					return $a_order <=> $b_order;
				}
			}

			$a_date = $a instanceof WP_Post ? (string) $a->post_date : '';
			$b_date = $b instanceof WP_Post ? (string) $b->post_date : '';
			return strcmp( $a_date, $b_date );
		} );
	}

	private function get_allowed_intervals(): array {
		return [ 0.5, 1.0, 2.0, 4.0, 6.0, 12.0, 24.0, 48.0, 72.0, 168.0 ];
	}

	private function get_interval_seconds( float $hours ): int {
		return max( 1, (int) round( $hours * HOUR_IN_SECONDS ) );
	}

	private function get_schedule_key( float $hours ): string {
		return 0.5 === $hours ? 'aiseo_every_30m' : 'aiseo_every_' . (int) $hours . 'h';
	}

	private function get_last_published_category_ids(): array {
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'meta_value',
			'meta_key'       => '_aiseo_auto_published',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => '_aiseo_auto_published', 'compare' => 'EXISTS' ] ],
		] );

		if ( empty( $posts ) ) {
			return [];
		}

		$root_ids = [];
		foreach ( wp_get_post_categories( (int) $posts[0] ) as $cat_id ) {
			$root_ids[] = $this->get_root_category_id_for( (int) $cat_id );
		}
		return array_unique( $root_ids );
	}

	private function get_root_category_id( int $post_id ): int {
		$categories = wp_get_post_categories( $post_id );
		if ( empty( $categories ) ) {
			return 0;
		}

		// En derin alt kategoriyi bul, onun kök atasını döndür
		usort( $categories, static function ( $a, $b ) {
			$depth_a = count( get_ancestors( $a, 'category' ) );
			$depth_b = count( get_ancestors( $b, 'category' ) );
			return $depth_b <=> $depth_a;
		} );

		return $this->get_root_category_id_for( (int) $categories[0] );
	}

	private function get_root_category_id_for( int $category_id ): int {
		$ancestors = get_ancestors( $category_id, 'category' );
		return empty( $ancestors ) ? $category_id : (int) end( $ancestors );
	}

	public function resolve_post_queue_category_context( int $post_id ): array {
		$category_ids = array_map( 'intval', wp_get_post_categories( $post_id ) );

		if ( empty( $category_ids ) ) {
			return [
				'bucket_term_id'      => 0,
				'bucket_slug'         => 'uncategorized',
				'bucket_name'         => 'Kategorisiz',
				'main_term_id'        => 0,
				'main_category_slug'  => 'uncategorized',
				'main_category_name'  => 'Kategorisiz',
				'depth'               => 0,
			];
		}

		usort( $category_ids, function ( int $a, int $b ) {
			$depth_compare = $this->get_category_depth( $b ) <=> $this->get_category_depth( $a );
			if ( 0 !== $depth_compare ) {
				return $depth_compare;
			}

			return $a <=> $b;
		} );

		$bucket_term_id = (int) $category_ids[0];
		$bucket_term    = get_term( $bucket_term_id, 'category' );
		$main_term_id   = $this->get_root_category_id_for( $bucket_term_id );
		$main_term      = $main_term_id > 0 ? get_term( $main_term_id, 'category' ) : null;

		return [
			'bucket_term_id'      => $bucket_term_id,
			'bucket_slug'         => $bucket_term instanceof WP_Term && ! is_wp_error( $bucket_term ) ? (string) $bucket_term->slug : 'uncategorized',
			'bucket_name'         => $bucket_term instanceof WP_Term && ! is_wp_error( $bucket_term ) ? (string) $bucket_term->name : 'Kategorisiz',
			'main_term_id'        => $main_term_id,
			'main_category_slug'  => $main_term instanceof WP_Term && ! is_wp_error( $main_term ) ? (string) $main_term->slug : 'uncategorized',
			'main_category_name'  => $main_term instanceof WP_Term && ! is_wp_error( $main_term ) ? (string) $main_term->name : 'Kategorisiz',
			'depth'               => $this->get_category_depth( $bucket_term_id ),
		];
	}

	private function get_primary_category_id( int $post_id ): int {
		$categories = wp_get_post_categories( $post_id );
		if ( empty( $categories ) ) {
			return 0;
		}

		usort( $categories, function ( $a, $b ) {
			$depth_compare = $this->get_category_depth( (int) $b ) <=> $this->get_category_depth( (int) $a );
			if ( 0 !== $depth_compare ) {
				return $depth_compare;
			}

			return (int) $a <=> (int) $b;
		} );

		return (int) $categories[0];
	}

	private function get_category_depth( int $category_id ): int {
		if ( $category_id <= 0 ) {
			return 0;
		}

		return count( get_ancestors( $category_id, 'category' ) );
	}

	private function get_category_publish_counts( array $category_ids ): array {
		$counts = [ 0 => 0 ];
		$terms  = get_terms( [
			'taxonomy'   => 'category',
			'hide_empty' => false,
		] );

		if ( is_wp_error( $terms ) ) {
			return $counts;
		}

		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$counts[ (int) $term->term_id ] = (int) $term->count;
			}
		}

		foreach ( array_unique( array_map( 'intval', $category_ids ) ) as $category_id ) {
			if ( ! isset( $counts[ $category_id ] ) ) {
				$counts[ $category_id ] = 0;
			}
		}

		return $counts;
	}

	private function build_queue_order_by_subcategory_coverage( array $posts, array $avoid_root_category_ids = [] ): array {
		$buckets      = [];
		$category_ids = [];

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$category_id   = $this->get_primary_category_id( $post->ID );
			$root_id       = $category_id > 0 ? $this->get_root_category_id_for( $category_id ) : 0;
			$category_ids[] = $category_id;

			if ( ! isset( $buckets[ $category_id ] ) ) {
				$buckets[ $category_id ] = [
					'category_id'   => $category_id,
					'root_id'       => $root_id,
					'publish_count' => 0,
					'posts'         => [],
					'first_date'    => (string) $post->post_date,
				];
			}

			$buckets[ $category_id ]['posts'][] = $post;
			if ( strcmp( (string) $post->post_date, $buckets[ $category_id ]['first_date'] ) < 0 ) {
				$buckets[ $category_id ]['first_date'] = (string) $post->post_date;
			}
		}

		if ( empty( $buckets ) ) {
			return [];
		}

		$publish_counts = $this->get_category_publish_counts( $category_ids );
		foreach ( $buckets as $category_id => $bucket ) {
			$buckets[ $category_id ]['publish_count'] = (int) ( $publish_counts[ (int) $category_id ] ?? 0 );
		}

		$ordered_bucket_ids = array_keys( $buckets );
		usort( $ordered_bucket_ids, function ( $a, $b ) use ( $buckets ) {
			$count_compare = (int) $buckets[ $a ]['publish_count'] <=> (int) $buckets[ $b ]['publish_count'];
			if ( 0 !== $count_compare ) {
				return $count_compare;
			}

			$date_compare = strcmp( (string) $buckets[ $a ]['first_date'], (string) $buckets[ $b ]['first_date'] );
			if ( 0 !== $date_compare ) {
				return $date_compare;
			}

			return (int) $a <=> (int) $b;
		} );

		$tiers = [];
		foreach ( $ordered_bucket_ids as $category_id ) {
			$tiers[ (int) $buckets[ $category_id ]['publish_count'] ][ $category_id ] = $buckets[ $category_id ];
		}

		ksort( $tiers, SORT_NUMERIC );

		$result            = [];
		$previous_category = 0;
		$previous_root     = 0;
		$avoid_root_map    = array_fill_keys( array_map( 'intval', $avoid_root_category_ids ), true );

		foreach ( $tiers as $tier_buckets ) {
			while ( ! empty( $tier_buckets ) ) {
				$next_category_id = $this->select_next_queue_bucket(
					$tier_buckets,
					$previous_category,
					$previous_root,
					$avoid_root_map,
					empty( $result )
				);

				if ( null === $next_category_id ) {
					break;
				}

				$result[]          = array_shift( $tier_buckets[ $next_category_id ]['posts'] );
				$previous_category = (int) $tier_buckets[ $next_category_id ]['category_id'];
				$previous_root     = (int) $tier_buckets[ $next_category_id ]['root_id'];

				if ( empty( $tier_buckets[ $next_category_id ]['posts'] ) ) {
					unset( $tier_buckets[ $next_category_id ] );
				}
			}
		}

		return $result;
	}

	private function select_next_queue_bucket( array $buckets, int $previous_category, int $previous_root, array $avoid_root_map, bool $is_first_pick ): ?int {
		if ( empty( $buckets ) ) {
			return null;
		}

		$best_category_id = null;
		$best_score       = null;

		foreach ( $buckets as $category_id => $bucket ) {
			$score = [
				$previous_category > 0 && (int) $bucket['category_id'] === $previous_category ? 1 : 0,
				$previous_root > 0 && (int) $bucket['root_id'] === $previous_root ? 1 : 0,
				$is_first_pick && ! empty( $avoid_root_map[ (int) $bucket['root_id'] ] ) ? 1 : 0,
				(string) $bucket['first_date'],
				(int) $category_id,
			];

			if ( null === $best_score || $score < $best_score ) {
				$best_score       = $score;
				$best_category_id = (int) $category_id;
			}
		}

		return $best_category_id;
	}

	private function build_round_robin_buckets( array $posts ): array {
		$buckets         = [];
		$bucket_term_ids = [];

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$context         = $this->resolve_post_queue_category_context( (int) $post->ID );
			$bucket_key      = (string) $context['bucket_slug'];
			$existing_order  = metadata_exists( 'post', $post->ID, self::QUEUE_ORDER_META ) ? (int) get_post_meta( $post->ID, self::QUEUE_ORDER_META, true ) : PHP_INT_MAX;
			$has_queue_order = metadata_exists( 'post', $post->ID, self::QUEUE_ORDER_META );

			if ( ! isset( $buckets[ $bucket_key ] ) ) {
				$buckets[ $bucket_key ] = [
					'key'                => $bucket_key,
					'context'            => $context,
					'published_count'    => 0,
					'has_queue_order'    => false,
					'min_existing_order' => PHP_INT_MAX,
					'min_post_id'        => PHP_INT_MAX,
					'posts'              => [],
				];
				$bucket_term_ids[] = (int) $context['bucket_term_id'];
			}

			$buckets[ $bucket_key ]['posts'][] = [
				'post'            => $post,
				'context'         => $context,
				'existing_order'  => $existing_order,
				'has_queue_order' => $has_queue_order,
			];
			$buckets[ $bucket_key ]['has_queue_order'] = $buckets[ $bucket_key ]['has_queue_order'] || $has_queue_order;
			$buckets[ $bucket_key ]['min_existing_order'] = min( $buckets[ $bucket_key ]['min_existing_order'], $existing_order );
			$buckets[ $bucket_key ]['min_post_id']        = min( $buckets[ $bucket_key ]['min_post_id'], (int) $post->ID );
		}

		$publish_counts = $this->get_category_publish_counts( $bucket_term_ids );

		foreach ( $buckets as $bucket_key => $bucket ) {
			$buckets[ $bucket_key ]['published_count'] = (int) ( $publish_counts[ (int) $bucket['context']['bucket_term_id'] ] ?? 0 );
			usort( $buckets[ $bucket_key ]['posts'], static function ( array $a, array $b ) {
				if ( $a['has_queue_order'] !== $b['has_queue_order'] ) {
					return $a['has_queue_order'] ? -1 : 1;
				}

				if ( $a['existing_order'] !== $b['existing_order'] ) {
					return $a['existing_order'] <=> $b['existing_order'];
				}

				return (int) $a['post']->ID <=> (int) $b['post']->ID;
			} );
		}

		return $buckets;
	}

	private function build_round_robin_entries( array $buckets ): array {
		$result          = [];
		$round           = 1;
		$previous_bucket = '';
		$previous_main   = '';

		while ( true ) {
			$active_keys = array_values( array_filter(
				array_keys( $buckets ),
				static fn( string $bucket_key ): bool => ! empty( $buckets[ $bucket_key ]['posts'] )
			) );

			if ( empty( $active_keys ) ) {
				break;
			}

			$round_keys = $this->sort_round_robin_bucket_keys( $active_keys, $buckets, $previous_bucket, $previous_main );

			foreach ( $round_keys as $bucket_key ) {
				if ( empty( $buckets[ $bucket_key ]['posts'] ) ) {
					continue;
				}

				$entry            = array_shift( $buckets[ $bucket_key ]['posts'] );
				$entry['round']   = $round;
				$result[]         = $entry;
				$previous_bucket  = (string) $entry['context']['bucket_slug'];
				$previous_main    = (string) $entry['context']['main_category_slug'];
			}

			$round++;
		}

		return $result;
	}

	private function sort_round_robin_bucket_keys( array $bucket_keys, array $buckets, string $previous_bucket, string $previous_main ): array {
		usort( $bucket_keys, static function ( string $a, string $b ) use ( $buckets, $previous_bucket, $previous_main ) {
			$bucket_a = $buckets[ $a ];
			$bucket_b = $buckets[ $b ];

			$adjacent_a = [
				$a === $previous_bucket ? 1 : 0,
				(string) $bucket_a['context']['main_category_slug'] === $previous_main ? 1 : 0,
			];
			$adjacent_b = [
				$b === $previous_bucket ? 1 : 0,
				(string) $bucket_b['context']['main_category_slug'] === $previous_main ? 1 : 0,
			];

			if ( $adjacent_a !== $adjacent_b ) {
				return $adjacent_a <=> $adjacent_b;
			}

			$score_a = [
				(int) $bucket_a['published_count'],
				$bucket_a['has_queue_order'] ? 0 : 1,
				(int) $bucket_a['min_existing_order'],
				(int) $bucket_a['min_post_id'],
				(string) $bucket_a['key'],
			];
			$score_b = [
				(int) $bucket_b['published_count'],
				$bucket_b['has_queue_order'] ? 0 : 1,
				(int) $bucket_b['min_existing_order'],
				(int) $bucket_b['min_post_id'],
				(string) $bucket_b['key'],
			];

			return $score_a <=> $score_b;
		} );

		return $bucket_keys;
	}

	private function backup_queue_order_to_csv( array $draft_posts ): string {
		$upload_dir = wp_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';

		if ( '' === $basedir ) {
			return '';
		}

		$backup_dir = trailingslashit( $basedir ) . 'aiseo-backups/';
		if ( ! wp_mkdir_p( $backup_dir ) ) {
			return '';
		}

		$filename = sanitize_file_name( 'aiseo-queue-order-backup-' . gmdate( 'Y-m-d-His' ) . '.csv' );
		$path     = $backup_dir . $filename;
		$handle   = @fopen( $path, 'w' );

		if ( false === $handle ) {
			return '';
		}

		fputcsv( $handle, [ 'post_id', 'post_title', 'post_status', 'post_name', 'old_queue_order' ] );

		foreach ( $draft_posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			fputcsv( $handle, [
				(int) $post->ID,
				(string) $post->post_title,
				(string) $post->post_status,
				(string) $post->post_name,
				metadata_exists( 'post', $post->ID, self::QUEUE_ORDER_META ) ? (string) get_post_meta( $post->ID, self::QUEUE_ORDER_META, true ) : '',
			] );
		}

		fclose( $handle );

		return $path;
	}

	private function build_queue_rebuild_report( array $ordered_entries, array $first_20_items, int $updated_count, int $total_drafts, string $backup_path ): array {
		$bucket_slugs                  = [];
		$first_160_adjacent_duplicates = 0;
		$previous_bucket               = null;
		$first_160_bucket_slice        = [];

		foreach ( $ordered_entries as $index => $entry ) {
			$bucket_slug                = (string) $entry['context']['bucket_slug'];
			$bucket_slugs[ $bucket_slug ] = true;

			if ( $index < 160 ) {
				$first_160_bucket_slice[] = $bucket_slug;
				if ( $bucket_slug === $previous_bucket ) {
					$first_160_adjacent_duplicates++;
				}
				$previous_bucket = $bucket_slug;
			}
		}

		$next_entry = $ordered_entries[0] ?? null;

		return [
			'updated_count'                      => $updated_count,
			'total_drafts'                       => $total_drafts,
			'bucket_count'                       => count( $bucket_slugs ),
			'first_20_items'                     => $first_20_items,
			'first_160_unique_child_count'       => count( array_unique( $first_160_bucket_slice ) ),
			'first_160_adjacent_duplicate_count' => $first_160_adjacent_duplicates,
			'backup_path'                        => $backup_path,
			'next_post_id'                       => $next_entry ? (int) $next_entry['post']->ID : 0,
			'next_post_title'                    => $next_entry ? (string) $next_entry['post']->post_title : '',
			'report_saved'                       => false,
		];
	}

	private function diversify_posts_by_category( array $posts, int $limit, array $avoid_category_ids = [] ): array {
		$buckets = [];
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$category_id = $this->get_root_category_id( $post->ID );
			$buckets[ $category_id ][] = $post;
		}

		foreach ( $buckets as $category_id => $bucket ) {
			shuffle( $bucket );
			$buckets[ $category_id ] = $bucket;
		}

		$result            = [];
		$previous_category = 0;
		$avoid_map         = array_fill_keys( array_map( 'intval', $avoid_category_ids ), true );

		while ( count( $result ) < $limit && ! empty( $buckets ) ) {
			$candidates = array_filter(
				array_keys( $buckets ),
				static fn( $category_id ) => (int) $category_id !== (int) $previous_category
			);

			if ( empty( $result ) && count( $buckets ) > 1 ) {
				$non_recent = array_filter(
					$candidates,
					static fn( $category_id ) => empty( $avoid_map[ (int) $category_id ] )
				);
				if ( ! empty( $non_recent ) ) {
					$candidates = $non_recent;
				}
			}

			if ( empty( $candidates ) ) {
				$candidates = array_keys( $buckets );
			}

			$max_remaining = max( array_map( static fn( $category_id ) => count( $buckets[ $category_id ] ), $candidates ) );
			$candidates    = array_values( array_filter(
				$candidates,
				static fn( $category_id ) => count( $buckets[ $category_id ] ) === $max_remaining
			) );
			$category_id   = (int) $candidates[ wp_rand( 0, count( $candidates ) - 1 ) ];
			$result[]      = array_shift( $buckets[ $category_id ] );
			$previous_category = $category_id;

			if ( empty( $buckets[ $category_id ] ) ) {
				unset( $buckets[ $category_id ] );
			}
		}

		return $result;
	}

	public function count_queue(): int {
		$settings = $this->get_settings();
		$args     = [
			'post_type'              => 'post',
			'post_status'            => 'draft',
			'posts_per_page'         => 1,
			'orderby'                => 'date',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => '_aiseo_auto_publish_skip',
					'compare' => 'NOT EXISTS',
				],
			],
		];

		if ( ! empty( $settings['category_ids'] ) ) {
			$args['category__in'] = $settings['category_ids'];
		}

		$query = new WP_Query( $args );

		return max( 0, (int) $query->found_posts );
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
