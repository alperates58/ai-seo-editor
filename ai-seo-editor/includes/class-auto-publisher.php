<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Auto_Publisher {

	private const OPTION_KEY = 'aiseo_auto_publisher_settings';
	private const CRON_HOOK  = 'aiseo_auto_publish_cron';
	private const QUEUE_ORDER_META = '_aiseo_auto_publish_queue_order';
	private const PROCESSING_TRANSIENT = 'aiseo_auto_publish_processing_post';

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

	public function get_next_scheduled(): ?string {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		return $ts ? wp_date( 'd.m.Y H:i', $ts ) : null;
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
		$posts = $this->get_draft_posts( $settings, 50 );
		if ( empty( $posts ) ) {
			return null;
		}
		if ( $this->has_queue_order( $posts ) ) {
			return $posts[0];
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
			$new_content = wp_kses_post( $result['content'] );
			$new_meta    = aiseo_normalize_meta_description( (string) ( $result['meta_description'] ?? $meta ) );
			$tokens      = (int) ( $result['tokens_used'] ?? 0 );

			// AI tarafından üretilen SEO başlığını Yoast'a kaydet (post_title değişmez)
			$ai_seo_title = aiseo_normalize_seo_title( (string) ( $result['title'] ?? $title ) );
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
		$posts = $this->get_draft_posts( $settings, max( $limit * 4, 50 ) );
		$posts = $this->has_queue_order( $posts )
			? array_slice( $posts, 0, $limit )
			: $this->diversify_posts_by_category( $posts, $limit, $this->get_last_published_category_ids() );

		foreach ( $posts as $post ) {
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

	public function refresh_queue_order( int $limit = 200 ): array {
		$settings           = $this->get_settings();
		$processing_post_id = absint( get_transient( self::PROCESSING_TRANSIENT ) );
		$order_limit        = max( 200, $limit * 4 );
		$posts              = $this->get_draft_posts( $settings, $order_limit, false );
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

		$ordered = $this->diversify_posts_by_category( $posts_to_sort, count( $posts_to_sort ), $this->get_last_published_category_ids() );
		$stamp   = time();
		foreach ( $ordered as $index => $post ) {
			update_post_meta( $post->ID, self::QUEUE_ORDER_META, $stamp + $index );
		}

		return $this->get_queue( min( 50, max( 1, $limit ) ) );
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
