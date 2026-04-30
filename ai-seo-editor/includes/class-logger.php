<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AISEO_Logger {

	public function log_ai_operation(
		int $post_id,
		string $operation_type,
		string $model,
		int $input_tokens,
		int $output_tokens,
		string $status,
		string $error_message = ''
	): int {
		global $wpdb;

		$settings = AISEO_Plugin::get_instance()->get_settings();
		if ( ! $settings->get( 'enable_logging' ) ) {
			return 0;
		}

		$wpdb->insert(
			$wpdb->prefix . 'aiseo_ai_logs',
			[
				'user_id'        => get_current_user_id(),
				'post_id'        => $post_id,
				'operation_type' => $operation_type,
				'model'          => $model,
				'input_tokens'   => $input_tokens,
				'output_tokens'  => $output_tokens,
				'total_tokens'   => $input_tokens + $output_tokens,
				'status'         => $status,
				'error_message'  => $status === 'error' ? $error_message : null,
				'created_at'     => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	public function log_analysis(
		int $post_id,
		int $seo_score,
		int $readability_score,
		array $criteria_data,
		string $keyword,
		int $word_count,
		float $keyword_density,
		string $cache_hash
	): int {
		global $wpdb;

		$issues_summary = $this->summarize_issues( $criteria_data );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}aiseo_analysis_logs WHERE post_id = %d",
				$post_id
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'aiseo_analysis_logs',
			[
				'post_id'           => $post_id,
				'focus_keyword'     => $keyword,
				'seo_score'         => $seo_score,
				'readability_score' => $readability_score,
				'word_count'        => $word_count,
				'keyword_density'   => $keyword_density,
				'criteria_data'     => wp_json_encode( $criteria_data ),
				'issues_summary'    => wp_json_encode( $issues_summary ),
				'analyzed_at'       => current_time( 'mysql' ),
				'cache_hash'        => $cache_hash,
			],
			[ '%d', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s' ]
		);

		update_post_meta( $post_id, '_aiseo_seo_score', $seo_score );
		update_post_meta( $post_id, '_aiseo_readability_score', $readability_score );
		update_post_meta( $post_id, '_aiseo_last_analysis', current_time( 'mysql' ) );

		return (int) $wpdb->insert_id;
	}

	public function get_cached_analysis( int $post_id, string $cache_hash ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiseo_analysis_logs WHERE post_id = %d AND cache_hash = %s ORDER BY analyzed_at DESC LIMIT 1",
				$post_id,
				$cache_hash
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$settings = AISEO_Plugin::get_instance()->get_settings();
		$ttl      = (int) $settings->get( 'analysis_cache_ttl' );
		$age      = time() - strtotime( $row['analyzed_at'] );

		if ( $age > $ttl ) {
			return null;
		}

		$row['criteria_data']  = json_decode( $row['criteria_data'], true ) ?? [];
		$row['issues_summary'] = json_decode( $row['issues_summary'], true ) ?? [];

		return $row;
	}

	public function invalidate_cache( int $post_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}aiseo_analysis_logs WHERE post_id = %d",
				$post_id
			)
		);
	}

	public function get_ai_logs( array $filters = [], int $per_page = 50, int $page = 1 ): array {
		global $wpdb;

		$where  = '1=1';
		$params = [];

		if ( ! empty( $filters['post_id'] ) ) {
			$where    .= ' AND post_id = %d';
			$params[] = (int) $filters['post_id'];
		}
		if ( ! empty( $filters['operation_type'] ) ) {
			$where    .= ' AND operation_type = %s';
			$params[] = sanitize_text_field( $filters['operation_type'] );
		}
		if ( ! empty( $filters['status'] ) ) {
			$where    .= ' AND status = %s';
			$params[] = sanitize_text_field( $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where    .= ' AND created_at >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where    .= ' AND created_at <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aiseo_ai_logs WHERE $where";
		$data_sql  = "SELECT * FROM {$wpdb->prefix}aiseo_ai_logs WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
			$rows  = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ), ARRAY_A );
		}

		return [
			'items'       => $rows ?: [],
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	public function get_monthly_token_usage( string $year_month = '' ): int {
		global $wpdb;
		if ( empty( $year_month ) ) {
			$year_month = current_time( 'Y-m' );
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total_tokens) FROM {$wpdb->prefix}aiseo_ai_logs WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s AND status = 'success'",
				$year_month
			)
		);
	}

	public function get_token_usage_by_day( int $days = 30 ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day, SUM(total_tokens) as tokens
				 FROM {$wpdb->prefix}aiseo_ai_logs
				 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND status = 'success'
				 GROUP BY DATE(created_at)
				 ORDER BY day ASC",
				$days
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	public function get_recent_ai_logs( int $limit = 10 ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, p.post_title FROM {$wpdb->prefix}aiseo_ai_logs l
				 LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
				 ORDER BY l.created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	public function get_dashboard_stats(): array {
		global $wpdb;

		$total_posts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'"
		);

		$green = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aiseo_analysis_logs WHERE seo_score >= 80"
		);
		$yellow = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aiseo_analysis_logs WHERE seo_score >= 60 AND seo_score < 80"
		);
		$red = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}aiseo_analysis_logs WHERE seo_score < 60"
		);

		$avg_seo = (float) $wpdb->get_var(
			"SELECT AVG(seo_score) FROM {$wpdb->prefix}aiseo_analysis_logs"
		);
		$avg_read = (float) $wpdb->get_var(
			"SELECT AVG(readability_score) FROM {$wpdb->prefix}aiseo_analysis_logs"
		);

		$no_meta = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 WHERE p.post_status = 'publish' AND p.post_type = 'post'
			 AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm
				WHERE pm.post_id = p.ID AND pm.meta_key IN ('_aiseo_meta_description','_yoast_wpseo_metadesc')
				AND pm.meta_value != ''
			 )"
		);

		$monthly_tokens = $this->get_monthly_token_usage();
		$recent_logs    = $this->get_recent_ai_logs( 10 );
		$token_by_day   = $this->get_token_usage_by_day( 30 );

		$low_score_posts = $wpdb->get_results(
			"SELECT l.post_id, l.seo_score, l.readability_score, p.post_title
			 FROM {$wpdb->prefix}aiseo_analysis_logs l
			 JOIN {$wpdb->posts} p ON l.post_id = p.ID
			 WHERE p.post_status = 'publish'
			 ORDER BY l.seo_score ASC LIMIT 10",
			ARRAY_A
		);

		return [
			'total_posts'     => $total_posts,
			'green'           => $green,
			'yellow'          => $yellow,
			'red'             => $red,
			'avg_seo'         => round( $avg_seo, 1 ),
			'avg_readability' => round( $avg_read, 1 ),
			'no_meta'         => $no_meta,
			'monthly_tokens'  => $monthly_tokens,
			'recent_logs'     => $recent_logs,
			'token_by_day'    => $token_by_day,
			'low_score_posts' => $low_score_posts ?: [],
		];
	}

	public function prune_old_logs( int $days = 90 ): int {
		global $wpdb;
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}aiseo_ai_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
		return $result !== false ? $result : 0;
	}

	private function summarize_issues( array $criteria_data ): array {
		$summary = [ 'good' => 0, 'warning' => 0, 'error' => 0 ];
		foreach ( $criteria_data as $criterion ) {
			$status = $criterion['status'] ?? 'error';
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ]++;
			}
		}
		return $summary;
	}
}
