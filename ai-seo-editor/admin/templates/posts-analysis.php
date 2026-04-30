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
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-search"></span>
		<?php esc_html_e( 'Yazı Analizi', 'ai-seo-editor' ); ?>
	</h1>

	<!-- Arama ve Filtreler -->
	<div class="aiseo-toolbar">
		<form method="get" action="">
			<input type="hidden" name="page" value="aiseo-posts">
			<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Yazı ara...', 'ai-seo-editor' ); ?>" class="aiseo-search-input">
			<select name="post_type">
				<option value="post" <?php selected( $post_type, 'post' ); ?>><?php esc_html_e( 'Yazılar', 'ai-seo-editor' ); ?></option>
				<option value="page" <?php selected( $post_type, 'page' ); ?>><?php esc_html_e( 'Sayfalar', 'ai-seo-editor' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'ai-seo-editor' ); ?></button>
		</form>
		<span class="aiseo-toolbar__count">
			<?php echo esc_html( sprintf( __( '%d yazı bulundu', 'ai-seo-editor' ), $total ) ); ?>
		</span>
	</div>

	<div id="aiseo-posts-notice"></div>

	<table class="aiseo-table aiseo-table--posts wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:30%"><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Odak KW', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'İç Link', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Meta', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'İşlemler', 'ai-seo-editor' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $posts ) ) : ?>
			<tr><td colspan="8" class="aiseo-empty"><?php esc_html_e( 'Yazı bulunamadı.', 'ai-seo-editor' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $posts as $post ) :
				$seo_score  = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
				$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
				$keyword    = $yoast->get_focus_keyword( $post->ID );
				$meta_desc  = $yoast->get_meta_description( $post->ID );
				$last_anal  = get_post_meta( $post->ID, '_aiseo_last_analysis', true );
				$content    = apply_filters( 'the_content', $post->post_content );
				$links      = aiseo_extract_links( $content );
				$int_count  = count( $links['internal'] );
			?>
			<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>">
				<td>
					<strong><a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank"><?php echo esc_html( $post->post_title ); ?></a></strong>
					<?php if ( $last_anal ) : ?>
					<div class="aiseo-row-meta"><?php echo esc_html( sprintf( __( 'Son analiz: %s', 'ai-seo-editor' ), human_time_diff( strtotime( $last_anal ) ) . ' önce' ) ); ?></div>
					<?php endif; ?>
				</td>
				<td>
					<span class="aiseo-status aiseo-status--<?php echo esc_attr( $post->post_status ); ?>">
						<?php echo esc_html( get_post_status_object( $post->post_status )->label ?? $post->post_status ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $keyword ?: '—' ); ?></td>
				<td class="aiseo-score-cell aiseo-seo-score-cell" data-score="<?php echo esc_attr( $seo_score ); ?>">
					<?php if ( $seo_score > 0 ) : ?>
						<?php echo wp_kses_post( aiseo_score_badge( $seo_score ) ); ?>
					<?php else : ?>
						<span class="aiseo-badge aiseo-badge--none">—</span>
					<?php endif; ?>
				</td>
				<td class="aiseo-read-score-cell" data-score="<?php echo esc_attr( $read_score ); ?>">
					<?php if ( $read_score > 0 ) : ?>
						<?php echo wp_kses_post( aiseo_score_badge( $read_score ) ); ?>
					<?php else : ?>
						<span class="aiseo-badge aiseo-badge--none">—</span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $int_count ); ?></td>
				<td>
					<?php if ( empty( $meta_desc ) ) : ?>
						<span class="aiseo-badge aiseo-badge--red"><?php esc_html_e( 'Eksik', 'ai-seo-editor' ); ?></span>
					<?php else : ?>
						<span class="aiseo-badge aiseo-badge--green"><?php esc_html_e( 'Var', 'ai-seo-editor' ); ?></span>
					<?php endif; ?>
				</td>
				<td class="aiseo-actions">
					<button class="button button-small aiseo-btn-analyze" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Analiz Et', 'ai-seo-editor' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts&action=detail&post_id=' . $post->ID ) ); ?>" class="button button-small button-primary">
						<?php esc_html_e( 'Detay', 'ai-seo-editor' ); ?>
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="aiseo-pagination">
		<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
			<?php if ( $p === $paged ) : ?>
				<span class="aiseo-page-num aiseo-page-num--current"><?php echo esc_html( $p ); ?></span>
			<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( [ 'paged' => $p ] ) ); ?>" class="aiseo-page-num"><?php echo esc_html( $p ); ?></a>
			<?php endif; ?>
		<?php endfor; ?>
	</div>
	<?php endif; ?>

	<!-- Analiz Sonuç Paneli (inline, AJAX ile dolar) -->
	<div id="aiseo-inline-result" class="aiseo-inline-result" style="display:none">
		<div class="aiseo-inline-result__header">
			<strong id="aiseo-inline-title"></strong>
			<button id="aiseo-inline-close" class="aiseo-btn-close">&times;</button>
		</div>
		<div id="aiseo-inline-body"></div>
	</div>
</div>

<?php if ( isset( $_GET['action'] ) && $_GET['action'] === 'detail' && ! empty( $_GET['post_id'] ) ) :
	$detail_post_id = absint( $_GET['post_id'] );
	$analyzer       = new AISEO_Analyzer();
	$result         = $analyzer->analyze( $detail_post_id );
	include AISEO_PLUGIN_DIR . 'admin/templates/post-detail.php';
endif; ?>
