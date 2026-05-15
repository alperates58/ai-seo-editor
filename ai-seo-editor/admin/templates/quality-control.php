<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $summary */
/** @var string $tab */
/** @var array $items */
/** @var int $paged */

$summary = $summary ?? [];
$tab     = $tab ?? 'shortcode';
$items   = $items ?? [];
$paged   = $paged ?? 1;

$tabs = [
	'shortcode'   => __( 'Shortcode', 'ai-seo-editor' ),
	'meta'        => __( 'SEO Meta', 'ai-seo-editor' ),
	'category'    => __( 'Kategori', 'ai-seo-editor' ),
	'broken_text' => __( 'Bozuk Metin', 'ai-seo-editor' ),
	'risk_notice' => __( 'Risk Uyarıları', 'ai-seo-editor' ),
];

$total_items = (int) ( $items['total'] ?? 0 );
$per_page    = (int) ( $items['per_page'] ?? 25 );
$total_pages = $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 1;
$rows        = (array) ( $items['items'] ?? [] );

$status_labels = [
	'publish' => __( 'Yayında', 'ai-seo-editor' ),
	'draft'   => __( 'Taslak', 'ai-seo-editor' ),
	'pending' => __( 'Bekliyor', 'ai-seo-editor' ),
];

$summary_cards = [
	[
		'key'   => 'shortcode_missing',
		'label' => __( 'Shortcode Eksik', 'ai-seo-editor' ),
		'icon'  => 'dashicons-shortcode',
		'tone'  => ( (int) ( $summary['shortcode_missing'] ?? 0 ) ) > 0 ? 'danger' : 'success',
		'tab'   => 'shortcode',
	],
	[
		'key'   => 'meta_missing',
		'label' => __( 'Meta Eksik', 'ai-seo-editor' ),
		'icon'  => 'dashicons-tag',
		'tone'  => ( (int) ( $summary['meta_missing'] ?? 0 ) ) > 0 ? 'warning' : 'success',
		'tab'   => 'meta',
	],
	[
		'key'   => 'broken_text',
		'label' => __( 'Bozuk Metin', 'ai-seo-editor' ),
		'icon'  => 'dashicons-editor-code',
		'tone'  => ( (int) ( $summary['broken_text'] ?? 0 ) ) > 0 ? 'danger' : 'success',
		'tab'   => 'broken_text',
	],
	[
		'key'   => 'cat_missing',
		'label' => __( 'Kategori Eksik', 'ai-seo-editor' ),
		'icon'  => 'dashicons-category',
		'tone'  => ( (int) ( $summary['cat_missing'] ?? 0 ) ) > 0 ? 'warning' : 'success',
		'tab'   => 'category',
	],
	[
		'key'   => 'seo_title_missing',
		'label' => __( 'SEO Title Eksik', 'ai-seo-editor' ),
		'icon'  => 'dashicons-search',
		'tone'  => ( (int) ( $summary['seo_title_missing'] ?? 0 ) ) > 0 ? 'warning' : 'success',
		'tab'   => 'meta',
	],
];
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-quality-control">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-shield-alt',
			'eyebrow'  => __( 'İçerik kalite raporu', 'ai-seo-editor' ),
			'title'    => __( 'Kalite Kontrol', 'ai-seo-editor' ),
			'subtitle' => __( 'Shortcode eksikleri, meta sorunları, bozuk metin kalıpları ve kategori hataları. Sadece rapor — bu sayfa içerik değiştirmez.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => sprintf( __( '%d toplam sorun', 'ai-seo-editor' ), (int) ( $summary['total_issues'] ?? 0 ) ),
					'tone'  => ( (int) ( $summary['total_issues'] ?? 0 ) ) > 0 ? 'warning' : 'success',
				],
			],
		]
	);
	?>

	<?php /* ── Özet Kart Satırı ── */ ?>
	<section class="aiseo-stats-grid aiseo-stats-grid--5">
		<?php foreach ( $summary_cards as $card ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'aiseo-quality-control', 'tab' => $card['tab'], 'paged' => 1 ], admin_url( 'admin.php' ) ) ); ?>" class="aiseo-stat-card aiseo-stat-card--link aiseo-stat-card--<?php echo esc_attr( $card['tone'] ); ?>">
				<div class="aiseo-stat-card__icon"><span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span></div>
				<div class="aiseo-stat-card__body">
					<div class="aiseo-stat-card__value"><?php echo esc_html( number_format_i18n( (int) ( $summary[ $card['key'] ] ?? 0 ) ) ); ?></div>
					<div class="aiseo-stat-card__label"><?php echo esc_html( $card['label'] ); ?></div>
				</div>
			</a>
		<?php endforeach; ?>
	</section>

	<?php /* ── Sekme Navigasyonu ── */ ?>
	<nav class="aiseo-quality-tabs" aria-label="<?php esc_attr_e( 'Kalite kontrol sekmeleri', 'ai-seo-editor' ); ?>">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a
				href="<?php echo esc_url( add_query_arg( [ 'page' => 'aiseo-quality-control', 'tab' => $tab_key, 'paged' => 1 ], admin_url( 'admin.php' ) ) ); ?>"
				class="aiseo-quality-tab<?php echo $tab === $tab_key ? ' is-active' : ''; ?>"
			>
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php /* ── Sekme Açıklama Satırı ── */ ?>
	<div class="aiseo-quality-tab-desc">
		<?php if ( 'shortcode' === $tab ) : ?>
			<p><?php esc_html_e( 'İçeriğinde [hc_...] shortcode bulunmayan TASLAK yazılar. Her hesaplama yazısında shortcode olmalıdır.', 'ai-seo-editor' ); ?> <span class="aiseo-soft-badge aiseo-soft-badge--muted"><?php esc_html_e( 'Sadece taslaklar', 'ai-seo-editor' ); ?></span></p>
		<?php elseif ( 'meta' === $tab ) : ?>
			<p><?php esc_html_e( 'Yoast meta description eksik olan YAYINLANMIŞ yazılar.', 'ai-seo-editor' ); ?> <span class="aiseo-soft-badge aiseo-soft-badge--info"><?php esc_html_e( 'Sadece yayınlar', 'ai-seo-editor' ); ?></span></p>
		<?php elseif ( 'category' === $tab ) : ?>
			<p><?php esc_html_e( 'Alt kategori atanmamış (sadece ana kategoride kalan) YAYINLANMIŞ yazılar.', 'ai-seo-editor' ); ?> <span class="aiseo-soft-badge aiseo-soft-badge--info"><?php esc_html_e( 'Sadece yayınlar', 'ai-seo-editor' ); ?></span></p>
		<?php elseif ( 'broken_text' === $tab ) : ?>
			<p><?php esc_html_e( 'İçeriğinde bozuk CSS/renk artıkları bulunan YAYINLANMIŞ yazılar: [-0.094rem], [#303030], [#8F8F8F], [9px], [#F4F4F4], [show_150ms_ease-in]', 'ai-seo-editor' ); ?> <span class="aiseo-soft-badge aiseo-soft-badge--info"><?php esc_html_e( 'Sadece yayınlar', 'ai-seo-editor' ); ?></span></p>
		<?php elseif ( 'risk_notice' === $tab ) : ?>
			<p><?php esc_html_e( 'Başlığında sağlık/finans anahtar kelimesi geçen YAYINLANMIŞ yazılar — uyarı kutusu olup olmadığı manuel kontrol edilmeli.', 'ai-seo-editor' ); ?> <span class="aiseo-soft-badge aiseo-soft-badge--info"><?php esc_html_e( 'Sadece yayınlar', 'ai-seo-editor' ); ?></span></p>
		<?php endif; ?>
	</div>

	<?php /* ── Tablo ── */ ?>
	<section class="aiseo-panel">
		<div class="aiseo-panel__header">
			<div>
				<h2 class="aiseo-panel__title">
					<?php echo esc_html( $tabs[ $tab ] ?? '' ); ?>
					<?php if ( $total_items > 0 ) : ?>
						<span class="aiseo-soft-badge aiseo-soft-badge--warn"><?php echo esc_html( number_format_i18n( $total_items ) ); ?></span>
					<?php endif; ?>
				</h2>
			</div>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<div class="aiseo-empty-state">
				<span class="dashicons dashicons-yes-alt"></span>
				<p><?php esc_html_e( 'Bu kategoride sorun bulunamadı. 🎉', 'ai-seo-editor' ); ?></p>
			</div>
		<?php else : ?>
			<div class="aiseo-table-shell">
				<table class="aiseo-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:60px"><?php esc_html_e( 'ID', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></th>
							<th style="width:90px"><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Sorun', 'ai-seo-editor' ); ?></th>
							<th style="width:140px"><?php esc_html_e( 'İşlemler', 'ai-seo-editor' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><small><?php echo esc_html( (string) $row['id'] ); ?></small></td>
								<td>
									<strong><?php echo esc_html( $row['title'] ); ?></strong>
									<?php if ( ! empty( $row['slug'] ) ) : ?>
										<br><small class="aiseo-muted"><?php echo esc_html( $row['slug'] ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$status_label = $status_labels[ $row['status'] ] ?? esc_html( $row['status'] );
									$status_tone  = 'publish' === $row['status'] ? 'success' : 'muted';
									echo wp_kses_post( aiseo_admin_status_badge( $status_label, $status_tone ) );
									?>
								</td>
								<td><small><?php echo esc_html( $row['description'] ); ?></small></td>
								<td>
									<div class="aiseo-table-actions">
										<?php if ( ! empty( $row['edit_url'] ) ) : ?>
											<a href="<?php echo esc_url( $row['edit_url'] ); ?>" class="button button-small"><?php esc_html_e( 'Düzenle', 'ai-seo-editor' ); ?></a>
										<?php endif; ?>
										<?php if ( ! empty( $row['view_url'] ) && 'publish' === $row['status'] ) : ?>
											<a href="<?php echo esc_url( $row['view_url'] ); ?>" class="button button-small" target="_blank" rel="noopener"><?php esc_html_e( 'Görüntüle', 'ai-seo-editor' ); ?></a>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="aiseo-pagination">
					<?php
					$base_url = add_query_arg( [
						'page' => 'aiseo-quality-control',
						'tab'  => $tab,
					], admin_url( 'admin.php' ) );
					if ( $paged > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>" class="button button-secondary aiseo-pagination__btn">&laquo; <?php esc_html_e( 'Önceki', 'ai-seo-editor' ); ?></a>
					<?php endif; ?>
					<span class="aiseo-pagination__info">
						<?php echo esc_html( sprintf( __( 'Sayfa %1$d / %2$d (%3$d sorun)', 'ai-seo-editor' ), $paged, $total_pages, $total_items ) ); ?>
					</span>
					<?php if ( $paged < $total_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>" class="button button-secondary aiseo-pagination__btn"><?php esc_html_e( 'Sonraki', 'ai-seo-editor' ); ?> &raquo;</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</section>
</div>
