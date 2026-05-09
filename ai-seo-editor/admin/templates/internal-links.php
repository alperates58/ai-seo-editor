<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-links">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-admin-links',
			'eyebrow'  => __( 'Internal linking intelligence', 'ai-seo-editor' ),
			'title'    => __( 'Ic Link Onerileri', 'ai-seo-editor' ),
			'subtitle' => __( 'Surface missing-link opportunities, generate anchor suggestions, and apply approved links with a more guided recommendation experience.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => __( 'Bulk apply destekli', 'ai-seo-editor' ),
					'tone'  => 'info',
				],
			],
		]
	);
	?>

	<section class="aiseo-stats-grid aiseo-stats-grid--3">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Tarama listesi', 'ai-seo-editor' ), 'value' => (string) count( $posts ), 'meta' => __( 'Secilebilir kaynak yazi', 'ai-seo-editor' ), 'icon' => 'dashicons-media-text', 'tone' => 'info', 'counter' => (string) count( $posts ) ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Akis modeli', 'ai-seo-editor' ), 'value' => __( 'Review + apply', 'ai-seo-editor' ), 'meta' => __( 'Onayladiginiz linkler uygulanir', 'ai-seo-editor' ), 'icon' => 'dashicons-editor-ul', 'tone' => 'success' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Auto-save support', 'ai-seo-editor' ), 'value' => __( 'Enabled', 'ai-seo-editor' ), 'meta' => __( 'Hizli uygulama aksiyonlari hazir', 'ai-seo-editor' ), 'icon' => 'dashicons-saved', 'tone' => 'muted' ] );
		?>
	</section>

	<div class="aiseo-links-layout">
		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Opportunity scan', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Ic link olmayan yazilar', 'ai-seo-editor' ); ?></h2>
				</div>
				<button type="button" id="aiseo-refresh-linkless" class="button button-secondary"><?php esc_html_e( 'Listeyi Yenile', 'ai-seo-editor' ); ?></button>
			</div>
			<div class="aiseo-table-shell">
				<table class="aiseo-table aiseo-data-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Yazi', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Kategori', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Kelime', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Aday', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Son Guncelleme', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Islem', 'ai-seo-editor' ); ?></th>
						</tr>
					</thead>
					<tbody id="aiseo-linkless-tbody">
						<tr><td colspan="6" class="aiseo-empty"><?php esc_html_e( 'Yazilar taraniyor...', 'ai-seo-editor' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Recommendation builder', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Kaynak yazi sec ve onerileri olustur', 'ai-seo-editor' ); ?></h2>
				</div>
			</div>
			<div class="aiseo-form-stack">
				<div class="aiseo-field">
					<label for="aiseo-link-post-select"><?php esc_html_e( 'Kaynak yazi', 'ai-seo-editor' ); ?></label>
					<select id="aiseo-link-post-select" class="aiseo-input aiseo-input--select">
						<option value=""><?php esc_html_e( '-- Yazi secin --', 'ai-seo-editor' ); ?></option>
						<?php foreach ( $posts as $post ) : ?>
							<option value="<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( $post->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aiseo-generator-actions">
					<button type="button" id="aiseo-compute-links" class="button button-primary"><?php esc_html_e( 'Link Onerileri Olustur', 'ai-seo-editor' ); ?></button>
					<span id="aiseo-links-spinner" class="aiseo-spinner" style="display:none"></span>
				</div>
				<div id="aiseo-links-loading" style="display:none" class="aiseo-loading-text"><?php esc_html_e( 'Baglanti firsatlari analiz ediliyor...', 'ai-seo-editor' ); ?></div>
			</div>

			<div class="aiseo-links-info-grid">
				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Opportunity scoring', 'ai-seo-editor' ); ?></strong>
					<p><?php esc_html_e( 'Ayni kategori, benzerlik puani, anchor uygunlugu ve baglam cümlesi birlikte degerlendirilir.', 'ai-seo-editor' ); ?></p>
				</div>
				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Guvenli uygulama', 'ai-seo-editor' ); ?></strong>
					<p><?php esc_html_e( 'Sadece isaretlenen oneriler uygulanir. Editor akisi ve otomatik kayit davranisi korunur.', 'ai-seo-editor' ); ?></p>
				</div>
			</div>
		</section>
	</div>

	<div id="aiseo-links-notice"></div>

	<div id="aiseo-links-results" style="display:none">
		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Smart recommendations', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Link Onerileri', 'ai-seo-editor' ); ?></h2>
				</div>
				<?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Bulk select', 'ai-seo-editor' ), 'info' ) ); ?>
			</div>
			<div class="aiseo-table-shell">
				<table class="aiseo-table aiseo-data-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:30px"><input type="checkbox" id="aiseo-select-all-links"></th>
							<th><?php esc_html_e( 'Hedef Yazi', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Onerilen Anchor Text', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Baglam Cumlesi', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Alaka Puani', 'ai-seo-editor' ); ?></th>
						</tr>
					</thead>
					<tbody id="aiseo-links-tbody"></tbody>
				</table>
			</div>
			<div class="aiseo-form-actions aiseo-sticky-action-bar">
				<button type="button" id="aiseo-apply-links" class="button button-primary"><?php esc_html_e( 'Secili Linkleri Yaziya Ekle', 'ai-seo-editor' ); ?></button>
				<p class="description"><?php esc_html_e( 'Sadece onayladiginiz linkler eklenir. Uygulama oncesi revision olusturulur veya hizli akista auto-save kullanilir.', 'ai-seo-editor' ); ?></p>
			</div>
		</section>
	</div>
</div>
