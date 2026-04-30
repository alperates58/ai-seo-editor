<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $stats */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-chart-area"></span>
		<?php esc_html_e( 'AI SEO Editor — Dashboard', 'ai-seo-editor' ); ?>
	</h1>

	<?php if ( ! AISEO_Plugin::get_instance()->get_settings()->get_api_key() ) : ?>
	<div class="aiseo-notice aiseo-notice--warning">
		<strong><?php esc_html_e( 'OpenAI API anahtarı eksik!', 'ai-seo-editor' ); ?></strong>
		<?php esc_html_e( 'AI özelliklerini kullanmak için', 'ai-seo-editor' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-settings' ) ); ?>"><?php esc_html_e( 'Ayarlar', 'ai-seo-editor' ); ?></a>
		<?php esc_html_e( 'sayfasından API anahtarınızı girin.', 'ai-seo-editor' ); ?>
	</div>
	<?php endif; ?>

	<!-- Skor Özet Kartları -->
	<div class="aiseo-grid aiseo-grid--4">
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-admin-post"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( $stats['total_posts'] ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Toplam Yazı', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat aiseo-card--green">
			<div class="aiseo-stat__icon dashicons dashicons-yes-alt"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( $stats['green'] ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'İyi SEO (80+)', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat aiseo-card--orange">
			<div class="aiseo-stat__icon dashicons dashicons-warning"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( $stats['yellow'] ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Geliştirilebilir (60-79)', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat aiseo-card--red">
			<div class="aiseo-stat__icon dashicons dashicons-dismiss"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( $stats['red'] ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Zayıf SEO (&lt;60)', 'ai-seo-editor' ); ?></div>
		</div>
	</div>

	<div class="aiseo-grid aiseo-grid--3" style="margin-top:20px">
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-chart-bar"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( $stats['avg_seo'] ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Ort. SEO Puanı', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-editor-paragraph"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( $stats['avg_readability'] ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Ort. Okunabilirlik', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-editor-quote"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( number_format( $stats['monthly_tokens'] ) ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Bu Ay Token', 'ai-seo-editor' ); ?></div>
		</div>
	</div>

	<div class="aiseo-grid aiseo-grid--2" style="margin-top:20px">

		<!-- Optimize Edilmesi Gereken Yazılar -->
		<div class="aiseo-card">
			<h2 class="aiseo-card__title"><?php esc_html_e( 'Optimize Edilmesi Önerilen Yazılar', 'ai-seo-editor' ); ?></h2>
			<?php if ( ! empty( $stats['low_score_posts'] ) ) : ?>
			<table class="aiseo-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Yazı', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Okun.', 'ai-seo-editor' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $stats['low_score_posts'] as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_permalink( $row['post_id'] ) ); ?>" target="_blank"><?php echo esc_html( aiseo_truncate( $row['post_title'], 40 ) ); ?></a></td>
						<td><?php echo wp_kses_post( aiseo_score_badge( (int) $row['seo_score'] ) ); ?></td>
						<td><?php echo wp_kses_post( aiseo_score_badge( (int) $row['readability_score'] ) ); ?></td>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts&post_id=' . $row['post_id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Analiz', 'ai-seo-editor' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p class="aiseo-empty"><?php esc_html_e( 'Henüz analiz verisi yok.', 'ai-seo-editor' ); ?></p>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts' ) ); ?>" class="aiseo-card__link"><?php esc_html_e( 'Tüm yazıları gör →', 'ai-seo-editor' ); ?></a>
		</div>

		<!-- Son AI İşlemleri -->
		<div class="aiseo-card">
			<h2 class="aiseo-card__title"><?php esc_html_e( 'Son AI İşlemleri', 'ai-seo-editor' ); ?></h2>
			<?php if ( ! empty( $stats['recent_logs'] ) ) : ?>
			<table class="aiseo-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'İşlem', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Yazı', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Token', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $stats['recent_logs'] as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['operation_type'] ); ?></td>
						<td><?php echo esc_html( aiseo_truncate( $log['post_title'] ?? '—', 25 ) ); ?></td>
						<td><?php echo esc_html( number_format( $log['total_tokens'] ) ); ?></td>
						<td>
							<span class="aiseo-status aiseo-status--<?php echo esc_attr( $log['status'] ); ?>">
								<?php echo esc_html( $log['status'] ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<p class="aiseo-empty"><?php esc_html_e( 'Henüz AI işlemi yok.', 'ai-seo-editor' ); ?></p>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-logs' ) ); ?>" class="aiseo-card__link"><?php esc_html_e( 'Tüm logları gör →', 'ai-seo-editor' ); ?></a>
		</div>
	</div>

	<!-- Hızlı Erişim -->
	<div class="aiseo-card" style="margin-top:20px">
		<h2 class="aiseo-card__title"><?php esc_html_e( 'Hızlı Erişim', 'ai-seo-editor' ); ?></h2>
		<div class="aiseo-quick-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts' ) ); ?>" class="aiseo-quick-action">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Yazı Analizi', 'ai-seo-editor' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-generator' ) ); ?>" class="aiseo-quick-action">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Makale Yaz', 'ai-seo-editor' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-bulk' ) ); ?>" class="aiseo-quick-action">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Toplu Analiz', 'ai-seo-editor' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-links' ) ); ?>" class="aiseo-quick-action">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'İç Linkler', 'ai-seo-editor' ); ?>
			</a>
		</div>
	</div>
</div>
