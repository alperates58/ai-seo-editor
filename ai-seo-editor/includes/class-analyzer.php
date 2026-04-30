<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Analyzer {

	private AISEO_Yoast_Integration $yoast;
	private AISEO_Readability $readability;
	private AISEO_Scorer $scorer;

	public function __construct() {
		$this->yoast       = new AISEO_Yoast_Integration();
		$this->readability = new AISEO_Readability();
		$this->scorer      = new AISEO_Scorer();
	}

	public function analyze( int $post_id, bool $force = false ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [ 'error' => __( 'Yazı bulunamadı.', 'ai-seo-editor' ) ];
		}

		$data       = $this->prepare_post_data( $post );
		$cache_hash = md5( $data['content_raw'] . $data['keyword'] );

		if ( ! $force ) {
			$logger = AISEO_Plugin::get_instance()->get_logger();
			$cached = $logger->get_cached_analysis( $post_id, $cache_hash );
			if ( $cached ) {
				return array_merge( $cached, [ 'from_cache' => true ] );
			}
		}

		$seo_criteria          = $this->run_seo_checks( $data );
		$readability_criteria  = $this->readability->analyze( $data['content_html'] );

		$seo_score         = $this->scorer->calculate_seo_score( $seo_criteria );
		$readability_score = $this->scorer->calculate_readability_score( $readability_criteria );

		$all_criteria = array_merge( $seo_criteria, $readability_criteria );

		$logger = AISEO_Plugin::get_instance()->get_logger();
		$logger->log_analysis(
			$post_id,
			$seo_score,
			$readability_score,
			$all_criteria,
			$data['keyword'],
			$data['word_count'],
			$data['keyword_density'],
			$cache_hash
		);

		return [
			'post_id'            => $post_id,
			'post_title'         => $data['title'],
			'permalink'          => $data['permalink'],
			'keyword'            => $data['keyword'],
			'word_count'         => $data['word_count'],
			'keyword_density'    => $data['keyword_density'],
			'seo_score'          => $seo_score,
			'readability_score'  => $readability_score,
			'seo_criteria'       => $seo_criteria,
			'readability_criteria' => $readability_criteria,
			'internal_links'     => $data['links']['internal'],
			'external_links'     => $data['links']['external'],
			'images'             => $data['images'],
			'headings'           => $data['headings'],
			'from_cache'         => false,
			'analyzed_at'        => current_time( 'mysql' ),
		];
	}

	private function prepare_post_data( WP_Post $post ): array {
		$content_html = apply_filters( 'the_content', $post->post_content );
		$content_raw  = aiseo_strip_html( $content_html );

		$keyword          = $this->yoast->get_focus_keyword( $post->ID );
		$meta_description = $this->yoast->get_meta_description( $post->ID );
		$seo_title        = $this->yoast->get_seo_title( $post->ID );
		$permalink        = get_permalink( $post->ID );
		$slug             = $post->post_name;

		$word_count      = aiseo_count_words( $content_raw );
		$keyword_density = aiseo_keyword_density( $content_raw, $keyword );

		$first_paragraph = aiseo_get_first_paragraph( $content_html );
		$headings        = aiseo_extract_headings( $content_html );
		$links           = aiseo_extract_links( $content_html );
		$images          = aiseo_extract_images( $content_html );

		return compact(
			'content_html', 'content_raw', 'keyword', 'meta_description',
			'seo_title', 'permalink', 'slug', 'word_count', 'keyword_density',
			'first_paragraph', 'headings', 'links', 'images'
		) + [
			'title'      => $post->post_title,
			'post_id'    => $post->ID,
			'post_type'  => $post->post_type,
		];
	}

	private function run_seo_checks( array $data ): array {
		return [
			$this->check_focus_keyword_present( $data ),
			$this->check_keyword_in_title( $data ),
			$this->check_keyword_in_meta_description( $data ),
			$this->check_keyword_in_first_paragraph( $data ),
			$this->check_keyword_in_url( $data ),
			$this->check_keyword_density( $data ),
			$this->check_title_length( $data ),
			$this->check_meta_description_length( $data ),
			$this->check_content_length( $data ),
			$this->check_images_present( $data ),
			$this->check_image_alt_text( $data ),
			$this->check_internal_links( $data ),
			$this->check_external_links( $data ),
			$this->check_headings_structure( $data ),
			$this->check_keyword_in_headings( $data ),
			$this->check_schema_markup( $data ),
			$this->check_canonical_tag( $data ),
			$this->check_og_tags( $data ),
			$this->check_outbound_link_quality( $data ),
		];
	}

	private function check_focus_keyword_present( array $data ): array {
		if ( ! empty( $data['keyword'] ) ) {
			return $this->criterion( 'focus_keyword_present', 'good',
				__( 'Odak anahtar kelime tanımlanmış.', 'ai-seo-editor' ), $data['keyword'] );
		}
		return $this->criterion( 'focus_keyword_present', 'error',
			__( 'Odak anahtar kelime tanımlanmamış. Lütfen bir odak kelime belirleyin.', 'ai-seo-editor' ), '' );
	}

	private function check_keyword_in_title( array $data ): array {
		$label = __( 'Anahtar kelime başlıkta geçiyor', 'ai-seo-editor' );
		if ( empty( $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_title', 'error', __( 'Odak anahtar kelime yok.', 'ai-seo-editor' ), '' );
		}
		if ( aiseo_keyword_in_text( $data['seo_title'], $data['keyword'] ) ||
			 aiseo_keyword_in_text( $data['title'], $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_title', 'good',
				__( 'Anahtar kelime SEO başlığında kullanılmış.', 'ai-seo-editor' ), $data['keyword'] );
		}
		return $this->criterion( 'keyword_in_title', 'error',
			__( 'Anahtar kelime başlıkta geçmiyor. SEO başlığına ekleyin.', 'ai-seo-editor' ), '' );
	}

	private function check_keyword_in_meta_description( array $data ): array {
		if ( empty( $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_meta_description', 'error', __( 'Odak anahtar kelime yok.', 'ai-seo-editor' ), '' );
		}
		if ( ! empty( $data['meta_description'] ) && aiseo_keyword_in_text( $data['meta_description'], $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_meta_description', 'good',
				__( 'Anahtar kelime meta açıklamada kullanılmış.', 'ai-seo-editor' ), $data['keyword'] );
		}
		if ( empty( $data['meta_description'] ) ) {
			return $this->criterion( 'keyword_in_meta_description', 'error',
				__( 'Meta açıklama eksik.', 'ai-seo-editor' ), '' );
		}
		return $this->criterion( 'keyword_in_meta_description', 'warning',
			__( 'Anahtar kelime meta açıklamada geçmiyor.', 'ai-seo-editor' ), $data['meta_description'] );
	}

	private function check_keyword_in_first_paragraph( array $data ): array {
		if ( empty( $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_first_paragraph', 'error', __( 'Odak anahtar kelime yok.', 'ai-seo-editor' ), '' );
		}
		if ( aiseo_keyword_in_text( $data['first_paragraph'], $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_first_paragraph', 'good',
				__( 'Anahtar kelime ilk paragrafta kullanılmış.', 'ai-seo-editor' ), $data['keyword'] );
		}
		return $this->criterion( 'keyword_in_first_paragraph', 'error',
			__( 'Anahtar kelime ilk paragrafta geçmiyor. İlk 100 kelimede kullanın.', 'ai-seo-editor' ), '' );
	}

	private function check_keyword_in_url( array $data ): array {
		if ( empty( $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_url', 'error', __( 'Odak anahtar kelime yok.', 'ai-seo-editor' ), '' );
		}
		$slug_clean    = str_replace( [ '-', '_' ], ' ', urldecode( $data['slug'] ) );
		$keyword_clean = mb_strtolower( $data['keyword'] );
		if ( mb_strpos( mb_strtolower( $slug_clean ), $keyword_clean ) !== false ||
			 mb_strpos( mb_strtolower( $data['slug'] ), str_replace( ' ', '-', $keyword_clean ) ) !== false ) {
			return $this->criterion( 'keyword_in_url', 'good',
				__( 'Anahtar kelime URL\'de kullanılmış.', 'ai-seo-editor' ), $data['slug'] );
		}
		return $this->criterion( 'keyword_in_url', 'warning',
			__( 'Anahtar kelime URL\'de geçmiyor. Slug\'ı güncellemeyi deneyin.', 'ai-seo-editor' ), $data['slug'] );
	}

	private function check_keyword_density( array $data ): array {
		if ( empty( $data['keyword'] ) ) {
			return $this->criterion( 'keyword_density', 'error', __( 'Odak anahtar kelime yok.', 'ai-seo-editor' ), 0 );
		}
		$density = $data['keyword_density'];
		if ( $density >= 0.5 && $density <= 2.5 ) {
			return $this->criterion( 'keyword_density', 'good',
				sprintf( __( 'Anahtar kelime yoğunluğu doğal: %%%.2f', 'ai-seo-editor' ), $density ), $density );
		}
		if ( $density > 2.5 ) {
			return $this->criterion( 'keyword_density', 'warning',
				sprintf( __( 'Anahtar kelime yoğunluğu yüksek (%%%.2f). Keyword stuffing riski.', 'ai-seo-editor' ), $density ), $density );
		}
		return $this->criterion( 'keyword_density', 'warning',
			sprintf( __( 'Anahtar kelime yoğunluğu düşük (%%%.2f). Doğal kullanımı artırın.', 'ai-seo-editor' ), $density ), $density );
	}

	private function check_title_length( array $data ): array {
		$title  = ! empty( $data['seo_title'] ) ? $data['seo_title'] : $data['title'];
		$length = mb_strlen( $title );
		if ( $length >= 50 && $length <= 60 ) {
			return $this->criterion( 'title_length', 'good',
				sprintf( __( 'Başlık uzunluğu mükemmel: %d karakter.', 'ai-seo-editor' ), $length ), $length );
		}
		if ( $length >= 40 && $length <= 70 ) {
			return $this->criterion( 'title_length', 'warning',
				sprintf( __( 'Başlık uzunluğu kabul edilebilir: %d karakter (ideal: 50-60).', 'ai-seo-editor' ), $length ), $length );
		}
		return $this->criterion( 'title_length', 'error',
			sprintf( __( 'Başlık uzunluğu uygun değil: %d karakter (ideal: 50-60).', 'ai-seo-editor' ), $length ), $length );
	}

	private function check_meta_description_length( array $data ): array {
		if ( empty( $data['meta_description'] ) ) {
			return $this->criterion( 'meta_description_length', 'error',
				__( 'Meta açıklama eksik. 120-158 karakter arası bir açıklama yazın.', 'ai-seo-editor' ), 0 );
		}
		$length = mb_strlen( $data['meta_description'] );
		if ( $length >= 120 && $length <= 158 ) {
			return $this->criterion( 'meta_description_length', 'good',
				sprintf( __( 'Meta açıklama uzunluğu mükemmel: %d karakter.', 'ai-seo-editor' ), $length ), $length );
		}
		if ( $length >= 100 && $length <= 170 ) {
			return $this->criterion( 'meta_description_length', 'warning',
				sprintf( __( 'Meta açıklama uzunluğu kabul edilebilir: %d karakter (ideal: 120-158).', 'ai-seo-editor' ), $length ), $length );
		}
		return $this->criterion( 'meta_description_length', 'error',
			sprintf( __( 'Meta açıklama %d karakter — ideal: 120-158.', 'ai-seo-editor' ), $length ), $length );
	}

	private function check_content_length( array $data ): array {
		$wc = $data['word_count'];
		if ( $wc >= 1000 ) {
			return $this->criterion( 'content_length', 'good',
				sprintf( __( 'Yazı uzunluğu yeterli: %d kelime.', 'ai-seo-editor' ), $wc ), $wc );
		}
		if ( $wc >= 600 ) {
			return $this->criterion( 'content_length', 'warning',
				sprintf( __( 'Yazı biraz kısa: %d kelime. 1000+ kelime önerilir.', 'ai-seo-editor' ), $wc ), $wc );
		}
		if ( $wc >= 300 ) {
			return $this->criterion( 'content_length', 'warning',
				sprintf( __( 'Yazı kısa: %d kelime. Kapsamı artırın.', 'ai-seo-editor' ), $wc ), $wc );
		}
		return $this->criterion( 'content_length', 'error',
			sprintf( __( 'Yazı çok kısa: %d kelime. En az 300 kelime önerilir.', 'ai-seo-editor' ), $wc ), $wc );
	}

	private function check_images_present( array $data ): array {
		$count = count( $data['images'] );
		if ( $count >= 1 ) {
			return $this->criterion( 'images_present', 'good',
				sprintf( __( '%d görsel kullanılmış.', 'ai-seo-editor' ), $count ), $count );
		}
		return $this->criterion( 'images_present', 'warning',
			__( 'Görselsiz yazı. En az 1 görsel ekleyin.', 'ai-seo-editor' ), 0 );
	}

	private function check_image_alt_text( array $data ): array {
		$images = $data['images'];
		if ( empty( $images ) ) {
			return $this->criterion( 'image_alt_text', 'warning',
				__( 'Görsel yok, alt metin kontrolü yapılamıyor.', 'ai-seo-editor' ), 0 );
		}
		$missing = 0;
		foreach ( $images as $img ) {
			if ( empty( $img['alt'] ) ) {
				$missing++;
			}
		}
		if ( $missing === 0 ) {
			return $this->criterion( 'image_alt_text', 'good',
				__( 'Tüm görsellerde alt metin var.', 'ai-seo-editor' ), 0 );
		}
		return $this->criterion( 'image_alt_text', 'error',
			sprintf( __( '%d görselde alt metin eksik.', 'ai-seo-editor' ), $missing ), $missing );
	}

	private function check_internal_links( array $data ): array {
		$count = count( $data['links']['internal'] );
		if ( $count >= 2 ) {
			return $this->criterion( 'internal_links', 'good',
				sprintf( __( '%d iç link var.', 'ai-seo-editor' ), $count ), $count );
		}
		if ( $count === 1 ) {
			return $this->criterion( 'internal_links', 'warning',
				__( 'Yalnızca 1 iç link var. En az 2-3 iç link ekleyin.', 'ai-seo-editor' ), $count );
		}
		return $this->criterion( 'internal_links', 'error',
			__( 'İç link yok. Siteye bağlı diğer yazılara link ekleyin.', 'ai-seo-editor' ), 0 );
	}

	private function check_external_links( array $data ): array {
		$count = count( $data['links']['external'] );
		if ( $count >= 1 ) {
			return $this->criterion( 'external_links', 'good',
				sprintf( __( '%d dış link var.', 'ai-seo-editor' ), $count ), $count );
		}
		return $this->criterion( 'external_links', 'warning',
			__( 'Dış link yok. Güvenilir kaynaklara link ekleyin.', 'ai-seo-editor' ), 0 );
	}

	private function check_headings_structure( array $data ): array {
		$h2_count = count( array_filter( $data['headings'], fn( $h ) => $h['level'] === 'h2' ) );
		$h3_count = count( array_filter( $data['headings'], fn( $h ) => $h['level'] === 'h3' ) );

		if ( $h2_count >= 2 ) {
			return $this->criterion( 'headings_structure', 'good',
				sprintf( __( 'Başlık yapısı iyi: %d H2, %d H3.', 'ai-seo-editor' ), $h2_count, $h3_count ),
				$h2_count );
		}
		if ( $h2_count >= 1 ) {
			return $this->criterion( 'headings_structure', 'warning',
				__( 'Yalnızca 1 H2 başlık var. Daha fazla alt başlık ekleyin.', 'ai-seo-editor' ), $h2_count );
		}
		return $this->criterion( 'headings_structure', 'error',
			__( 'H2 başlık yok. İçeriği bölümlere ayırmak için H2 başlıklar kullanın.', 'ai-seo-editor' ), 0 );
	}

	private function check_keyword_in_headings( array $data ): array {
		if ( empty( $data['keyword'] ) ) {
			return $this->criterion( 'keyword_in_headings', 'error', __( 'Odak anahtar kelime yok.', 'ai-seo-editor' ), '' );
		}
		foreach ( $data['headings'] as $heading ) {
			if ( $heading['level'] === 'h2' && aiseo_keyword_in_text( $heading['text'], $data['keyword'] ) ) {
				return $this->criterion( 'keyword_in_headings', 'good',
					__( 'Anahtar kelime en az bir H2 başlıkta kullanılmış.', 'ai-seo-editor' ), $data['keyword'] );
			}
		}
		if ( ! empty( $data['headings'] ) ) {
			return $this->criterion( 'keyword_in_headings', 'warning',
				__( 'Anahtar kelime H2 başlıklarda geçmiyor. En az birine ekleyin.', 'ai-seo-editor' ), '' );
		}
		return $this->criterion( 'keyword_in_headings', 'error',
			__( 'Başlık yok ve anahtar kelime kullanılmamış.', 'ai-seo-editor' ), '' );
	}

	private function check_schema_markup( array $data ): array {
		if ( mb_strpos( $data['content_html'], 'application/ld+json' ) !== false ||
			 mb_strpos( $data['content_html'], 'itemtype' ) !== false ||
			 class_exists( 'WPSEO_Schema' ) ) {
			return $this->criterion( 'schema_markup', 'good',
				__( 'Schema markup tespit edildi.', 'ai-seo-editor' ), true );
		}
		return $this->criterion( 'schema_markup', 'warning',
			__( 'Schema markup bulunamadı. Yoast SEO veya Schema eklentisi kullanmayı düşünün.', 'ai-seo-editor' ), false );
	}

	private function check_canonical_tag( array $data ): array {
		$canonical = get_post_meta( $data['post_id'], '_yoast_wpseo_canonical', true );
		if ( ! empty( $canonical ) || $this->yoast->is_yoast_active() ) {
			return $this->criterion( 'canonical_tag', 'good',
				__( 'Canonical tag Yoast SEO üzerinden yönetiliyor.', 'ai-seo-editor' ), true );
		}
		return $this->criterion( 'canonical_tag', 'warning',
			__( 'Canonical tag durumu doğrulanamadı. Yoast SEO kurulumu önerilir.', 'ai-seo-editor' ), false );
	}

	private function check_og_tags( array $data ): array {
		if ( $this->yoast->is_yoast_active() ) {
			return $this->criterion( 'og_tags', 'good',
				__( 'Open Graph etiketleri Yoast SEO tarafından yönetiliyor.', 'ai-seo-editor' ), true );
		}
		if ( is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) ||
			 defined( 'RANK_MATH_VERSION' ) ) {
			return $this->criterion( 'og_tags', 'good',
				__( 'Open Graph etiketleri bir SEO eklentisi tarafından yönetiliyor.', 'ai-seo-editor' ), true );
		}
		return $this->criterion( 'og_tags', 'warning',
			__( 'Open Graph etiketleri kontrol edilemiyor. Bir SEO eklentisi kullanın.', 'ai-seo-editor' ), false );
	}

	private function check_outbound_link_quality( array $data ): array {
		$external = $data['links']['external'];
		if ( empty( $external ) ) {
			return $this->criterion( 'outbound_link_quality', 'warning',
				__( 'Dış link yok.', 'ai-seo-editor' ), 0 );
		}
		$dofollow = 0;
		foreach ( $external as $link ) {
			if ( mb_strpos( $data['content_html'], 'rel="nofollow"' ) === false ) {
				$dofollow++;
			}
		}
		return $this->criterion( 'outbound_link_quality', 'good',
			sprintf( __( '%d dış link tespit edildi.', 'ai-seo-editor' ), count( $external ) ), count( $external ) );
	}

	private function criterion( string $id, string $status, string $message, mixed $value = null ): array {
		$labels = [
			'focus_keyword_present'       => __( 'Odak anahtar kelime', 'ai-seo-editor' ),
			'keyword_in_title'            => __( 'Başlıkta anahtar kelime', 'ai-seo-editor' ),
			'keyword_in_meta_description' => __( 'Meta açıklamada anahtar kelime', 'ai-seo-editor' ),
			'keyword_in_first_paragraph'  => __( 'İlk paragrafta anahtar kelime', 'ai-seo-editor' ),
			'keyword_in_url'              => __( 'URL\'de anahtar kelime', 'ai-seo-editor' ),
			'keyword_density'             => __( 'Anahtar kelime yoğunluğu', 'ai-seo-editor' ),
			'title_length'                => __( 'SEO başlık uzunluğu', 'ai-seo-editor' ),
			'meta_description_length'     => __( 'Meta açıklama uzunluğu', 'ai-seo-editor' ),
			'content_length'              => __( 'İçerik uzunluğu', 'ai-seo-editor' ),
			'images_present'              => __( 'Görsel kullanımı', 'ai-seo-editor' ),
			'image_alt_text'              => __( 'Görsel alt metni', 'ai-seo-editor' ),
			'internal_links'              => __( 'İç bağlantılar', 'ai-seo-editor' ),
			'external_links'              => __( 'Dış bağlantılar', 'ai-seo-editor' ),
			'headings_structure'          => __( 'Başlık yapısı (H2/H3)', 'ai-seo-editor' ),
			'keyword_in_headings'         => __( 'Başlıklarda anahtar kelime', 'ai-seo-editor' ),
			'schema_markup'               => __( 'Schema işaretlemesi', 'ai-seo-editor' ),
			'canonical_tag'               => __( 'Canonical etiket', 'ai-seo-editor' ),
			'og_tags'                     => __( 'Open Graph etiketleri', 'ai-seo-editor' ),
			'outbound_link_quality'       => __( 'Dış link kalitesi', 'ai-seo-editor' ),
		];

		return [
			'id'      => $id,
			'label'   => $labels[ $id ] ?? $id,
			'status'  => $status,
			'message' => $message,
			'value'   => $value,
		];
	}

	private function result( string $id, string $status, string $message, mixed $value = null ): array {
		return $this->criterion( $id, $status, $message, $value );
	}
}
