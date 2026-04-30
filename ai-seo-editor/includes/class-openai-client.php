<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_OpenAI_Client {

	private string $api_key;
	private string $model;
	private int $max_tokens;
	private string $base_url = 'https://api.openai.com/v1';
	private int $timeout     = 90;

	public function __construct( AISEO_Settings $settings ) {
		$this->api_key    = $settings->get_api_key();
		$this->model      = $settings->get( 'openai_model' );
		$this->max_tokens = (int) $settings->get( 'max_tokens' );
	}

	public function chat_completion(
		array $messages,
		?int $max_tokens   = null,
		float $temperature = 0.7,
		bool $json_mode    = false
	): array {
		if ( empty( $this->api_key ) ) {
			throw new RuntimeException( 'OpenAI API anahtarı tanımlanmamış.' );
		}

		$payload = [
			'model'       => $this->model,
			'messages'    => $messages,
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens ?? $this->max_tokens,
		];

		if ( $json_mode ) {
			$payload['response_format'] = [ 'type' => 'json_object' ];
		}

		return $this->make_request( $payload );
	}

	public function optimize_title( int $post_id, string $keyword ): array {
		$post  = get_post( $post_id );
		$title = get_the_title( $post_id );
		$intro = aiseo_get_first_paragraph( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO başlık uzmanısın. Verilen odak kelimeyi içeren, 50-60 karakter arasında, ilgi çekici ve tıklanabilir bir SEO başlığı üret. YALNIZCA başlık metnini döndür, ekstra açıklama yapma.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut başlık: {$title}\nİçerik özeti: " . aiseo_truncate( $intro, 200 ),
			],
		];

		$response = $this->chat_completion( $messages, 100, 0.7 );
		return [
			'before' => $title,
			'after'  => trim( $response['content'] ?? '' ),
			'field'  => 'post_title',
		];
	}

	public function optimize_meta( int $post_id, string $keyword ): array {
		$yoast   = new AISEO_Yoast_Integration();
		$current = $yoast->get_meta_description( $post_id );
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO meta açıklama uzmanısın. 120-158 karakter arasında, odak kelimeyi içeren, okuyucuyu tıklamaya teşvik eden bir meta açıklama yaz. YALNIZCA meta açıklamayı döndür.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut meta: {$current}\nİçerik: " . aiseo_truncate( $content, 500 ),
			],
		];

		$response = $this->chat_completion( $messages, 200, 0.7 );
		return [
			'before'    => $current,
			'after'     => trim( $response['content'] ?? '' ),
			'field'     => 'meta',
			'meta_key'  => '_aiseo_meta_description',
		];
	}

	public function improve_intro( int $post_id, string $keyword, string $tone ): array {
		$post    = get_post( $post_id );
		$content = apply_filters( 'the_content', $post->post_content ?? '' );
		$intro   = aiseo_get_first_paragraph( $content );

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir içerik yazarısın. Giriş paragrafını şu kurallara göre yeniden yaz: 1) İlk cümlede odak kelime geçmeli, 2) Okuyucuyu içeriğe çekmeli, 3) {$tone} tonunda olmalı, 4) 100-150 kelime arasında olmalı. YALNIZCA yeniden yazılmış paragrafı döndür.",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut giriş: {$intro}",
			],
		];

		$response = $this->chat_completion( $messages, 300, 0.7 );
		return [
			'before' => $intro,
			'after'  => trim( $response['content'] ?? '' ),
			'field'  => 'intro',
		];
	}

	public function improve_structure( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir içerik stratejistisin. İçerik yapısı için optimize H2 ve H3 başlıkları üret. JSON formatında döndür: {"headings":[{"level":"h2","text":"..."},{"level":"h3","text":"..."}],"rationale":"..."}',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nİçerik: " . aiseo_truncate( $content, 1000 ),
			],
		];

		$response = $this->chat_completion( $messages, 600, 0.7, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		return [
			'before' => '',
			'after'  => $response['content'] ?? '',
			'field'  => 'structure',
			'data'   => $parsed,
		];
	}

	public function improve_readability( int $post_id, string $tone ): array {
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';
		$excerpt = aiseo_strip_html( apply_filters( 'the_content', $content ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir okunabilirlik editörüsün. Verilen HTML içeriği şu kurallara göre yeniden yaz: 1) Cümleleri kısalt (max 20 kelime), 2) Aktif ses kullan, 3) Geçiş kelimeleri ekle, 4) Tüm gerçekleri koru, 5) {$tone} tonunu koru. YALNIZCA HTML içeriğini (<p>,<ul>,<ol>,<strong> etiketleriyle) döndür.",
			],
			[
				'role'    => 'user',
				'content' => "Ton: {$tone}\nİçerik:\n" . aiseo_truncate( $excerpt, 1500 ),
			],
		];

		$response = $this->chat_completion( $messages, 2000, 0.6 );
		return [
			'before' => $content,
			'after'  => trim( $response['content'] ?? '' ),
			'field'  => 'post_content',
		];
	}

	public function generate_faq( int $post_id, string $keyword, int $count = 5 ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir FAQ uzmanısın. İçerik ve odak kelimeye göre {$count} adet soru-cevap üret. JSON formatında döndür: {\"faqs\":[{\"question\":\"...\",\"answer\":\"...\"}]}",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nİçerik: " . aiseo_truncate( $content, 1500 ),
			],
		];

		$response = $this->chat_completion( $messages, 1000, 0.7, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		$faqs = $parsed['faqs'] ?? [];
		$html = '';
		if ( ! empty( $faqs ) ) {
			$html = '<div class="aiseo-faq-section"><h2>' . esc_html__( 'Sıkça Sorulan Sorular', 'ai-seo-editor' ) . '</h2>';
			foreach ( $faqs as $faq ) {
				$html .= '<div class="faq-item"><h3>' . esc_html( $faq['question'] ?? '' ) . '</h3><p>' . esc_html( $faq['answer'] ?? '' ) . '</p></div>';
			}
			$html .= '</div>';
		}

		return [
			'before' => '',
			'after'  => $html,
			'field'  => 'append_content',
			'data'   => $faqs,
		];
	}

	public function improve_keyword_density( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );
		$density = aiseo_keyword_density( $content, $keyword );

		$instruction = $density > 2.5
			? 'Anahtar kelime yoğunluğunu azalt (doğal görünüm için %1-1.5 hedefle).'
			: 'Anahtar kelimeyi doğal şekilde daha fazla kullan (%1-1.5 hedefle).';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir SEO içerik editörüsün. {$instruction} İçeriği yeniden yaz. HTML formatında döndür.",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut yoğunluk: %{$density}\nİçerik: " . aiseo_truncate( $content, 1500 ),
			],
		];

		$response = $this->chat_completion( $messages, 2000, 0.6 );
		return [
			'before' => $post->post_content ?? '',
			'after'  => trim( $response['content'] ?? '' ),
			'field'  => 'post_content',
		];
	}

	public function improve_conclusion( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir içerik yazarısın. Verilen içerik için güçlü bir sonuç bölümü yaz. Anahtar kelimeyi kullan, okuyucuya eylem çağrısı yap, 100-150 kelime olsun. YALNIZCA sonuç paragrafını HTML\'de döndür.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nİçerik: " . aiseo_truncate( $content, 1000 ),
			],
		];

		$response = $this->chat_completion( $messages, 400, 0.7 );
		$html     = '<h2>' . esc_html__( 'Sonuç', 'ai-seo-editor' ) . '</h2>' . trim( $response['content'] ?? '' );

		return [
			'before' => '',
			'after'  => $html,
			'field'  => 'append_content',
		];
	}

	public function suggest_internal_links( int $post_id, array $posts_index ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$posts_json = array_map( function ( $p ) {
			return [
				'id'      => $p['id'],
				'title'   => $p['title'],
				'url'     => $p['url'],
				'keyword' => $p['keyword'],
			];
		}, array_slice( $posts_index, 0, 20 ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir iç linkleme uzmanısın. Kaynak içerik ve hedef yazıları analiz ederek en uygun iç link fırsatlarını bul. JSON formatında döndür: {"suggestions":[{"anchor_text":"...","target_post_id":0,"target_url":"...","context_sentence":"...","relevance_reason":"..."}]}',
			],
			[
				'role'    => 'user',
				'content' => "Kaynak içerik:\n" . aiseo_truncate( $content, 1000 ) . "\n\nHedef yazılar:\n" . wp_json_encode( $posts_json, JSON_UNESCAPED_UNICODE ),
			],
		];

		$response = $this->chat_completion( $messages, 1000, 0.5, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		return [
			'before'      => '',
			'after'       => $response['content'] ?? '',
			'field'       => 'internal_links',
			'suggestions' => $parsed['suggestions'] ?? [],
			'tokens_used' => $response['total_tokens'] ?? 0,
		];
	}

	public function optimize_image_alts( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = apply_filters( 'the_content', $post->post_content ?? '' );
		$images  = aiseo_extract_images( $content );

		$missing = array_filter( $images, fn( $img ) => empty( $img['alt'] ) );
		if ( empty( $missing ) ) {
			return [
				'before' => '',
				'after'  => __( 'Tüm görsellerde alt metin mevcut.', 'ai-seo-editor' ),
				'field'  => 'image_alts',
				'alts'   => [],
			];
		}

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO görsel uzmanısın. Verilen görseller için odak kelimeyi içeren, açıklayıcı alt metinler üret. JSON formatında döndür: {"alts":[{"src":"...","alt":"..."}]}',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nGörseller:\n" . wp_json_encode( array_slice( $missing, 0, 10 ), JSON_UNESCAPED_UNICODE ),
			],
		];

		$response = $this->chat_completion( $messages, 500, 0.6, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		return [
			'before' => '',
			'after'  => $response['content'] ?? '',
			'field'  => 'image_alts',
			'alts'   => $parsed['alts'] ?? [],
		];
	}

	public function generate_full_article( array $params ): array {
		$keyword     = sanitize_text_field( $params['keyword'] ?? '' );
		$title       = sanitize_text_field( $params['title'] ?? '' );
		$tone        = sanitize_text_field( $params['tone'] ?? 'professional' );
		$language    = sanitize_text_field( $params['language'] ?? 'tr' );
		$target_wc   = max( 500, (int) ( $params['target_words'] ?? 1200 ) );
		$include_faq = (bool) ( $params['include_faq'] ?? true );
		$aux_kw      = sanitize_text_field( $params['aux_keywords'] ?? '' );

		$lang_map = [ 'tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español' ];
		$lang_str = $lang_map[ $language ] ?? 'Türkçe';

		$faq_note = $include_faq ? '- "faq" dizisi en az 5 soru-cevap içermeli' : '';
		$aux_note = $aux_kw ? "\nYardımcı kelimeler: {$aux_kw}" : '';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen uzman bir SEO içerik yazarısın. Verilen parametrelerle tam makale üret. JSON formatında döndür:
{\"title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\",\"content\":{\"introduction\":\"<p>...</p>\",\"sections\":[{\"heading\":\"H2 başlık\",\"content\":\"<p>...</p>\",\"subsections\":[{\"heading\":\"H3 başlık\",\"content\":\"<p>...</p>\"}]}],\"conclusion\":\"<p>...</p>\",\"faq\":[{\"question\":\"...\",\"answer\":\"...\"}]},\"word_count_estimate\":0,\"suggested_tags\":[]}
Kurallar: İçerik {$lang_str} dilinde olacak, ton: {$tone}, yaklaşık {$target_wc} kelime, Google EEAT prensiplerine uygun, doğal ve okuyucu odaklı.{$faq_note}",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nBaşlık: {$title}{$aux_note}\nHedef kelime sayısı: {$target_wc}",
			],
		];

		$tokens   = min( 4000, $this->max_tokens );
		$response = $this->chat_completion( $messages, $tokens, 0.7, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		return array_merge( $parsed, [
			'tokens_used'  => $response['total_tokens'] ?? 0,
			'raw_response' => $response['content'] ?? '',
		] );
	}

	public function test_connection(): bool {
		if ( empty( $this->api_key ) ) {
			return false;
		}
		try {
			$result = $this->chat_completion(
				[ [ 'role' => 'user', 'content' => 'Hi' ] ],
				5,
				0.0
			);
			return ! empty( $result['content'] );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	private function make_request( array $payload ): array {
		$response = wp_remote_post(
			$this->base_url . '/chat/completions',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
				'timeout' => $this->timeout,
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( 'HTTP isteği başarısız: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $http_code !== 200 ) {
			$error_msg = $data['error']['message'] ?? 'API isteği başarısız';
			throw new RuntimeException( 'OpenAI API hatası: ' . $http_code . '. ' . $error_msg );
		}

		$content      = $data['choices'][0]['message']['content'] ?? '';
		$input_tokens  = $data['usage']['prompt_tokens'] ?? 0;
		$output_tokens = $data['usage']['completion_tokens'] ?? 0;

		return [
			'content'       => $content,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
			'total_tokens'  => $input_tokens + $output_tokens,
			'model'         => $data['model'] ?? $this->model,
			'finish_reason' => $data['choices'][0]['finish_reason'] ?? '',
		];
	}

	private function parse_json_response( string $content ): array {
		$content = trim( $content );

		if ( preg_match( '/```json\s*(.*?)\s*```/s', $content, $m ) ) {
			$content = $m[1];
		} elseif ( preg_match( '/```\s*(.*?)\s*```/s', $content, $m ) ) {
			$content = $m[1];
		}

		$decoded = json_decode( $content, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}

		return [ 'raw' => $content ];
	}
}
