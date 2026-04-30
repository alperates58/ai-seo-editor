<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Article_Generator {

	private AISEO_OpenAI_Client $client;
	private AISEO_Logger $logger;

	public function __construct( AISEO_OpenAI_Client $client, AISEO_Logger $logger ) {
		$this->client = $client;
		$this->logger = $logger;
	}

	public function generate( array $params ): array {
		$model = AISEO_Plugin::get_instance()->get_settings()->get( 'openai_model' );

		try {
			$result = $this->client->generate_full_article( $params );

			if ( isset( $result['error'] ) ) {
				return [ 'success' => false, 'error' => __( 'Makale üretilemedi.', 'ai-seo-editor' ) ];
			}

			$content_html = $this->build_html( $result );
			$word_count   = aiseo_count_words( $content_html );
			$tokens       = $result['tokens_used'] ?? 0;

			$this->logger->log_ai_operation( 0, 'generate_article', $model, 0, $tokens, 'success' );

			return [
				'success'          => true,
				'title'            => sanitize_text_field( $result['title'] ?? '' ),
				'meta_description' => sanitize_textarea_field( $result['meta_description'] ?? '' ),
				'focus_keyword'    => sanitize_text_field( $result['focus_keyword'] ?? $params['keyword'] ?? '' ),
				'content'          => $content_html,
				'word_count'       => $word_count,
				'tokens_used'      => $tokens,
				'suggested_tags'   => array_map( 'sanitize_text_field', $result['suggested_tags'] ?? [] ),
				'raw'              => $result,
			];

		} catch ( Throwable $e ) {
			$this->logger->log_ai_operation( 0, 'generate_article', $model, 0, 0, 'error', $e->getMessage() );
			return [ 'success' => false, 'error' => __( 'AI isteği başarısız. Lütfen tekrar deneyin.', 'ai-seo-editor' ) ];
		}
	}

	public function create_draft( array $generation_result, array $params ): int {
		$content    = $this->clean_generated_html( $generation_result['content'] ?? '' );
		$title      = sanitize_text_field( $generation_result['title'] ?? ( $params['title'] ?? __( 'Taslak Makale', 'ai-seo-editor' ) ) );
		$keyword    = sanitize_text_field( $generation_result['focus_keyword'] ?? '' );
		$meta_desc  = sanitize_textarea_field( $generation_result['meta_description'] ?? '' );
		$category   = absint( $params['category'] ?? 0 );

		$post_data = [
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		];

		if ( $category > 0 ) {
			$post_data['post_category'] = [ $category ];
		}

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_aiseo_focus_keyword', $keyword );
		update_post_meta( $post_id, '_aiseo_meta_description', $meta_desc );

		$yoast = new AISEO_Yoast_Integration();
		if ( $yoast->is_yoast_active() ) {
			$yoast->set_focus_keyword( $post_id, $keyword );
			$yoast->set_meta_description( $post_id, $meta_desc );
		}

		if ( ! empty( $generation_result['suggested_tags'] ) ) {
			$tags = array_map( 'sanitize_text_field', $generation_result['suggested_tags'] );
			wp_set_post_tags( $post_id, $tags );
		}

		return $post_id;
	}

	private function build_html( array $result ): string {
		$html    = '';
		$content = $result['content'] ?? [];

		if ( is_string( $content ) ) {
			return $this->clean_generated_html( $content );
		}

		if ( ! empty( $content['introduction'] ) ) {
			$html .= $this->clean_generated_html( $content['introduction'] ) . "\n\n";
		}

		if ( ! empty( $content['sections'] ) ) {
			foreach ( $content['sections'] as $section ) {
				if ( ! empty( $section['heading'] ) ) {
					$html .= '<h2>' . esc_html( $section['heading'] ) . '</h2>' . "\n";
				}
				if ( ! empty( $section['content'] ) ) {
					$html .= $this->clean_generated_html( $section['content'] ) . "\n\n";
				}
				if ( ! empty( $section['subsections'] ) ) {
					foreach ( $section['subsections'] as $sub ) {
						if ( ! empty( $sub['heading'] ) ) {
							$html .= '<h3>' . esc_html( $sub['heading'] ) . '</h3>' . "\n";
						}
						if ( ! empty( $sub['content'] ) ) {
							$html .= $this->clean_generated_html( $sub['content'] ) . "\n\n";
						}
					}
				}
			}
		}

		if ( ! empty( $content['conclusion'] ) ) {
			$html .= '<h2>' . esc_html__( 'Sonuç', 'ai-seo-editor' ) . '</h2>' . "\n";
			$html .= $this->clean_generated_html( $content['conclusion'] ) . "\n\n";
		}

		if ( ! empty( $content['faq'] ) ) {
			$html .= '<h2>' . esc_html__( 'Sıkça Sorulan Sorular', 'ai-seo-editor' ) . '</h2>' . "\n";
			foreach ( $content['faq'] as $faq ) {
				$html .= '<h3>' . esc_html( $faq['question'] ?? '' ) . '</h3>' . "\n";
				$html .= '<p>' . esc_html( $faq['answer'] ?? '' ) . '</p>' . "\n";
			}
		}

		return $html;
	}

	private function clean_generated_html( string $html ): string {
		$html = trim( $html );
		$html = preg_replace( '/^\s*```(?:html|HTML)?\s*/', '', $html );
		$html = preg_replace( '/\s*```\s*$/', '', $html );
		$html = preg_replace( '/^\s*(?:<!doctype\s+html[^>]*>|<html[^>]*>|<body[^>]*>)/i', '', $html );
		$html = preg_replace( '/(?:<\/body>|<\/html>)\s*$/i', '', $html );

		return wp_kses_post( trim( $html ) );
	}
}
