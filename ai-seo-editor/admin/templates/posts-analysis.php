<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
/** @var int $total */
/** @var int $paged */
/** @var int $per_page */
/** @var string $search */
/** @var string $post_type */

$yoast = new AISEO_Yoast_Integration();
$total_pages = (int) ceil( $total / $per_page );
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-posts">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-search',
			'eyebrow'  => __( 'Advanced content audit', 'ai-seo-editor' ),
			'title'    => __( 'Yazi Analizi', 'ai-seo-editor' ),
			'subtitle' => __( 'Filter published content, compare SEO signals, and jump into AI-assisted improvements without changing the underlying analysis workflow.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => sprintf( __( '%d kayit', 'ai-seo-editor' ), $total ),
					'tone'  => 'info',
				],
				[
					'label' => 'page' === $post_type ? __( 'Sayfa modu', 'ai-seo-editor' ) : __( 'Yazi modu', 'ai-seo-editor' ),
					'tone'  => 'muted',
				],
			],
		]
	);
	?>

	<div class="aiseo-toolbar-shell aiseo-toolbar-shell--sticky">
		<form method="get" action="" class="aiseo-filter-bar">
			<input type="hidden" name="page" value="aiseo-posts">
			<div class="aiseo-filter-bar__group">
				<label class="screen-reader-text" for="aiseo-post-search"><?php esc_html_e( 'Yazi ara', 'ai-seo-editor' ); ?></label>
				<input id="aiseo-post-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Baslik veya icerik ara...', 'ai-seo-editor' ); ?>" class="aiseo-input aiseo-input--search aiseo-search-input">
				<select name="post_type" class="aiseo-input aiseo-input--select">
					<option value="post" <?php selected( $post_type, 'post' ); ?>><?php esc_html_e( 'Yazilar', 'ai-seo-editor' ); ?></option>
					<option value="page" <?php selected( $post_type, 'page' ); ?>><?php esc_html_e( 'Sayfalar', 'ai-seo-editor' ); ?></option>
				</select>
			</div>
			<div class="aiseo-filter-bar__group">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrele', 'ai-seo-editor' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Sifirla', 'ai-seo-editor' ); ?></a>
			</div>
		</form>
		<div class="aiseo-toolbar-summary">
			<?php echo wp_kses_post( aiseo_admin_status_badge( sprintf( __( '%d yazi bulundu', 'ai-seo-editor' ), $total ), 'info' ) ); ?>
			<button type="button" class="button button-secondary aiseo-compact-toggle" data-target="aiseo-posts-table-shell"><?php esc_html_e( 'Compact mode', 'ai-seo-editor' ); ?></button>
		</div>
	</div>

	<div class="aiseo-chip-row">
		<a href="<?php echo esc_url( add_query_arg( [ 'post_type' => $post_type, 's' => '' ], admin_url( 'admin.php?page=aiseo-posts' ) ) ); ?>" class="aiseo-chip<?php echo '' === $search ? ' is-active' : ''; ?>"><?php esc_html_e( 'Tum icerik', 'ai-seo-editor' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-bulk&score_filter=red' ) ); ?>" class="aiseo-chip"><?php esc_html_e( 'Dusuk SEO', 'ai-seo-editor' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-links' ) ); ?>" class="aiseo-chip"><?php esc_html_e( 'Ic link firsatlari', 'ai-seo-editor' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-seo-title-fix' ) ); ?>" class="aiseo-chip"><?php esc_html_e( 'Baslik duzeltme', 'ai-seo-editor' ); ?></a>
	</div>

	<div id="aiseo-posts-notice"></div>

	<section class="aiseo-panel">
		<div class="aiseo-panel__header">
			<div>
				<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Table view', 'ai-seo-editor' ); ?></div>
				<h2 class="aiseo-panel__title"><?php esc_html_e( 'Published content inventory', 'ai-seo-editor' ); ?></h2>
			</div>
		</div>
		<div id="aiseo-posts-table-shell" class="aiseo-table-shell">
			<table class="aiseo-table aiseo-data-table aiseo-data-table--interactive aiseo-table--posts wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Baslik', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Odak KW', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Ic Link', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Meta', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Quick insight', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Islemler', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $posts ) ) : ?>
						<tr>
							<td colspan="9">
								<?php
								aiseo_admin_empty_state(
									[
										'icon'        => 'dashicons-search',
										'title'       => __( 'Yazi bulunamadi', 'ai-seo-editor' ),
										'description' => __( 'Mevcut filtrelerle eslesen yayinlanmis bir icerik yok. Aramayi veya icerik tipini degistirin.', 'ai-seo-editor' ),
									]
								);
								?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $posts as $post ) : ?>
							<?php
							$seo_score   = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
							$read_score  = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
							$keyword     = $yoast->get_focus_keyword( $post->ID );
							$meta_desc   = $yoast->get_meta_description( $post->ID );
							$last_anal   = get_post_meta( $post->ID, '_aiseo_last_analysis', true );
							$content     = apply_filters( 'the_content', $post->post_content );
							$links       = aiseo_extract_links( $content );
							$int_count   = count( $links['internal'] );
							$meta_state  = empty( $meta_desc ) ? 'warning' : 'success';
							$insight     = [];
							if ( $seo_score > 0 && $seo_score < 60 ) {
								$insight[] = __( 'SEO attention', 'ai-seo-editor' );
							}
							if ( $read_score > 0 && $read_score < 60 ) {
								$insight[] = __( 'Readability low', 'ai-seo-editor' );
							}
							if ( empty( $meta_desc ) ) {
								$insight[] = __( 'Meta missing', 'ai-seo-editor' );
							}
							if ( 0 === $int_count ) {
								$insight[] = __( 'No internal links', 'ai-seo-editor' );
							}
							?>
							<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>">
								<td>
									<div class="aiseo-table-title">
										<strong><a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $post->post_title ); ?></a></strong>
										<small><?php echo $last_anal ? esc_html( sprintf( __( 'Son analiz: %s once', 'ai-seo-editor' ), human_time_diff( strtotime( $last_anal ) ) ) ) : esc_html__( 'Henuz analiz yok', 'ai-seo-editor' ); ?></small>
									</div>
								</td>
								<td><?php echo wp_kses_post( aiseo_admin_status_badge( (string) ( get_post_status_object( $post->post_status )->label ?? $post->post_status ), $post->post_status === 'publish' ? 'success' : 'muted' ) ); ?></td>
								<td><?php echo esc_html( $keyword ?: '--' ); ?></td>
								<td class="aiseo-score-cell aiseo-seo-score-cell" data-score="<?php echo esc_attr( $seo_score ); ?>"><?php echo $seo_score > 0 ? wp_kses_post( aiseo_score_badge( $seo_score ) ) : wp_kses_post( aiseo_admin_status_badge( '--', 'muted' ) ); ?></td>
								<td class="aiseo-read-score-cell" data-score="<?php echo esc_attr( $read_score ); ?>"><?php echo $read_score > 0 ? wp_kses_post( aiseo_score_badge( $read_score ) ) : wp_kses_post( aiseo_admin_status_badge( '--', 'muted' ) ); ?></td>
								<td><?php echo wp_kses_post( aiseo_admin_status_badge( (string) $int_count, $int_count > 0 ? 'success' : 'warning' ) ); ?></td>
								<td><?php echo wp_kses_post( aiseo_admin_status_badge( empty( $meta_desc ) ? __( 'Eksik', 'ai-seo-editor' ) : __( 'Hazir', 'ai-seo-editor' ), $meta_state ) ); ?></td>
								<td>
									<?php if ( ! empty( $insight ) ) : ?>
										<div class="aiseo-inline-badges">
											<?php foreach ( array_slice( $insight, 0, 2 ) as $note ) : ?>
												<?php echo wp_kses_post( aiseo_admin_status_badge( $note, 'warning' ) ); ?>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Healthy', 'ai-seo-editor' ), 'success' ) ); ?>
									<?php endif; ?>
								</td>
								<td class="aiseo-actions">
									<div class="aiseo-row-actions">
										<button class="button button-small aiseo-btn-analyze" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Analiz Et', 'ai-seo-editor' ); ?></button>
										<a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Editorde Iyilestir', 'ai-seo-editor' ); ?></a>
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
				<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
					<?php if ( $p === $paged ) : ?>
						<span class="aiseo-page-num aiseo-page-num--current"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'paged' => $p ] ) ); ?>" class="aiseo-page-num"><?php echo esc_html( $p ); ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
		</nav>
	<?php endif; ?>

	<div id="aiseo-inline-result" class="aiseo-inline-result aiseo-drawer-like" style="display:none">
		<div class="aiseo-inline-result__header">
			<strong id="aiseo-inline-title"></strong>
			<button id="aiseo-inline-close" class="aiseo-btn-close" type="button">&times;</button>
		</div>
		<div id="aiseo-inline-body"></div>
	</div>
</div>

<?php
if ( isset( $_GET['action'] ) && 'detail' === $_GET['action'] && ! empty( $_GET['post_id'] ) ) :
	$detail_post_id = absint( $_GET['post_id'] );
	$analyzer       = new AISEO_Analyzer();
	$result         = $analyzer->analyze( $detail_post_id );
	include AISEO_PLUGIN_DIR . 'admin/templates/post-detail.php';
endif;
?>
