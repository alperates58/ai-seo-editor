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
		$current = $this->get_settings();
		$token   = sanitize_text_field( wp_unslash( $data['token'] ?? '' ) );
		if ( $token === '' || str_contains( $token, '*' ) ) {
			$token = $current['token'] ?? '';
		}

		update_option(
			self::OPTION_KEY,
			[
				'repo'   => $this->normalize_repo( sanitize_text_field( wp_unslash( $data['repo'] ?? '' ) ) ),
				'branch' => sanitize_text_field( wp_unslash( $data['branch'] ?? 'main' ) ),
				'token'  => $token,
			]
		);
	}

	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->die_forbidden();
		}

		if ( ! check_admin_referer( 'aiseo_save_github_settings', '_wpnonce', false ) ) {
			$this->die_bad_nonce();
		}

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

	public function get_remote_version(): string|WP_Error|null {
		$settings = $this->get_settings();
		$validation = $this->validate_settings( $settings );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$url      = $this->build_github_api_url( $settings['repo'], 'commits/' . rawurlencode( $settings['branch'] ) );
		$response = wp_remote_get( $url, $this->get_request_args( 20 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aiseo_github_http', sprintf( __( 'GitHub bağlantısı başarısız: %s', 'ai-seo-editor' ), $response->get_error_message() ) );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'aiseo_github_response', $this->github_error_message( $response, __( 'GitHub sürümü okunamadı.', 'ai-seo-editor' ) ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? ( $body['sha'] ?? null ) : null;
	}

	public function ajax_check_version(): void {
		if ( ! check_ajax_referer( 'aiseo_github_version', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Güvenlik doğrulaması başarısız.', 'ai-seo-editor' ) ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'ai-seo-editor' ) ], 403 );
		}

		$sha = $this->get_remote_version();
		if ( is_wp_error( $sha ) ) {
			wp_send_json_error( [ 'message' => $sha->get_error_message() ] );
		}

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
			$this->die_forbidden();
		}

		if ( ! check_admin_referer( 'aiseo_update_from_github', '_wpnonce', false ) ) {
			$this->die_bad_nonce();
		}

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
		$validation = $this->validate_settings( $settings );
		if ( is_wp_error( $validation ) ) {
			return $validation->get_error_message();
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

		$copy_result = $this->copy_directory_contents( $source_dir, $destination );
		if ( is_wp_error( $copy_result ) ) {
			return $copy_result->get_error_message();
		}

		if ( is_dir( $extracted_dir ) && $extracted_dir !== $source_dir ) {
			$wp_filesystem->delete( $extracted_dir, true );
		}

		$remote_sha = $this->get_remote_version();

		update_option( 'aiseo_last_update', current_time( 'mysql' ) );
		update_option( 'aiseo_last_update_version', (string) time() );
		if ( is_string( $remote_sha ) && $remote_sha !== '' ) {
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
		$validation = $this->validate_settings( $settings );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$zip_url = $this->build_github_web_url( $settings['repo'], 'archive/refs/heads/' . rawurlencode( $settings['branch'] ) . '.zip' );

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
			return new WP_Error( 'aiseo_download_failed', $this->github_error_message( $response, __( 'GitHub ZIP indirilemedi.', 'ai-seo-editor' ) ) );
		}

		return $tmp_file;
	}

	private function validate_settings( array $settings ) {
		if ( empty( $settings['repo'] ) || empty( $settings['branch'] ) ) {
			return new WP_Error( 'aiseo_github_missing_settings', __( 'Repo ve branch bilgisi zorunludur.', 'ai-seo-editor' ) );
		}

		if ( ! preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', (string) $settings['repo'] ) ) {
			return new WP_Error( 'aiseo_github_invalid_repo', __( 'Repo formatı owner/repository şeklinde olmalıdır.', 'ai-seo-editor' ) );
		}

		return true;
	}

	private function normalize_repo( string $repo ): string {
		$repo = trim( $repo );
		$repo = preg_replace( '#^https?://github\.com/#i', '', $repo );
		$repo = trim( (string) $repo, " \t\n\r\0\x0B/" );
		$parts = array_slice( explode( '/', $repo ), 0, 2 );
		$parts = array_map(
			static fn( string $part ): string => (string) preg_replace( '/[^A-Za-z0-9_.-]/', '', $part ),
			$parts
		);
		return implode( '/', $parts );
	}

	private function build_github_api_url( string $repo, string $path ): string {
		[ $owner, $name ] = explode( '/', $repo, 2 );
		return sprintf( 'https://api.github.com/repos/%s/%s/%s', rawurlencode( $owner ), rawurlencode( $name ), ltrim( $path, '/' ) );
	}

	private function build_github_web_url( string $repo, string $path ): string {
		[ $owner, $name ] = explode( '/', $repo, 2 );
		return sprintf( 'https://github.com/%s/%s/%s', rawurlencode( $owner ), rawurlencode( $name ), ltrim( $path, '/' ) );
	}

	private function github_error_message( array $response, string $fallback ): string {
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$message = is_array( $body ) && ! empty( $body['message'] ) ? sanitize_text_field( $body['message'] ) : $fallback;

		if ( in_array( $code, [ 401, 403 ], true ) ) {
			return __( 'GitHub erişimi reddetti. Token yetkisini veya rate limit durumunu kontrol edin.', 'ai-seo-editor' );
		}
		if ( 404 === $code ) {
			return __( 'GitHub repo veya branch bulunamadı. Repo/branch bilgisini kontrol edin.', 'ai-seo-editor' );
		}

		return sprintf( '%s HTTP %d: %s', $fallback, $code, $message );
	}

	private function copy_directory_contents( string $source, string $destination ) {
		if ( ! is_dir( $source ) ) {
			return new WP_Error( 'aiseo_copy_missing_source', __( 'Güncelleme paketi içinde kaynak klasör bulunamadı.', 'ai-seo-editor' ) );
		}

		if ( ! is_dir( $destination ) && ! wp_mkdir_p( $destination ) ) {
			return new WP_Error( 'aiseo_copy_destination', __( 'Eklenti klasörü yazılabilir değil.', 'ai-seo-editor' ) );
		}

		$items = scandir( $source );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'aiseo_copy_read', __( 'Güncelleme paketi okunamadı.', 'ai-seo-editor' ) );
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$from = trailingslashit( $source ) . $item;
			$to   = trailingslashit( $destination ) . $item;

			if ( is_dir( $from ) ) {
				$result = $this->copy_directory_contents( $from, $to );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				continue;
			}

			if ( ! @copy( $from, $to ) ) {
				return new WP_Error( 'aiseo_copy_file', sprintf( __( 'Dosya kopyalanamadı: %s', 'ai-seo-editor' ), $item ) );
			}
		}

		return true;
	}

	private function die_forbidden(): void {
		wp_die(
			esc_html__( 'Bu işlem için yönetici yetkisi gerekir.', 'ai-seo-editor' ),
			esc_html__( 'Yetkisiz işlem', 'ai-seo-editor' ),
			[ 'response' => 403 ]
		);
	}

	private function die_bad_nonce(): void {
		wp_die(
			esc_html__( 'Güvenlik doğrulaması başarısız oldu. Sayfayı yenileyip tekrar deneyin.', 'ai-seo-editor' ),
			esc_html__( 'Güvenlik doğrulaması', 'ai-seo-editor' ),
			[ 'response' => 403 ]
		);
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
