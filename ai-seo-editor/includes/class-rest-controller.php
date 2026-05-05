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

		register_rest_route( self::NAMESPACE, '/agent/optimize', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'run_agent_optimize' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/agent/apply', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'apply_agent_optimization' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/regenerate/(?P<post_id>\d+)', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'regenerate_post' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/optimize/apply', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'apply_optimization' ],
			'permission_callback' => [ $this, 'check_permissions' ],
		] );

		register_rest_route( self::NAMESPACE, '/tags/optimize/(?P<post_id>\d+)', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'optimize_tags' ],
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

		$post    = get_post( $post_id );
		$yoast   = new AISEO_Yoast_Integration();
		$keyword = $yoast->get_focus_keyword( $post_id );

		if ( empty( $keyword ) && $post instanceof WP_Post ) {
			$keyword = $post->post_title;
		}

		if ( empty( $keyword ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Tam duzeltme icin baslik veya odak kelime gereklidir.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$content_before = $post instanceof WP_Post ? $post->post_content : '';
		$title_before   = $post instanceof WP_Post ? $post->post_title : '';
		$meta_before    = $yoast->get_meta_description( $post_id );

		try {
			$client = new AISEO_OpenAI_Client( $this->settings );
			$result = $client->optimize_full_post( $post_id, $keyword, (string) $this->settings->get( 'default_tone' ) );
		} catch ( Throwable $e ) {
			$this->logger->log_ai_operation(
				$post_id,
				'full_optimize',
				(string) $this->settings->get( 'openai_model' ),
				0,
				0,
				'error',
				$e->getMessage()
			);
			return new WP_Error( 'aiseo_optimize_error', $e->getMessage(), [ 'status' => 500 ] );
		}

		if ( empty( $result['content'] ) ) {
			$error = sanitize_text_field( (string) ( $result['error'] ?? __( 'Tam düzeltme önerisi üretilemedi.', 'ai-seo-editor' ) ) );
			$this->logger->log_ai_operation(
				$post_id,
				'full_optimize',
				(string) $this->settings->get( 'openai_model' ),
				0,
				0,
				'error',
				$error
			);
			return new WP_Error( 'aiseo_optimize_error', $error, [ 'status' => 500 ] );
		}

		$title   = sanitize_text_field( $result['title'] ?? $title_before );
		$meta    = sanitize_textarea_field( $result['meta_description'] ?? $meta_before );
		$content = wp_kses_post( $result['content'] ?? $content_before );
		$tags    = array_map( 'sanitize_text_field', is_array( $result['suggested_tags'] ?? null ) ? $result['suggested_tags'] : [] );
		$tags    = $this->filter_new_tags( $post_id, $tags, 3 );
		$tokens  = (int) ( $result['tokens_used'] ?? 0 );

		$this->logger->log_ai_operation(
			$post_id,
			'full_optimize',
			(string) $this->settings->get( 'openai_model' ),
			0,
			$tokens,
			'success'
		);

		$steps = [
			[
				'operation' => 'optimize_title',
				'success'   => true,
				'field'     => 'post_title',
				'before'    => $title_before,
				'after'     => $title,
			],
			[
				'operation' => 'optimize_meta',
				'success'   => true,
				'field'     => 'meta',
				'before'    => $meta_before,
				'after'     => $meta,
			],
			[
				'operation' => 'full_content_optimization',
				'success'   => true,
				'field'     => 'post_content',
				'before'    => $content_before,
				'after'     => $content,
			],
		];

		return $this->ok(
			[
				'post_id' => $post_id,
				'title'   => $title,
				'content' => $content,
				'meta'    => $meta,
				'tags'    => $tags,
				'steps'   => $steps,
			],
			__( 'Tam duzeltme onerisi hazir.', 'ai-seo-editor' )
		);
	}

	public function run_agent_optimize( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id     = absint( $request->get_param( 'post_id' ) );
		$target_seo  = max( 1, min( 100, absint( $request->get_param( 'target_seo' ) ?? 80 ) ) );
		$target_read = max( 1, min( 100, absint( $request->get_param( 'target_readability' ) ?? 75 ) ) );

		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$analyzer = new AISEO_Analyzer();
		$before   = $analyzer->analyze( $post_id, true );
		if ( isset( $before['error'] ) ) {
			return new WP_Error( 'aiseo_analysis_error', $before['error'], [ 'status' => 500 ] );
		}

		$seo_score  = (int) ( $before['seo_score'] ?? 0 );
		$read_score = (int) ( $before['readability_score'] ?? 0 );

		if ( $seo_score >= $target_seo && $read_score >= $target_read ) {
			return $this->ok(
				[
					'post_id' => $post_id,
					'skipped' => true,
					'reason'  => __( 'Yazı hedef skorların üzerinde.', 'ai-seo-editor' ),
					'before'  => [
						'seo_score'         => $seo_score,
						'readability_score' => $read_score,
					],
				],
				__( 'İyileştirme gerekmiyor.', 'ai-seo-editor' )
			);
		}

		$this->check_token_budget();

		$proposal = $this->build_full_optimization_proposal( $post_id );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}

		$proposal['before'] = [
			'seo_score'         => $seo_score,
			'readability_score' => $read_score,
		];
		$proposal['targets'] = [
			'seo_score'         => $target_seo,
			'readability_score' => $target_read,
		];

		return $this->ok( $proposal, __( 'Agent önerisi hazır.', 'ai-seo-editor' ) );
	}

	public function apply_agent_optimization( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$title   = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$content = wp_kses_post( $request->get_param( 'content' ) ?? '' );
		$meta    = sanitize_textarea_field( $request->get_param( 'meta' ) ?? '' );
		$tags    = array_map( 'sanitize_text_field', (array) ( $request->get_param( 'tags' ) ?? [] ) );

		if ( empty( $title ) || empty( $content ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Başlık ve içerik gereklidir.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$post = get_post( $post_id );
		if ( $post instanceof WP_Post && post_type_supports( $post->post_type, 'revisions' ) ) {
			wp_save_post_revision( $post_id );
		}

		$updated = wp_update_post( [
			'ID'           => $post_id,
			'post_title'   => $title,
			'post_content' => $content,
		], true );

		if ( is_wp_error( $updated ) ) {
			return new WP_Error( 'aiseo_apply_error', $updated->get_error_message(), [ 'status' => 500 ] );
		}

		if ( $meta !== '' ) {
			update_post_meta( $post_id, '_aiseo_meta_description', $meta );
			( new AISEO_Yoast_Integration() )->set_meta_description( $post_id, $meta );
		}

		if ( ! empty( $tags ) ) {
			wp_set_post_tags( $post_id, $this->clean_tag_list( $tags, 8 ), false );
		}

		$this->logger->invalidate_cache( $post_id );
		$after = ( new AISEO_Analyzer() )->analyze( $post_id, true );

		return $this->ok(
			[
				'post_id' => $post_id,
				'after'   => [
					'seo_score'         => (int) ( $after['seo_score'] ?? 0 ),
					'readability_score' => (int) ( $after['readability_score'] ?? 0 ),
				],
			],
			__( 'Agent önerisi uygulandı ve yeniden analiz edildi.', 'ai-seo-editor' )
		);
	}

	public function regenerate_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$this->check_token_budget();

		$post    = get_post( $post_id );
		$yoast   = new AISEO_Yoast_Integration();
		$keyword = $yoast->get_focus_keyword( $post_id );
		$title   = $post instanceof WP_Post ? $post->post_title : '';

		if ( empty( $keyword ) ) {
			$keyword = $title;
		}
		if ( empty( $keyword ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'BaÅŸtan oluÅŸturmak iÃ§in baÅŸlÄ±k veya odak kelime gereklidir.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$categories = wp_get_post_categories( $post_id );
		$content    = $post instanceof WP_Post ? $post->post_content : '';

		$params = [
			'keyword'      => $keyword,
			'title'        => $title,
			'tone'         => $this->settings->get( 'default_tone' ),
			'language'     => $this->settings->get( 'default_language' ),
			'target_words' => max( 800, min( 2500, aiseo_count_words( $content ) ?: 1200 ) ),
			'include_faq'  => true,
			'category'     => ! empty( $categories ) ? (int) $categories[0] : 0,
		];

		$client    = new AISEO_OpenAI_Client( $this->settings );
		$generator = new AISEO_Article_Generator( $client, $this->logger );
		$result    = $generator->generate( $params );
		$new_content = $this->preserve_bracket_blocks( $content, (string) ( $result['content'] ?? '' ) );

		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'aiseo_regenerate_error', $result['error'] ?? __( 'Makale baÅŸtan oluÅŸturulamadÄ±.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		return $this->ok(
			[
				'post_id' => $post_id,
				'title'   => $result['title'] ?? $title,
				'content' => $new_content,
				'meta'    => $result['meta_description'] ?? '',
				'tags'    => $result['suggested_tags'] ?? [],
			],
			__( 'Makale baÅŸtan oluÅŸturuldu.', 'ai-seo-editor' )
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
		$tags     = array_map( 'sanitize_text_field', (array) ( $request->get_param( 'suggested_tags' ) ?? [] ) );

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
				'suggested_tags'   => $tags,
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
		$content        = $request->has_param( 'content' ) ? wp_kses_post( $request->get_param( 'content' ) ) : null;

		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}
		if ( empty( $suggestion_ids ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Öneri ID listesi boş.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$client  = new AISEO_OpenAI_Client( $this->settings );
		$linker  = new AISEO_Internal_Linker( $client, $this->logger );
		$new_content = $linker->apply_suggestions( $post_id, $suggestion_ids, $content );

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
		$body = $request->get_json_params() ?: $request->get_body_params();
		if ( ! empty( $body ) ) {
			$this->settings->save( $body );
		}

		$client = new AISEO_OpenAI_Client( $this->settings );
		$result = $client->test_connection_details();
		$ok     = (bool) ( $result['connected'] ?? false );
		$error  = sanitize_text_field( (string) ( $result['message'] ?? '' ) );

		return $this->ok(
			[
				'connected' => $ok,
				'model'     => sanitize_text_field( (string) ( $result['model'] ?? $this->settings->get( 'openai_model' ) ) ),
				'error'     => $ok ? '' : $error,
			],
			$ok
				? __( 'API bağlantısı başarılı!', 'ai-seo-editor' )
				: sprintf(
					/* translators: %s: API error message */
					__( 'API bağlantısı başarısız: %s', 'ai-seo-editor' ),
					$error ?: __( 'Bilinmeyen hata.', 'ai-seo-editor' )
				)
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

	private function preserve_bracket_blocks( string $source, string $target ): string {
		if ( ! preg_match_all( '/\[[^\[\]\r\n]{1,800}\]/u', $source, $matches ) ) {
			return $target;
		}

		$missing = [];
		foreach ( array_unique( $matches[0] ) as $block ) {
			if ( strpos( $target, $block ) === false ) {
				$missing[] = $block;
			}
		}

		if ( empty( $missing ) ) {
			return $target;
		}

		return implode( "\n\n", $missing ) . "\n\n" . $target;
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'aiseo_not_found', __( 'Yazı bulunamadı.', 'ai-seo-editor' ), [ 'status' => 404 ] );
	}

	private function build_full_optimization_proposal( int $post_id ): array|WP_Error {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $this->not_found();
		}

		$yoast   = new AISEO_Yoast_Integration();
		$keyword = $yoast->get_focus_keyword( $post_id );
		if ( empty( $keyword ) ) {
			$keyword = $post->post_title;
		}
		if ( empty( $keyword ) ) {
			return new WP_Error( 'aiseo_missing_param', __( 'Tam düzeltme için başlık veya odak kelime gereklidir.', 'ai-seo-editor' ), [ 'status' => 422 ] );
		}

		$content_before = $post->post_content;
		$title_before   = $post->post_title;
		$meta_before    = $yoast->get_meta_description( $post_id );

		try {
			$client = new AISEO_OpenAI_Client( $this->settings );
			$result = $client->optimize_full_post( $post_id, $keyword, (string) $this->settings->get( 'default_tone' ) );
		} catch ( Throwable $e ) {
			$this->logger->log_ai_operation(
				$post_id,
				'agent_full_optimize',
				(string) $this->settings->get( 'openai_model' ),
				0,
				0,
				'error',
				$e->getMessage()
			);
			return new WP_Error( 'aiseo_optimize_error', $e->getMessage(), [ 'status' => 500 ] );
		}

		if ( empty( $result['content'] ) ) {
			$error = sanitize_text_field( (string) ( $result['error'] ?? __( 'Tam düzeltme önerisi üretilemedi.', 'ai-seo-editor' ) ) );
			$this->logger->log_ai_operation(
				$post_id,
				'agent_full_optimize',
				(string) $this->settings->get( 'openai_model' ),
				0,
				0,
				'error',
				$error
			);
			return new WP_Error( 'aiseo_optimize_error', $error, [ 'status' => 500 ] );
		}

		$title   = sanitize_text_field( $result['title'] ?? $title_before );
		$meta    = sanitize_textarea_field( $result['meta_description'] ?? $meta_before );
		$content = wp_kses_post( $result['content'] ?? $content_before );
		$tags    = array_map( 'sanitize_text_field', is_array( $result['suggested_tags'] ?? null ) ? $result['suggested_tags'] : [] );
		$tags    = $this->filter_new_tags( $post_id, $tags, 3 );
		$tokens  = (int) ( $result['tokens_used'] ?? 0 );

		$this->logger->log_ai_operation(
			$post_id,
			'agent_full_optimize',
			(string) $this->settings->get( 'openai_model' ),
			0,
			$tokens,
			'success'
		);

		return [
			'post_id' => $post_id,
			'title'   => $title,
			'content' => $content,
			'meta'    => $meta,
			'tags'    => $tags,
			'steps'   => [
				[
					'operation' => 'optimize_title',
					'success'   => true,
					'before'    => $title_before,
					'after'     => $title,
				],
				[
					'operation' => 'optimize_meta',
					'success'   => true,
					'before'    => $meta_before,
					'after'     => $meta,
				],
				[
					'operation' => 'full_content_optimization',
					'success'   => true,
					'before'    => $content_before,
					'after'     => $content,
				],
			],
		];
	}

	public function optimize_tags( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $this->post_exists( $post_id ) ) {
			return $this->not_found();
		}

		$this->check_token_budget();

		$post    = get_post( $post_id );
		$yoast   = new AISEO_Yoast_Integration();
		$keyword = $yoast->get_focus_keyword( $post_id );
		$content = $request->has_param( 'content' ) ? wp_kses_post( $request->get_param( 'content' ) ) : ( $post instanceof WP_Post ? $post->post_content : '' );
		$current = (array) ( $request->get_param( 'current_tags' ) ?? wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ) );
		$current = array_map( 'sanitize_text_field', $current );

		if ( empty( $keyword ) && $post instanceof WP_Post ) {
			$keyword = $post->post_title;
		}

		$client = new AISEO_OpenAI_Client( $this->settings );
		$result = $client->optimize_tags( $post_id, $keyword, $content, $current );
		$tags   = $this->clean_tag_list( is_array( $result['tags'] ?? null ) ? $result['tags'] : [], 8 );

		if ( empty( $tags ) ) {
			return new WP_Error( 'aiseo_tags_empty', __( 'Etiket önerisi üretilemedi.', 'ai-seo-editor' ), [ 'status' => 500 ] );
		}

		wp_set_post_tags( $post_id, $tags, false );

		$this->logger->log_ai_operation(
			$post_id,
			'optimize_tags',
			(string) $this->settings->get( 'openai_model' ),
			0,
			(int) ( $result['tokens_used'] ?? 0 ),
			'success'
		);

		return $this->ok(
			[
				'post_id' => $post_id,
				'before'  => $current,
				'tags'    => $tags,
			],
			__( 'Etiketler güncellendi.', 'ai-seo-editor' )
		);
	}

	private function filter_new_tags( int $post_id, array $tags, int $limit = 3 ): array {
		$existing = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
		$seen     = [];

		foreach ( (array) $existing as $tag ) {
			$key = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $tag ) ) );
			if ( $key !== '' ) {
				$seen[ $key ] = true;
			}
		}

		$clean = [];
		foreach ( $tags as $tag ) {
			$tag = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $tag ) ) );
			$key = mb_strtolower( $tag );
			if ( $tag === '' || mb_strlen( $tag ) < 4 || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$clean[]      = $tag;
			if ( count( $clean ) >= $limit ) {
				break;
			}
		}

		return $clean;
	}

	private function clean_tag_list( array $tags, int $limit = 8 ): array {
		$seen  = [];
		$clean = [];

		foreach ( $tags as $tag ) {
			$tag = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $tag ) ) );
			$tag = trim( str_replace( [ '#', ',' ], ' ', $tag ) );
			$key = mb_strtolower( $tag );

			if ( $tag === '' || mb_strlen( $tag ) < 4 || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$clean[]      = $tag;

			if ( count( $clean ) >= $limit ) {
				break;
			}
		}

		return $clean;
	}
}
