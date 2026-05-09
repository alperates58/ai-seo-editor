<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Term[] $categories */
/** @var AISEO_Settings $settings */
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-generator">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-edit',
			'eyebrow'  => __( 'Premium AI writing assistant', 'ai-seo-editor' ),
			'title'    => __( 'AI Makale Yaz', 'ai-seo-editor' ),
			'subtitle' => __( 'Create structured SEO-ready drafts with clearer field grouping, quality presets, token-aware hints, and a stronger preview workflow.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => __( 'Draft-first workflow', 'ai-seo-editor' ),
					'tone'  => 'info',
				],
			],
		]
	);
	?>

	<section class="aiseo-stats-grid aiseo-stats-grid--3">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Varsayilan dil', 'ai-seo-editor' ), 'value' => strtoupper( (string) $settings->get( 'default_language' ) ), 'meta' => __( 'Ayarlar ile senkron', 'ai-seo-editor' ), 'icon' => 'dashicons-translation', 'tone' => 'info' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Kalite modu', 'ai-seo-editor' ), 'value' => ucfirst( (string) $settings->get( 'quality_mode' ) ), 'meta' => __( 'Saglayici ayarlarindan gelir', 'ai-seo-editor' ), 'icon' => 'dashicons-star-filled', 'tone' => 'success' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Tahmini token seviyesi', 'ai-seo-editor' ), 'value' => (string) (int) $settings->get( 'max_tokens' ), 'meta' => __( 'Model ust limiti', 'ai-seo-editor' ), 'icon' => 'dashicons-performance', 'tone' => 'muted' ] );
		?>
	</section>

	<div class="aiseo-generator-layout">
		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Step 1', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Makale parametreleri', 'ai-seo-editor' ); ?></h2>
				</div>
			</div>

			<div class="aiseo-form-stack">
				<div class="aiseo-form-card">
					<div class="aiseo-form-card__title"><?php esc_html_e( 'Topic and intent', 'ai-seo-editor' ); ?></div>
					<div class="aiseo-field-grid">
						<div class="aiseo-field">
							<label for="aiseo-gen-keyword"><?php esc_html_e( 'Odak Anahtar Kelime *', 'ai-seo-editor' ); ?></label>
							<input type="text" id="aiseo-gen-keyword" class="aiseo-input regular-text" required placeholder="<?php esc_attr_e( 'orn: wordpress seo eklentisi', 'ai-seo-editor' ); ?>">
							<p class="description"><?php esc_html_e( 'Makalenin ana arama niyetini tanimlar.', 'ai-seo-editor' ); ?></p>
						</div>
						<div class="aiseo-field">
							<label for="aiseo-gen-title"><?php esc_html_e( 'Makale Basligi', 'ai-seo-editor' ); ?></label>
							<input type="text" id="aiseo-gen-title" class="aiseo-input regular-text" placeholder="<?php esc_attr_e( 'Bos birakirsaniz AI uretir', 'ai-seo-editor' ); ?>">
							<p class="description"><?php esc_html_e( 'Dilerseniz yonlendirici bir baslik belirleyin.', 'ai-seo-editor' ); ?></p>
						</div>
					</div>
					<div class="aiseo-field">
						<label for="aiseo-gen-aux-keywords"><?php esc_html_e( 'Yardimci Anahtar Kelimeler', 'ai-seo-editor' ); ?></label>
						<input type="text" id="aiseo-gen-aux-keywords" class="aiseo-input regular-text" placeholder="<?php esc_attr_e( 'kelime1, kelime2, kelime3', 'ai-seo-editor' ); ?>">
						<p class="description"><?php esc_html_e( 'Virgulle ayirarak ikincil sorgular ekleyin.', 'ai-seo-editor' ); ?></p>
					</div>
				</div>

				<div class="aiseo-form-card">
					<div class="aiseo-form-card__title"><?php esc_html_e( 'Step 2: writing profile', 'ai-seo-editor' ); ?></div>
					<div class="aiseo-field-grid aiseo-field-grid--3">
						<div class="aiseo-field">
							<label for="aiseo-gen-word-count"><?php esc_html_e( 'Hedef Kelime Sayisi', 'ai-seo-editor' ); ?></label>
							<select id="aiseo-gen-word-count" class="aiseo-input aiseo-input--select">
								<option value="800">800</option>
								<option value="1200" selected>1200</option>
								<option value="1500">1500</option>
								<option value="2000">2000</option>
								<option value="2500">2500</option>
							</select>
						</div>
						<div class="aiseo-field">
							<label for="aiseo-gen-tone"><?php esc_html_e( 'Yazi Tonu', 'ai-seo-editor' ); ?></label>
							<select id="aiseo-gen-tone" class="aiseo-input aiseo-input--select">
								<?php foreach ( $settings->get_available_tones() as $tone_id => $tone_name ) : ?>
									<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $settings->get( 'default_tone' ), $tone_id ); ?>><?php echo esc_html( $tone_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="aiseo-field">
							<label for="aiseo-gen-language"><?php esc_html_e( 'Dil', 'ai-seo-editor' ); ?></label>
							<select id="aiseo-gen-language" class="aiseo-input aiseo-input--select">
								<option value="tr" <?php selected( $settings->get( 'default_language' ), 'tr' ); ?>>Turkce</option>
								<option value="en" <?php selected( $settings->get( 'default_language' ), 'en' ); ?>>English</option>
								<option value="de" <?php selected( $settings->get( 'default_language' ), 'de' ); ?>>Deutsch</option>
								<option value="fr" <?php selected( $settings->get( 'default_language' ), 'fr' ); ?>>Francais</option>
							</select>
						</div>
					</div>
					<div class="aiseo-chip-row aiseo-chip-row--selectable" data-generator-presets>
						<button type="button" class="aiseo-chip is-active" data-target-words="1200"><?php esc_html_e( 'Balanced', 'ai-seo-editor' ); ?></button>
						<button type="button" class="aiseo-chip" data-target-words="800"><?php esc_html_e( 'Fast draft', 'ai-seo-editor' ); ?></button>
						<button type="button" class="aiseo-chip" data-target-words="2000"><?php esc_html_e( 'Long-form SEO', 'ai-seo-editor' ); ?></button>
					</div>
				</div>

				<div class="aiseo-form-card">
					<div class="aiseo-form-card__title"><?php esc_html_e( 'Step 3: content options', 'ai-seo-editor' ); ?></div>
					<div class="aiseo-check-grid">
						<label class="aiseo-check-tile"><input type="checkbox" id="aiseo-gen-include-faq" checked><span><?php esc_html_e( 'FAQ bolumu ekle', 'ai-seo-editor' ); ?></span></label>
						<label class="aiseo-check-tile"><input type="checkbox" id="aiseo-gen-include-meta" checked><span><?php esc_html_e( 'Meta aciklama uret', 'ai-seo-editor' ); ?></span></label>
						<label class="aiseo-check-tile"><input type="checkbox" id="aiseo-gen-include-links"><span><?php esc_html_e( 'Ic link onerisi iste', 'ai-seo-editor' ); ?></span></label>
						<label class="aiseo-check-tile"><input type="checkbox" id="aiseo-gen-auto-links"><span><?php esc_html_e( 'Taslakta ic linkleri otomatik ekle', 'ai-seo-editor' ); ?></span></label>
					</div>
					<?php if ( ! empty( $categories ) ) : ?>
						<div class="aiseo-field">
							<label for="aiseo-gen-category"><?php esc_html_e( 'Kategori', 'ai-seo-editor' ); ?></label>
							<select id="aiseo-gen-category" class="aiseo-input aiseo-input--select">
								<option value=""><?php esc_html_e( '-- Secin --', 'ai-seo-editor' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="aiseo-generator-actions">
				<button type="button" id="aiseo-generate-btn" class="button button-primary button-large"><?php esc_html_e( 'Makaleyi Uret', 'ai-seo-editor' ); ?></button>
				<span id="aiseo-generate-spinner" class="aiseo-spinner" style="display:none"></span>
				<div class="aiseo-generator-token-hint" data-generator-token-estimate><?php esc_html_e( 'Tahmini taslak seviyesi: 1200 kelime', 'ai-seo-editor' ); ?></div>
			</div>
			<div id="aiseo-generate-loading" style="display:none" class="aiseo-loading-text"><?php esc_html_e( 'Makale uretiliyor, bu islem 30-60 saniye surebilir...', 'ai-seo-editor' ); ?></div>
		</section>

		<section class="aiseo-panel aiseo-panel--preview" id="aiseo-preview-card" style="display:none">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Step 4', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Makale onizleme', 'ai-seo-editor' ); ?></h2>
				</div>
				<?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Ready for draft', 'ai-seo-editor' ), 'success' ) ); ?>
			</div>
			<div class="aiseo-preview-meta aiseo-preview-meta--cards">
				<div><strong><?php esc_html_e( 'SEO Basligi', 'ai-seo-editor' ); ?></strong><span id="aiseo-preview-title"></span></div>
				<div><strong><?php esc_html_e( 'Meta Aciklama', 'ai-seo-editor' ); ?></strong><span id="aiseo-preview-meta"></span></div>
				<div><strong><?php esc_html_e( 'Kelime Sayisi', 'ai-seo-editor' ); ?></strong><span id="aiseo-preview-wc"></span></div>
				<div><strong><?php esc_html_e( 'Odak Kelime', 'ai-seo-editor' ); ?></strong><span id="aiseo-preview-keyword"></span></div>
			</div>
			<div id="aiseo-preview-content" class="aiseo-preview-content"></div>
			<div class="aiseo-form-actions">
				<button type="button" id="aiseo-create-draft-btn" class="button button-primary"><?php esc_html_e( 'Taslak Olarak Kaydet', 'ai-seo-editor' ); ?></button>
			</div>
		</section>
	</div>

	<div id="aiseo-generator-notice"></div>
</div>
