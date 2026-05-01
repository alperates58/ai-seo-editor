<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_OpenAI_Client {

	private string $api_key;
	private string $model;
	private int $max_tokens;
	private string $base_url = 'https://api.openai.com/v1';
	private int $timeout     = 120;

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
			throw new RuntimeException( 'OpenAI API anahtari tanimlanmamis.' );
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
				'content' => 'Sen bir SEO baslik uzmanisin. Odak kelimeyi iceren, 50-60 karakter arasinda, net ve tiklanabilir bir SEO basligi uret. YALNIZCA baslik metnini dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut baslik: {$title}\nIcerik ozeti: " . aiseo_truncate( $intro, 300 ),
			],
		];

		$response = $this->chat_completion( $messages, 120, 0.6 );
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
				'content' => 'Sen bir SEO meta aciklama uzmanisin. 120-158 karakter arasinda, odak kelimeyi iceren, dogal ve tiklamaya tesvik eden bir meta aciklama yaz. YALNIZCA meta aciklamayi dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut meta: {$current}\nIcerik: " . aiseo_truncate( $content, 900 ),
			],
		];

		$response = $this->chat_completion( $messages, 220, 0.6 );
		return [
			'before'   => $current,
			'after'    => trim( $response['content'] ?? '' ),
			'field'    => 'meta',
			'meta_key' => '_aiseo_meta_description',
		];
	}

	public function improve_intro( int $post_id, string $keyword, string $tone ): array {
		$post    = get_post( $post_id );
		$content = apply_filters( 'the_content', $post->post_content ?? '' );
		$intro   = aiseo_get_first_paragraph( $content );

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir icerik yazarisisin. Giris paragrafini yeniden yaz: ilk cumlede odak kelime gecsin, okuyucuyu konuya ceksin, {$tone} tonunda olsun, 100-150 kelime olsun. YALNIZCA paragraf metnini dondur.",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut giris: {$intro}",
			],
		];

		$response = $this->chat_completion( $messages, 380, 0.65 );
		return [
			'before' => $intro,
			'after'  => trim( $response['content'] ?? '' ),
			'field'  => 'intro',
		];
	}

	public function improve_structure( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO icerik editorusun. WordPress HTML icerigini koruyarak H2/H3 yapisini iyilestir. En az 2 anlamli H2 kullan, uygun yerlerde H3 ekle, en az bir H2 icinde odak kelimeyi dogal bicimde gecir. Mevcut gercekleri koru. YALNIZCA temiz WordPress HTML dondur; aciklama, markdown fence, html/body etiketi ekleme.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nIcerik:\n" . $this->limit_content_for_prompt( $content, 12000 ),
			],
		];

		$response = $this->chat_completion( $messages, 2800, 0.55 );
		return [
			'before' => $content,
			'after'  => $this->clean_model_html( $response['content'] ?? '' ),
			'field'  => 'post_content',
		];
	}

	public function improve_readability( int $post_id, string $tone, string $keyword = '' ): array {
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';
		$density = $keyword ? aiseo_keyword_density( $content, $keyword ) : 0;
		$keyword_note = $keyword
			? " Odak kelime: \"{$keyword}\". Bu kelimeyi ve mevcut SEO sinyallerini koru; baslik, ilk paragraf veya H2 icinde zaten dogal geciyorsa yerini bozma. Anahtar kelimeyi gereksiz tekrar etme; mevcut yogunluk %{$density}, hedef aralik %0.8-1.8."
			: '';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir okunabilirlik editorusun. Verilen WordPress HTML icerigini bastan sona duzenle: cumleleri kisalt, uzun paragraflari bol, aktif anlatim kullan, gecis kelimeleri ekle, H2/H3 yapisini ve tum gercekleri koru. Ton: {$tone}.{$keyword_note} Icerigi kisaltma; kapsam zayifsa yalnizca dogal ornekler ve aciklayici paragraflar ekle. Yeni FAQ, yeni sonuc bolumu veya tekrar eden anahtar kelime listeleri ekleme. YALNIZCA temiz WordPress HTML dondur.",
			],
			[
				'role'    => 'user',
				'content' => "Ton: {$tone}\nIcerik:\n" . $this->limit_content_for_prompt( $content, 14000 ),
			],
		];

		$response = $this->chat_completion( $messages, 3800, 0.55 );
		return [
			'before' => $content,
			'after'  => $this->clean_model_html( $response['content'] ?? '' ),
			'field'  => 'post_content',
		];
	}

	public function generate_faq( int $post_id, string $keyword, int $count = 5 ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir FAQ uzmanisin. Icerik ve odak kelimeye gore {$count} adet soru-cevap uret. Cevaplar 45-80 kelime araliginda, net ve faydali olsun. JSON formatinda dondur: {\"faqs\":[{\"question\":\"...\",\"answer\":\"...\"}]}",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nIcerik: " . $this->limit_content_for_prompt( $content, 5000 ),
			],
		];

		$response = $this->chat_completion( $messages, 1400, 0.65, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		$faqs = $parsed['faqs'] ?? [];
		$html = '';
		if ( ! empty( $faqs ) ) {
			$html = '<div class="aiseo-faq-section"><h2>' . esc_html__( 'Sikca Sorulan Sorular', 'ai-seo-editor' ) . '</h2>';
			foreach ( $faqs as $faq ) {
				$html .= '<div class="faq-item"><h3>' . esc_html( $faq['question'] ?? '' ) . '</h3><p>' . esc_html( $faq['answer'] ?? '' ) . '</p></div>';
			}
			$html .= '</div>';
		}

		return [
			'before'      => '',
			'after'       => $html,
			'field'       => 'append_content',
			'data'        => $faqs,
			'tokens_used' => $response['total_tokens'] ?? 0,
		];
	}

	public function improve_keyword_density( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';
		$density = aiseo_keyword_density( $content, $keyword );

		$instruction = $density > 2.5
			? 'Anahtar kelime yogunlugunu azalt; tekrar eden kullanımlari es anlamli veya baglamsal ifadelerle degistir ve yuzde 0.8-1.8 araligini hedefle.'
			: 'Anahtar kelime eksik kalan kritik yerlere dogal sekilde eklenebilir; yuzde 0.8-1.8 araligini hedefle ve keyword stuffing yapma.';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir SEO icerik editorusun. {$instruction} Icerigin tamamini WordPress HTML olarak yeniden duzenle. Odak kelimeyi yalnizca gerekli yerlerde kullan: ilk paragraf ve en fazla bir H2 yeterlidir. Yeni FAQ, sonuc bolumu veya anahtar kelime listesi ekleme. Gercekleri koru, gerekirse kisa aciklayici eklemeler yap. YALNIZCA temiz WordPress HTML dondur.",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut yogunluk: %{$density}\nIcerik:\n" . $this->limit_content_for_prompt( $content, 14000 ),
			],
		];

		$response = $this->chat_completion( $messages, 3800, 0.55 );
		return [
			'before' => $post->post_content ?? '',
			'after'  => $this->clean_model_html( $response['content'] ?? '' ),
			'field'  => 'post_content',
		];
	}

	public function improve_conclusion( int $post_id, string $keyword ): array {
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir icerik yazarisisin. Verilen icerik icin guclu bir sonuc bolumu yaz. Anahtar kelimeyi kullan, okuyucuya eylem cagrisi yap, 100-150 kelime olsun. YALNIZCA sonuc paragrafini HTML olarak dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nIcerik: " . $this->limit_content_for_prompt( $content, 3000 ),
			],
		];

		$response = $this->chat_completion( $messages, 500, 0.65 );
		$html     = '<h2>' . esc_html__( 'Sonuc', 'ai-seo-editor' ) . '</h2>' . $this->clean_model_html( $response['content'] ?? '' );

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
				'content' => 'Sen bir ic linkleme uzmanisin. Kaynak icerik ve hedef yazilari analiz ederek en uygun ic link firsatlarini bul. JSON formatinda dondur: {"suggestions":[{"anchor_text":"...","target_post_id":0,"target_url":"...","context_sentence":"...","relevance_reason":"..."}]}',
			],
			[
				'role'    => 'user',
				'content' => "Kaynak icerik:\n" . $this->limit_content_for_prompt( $content, 4000 ) . "\n\nHedef yazilar:\n" . wp_json_encode( $posts_json, JSON_UNESCAPED_UNICODE ),
			],
		];

		$response = $this->chat_completion( $messages, 1200, 0.5, true );
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
				'after'  => __( 'Tum gorsellerde alt metin mevcut.', 'ai-seo-editor' ),
				'field'  => 'image_alts',
				'alts'   => [],
			];
		}

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO gorsel uzmanisin. Verilen gorseller icin odak kelimeyi dogal bicimde iceren, aciklayici alt metinler uret. JSON formatinda dondur: {"alts":[{"src":"...","alt":"..."}]}',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nGorseller:\n" . wp_json_encode( array_slice( $missing, 0, 10 ), JSON_UNESCAPED_UNICODE ),
			],
		];

		$response = $this->chat_completion( $messages, 600, 0.6, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		return [
			'before' => '',
			'after'  => $response['content'] ?? '',
			'field'  => 'image_alts',
			'alts'   => $parsed['alts'] ?? [],
		];
	}

	public function optimize_full_post( int $post_id, string $keyword, string $tone ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [];
		}

		$yoast   = new AISEO_Yoast_Integration();
		$content = $post->post_content ?? '';
		$current = [
			'title'            => get_the_title( $post_id ),
			'meta_description' => $yoast->get_meta_description( $post_id ),
			'word_count'       => aiseo_count_words( $content ),
			'keyword_density'  => aiseo_keyword_density( $content, $keyword ),
			'has_faq'          => $this->content_has_faq( $content ),
			'has_conclusion'   => $this->content_has_conclusion( $content ),
			'existing_tags'    => wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ),
		];

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen deneyimli bir Turkce SEO editorusun. Verilen WordPress yazisini Yoast benzeri kriterlere gore kapsamli bicimde iyilestir. JSON dondur: {"title":"...","meta_description":"...","content":"<p>...</p>","suggested_tags":["..."]}. Kurallar: baslik 50-60 karakter, meta 120-158 karakter, odak kelime baslikta/metada/ilk paragrafta/en fazla bir H2 icinde dogal gecsin. Anahtar kelime yogunlugunu %0.8-1.8 araliginda tut; ayni kelimeyi sirf skor icin tekrar etme. Okunabilirligi dusurme: kisa cumleler, kisa paragraflar, aktif anlatim ve gecis kelimeleri kullan. Icerik 1000 kelimenin altindaysa kapsamli ama gercekci bicimde genislet, en az 2 H2 ve uygun H3 kullan. Mevcut FAQ varsa yeni FAQ ekleme; mevcut sonuc bolumu varsa ikinci sonuc bolumu ekleme. Mevcut gercekleri bozma, markdown fence/html/body etiketi kullanma. suggested_tags sadece mevcut etiketlerde olmayan 0-3 yeni etiket icersin; yeterli etiket varsa bos dizi dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nTon: {$tone}\nMevcut baslik: {$current['title']}\nMevcut meta: {$current['meta_description']}\nKelime sayisi: {$current['word_count']}\nAnahtar kelime yogunlugu: %{$current['keyword_density']}\nFAQ var mi: " . ( $current['has_faq'] ? 'evet' : 'hayir' ) . "\nSonuc bolumu var mi: " . ( $current['has_conclusion'] ? 'evet' : 'hayir' ) . "\nMevcut etiketler: " . implode( ', ', (array) $current['existing_tags'] ) . "\n\nMevcut HTML icerik:\n" . $this->limit_content_for_prompt( $content, 18000 ),
			],
		];

		$response = $this->chat_completion( $messages, 5000, 0.6, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		if ( ! empty( $parsed['content'] ) ) {
			$parsed['content'] = $this->clean_model_html( (string) $parsed['content'] );
		}

		if ( isset( $parsed['suggested_tags'] ) && is_array( $parsed['suggested_tags'] ) ) {
			$parsed['suggested_tags'] = $this->clean_tags( $parsed['suggested_tags'] );
		}

		return array_merge( $parsed, [
			'tokens_used'  => $response['total_tokens'] ?? 0,
			'raw_response' => $response['content'] ?? '',
		] );
	}

	public function generate_full_article( array $params ): array {
		$keyword     = sanitize_text_field( $params['keyword'] ?? '' );
		$title       = sanitize_text_field( $params['title'] ?? '' );
		$tone        = sanitize_text_field( $params['tone'] ?? 'professional' );
		$language    = sanitize_text_field( $params['language'] ?? 'tr' );
		$target_wc   = max( 500, (int) ( $params['target_words'] ?? 1200 ) );
		$include_faq = (bool) ( $params['include_faq'] ?? true );
		$aux_kw      = sanitize_text_field( $params['aux_keywords'] ?? '' );

		$lang_map = [ 'tr' => 'Turkce', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Francais', 'es' => 'Espanol' ];
		$lang_str = $lang_map[ $language ] ?? 'Turkce';

		$faq_note = $include_faq ? '- "faq" dizisi en az 5 soru-cevap icermeli' : '';
		$aux_note = $aux_kw ? "\nYardimci kelimeler: {$aux_kw}" : '';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen uzman bir SEO icerik yazarisisin. Verilen parametrelerle tam makale uret. JSON formatinda dondur:
{\"title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\",\"content\":{\"introduction\":\"<p>...</p>\",\"sections\":[{\"heading\":\"H2 baslik\",\"content\":\"<p>...</p>\",\"subsections\":[{\"heading\":\"H3 baslik\",\"content\":\"<p>...</p>\"}]}],\"conclusion\":\"<p>...</p>\",\"faq\":[{\"question\":\"...\",\"answer\":\"...\"}]},\"word_count_estimate\":0,\"suggested_tags\":[]}
Kurallar: Icerik {$lang_str} dilinde olacak, ton: {$tone}, yaklasik {$target_wc} kelime, Google EEAT prensiplerine uygun, dogal ve okuyucu odakli. suggested_tags alani 8-12 adet, 2-4 kelimelik, kisa olmayan SEO etiketi icermeli.{$faq_note}",
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nBaslik: {$title}{$aux_note}\nHedef kelime sayisi: {$target_wc}",
			],
		];

		$tokens   = min( 5000, max( 3000, $this->max_tokens ) );
		$response = $this->chat_completion( $messages, $tokens, 0.7, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );

		if ( isset( $parsed['suggested_tags'] ) && is_array( $parsed['suggested_tags'] ) ) {
			$parsed['suggested_tags'] = $this->clean_tags( $parsed['suggested_tags'] );
		}

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
			throw new RuntimeException( 'HTTP istegi basarisiz: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );

		if ( $http_code !== 200 ) {
			$error_msg = $data['error']['message'] ?? 'API istegi basarisiz';
			throw new RuntimeException( 'OpenAI API hatasi: ' . $http_code . '. ' . $error_msg );
		}

		$content       = $data['choices'][0]['message']['content'] ?? '';
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

	private function limit_content_for_prompt( string $content, int $limit ): string {
		$content = trim( $content );
		if ( mb_strlen( $content ) <= $limit ) {
			return $content;
		}

		return mb_substr( $content, 0, $limit ) . "\n\n[Icerik uzun oldugu icin burada kesildi; verilen bolumu butunlugunu koruyarak iyilestir.]";
	}

	private function clean_model_html( string $html ): string {
		$html = trim( $html );
		$html = preg_replace( '/^\s*```(?:html|HTML)?\s*/', '', $html );
		$html = preg_replace( '/\s*```\s*$/', '', $html );
		$html = preg_replace( '/^\s*(?:<!doctype\s+html[^>]*>|<html[^>]*>|<body[^>]*>)/i', '', $html );
		$html = preg_replace( '/(?:<\/body>|<\/html>)\s*$/i', '', $html );

		return trim( (string) $html );
	}

	private function content_has_faq( string $content ): bool {
		$text = mb_strtolower( aiseo_strip_html( $content ) );
		return str_contains( mb_strtolower( $content ), 'aiseo-faq-section' )
			|| str_contains( $text, 'sikca sorulan sorular' )
			|| str_contains( $text, 'sıkça sorulan sorular' )
			|| str_contains( $text, 'sss' );
	}

	private function content_has_conclusion( string $content ): bool {
		foreach ( aiseo_extract_headings( $content ) as $heading ) {
			$text = mb_strtolower( $heading['text'] ?? '' );
			if ( in_array( $text, [ 'sonuc', 'sonuç', 'sonuç bölümü', 'sonuc bolumu' ], true ) ) {
				return true;
			}
		}
		return false;
	}

	private function clean_tags( array $tags ): array {
		$clean = [];
		foreach ( $tags as $tag ) {
			$tag = trim( wp_strip_all_tags( (string) $tag ) );
			if ( mb_strlen( $tag ) < 4 ) {
				continue;
			}
			$clean[ mb_strtolower( $tag ) ] = $tag;
		}
		return array_values( $clean );
	}
}
