<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
