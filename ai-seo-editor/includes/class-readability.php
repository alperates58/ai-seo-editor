<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Readability {

	private array $transition_words_tr = [
		'ayrıca', 'bunun yanı sıra', 'öte yandan', 'bununla birlikte', 'dolayısıyla',
		'bu nedenle', 'sonuç olarak', 'özellikle', 'örneğin', 'yani', 'ancak', 'fakat',
		'oysa', 'halbuki', 'ne var ki', 'buna karşın', 'buna rağmen', 'üstelik',
		'dahası', 'nitekim', 'kısacası', 'özetle', 'sonuçta', 'ilk olarak',
		'ikinci olarak', 'üçüncü olarak', 'son olarak', 'öncelikle', 'ardından',
		'daha sonra', 'böylece', 'bu sayede', 'bu durumda', 'aslında', 'gerçekten',
	];

	private array $transition_words_en = [
		'additionally', 'furthermore', 'moreover', 'however', 'therefore', 'thus',
		'consequently', 'meanwhile', 'nevertheless', 'nonetheless', 'in addition',
		'in contrast', 'on the other hand', 'for example', 'for instance', 'in fact',
		'as a result', 'in conclusion', 'first', 'second', 'third', 'finally',
		'firstly', 'secondly', 'lastly', 'also', 'besides', 'instead', 'otherwise',
		'similarly', 'likewise', 'specifically', 'especially', 'particularly',
	];

	private array $passive_indicators_tr = [
		'edildi', 'edilmiş', 'edilmekte', 'edilecek', 'edilir', 'edilmez',
		'yapıldı', 'yapılmış', 'yapılmakta', 'yapılacak', 'yapılır',
		'verildi', 'verilmiş', 'verilmekte', 'verilecek',
		'alındı', 'alınmış', 'alınmakta', 'alınacak',
		'görüldü', 'görülmüş', 'görülmekte', 'görülecek',
		'söylendi', 'söylenmiş', 'söylenmekte', 'söylenecek',
		'bulundu', 'bulunmuş', 'bulunmakta', 'bulunacak',
	];

	public function analyze( string $html ): array {
		$text = aiseo_strip_html( $html );

		$criteria = [
			$this->check_sentence_length( $text ),
			$this->check_paragraph_length( $html ),
			$this->check_passive_voice( $text ),
			$this->check_transition_words( $text ),
			$this->check_consecutive_sentences( $text ),
			$this->check_subheading_distribution( $html ),
			$this->check_flesch_reading_ease( $text ),
			$this->check_text_complexity( $text ),
		];

		return $criteria;
	}

	private function check_sentence_length( string $text ): array {
		$sentences   = $this->split_sentences( $text );
		$total       = count( $sentences );
		if ( $total === 0 ) {
			return $this->result( 'sentence_length', 'warning', __( 'Metin analiz edilemedi.', 'ai-seo-editor' ), 0 );
		}

		$long_count = 0;
		foreach ( $sentences as $sentence ) {
			$wc = aiseo_count_words( $sentence );
			if ( $wc > 20 ) {
				$long_count++;
			}
		}
		$ratio = $long_count / $total;

		if ( $ratio <= 0.25 ) {
			return $this->result( 'sentence_length', 'good',
				__( 'Cümle uzunlukları uygun.', 'ai-seo-editor' ), $ratio );
		}
		if ( $ratio <= 0.40 ) {
			return $this->result( 'sentence_length', 'warning',
				sprintf( __( 'Cümlelerin %%%.0f\'i 20 kelimeden uzun. Kısaltmayı deneyin.', 'ai-seo-editor' ), $ratio * 100 ),
				$ratio );
		}
		return $this->result( 'sentence_length', 'error',
			sprintf( __( 'Cümlelerin %%%.0f\'i çok uzun (20+ kelime). Metni bölerek kısaltın.', 'ai-seo-editor' ), $ratio * 100 ),
			$ratio );
	}

	private function check_paragraph_length( string $html ): array {
		$paragraphs = $this->split_paragraphs( $html );
		$total      = count( $paragraphs );
		if ( $total === 0 ) {
			return $this->result( 'paragraph_length', 'warning', __( 'Paragraf bulunamadı.', 'ai-seo-editor' ), 0 );
		}

		$long_count = 0;
		foreach ( $paragraphs as $para ) {
			if ( aiseo_count_words( $para ) > 150 ) {
				$long_count++;
			}
		}
		$ratio = $long_count / $total;

		if ( $ratio <= 0.2 ) {
			return $this->result( 'paragraph_length', 'good',
				__( 'Paragraf uzunlukları uygun.', 'ai-seo-editor' ), $ratio );
		}
		if ( $ratio <= 0.4 ) {
			return $this->result( 'paragraph_length', 'warning',
				__( 'Bazı paragraflar çok uzun. 150 kelimeden kısa tutun.', 'ai-seo-editor' ), $ratio );
		}
		return $this->result( 'paragraph_length', 'error',
			__( 'Paragrafların büyük çoğunluğu çok uzun. Bölün.', 'ai-seo-editor' ), $ratio );
	}

	private function check_passive_voice( string $text ): array {
		$sentences  = $this->split_sentences( $text );
		$total      = count( $sentences );
		if ( $total === 0 ) {
			return $this->result( 'passive_voice', 'warning', __( 'Metin analiz edilemedi.', 'ai-seo-editor' ), 0 );
		}

		$passive_count = 0;
		foreach ( $sentences as $sentence ) {
			if ( $this->has_passive_voice( $sentence ) ) {
				$passive_count++;
			}
		}
		$ratio = $passive_count / $total;

		if ( $ratio <= 0.10 ) {
			return $this->result( 'passive_voice', 'good',
				__( 'Pasif anlatım oranı düşük.', 'ai-seo-editor' ), $ratio );
		}
		if ( $ratio <= 0.20 ) {
			return $this->result( 'passive_voice', 'warning',
				sprintf( __( 'Cümlelerin %%%.0f\'i pasif anlatım içeriyor.', 'ai-seo-editor' ), $ratio * 100 ), $ratio );
		}
		return $this->result( 'passive_voice', 'error',
			sprintf( __( 'Pasif anlatım çok yüksek (%%%.0f). Etken cümle yapısı kullanın.', 'ai-seo-editor' ), $ratio * 100 ), $ratio );
	}

	private function check_transition_words( string $text ): array {
		$sentences = $this->split_sentences( $text );
		$total     = count( $sentences );
		if ( $total === 0 ) {
			return $this->result( 'transition_words', 'warning', __( 'Metin analiz edilemedi.', 'ai-seo-editor' ), 0 );
		}

		$all_transitions = array_merge( $this->transition_words_tr, $this->transition_words_en );
		$transition_count = 0;

		foreach ( $sentences as $sentence ) {
			$lower = mb_strtolower( $sentence );
			foreach ( $all_transitions as $word ) {
				if ( mb_strpos( $lower, $word ) !== false ) {
					$transition_count++;
					break;
				}
			}
		}
		$ratio = $transition_count / $total;

		if ( $ratio >= 0.30 ) {
			return $this->result( 'transition_words', 'good',
				sprintf( __( 'Geçiş kelime kullanımı iyi (%%%.0f).', 'ai-seo-editor' ), $ratio * 100 ), $ratio );
		}
		if ( $ratio >= 0.15 ) {
			return $this->result( 'transition_words', 'warning',
				sprintf( __( 'Geçiş kelimeleri az (%%%.0f). "Ayrıca", "Bu nedenle" gibi kelimeler ekleyin.', 'ai-seo-editor' ), $ratio * 100 ), $ratio );
		}
		return $this->result( 'transition_words', 'error',
			__( 'Geçiş kelimeleri çok az. Okuyucu akışı bozuk görünüyor.', 'ai-seo-editor' ), $ratio );
	}

	private function check_consecutive_sentences( string $text ): array {
		$sentences = $this->split_sentences( $text );
		if ( count( $sentences ) < 3 ) {
			return $this->result( 'consecutive_sentences', 'good', __( 'Yeterli cümle yok.', 'ai-seo-editor' ), 0 );
		}

		$max_consecutive = 0;
		$current_start   = '';
		$streak          = 1;

		for ( $i = 1; $i < count( $sentences ); $i++ ) {
			$start = mb_substr( trim( $sentences[ $i ] ), 0, 5 );
			$prev  = mb_substr( trim( $sentences[ $i - 1 ] ), 0, 5 );
			if ( $start === $prev && ! empty( $start ) ) {
				$streak++;
				$max_consecutive = max( $max_consecutive, $streak );
			} else {
				$streak = 1;
			}
		}

		if ( $max_consecutive <= 2 ) {
			return $this->result( 'consecutive_sentences', 'good',
				__( 'Cümle başlangıçlarında çeşitlilik var.', 'ai-seo-editor' ), $max_consecutive );
		}
		return $this->result( 'consecutive_sentences', 'warning',
			__( '3 veya daha fazla cümle aynı kelimeyle başlıyor. Çeşitlendirin.', 'ai-seo-editor' ), $max_consecutive );
	}

	private function check_subheading_distribution( string $html ): array {
		$word_count = aiseo_count_words( aiseo_strip_html( $html ) );
		$headings   = aiseo_extract_headings( $html );
		$h2_count   = count( array_filter( $headings, fn( $h ) => $h['level'] === 'h2' ) );

		if ( $word_count < 300 ) {
			return $this->result( 'subheading_distribution', 'good',
				__( 'Kısa metin için başlık yapısı yeterli.', 'ai-seo-editor' ), $h2_count );
		}

		$needed = max( 1, (int) floor( $word_count / 350 ) );

		if ( $h2_count >= $needed ) {
			return $this->result( 'subheading_distribution', 'good',
				sprintf( __( 'Alt başlık dağılımı iyi (%d H2 başlık).', 'ai-seo-editor' ), $h2_count ), $h2_count );
		}
		if ( $h2_count >= max( 1, $needed - 1 ) ) {
			return $this->result( 'subheading_distribution', 'warning',
				sprintf( __( 'Daha fazla H2 başlık önerilir. Mevcut: %d, Önerilen: %d.', 'ai-seo-editor' ), $h2_count, $needed ), $h2_count );
		}
		return $this->result( 'subheading_distribution', 'error',
			sprintf( __( 'H2 başlık sayısı yetersiz. Mevcut: %d, Önerilen: %d.', 'ai-seo-editor' ), $h2_count, $needed ), $h2_count );
	}

	private function check_flesch_reading_ease( string $text ): array {
		$words     = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = $this->split_sentences( $text );

		$word_count     = count( $words ?: [] );
		$sentence_count = count( $sentences );

		if ( $word_count === 0 || $sentence_count === 0 ) {
			return $this->result( 'flesch_reading_ease', 'warning', __( 'Metin analiz edilemedi.', 'ai-seo-editor' ), 0 );
		}

		$syllable_count = 0;
		foreach ( ( $words ?: [] ) as $word ) {
			$syllable_count += $this->count_syllables( $word );
		}

		$avg_sentence_length = $word_count / $sentence_count;
		$avg_syllables       = $syllable_count / $word_count;

		$flesch = 206.835 - ( 1.015 * $avg_sentence_length ) - ( 84.6 * $avg_syllables );
		$flesch = round( max( 0, min( 100, $flesch ) ), 1 );

		if ( $flesch >= 60 ) {
			return $this->result( 'flesch_reading_ease', 'good',
				sprintf( __( 'Okunabilirlik skoru iyi (%.1f).', 'ai-seo-editor' ), $flesch ), $flesch );
		}
		if ( $flesch >= 40 ) {
			return $this->result( 'flesch_reading_ease', 'warning',
				sprintf( __( 'Okunabilirlik orta (%.1f). Daha kısa cümle ve kelimeler deneyin.', 'ai-seo-editor' ), $flesch ), $flesch );
		}
		return $this->result( 'flesch_reading_ease', 'error',
			sprintf( __( 'Metin çok zor okunuyor (%.1f). Cümleleri ve kelimeleri sadeleştirin.', 'ai-seo-editor' ), $flesch ), $flesch );
	}

	private function check_text_complexity( string $text ): array {
		$words          = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$word_count     = count( $words );
		if ( $word_count === 0 ) {
			return $this->result( 'text_complexity', 'warning', __( 'Metin analiz edilemedi.', 'ai-seo-editor' ), 0 );
		}

		$long_words = 0;
		foreach ( $words as $word ) {
			if ( mb_strlen( preg_replace( '/[^a-zA-ZçğışöüÇĞİŞÖÜ]/u', '', $word ) ) > 12 ) {
				$long_words++;
			}
		}
		$ratio = $long_words / $word_count;

		if ( $ratio <= 0.15 ) {
			return $this->result( 'text_complexity', 'good',
				__( 'Kelime karmaşıklığı uygun.', 'ai-seo-editor' ), $ratio );
		}
		if ( $ratio <= 0.30 ) {
			return $this->result( 'text_complexity', 'warning',
				__( 'Bazı kelimeler çok uzun. Daha basit alternatifler kullanabilirsiniz.', 'ai-seo-editor' ), $ratio );
		}
		return $this->result( 'text_complexity', 'error',
			__( 'Metin gereksiz yere karmaşık kelimeler içeriyor.', 'ai-seo-editor' ), $ratio );
	}

	private function split_sentences( string $text ): array {
		$text      = preg_replace( '/\s+/', ' ', trim( $text ) );
		$sentences = preg_split( '/(?<=[.!?…])\s+(?=[A-ZÇĞİŞÖÜa-zçğışöü])/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return array_filter( $sentences ?: [], fn( $s ) => aiseo_count_words( $s ) > 2 );
	}

	private function split_paragraphs( string $html ): array {
		preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $matches );
		if ( ! empty( $matches[1] ) ) {
			return array_filter( array_map( 'wp_strip_all_tags', $matches[1] ) );
		}
		$text  = aiseo_strip_html( $html );
		$paras = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );
		return array_filter( $paras ?: [] );
	}

	private function has_passive_voice( string $sentence ): bool {
		$lower = mb_strtolower( $sentence );
		foreach ( $this->passive_indicators_tr as $indicator ) {
			if ( mb_strpos( $lower, $indicator ) !== false ) {
				return true;
			}
		}
		if ( preg_match( '/\b(is|are|was|were|been|being)\s+\w+ed\b/i', $sentence ) ) {
			return true;
		}
		return false;
	}

	private function count_syllables( string $word ): int {
		$word  = mb_strtolower( preg_replace( '/[^a-zA-ZçğışöüÇĞİŞÖÜ]/u', '', $word ) );
		if ( empty( $word ) ) {
			return 1;
		}
		$count = preg_match_all( '/[aeıioöuüAEIİOÖUÜaeiou]/u', $word );
		return max( 1, $count ?: 1 );
	}
}
