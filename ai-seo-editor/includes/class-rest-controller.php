<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Rest_Controller {

	private const NAMESPACE = 'aiseo/v1';

	private AISEO_Settings $settings;
	private AISEO_Logger $logger;

	public function __construct( AISEO_Settings $settings, AISEO_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/dashboard', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_dashboard' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/analyze/(?P<post_id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analysis' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run_analysis' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/optimize', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'run_optimize' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/optimize/full', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'run_full_optimize' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/optimize/apply', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'apply_optimization' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/bulk-analyze', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'bulk_analyze' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/generate', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'generate_article' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/generate/create-draft', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create_draft' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/links/(?P<post_id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_link_suggestions' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/links/(?P<post_id>\d+)/compute', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'compute_links' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/links/apply', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'apply_links' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/logs', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_logs' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/settings/test-key', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'test_api_key' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );
	}

	public function check_permissions( ?WP_REST_Request $request = null ): bool|WP_Error {
		$post_id = $request instanceof WP_REST_Request ? absint( $request->get_param( 'post_id' ) ) : 0;

		if ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		if ( ! $post_id && $request instanceof WP_REST_Request ) {
			$route = $request->get_route();
			if ( str_contains( $route, '/generate' ) && current_user_can( 'edit_posts' ) ) {
				return true;
			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'aiseo_forbidden',
				__( 'Bu işlem için yetkiniz yok.', 'ai-seo-editor' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	public function get_dashboard( WP_REST_Request $request ): WP_REST_Response {
		$stats = $this->logger->get_dashboard_stats();
		return $this->ok( $stats );
	}

	public function get_analysis( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$score  = (int) get_post_meta( $post_id, '_aiseo_seo_score', true );
		$read   = (int) get_post_meta( $post_id, '_aiseo_readability_score', true );
		$last   = get_post_meta( $post_id, '_aiseo_last_analysis', true );

		return $this->ok( [
			'post_id'            => $post_id,
			'seo_score'          => $score,
			'readability_score'  => $read,
			'last_analysis'      => $last,
		] );
	}

	public function run_analysis( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$force    = (bool) $request->get_param( 'force' );
		$analyzer = new AISEO_Analyzer();
		$result   = $analyzer->analyze( $post_id, $force );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'aiseo_analysis_error', $result['error'], [ 'status' => 500 ] );
		}

		return $this->ok( $result, __( 'Analiz tamamlandı.', 'ai-seo-editor' ) );
	}

	public function run_optimize( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id   = absint( $request->get_param( 'post_id' ) );
		$operation = sanitize_key( $request->get_param( 'operation' ) ?? '' );

		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}
		if ( empty( $operation ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'İşlem tipi belirtilmedi.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$this->check_token_budget();

		$client    = new AISEO_OpenAI_Client( $this->settings );
		$optimizer = new AISEO_Optimizer( $client, $this->logger );
		$result    = $optimizer->run( $post_id, $operation );

		if ( ! $result['success'] ) {
			return new WP_Error( 'aiseo_optimize_error', $result['error'] ?? __( 'İyileştirme başarısız.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		return $this->ok( $result, __( 'AI önerisi hazır.', 'ai-seo-editor' ) );
	}

	public function run_full_optimize( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$this->check_token_budget();

		$post      = get_post( $post_id );
		$content   = $post instanceof WP_Post ? $post->post_content : '';
		$title     = $post instanceof WP_Post ? $post->post_title : '';
		$meta      = '';
		$client    = new AISEO_OpenAI_Client( $this->settings );
		$optimizer = new AISEO_Optimizer( $client, $this->logger );
		$steps     = [];

		foreach ( [ 'optimize_title', 'optimize_meta', 'improve_readability', 'add_faq' ] as $operation ) {
			$result = $optimizer->run( $post_id, $operation );
			if ( empty( $result['success'] ) ) {
				$steps[] = [
					'operation' => $operation,
					'success'   => false,
					'error'     => $result['error'] ?? __( 'Öneri üretilemedi.', 'ai-seo-editor' ),
				];
				continue;
			}

			$field = $result['field'] ?? '';
			$after = (string) ( $result['after'] ?? '' );

			if ( 'post_title' === $field && $after !== '' ) {
				$title = sanitize_text_field( $after );
			} elseif ( 'meta' === $field && $after !== '' ) {
				$meta = sanitize_textarea_field( $after );
			} elseif ( 'post_content' === $field && $after !== '' ) {
				$content = wp_kses_post( $after );
			} elseif ( 'append_content' === $field && $after !== '' ) {
				$content .= "\n\n" . wp_kses_post( $after );
			}

			$steps[] = [
				'operation' => $operation,
				'success'   => true,
				'field'     => $field,
				'before'    => $result['before'] ?? '',
				'after'     => $after,
			];
		}

		return $this->ok(
			[
				'post_id' => $post_id,
				'title'   => $title,
				'content' => $content,
				'meta'    => $meta,
				'steps'   => $steps,
			],
			__( 'Tam düzeltme önerisi hazır.', 'ai-seo-editor' )
		);
	}

	public function apply_optimization( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id   = absint( $request->get_param( 'post_id' ) );
		$operation = sanitize_key( $request->get_param( 'operation' ) ?? '' );
		$field     = sanitize_key( $request->get_param( 'field' ) ?? '' );
		$meta_key  = sanitize_key( $request->get_param( 'meta_key' ) ?? '' );
		$new_value = wp_kses_post( $request->get_param( 'new_value' ) ?? '' );

		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}
		if ( empty( $field ) || empty( $new_value ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Alan ve yeni değer gereklidir.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$client    = new AISEO_OpenAI_Client( $this->settings );
		$optimizer = new AISEO_Optimizer( $client, $this->logger );
		$success   = $optimizer->apply( $post_id, $operation, $new_value, $field, $meta_key );

		if ( ! $success ) {
			return new WP_Error( 'aiseo_apply_error', __( 'Değişiklik uygulanamadı.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		$this->logger->invalidate_cache( $post_id );

		return $this->ok(
			[ 'post_id' => $post_id, 'field' => $field ],
			__( 'Değişiklik uygulandı. Revision oluşturuldu.', 'ai-seo-editor' )
		);
	}

	public function bulk_analyze( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_ids = array_map( 'absint', (array) ( $request->get_param( 'post_ids' ) ?? [] ) );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Post ID listesi boş.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$post_ids = array_slice( $post_ids, 0, 20 );
		$analyzer = new AISEO_Analyzer();
		$results  = [];

		foreach ( $post_ids as $post_id ) {
			if ( $this->post_exists( $post_id ) ) {
				$result    = $analyzer->analyze( $post_id );
				$results[] = [
					'post_id'           => $post_id,
					'seo_score'         => $result['seo_score'] ?? 0,
					'readability_score' => $result['readability_score'] ?? 0,
					'last_analysis'     => $result['analyzed_at'] ?? get_post_meta( $post_id, '_aiseo_last_analysis', true ),
					'success'           => ! isset( $result['error'] ),
					'error'             => $result['error'] ?? '',
				];
			}
		}

		return $this->ok( [ 'results' => $results ] );
	}

	public function generate_article( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$keyword = sanitize_text_field( $request->get_param( 'keyword' ) ?? '' );
		if ( empty( $keyword ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Anahtar kelime zorunludur.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$this->check_token_budget();

		$params = [
			'keyword'      => $keyword,
			'title'        => sanitize_text_field( $request->get_param( 'title' ) ?? '' ),
			'tone'         => sanitize_text_field( $request->get_param( 'tone' ) ?? $this->settings->get( 'default_tone' ) ),
			'language'     => sanitize_text_field( $request->get_param( 'language' ) ?? $this->settings->get( 'default_language' ) ),
			'target_words' => max( 500, (int) ( $request->get_param( 'target_words' ) ?? 1200 ) ),
			'include_faq'  => (bool) ( $request->get_param( 'include_faq' ) ?? true ),
			'aux_keywords' => sanitize_text_field( $request->get_param( 'aux_keywords' ) ?? '' ),
			'category'     => absint( $request->get_param( 'category' ) ?? 0 ),
		];

		$client    = new AISEO_OpenAI_Client( $this->settings );
		$generator = new AISEO_Article_Generator( $client, $this->logger );
		$result    = $generator->generate( $params );

		if ( ! $result['success'] ) {
			return new WP_Error( 'aiseo_generate_error', $result['error'] ?? __( 'Makale üretilemedi.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		return $this->ok( $result, __( 'Makale üretildi!', 'ai-seo-editor' ) );
	}

	public function create_draft( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$content  = wp_kses_post( $request->get_param( 'content' ) ?? '' );
		$title    = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$keyword  = sanitize_text_field( $request->get_param( 'focus_keyword' ) ?? '' );
		$meta     = sanitize_textarea_field( $request->get_param( 'meta_description' ) ?? '' );
		$category = absint( $request->get_param( 'category' ) ?? 0 );

		if ( empty( $content ) || empty( $title ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Başlık ve içerik zorunludur.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$client    = new AISEO_OpenAI_Client( $this->settings );
		$generator = new AISEO_Article_Generator( $client, $this->logger );

		$post_id = $generator->create_draft(
			[
				'content'          => $content,
				'title'            => $title,
				'focus_keyword'    => $keyword,
				'meta_description' => $meta,
			],
			[ 'category' => $category ]
		);

		if ( ! $post_id ) {
			return new WP_Error( 'aiseo_draft_error', __( 'Taslak oluşturulamadı.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		return $this->ok(
			[
				'post_id'  => $post_id,
				'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			],
			__( 'Taslak oluşturuldu!', 'ai-seo-editor' )
		);
	}

	public function get_link_suggestions( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$client  = new AISEO_OpenAI_Client( $this->settings );
		$linker  = new AISEO_Internal_Linker( $client, $this->logger );
		$cached  = $linker->get_cached( $post_id );

		return $this->ok( [ 'suggestions' => $cached ] );
	}

	public function compute_links( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$client      = new AISEO_OpenAI_Client( $this->settings );
		$linker      = new AISEO_Internal_Linker( $client, $this->logger );
		$suggestions = $linker->find_suggestions( $post_id );

		return $this->ok( [ 'suggestions' => $suggestions ], __( 'Link önerileri hesaplandı.', 'ai-seo-editor' ) );
	}

	public function apply_links( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id        = absint( $request->get_param( 'post_id' ) );
		$suggestion_ids = array_map( 'absint', (array) ( $request->get_param( 'suggestion_ids' ) ?? [] ) );

		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}
		if ( empty( $suggestion_ids ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Öneri ID listesi boş.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$client  = new AISEO_OpenAI_Client( $this->settings );
		$linker  = new AISEO_Internal_Linker( $client, $this->logger );
		$new_content = $linker->apply_suggestions( $post_id, $suggestion_ids );

		if ( empty( $new_content ) ) {
			return new WP_Error( 'aiseo_apply_error', __( 'İç linkler hazırlanamadı.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		return $this->ok(
			[
				'post_id'  => $post_id,
				'content'  => $new_content,
				'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
				'changed'  => $new_content !== get_post_field( 'post_content', $post_id ),
			],
			__( 'İç linkler editörde uygulanmak üzere hazırlandı.', 'ai-seo-editor' )
		);
	}

	public function get_logs( WP_REST_Request $request ): WP_REST_Response {
		$page    = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );
		$filters = [
			'operation_type' => sanitize_text_field( $request->get_param( 'operation_type' ) ?? '' ),
			'status'         => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
			'date_from'      => sanitize_text_field( $request->get_param( 'date_from' ) ?? '' ),
			'date_to'        => sanitize_text_field( $request->get_param( 'date_to' ) ?? '' ),
		];
		$logs = $this->logger->get_ai_logs( array_filter( $filters ), 25, $page );
		return $this->ok( $logs );
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$all           = $this->settings->get_all();
		$all['openai_api_key'] = $this->settings->get_masked_api_key();
		return $this->ok( $all );
	}

	public function save_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $request->get_json_params() ?: $request->get_body_params();
		if ( empty( $body ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Ayar verisi boş.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$this->settings->save( $body );
		return $this->ok( [], __( 'Ayarlar kaydedildi.', 'ai-seo-editor' ) );
	}

	public function test_api_key( WP_REST_Request $request ): WP_REST_Response {
		$raw_key = sanitize_text_field( $request->get_param( 'api_key' ) ?? '' );

		if ( ! empty( $raw_key ) && strpos( $raw_key, '*' ) === false ) {
			$this->settings->save( [ 'openai_api_key' => $raw_key ] );
		}

		$client = new AISEO_OpenAI_Client( $this->settings );
		$ok     = $client->test_connection();

		return $this->ok(
			[ 'connected' => $ok ],
			$ok
				? __( 'API bağlantısı başarılı!', 'ai-seo-editor' )
				: __( 'API anahtarı geçersiz veya bağlantı hatası.', 'ai-seo-editor' )
		);
	}

	private function post_exists( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$post = get_post( $post_id );
		return $post instanceof WP_Post;
	}

	private function check_token_budget(): void {
		$limit = (int) $this->settings->get( 'monthly_token_limit' );
		$used  = $this->logger->get_monthly_token_usage();
		if ( $limit > 0 && $used >= $limit ) {
			wp_send_json_error( [
				'code'    => 'aiseo_budget_exceeded',
				'message' => __( 'Aylık token limiti aşıldı.', 'ai-seo-editor' ),
			], 429 );
			exit;
		}
	}

	private function ok( array $data, string $message = '' ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'message' => $message,
		], 200 );
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'aiseo_not_found', __( 'Yazı bulunamadı.', 'ai-seo-editor' ), [ 'status' => 404 ] );
	}
}
