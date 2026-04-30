<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Scorer {

	private array $seo_weights = [
		'focus_keyword_present'       => 5,
		'keyword_in_title'            => 9,
		'keyword_in_meta_description' => 7,
		'keyword_in_first_paragraph'  => 7,
		'keyword_in_url'              => 5,
		'keyword_density'             => 5,
		'title_length'                => 6,
		'meta_description_length'     => 6,
		'content_length'              => 8,
		'images_present'              => 4,
		'image_alt_text'              => 5,
		'internal_links'              => 6,
		'external_links'              => 4,
		'headings_structure'          => 7,
		'keyword_in_headings'         => 5,
		'schema_markup'               => 4,
		'canonical_tag'               => 4,
		'og_tags'                     => 4,
		'outbound_link_quality'       => 4,
	];

	private array $readability_weights = [
		'sentence_length'              => 20,
		'paragraph_length'             => 15,
		'passive_voice'                => 10,
		'transition_words'             => 15,
		'consecutive_sentences'        => 5,
		'subheading_distribution'      => 10,
		'flesch_reading_ease'          => 20,
		'text_complexity'              => 5,
	];

	public function calculate_seo_score( array $criteria ): int {
		$total = 0.0;
		foreach ( $criteria as $criterion ) {
			$id     = $criterion['id'] ?? '';
			$status = $criterion['status'] ?? 'error';
			$value  = $criterion['value'] ?? null;
			$weight = $this->seo_weights[ $id ] ?? 0;

			$multiplier = $this->get_multiplier( $id, $status, $value );
			$total     += $weight * $multiplier;
		}
		return min( 100, max( 0, (int) floor( $total ) ) );
	}

	public function calculate_readability_score( array $criteria ): int {
		$total = 0.0;
		foreach ( $criteria as $criterion ) {
			$id     = $criterion['id'] ?? '';
			$status = $criterion['status'] ?? 'error';
			$value  = $criterion['value'] ?? null;
			$weight = $this->readability_weights[ $id ] ?? 0;

			$multiplier = $this->get_multiplier( $id, $status, $value );
			$total     += $weight * $multiplier;
		}
		return min( 100, max( 0, (int) floor( $total ) ) );
	}

	private function get_multiplier( string $id, string $status, mixed $value ): float {
		switch ( $id ) {
			case 'keyword_density':
				return $this->keyword_density_multiplier( (float) $value );

			case 'flesch_reading_ease':
				return $this->flesch_multiplier( (float) $value );

			case 'content_length':
				return $this->content_length_multiplier( (int) $value );

			default:
				return match ( $status ) {
					'good'    => 1.0,
					'warning' => 0.5,
					default   => 0.0,
				};
		}
	}

	private function keyword_density_multiplier( float $density ): float {
		if ( $density <= 0 ) {
			return 0.0;
		}
		if ( $density < 0.5 ) {
			return 0.2;
		}
		if ( $density < 1.0 ) {
			return 0.6;
		}
		if ( $density <= 1.5 ) {
			return 1.0;
		}
		if ( $density <= 2.0 ) {
			return 0.8;
		}
		if ( $density <= 2.5 ) {
			return 0.4;
		}
		return 0.1;
	}

	private function flesch_multiplier( float $score ): float {
		if ( $score < 30 ) {
			return 0.0;
		}
		if ( $score < 50 ) {
			return 0.4;
		}
		if ( $score < 60 ) {
			return 0.7;
		}
		if ( $score <= 70 ) {
			return 1.0;
		}
		if ( $score <= 80 ) {
			return 0.9;
		}
		return 0.7;
	}

	private function content_length_multiplier( int $words ): float {
		if ( $words < 300 ) {
			return 0.2;
		}
		if ( $words < 600 ) {
			return 0.5;
		}
		if ( $words < 1000 ) {
			return 0.7;
		}
		if ( $words < 1500 ) {
			return 0.9;
		}
		return 1.0;
	}

	public function get_score_color( int $score ): string {
		return aiseo_get_score_color( $score );
	}

	public function get_score_label( int $score ): string {
		return aiseo_get_score_label( $score );
	}
}
