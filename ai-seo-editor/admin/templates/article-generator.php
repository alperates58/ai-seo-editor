<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Term[] $categories */
/** @var AISEO_Settings $settings */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-edit"></span>
		<?php esc_html_e( 'AI Makale Yaz', 'ai-seo-editor' ); ?>
	</h1>

	<div class="aiseo-grid aiseo-grid--2">
		<!-- Form -->
		<div class="aiseo-card">
			<h2 class="aiseo-card__title"><?php esc_html_e( 'Makale Parametreleri', 'ai-seo-editor' ); ?></h2>
			<table class="form-table aiseo-settings-table">
				<tr>
					<th><label for="aiseo-gen-keyword"><?php esc_html_e( 'Odak Anahtar Kelime *', 'ai-seo-editor' ); ?></label></th>
					<td><input type="text" id="aiseo-gen-keyword" class="regular-text" required placeholder="<?php esc_attr_e( 'örn: wordpress seo eklentisi', 'ai-seo-editor' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="aiseo-gen-title"><?php esc_html_e( 'Makale Başlığı', 'ai-seo-editor' ); ?></label></th>
					<td><input type="text" id="aiseo-gen-title" class="regular-text" placeholder="<?php esc_attr_e( 'Boş bırakırsanız AI üretir', 'ai-seo-editor' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="aiseo-gen-aux-keywords"><?php esc_html_e( 'Yardımcı Anahtar Kelimeler', 'ai-seo-editor' ); ?></label></th>
					<td>
						<input type="text" id="aiseo-gen-aux-keywords" class="regular-text" placeholder="<?php esc_attr_e( 'kelime1, kelime2, kelime3', 'ai-seo-editor' ); ?>">
						<p class="description"><?php esc_html_e( 'Virgülle ayırın', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="aiseo-gen-word-count"><?php esc_html_e( 'Hedef Kelime Sayısı', 'ai-seo-editor' ); ?></label></th>
					<td>
						<select id="aiseo-gen-word-count">
							<option value="800">800</option>
							<option value="1200" selected>1200</option>
							<option value="1500">1500</option>
							<option value="2000">2000</option>
							<option value="2500">2500</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="aiseo-gen-tone"><?php esc_html_e( 'Yazı Tonu', 'ai-seo-editor' ); ?></label></th>
					<td>
						<select id="aiseo-gen-tone">
							<?php foreach ( $settings->get_available_tones() as $tone_id => $tone_name ) : ?>
							<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $settings->get( 'default_tone' ), $tone_id ); ?>>
								<?php echo esc_html( $tone_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="aiseo-gen-language"><?php esc_html_e( 'Dil', 'ai-seo-editor' ); ?></label></th>
					<td>
						<select id="aiseo-gen-language">
							<option value="tr" <?php selected( $settings->get( 'default_language' ), 'tr' ); ?>>Türkçe</option>
							<option value="en" <?php selected( $settings->get( 'default_language' ), 'en' ); ?>>English</option>
							<option value="de" <?php selected( $settings->get( 'default_language' ), 'de' ); ?>>Deutsch</option>
							<option value="fr" <?php selected( $settings->get( 'default_language' ), 'fr' ); ?>>Français</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Seçenekler', 'ai-seo-editor' ); ?></th>
					<td>
						<label><input type="checkbox" id="aiseo-gen-include-faq" checked> <?php esc_html_e( 'FAQ Bölümü Ekle', 'ai-seo-editor' ); ?></label><br>
						<label><input type="checkbox" id="aiseo-gen-include-meta" checked> <?php esc_html_e( 'Meta Açıklama Üret', 'ai-seo-editor' ); ?></label><br>
						<label><input type="checkbox" id="aiseo-gen-include-links"> <?php esc_html_e( 'İç Link Önerisi İste', 'ai-seo-editor' ); ?></label>
					</td>
				</tr>
				<?php if ( ! empty( $categories ) ) : ?>
				<tr>
					<th><label for="aiseo-gen-category"><?php esc_html_e( 'Kategori', 'ai-seo-editor' ); ?></label></th>
					<td>
						<select id="aiseo-gen-category">
							<option value=""><?php esc_html_e( '— Seçin —', 'ai-seo-editor' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php endif; ?>
			</table>

			<div class="aiseo-form-actions">
				<button type="button" id="aiseo-generate-btn" class="button button-primary button-large">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Makaleyi Üret', 'ai-seo-editor' ); ?>
				</button>
				<span id="aiseo-generate-spinner" class="aiseo-spinner" style="display:none"></span>
			</div>
			<div id="aiseo-generate-loading" style="display:none" class="aiseo-loading-text">
				<?php esc_html_e( 'Makale üretiliyor, bu 30-60 saniye sürebilir...', 'ai-seo-editor' ); ?>
			</div>
		</div>

		<!-- Önizleme -->
		<div class="aiseo-card" id="aiseo-preview-card" style="display:none">
			<h2 class="aiseo-card__title"><?php esc_html_e( 'Makale Önizleme', 'ai-seo-editor' ); ?></h2>

			<div class="aiseo-preview-meta">
				<p><strong><?php esc_html_e( 'SEO Başlığı:', 'ai-seo-editor' ); ?></strong> <span id="aiseo-preview-title"></span></p>
				<p><strong><?php esc_html_e( 'Meta Açıklama:', 'ai-seo-editor' ); ?></strong> <span id="aiseo-preview-meta"></span></p>
				<p><strong><?php esc_html_e( 'Kelime Sayısı:', 'ai-seo-editor' ); ?></strong> <span id="aiseo-preview-wc"></span></p>
				<p><strong><?php esc_html_e( 'Odak Kelime:', 'ai-seo-editor' ); ?></strong> <span id="aiseo-preview-keyword"></span></p>
			</div>

			<div id="aiseo-preview-content" class="aiseo-preview-content"></div>

			<div class="aiseo-form-actions" style="margin-top:20px">
				<button type="button" id="aiseo-create-draft-btn" class="button button-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Taslak Olarak Kaydet', 'ai-seo-editor' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div id="aiseo-generator-notice"></div>
</div>
