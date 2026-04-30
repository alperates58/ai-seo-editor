<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Activator {

	public static function activate(): void {
		self::check_php_version();
		self::check_wp_version();
		self::generate_encryption_key();
		self::create_tables();
		self::set_default_options();
		flush_rewrite_rules();
	}

	private static function check_php_version(): void {
		if ( version_compare( PHP_VERSION, AISEO_MIN_PHP_VERSION, '<' ) ) {
			deactivate_plugins( plugin_basename( AISEO_PLUGIN_FILE ) );
			wp_die(
				sprintf(
					/* translators: 1: required PHP version 2: current PHP version */
					esc_html__( 'AI SEO Editor requires PHP %1$s or higher. Your current PHP version is %2$s.', 'ai-seo-editor' ),
					AISEO_MIN_PHP_VERSION,
					PHP_VERSION
				),
				esc_html__( 'Plugin Activation Error', 'ai-seo-editor' ),
				[ 'back_link' => true ]
			);
		}
	}

	private static function check_wp_version(): void {
		global $wp_version;
		if ( version_compare( $wp_version, AISEO_MIN_WP_VERSION, '<' ) ) {
			deactivate_plugins( plugin_basename( AISEO_PLUGIN_FILE ) );
			wp_die(
				sprintf(
					/* translators: 1: required WP version 2: current WP version */
					esc_html__( 'AI SEO Editor requires WordPress %1$s or higher. Your current version is %2$s.', 'ai-seo-editor' ),
					AISEO_MIN_WP_VERSION,
					$wp_version
				),
				esc_html__( 'Plugin Activation Error', 'ai-seo-editor' ),
				[ 'back_link' => true ]
			);
		}
	}

	private static function generate_encryption_key(): void {
		if ( ! get_option( AISEO_ENCRYPTION_KEY_OPTION ) ) {
			update_option( AISEO_ENCRYPTION_KEY_OPTION, wp_generate_password( 64, true, true ), false );
		}
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql_analysis_logs = "CREATE TABLE {$wpdb->prefix}aiseo_analysis_logs (
			id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id           BIGINT(20) UNSIGNED NOT NULL,
			focus_keyword     VARCHAR(255) NOT NULL DEFAULT '',
			seo_score         TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			readability_score TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			word_count        INT(11) UNSIGNED NOT NULL DEFAULT 0,
			keyword_density   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
			criteria_data     LONGTEXT NOT NULL,
			issues_summary    TEXT NOT NULL,
			analyzed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			cache_hash        VARCHAR(64) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY idx_post_id (post_id),
			KEY idx_seo_score (seo_score),
			KEY idx_analyzed_at (analyzed_at),
			UNIQUE KEY uq_post_cache (post_id, cache_hash)
		) $charset_collate;";

		$sql_ai_logs = "CREATE TABLE {$wpdb->prefix}aiseo_ai_logs (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id        BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			post_id        BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			operation_type VARCHAR(100) NOT NULL,
			model          VARCHAR(100) NOT NULL,
			input_tokens   INT(11) UNSIGNED NOT NULL DEFAULT 0,
			output_tokens  INT(11) UNSIGNED NOT NULL DEFAULT 0,
			total_tokens   INT(11) UNSIGNED NOT NULL DEFAULT 0,
			status         VARCHAR(20) NOT NULL DEFAULT 'success',
			error_message  TEXT DEFAULT NULL,
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_id (user_id),
			KEY idx_post_id (post_id),
			KEY idx_created_at (created_at),
			KEY idx_monthly (created_at, total_tokens)
		) $charset_collate;";

		$sql_link_suggestions = "CREATE TABLE {$wpdb->prefix}aiseo_internal_link_suggestions (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_post_id   BIGINT(20) UNSIGNED NOT NULL,
			target_post_id   BIGINT(20) UNSIGNED NOT NULL,
			anchor_text      VARCHAR(500) NOT NULL DEFAULT '',
			context_snippet  TEXT NOT NULL DEFAULT '',
			similarity_score DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
			status           VARCHAR(20) NOT NULL DEFAULT 'pending',
			computed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			reviewed_by      BIGINT(20) UNSIGNED DEFAULT NULL,
			reviewed_at      DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY idx_source (source_post_id),
			KEY idx_target (target_post_id),
			KEY idx_status (status),
			UNIQUE KEY uq_pair (source_post_id, target_post_id, anchor_text(100))
		) $charset_collate;";

		dbDelta( $sql_analysis_logs );
		dbDelta( $sql_ai_logs );
		dbDelta( $sql_link_suggestions );

		update_option( 'aiseo_db_version', AISEO_VERSION );
	}

	private static function set_default_options(): void {
		if ( ! get_option( AISEO_OPTION_SETTINGS ) ) {
			update_option(
				AISEO_OPTION_SETTINGS,
				[
					'openai_api_key'       => '',
					'openai_model'         => 'gpt-4o-mini',
					'quality_mode'         => 'balanced',
					'max_tokens'           => 2000,
					'default_language'     => 'tr',
					'default_tone'         => 'professional',
					'monthly_token_limit'  => 500000,
					'enable_logging'       => true,
					'enable_yoast_sync'    => false,
					'analysis_cache_ttl'   => 86400,
					'daily_limit'          => 100,
				],
				false
			);
		}
	}
}
