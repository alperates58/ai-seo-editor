<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_OpenAI_Client {

	private string $api_key;
	private string $model;
	private int $max_tokens;
	private string $base_url = 'https://api.openai.com/v1';
	private int $timeout     = 240;
	private bool $is_deepseek = false;

	public function set_timeout( int $seconds ): void {
		$this->timeout = max( 10, $seconds );
	}

	public function __construct( AISEO_Settings $settings ) {
		$this->api_key    = $settings->get_api_key();
		$this->model      = $settings->get( 'openai_model' );
		$this->max_tokens = (int) $settings->get( 'max_tokens' );
		$provider         = (string) $settings->get( 'ai_provider' );
		$base_url         = trim( (string) $settings->get( 'ai_base_url' ) );
		$this->is_deepseek = $provider === 'deepseek' || str_starts_with( $this->model, 'deepseek-' );
		if ( $this->is_deepseek ) {
			$this->base_url = 'https://api.deepseek.com';
		}
		if ( $base_url !== '' ) {
			$this->base_url = rtrim( $base_url, '/' );
		}
	}

	public function chat_completion(
		array $messages,
		?int $max_tokens   = null,
		float $temperature = 0.7,
		bool $json_mode    = false
	): array {
		if ( empty( $this->api_key ) ) {
			throw new RuntimeException( 'AI API anahtari tanimlanmamis.' );
		}

		$payload = [
			'model'       => $this->model,
			'messages'    => $messages,
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens ?? $this->max_tokens,
		];

		if ( $this->is_deepseek && $this->model !== 'deepseek-reasoner' ) {
			$payload['thinking'] = [ 'type' => 'disabled' ];
		}

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
				'content' => 'Sen bir SEO baslik uzmanisin. Odak kelimeyi iceren, net ve tiklanabilir bir SEO basligi uret. 50-60 karakter hedefle; 58 karakteri ASLA gecme. Baslik 48 karakterin altinda kaliyorsa "Nasil Yapilir", "Hesaplama Rehberi", "Pratik Hesaplama" veya "Detayli Rehber" gibi dogal bir tamamlayici ekle — spammy veya clickbait yapma. YALNIZCA baslik metnini dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut baslik: {$title}\nIcerik ozeti: " . aiseo_truncate( $intro, 300 ),
			],
		];

		$response = $this->chat_completion( $messages, 120, 0.6 );
		return [
			'before' => $title,
			'after'  => aiseo_normalize_seo_title( trim( (string) ( $response['content'] ?? '' ) ) ),
			'field'  => 'post_title',
		];
	}

	public function generate_seo_title( int $post_id, string $keyword, string $content = '', string $title_override = '' ): string {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$title = $title_override !== '' ? $title_override : get_the_title( $post_id );
		$body  = $content !== '' ? $content : (string) $post->post_content;
		$plain = aiseo_strip_html( apply_filters( 'the_content', $body ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen Turkce SEO baslik uzmanisin. 50-60 karakter hedefle; 48 karakter altina dusme. Odak kelimeyi dogal bicimde iceren, clickbait olmayan, net bir SEO title uret. Baslik kisa kaliyorsa "Nasil Yapilir", "Hesaplama Rehberi", "Maliyet Analizi" veya "Pratik Hesaplama" gibi dogal bir tamamlayici kullan. Post titlei aynen tekrar etme. Cevapta yalnizca baslik metni veya {"seo_title":"..."} dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut post title: {$title}\nIcerik ozeti: " . aiseo_truncate( $plain, 1200 ),
			],
		];

		try {
			$response = $this->chat_completion( $messages, 120, 0.4 );
		} catch ( Throwable $e ) {
			return '';
		}

		$content = trim( (string) ( $response['content'] ?? '' ) );
		if ( $content === '' ) {
			return '';
		}

		$parsed = $this->parse_json_response( $content );
		$title_candidate = '';

		if ( isset( $parsed['seo_title'] ) ) {
			$title_candidate = (string) $parsed['seo_title'];
		} elseif ( isset( $parsed['title'] ) ) {
			$title_candidate = (string) $parsed['title'];
		} else {
			$title_candidate = $content;
		}

		return aiseo_normalize_seo_title( trim( $title_candidate ) );
	}

	public function optimize_meta( int $post_id, string $keyword ): array {
		$yoast   = new AISEO_Yoast_Integration();
		$current = $yoast->get_meta_description( $post_id );
		$post    = get_post( $post_id );
		$content = aiseo_strip_html( apply_filters( 'the_content', $post->post_content ?? '' ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO meta aciklama uzmanisin. 140-155 karakter arasinda bir meta aciklama yaz. Odak kelime birebir (aynen) metinde gecmeli. Dogal, tiklamaya tesvik eden bir ton kullan. 155 karakteri ASLA gecme. YALNIZCA meta aciklamayi dondur.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nMevcut meta: {$current}\nIcerik: " . aiseo_truncate( $content, 900 ),
			],
		];

		$response = $this->chat_completion( $messages, 220, 0.6 );
		return [
			'before'   => $current,
			'after'    => aiseo_normalize_meta_description( (string) ( $response['content'] ?? '' ) ),
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
				'content' => "Sen bir icerik yazarisisin. Giris paragrafini yeniden yaz: ilk 100 karakterde odak kelime birebir gecmeli, okuyucuyu konuya ceksin, {$tone} tonunda olsun, 100-150 kelime olsun. Edilgen cati kullanma; etken cumleler kur. Her 2-3 cumlede gecis ifadesi kullan (ayrica, bu nedenle, ornegin, boylece). Cumleler 20 kelime altinda olsun. " . $this->shortcode_instruction() . " YALNIZCA paragraf metnini dondur.",
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
		$locked  = $this->protect_bracket_blocks( $content );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir SEO icerik editorusun. WordPress HTML icerigini koruyarak H2/H3 yapisini iyilestir. En az 2 anlamli H2 kullan, uygun yerlerde H3 ekle, en az bir H2 icinde odak kelimeyi dogal bicimde gecir. Mevcut gercekleri koru. <h1> uretme; icerik basliklar yalnizca H2/H3 olmali. ' . $this->shortcode_instruction() . ' ' . $this->protected_block_instruction() . ' YALNIZCA temiz WordPress HTML dondur; aciklama, markdown fence, html/body etiketi ekleme.',
			],
			[
				'role'    => 'user',
				'content' => "Konu basligi: " . get_the_title( $post_id ) . "\nOdak kelime: {$keyword}\nIcerik:\n" . $this->limit_content_for_prompt( $locked['content'], 12000 ),
			],
		];

		$response = $this->chat_completion( $messages, 2800, 0.55 );
		return [
			'before' => $content,
			'after'  => $this->enforce_shortcode_placement( $content, $this->restore_bracket_blocks( $this->clean_model_html( $response['content'] ?? '' ), $locked['blocks'] ) ),
			'field'  => 'post_content',
		];
	}

	public function improve_readability( int $post_id, string $tone, string $keyword = '' ): array {
		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';
		$locked  = $this->protect_bracket_blocks( $content );
		$density = $keyword ? aiseo_keyword_density( $content, $keyword ) : 0;
		$keyword_note = $keyword
			? " Odak kelime: \"{$keyword}\". Bu kelimeyi ve mevcut SEO sinyallerini koru; baslik, ilk paragraf veya H2 icinde zaten dogal geciyorsa yerini bozma. Anahtar kelimeyi gereksiz tekrar etme; mevcut yogunluk %{$density}, hedef aralik %0.8-1.8."
			: '';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir okunabilirlik editorusun. Verilen WordPress HTML icerigini bastan sona duzenle: cumleleri kisalt, uzun paragraflari bol, aktif anlatim kullan, gecis kelimeleri ekle, H2/H3 yapisini ve tum gercekleri koru. Ton: {$tone}.{$keyword_note} Icerigi kisaltma; kapsam zayifsa yalnizca dogal ornekler ve aciklayici paragraflar ekle. Yeni FAQ, yeni sonuc bolumu veya tekrar eden anahtar kelime listeleri ekleme. <h1> uretme; icerik basliklar yalnizca H2/H3 olmali. " . $this->readability_instruction() . ' ' . $this->shortcode_instruction() . ' ' . $this->protected_block_instruction() . " YALNIZCA temiz WordPress HTML dondur.",
			],
			[
				'role'    => 'user',
				'content' => "Konu basligi: " . get_the_title( $post_id ) . "\nTon: {$tone}\nIcerik:\n" . $this->limit_content_for_prompt( $locked['content'], 14000 ),
			],
		];

		$response = $this->chat_completion( $messages, 3800, 0.55 );
		return [
			'before' => $content,
			'after'  => $this->enforce_shortcode_placement( $content, $this->restore_bracket_blocks( $this->clean_model_html( $response['content'] ?? '' ), $locked['blocks'] ) ),
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
		$locked  = $this->protect_bracket_blocks( $content );
		$density = aiseo_keyword_density( $content, $keyword );

		$instruction = $density > 2.5
			? 'Anahtar kelime yogunlugunu azalt; tekrar eden kullanımlari es anlamli veya baglamsal ifadelerle degistir ve yuzde 0.8-1.8 araligini hedefle.'
			: 'Anahtar kelime eksik kalan kritik yerlere dogal sekilde eklenebilir; yuzde 0.8-1.8 araligini hedefle ve keyword stuffing yapma.';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen bir SEO icerik editorusun. {$instruction} Icerigin tamamini WordPress HTML olarak yeniden duzenle. Odak kelimeyi yalnizca gerekli yerlerde kullan: ilk paragraf ve en fazla bir H2 yeterlidir. Yeni FAQ, sonuc bolumu veya anahtar kelime listesi ekleme. Gercekleri koru, gerekirse kisa aciklayici eklemeler yap. <h1> uretme; icerik basliklar yalnizca H2/H3 olmali. " . $this->shortcode_instruction() . ' ' . $this->protected_block_instruction() . " YALNIZCA temiz WordPress HTML dondur.",
			],
			[
				'role'    => 'user',
				'content' => "Konu basligi: " . get_the_title( $post_id ) . "\nOdak kelime: {$keyword}\nMevcut yogunluk: %{$density}\nIcerik:\n" . $this->limit_content_for_prompt( $locked['content'], 14000 ),
			],
		];

		$response = $this->chat_completion( $messages, 3800, 0.55 );
		return [
			'before' => $post->post_content ?? '',
			'after'  => $this->enforce_shortcode_placement( $post->post_content ?? '', $this->restore_bracket_blocks( $this->clean_model_html( $response['content'] ?? '' ), $locked['blocks'] ) ),
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

	public function optimize_full_post( int $post_id, string $keyword, string $tone, string $content_override = '', string $title_override = '', string $meta_override = '', array $current_tags = [] ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [];
		}

		$yoast   = new AISEO_Yoast_Integration();
		$content = $content_override !== '' ? $content_override : ( $post->post_content ?? '' );
		$locked  = $this->protect_bracket_blocks( $content );
		$current = [
			'title'            => $title_override !== '' ? $title_override : get_the_title( $post_id ),
			'meta_description' => $meta_override !== '' ? $meta_override : $yoast->get_meta_description( $post_id ),
			'word_count'       => aiseo_count_words( $content ),
			'keyword_density'  => aiseo_keyword_density( $content, $keyword ),
			'has_faq'          => $this->content_has_faq( $content ),
			'has_conclusion'   => $this->content_has_conclusion( $content ),
			'existing_tags'    => ! empty( $current_tags ) ? $current_tags : wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ),
		];

		$cats      = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
		$cat_label = ! empty( $cats ) ? $this->get_category_label( (int) $cats[0] ) : '';
		$cat_note  = $cat_label !== '' ? "\nKategori: {$cat_label}" : '';

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen deneyimli bir Turkce SEO editorusun. Verilen WordPress yazisini Yoast benzeri kriterlere gore kapsamli bicimde iyilestir. Cevabinda analiz, aciklama, madde madde yorum, ingilizce not veya hesaplama sureci yazma. Yalnizca su formati dondur: <article data-aiseo-output="1">...temiz WordPress HTML...</article>. SEO KURALLARI: Odak kelime ilk paragrafin ilk 100 karakterinde birebir gecmeli. En az bir H2 basliginda odak kelime birebir gecmeli. Anahtar kelime yogunlugunu %0.8-1.8 araliginda tut; ayni kelimeyi sirf skor icin tekrar etme. Konuyu oncelikle mevcut basliktan anla; odak kelime eksikse basligi konu kaynagi kabul et. Icerik 1000 kelimenin altindaysa en az 1000-1200 kelimeye ulasacak sekilde kapsamli ama gercekci bicimde genislet; hesaplama ornekleri, sonuc yorumlama veya sik yapilan hatalar gibi dogal bolumler ekle; en az 2 H2 ve uygun H3 kullan. Mevcut FAQ varsa yeni FAQ ekleme; mevcut sonuc bolumu varsa ikinci sonuc bolumu ekleme. Icerik icinde <h1> veya markdown # baslik KULLANMA; icerik basliklar yalnizca H2/H3 olmali; mevcut <h1> gorursen icerige dahil etme. ' . $this->readability_instruction() . ' ' . $this->shortcode_instruction() . ' ' . $this->protected_block_instruction() . ' Mevcut gercekleri bozma, markdown fence, html/body etiketi kullanma.',
			],
			[
				'role'    => 'user',
				'content' => "Konu basligi: {$current['title']}\nOdak kelime: {$keyword}\nTon: {$tone}{$cat_note}\nMevcut baslik: {$current['title']}\nMevcut meta: {$current['meta_description']}\nKelime sayisi: {$current['word_count']}\nAnahtar kelime yogunlugu: %{$current['keyword_density']}\nFAQ var mi: " . ( $current['has_faq'] ? 'evet' : 'hayir' ) . "\nSonuc bolumu var mi: " . ( $current['has_conclusion'] ? 'evet' : 'hayir' ) . "\nMevcut etiketler: " . implode( ', ', (array) $current['existing_tags'] ) . "\n\nMevcut HTML icerik:\n" . $this->limit_content_for_prompt( $locked['content'], 18000 ),
			],
		];

		$minimum_word_count = min( 300, max( 120, (int) floor( (int) $current['word_count'] * 0.4 ) ) );
		$response           = $this->chat_completion( $messages, min( 6000, max( 3200, $this->max_tokens ) ), 0.55, false );
		$parsed             = $this->parse_full_post_response( $response, $current, $locked['blocks'], $minimum_word_count );

		if ( empty( $parsed['content'] ) ) {
			$retry_messages = $messages;
			$retry_messages[] = [
				'role'    => 'user',
				'content' => "Onceki yanit uygulanabilir WordPress HTML icerik degildi. Simdi yalnizca <article data-aiseo-output=\"1\">...</article> dondur. En az {$minimum_word_count} kelime temiz icerik yaz; analiz, JSON, markdown, not, ozur veya aciklama yazma.",
			];

			$retry_response = $this->chat_completion( $retry_messages, min( 7000, max( 4200, $this->max_tokens ) ), 0.5, false );
			$retry_parsed   = $this->parse_full_post_response( $retry_response, $current, $locked['blocks'], $minimum_word_count );

			if ( ! empty( $retry_parsed['content'] ) ) {
				$parsed = $retry_parsed;
				$response['total_tokens'] = (int) ( $response['total_tokens'] ?? 0 ) + (int) ( $retry_response['total_tokens'] ?? 0 );
				$response['content'] = $retry_response['content'] ?? '';
			}
		}

		if ( isset( $parsed['suggested_tags'] ) && is_array( $parsed['suggested_tags'] ) ) {
			$parsed['suggested_tags'] = $this->clean_tags( $parsed['suggested_tags'] );
		}

		if ( ! empty( $parsed['content'] ) ) {
			$parsed['content'] = $this->enforce_shortcode_placement( $content, $parsed['content'] );
		}

		$current_seo_title = (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
		if ( $current_seo_title === '' ) {
			$yoast->set_seo_title( $post_id, $current['title'] );
		}

		return array_merge( $parsed, [
			'tokens_used'  => $response['total_tokens'] ?? 0,
			'raw_response' => $response['content'] ?? '',
		] );
	}

	public function optimize_tags( int $post_id, string $keyword = '', string $content_override = '', array $current_tags = [] ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [];
		}

		$content = $content_override ?: ( $post->post_content ?? '' );
		$title   = get_the_title( $post_id );
		$current_tags = array_filter( array_map( 'sanitize_text_field', $current_tags ) );

		$messages = [
			[
				'role'    => 'system',
				'content' => 'Sen bir Turkce SEO etiket editorusun. Verilen WordPress yazisi icin temiz, odakli ve tekrar etmeyen etiket listesi uret. JSON dondur: {"tags":["..."]}. Kurallar: 5-8 etiket uret. Etiketler kucuk harfle yazilsin. Kullanici arama niyetini yansitan 2-4 kelimelik ifadeler kullan: "hesaplama", "maliyet", "nasil hesaplanir", "karsilastirma", "rehber" gibi niyetli kelimeler iceren etiketler tercih et. Odak keyword varyasyonlarini dogal kullan. Tek kelimelik, cok genel, soyut veya birbirini tekrar eden etiketlerden kacin. Marka, uydurma terim veya gereksiz trend etiketi ekleme. Mevcut etiketleri otomatik koruma; sadece kaliteli olanlari tut. Hashtag, virgul ve aciklama ekleme.',
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nBaslik: {$title}\nMevcut etiketler: " . implode( ', ', $current_tags ) . "\nIcerik:\n" . $this->limit_content_for_prompt( aiseo_strip_html( $content ), 5000 ),
			],
		];

		$response = $this->chat_completion( $messages, 900, 0.45, true );
		$parsed   = $this->parse_json_response( $response['content'] ?? '' );
		$tags     = isset( $parsed['tags'] ) && is_array( $parsed['tags'] ) ? $this->clean_tags( $parsed['tags'] ) : [];

		return [
			'tags'         => array_slice( $tags, 0, 8 ),
			'tokens_used'  => $response['total_tokens'] ?? 0,
			'raw_response' => $response['content'] ?? '',
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

		$lang_map = [ 'tr' => 'Turkce', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Francais', 'es' => 'Espanol' ];
		$lang_str = $lang_map[ $language ] ?? 'Turkce';

		$faq_note = $include_faq ? '- "faq" dizisi en az 5 soru-cevap icermeli' : '';
		$aux_note = $aux_kw ? "\nYardimci kelimeler: {$aux_kw}" : '';
		$cat_id   = absint( $params['category'] ?? 0 );
		$cat_note = $cat_id > 0 ? "\nKategori: " . $this->get_category_label( $cat_id ) : '';

		$messages = [
			[
				'role'    => 'system',
				'content' => "Sen uzman bir SEO icerik yazarisisin. Verilen parametrelerle tam makale uret. JSON formatinda dondur:
{\"title\":\"...\",\"meta_description\":\"...\",\"focus_keyword\":\"...\",\"content\":{\"introduction\":\"<p>...</p>\",\"sections\":[{\"heading\":\"H2 baslik\",\"content\":\"<p>...</p>\",\"subsections\":[{\"heading\":\"H3 baslik\",\"content\":\"<p>...</p>\"}]}],\"conclusion\":\"<p>...</p>\",\"faq\":[{\"question\":\"...\",\"answer\":\"...\"}]},\"word_count_estimate\":0,\"suggested_tags\":[]}
SEO KURALLARI: meta_description 140-155 karakter arasinda olacak ve 155 karakteri ASLA gecmeyecek; odak kelime birebir (aynen) meta_description icinde gecmeli. introduction alaninin ilk 100 karakterinde odak kelime birebir gecmeli. En az bir bolum basligi (H2) odak kelimeyi birebir icermeli. Anahtar kelime yogunlugunu %0.8-1.8 araliginda tut; keyword stuffing yapma. title alani 50-60 karakter olmali; verilen basliktan farkli ama ona yakin olmali. suggested_tags alani 5-8 adet, kucuk harfle, 2-4 kelimelik, kullanici arama niyeti tasiyan SEO etiketi icermeli; marka, uydurma terim veya tek kelimelik etiket ekleme. Icerik 1000 kelimenin altina dusturme; hedef kelime sayisi 1000'den az ayarlanmis olsa bile dogal hesaplama ornekleri, sonuc yorumlama ve sik yapilan hatalar gibi bolumlerle minimum 1000 kelimeye ulastirilsin.{$faq_note} Icerik {$lang_str} dilinde olacak, ton: {$tone}, yaklasik {$target_wc} kelime, Google EEAT prensiplerine uygun, dogal ve okuyucu odakli. Icerik bolumlerinde <h1> veya markdown # baslik KULLANMA; WordPress sayfa H1'i tema tarafindan basilir, icerik basliklar yalnizca H2/H3 olmali. " . $this->readability_instruction() . ' ' . $this->shortcode_instruction(),
			],
			[
				'role'    => 'user',
				'content' => "Odak kelime: {$keyword}\nBaslik: {$title}{$cat_note}{$aux_note}\nHedef kelime sayisi: {$target_wc}",
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
		$result = $this->test_connection_details();
		return (bool) ( $result['connected'] ?? false );
	}

	public function test_connection_details(): array {
		if ( empty( $this->api_key ) ) {
			return [
				'connected' => false,
				'message'   => 'AI API anahtari tanimlanmamis.',
			];
		}

		try {
			$result = $this->chat_completion(
				[ [ 'role' => 'user', 'content' => 'Baglanti testi. Tek kelimeyle OK yaz.' ] ],
				64,
				0.0
			);
			$connected = ! empty( $result['has_choice'] );
			return [
				'connected' => $connected,
				'message'   => $connected ? 'API baglantisi basarili.' : 'API yaniti beklenen bicimde donmedi.',
				'model'     => $result['model'] ?? $this->model,
			];
		} catch ( Throwable $e ) {
			return [
				'connected' => false,
				'message'   => $e->getMessage(),
			];
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
			throw new RuntimeException( 'AI API hatasi: ' . $http_code . '. ' . $error_msg );
		}

		$message       = is_array( $data['choices'][0]['message'] ?? null ) ? $data['choices'][0]['message'] : [];
		$content       = (string) ( $message['content'] ?? '' );
		if ( $content === '' && ! empty( $message['reasoning_content'] ) && empty( $payload['response_format'] ) ) {
			$content = (string) $message['reasoning_content'];
		}
		$input_tokens  = $data['usage']['prompt_tokens'] ?? 0;
		$output_tokens = $data['usage']['completion_tokens'] ?? 0;

		return [
			'content'       => $content,
			'has_choice'    => ! empty( $data['choices'] ),
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
			'total_tokens'  => $input_tokens + $output_tokens,
			'model'         => $data['model'] ?? $this->model,
			'finish_reason' => $data['choices'][0]['finish_reason'] ?? '',
			'raw_body'      => $body,
		];
	}

	private function get_category_label( int $cat_id ): string {
		if ( $cat_id <= 0 ) {
			return '';
		}
		$cat = get_term( $cat_id, 'category' );
		if ( ! $cat instanceof WP_Term ) {
			return '';
		}
		if ( $cat->parent > 0 ) {
			$parent = get_term( $cat->parent, 'category' );
			if ( $parent instanceof WP_Term ) {
				return $parent->name . ' > ' . $cat->name;
			}
		}
		return $cat->name;
	}

	private function protected_block_instruction(): string {
		return 'Icerikte AISEO_PROTECTED_BRACKET_BLOCK_* biciminde kilitli bloklar gorebilirsin. Bunlar yazidaki [ ... ] shortcode/hesaplama tablosu alanlaridir; silme, degistirme, bolme, cevirme veya yeniden yazma. Ciktida aynen ve mumkunse ayni konumda koru.';
	}

	private function shortcode_instruction(): string {
		return 'SHORTCODE KORUMA (KESIN KURAL): Icerik icinde [hc_...] ile baslayan shortcode varsa ASLA silme, degistirme, yeniden adlandirma, escape etme veya baska bir shortcode ile degistirme. Ham metin olarak bozma. Shortcode ciktida birebir, harfi harfine ayni sekilde yer almali. Yoksa yeni [hc_...] veya baska herhangi bir shortcode uydurma.';
	}

	private function readability_instruction(): string {
		return 'OKUNABILIRLIK KURALLARI: (1) Edilgen cati kaliplarini (hesaplanir, belirlenir, kullanilir, olculur, degerlendirilir, uygulanir, elde edilir, yapilir, edilir) mutlaka etken yap — ornek: "Bu arac degeri hesaplar.", "Kullanici sonucu gorur.", "Formul toplam maliyeti bulur." (2) Her 2-3 cumlede en az bir gecis ifadesi kullan: ayrica, bu nedenle, ornegin, boylece, sonuc olarak, ancak, bununla birlikte, ozellikle, kisaca, diger yandan, bu noktada, pratikte. (3) Cumleler cogunlukla 20 kelime altinda olsun. (4) Paragraflar 2-3 cumle ile sinirlandir; her paragraf tek fikre odaklansin.';
	}

	private function protect_bracket_blocks( string $content ): array {
		$blocks = [];
		$locked = preg_replace_callback(
			'/\[[^\[\]\r\n]{1,800}\]/u',
			function ( array $match ) use ( &$blocks ): string {
				$token            = 'AISEO_PROTECTED_BRACKET_BLOCK_' . count( $blocks );
				$blocks[ $token ] = $match[0];
				return $token;
			},
			$content
		);

		return [
			'content' => is_string( $locked ) ? $locked : $content,
			'blocks'  => $blocks,
		];
	}

	private function restore_bracket_blocks( string $content, array $blocks ): string {
		foreach ( $blocks as $token => $block ) {
			$content = str_replace( $token, $block, $content );
		}

		$missing = [];
		foreach ( $blocks as $block ) {
			if ( strpos( $content, $block ) === false ) {
				$missing[] = $block;
			}
		}

		if ( ! empty( $missing ) ) {
			$content = implode( "\n\n", $missing ) . "\n\n" . $content;
		}

		return $content;
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

		$start = strpos( $content, '{' );
		$end   = strrpos( $content, '}' );
		if ( $start !== false && $end !== false && $end > $start ) {
			$json = substr( $content, $start, $end - $start + 1 );
			$decoded = json_decode( $json, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return [ 'raw' => $content ];
	}

	private function parse_full_post_response( array $response, array $current, array $locked_blocks, int $minimum_word_count ): array {
		$parsed          = $this->parse_json_response( $response['content'] ?? '' );
		$article_content = $this->extract_article_output( (string) ( $response['content'] ?? '' ) );

		if ( $article_content !== '' ) {
			$parsed['content'] = $article_content;
			$parsed['meta_description'] = $parsed['meta_description'] ?? $current['meta_description'];
			$parsed['suggested_tags'] = $parsed['suggested_tags'] ?? [];
		}

		if ( empty( $parsed['content'] ) ) {
			$fallback_content = $this->extract_html_fallback( (string) ( $response['content'] ?? '' ) );
			if ( $fallback_content !== '' ) {
				$parsed = array_merge(
					[
						'meta_description' => $current['meta_description'],
						'suggested_tags'    => [],
					],
					$parsed,
					[ 'content' => $fallback_content ]
				);
			} else {
				$parsed['error'] = 'AI yaniti temiz WordPress HTML olarak okunamadi.';
			}
		}

		if ( ! empty( $parsed['content'] ) ) {
			$clean_content        = $this->clean_model_html( (string) $parsed['content'] );
			$generated_word_count = aiseo_count_words( $clean_content );

			if ( $this->looks_like_analysis_dump( $clean_content ) || $this->looks_like_raw_json_dump( $clean_content ) ) {
				unset( $parsed['content'] );
				$parsed['error'] = 'AI yaniti temiz icerik yerine analiz/JSON metni dondurdu. Icerik uygulanmadi.';
			} elseif ( $generated_word_count < $minimum_word_count ) {
				unset( $parsed['content'] );
				$parsed['error'] = 'AI yaniti cok kisa dondu. Icerik uygulanmadi.';
			} else {
				$parsed['content'] = $this->restore_bracket_blocks( $clean_content, $locked_blocks );
			}
		}

		if ( isset( $parsed['title'] ) ) {
			$parsed['title'] = aiseo_normalize_seo_title( (string) $parsed['title'] );
		}

		return $parsed;
	}

	private function extract_html_fallback( string $content ): string {
		$content = $this->clean_model_html( $content );
		if ( $content === '' ) {
			return '';
		}

		if ( $this->looks_like_analysis_dump( $content ) || $this->looks_like_raw_json_dump( $content ) ) {
			return '';
		}

		if ( preg_match( '/<(p|h2|h3|ul|ol|blockquote)\b/i', $content ) ) {
			return $content;
		}

		if ( mb_strlen( aiseo_strip_html( $content ) ) > 600 ) {
			return wpautop( wp_strip_all_tags( $content ) );
		}

		return '';
	}

	private function extract_article_output( string $content ): string {
		if ( preg_match( '/<article\b[^>]*data-aiseo-output=["\']?1["\']?[^>]*>(.*?)<\/article>/is', $content, $match ) ) {
			return $this->clean_model_html( $match[1] );
		}
		if ( preg_match( '/<article\b[^>]*>(.*?)<\/article>/is', $content, $match ) && ! $this->looks_like_analysis_dump( $match[1] ) ) {
			return $this->clean_model_html( $match[1] );
		}
		return '';
	}

	private function looks_like_analysis_dump( string $content ): bool {
		$text = mb_strtolower( aiseo_strip_html( $content ) );
		$signals = [
			'we need to improve',
			'yoast criteria',
			'current content',
			'keyword density',
			'meta description',
			'need to',
			'let\'s',
			'actually',
			'analysis',
		];
		$hits = 0;
		foreach ( $signals as $signal ) {
			if ( str_contains( $text, $signal ) ) {
				$hits++;
			}
		}
		return $hits >= 3;
	}

	private function looks_like_raw_json_dump( string $content ): bool {
		$trimmed = trim( $content );
		if ( $trimmed === '' ) {
			return false;
		}

		if ( str_starts_with( $trimmed, '{' ) && preg_match( '/"(title|meta_description|content)"\s*:/i', $trimmed ) ) {
			return true;
		}

		return preg_match( '/^\s*\{\s*"title"\s*:/i', $trimmed ) === 1;
	}

	private function limit_content_for_prompt( string $content, int $limit ): string {
		$content = trim( $content );
		if ( mb_strlen( $content ) <= $limit ) {
			return $content;
		}

		return mb_substr( $content, 0, $limit ) . "\n\nNot: Icerik uzun oldugu icin burada kesildi; verilen bolumu butunlugunu koruyarak iyilestir.";
	}

	private function clean_model_html( string $html ): string {
		$html = trim( $html );
		$html = preg_replace( '/^\s*```(?:html|HTML)?\s*/', '', $html );
		$html = preg_replace( '/\s*```\s*$/', '', $html );
		$html = preg_replace( '/^\s*(?:<!doctype\s+html[^>]*>|<html[^>]*>|<body[^>]*>)/i', '', $html );
		$html = preg_replace( '/(?:<\/body>|<\/html>)\s*$/i', '', $html );
		$html = aiseo_demote_content_h1_to_h2( trim( (string) $html ) );

		return trim( $html );
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

	private function extract_hc_shortcode( string $content ): ?string {
		if ( preg_match( '/\[hc_[^\[\]]{1,300}\]/u', $content, $match ) ) {
			return $match[0];
		}
		return null;
	}

	private function remove_hc_shortcodes( string $content ): string {
		$result = preg_replace( '/[ \t]*\[hc_[^\[\]]{1,300}\][ \t]*/u', '', $content );
		if ( ! is_string( $result ) ) {
			return $content;
		}
		$result = preg_replace( '/\n{3,}/', "\n\n", $result );
		return trim( $result );
	}

	private function place_shortcode_after_intro( string $html, string $shortcode ): string {
		$p_close_pos = stripos( $html, '</p>' );
		$h2_open_pos = stripos( $html, '<h2' );

		if ( $p_close_pos !== false && ( $h2_open_pos === false || $p_close_pos < $h2_open_pos ) ) {
			return preg_replace( '/<\/p>/i', '</p>' . "\n\n" . $shortcode, $html, 1 ) ?? $html;
		}

		if ( $h2_open_pos !== false ) {
			$before         = substr( $html, 0, $h2_open_pos );
			$after          = substr( $html, $h2_open_pos );
			$before_trimmed = rtrim( $before );
			if ( $before_trimmed !== '' ) {
				return $before_trimmed . "\n\n" . $shortcode . "\n\n" . ltrim( $after );
			}
			return $shortcode . "\n\n" . ltrim( $after );
		}

		return $shortcode . "\n\n" . $html;
	}

	private function enforce_shortcode_placement( string $original_content, string $ai_html ): string {
		$original_shortcode = $this->extract_hc_shortcode( $original_content );
		$clean_html         = $this->remove_hc_shortcodes( $ai_html );
		if ( $original_shortcode !== null ) {
			return $this->place_shortcode_after_intro( $clean_html, $original_shortcode );
		}
		return $clean_html;
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
