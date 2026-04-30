<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Github_Updater {

	private const OPTION_KEY = 'aiseo_github_settings';

	public function init(): void {
		add_action( 'admin_post_aiseo_save_github_settings', [ $this, 'handle_save_settings' ] );
		add_action( 'admin_post_aiseo_update_from_github', [ $this, 'handle_update' ] );
		add_action( 'wp_ajax_aiseo_check_github_version', [ $this, 'ajax_check_version' ] );
	}

	public function get_settings(): array {
		return wp_parse_args(
			get_option( self::OPTION_KEY, [] ),
			[
				'repo'   => 'alperates58/ai-seo-editor',
				'branch' => 'main',
				'token'  => '',
			]
		);
	}

	public function save_settings( array $data ): void {
		update_option(
			self::OPTION_KEY,
			[
				'repo'   => sanitize_text_field( wp_unslash( $data['repo'] ?? '' ) ),
				'branch' => sanitize_text_field( wp_unslash( $data['branch'] ?? 'main' ) ),
				'token'  => sanitize_text_field( wp_unslash( $data['token'] ?? '' ) ),
			]
		);
	}

	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		check_admin_referer( 'aiseo_save_github_settings' );
		$this->save_settings( $_POST );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'  => 'aiseo-github',
					'saved' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function get_remote_version(): ?string {
		$settings = $this->get_settings();
		if ( empty( $settings['repo'] ) || empty( $settings['branch'] ) ) {
			return null;
		}

		$url      = sprintf( 'https://api.github.com/repos/%s/commits/%s', rawurlencode( $settings['repo'] ), rawurlencode( $settings['branch'] ) );
		$url      = str_replace( '%2F', '/', $url );
		$response = wp_remote_get( $url, $this->get_request_args( 20 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? ( $body['sha'] ?? null ) : null;
	}

	public function ajax_check_version(): void {
		if ( ! check_ajax_referer( 'wp_rest', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Güvenlik doğrulaması başarısız.', 'ai-seo-editor' ) ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'ai-seo-editor' ) ], 403 );
		}

		$sha = $this->get_remote_version();
		if ( ! $sha ) {
			wp_send_json_error( [ 'message' => __( 'GitHub sürümü okunamadı. Repo, branch veya token bilgisini kontrol edin.', 'ai-seo-editor' ) ] );
		}

		wp_send_json_success(
			[
				'sha'     => substr( $sha, 0, 7 ),
				'message' => sprintf( __( 'Son commit: %s', 'ai-seo-editor' ), substr( $sha, 0, 7 ) ),
			]
		);
	}

	public function handle_update(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'ai-seo-editor' ) );
		}

		check_admin_referer( 'aiseo_update_from_github' );
		$result = $this->download_and_install();
		$args   = [ 'page' => 'aiseo-github' ];

		if ( true === $result ) {
			$args['update'] = 'success';
		} else {
			$args['update_error'] = rawurlencode( (string) $result );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function download_and_install() {
		$settings = $this->get_settings();
		if ( empty( $settings['repo'] ) || empty( $settings['branch'] ) ) {
			return __( 'Repo veya branch ayarı eksik.', 'ai-seo-editor' );
		}

		$tmp = $this->download_zip( $settings );
		if ( is_wp_error( $tmp ) ) {
			return $tmp->get_error_message();
		}

		$plugin_base = dirname( AISEO_PLUGIN_DIR );
		$destination = untrailingslashit( AISEO_PLUGIN_DIR );

		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;
		WP_Filesystem();

		$unzip = unzip_file( $tmp, $plugin_base );
		@unlink( $tmp );

		if ( is_wp_error( $unzip ) ) {
			return $unzip->get_error_message();
		}

		$repo_name     = basename( $settings['repo'] );
		$branch_suffix = str_replace( '/', '-', $settings['branch'] );
		$extracted_dir = trailingslashit( $plugin_base ) . $repo_name . '-' . $branch_suffix;

		if ( ! is_dir( $extracted_dir ) ) {
			return __( 'İndirilen paket açıldı ama beklenen klasör bulunamadı.', 'ai-seo-editor' );
		}

		$plugin_slug = basename( $destination );
		$source_dir  = trailingslashit( $extracted_dir ) . $plugin_slug;
		if ( ! is_dir( $source_dir ) ) {
			$source_dir = $extracted_dir;
		}

		if ( ! $wp_filesystem->delete( $destination, true ) ) {
			return __( 'Mevcut eklenti klasörü silinemedi.', 'ai-seo-editor' );
		}

		if ( ! @rename( $source_dir, $destination ) ) {
			return __( 'Yeni eklenti klasörü yerine taşınamadı.', 'ai-seo-editor' );
		}

		if ( is_dir( $extracted_dir ) && $extracted_dir !== $source_dir ) {
			$wp_filesystem->delete( $extracted_dir, true );
		}

		$remote_sha = $this->get_remote_version();

		update_option( 'aiseo_last_update', current_time( 'mysql' ) );
		update_option( 'aiseo_last_update_version', (string) time() );
		if ( $remote_sha ) {
			update_option( 'aiseo_last_update_sha', $remote_sha );
		}

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( true );
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		return true;
	}

	private function download_zip( array $settings ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$zip_url = sprintf(
			'https://github.com/%s/archive/refs/heads/%s.zip',
			$settings['repo'],
			rawurlencode( $settings['branch'] )
		);

		if ( empty( $settings['token'] ) ) {
			return download_url( $zip_url, 60 );
		}

		$tmp_file = wp_tempnam( $settings['repo'] . '-' . $settings['branch'] . '.zip' );
		if ( ! $tmp_file ) {
			return new WP_Error( 'aiseo_temp_file', __( 'Geçici dosya oluşturulamadı.', 'ai-seo-editor' ) );
		}

		$args             = $this->get_request_args( 60 );
		$args['stream']   = true;
		$args['filename'] = $tmp_file;

		$response = wp_remote_get( $zip_url, $args );
		if ( is_wp_error( $response ) ) {
			@unlink( $tmp_file );
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'aiseo_download_failed', __( 'GitHub ZIP indirilemedi.', 'ai-seo-editor' ) );
		}

		return $tmp_file;
	}

	private function get_request_args( int $timeout ): array {
		$settings = $this->get_settings();
		$headers  = [
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'ai-seo-editor',
		];

		if ( ! empty( $settings['token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['token'];
		}

		return [
			'timeout' => $timeout,
			'headers' => $headers,
		];
	}
}
