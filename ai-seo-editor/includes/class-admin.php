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
			__( 'Dashboard', 'ai-seo-editor' ),
			__( 'Dashboard', 'ai-seo-editor' ),
			'manage_options',
			$this->menu_slug,
			[ $this, 'page_dashboard' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Yazı Analizi', 'ai-seo-editor' ),
			__( 'Yazı Analizi', 'ai-seo-editor' ),
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
			__( 'AI Makale Yaz', 'ai-seo-editor' ),
			__( 'AI Makale Yaz', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-generator',
			[ $this, 'page_article_generator' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'İç Link Önerileri', 'ai-seo-editor' ),
			__( 'İç Link Önerileri', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-links',
			[ $this, 'page_internal_links' ]
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
			__( 'GitHub Güncelleme', 'ai-seo-editor' ),
			__( 'GitHub Güncelleme', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-github',
			[ $this, 'page_github' ]
		);

		add_submenu_page(
			$this->menu_slug,
			__( 'Kullanım / Loglar', 'ai-seo-editor' ),
			__( 'Kullanım / Loglar', 'ai-seo-editor' ),
			'manage_options',
			'aiseo-logs',
			[ $this, 'page_logs' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		$aiseo_pages = [
			'toplevel_page_aiseo-dashboard',
			'ai-seo-editor_page_aiseo-posts',
			'ai-seo-editor_page_aiseo-bulk',
			'ai-seo-editor_page_aiseo-generator',
			'ai-seo-editor_page_aiseo-links',
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
			],
		] );
	}

	public function page_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$stats = $this->logger->get_dashboard_stats();
		$this->render_template( 'dashboard', [ 'stats' => $stats ] );
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
		$posts = get_posts( [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		$this->render_template( 'bulk-analysis', [ 'posts' => $posts ] );
	}

	public function page_article_generator(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}
		$categories = get_categories( [ 'hide_empty' => false ] );
		$settings   = $this->settings;
		$this->render_template( 'article-generator', compact( 'categories', 'settings' ) );
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
		$saved    = isset( $_GET['saved'] );
		$update   = sanitize_text_field( wp_unslash( $_GET['update'] ?? '' ) );
		$error    = sanitize_text_field( wp_unslash( $_GET['update_error'] ?? '' ) );
		$last     = get_option( 'aiseo_last_update', '-' );
		$sha      = substr( (string) get_option( 'aiseo_last_update_sha', '' ), 0, 7 );

		$this->render_template(
			'github',
			compact( 'settings', 'saved', 'update', 'error', 'last', 'sha' )
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
