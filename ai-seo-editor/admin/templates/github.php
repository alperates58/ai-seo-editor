<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $settings */
/** @var bool $has_token */
/** @var bool $saved */
/** @var string $update */
/** @var string $error */
/** @var string $last */
/** @var string $sha */
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-github">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-update',
			'eyebrow'  => __( 'Release channel manager', 'ai-seo-editor' ),
			'title'    => __( 'GitHub Guncelleme', 'ai-seo-editor' ),
			'subtitle' => __( 'Manage repository connection, compare current plugin state, and run safer update flows from a clearer updater UI.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => $has_token ? __( 'Token hazir', 'ai-seo-editor' ) : __( 'Public mode', 'ai-seo-editor' ),
					'tone'  => $has_token ? 'success' : 'muted',
				],
			],
		]
	);
	?>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'GitHub ayarlari kaydedildi.', 'ai-seo-editor' ); ?></p></div>
	<?php endif; ?>
	<?php if ( 'success' === $update ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Eklenti GitHub uzerinden basariyla guncellendi.', 'ai-seo-editor' ); ?></p></div>
	<?php elseif ( $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( rawurldecode( $error ) ); ?></p></div>
	<?php endif; ?>

	<div id="aiseo-github-notice"></div>

	<section class="aiseo-stats-grid aiseo-stats-grid--4">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Aktif surum', 'ai-seo-editor' ), 'value' => AISEO_VERSION, 'meta' => __( 'Yuklu plugin paketi', 'ai-seo-editor' ), 'icon' => 'dashicons-admin-plugins', 'tone' => 'info' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Son guncelleme', 'ai-seo-editor' ), 'value' => (string) $last, 'meta' => __( 'Kaydedilen son update', 'ai-seo-editor' ), 'icon' => 'dashicons-backup', 'tone' => 'muted' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Commit hash', 'ai-seo-editor' ), 'value' => $sha ? $sha : '--', 'meta' => __( 'Kurulu izleme bilgisi', 'ai-seo-editor' ), 'icon' => 'dashicons-editor-code', 'tone' => 'success' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Update health', 'ai-seo-editor' ), 'value' => $error ? __( 'Needs review', 'ai-seo-editor' ) : __( 'Ready', 'ai-seo-editor' ), 'meta' => __( 'Baglanti ve form akislari hazir', 'ai-seo-editor' ), 'icon' => 'dashicons-shield', 'tone' => $error ? 'warning' : 'success' ] );
		?>
	</section>

	<div class="aiseo-dashboard-grid aiseo-dashboard-grid--secondary">
		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Repository settings', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'GitHub Baglantisi', 'ai-seo-editor' ); ?></h2>
				</div>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiseo-form-stack">
				<?php wp_nonce_field( 'aiseo_save_github_settings' ); ?>
				<input type="hidden" name="action" value="aiseo_save_github_settings">
				<div class="aiseo-field">
					<label for="aiseo-github-repo"><?php esc_html_e( 'Repository', 'ai-seo-editor' ); ?></label>
					<input type="text" id="aiseo-github-repo" name="repo" value="<?php echo esc_attr( $settings['repo'] ); ?>" class="aiseo-input regular-text" placeholder="owner/repo">
				</div>
				<div class="aiseo-field">
					<label for="aiseo-github-branch"><?php esc_html_e( 'Branch', 'ai-seo-editor' ); ?></label>
					<input type="text" id="aiseo-github-branch" name="branch" value="<?php echo esc_attr( $settings['branch'] ); ?>" class="aiseo-input regular-text" placeholder="main">
				</div>
				<div class="aiseo-field">
					<label for="aiseo-github-token"><?php esc_html_e( 'Token', 'ai-seo-editor' ); ?></label>
					<input type="password" id="aiseo-github-token" name="token" value="" class="aiseo-input regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_token ? __( 'Mevcut token korunacak', 'ai-seo-editor' ) : 'ghp_xxxx' ); ?>">
					<p class="description"><?php esc_html_e( 'Public repo ise token bos kalabilir. Private veya rate limit hassasiyetinde token kullanin.', 'ai-seo-editor' ); ?></p>
				</div>
				<div class="aiseo-form-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Kaydet', 'ai-seo-editor' ); ?></button>
					<button type="button" id="aiseo-check-github-version" class="button button-secondary"><?php esc_html_e( 'Son Versiyonu Kontrol Et', 'ai-seo-editor' ); ?></button>
					<span id="aiseo-github-version-result" class="aiseo-muted-inline"></span>
				</div>
			</form>
		</section>

		<section class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Update action', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Profesyonel updater', 'ai-seo-editor' ); ?></h2>
				</div>
			</div>
			<div class="aiseo-info-card-grid">
				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Current version', 'ai-seo-editor' ); ?></strong>
					<p><?php echo esc_html( AISEO_VERSION ); ?></p>
				</div>
				<div class="aiseo-info-card">
					<strong><?php esc_html_e( 'Latest known commit', 'ai-seo-editor' ); ?></strong>
					<p><?php echo esc_html( $sha ? $sha : '--' ); ?></p>
				</div>
			</div>
			<div class="aiseo-notice aiseo-notice--warning">
				<strong><?php esc_html_e( 'Guvenli guncelleme notu', 'ai-seo-editor' ); ?></strong>
				<?php esc_html_e( 'Canli sitede guncelleme yapmadan once yedek almaniz onerilir. Update islemi mevcut updater business logic ile calisir.', 'ai-seo-editor' ); ?>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aiseo_update_from_github' ); ?>
				<input type="hidden" name="action" value="aiseo_update_from_github">
				<button type="submit" class="button button-primary button-large" onclick="return confirm('<?php echo esc_js( __( 'GitHub uzerinden guncelleme yapmak istediginize emin misiniz?', 'ai-seo-editor' ) ); ?>')"><?php esc_html_e( "GitHub'dan Guncelle", 'ai-seo-editor' ); ?></button>
			</form>
			<div class="aiseo-info-card aiseo-info-card--muted">
				<strong><?php esc_html_e( 'Release notes', 'ai-seo-editor' ); ?></strong>
				<p><?php esc_html_e( 'Mevcut updater akisinda release notes verisi ayrica gelmiyor. Bu alan gelecekte veri mevcut oldugunda kullanilmak uzere hazir tutulur.', 'ai-seo-editor' ); ?></p>
			</div>
		</section>
	</div>
</div>
