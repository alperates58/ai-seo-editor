<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $stats */

$settings = AISEO_Plugin::get_instance()->get_settings();
$needs_attention = [
	[
		'label' => __( 'Dusuk SEO skorlu yazilar', 'ai-seo-editor' ),
		'value' => (string) (int) ( $stats['red'] ?? 0 ),
		'tone'  => ! empty( $stats['red'] ) ? 'danger' : 'muted',
	],
	[
		'label' => __( 'Meta aciklamasi eksik', 'ai-seo-editor' ),
		'value' => (string) count( (array) ( $stats['low_score_posts'] ?? [] ) ),
		'tone'  => ! empty( $stats['low_score_posts'] ) ? 'warning' : 'muted',
	],
	[
		'label' => __( 'Bu ay token kullanimi', 'ai-seo-editor' ),
		'value' => number_format_i18n( (int) ( $stats['monthly_tokens'] ?? 0 ) ),
		'tone'  => 'info',
	],
];
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-dashboard">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-chart-area',
			'eyebrow'  => __( 'Executive overview', 'ai-seo-editor' ),
			'title'    => __( 'AI SEO Editor Dashboard', 'ai-seo-editor' ),
			'subtitle' => __( 'SEO health, AI operations, and optimization opportunities in one premium control center.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => $settings->get_api_key() ? __( 'API baglantisi hazir', 'ai-seo-editor' ) : __( 'API anahtari bekleniyor', 'ai-seo-editor' ),
					'tone'  => $settings->get_api_key() ? 'success' : 'warning',
				],
			],
			'actions'  => [
				[
					'label' => __( 'Tum Analizleri Yenile', 'ai-seo-editor' ),
					'class' => 'button-primary',
					'attrs' => [
						'id' => 'aiseo-refresh-all-analyses',
					],
				],
				[
					'label' => __( 'Yazi Analizi', 'ai-seo-editor' ),
					'href'  => admin_url( 'admin.php?page=aiseo-posts' ),
					'class' => 'button-secondary',
				],
			],
		]
	);
	?>

	<div id="aiseo-dashboard-notice"></div>

	<div id="aiseo-dashboard-refresh-progress" class="aiseo-progress-card" style="display:none">
		<div class="aiseo-progress-card__copy">
			<strong><?php esc_html_e( 'Toplu analiz yenileniyor', 'ai-seo-editor' ); ?></strong>
			<span id="aiseo-dashboard-progress-status">0 / 0</span>
		</div>
		<div class="aiseo-progress-bar aiseo-progress-bar--lg">
			<div class="aiseo-progress-fill" id="aiseo-dashboard-progress-bar" style="width:0%"></div>
		</div>
	</div>

	<?php if ( ! $settings->get_api_key() ) : ?>
		<div class="aiseo-notice aiseo-notice--warning">
			<strong><?php esc_html_e( 'OpenAI API anahtari eksik.', 'ai-seo-editor' ); ?></strong>
			<?php esc_html_e( 'AI ozelliklerini etkinlestirmek icin ayarlar sayfasindan baglantiyi tamamlayin.', 'ai-seo-editor' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-settings' ) ); ?>"><?php esc_html_e( 'Ayarlara git', 'ai-seo-editor' ); ?></a>
		</div>
	<?php endif; ?>

	<section class="aiseo-stats-grid aiseo-stats-grid--6">
		<?php
		aiseo_admin_stat_card(
			[
				'label'   => __( 'Toplam yazi', 'ai-seo-editor' ),
				'value'   => (string) (int) ( $stats['total_posts'] ?? 0 ),
				'meta'    => __( 'Yayinlanmis icerik havuzu', 'ai-seo-editor' ),
				'icon'    => 'dashicons-admin-post',
				'tone'    => 'info',
				'href'    => admin_url( 'admin.php?page=aiseo-posts' ),
				'counter' => (string) (int) ( $stats['total_posts'] ?? 0 ),
			]
		);
		aiseo_admin_stat_card(
			[
				'label'   => __( 'Iyi SEO', 'ai-seo-editor' ),
				'value'   => (string) (int) ( $stats['green'] ?? 0 ),
				'meta'    => __( '80+ puan', 'ai-seo-editor' ),
				'icon'    => 'dashicons-yes-alt',
				'tone'    => 'success',
				'href'    => admin_url( 'admin.php?page=aiseo-bulk&score_filter=green' ),
				'counter' => (string) (int) ( $stats['green'] ?? 0 ),
			]
		);
		aiseo_admin_stat_card(
			[
				'label'   => __( 'Gelistirilebilir', 'ai-seo-editor' ),
				'value'   => (string) (int) ( $stats['yellow'] ?? 0 ),
				'meta'    => __( '60-79 arasi', 'ai-seo-editor' ),
				'icon'    => 'dashicons-warning',
				'tone'    => 'warning',
				'href'    => admin_url( 'admin.php?page=aiseo-bulk&score_filter=orange' ),
				'counter' => (string) (int) ( $stats['yellow'] ?? 0 ),
			]
		);
		aiseo_admin_stat_card(
			[
				'label'   => __( 'Needs attention', 'ai-seo-editor' ),
				'value'   => (string) (int) ( $stats['red'] ?? 0 ),
				'meta'    => __( '60 altindaki yazilar', 'ai-seo-editor' ),
				'icon'    => 'dashicons-dismiss',
				'tone'    => 'danger',
				'href'    => admin_url( 'admin.php?page=aiseo-bulk&score_filter=red' ),
				'counter' => (string) (int) ( $stats['red'] ?? 0 ),
			]
		);
		aiseo_admin_stat_card(
			[
				'label' => __( 'Ort. SEO', 'ai-seo-editor' ),
				'value' => (string) ( $stats['avg_seo'] ?? '--' ),
				'meta'  => __( 'Genel optimizasyon seviyesi', 'ai-seo-editor' ),
				'icon'  => 'dashicons-chart-bar',
				'tone'  => 'info',
				'href'  => admin_url( 'admin.php?page=aiseo-bulk' ),
			]
		);
		aiseo_admin_stat_card(
			[
				'label' => __( 'Bu ay token', 'ai-seo-editor' ),
				'value' => number_format_i18n( (int) ( $stats['monthly_tokens'] ?? 0 ) ),
				'meta'  => __( 'Model kullanimi', 'ai-seo-editor' ),
				'icon'  => 'dashicons-performance',
				'tone'  => 'muted',
				'href'  => admin_url( 'admin.php?page=aiseo-logs' ),
			]
		);
		?>
	</section>

	<section class="aiseo-dashboard-grid">
		<div class="aiseo-panel aiseo-panel--hero">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Health overview', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'SEO and readability health', 'ai-seo-editor' ); ?></h2>
				</div>
				<?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Canli ozet', 'ai-seo-editor' ), 'info' ) ); ?>
			</div>
			<div class="aiseo-health-grid">
				<div class="aiseo-health-card">
					<div class="aiseo-health-card__label"><?php esc_html_e( 'SEO ortalamasi', 'ai-seo-editor' ); ?></div>
					<div class="aiseo-health-card__value"><?php echo esc_html( (string) ( $stats['avg_seo'] ?? '--' ) ); ?></div>
					<div class="aiseo-progress-bar"><div class="aiseo-progress-fill" style="width:<?php echo esc_attr( min( 100, max( 0, (int) ( $stats['avg_seo'] ?? 0 ) ) ) ); ?>%"></div></div>
				</div>
				<div class="aiseo-health-card">
					<div class="aiseo-health-card__label"><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></div>
					<div class="aiseo-health-card__value"><?php echo esc_html( (string) ( $stats['avg_readability'] ?? '--' ) ); ?></div>
					<div class="aiseo-progress-bar"><div class="aiseo-progress-fill aiseo-progress-fill--alt" style="width:<?php echo esc_attr( min( 100, max( 0, (int) ( $stats['avg_readability'] ?? 0 ) ) ) ); ?>%"></div></div>
				</div>
				<div class="aiseo-health-card">
					<div class="aiseo-health-card__label"><?php esc_html_e( 'AI operasyonlar', 'ai-seo-editor' ); ?></div>
					<div class="aiseo-health-card__value"><?php echo esc_html( number_format_i18n( count( (array) ( $stats['recent_logs'] ?? [] ) ) ) ); ?></div>
					<div class="aiseo-health-card__hint"><?php esc_html_e( 'Son hareketler dashboardda listeleniyor', 'ai-seo-editor' ); ?></div>
				</div>
			</div>
			<div class="aiseo-attention-list">
				<?php foreach ( $needs_attention as $item ) : ?>
					<div class="aiseo-attention-list__item">
						<span><?php echo esc_html( $item['label'] ); ?></span>
						<?php echo wp_kses_post( aiseo_admin_status_badge( $item['value'], $item['tone'] ) ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Quick actions', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Hizli erisim', 'ai-seo-editor' ); ?></h2>
				</div>
			</div>
			<div class="aiseo-quick-grid">
				<a class="aiseo-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts' ) ); ?>"><span class="dashicons dashicons-search"></span><strong><?php esc_html_e( 'Yazi Analizi', 'ai-seo-editor' ); ?></strong><small><?php esc_html_e( 'Tekil analiz ve editor akislari', 'ai-seo-editor' ); ?></small></a>
				<a class="aiseo-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-generator' ) ); ?>"><span class="dashicons dashicons-edit"></span><strong><?php esc_html_e( 'AI Makale Yaz', 'ai-seo-editor' ); ?></strong><small><?php esc_html_e( 'Yeni icerik taslagi uret', 'ai-seo-editor' ); ?></small></a>
				<a class="aiseo-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-agent' ) ); ?>"><span class="dashicons dashicons-update-alt"></span><strong><?php esc_html_e( 'Otomatik Iyilestirme', 'ai-seo-editor' ); ?></strong><small><?php esc_html_e( 'AI onerileri ve uygulama akisleri', 'ai-seo-editor' ); ?></small></a>
				<a class="aiseo-quick-card" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-links' ) ); ?>"><span class="dashicons dashicons-admin-links"></span><strong><?php esc_html_e( 'Ic Linkler', 'ai-seo-editor' ); ?></strong><small><?php esc_html_e( 'Link firsatlarini uygula', 'ai-seo-editor' ); ?></small></a>
			</div>
		</div>
	</section>

	<section class="aiseo-dashboard-grid aiseo-dashboard-grid--secondary">
		<div class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Needs attention', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Optimize edilmesi onerilen yazilar', 'ai-seo-editor' ); ?></h2>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts' ) ); ?>"><?php esc_html_e( 'Tumunu gor', 'ai-seo-editor' ); ?></a>
			</div>
			<?php if ( ! empty( $stats['low_score_posts'] ) ) : ?>
				<div class="aiseo-table-shell">
					<table class="aiseo-table aiseo-data-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Yazi', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Aksiyon', 'ai-seo-editor' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats['low_score_posts'] as $row ) : ?>
								<tr>
									<td>
										<div class="aiseo-table-title">
											<strong><?php echo esc_html( aiseo_truncate( $row['post_title'], 56 ) ); ?></strong>
											<small>#<?php echo esc_html( (string) $row['post_id'] ); ?></small>
										</div>
									</td>
									<td><?php echo wp_kses_post( aiseo_score_badge( (int) $row['seo_score'] ) ); ?></td>
									<td><?php echo wp_kses_post( aiseo_score_badge( (int) $row['readability_score'] ) ); ?></td>
									<td><a class="button button-small button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts&post_id=' . (int) $row['post_id'] ) ); ?>"><?php esc_html_e( 'Analiz et', 'ai-seo-editor' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<?php
				aiseo_admin_empty_state(
					[
						'icon'        => 'dashicons-yes-alt',
						'title'       => __( 'Kritik problem gorunmuyor', 'ai-seo-editor' ),
						'description' => __( 'Dusuk skorlu yazi listesi bos. Yeni analizler geldikce burada guncellenir.', 'ai-seo-editor' ),
					]
				);
				?>
			<?php endif; ?>
		</div>

		<div class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Recent activity', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Son AI islemleri', 'ai-seo-editor' ); ?></h2>
				</div>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-logs' ) ); ?>"><?php esc_html_e( 'Loglari ac', 'ai-seo-editor' ); ?></a>
			</div>
			<?php if ( ! empty( $stats['recent_logs'] ) ) : ?>
				<div class="aiseo-timeline">
					<?php foreach ( $stats['recent_logs'] as $log ) : ?>
						<div class="aiseo-timeline__item">
							<div class="aiseo-timeline__icon"><span class="dashicons <?php echo 'success' === $log['status'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span></div>
							<div class="aiseo-timeline__body">
								<div class="aiseo-timeline__title-row">
									<strong><?php echo esc_html( $log['operation_type'] ); ?></strong>
									<?php echo wp_kses_post( aiseo_admin_status_badge( (string) $log['status'], 'success' === $log['status'] ? 'success' : 'danger' ) ); ?>
								</div>
								<p><?php echo esc_html( aiseo_truncate( (string) ( $log['post_title'] ?? __( 'Genel islem', 'ai-seo-editor' ) ), 64 ) ); ?></p>
								<small><?php echo esc_html( sprintf( __( '%s token', 'ai-seo-editor' ), number_format_i18n( (int) ( $log['total_tokens'] ?? 0 ) ) ) ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<?php
				aiseo_admin_empty_state(
					[
						'icon'        => 'dashicons-chart-line',
						'title'       => __( 'Henuz AI aktivitesi yok', 'ai-seo-editor' ),
						'description' => __( 'Makale yazma, analiz veya optimizasyon calistirdiginizda aktivite akisi burada gorunur.', 'ai-seo-editor' ),
					]
				);
				?>
			<?php endif; ?>
		</div>
	</section>
</div>
