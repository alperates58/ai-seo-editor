<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aiseo_analysis_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aiseo_ai_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aiseo_internal_link_suggestions" );

delete_option( 'aiseo_settings' );
delete_option( 'aiseo_encryption_key' );
delete_option( 'aiseo_db_version' );

$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aiseo_%'" );
