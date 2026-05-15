<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Quality_Control {

	// Bozuk köşeli metin kalıpları (CSS/renk artıkları)
	private array $broken_patterns = [
		'[-0.094rem]',
		'[#303030]',
		'[#8F8F8F]',
		'[9px]',
		'[#F4F4F4]',
		'[show_150ms_ease-in]',
	];

	// Sağlık/finans anahtar kelimeleri (uyarı kutusu gerektirir)
	private array $risk_keywords = [
		'hastalık', 'hastalıklar', 'doktor', 'tanı', 'teşhis', 'tedavi',
		'ilaç', 'tıbbi', 'sağlık riski', 'kalori ihtiyacı', 'beden kitle',
		'kalp ritmi', 'nabız', 'tansiyon', 'kan şekeri',
		'kredi faiz', 'vergi', 'yatırım riski', 'finansal tavsiye',
		'hukuki', 'avukat', 'mahkeme',
	];

	// Risk gerektirmeyen yazı slug kalıpları
	private array $safe_slug_patterns = [
		'yuzde', 'hesapla', 'faiz', 'bmi', 'kilo',
	];

	public function get_summary(): array {
		global $wpdb;

		$broken_like = $this->build_broken_like_sql();

		$shortcode_missing = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
			WHERE post_type = 'post'
			  AND post_status = 'draft'
			  AND post_content NOT LIKE '%[hc\_%' ESCAPE '\\\\'"
		);

		$meta_missing = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
			  ON pm.post_id = p.ID AND pm.meta_key = '_yoast_wpseo_metadesc'
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
			  AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);

		$broken_text = $broken_like
			? (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
				WHERE post_type = 'post'
				  AND post_status = 'publish'
				  AND ({$broken_like})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
			: 0;

		$cat_missing = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			LEFT JOIN {$wpdb->term_taxonomy} tt
			  ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
			LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
			  AND (tt.parent IS NULL OR tt.parent = 0 OR tt.term_taxonomy_id IS NULL)"
		);

		$seo_title_missing = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
			  ON pm.post_id = p.ID AND pm.meta_key = '_yoast_wpseo_title'
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
			  AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);

		return [
			'shortcode_missing' => $shortcode_missing,
			'meta_missing'      => $meta_missing,
			'broken_text'       => $broken_text,
			'cat_missing'       => $cat_missing,
			'seo_title_missing' => $seo_title_missing,
			'total_issues'      => $shortcode_missing + $meta_missing + $broken_text + $cat_missing + $seo_title_missing,
		];
	}

	public function get_tab_data( string $tab, int $paged, int $per_page ): array {
		switch ( $tab ) {
			case 'meta':
				return $this->get_meta_issues( $paged, $per_page );
			case 'category':
				return $this->get_category_issues( $paged, $per_page );
			case 'broken_text':
				return $this->get_broken_text_issues( $paged, $per_page );
			case 'risk_notice':
				return $this->get_risk_notice_issues( $paged, $per_page );
			default:
				return $this->get_shortcode_issues( $paged, $per_page );
		}
	}

	private function get_shortcode_issues( int $paged, int $per_page ): array {
		global $wpdb;
		$offset = ( $paged - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
			WHERE post_type = 'post'
			  AND post_status = 'draft'
			  AND post_content NOT LIKE '%[hc\_%' ESCAPE '\\\\'"
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_status, p.post_name
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'post'
				  AND p.post_status = 'draft'
				  AND p.post_content NOT LIKE '%%[hc\_%%' ESCAPE '\\\\'
				ORDER BY p.ID DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return [
			'total'   => $total,
			'items'   => $this->map_rows( $rows, 'Shortcode eksik', 'shortcode' ),
			'paged'   => $paged,
			'per_page' => $per_page,
		];
	}

	private function get_meta_issues( int $paged, int $per_page ): array {
		global $wpdb;
		$offset = ( $paged - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
			  ON pm.post_id = p.ID AND pm.meta_key = '_yoast_wpseo_metadesc'
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
			  AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_status, p.post_name
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm
				  ON pm.post_id = p.ID AND pm.meta_key = '_yoast_wpseo_metadesc'
				WHERE p.post_type = 'post'
				  AND p.post_status = 'publish'
				  AND (pm.meta_value IS NULL OR pm.meta_value = '')
				ORDER BY p.ID DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return [
			'total'    => $total,
			'items'    => $this->map_rows( $rows, 'Meta description eksik', 'meta' ),
			'paged'    => $paged,
			'per_page' => $per_page,
		];
	}

	private function get_category_issues( int $paged, int $per_page ): array {
		global $wpdb;
		$offset = ( $paged - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			LEFT JOIN {$wpdb->term_taxonomy} tt
			  ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
			WHERE p.post_type = 'post'
			  AND p.post_status = 'publish'
			  AND (tt.parent IS NULL OR tt.parent = 0 OR tt.term_taxonomy_id IS NULL)"
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_title, p.post_status, p.post_name
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				LEFT JOIN {$wpdb->term_taxonomy} tt
				  ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
				WHERE p.post_type = 'post'
				  AND p.post_status = 'publish'
				  AND (tt.parent IS NULL OR tt.parent = 0 OR tt.term_taxonomy_id IS NULL)
				ORDER BY p.ID DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return [
			'total'    => $total,
			'items'    => $this->map_rows( $rows, 'Alt kategori eksik', 'category' ),
			'paged'    => $paged,
			'per_page' => $per_page,
		];
	}

	private function get_broken_text_issues( int $paged, int $per_page ): array {
		global $wpdb;
		$offset      = ( $paged - 1 ) * $per_page;
		$broken_like = $this->build_broken_like_sql();

		if ( ! $broken_like ) {
			return [ 'total' => 0, 'items' => [], 'paged' => $paged, 'per_page' => $per_page ];
		}

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
			WHERE post_type = 'post'
			  AND post_status = 'publish'
			  AND ({$broken_like})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_status, post_name
				FROM {$wpdb->posts}
				WHERE post_type = 'post'
				  AND post_status = 'publish'
				  AND ({$broken_like})
				ORDER BY ID DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$items = [];
		foreach ( $rows as $row ) {
			$found = $this->find_broken_patterns_in_post( (int) $row['ID'] );
			$items[] = [
				'id'          => (int) $row['ID'],
				'title'       => (string) $row['post_title'],
				'status'      => (string) $row['post_status'],
				'slug'        => (string) $row['post_name'],
				'issue_type'  => 'broken_text',
				'description' => implode( ', ', $found ),
				'edit_url'    => get_edit_post_link( (int) $row['ID'], 'raw' ) ?: '',
				'view_url'    => get_permalink( (int) $row['ID'] ) ?: '',
			];
		}

		return [
			'total'    => $total,
			'items'    => $items,
			'paged'    => $paged,
			'per_page' => $per_page,
		];
	}

	private function get_risk_notice_issues( int $paged, int $per_page ): array {
		global $wpdb;
		$offset = ( $paged - 1 ) * $per_page;

		$keyword_likes = [];
		foreach ( $this->risk_keywords as $kw ) {
			$keyword_likes[] = $wpdb->prepare( 'post_title LIKE %s', '%' . $wpdb->esc_like( $kw ) . '%' );
		}
		$keyword_sql = implode( ' OR ', $keyword_likes );

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts}
			WHERE post_type = 'post'
			  AND post_status = 'publish'
			  AND ({$keyword_sql})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_status, post_name
				FROM {$wpdb->posts}
				WHERE post_type = 'post'
				  AND post_status = 'publish'
				  AND ({$keyword_sql})
				ORDER BY ID DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return [
			'total'    => $total,
			'items'    => $this->map_rows( $rows, 'Risk uyarısı gerektiriyor olabilir', 'risk_notice' ),
			'paged'    => $paged,
			'per_page' => $per_page,
		];
	}

	private function build_broken_like_sql(): string {
		global $wpdb;
		$parts = [];
		foreach ( $this->broken_patterns as $pattern ) {
			$parts[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $pattern ) . '%' );
		}
		return implode( ' OR ', $parts );
	}

	private function find_broken_patterns_in_post( int $post_id ): array {
		global $wpdb;
		$content = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
		);
		$found = [];
		foreach ( $this->broken_patterns as $pattern ) {
			if ( str_contains( $content, $pattern ) ) {
				$found[] = esc_html( $pattern );
			}
		}
		return $found;
	}

	private function map_rows( array $rows, string $description, string $issue_type ): array {
		$items = [];
		foreach ( $rows as $row ) {
			$post_id = (int) $row['ID'];
			$items[] = [
				'id'          => $post_id,
				'title'       => (string) $row['post_title'],
				'status'      => (string) $row['post_status'],
				'slug'        => (string) $row['post_name'],
				'issue_type'  => $issue_type,
				'description' => $description,
				'edit_url'    => get_edit_post_link( $post_id, 'raw' ) ?: '',
				'view_url'    => get_permalink( $post_id ) ?: '',
			];
		}
		return $items;
	}
}
