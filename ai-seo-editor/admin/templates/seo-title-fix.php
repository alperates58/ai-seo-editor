<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
/** @var int $total */
/** @var int $paged */
/** @var int $per_page */
/** @var string $search */
/** @var string $filter */

$total_pages = (int) ceil( $total / $per_page );
$base_url    = admin_url( 'admin.php?page=aiseo-seo-title-fix' );
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-stf">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-tag',
			'eyebrow'  => __( 'SERP title workspace', 'ai-seo-editor' ),
			'title'    => __( 'SEO Baslik Duzelt', 'ai-seo-editor' ),
			'subtitle' => __( 'Review weak or missing Yoast titles, fix them inline, and batch-generate stronger variants without changing the existing REST workflow.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => sprintf( __( '%d yazi', 'ai-seo-editor' ), $total ),
					'tone'  => 'info',
				],
			],
		]
	);
	?>

	<section class="aiseo-panel">
		<div class="aiseo-bulk-controls aiseo-bulk-controls--premium">
			<form method="get" class="aiseo-filter-bar aiseo-filter-bar--inline">
				<input type="hidden" name="page" value="aiseo-seo-title-fix">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Baslik ara...', 'ai-seo-editor' ); ?>" class="aiseo-input aiseo-input--search regular-text">
				<select name="filter" class="aiseo-input aiseo-input--select">
					<option value="" <?php selected( $filter, '' ); ?>><?php esc_html_e( 'Tum yazilar', 'ai-seo-editor' ); ?></option>
					<option value="missing" <?php selected( $filter, 'missing' ); ?>><?php esc_html_e( 'SEO basligi eksik / ayni', 'ai-seo-editor' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'ai-seo-editor' ); ?></button>
			</form>
			<label class="aiseo-check-line">
				<input type="checkbox" id="aiseo-stf-select-all">
				<span><?php esc_html_e( 'Tumunu Sec', 'ai-seo-editor' ); ?></span>
			</label>
			<button type="button" id="aiseo-stf-fix-selected" class="button button-primary" disabled><?php esc_html_e( 'Secilenleri Duzelt', 'ai-seo-editor' ); ?></button>
			<span id="aiseo-stf-status" class="aiseo-toolbar__count"></span>
		</div>
		<div id="aiseo-stf-progress-wrap" class="aiseo-progress-card aiseo-progress-card--inline" style="display:none;margin-top:10px;">
			<div class="aiseo-progress-bar"><div class="aiseo-progress-fill" id="aiseo-stf-progress" style="width:0%"></div></div>
		</div>
	</section>

	<div id="aiseo-stf-notice"></div>

	<section class="aiseo-panel">
		<div class="aiseo-table-shell">
			<table class="aiseo-table aiseo-data-table aiseo-data-table--interactive wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:30px;"><input type="checkbox" id="aiseo-stf-select-all-header"></th>
						<th><?php esc_html_e( 'Yazi Basligi', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Yoast SEO Basligi', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'SERP Preview', 'ai-seo-editor' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Islem', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $posts ) ) : ?>
						<tr><td colspan="5" class="aiseo-empty"><?php esc_html_e( 'Yazi bulunamadi.', 'ai-seo-editor' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $posts as $post ) : ?>
							<?php
							$seo_title = (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true );
							$is_same   = empty( $seo_title ) || $seo_title === $post->post_title;
							$preview   = $is_same ? $post->post_title : $seo_title;
							$length    = mb_strlen( $preview );
							?>
							<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>" id="aiseo-stf-row-<?php echo esc_attr( $post->ID ); ?>">
								<td><input type="checkbox" class="aiseo-stf-checkbox" value="<?php echo esc_attr( $post->ID ); ?>"></td>
								<td>
									<div class="aiseo-table-title">
										<strong><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $post->post_title ); ?></a></strong>
										<small>#<?php echo esc_html( (string) $post->ID ); ?></small>
									</div>
								</td>
								<td>
									<span class="aiseo-stf-seo-title" id="aiseo-stf-title-<?php echo esc_attr( $post->ID ); ?>">
										<?php echo $is_same ? esc_html__( '(Taslak baslikla ayni veya bos)', 'ai-seo-editor' ) : esc_html( $seo_title ); ?>
									</span>
									<div class="aiseo-inline-badges">
										<?php echo wp_kses_post( aiseo_admin_status_badge( $is_same ? __( 'Weak', 'ai-seo-editor' ) : __( 'Custom', 'ai-seo-editor' ), $is_same ? 'warning' : 'success' ) ); ?>
									</div>
								</td>
								<td>
									<div class="aiseo-serp-preview">
										<strong><?php echo esc_html( aiseo_truncate( $preview, 58 ) ); ?></strong>
										<small><?php echo esc_html( home_url( '/' ) ); ?></small>
										<span class="aiseo-serp-preview__count<?php echo $length > 58 ? ' is-over' : ''; ?>"><?php echo esc_html( (string) $length ); ?>/58</span>
									</div>
								</td>
								<td>
									<div class="aiseo-row-actions">
										<button type="button" class="button button-small aiseo-stf-fix-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Duzelt', 'ai-seo-editor' ); ?></button>
										<span class="aiseo-stf-row-status" id="aiseo-stf-row-status-<?php echo esc_attr( $post->ID ); ?>"></span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<?php if ( $total_pages > 1 ) : ?>
		<nav class="aiseo-pagination-shell">
			<div class="aiseo-pagination">
				<?php if ( $paged > 1 ) : ?>
					<a class="aiseo-page-num" href="<?php echo esc_url( add_query_arg( [ 'paged' => $paged - 1, 's' => $search, 'filter' => $filter ], $base_url ) ); ?>">&laquo;</a>
				<?php endif; ?>
				<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
					<?php if ( $p === $paged ) : ?>
						<span class="aiseo-page-num aiseo-page-num--current"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'paged' => $p, 's' => $search, 'filter' => $filter ], $base_url ) ); ?>" class="aiseo-page-num"><?php echo esc_html( $p ); ?></a>
					<?php endif; ?>
				<?php endfor; ?>
				<?php if ( $paged < $total_pages ) : ?>
					<a class="aiseo-page-num" href="<?php echo esc_url( add_query_arg( [ 'paged' => $paged + 1, 's' => $search, 'filter' => $filter ], $base_url ) ); ?>">&raquo;</a>
				<?php endif; ?>
			</div>
		</nav>
	<?php endif; ?>
</div>
