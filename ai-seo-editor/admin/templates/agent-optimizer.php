<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */

$total_posts = count( $posts );
$low_candidates = 0;
foreach ( $posts as $post ) {
	$seo_score  = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
	$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
	if ( ( $seo_score > 0 && $seo_score < 80 ) || ( $read_score > 0 && $read_score < 75 ) || 0 === $seo_score || 0 === $read_score ) {
		$low_candidates++;
	}
}
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-agent">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-update-alt',
			'eyebrow'  => __( 'AI workflow center', 'ai-seo-editor' ),
			'title'    => __( 'Otomatik Iyilestirme', 'ai-seo-editor' ),
			'subtitle' => __( 'Prepare AI optimization recommendations, compare status at scale, and apply them only when you are ready.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => sprintf( __( '%d aday yazi', 'ai-seo-editor' ), $low_candidates ),
					'tone'  => 'warning',
				],
			],
		]
	);
	?>

	<section class="aiseo-stats-grid aiseo-stats-grid--3">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Tarama havuzu', 'ai-seo-editor' ), 'value' => (string) $total_posts, 'meta' => __( 'Yayinlanmis icerik', 'ai-seo-editor' ), 'icon' => 'dashicons-database', 'tone' => 'info', 'counter' => (string) $total_posts ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Optimizasyon adayi', 'ai-seo-editor' ), 'value' => (string) $low_candidates, 'meta' => __( 'Hedef skorun altinda', 'ai-seo-editor' ), 'icon' => 'dashicons-warning', 'tone' => 'warning', 'counter' => (string) $low_candidates ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Apply modeli', 'ai-seo-editor' ), 'value' => __( 'Review first', 'ai-seo-editor' ), 'meta' => __( 'Degisiklikler siz onaylamadan kaydedilmez', 'ai-seo-editor' ), 'icon' => 'dashicons-shield', 'tone' => 'success' ] );
		?>
	</section>

	<section class="aiseo-panel aiseo-agent-hero">
		<div class="aiseo-bulk-controls aiseo-bulk-controls--premium">
			<label>
				<?php esc_html_e( 'Hedef SEO', 'ai-seo-editor' ); ?>
				<input type="number" id="aiseo-agent-target-seo" value="80" min="1" max="100" class="small-text">
			</label>
			<label>
				<?php esc_html_e( 'Hedef Okunabilirlik', 'ai-seo-editor' ); ?>
				<input type="number" id="aiseo-agent-target-read" value="75" min="1" max="100" class="small-text">
			</label>
			<label class="aiseo-check-line">
				<input type="checkbox" id="aiseo-agent-select-all">
				<span><?php esc_html_e( 'Tumunu Sec', 'ai-seo-editor' ); ?></span>
			</label>
			<button type="button" id="aiseo-agent-start" class="button button-primary"><?php esc_html_e( 'Secilenlere Oneri Hazirla', 'ai-seo-editor' ); ?></button>
			<button type="button" id="aiseo-agent-refresh-all" class="button button-secondary"><?php esc_html_e( 'Tumunu Yenile', 'ai-seo-editor' ); ?></button>
		</div>
		<div id="aiseo-agent-progress-wrap" class="aiseo-progress-card aiseo-progress-card--inline" style="display:none">
			<div class="aiseo-progress-card__copy">
				<strong><?php esc_html_e( 'AI queue isliyor', 'ai-seo-editor' ); ?></strong>
				<span id="aiseo-agent-status"></span>
			</div>
			<div class="aiseo-progress-bar"><div class="aiseo-progress-fill" id="aiseo-agent-progress" style="width:0%"></div></div>
		</div>
		<p class="aiseo-panel__description"><?php esc_html_e( 'Mevcut DeepSeek / AI optimizasyon akisi korunur. Sistem sadece onerileri hazirlar; siz Uygula demeden kalici degisiklik yapmaz.', 'ai-seo-editor' ); ?></p>
	</section>

	<div id="aiseo-agent-notice"></div>

	<section class="aiseo-panel">
		<div class="aiseo-panel__header">
			<div>
				<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Optimization queue', 'ai-seo-editor' ); ?></div>
				<h2 class="aiseo-panel__title"><?php esc_html_e( 'AI recommendation pipeline', 'ai-seo-editor' ); ?></h2>
			</div>
			<?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Preview before apply', 'ai-seo-editor' ), 'info' ) ); ?>
		</div>
		<div class="aiseo-table-shell">
			<table class="aiseo-table aiseo-data-table aiseo-data-table--interactive wp-list-table widefat fixed striped" id="aiseo-agent-table">
				<thead>
					<tr>
						<th style="width:30px"><input type="checkbox" id="aiseo-agent-select-all-header"></th>
						<th><?php esc_html_e( 'Baslik', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Islem', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php
						$seo_score  = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
						$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
						?>
						<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>">
							<td><input type="checkbox" class="aiseo-agent-select" value="<?php echo esc_attr( $post->ID ); ?>"></td>
							<td>
								<div class="aiseo-table-title">
									<strong><a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></strong>
									<small><?php esc_html_e( 'Before / after preview AI tarafinda hazirlanir', 'ai-seo-editor' ); ?></small>
								</div>
							</td>
							<td class="aiseo-agent-seo"><?php echo $seo_score > 0 ? wp_kses_post( aiseo_score_badge( $seo_score ) ) : wp_kses_post( aiseo_admin_status_badge( '--', 'muted' ) ); ?></td>
							<td class="aiseo-agent-read"><?php echo $read_score > 0 ? wp_kses_post( aiseo_score_badge( $read_score ) ) : wp_kses_post( aiseo_admin_status_badge( '--', 'muted' ) ); ?></td>
							<td class="aiseo-agent-state"><?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Bekliyor', 'ai-seo-editor' ), 'muted' ) ); ?></td>
							<td class="aiseo-agent-action"><a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>" class="button button-small"><?php esc_html_e( 'Editorde Ac', 'ai-seo-editor' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
