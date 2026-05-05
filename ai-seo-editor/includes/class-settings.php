<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Settings {

	private array $defaults = [
		'ai_provider'         => 'openai',
		'openai_api_key'      => '',
		'openai_model'        => 'gpt-4o-mini',
		'ai_base_url'         => '',
		'quality_mode'        => 'balanced',
		'max_tokens'          => 2000,
		'default_language'    => 'tr',
		'default_tone'        => 'professional',
		'monthly_token_limit' => 500000,
		'enable_logging'      => true,
		'enable_yoast_sync'   => false,
		'analysis_cache_ttl'  => 86400,
		'daily_limit'         => 100,
	];

	private ?array $cached = null;

	public function get( string $key ): mixed {
		$all = $this->get_all();
		return $all[ $key ] ?? ( $this->defaults[ $key ] ?? null );
	}

	public function get_all(): array {
		if ( $this->cached === null ) {
			$stored       = get_option( AISEO_OPTION_SETTINGS, [] );
			$this->cached = wp_parse_args( $stored, $this->defaults );
		}
		return $this->cached;
	}

	public function save( array $new_settings ): bool {
		$current = $this->get_all();

		if ( isset( $new_settings['openai_api_key'] ) ) {
			$raw_key = sanitize_text_field( $new_settings['openai_api_key'] );
			if ( ! empty( $raw_key ) && strpos( $raw_key, '*' ) === false ) {
				$current['openai_api_key'] = $this->encrypt( $raw_key );
			}
			unset( $new_settings['openai_api_key'] );
		}

		$sanitized = $this->sanitize( $new_settings );
		$merged    = array_merge( $current, $sanitized );

		$result       = update_option( AISEO_OPTION_SETTINGS, $merged );
		$this->cached = null;
		return $result;
	}

	public function get_api_key(): string {
		$stored = $this->get( 'openai_api_key' );
		if ( empty( $stored ) ) {
			return '';
		}
		return $this->decrypt( $stored );
	}

	public function get_masked_api_key(): string {
		$key = $this->get_api_key();
		if ( empty( $key ) ) {
			return '';
		}
		$len = strlen( $key );
		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}
		return str_repeat( '*', $len - 4 ) . substr( $key, -4 );
	}

	private function encrypt( string $value ): string {
		$key    = $this->get_encryption_key();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	private function decrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}
		$decoded = base64_decode( $value, true );
		if ( $decoded === false || strlen( $decoded ) < 17 ) {
			return '';
		}
		$key    = $this->get_encryption_key();
		$iv     = substr( $decoded, 0, 16 );
		$cipher = substr( $decoded, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return $plain !== false ? $plain : '';
	}

	private function get_encryption_key(): string {
		$key = get_option( AISEO_ENCRYPTION_KEY_OPTION, '' );
		if ( empty( $key ) ) {
			$key = wp_generate_password( 64, true, true );
			update_option( AISEO_ENCRYPTION_KEY_OPTION, $key, false );
		}
		return hash( 'sha256', $key, true );
	}

	private function sanitize( array $input ): array {
		$clean = [];

		if ( isset( $input['openai_model'] ) ) {
			$allowed_models         = array_keys( $this->get_available_models() );
			$clean['openai_model']  = in_array( $input['openai_model'], $allowed_models, true )
				? $input['openai_model']
				: 'gpt-4o-mini';
		}

		if ( isset( $input['ai_provider'] ) ) {
			$allowed_provider      = [ 'openai', 'deepseek' ];
			$clean['ai_provider'] = in_array( $input['ai_provider'], $allowed_provider, true )
				? $input['ai_provider']
				: 'openai';
		}

		if ( isset( $input['ai_base_url'] ) ) {
			$clean['ai_base_url'] = esc_url_raw( trim( (string) $input['ai_base_url'] ) );
		}

		if ( isset( $input['quality_mode'] ) ) {
			$allowed               = [ 'fast', 'balanced', 'quality' ];
			$clean['quality_mode'] = in_array( $input['quality_mode'], $allowed, true )
				? $input['quality_mode']
				: 'balanced';
		}

		if ( isset( $input['max_tokens'] ) ) {
			$clean['max_tokens'] = max( 500, min( 8000, (int) $input['max_tokens'] ) );
		}

		if ( isset( $input['monthly_token_limit'] ) ) {
			$clean['monthly_token_limit'] = max( 1000, (int) $input['monthly_token_limit'] );
		}

		if ( isset( $input['daily_limit'] ) ) {
			$clean['daily_limit'] = max( 1, (int) $input['daily_limit'] );
		}

		if ( isset( $input['default_language'] ) ) {
			$clean['default_language'] = sanitize_text_field( $input['default_language'] );
		}

		if ( isset( $input['default_tone'] ) ) {
			$allowed_tones         = [ 'professional', 'casual', 'academic', 'friendly', 'formal' ];
			$clean['default_tone'] = in_array( $input['default_tone'], $allowed_tones, true )
				? $input['default_tone']
				: 'professional';
		}

		if ( isset( $input['analysis_cache_ttl'] ) ) {
			$clean['analysis_cache_ttl'] = max( 3600, (int) $input['analysis_cache_ttl'] );
		}

		foreach ( [ 'enable_logging', 'enable_yoast_sync' ] as $bool_key ) {
			if ( isset( $input[ $bool_key ] ) ) {
				$clean[ $bool_key ] = (bool) $input[ $bool_key ];
			}
		}

		return $clean;
	}

	public function get_available_models(): array {
		return [
			'gpt-4o-mini'    => 'GPT-4o Mini (Ekonomik)',
			'gpt-4o'         => 'GPT-4o (Dengeli)',
			'gpt-4-turbo'    => 'GPT-4 Turbo (Premium)',
			'gpt-3.5-turbo'  => 'GPT-3.5 Turbo (Hızlı)',
			'deepseek-chat'  => 'DeepSeek Chat',
			'deepseek-reasoner' => 'DeepSeek Reasoner',
		];
	}

	public function get_available_tones(): array {
		return [
			'professional' => __( 'Profesyonel', 'ai-seo-editor' ),
			'casual'       => __( 'Günlük / Samimi', 'ai-seo-editor' ),
			'academic'     => __( 'Akademik', 'ai-seo-editor' ),
			'friendly'     => __( 'Arkadaşça', 'ai-seo-editor' ),
			'formal'       => __( 'Resmi', 'ai-seo-editor' ),
		];
	}
}
