<?php
/**
 * Plugin Name:       AI SEO Editor & Article Generator
 * Plugin URI:        https://example.com/ai-seo-editor
 * Description:       WordPress yazılarını analiz eden, Yoast uyumuna yaklaştıran, okunabilirliği artıran ve AI ile SEO içerik üreten profesyonel editör.
 * Version:           1.0.14
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            AI SEO Editor
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-seo-editor
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AISEO_VERSION', '1.0.14' );
define( 'AISEO_PLUGIN_FILE', __FILE__ );
define( 'AISEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AISEO_OPTION_SETTINGS', 'aiseo_settings' );
define( 'AISEO_ENCRYPTION_KEY_OPTION', 'aiseo_encryption_key' );
define( 'AISEO_MIN_PHP_VERSION', '8.0' );
define( 'AISEO_MIN_WP_VERSION', '6.0' );

spl_autoload_register( function ( string $class ): void {
	if ( strpos( $class, 'AISEO_' ) !== 0 ) {
		return;
	}
	$relative = strtolower( str_replace( '_', '-', substr( $class, 6 ) ) );
	$file     = AISEO_PLUGIN_DIR . 'includes/class-' . $relative . '.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

require_once AISEO_PLUGIN_DIR . 'includes/helpers.php';

register_activation_hook( __FILE__, [ 'AISEO_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AISEO_Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', function (): void {
	load_plugin_textdomain( 'ai-seo-editor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	AISEO_Plugin::get_instance()->init();
} );
