<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-links">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-admin-links',
			'eyebrow'  => __( 'Internal linking intelligence', 'ai-seo-editor' ),
			'title'    => __( 'Ic Link Onerileri', 'ai-seo-editor' ),
			'subtitle' => __( 'Otomatik yayindaki ayni ic link mantigiyla, ic linki eksik yazilari tarayin, onerileri gozden gecirin ve secili yazilara topluca uygulayin.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => __( 'Scan + review + bulk apply', 'ai-seo-editor' ),
					'tone'  => 'info',
				],
			],
		]
	);
	?>

	<section class="aiseo-stats-grid aiseo-stats-grid--3">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Ic linksiz yazi', 'ai-seo-editor' ), 'value' => '0', 'meta' => __( 'Taramayla guncellenir', 'ai-seo-editor' ), 'icon' => 'dashicons-search', 'tone' => 'info', 'counter' => '0', 'attrs' => [ 'id' => 'aiseo-links-stat-missing' ] ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Hazir oneriler', 'ai-seo-editor' ), 'value' => '0', 'meta' => __( 'Toplu uygulama icin secilir', 'ai-seo-editor' ), 'icon' => 'dashicons-editor-ul', 'tone' => 'success', 'counter' => '0', 'attrs' => [ 'id' => 'aiseo-links-stat-suggestions' ] ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Secili yazi', 'ai-seo-editor' ), 'value' => '0', 'meta' => __( 'Bulk apply kuyrugu', 'ai-seo-editor' ), 'icon' => 'dashicons-saved', 'tone' => 'muted', 'counter' => '0', 'attrs' => [ 'id' => 'aiseo-links-stat-selected' ] ] );
		?>
	</section>

	<div class="aiseo-links-layout aiseo-links-layout--bulk">
		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Opportunity scan', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Ic linki eksik yazilar', 'ai-seo-editor' ); ?></h2>
					<p class="aiseo-panel__description"><?php esc_html_e( 'Ayni kategori ve benzerlik puanina gore ic link verilebilecek yazilar otomatik listelenir.', 'ai-seo-editor' ); ?></p>
				</div>
				<div class="aiseo-row-actions">
					<button type="button" id="aiseo-links-select-all-posts" class="button button-secondary"><?php esc_html_e( 'Tumunu Sec', 'ai-seo-editor' ); ?></button>
					<button type="button" id="aiseo-refresh-link-opportunities" class="button button-secondary"><?php esc_html_e( 'Listeyi Yenile', 'ai-seo-editor' ); ?></button>
				</div>
			</div>

			<div class="aiseo-toolbar-shell aiseo-toolbar-shell--sticky">
				<div class="aiseo-filter-bar">
					<div class="aiseo-toolbar-summary">
						<strong id="aiseo-links-selection-count">0</strong>
						<span><?php esc_html_e( 'yazi secili', 'ai-seo-editor' ); ?></span>
					</div>
					<div class="aiseo-bulk-controls--premium">
						<button type="button" id="aiseo-bulk-apply-links" class="button button-primary"><?php esc_html_e( 'Secilenlere Ic Link Uygula', 'ai-seo-editor' ); ?></button>
					</div>
				</div>
			</div>

			<div class="aiseo-table-shell">
				<table class="aiseo-table aiseo-data-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:36px"><input type="checkbox" id="aiseo-links-check-all"></th>
							<th><?php esc_html_e( 'Yazi', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Kategori', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Kelime', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Oneri', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'En iyi skor', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Islem', 'ai-seo-editor' ); ?></th>
						</tr>
					</thead>
					<tbody id="aiseo-link-opportunities-body">
						<tr><td colspan="7" class="aiseo-empty"><?php esc_html_e( 'Yazilar taraniyor...', 'ai-seo-editor' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Review + apply', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Secili yazi incelemesi', 'ai-seo-editor' ); ?></h2>
					<p class="aiseo-panel__description"><?php esc_html_e( 'Bir satir secerek otomatik yayinda kullanilan ic link adaylarini gorun. Isterseniz tek yaziya, isterseniz toplu olarak uygulayin.', 'ai-seo-editor' ); ?></p>
				</div>
			</div>

			<div id="aiseo-links-review-empty" class="aiseo-empty-state aiseo-empty-state--compact">
				<div class="aiseo-empty-state__icon"><span class="dashicons dashicons-admin-links"></span></div>
				<h3 class="aiseo-empty-state__title"><?php esc_html_e( 'Inceleme icin bir yazi secin', 'ai-seo-editor' ); ?></h3>
				<p class="aiseo-empty-state__description"><?php esc_html_e( 'Soldaki listedeki bir yazinin Incele butonuna tiklayin.', 'ai-seo-editor' ); ?></p>
			</div>

			<div id="aiseo-links-review-panel" style="display:none" class="aiseo-form-stack">
				<div class="aiseo-preview-meta--cards">
					<div>
						<span class="aiseo-row-meta"><?php esc_html_e( 'Kaynak yazi', 'ai-seo-editor' ); ?></span>
						<strong id="aiseo-review-title">-</strong>
					</div>
					<div>
						<span class="aiseo-row-meta"><?php esc_html_e( 'Kategori / kelime', 'ai-seo-editor' ); ?></span>
						<strong id="aiseo-review-meta">-</strong>
					</div>
				</div>

				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Anchor ozeti', 'ai-seo-editor' ); ?></strong>
					<p id="aiseo-review-anchor-summary"><?php esc_html_e( 'Henuz secilmedi.', 'ai-seo-editor' ); ?></p>
				</div>

				<div class="aiseo-table-shell">
					<table class="aiseo-table aiseo-data-table wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width:36px"><input type="checkbox" id="aiseo-review-check-all"></th>
								<th><?php esc_html_e( 'Hedef yazi', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Anchor', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Baglam', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Skor', 'ai-seo-editor' ); ?></th>
							</tr>
						</thead>
						<tbody id="aiseo-review-suggestions-body"></tbody>
					</table>
				</div>

				<div class="aiseo-form-actions">
					<button type="button" id="aiseo-apply-review-links" class="button button-primary"><?php esc_html_e( 'Secili Onerileri Bu Yaziya Uygula', 'ai-seo-editor' ); ?></button>
					<a id="aiseo-review-edit-link" href="#" class="button button-secondary"><?php esc_html_e( 'Yaziyi Ac', 'ai-seo-editor' ); ?></a>
				</div>
			</div>

			<div class="aiseo-links-info-grid">
				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Auto-publisher mantigi', 'ai-seo-editor' ); ?></strong>
					<p><?php esc_html_e( 'Her yazi icin ayni kategorideki en uygun 3 hedef secilir, anchor metni metin icinde aranir; bulunamazsa ilgili linkler bolumu eklenir.', 'ai-seo-editor' ); ?></p>
				</div>
				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Guvenli bulk apply', 'ai-seo-editor' ); ?></strong>
					<p><?php esc_html_e( 'Toplu uygulama, her yazi icin revision olusturup icerigi kaydeder. Degisiklik cikmayan yazilar raporda ayrica gosterilir.', 'ai-seo-editor' ); ?></p>
				</div>
			</div>
		</section>
	</div>

	<div id="aiseo-links-notice"></div>

	<section class="aiseo-panel">
		<div class="aiseo-panel__header">
			<div>
				<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Bulk apply report', 'ai-seo-editor' ); ?></div>
				<h2 class="aiseo-panel__title"><?php esc_html_e( 'Son uygulama ozeti', 'ai-seo-editor' ); ?></h2>
			</div>
		</div>
		<div id="aiseo-links-bulk-summary" class="aiseo-links-bulk-summary">
			<p class="aiseo-row-meta"><?php esc_html_e( 'Henuz toplu uygulama calistirilmadi.', 'ai-seo-editor' ); ?></p>
		</div>
	</section>
</div>
