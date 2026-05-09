<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var AISEO_Settings $settings */
/** @var array $models */
/** @var array $tones */
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-settings">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-admin-settings',
			'eyebrow'  => __( 'Control center', 'ai-seo-editor' ),
			'title'    => __( 'AI SEO Editor Ayarlar', 'ai-seo-editor' ),
			'subtitle' => __( 'Manage provider connection, models, limits, SEO sync, and system behavior from a categorized premium settings layout.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => $settings->get_api_key() ? __( 'API bagli', 'ai-seo-editor' ) : __( 'API bekleniyor', 'ai-seo-editor' ),
					'tone'  => $settings->get_api_key() ? 'success' : 'warning',
				],
			],
		]
	);
	?>

	<div id="aiseo-settings-notice"></div>

	<div class="aiseo-settings-layout" data-settings-layout>
		<aside class="aiseo-settings-nav">
			<button type="button" class="aiseo-settings-nav__item is-active" data-settings-panel="provider"><?php esc_html_e( 'AI Provider', 'ai-seo-editor' ); ?></button>
			<button type="button" class="aiseo-settings-nav__item" data-settings-panel="models"><?php esc_html_e( 'Models', 'ai-seo-editor' ); ?></button>
			<button type="button" class="aiseo-settings-nav__item" data-settings-panel="limits"><?php esc_html_e( 'Limits', 'ai-seo-editor' ); ?></button>
			<button type="button" class="aiseo-settings-nav__item" data-settings-panel="sync"><?php esc_html_e( 'SEO Sync', 'ai-seo-editor' ); ?></button>
			<button type="button" class="aiseo-settings-nav__item" data-settings-panel="system"><?php esc_html_e( 'Logging & Performance', 'ai-seo-editor' ); ?></button>
			<button type="button" class="aiseo-settings-nav__item" data-settings-panel="automation"><?php esc_html_e( 'Automation', 'ai-seo-editor' ); ?></button>
		</aside>

		<div class="aiseo-settings-panels">
			<section class="aiseo-panel aiseo-settings-panel is-active" data-settings-content="provider">
				<div class="aiseo-panel__header">
					<div>
						<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Connection', 'ai-seo-editor' ); ?></div>
						<h2 class="aiseo-panel__title"><?php esc_html_e( 'AI Provider', 'ai-seo-editor' ); ?></h2>
					</div>
				</div>
				<div class="aiseo-form-stack">
					<div class="aiseo-field">
						<label for="aiseo-provider"><?php esc_html_e( 'Saglayici', 'ai-seo-editor' ); ?></label>
						<select name="ai_provider" id="aiseo-provider" class="aiseo-input aiseo-input--select">
							<option value="openai" <?php selected( $settings->get( 'ai_provider' ), 'openai' ); ?>>OpenAI</option>
							<option value="deepseek" <?php selected( $settings->get( 'ai_provider' ), 'deepseek' ); ?>>DeepSeek</option>
						</select>
						<p class="description"><?php esc_html_e( 'DeepSeek icin model seciminizi Models sekmesinden yapin.', 'ai-seo-editor' ); ?></p>
					</div>
					<div class="aiseo-field">
						<label for="aiseo-api-key"><?php esc_html_e( 'API Anahtari', 'ai-seo-editor' ); ?></label>
						<div class="aiseo-api-key-wrap">
							<input type="password" id="aiseo-api-key" name="openai_api_key" value="<?php echo esc_attr( $settings->get_masked_api_key() ); ?>" class="aiseo-input regular-text" autocomplete="new-password" placeholder="sk-...">
							<button type="button" id="aiseo-toggle-key" class="button" title="<?php esc_attr_e( 'Goster/Gizle', 'ai-seo-editor' ); ?>"><span class="dashicons dashicons-visibility"></span></button>
							<button type="button" id="aiseo-test-key" class="button button-secondary"><?php esc_html_e( 'Baglantiyi Test Et', 'ai-seo-editor' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Anahtar sifreli saklanir. Yildizli deger korunuyorsa yeni anahtar gonderilmez.', 'ai-seo-editor' ); ?></p>
					</div>
					<div class="aiseo-field">
						<label for="aiseo-base-url"><?php esc_html_e( 'Base URL', 'ai-seo-editor' ); ?></label>
						<input type="url" name="ai_base_url" id="aiseo-base-url" value="<?php echo esc_attr( $settings->get( 'ai_base_url' ) ); ?>" class="aiseo-input regular-text" placeholder="https://api.deepseek.com">
						<p class="description"><?php esc_html_e( 'Bos birakirsaniz saglayiciya gore varsayilan endpoint kullanilir.', 'ai-seo-editor' ); ?></p>
					</div>
				</div>
			</section>

			<section class="aiseo-panel aiseo-settings-panel" data-settings-content="models" hidden>
				<div class="aiseo-panel__header"><div><div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Model behavior', 'ai-seo-editor' ); ?></div><h2 class="aiseo-panel__title"><?php esc_html_e( 'Models', 'ai-seo-editor' ); ?></h2></div></div>
				<div class="aiseo-field-grid">
					<div class="aiseo-field">
						<label for="aiseo-model"><?php esc_html_e( 'Model', 'ai-seo-editor' ); ?></label>
						<select name="openai_model" id="aiseo-model" class="aiseo-input aiseo-input--select">
							<?php foreach ( $models as $model_id => $model_name ) : ?>
								<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $settings->get( 'openai_model' ), $model_id ); ?>><?php echo esc_html( $model_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="aiseo-field">
						<label for="aiseo-quality-mode"><?php esc_html_e( 'Kalite Modu', 'ai-seo-editor' ); ?></label>
						<select name="quality_mode" id="aiseo-quality-mode" class="aiseo-input aiseo-input--select">
							<option value="fast" <?php selected( $settings->get( 'quality_mode' ), 'fast' ); ?>><?php esc_html_e( 'Ekonomik (Hizli)', 'ai-seo-editor' ); ?></option>
							<option value="balanced" <?php selected( $settings->get( 'quality_mode' ), 'balanced' ); ?>><?php esc_html_e( 'Dengeli', 'ai-seo-editor' ); ?></option>
							<option value="quality" <?php selected( $settings->get( 'quality_mode' ), 'quality' ); ?>><?php esc_html_e( 'Premium (Yavas)', 'ai-seo-editor' ); ?></option>
						</select>
					</div>
					<div class="aiseo-field">
						<label><?php esc_html_e( 'Varsayilan Ton', 'ai-seo-editor' ); ?></label>
						<select name="default_tone" class="aiseo-input aiseo-input--select">
							<?php foreach ( $tones as $tone_id => $tone_name ) : ?>
								<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $settings->get( 'default_tone' ), $tone_id ); ?>><?php echo esc_html( $tone_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</section>

			<section class="aiseo-panel aiseo-settings-panel" data-settings-content="limits" hidden>
				<div class="aiseo-panel__header"><div><div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Guardrails', 'ai-seo-editor' ); ?></div><h2 class="aiseo-panel__title"><?php esc_html_e( 'Limits', 'ai-seo-editor' ); ?></h2></div></div>
				<div class="aiseo-field-grid">
					<div class="aiseo-field">
						<label><?php esc_html_e( 'Maks. Token', 'ai-seo-editor' ); ?></label>
						<input type="number" name="max_tokens" value="<?php echo esc_attr( $settings->get( 'max_tokens' ) ); ?>" min="500" max="8000" class="aiseo-input small-text">
					</div>
					<div class="aiseo-field">
						<label><?php esc_html_e( 'Aylik Token Limiti', 'ai-seo-editor' ); ?></label>
						<input type="number" name="monthly_token_limit" value="<?php echo esc_attr( $settings->get( 'monthly_token_limit' ) ); ?>" min="1000" class="aiseo-input regular-text">
					</div>
					<div class="aiseo-field">
						<label><?php esc_html_e( 'Gunluk Islem Limiti', 'ai-seo-editor' ); ?></label>
						<input type="number" name="daily_limit" value="<?php echo esc_attr( $settings->get( 'daily_limit' ) ); ?>" min="1" max="1000" class="aiseo-input small-text">
					</div>
				</div>
			</section>

			<section class="aiseo-panel aiseo-settings-panel" data-settings-content="sync" hidden>
				<div class="aiseo-panel__header"><div><div class="aiseo-panel__eyebrow"><?php esc_html_e( 'SEO ecosystem', 'ai-seo-editor' ); ?></div><h2 class="aiseo-panel__title"><?php esc_html_e( 'SEO Sync', 'ai-seo-editor' ); ?></h2></div></div>
				<div class="aiseo-form-stack">
					<div class="aiseo-field">
						<label><?php esc_html_e( 'Varsayilan Dil', 'ai-seo-editor' ); ?></label>
						<select name="default_language" class="aiseo-input aiseo-input--select">
							<option value="tr" <?php selected( $settings->get( 'default_language' ), 'tr' ); ?>>Turkce</option>
							<option value="en" <?php selected( $settings->get( 'default_language' ), 'en' ); ?>>English</option>
							<option value="de" <?php selected( $settings->get( 'default_language' ), 'de' ); ?>>Deutsch</option>
							<option value="fr" <?php selected( $settings->get( 'default_language' ), 'fr' ); ?>>Francais</option>
							<option value="es" <?php selected( $settings->get( 'default_language' ), 'es' ); ?>>Espanol</option>
						</select>
					</div>
					<label class="aiseo-check-tile aiseo-check-tile--wide">
						<input type="checkbox" name="enable_yoast_sync" value="1" <?php checked( $settings->get( 'enable_yoast_sync' ) ); ?>>
						<span><?php esc_html_e( 'AI onerilerini Yoast meta alanlarina da yaz', 'ai-seo-editor' ); ?></span>
					</label>
				</div>
			</section>

			<section class="aiseo-panel aiseo-settings-panel" data-settings-content="system" hidden>
				<div class="aiseo-panel__header"><div><div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Runtime behavior', 'ai-seo-editor' ); ?></div><h2 class="aiseo-panel__title"><?php esc_html_e( 'Logging & Performance', 'ai-seo-editor' ); ?></h2></div></div>
				<div class="aiseo-field-grid">
					<div class="aiseo-field">
						<label><?php esc_html_e( 'Analiz Onbellek Suresi', 'ai-seo-editor' ); ?></label>
						<select name="analysis_cache_ttl" class="aiseo-input aiseo-input--select">
							<option value="3600" <?php selected( $settings->get( 'analysis_cache_ttl' ), 3600 ); ?>><?php esc_html_e( '1 Saat', 'ai-seo-editor' ); ?></option>
							<option value="86400" <?php selected( $settings->get( 'analysis_cache_ttl' ), 86400 ); ?>><?php esc_html_e( '1 Gun', 'ai-seo-editor' ); ?></option>
							<option value="604800" <?php selected( $settings->get( 'analysis_cache_ttl' ), 604800 ); ?>><?php esc_html_e( '1 Hafta', 'ai-seo-editor' ); ?></option>
						</select>
					</div>
					<label class="aiseo-check-tile aiseo-check-tile--wide">
						<input type="checkbox" name="enable_logging" value="1" <?php checked( $settings->get( 'enable_logging' ) ); ?>>
						<span><?php esc_html_e( 'AI islemlerini logla', 'ai-seo-editor' ); ?></span>
					</label>
				</div>
			</section>

			<section class="aiseo-panel aiseo-settings-panel" data-settings-content="automation" hidden>
				<div class="aiseo-panel__header"><div><div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Related areas', 'ai-seo-editor' ); ?></div><h2 class="aiseo-panel__title"><?php esc_html_e( 'Automation & GitHub', 'ai-seo-editor' ); ?></h2></div></div>
				<div class="aiseo-info-card-grid">
					<div class="aiseo-info-card">
						<strong><?php esc_html_e( 'Otomatik Yayin', 'ai-seo-editor' ); ?></strong>
						<p><?php esc_html_e( 'Zamanlama, kuyruk ve publish akislari kendi sayfasinda ayni business logic ile yonetilir.', 'ai-seo-editor' ); ?></p>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-auto-publisher' ) ); ?>"><?php esc_html_e( 'Otomatik Yayin', 'ai-seo-editor' ); ?></a>
					</div>
					<div class="aiseo-info-card">
						<strong><?php esc_html_e( 'GitHub Updates', 'ai-seo-editor' ); ?></strong>
						<p><?php esc_html_e( 'Guncelleme kanali ayarlari ayri ekranda korunur. Buradan hizli erisim verilir.', 'ai-seo-editor' ); ?></p>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-github' ) ); ?>"><?php esc_html_e( 'GitHub sayfasini ac', 'ai-seo-editor' ); ?></a>
					</div>
				</div>
			</section>
		</div>
	</div>

	<div class="aiseo-sticky-action-bar aiseo-settings-footer">
		<button type="button" id="aiseo-save-settings" class="button button-primary button-large"><?php esc_html_e( 'Ayarlari Kaydet', 'ai-seo-editor' ); ?></button>
		<span id="aiseo-settings-spinner" class="aiseo-spinner" style="display:none"></span>
	</div>

	<section class="aiseo-panel aiseo-system-info-panel">
		<div class="aiseo-panel__header"><div><div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Environment', 'ai-seo-editor' ); ?></div><h2 class="aiseo-panel__title"><?php esc_html_e( 'Sistem Bilgisi', 'ai-seo-editor' ); ?></h2></div></div>
		<div class="aiseo-table-shell">
			<table class="aiseo-table">
				<tr><td><?php esc_html_e( 'Eklenti Versiyonu', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( AISEO_VERSION ); ?></td></tr>
				<tr><td><?php esc_html_e( 'PHP Versiyonu', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
				<tr><td><?php esc_html_e( 'WordPress Versiyonu', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Yoast SEO', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( ( new AISEO_Yoast_Integration() )->is_yoast_active() ? __( 'Aktif', 'ai-seo-editor' ) : __( 'Aktif Degil', 'ai-seo-editor' ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Bu Ay Kullanilan Token', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( number_format( AISEO_Plugin::get_instance()->get_logger()->get_monthly_token_usage() ) ); ?></td></tr>
			</table>
		</div>
	</section>
</div>
