<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $settings */
/** @var bool $saved */
/** @var string $update */
/** @var string $error */
/** @var string $last */
/** @var string $sha */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-update"></span>
		<?php esc_html_e( 'AI SEO Editor — GitHub Güncelleme', 'ai-seo-editor' ); ?>
	</h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'GitHub ayarları kaydedildi.', 'ai-seo-editor' ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'success' === $update ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Eklenti GitHub üzerinden başarıyla güncellendi.', 'ai-seo-editor' ); ?></p></div>
	<?php elseif ( $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( rawurldecode( $error ) ); ?></p></div>
	<?php endif; ?>

	<div id="aiseo-github-notice"></div>

	<div class="aiseo-grid aiseo-grid--2">
		<div class="aiseo-card">
			<h2 class="aiseo-card__title">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'GitHub Bağlantısı', 'ai-seo-editor' ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aiseo_save_github_settings' ); ?>
				<input type="hidden" name="action" value="aiseo_save_github_settings">

				<table class="form-table aiseo-settings-table">
					<tr>
						<th><label for="aiseo-github-repo"><?php esc_html_e( 'Repository', 'ai-seo-editor' ); ?></label></th>
						<td>
							<input type="text" id="aiseo-github-repo" name="repo" value="<?php echo esc_attr( $settings['repo'] ); ?>" class="regular-text" placeholder="alperates58/ai-seo-editor">
							<p class="description"><?php esc_html_e( 'GitHub kullanıcı adı ve repository adını birlikte girin.', 'ai-seo-editor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="aiseo-github-branch"><?php esc_html_e( 'Branch', 'ai-seo-editor' ); ?></label></th>
						<td>
							<input type="text" id="aiseo-github-branch" name="branch" value="<?php echo esc_attr( $settings['branch'] ); ?>" class="regular-text" placeholder="main">
						</td>
					</tr>
					<tr>
						<th><label for="aiseo-github-token"><?php esc_html_e( 'Token', 'ai-seo-editor' ); ?></label></th>
						<td>
							<input type="password" id="aiseo-github-token" name="token" value="<?php echo esc_attr( $settings['token'] ); ?>" class="regular-text" autocomplete="new-password" placeholder="ghp_xxxx">
							<p class="description"><?php esc_html_e( 'Public repo için boş bırakabilirsiniz.', 'ai-seo-editor' ); ?></p>
						</td>
					</tr>
				</table>

				<div class="aiseo-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Kaydet', 'ai-seo-editor' ); ?></button>
					<button type="button" id="aiseo-check-github-version" class="button"><?php esc_html_e( 'Son Versiyonu Kontrol Et', 'ai-seo-editor' ); ?></button>
					<span id="aiseo-github-version-result" class="aiseo-muted-inline"></span>
				</div>
			</form>
		</div>

		<div class="aiseo-card">
			<h2 class="aiseo-card__title">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Güncelleme', 'ai-seo-editor' ); ?>
			</h2>

			<table class="aiseo-table">
				<tr><td><?php esc_html_e( 'Son güncelleme', 'ai-seo-editor' ); ?></td><td><strong><?php echo esc_html( $last ); ?></strong></td></tr>
				<tr><td><?php esc_html_e( 'Son commit', 'ai-seo-editor' ); ?></td><td><?php echo $sha ? esc_html( $sha ) : '-'; ?></td></tr>
				<tr><td><?php esc_html_e( 'Aktif sürüm', 'ai-seo-editor' ); ?></td><td><?php echo esc_html( AISEO_VERSION ); ?></td></tr>
			</table>

			<p class="description aiseo-github-description"><?php esc_html_e( 'GitHub üzerindeki en güncel kodu indirip eklentiyi bu panelden yenileyebilirsiniz.', 'ai-seo-editor' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aiseo_update_from_github' ); ?>
				<input type="hidden" name="action" value="aiseo_update_from_github">
				<button type="submit" class="button button-primary button-large" onclick="return confirm('<?php echo esc_js( __( 'GitHub üzerinden güncelleme yapmak istediğinize emin misiniz?', 'ai-seo-editor' ) ); ?>')">
					<?php esc_html_e( "GitHub'dan Güncelle", 'ai-seo-editor' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>
