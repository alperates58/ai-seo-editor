<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
/** @var string $current_score_filter */
/** @var int $total */
/** @var int $paged */
/** @var int $per_page */

$current_score_filter = $current_score_filter ?? '';
$total                = $total ?? count( $posts );
$paged                = $paged ?? 1;
$per_page             = $per_page ?? 50;
$total_pages          = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

// Global counts passed from controller (all published posts, not just current page)
$counts = [
	'total'  => $global_total  ?? $total,
	'green'  => $global_green  ?? 0,
	'orange' => $global_orange ?? 0,
	'red'    => $global_red    ?? 0,
	'none'   => $global_none   ?? 0,
];
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-bulk">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-list-view',
			'eyebrow'  => __( 'Bulk operation center', 'ai-seo-editor' ),
			'title'    => __( 'Toplu Analiz', 'ai-seo-editor' ),
			'subtitle' => __( 'Queue large analysis runs with clearer status, safer bulk controls, and improved scanability while keeping the same backend analysis flow.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => sprintf( __( '%d yazi', 'ai-seo-editor' ), $counts['total'] ),
					'tone'  => 'info',
				],
				[
					'label' => __( 'Sadece analiz, otomatik degisiklik yok', 'ai-seo-editor' ),
					'tone'  => 'muted',
				],
			],
		]
	);
	?>

	<section class="aiseo-stats-grid aiseo-stats-grid--4">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Iyi SEO', 'ai-seo-editor' ), 'value' => (string) $counts['green'], 'meta' => __( '80+ puan', 'ai-seo-editor' ), 'icon' => 'dashicons-yes-alt', 'tone' => 'success', 'counter' => (string) $counts['green'] ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Gelistirilebilir', 'ai-seo-editor' ), 'value' => (string) $counts['orange'], 'meta' => __( '60-79 puan', 'ai-seo-editor' ), 'icon' => 'dashicons-warning', 'tone' => 'warning', 'counter' => (string) $counts['orange'] ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Zayif', 'ai-seo-editor' ), 'value' => (string) $counts['red'], 'meta' => __( '60 alti', 'ai-seo-editor' ), 'icon' => 'dashicons-dismiss', 'tone' => 'danger', 'counter' => (string) $counts['red'] ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Analiz edilmemis', 'ai-seo-editor' ), 'value' => (string) $counts['none'], 'meta' => __( 'Yeni veya taranmamis icerik', 'ai-seo-editor' ), 'icon' => 'dashicons-clock', 'tone' => 'muted', 'counter' => (string) $counts['none'] ] );
		?>
	</section>

	<section class="aiseo-panel aiseo-bulk-hero-panel">
		<div class="aiseo-bulk-controls aiseo-bulk-controls--premium">
			<label class="aiseo-check-line">
				<input type="checkbox" id="aiseo-select-all">
				<span><?php esc_html_e( 'Tumunu Sec', 'ai-seo-editor' ); ?></span>
			</label>
			<button type="button" id="aiseo-bulk-start" class="button button-primary"><?php esc_html_e( 'Secilenleri Analiz Et', 'ai-seo-editor' ); ?></button>
			<div id="aiseo-bulk-progress-wrap" class="aiseo-progress-card aiseo-progress-card--inline" style="display:none">
				<div class="aiseo-progress-card__copy">
					<strong><?php esc_html_e( 'Islem ilerliyor', 'ai-seo-editor' ); ?></strong>
					<span id="aiseo-bulk-status"></span>
				</div>
				<div class="aiseo-progress-bar"><div class="aiseo-progress-fill" id="aiseo-bulk-progress" style="width:0%"></div></div>
			</div>
		</div>
		<p class="aiseo-panel__description"><?php esc_html_e( 'Toplu analiz sadece SEO ve okunabilirlik puanlarini hesaplar. Yazi iceriginde otomatik degisiklik yapilmaz.', 'ai-seo-editor' ); ?></p>
	</section>

	<div id="aiseo-bulk-notice"></div>

	<div class="aiseo-toolbar-shell aiseo-toolbar-shell--sticky">
		<div class="aiseo-filter-bar">
			<div class="aiseo-filter-bar__group">
				<input type="text" id="aiseo-bulk-search" placeholder="<?php esc_attr_e( 'Yazi ara...', 'ai-seo-editor' ); ?>" class="aiseo-input aiseo-input--search aiseo-search-input">
				<select id="aiseo-bulk-filter" class="aiseo-input aiseo-input--select">
					<option value=""><?php esc_html_e( 'Tum Skorlar', 'ai-seo-editor' ); ?></option>
					<option value="red" <?php selected( $current_score_filter, 'red' ); ?>><?php esc_html_e( 'Kirmizi (<60)', 'ai-seo-editor' ); ?></option>
					<option value="orange" <?php selected( $current_score_filter, 'orange' ); ?>><?php esc_html_e( 'Sari (60-79)', 'ai-seo-editor' ); ?></option>
					<option value="green" <?php selected( $current_score_filter, 'green' ); ?>><?php esc_html_e( 'Yesil (80+)', 'ai-seo-editor' ); ?></option>
					<option value="none" <?php selected( $current_score_filter, 'none' ); ?>><?php esc_html_e( 'Analiz Edilmemis', 'ai-seo-editor' ); ?></option>
				</select>
			</div>
			<div class="aiseo-toolbar-summary">
				<span id="aiseo-bulk-visible-count" class="aiseo-toolbar__count"></span>
				<button type="button" class="button button-secondary aiseo-compact-toggle" data-target="aiseo-bulk-table-shell"><?php esc_html_e( 'Compact mode', 'ai-seo-editor' ); ?></button>
			</div>
		</div>
	</div>

	<div class="aiseo-chip-row">
		<button type="button" class="aiseo-chip<?php echo '' === $current_score_filter ? ' is-active' : ''; ?>" data-bulk-filter-chip=""><?php esc_html_e( 'Tum icerik', 'ai-seo-editor' ); ?></button>
		<button type="button" class="aiseo-chip<?php echo 'red' === $current_score_filter ? ' is-active' : ''; ?>" data-bulk-filter-chip="red"><?php esc_html_e( 'Needs attention', 'ai-seo-editor' ); ?></button>
		<button type="button" class="aiseo-chip<?php echo 'none' === $current_score_filter ? ' is-active' : ''; ?>" data-bulk-filter-chip="none"><?php esc_html_e( 'Yeni tarama', 'ai-seo-editor' ); ?></button>
	</div>

	<section class="aiseo-panel">
		<div id="aiseo-bulk-table-shell" class="aiseo-table-shell">
			<table class="aiseo-table aiseo-data-table aiseo-data-table--interactive wp-list-table widefat fixed striped" id="aiseo-bulk-table">
				<thead>
					<tr>
						<th style="width:30px"><input type="checkbox" id="aiseo-select-all-header"></th>
						<th><?php esc_html_e( 'Baslik', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Tarih', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'SEO Skoru', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Son Analiz', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Islem', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php
						$seo_score = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
						$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
						$last_anal = get_post_meta( $post->ID, '_aiseo_last_analysis', true );
						$color = $seo_score > 0 ? aiseo_get_score_color( $seo_score ) : 'none';
						?>
						<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-score-color="<?php echo esc_attr( $color ); ?>" data-title="<?php echo esc_attr( strtolower( $post->post_title ) ); ?>">
							<td><input type="checkbox" class="aiseo-post-select" value="<?php echo esc_attr( $post->ID ); ?>"></td>
							<td>
								<div class="aiseo-table-title">
									<strong><a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></strong>
									<small>#<?php echo esc_html( (string) $post->ID ); ?></small>
								</div>
							</td>
							<td><?php echo esc_html( get_the_date( 'd.m.Y', $post ) ); ?></td>
							<td class="aiseo-score-cell" id="seo-score-<?php echo esc_attr( $post->ID ); ?>"><?php echo $seo_score > 0 ? wp_kses_post( aiseo_score_badge( $seo_score ) ) : wp_kses_post( aiseo_admin_status_badge( '--', 'muted' ) ); ?></td>
							<td class="aiseo-score-cell" id="read-score-<?php echo esc_attr( $post->ID ); ?>"><?php echo $read_score > 0 ? wp_kses_post( aiseo_score_badge( $read_score ) ) : wp_kses_post( aiseo_admin_status_badge( '--', 'muted' ) ); ?></td>
							<td id="analysis-date-<?php echo esc_attr( $post->ID ); ?>"><?php echo $last_anal ? esc_html( human_time_diff( strtotime( $last_anal ) ) . ' once' ) : '--'; ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>" class="button button-small"><?php esc_html_e( 'Editorde Iyilestir', 'ai-seo-editor' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p id="aiseo-bulk-empty-state" class="aiseo-empty" style="display:none"><?php esc_html_e( 'Seçili filtrelerde gösterilecek yazı bulunamadı.', 'ai-seo-editor' ); ?></p>
	</section>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="aiseo-pagination">
			<?php
			$base_url = add_query_arg( [
				'page'         => 'aiseo-bulk',
				'score_filter' => $current_score_filter,
			], admin_url( 'admin.php' ) );

			if ( $paged > 1 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>" class="button button-secondary aiseo-pagination__btn">&laquo; <?php esc_html_e( 'Önceki', 'ai-seo-editor' ); ?></a>
			<?php endif; ?>

			<span class="aiseo-pagination__info">
				<?php echo esc_html( sprintf( __( 'Sayfa %1$d / %2$d (%3$d yazı)', 'ai-seo-editor' ), $paged, $total_pages, $total ) ); ?>
			</span>

			<?php if ( $paged < $total_pages ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>" class="button button-secondary aiseo-pagination__btn"><?php esc_html_e( 'Sonraki', 'ai-seo-editor' ); ?> &raquo;</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
