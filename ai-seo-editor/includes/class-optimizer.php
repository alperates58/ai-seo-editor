<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Optimizer {

	private AISEO_OpenAI_Client $client;
	private AISEO_Logger $logger;
	private AISEO_Yoast_Integration $yoast;

	public function __construct( AISEO_OpenAI_Client $client, AISEO_Logger $logger ) {
		$this->client = $client;
		$this->logger = $logger;
		$this->yoast  = new AISEO_Yoast_Integration();
	}

	public function run( int $post_id, string $operation ): array {
		$settings = AISEO_Plugin::get_instance()->get_settings();
		$keyword  = $this->yoast->get_focus_keyword( $post_id );
		$tone     = $settings->get( 'default_tone' );
		$model    = $settings->get( 'openai_model' );

		try {
			$result = match ( $operation ) {
				'optimize_title'          => $this->client->optimize_title( $post_id, $keyword ),
				'optimize_meta'           => $this->client->optimize_meta( $post_id, $keyword ),
				'improve_intro'           => $this->client->improve_intro( $post_id, $keyword, $tone ),
				'improve_structure'       => $this->client->improve_structure( $post_id, $keyword ),
				'improve_readability'     => $this->client->improve_readability( $post_id, $tone ),
				'improve_keyword_density' => $this->client->improve_keyword_density( $post_id, $keyword ),
				'add_faq'                 => $this->client->generate_faq( $post_id, $keyword ),
				'improve_conclusion'      => $this->client->improve_conclusion( $post_id, $keyword ),
				'add_internal_links'      => $this->get_internal_link_suggestions( $post_id ),
				'optimize_image_alts'     => $this->client->optimize_image_alts( $post_id, $keyword ),
				default                   => throw new InvalidArgumentException( "Bilinmeyen işlem: $operation" ),
			};

			$tokens = $result['tokens_used'] ?? 0;

			$this->logger->log_ai_operation(
				$post_id, $operation, $model, 0, $tokens, 'success'
			);

			return array_merge( $result, [
				'success'    => true,
				'operation'  => $operation,
				'post_id'    => $post_id,
				'tokens_used' => $tokens,
			] );

		} catch ( Throwable $e ) {
			$this->logger->log_ai_operation(
				$post_id, $operation, $model, 0, 0, 'error', $e->getMessage()
			);

			return [
				'success'   => false,
				'operation' => $operation,
				'error'     => __( 'AI isteği başarısız. Lütfen tekrar deneyin.', 'ai-seo-editor' ),
			];
		}
	}

	public function apply( int $post_id, string $operation, string $new_value, string $field, string $meta_key = '' ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$this->create_revision( $post_id );

		switch ( $field ) {
			case 'post_title':
				return (bool) wp_update_post( [
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $new_value ),
				] );

			case 'post_content':
				return (bool) wp_update_post( [
					'ID'           => $post_id,
					'post_content' => wp_kses_post( $new_value ),
				] );

			case 'append_content':
				$updated = $post->post_content . "\n\n" . wp_kses_post( $new_value );
				return (bool) wp_update_post( [
					'ID'           => $post_id,
					'post_content' => $updated,
				] );

			case 'meta':
				update_post_meta( $post_id, sanitize_key( $meta_key ), sanitize_textarea_field( $new_value ) );
				if ( $meta_key === '_aiseo_meta_description' ) {
					$this->yoast->set_meta_description( $post_id, $new_value );
				}
				return true;

			case 'intro':
				$content    = $post->post_content;
				$new_content = preg_replace(
					'/<p[^>]*>.*?<\/p>/is',
					'<p>' . esc_html( $new_value ) . '</p>',
					$content,
					1
				);
				if ( $new_content === $content ) {
					$new_content = '<p>' . esc_html( $new_value ) . '</p>' . "\n\n" . $content;
				}
				return (bool) wp_update_post( [
					'ID'           => $post_id,
					'post_content' => wp_kses_post( $new_content ),
				] );

			default:
				return false;
		}
	}

	private function create_revision( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post && post_type_supports( $post->post_type, 'revisions' ) ) {
			wp_save_post_revision( $post_id );
		}
	}

	private function get_internal_link_suggestions( int $post_id ): array {
		$linker = new AISEO_Internal_Linker( $this->client, $this->logger );
		$suggestions = $linker->find_suggestions( $post_id );

		$html = '';
		if ( ! empty( $suggestions ) ) {
			$html = '<p><strong>' . esc_html__( 'Önerilen İç Linkler:', 'ai-seo-editor' ) . '</strong></p><ul>';
			foreach ( $suggestions as $s ) {
				$html .= '<li><a href="' . esc_url( $s['target_url'] ) . '">' . esc_html( $s['anchor_text'] ) . '</a> → ' . esc_html( $s['target_title'] ) . ' (Alaka: ' . esc_html( round( $s['similarity_score'] * 100 ) ) . '%)</li>';
			}
			$html .= '</ul>';
		}

		return [
			'before'      => '',
			'after'       => $html,
			'field'       => 'internal_links',
			'suggestions' => $suggestions,
			'tokens_used' => 0,
		];
	}
}
