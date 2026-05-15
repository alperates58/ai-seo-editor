<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Admin {

	private AISEO_Settings $settings;
	private AISEO_Logger $logger;

	private string $menu_slug = 'aiseo-dashboard';

	public function __construct( AISEO_Settings $settings, AISEO_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_aiseo_rebuild_round_robin_queue', [ $this, 'ajax_rebuild_round_robin_queue' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_editor_metabox' ] );
		add_filter( 'manage_post_posts_columns', [ $this, 'add_seo_score_column' ] );
		add_action( 'manage_post_posts_custom_column', [ $this, 'render_seo_score_column' ], 10, 2 );
		add_filter( 'manage_page_posts_columns', [ $this, 'add_seo_score_column' ] );
		add_action( 'manage_page_posts_custom_column', [ $this, 'render_seo_score_column' ], 10, 2 );
	}

	public function add_admin_menu(): void {
		add_menu_page(
			__( 'AI SEO Editor', 'ai-seo-editor' ),
			__( 'AI SEO Editor', 'ai-seo-editor' ),
			'manage_options',
			$this->menu_slug,
			[ $this, 'page_dashboard' ],
			'dashicons-chart-area',
			'30.1'
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Genel Bakış', 'ai-seo-editor' ),
			__( 'Genel Bakış', 'ai-seo-editor' ),
			'manage_options',
			$this->menu_slug,
			[ $this, 'page_dashboard' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'İçerik Analizi', 'ai-seo-editor' ),
			__( 'İçerik Analizi', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-posts',
			[ $this, 'page_posts_analysis' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Toplu Analiz', 'ai-seo-editor' ),
			__( 'Toplu Analiz', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-bulk',
			[ $this, 'page_bulk_analysis' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Otomatik İyileştirme', 'ai-seo-editor' ),
			__( 'Otomatik İyileştirme', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-agent',
			[ $this, 'page_agent_optimizer' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'İçerik Üretimi', 'ai-seo-editor' ),
			__( 'İçerik Üretimi', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-generator',
			[ $this, 'page_article_generator' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'İç Linkleme', 'ai-seo-editor' ),
			__( 'İç Linkleme', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-links',
			[ $this, 'page_internal_links' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Otomatik Yayın', 'ai-seo-editor' ),
			__( 'Otomatik Yayın', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-auto-publisher',
			[ $this, 'page_auto_publisher' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Kalite Kontrol', 'ai-seo-editor' ),
			__( 'Kalite Kontrol', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-quality-control',
			[ $this, 'page_quality_control' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'SEO Eksikleri', 'ai-seo-editor' ),
			__( 'SEO Eksikleri', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-seo-title-fix',
			[ $this, 'page_seo_title_fix' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Loglar', 'ai-seo-editor' ),
			__( 'Loglar', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-logs',
			[ $this, 'page_logs' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Ayarlar', 'ai-seo-editor' ),
			__( 'Ayarlar', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-settings',
			[ $this, 'page_settings' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Geliştirici Araçları', 'ai-seo-editor' ),
			__( 'Geliştirici Araçları', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-github',
			[ $this, 'page_github' ]
		);
	}

	public function add_editor_metabox(): void {
		foreach ( [ 'post', 'page' ] as $post_type ) {
			add_meta_box(
				'aiseo-editor-panel',
				__( 'AI SEO Editor', 'ai-seo-editor' ),
				[ $this, 'render_editor_metabox' ],
				$post_type,
				'side',
				'high'
			);
		}
	}

	public function render_editor_metabox( WP_Post $post ): void {
		$seo_score  = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
		$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
		$last       = get_post_meta( $post->ID, '_aiseo_last_analysis', true );
		?>
		<div class="aiseo-editor-panel" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<div class="aiseo-editor-scores">
				<div>
					<span><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></span>
					<strong id="aiseo-editor-seo-score"><?php echo esc_html( $seo_score > 0 ? $seo_score : '—' ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></span>
					<strong id="aiseo-editor-read-score"><?php echo esc_html( $read_score > 0 ? $read_score : '—' ); ?></strong>
				</div>
			</div>
			<p id="aiseo-editor-last" class="aiseo-editor-last"><?php echo $last ? esc_html( sprintf( __( 'Son analiz: %s önce', 'ai-seo-editor' ), human_time_diff( strtotime( $last ) ) ) ) : ''; ?></p>

			<div class="aiseo-editor-actions">
				<button type="button" class="button button-secondary" id="aiseo-editor-analyze"><?php esc_html_e( 'Analiz Et', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button button-primary" id="aiseo-editor-fix-all"><?php esc_html_e( 'Tamamını Düzelt', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button button-secondary" id="aiseo-editor-regenerate"><?php esc_html_e( 'Baştan Oluştur', 'ai-seo-editor' ); ?></button>
			</div>

			<div class="aiseo-editor-quick">
				<button type="button" class="button aiseo-editor-optimize" data-operation="optimize_title"><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="optimize_meta"><?php esc_html_e( 'Meta', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="improve_intro"><?php esc_html_e( 'Giriş', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="improve_readability"><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="improve_structure"><?php esc_html_e( 'Başlıklar', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="improve_keyword_density"><?php esc_html_e( 'Keyword', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="add_faq"><?php esc_html_e( 'FAQ', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button aiseo-editor-optimize" data-operation="improve_conclusion"><?php esc_html_e( 'Sonuç', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button" id="aiseo-editor-internal-links" data-aiseo-action="internal-links"><?php esc_html_e( 'İç Link', 'ai-seo-editor' ); ?></button>
				<button type="button" class="button" id="aiseo-editor-fix-tags" data-aiseo-action="fix-tags"><?php esc_html_e( 'Etiketleri Düzelt', 'ai-seo-editor' ); ?></button>
			</div>

			<div id="aiseo-editor-notice"></div>
			<div id="aiseo-editor-preview"></div>
		</div>
		<?php
	}

	public function enqueue_assets( string $hook ): void {
		$aiseo_pages = [
			'toplevel_page_aiseo-dashboard',
			'ai-seo-editor_page_aiseo-posts',
			'ai-seo-editor_page_aiseo-bulk',
			'ai-seo-editor_page_aiseo-agent',
			'ai-seo-editor_page_aiseo-generator',
			'ai-seo-editor_page_aiseo-links',
			'ai-seo-editor_page_aiseo-auto-publisher',
			'ai-seo-editor_page_aiseo-quality-control',
			'ai-seo-editor_page_aiseo-seo-title-fix',
			'ai-seo-editor_page_aiseo-settings',
			'ai-seo-editor_page_aiseo-github',
			'ai-seo-editor_page_aiseo-logs',
		];

		$is_post_edit = in_array( $hook, [ 'post.php', 'post-new.php' ], true );

		if ( ! in_array( $hook, $aiseo_pages, true ) && ! $is_post_edit ) {
			if ( ! in_array( $hook, [ 'edit.php' ], true ) ) {
				return;
			}
		}

		wp_enqueue_style(
			'aiseo-admin',
			AISEO_PLUGIN_URL . 'admin/css/admin.css',
			[],
			AISEO_VERSION
		);

		wp_enqueue_script(
			'aiseo-admin',
			AISEO_PLUGIN_URL . 'admin/js/admin.js',
			[ 'jquery' ],
			AISEO_VERSION,
			true
		);

		$current_page = sanitize_key( $_GET['page'] ?? '' );
		$post_id      = absint( $_GET['post'] ?? 0 );

		wp_localize_script( 'aiseo-admin', 'AISeoConfig', [
			'restUrl'     => esc_url_raw( rest_url() ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'githubNonce' => wp_create_nonce( 'aiseo_github_version' ),
			'queueRebuildNonce' => wp_create_nonce( 'aiseo_rebuild_round_robin_queue' ),
			'postId'      => $post_id,
			'currentPage' => $current_page,
			'pluginUrl'   => AISEO_PLUGIN_URL,
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'i18n'        => [
				'analyzing'        => __( 'Analiz ediliyor...', 'ai-seo-editor' ),
				'generating'       => __( 'AI ile üretiliyor...', 'ai-seo-editor' ),
				'applying'         => __( 'Uygulanıyor...', 'ai-seo-editor' ),
				'applyChanges'     => __( 'Değişiklikleri Uygula', 'ai-seo-editor' ),
				'cancel'           => __( 'İptal', 'ai-seo-editor' ),
				'confirm'          => __( 'Onaylıyorum', 'ai-seo-editor' ),
				'revisionNote'     => __( 'Uygulama öncesinde otomatik revision oluşturulacak.', 'ai-seo-editor' ),
				'success'          => __( 'Başarılı!', 'ai-seo-editor' ),
				'error'            => __( 'Hata oluştu.', 'ai-seo-editor' ),
				'before'           => __( 'Mevcut', 'ai-seo-editor' ),
				'after'            => __( 'AI Önerisi', 'ai-seo-editor' ),
				'confirmApply'     => __( 'Bu değişikliği uygulamak istediğinizden emin misiniz? Revision otomatik oluşturulacak.', 'ai-seo-editor' ),
				'draftCreated'     => __( 'Taslak oluşturuldu! Düzenlemek ister misiniz?', 'ai-seo-editor' ),
				'selectPosts'      => __( 'Lütfen en az bir yazı seçin.', 'ai-seo-editor' ),
				'bulkDone'         => __( 'Toplu analiz tamamlandı!', 'ai-seo-editor' ),
				'noApiKey'         => __( 'API anahtarı girilmemiş. Lütfen ayarları kontrol edin.', 'ai-seo-editor' ),
				'testKeySuccess'   => __( 'API anahtarı geçerli!', 'ai-seo-editor' ),
				'testKeyFail'      => __( 'API anahtarı geçersiz veya bağlantı hatası.', 'ai-seo-editor' ),
				'confirmRebuildQueue' => __( 'Bu islem yazi iceriklerini silmez. Sadece otomatik yayin sirasini yeniden olusturur. Devam edilsin mi?', 'ai-seo-editor' ),
				'rebuildingQueue'    => __( 'Akilli kuyruk yeniden olusturuluyor...', 'ai-seo-editor' ),
				'rebuildQueueSuccess'=> __( 'Akilli kuyruk yeniden olusturuldu.', 'ai-seo-editor' ),
				'rebuildQueueError'  => __( 'Akilli kuyruk yeniden olusturulamadi.', 'ai-seo-editor' ),
			],
		] );
	}

	public function ajax_rebuild_round_robin_queue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Bu islem icin yetkiniz yok.', 'ai-seo-editor' ) ], 403 );
		}

		check_ajax_referer( 'aiseo_rebuild_round_robin_queue', 'nonce' );

		try {
			$auto_publisher = new AISEO_Auto_Publisher( $this->settings, $this->logger );
			$report         = $auto_publisher->rebuild_round_robin_queue();
			$queue          = $auto_publisher->get_queue( 20 );

			wp_send_json_success( [
				'report'      => $report,
				'queue'       => $queue,
				'queue_total' => $auto_publisher->count_queue(),
				'cron_status' => $auto_publisher->get_cron_status(),
				'next_post'   => $queue[0] ?? null,
			] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ?: __( 'Akilli kuyruk islemi basarisiz oldu.', 'ai-seo-editor' ) ], 500 );
		}
	}

	public function page_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		global $wpdb;

		$stats = $this->logger->get_dashboard_stats();

		// Queue health — sadece scalar COUNT sorguları
		$dash_total_drafts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'post' AND post_status = 'draft'"
		);
		$dash_queued_drafts = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm
			  ON pm.post_id = p.ID AND pm.meta_key = '_aiseo_auto_publish_queue_order'
			WHERE p.post_type = 'post' AND p.post_status = 'draft'"
		);
		$dash_queue_outside  = max( 0, $dash_total_drafts - $dash_queued_drafts );
		$dash_queue_coverage = $dash_total_drafts > 0 ? round( $dash_queued_drafts / $dash_total_drafts * 100 ) : 100;
		$dash_queue_report   = get_option( '_aiseo_last_round_robin_queue_report' );

		$this->render_template( 'dashboard', [
			'stats'               => $stats,
			'dash_total_drafts'   => $dash_total_drafts,
			'dash_queued_drafts'  => $dash_queued_drafts,
			'dash_queue_outside'  => $dash_queue_outside,
			'dash_queue_coverage' => $dash_queue_coverage,
			'dash_queue_report'   => $dash_queue_report,
		] );
	}

	public function page_posts_analysis(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page  = 20;
		$search    = sanitize_text_field( $_GET['s'] ?? '' );
		$post_type = sanitize_key( $_GET['post_type'] ?? 'post' );

		$args = [
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );

		$this->render_template( 'posts-analysis', [
			'posts'      => $query->posts,
			'total'      => $query->found_posts,
			'paged'      => $paged,
			'per_page'   => $per_page,
			'search'     => $search,
			'post_type'  => $post_type,
		] );
	}

	public function page_bulk_analysis(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		$paged                = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page             = 50;
		$current_score_filter = sanitize_key( $_GET['score_filter'] ?? '' );

		$meta_query = [];
		if ( in_array( $current_score_filter, [ 'green', 'orange', 'red' ], true ) ) {
			$score_ranges = [
				'green'  => [ 'min' => 80, 'max' => 100 ],
				'orange' => [ 'min' => 60, 'max' => 79 ],
				'red'    => [ 'min' => 1,  'max' => 59 ],
			];
			$range = $score_ranges[ $current_score_filter ];
			$meta_query = [
				[
					'key'     => '_aiseo_seo_score',
					'value'   => [ $range['min'], $range['max'] ],
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				],
			];
		} elseif ( 'none' === $current_score_filter ) {
			$meta_query = [
				'relation' => 'OR',
				[
					'key'     => '_aiseo_seo_score',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_aiseo_seo_score',
					'value'   => '0',
					'compare' => '=',
				],
			];
		}

		$args = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $args );

		$this->render_template( 'bulk-analysis', [
			'posts'                => $query->posts,
			'total'                => $query->found_posts,
			'paged'                => $paged,
			'per_page'             => $per_page,
			'current_score_filter' => $current_score_filter,
		] );
	}

	public function page_article_generator(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$categories = get_categories( [ 'hide_empty' => false ] );
		$settings   = $this->settings;
		$this->render_template( 'article-generator', compact( 'categories', 'settings' ) );
	}

	public function page_agent_optimizer(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 50;
		$filter   = sanitize_key( $_GET['filter'] ?? '' );
		$search   = sanitize_text_field( $_GET['s'] ?? '' );

		$meta_query = [];
		if ( 'low_seo' === $filter ) {
			$meta_query = [
				[
					'key'     => '_aiseo_seo_score',
					'value'   => [ 1, 79 ],
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				],
			];
		} elseif ( 'low_read' === $filter ) {
			$meta_query = [
				[
					'key'     => '_aiseo_readability_score',
					'value'   => [ 1, 74 ],
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				],
			];
		} elseif ( 'no_analysis' === $filter ) {
			$meta_query = [
				'relation' => 'OR',
				[
					'key'     => '_aiseo_seo_score',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_aiseo_seo_score',
					'value'   => '0',
					'compare' => '=',
				],
			];
		}

		$args = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}
		if ( $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );

		$this->render_template( 'agent-optimizer', [
			'posts'    => $query->posts,
			'total'    => $query->found_posts,
			'paged'    => $paged,
			'per_page' => $per_page,
			'filter'   => $filter,
			'search'   => $search,
		] );
	}

	public function page_internal_links(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		] );
		$this->render_template( 'internal-links', [ 'posts' => $posts ] );
	}

	public function page_auto_publisher(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		global $wpdb;

		$auto_publisher = new AISEO_Auto_Publisher( $this->settings, $this->logger );
		$ap_settings    = $auto_publisher->get_settings();
		$categories     = get_categories( [ 'hide_empty' => false ] );
		$queue          = $auto_publisher->get_queue( 20 );
		$total_queue_count = $auto_publisher->count_queue();
		$history        = $auto_publisher->get_history( 20 );
		$next_run       = $auto_publisher->get_next_scheduled();
		$cron_status    = $auto_publisher->get_cron_status();
		$next_queue_post = $queue[0] ?? null;

		// Queue health — sadece scalar COUNT sorguları
		$total_drafts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'post' AND post_status = 'draft'"
		);
		$queued_drafts = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm
			  ON pm.post_id = p.ID AND pm.meta_key = '_aiseo_auto_publish_queue_order'
			WHERE p.post_type = 'post' AND p.post_status = 'draft'"
		);
		$queue_outside  = max( 0, $total_drafts - $queued_drafts );
		$queue_coverage = $total_drafts > 0 ? round( $queued_drafts / $total_drafts * 100 ) : 100;
		$queue_report   = get_option( '_aiseo_last_round_robin_queue_report' );

		$this->render_template( 'auto-publisher', compact(
			'auto_publisher', 'ap_settings', 'categories', 'queue',
			'total_queue_count', 'history', 'next_run', 'cron_status', 'next_queue_post',
			'total_drafts', 'queued_drafts', 'queue_outside', 'queue_coverage', 'queue_report'
		) );
	}

	public function page_quality_control(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$qc      = new AISEO_Quality_Control();
		$summary = $qc->get_summary();
		$tab     = sanitize_key( $_GET['tab'] ?? 'shortcode' );
		$paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$items   = $qc->get_tab_data( $tab, $paged, 25 );
		$this->render_template( 'quality-control', compact( 'summary', 'tab', 'items', 'paged' ) );
	}

	public function page_seo_title_fix(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 50;
		$search   = sanitize_text_field( $_GET['s'] ?? '' );
		$filter   = sanitize_key( $_GET['filter'] ?? '' );

		$args = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( $search ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$posts = $query->posts;

		// Filtre: sadece SEO title'ı post_title ile aynı olanları göster
		if ( $filter === 'missing' ) {
			$posts = array_values( array_filter( $posts, function ( $post ) {
				$seo_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
				return empty( $seo_title ) || $seo_title === $post->post_title;
			} ) );
		}

		$this->render_template( 'seo-title-fix', [
			'posts'    => $posts,
			'total'    => $query->found_posts,
			'paged'    => $paged,
			'per_page' => $per_page,
			'search'   => $search,
			'filter'   => $filter,
		] );
	}

	public function page_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$this->render_template( 'settings', [
			'settings' => $this->settings,
			'models'   => $this->settings->get_available_models(),
			'tones'    => $this->settings->get_available_tones(),
		] );
	}

	public function page_github(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		$updater  = new AISEO_Github_Updater();
		$settings = $updater->get_settings();
		$has_token = ! empty( $settings['token'] );
		$saved    = isset( $_GET['saved'] );
		$update   = sanitize_text_field( wp_unslash( $_GET['update'] ?? '' ) );
		$error    = sanitize_text_field( wp_unslash( $_GET['update_error'] ?? '' ) );
		$last     = get_option( 'aiseo_last_update', '-' );
		$sha      = substr( (string) get_option( 'aiseo_last_update_sha', '' ), 0, 7 );

		$this->render_template(
			'github',
			compact( 'settings', 'has_token', 'saved', 'update', 'error', 'last', 'sha' )
		);
	}

	public function page_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$filters = [
			'operation_type' => sanitize_text_field( $_GET['operation'] ?? '' ),
			'status'         => sanitize_text_field( $_GET['status'] ?? '' ),
			'date_from'      => sanitize_text_field( $_GET['date_from'] ?? '' ),
			'date_to'        => sanitize_text_field( $_GET['date_to'] ?? '' ),
		];
		$logs = $this->logger->get_ai_logs( array_filter( $filters ), 25, $paged );
		$this->render_template( 'logs', [ 'logs' => $logs, 'filters' => $filters ] );
	}

	public function add_seo_score_column( array $columns ): array {
		$columns['aiseo_score'] = __( 'SEO Skoru', 'ai-seo-editor' );
		return $columns;
	}

	public function render_seo_score_column( string $column, int $post_id ): void {
		if ( $column !== 'aiseo_score' ) {
			return;
		}
		$score = (int) get_post_meta( $post_id, '_aiseo_seo_score', true );
		if ( $score > 0 ) {
			echo wp_kses_post( aiseo_score_badge( $score ) );
		} else {
			echo '<span class="aiseo-badge aiseo-badge--none">' . esc_html__( 'Analiz Yok', 'ai-seo-editor' ) . '</span>';
		}
	}

	private function render_template( string $template, array $vars = [] ): void {
		$file = AISEO_PLUGIN_DIR . 'admin/templates/' . $template . '.php';
		if ( ! file_exists( $file ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( "Template not found: $template" ) . '</p></div>';
			return;
		}
		extract( $vars, EXTR_SKIP );
		include $file;
	}
}
