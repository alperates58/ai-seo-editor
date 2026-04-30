<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Yoast_Integration {

	private array $field_map = [
		'focus_keyword'    => '_yoast_wpseo_focuskw',
		'meta_description' => '_yoast_wpseo_metadesc',
		'seo_title'        => '_yoast_wpseo_title',
	];

	public function is_yoast_active(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	public function get_focus_keyword( int $post_id ): string {
		if ( $this->is_yoast_active() ) {
			$kw = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			if ( ! empty( $kw ) ) {
				return sanitize_text_field( $kw );
			}
		}
		return sanitize_text_field( (string) get_post_meta( $post_id, '_aiseo_focus_keyword', true ) );
	}

	public function get_meta_description( int $post_id ): string {
		if ( $this->is_yoast_active() ) {
			$meta = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			if ( ! empty( $meta ) ) {
				return sanitize_textarea_field( $meta );
			}
		}
		return sanitize_textarea_field( (string) get_post_meta( $post_id, '_aiseo_meta_description', true ) );
	}

	public function get_seo_title( int $post_id ): string {
		if ( $this->is_yoast_active() ) {
			$title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
			if ( ! empty( $title ) ) {
				return sanitize_text_field( $title );
			}
		}
		return sanitize_text_field( (string) get_the_title( $post_id ) );
	}

	public function set_focus_keyword( int $post_id, string $keyword ): void {
		update_post_meta( $post_id, '_aiseo_focus_keyword', sanitize_text_field( $keyword ) );
		if ( $this->is_yoast_active() && $this->is_sync_enabled() ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $keyword ) );
		}
	}

	public function set_meta_description( int $post_id, string $meta ): void {
		update_post_meta( $post_id, '_aiseo_meta_description', sanitize_textarea_field( $meta ) );
		if ( $this->is_yoast_active() && $this->is_sync_enabled() ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $meta ) );
		}
	}

	public function set_seo_title( int $post_id, string $title ): void {
		if ( $this->is_yoast_active() && $this->is_sync_enabled() ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $title ) );
		}
	}

	public function get_yoast_field_map(): array {
		return $this->field_map;
	}

	private function is_sync_enabled(): bool {
		return (bool) AISEO_Plugin::get_instance()->get_settings()->get( 'enable_yoast_sync' );
	}
}
