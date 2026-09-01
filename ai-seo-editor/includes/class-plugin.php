<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Plugin {

	private static ?AISEO_Plugin $instance = null;

	private AISEO_Settings $settings;
	private AISEO_Logger $logger;

	private function __construct() {}

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		$this->settings = new AISEO_Settings();
		$this->logger   = new AISEO_Logger();

		if ( is_admin() ) {
			$github_updater = new AISEO_Github_Updater();
			$github_updater->init();

			$admin = new AISEO_Admin( $this->settings, $this->logger );
			$admin->init();
		}

		$auto_publisher = new AISEO_Auto_Publisher( $this->settings, $this->logger );
		$auto_publisher->init();

		$rest = new AISEO_Rest_Controller( $this->settings, $this->logger );
		add_action( 'rest_api_init', [ $rest, 'register_routes' ] );

		add_action( 'save_post', [ $this, 'on_save_post' ], 10, 1 );
	}

	public function get_settings(): AISEO_Settings {
		return $this->settings;
	}

	public function get_logger(): AISEO_Logger {
		return $this->logger;
	}

	public function on_save_post( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_aiseo_meta_description'] ) ) {
			$meta = sanitize_textarea_field( wp_unslash( $_POST['_aiseo_meta_description'] ) );
			if ( $meta !== '' ) {
				update_post_meta( $post_id, '_aiseo_meta_description', $meta );
				$yoast = new AISEO_Yoast_Integration();
				if ( $yoast->is_yoast_active() ) {
					$yoast->set_meta_description( $post_id, $meta );
				}
			}
		} elseif ( isset( $_POST['yoast_wpseo_metadesc'] ) ) {
			$meta = sanitize_textarea_field( wp_unslash( $_POST['yoast_wpseo_metadesc'] ) );
			if ( $meta !== '' ) {
				update_post_meta( $post_id, '_aiseo_meta_description', $meta );
			}
		}

		$this->logger->invalidate_cache( $post_id );
	}
}
