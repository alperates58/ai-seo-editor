<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var AISEO_Settings $settings */
/** @var array $models */
/** @var array $tones */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'AI SEO Editor — Ayarlar', 'ai-seo-editor' ); ?>
	</h1>

	<div id="aiseo-settings-notice"></div>

	<div class="aiseo-grid aiseo-grid--2">
		<!-- OpenAI Ayarları -->
		<div class="aiseo-card">
			<h2 class="aiseo-card__title"><?php esc_html_e( 'AI API Ayarları', 'ai-seo-editor' ); ?></h2>
			<table class="form-table aiseo-settings-table">
				<tr>
					<th><?php esc_html_e( 'Sağlayıcı', 'ai-seo-editor' ); ?></th>
					<td>
						<select name="ai_provider" id="aiseo-provider">
							<option value="openai" <?php selected( $settings->get( 'ai_provider' ), 'openai' ); ?>>OpenAI</option>
							<option value="deepseek" <?php selected( $settings->get( 'ai_provider' ), 'deepseek' ); ?>>DeepSeek</option>
						</select>
						<p class="description"><?php esc_html_e( 'DeepSeek için model olarak deepseek-v4-flash veya deepseek-v4-pro seçin.', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'API Anahtarı', 'ai-seo-editor' ); ?></th>
					<td>
						<div class="aiseo-api-key-wrap">
							<input type="password" id="aiseo-api-key" name="openai_api_key"
								value="<?php echo esc_attr( $settings->get_masked_api_key() ); ?>"
								class="regular-text" autocomplete="new-password"
								placeholder="sk-...">
							<button type="button" id="aiseo-toggle-key" class="button" title="<?php esc_attr_e( 'Göster/Gizle', 'ai-seo-editor' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" id="aiseo-test-key" class="button">
								<?php esc_html_e( 'Bağlantıyı Test Et', 'ai-seo-editor' ); ?>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'API anahtarınız şifreli olarak saklanır.', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Base URL', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="url" name="ai_base_url" id="aiseo-base-url"
							value="<?php echo esc_attr( $settings->get( 'ai_base_url' ) ); ?>"
							class="regular-text" placeholder="https://api.deepseek.com">
						<p class="description"><?php esc_html_e( 'Boş bırakılırsa OpenAI için https://api.openai.com/v1, DeepSeek için https://api.deepseek.com kullanılır.', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Model', 'ai-seo-editor' ); ?></th>
					<td>
						<select name="openai_model" id="aiseo-model">
							<?php foreach ( $models as $model_id => $model_name ) : ?>
							<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $settings->get( 'openai_model' ), $model_id ); ?>>
								<?php echo esc_html( $model_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Kalite Modu', 'ai-seo-editor' ); ?></th>
					<td>
						<select name="quality_mode" id="aiseo-quality-mode">
							<option value="fast" <?php selected( $settings->get( 'quality_mode' ), 'fast' ); ?>><?php esc_html_e( 'Ekonomik (Hızlı)', 'ai-seo-editor' ); ?></option>
							<option value="balanced" <?php selected( $settings->get( 'quality_mode' ), 'balanced' ); ?>><?php esc_html_e( 'Dengeli', 'ai-seo-editor' ); ?></option>
							<option value="quality" <?php selected( $settings->get( 'quality_mode' ), 'quality' ); ?>><?php esc_html_e( 'Premium (Yavaş)', 'ai-seo-editor' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Maks. Token', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" name="max_tokens" value="<?php echo esc_attr( $settings->get( 'max_tokens' ) ); ?>" min="500" max="8000" class="small-text">
						<p class="description"><?php esc_html_e( 'Yanıt başına maksimum token (500-8000)', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Aylık Token Limiti', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" name="monthly_token_limit" value="<?php echo esc_attr( $settings->get( 'monthly_token_limit' ) ); ?>" min="1000" class="regular-text">
						<p class="description"><?php esc_html_e( 'Bu limiti aşınca AI işlemleri durdurulur.', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Genel Ayarlar -->
		<div class="aiseo-card">
			<h2 class="aiseo-card__title"><?php esc_html_e( 'Genel Ayarlar', 'ai-seo-editor' ); ?></h2>
			<table class="form-table aiseo-settings-table">
				<tr>
					<th><?php esc_html_e( 'Varsayılan Dil', 'ai-seo-editor' ); ?></th>
					<td>
						<select name="default_language">
							<option value="tr" <?php selected( $settings->get( 'default_language' ), 'tr' ); ?>>Türkçe</option>
							<option value="en" <?php selected( $settings->get( 'default_language' ), 'en' ); ?>>English</option>
							<option value="de" <?php selected( $settings->get( 'default_language' ), 'de' ); ?>>Deutsch</option>
							<option value="fr" <?php selected( $settings->get( 'default_language' ), 'fr' ); ?>>Français</option>
							<option value="es" <?php selected( $settings->get( 'default_language' ), 'es' ); ?>>Español</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Varsayılan Ton', 'ai-seo-editor' ); ?></th>
					<td>
						<select name="default_tone">
							<?php foreach ( $tones as $tone_id => $tone_name ) : ?>
							<option value="<?php echo esc_attr( $tone_id ); ?>" <?php selected( $settings->get( 'default_tone' ), $tone_id ); ?>>
								<?php echo esc_html( $tone_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Günlük İşlem Limiti', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" name="daily_limit" value="<?php echo esc_attr( $settings->get( 'daily_limit' ) ); ?>" min="1" max="1000" class="small-text">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Analiz Önbellek Süresi', 'ai-seo-editor' ); ?></th>
					<td>
						<select name="analysis_cache_ttl">
							<option value="3600" <?php selected( $settings->get( 'analysis_cache_ttl' ), 3600 ); ?>><?php esc_html_e( '1 Saat', 'ai-seo-editor' ); ?></option>
							<option value="86400" <?php selected( $settings->get( 'analysis_cache_ttl' ), 86400 ); ?>><?php esc_html_e( '1 Gün', 'ai-seo-editor' ); ?></option>
							<option value="604800" <?php selected( $settings->get( 'analysis_cache_ttl' ), 604800 ); ?>><?php esc_html_e( '1 Hafta', 'ai-seo-editor' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'İşlem Logları', 'ai-seo-editor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_logging" value="1" <?php checked( $settings->get( 'enable_logging' ) ); ?>>
							<?php esc_html_e( 'AI işlemlerini logla', 'ai-seo-editor' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Yoast SEO Senkronizasyonu', 'ai-seo-editor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_yoast_sync" value="1" <?php checked( $settings->get( 'enable_yoast_sync' ) ); ?>>
							<?php esc_html_e( 'AI önerilerini Yoast meta alanlarına da yaz', 'ai-seo-editor' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Yoast SEO kurulu olmadıkça bu seçenek etkisizdir.', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="aiseo-settings-footer">
		<button type="button" id="aiseo-save-settings" class="button button-primary button-large">
			<?php esc_html_e( 'Ayarları Kaydet', 'ai-seo-editor' ); ?>
		</button>
		<span id="aiseo-settings-spinner" class="aiseo-spinner" style="display:none"></span>
	</div>

	<!-- Sistem Bilgisi -->
	<div class="aiseo-card" style="margin-top:20px">
		<h3><?php esc_html_e( 'Sistem Bilgisi', 'ai-seo-editor' ); ?></h3>
		<table class="aiseo-table">
			<tr><td><?php esc_html_e( 'Eklenti Versiyonu', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( AISEO_VERSION ); ?></td></tr>
			<tr><td><?php esc_html_e( 'PHP Versiyonu', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
			<tr><td><?php esc_html_e( 'WordPress Versiyonu', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Yoast SEO', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( ( new AISEO_Yoast_Integration() )->is_yoast_active() ? __( 'Aktif', 'ai-seo-editor' ) : __( 'Aktif Değil', 'ai-seo-editor' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Bu Ay Kullanılan Token', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( number_format( AISEO_Plugin::get_instance()->get_logger()->get_monthly_token_usage() ) ); ?></td></tr>
		</table>
	</div>
</div>
