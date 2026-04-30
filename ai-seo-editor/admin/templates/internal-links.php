<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-admin-links"></span>
		<?php esc_html_e( 'İç Link Önerileri', 'ai-seo-editor' ); ?>
	</h1>

	<div class="aiseo-card">
		<p><?php esc_html_e( 'Bir yazı seçin, AI ve içerik benzerliğine göre iç link önerileri oluşturun.', 'ai-seo-editor' ); ?></p>
		<div class="aiseo-link-controls">
			<select id="aiseo-link-post-select" style="min-width:300px">
				<option value=""><?php esc_html_e( '— Yazı seçin —', 'ai-seo-editor' ); ?></option>
				<?php foreach ( $posts as $post ) : ?>
				<option value="<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $post->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" id="aiseo-compute-links" class="button button-primary">
				<?php esc_html_e( 'Link Önerileri Oluştur', 'ai-seo-editor' ); ?>
			</button>
			<span id="aiseo-links-spinner" class="aiseo-spinner" style="display:none"></span>
		</div>
		<div id="aiseo-links-loading" style="display:none" class="aiseo-loading-text">
			<?php esc_html_e( 'Bağlantı fırsatları analiz ediliyor...', 'ai-seo-editor' ); ?>
		</div>
	</div>

	<div id="aiseo-links-notice"></div>

	<div id="aiseo-links-results" style="display:none">
		<div class="aiseo-card">
			<h3><?php esc_html_e( 'Link Önerileri', 'ai-seo-editor' ); ?></h3>
			<table class="aiseo-table wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:30px"><input type="checkbox" id="aiseo-select-all-links"></th>
						<th><?php esc_html_e( 'Hedef Yazı', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Önerilen Anchor Text', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Bağlam Cümlesi', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Alaka Puanı', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody id="aiseo-links-tbody">
				</tbody>
			</table>
			<div class="aiseo-form-actions" style="margin-top:15px">
				<button type="button" id="aiseo-apply-links" class="button button-primary">
					<?php esc_html_e( 'Seçili Linkleri Yazıya Ekle', 'ai-seo-editor' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'Sadece onayladığınız linkler eklenir. Uygulama öncesi revision oluşturulur.', 'ai-seo-editor' ); ?></p>
			</div>
		</div>
	</div>
</div>
