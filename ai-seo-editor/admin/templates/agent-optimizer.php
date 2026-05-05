<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-update-alt"></span>
		<?php esc_html_e( 'Otomatik İyileştirme', 'ai-seo-editor' ); ?>
	</h1>

	<div class="aiseo-card">
		<div class="aiseo-bulk-controls">
			<label>
				<?php esc_html_e( 'Hedef SEO', 'ai-seo-editor' ); ?>
				<input type="number" id="aiseo-agent-target-seo" value="80" min="1" max="100" class="small-text">
			</label>
			<label>
				<?php esc_html_e( 'Hedef Okunabilirlik', 'ai-seo-editor' ); ?>
				<input type="number" id="aiseo-agent-target-read" value="75" min="1" max="100" class="small-text">
			</label>
			<label>
				<input type="checkbox" id="aiseo-agent-select-all">
				<?php esc_html_e( 'Tümünü Seç', 'ai-seo-editor' ); ?>
			</label>
			<button type="button" id="aiseo-agent-start" class="button button-primary">
				<?php esc_html_e( 'Seçilenlere Öneri Hazırla', 'ai-seo-editor' ); ?>
			</button>
			<div id="aiseo-agent-progress-wrap" style="display:none">
				<div class="aiseo-progress-bar">
					<div class="aiseo-progress-fill" id="aiseo-agent-progress" style="width:0%"></div>
				</div>
				<span id="aiseo-agent-status"></span>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'DeepSeek düşük skorlu yazılar için başlık, meta ve içerik önerisi hazırlar. Değişiklikler siz Uygula demeden yazıya kaydedilmez.', 'ai-seo-editor' ); ?></p>
	</div>

	<div id="aiseo-agent-notice"></div>

	<table class="aiseo-table wp-list-table widefat fixed striped" id="aiseo-agent-table">
		<thead>
			<tr>
				<th style="width:30px"><input type="checkbox" id="aiseo-agent-select-all-header"></th>
				<th><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'İşlem', 'ai-seo-editor' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $posts as $post ) :
			$seo_score  = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
			$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
		?>
			<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>">
				<td><input type="checkbox" class="aiseo-agent-select" value="<?php echo esc_attr( $post->ID ); ?>"></td>
				<td>
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>">
						<?php echo esc_html( $post->post_title ); ?>
					</a>
				</td>
				<td class="aiseo-agent-seo"><?php echo $seo_score > 0 ? wp_kses_post( aiseo_score_badge( $seo_score ) ) : '<span class="aiseo-badge aiseo-badge--none">—</span>'; ?></td>
				<td class="aiseo-agent-read"><?php echo $read_score > 0 ? wp_kses_post( aiseo_score_badge( $read_score ) ) : '<span class="aiseo-badge aiseo-badge--none">—</span>'; ?></td>
				<td class="aiseo-agent-state"><?php esc_html_e( 'Bekliyor', 'ai-seo-editor' ); ?></td>
				<td class="aiseo-agent-action">
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>" class="button button-small"><?php esc_html_e( 'Editörde Aç', 'ai-seo-editor' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
