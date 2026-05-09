<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $logs */
/** @var array $filters */

$monthly_usage = AISEO_Plugin::get_instance()->get_logger()->get_monthly_token_usage();
$monthly_limit = (int) AISEO_Plugin::get_instance()->get_settings()->get( 'monthly_token_limit' );
$usage_pct     = $monthly_limit > 0 ? min( 100, round( $monthly_usage / $monthly_limit * 100 ) ) : 0;
$error_count   = 0;
$success_count = 0;
$models_used   = [];
foreach ( (array) ( $logs['items'] ?? [] ) as $log ) {
	if ( 'error' === ( $log['status'] ?? '' ) ) {
		$error_count++;
	} else {
		$success_count++;
	}
	if ( ! empty( $log['model'] ) ) {
		$models_used[ $log['model'] ] = true;
	}
}
?>
<div class="wrap aiseo-wrap aiseo-admin-shell aiseo-page-logs">
	<?php
	aiseo_admin_page_header(
		[
			'icon'     => 'dashicons-chart-line',
			'eyebrow'  => __( 'Professional activity center', 'ai-seo-editor' ),
			'title'    => __( 'Kullanim / Loglar', 'ai-seo-editor' ),
			'subtitle' => __( 'Monitor token usage, operation volume, errors, and AI model activity from a denser but cleaner reporting layout.', 'ai-seo-editor' ),
			'badges'   => [
				[
					'label' => sprintf( __( '%d kayit', 'ai-seo-editor' ), (int) ( $logs['total'] ?? 0 ) ),
					'tone'  => 'info',
				],
			],
		]
	);
	?>

	<section class="aiseo-stats-grid aiseo-stats-grid--4">
		<?php
		aiseo_admin_stat_card( [ 'label' => __( 'Bu ay token', 'ai-seo-editor' ), 'value' => number_format_i18n( $monthly_usage ), 'meta' => __( 'Toplam kullanilan token', 'ai-seo-editor' ), 'icon' => 'dashicons-performance', 'tone' => 'info' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Aylik limit', 'ai-seo-editor' ), 'value' => number_format_i18n( $monthly_limit ), 'meta' => __( 'Konfigurasyondan gelir', 'ai-seo-editor' ), 'icon' => 'dashicons-lock', 'tone' => 'muted' ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Basarili islemler', 'ai-seo-editor' ), 'value' => (string) $success_count, 'meta' => __( 'Bu sayfadaki filtreli liste', 'ai-seo-editor' ), 'icon' => 'dashicons-yes-alt', 'tone' => 'success', 'counter' => (string) $success_count ] );
		aiseo_admin_stat_card( [ 'label' => __( 'Hata sinyalleri', 'ai-seo-editor' ), 'value' => (string) $error_count, 'meta' => __( 'Yeniden deneme gerektirebilir', 'ai-seo-editor' ), 'icon' => 'dashicons-warning', 'tone' => 'warning', 'counter' => (string) $error_count ] );
		?>
	</section>

	<section class="aiseo-dashboard-grid aiseo-dashboard-grid--secondary">
		<div class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Token analytics', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Kullanim orani', 'ai-seo-editor' ); ?></h2>
				</div>
				<?php echo wp_kses_post( aiseo_admin_status_badge( $usage_pct . '%', $usage_pct >= 90 ? 'danger' : 'info' ) ); ?>
			</div>
			<div class="aiseo-progress-bar aiseo-progress-bar--lg">
				<div class="aiseo-progress-fill<?php echo $usage_pct >= 90 ? ' aiseo-progress-fill--danger' : ''; ?>" style="width:<?php echo esc_attr( $usage_pct ); ?>%"></div>
			</div>
			<p class="aiseo-panel__description"><?php esc_html_e( 'Aylik limit asildiginda AI islemleri durdurulur. Limitler Ayarlar sayfasindan degistirilebilir.', 'ai-seo-editor' ); ?></p>
		</div>
		<div class="aiseo-panel">
			<div class="aiseo-panel__header">
				<div>
					<div class="aiseo-panel__eyebrow"><?php esc_html_e( 'Model usage', 'ai-seo-editor' ); ?></div>
					<h2 class="aiseo-panel__title"><?php esc_html_e( 'Aktif modeller', 'ai-seo-editor' ); ?></h2>
				</div>
			</div>
			<div class="aiseo-inline-badges">
				<?php if ( ! empty( $models_used ) ) : ?>
					<?php foreach ( array_keys( $models_used ) as $model_name ) : ?>
						<?php echo wp_kses_post( aiseo_admin_status_badge( $model_name, 'muted' ) ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<?php echo wp_kses_post( aiseo_admin_status_badge( __( 'Model verisi bekleniyor', 'ai-seo-editor' ), 'muted' ) ); ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="aiseo-panel">
		<form method="get" action="" class="aiseo-filter-bar aiseo-filter-bar--stacked">
			<input type="hidden" name="page" value="aiseo-logs">
			<div class="aiseo-filter-bar__group">
				<select name="operation" class="aiseo-input aiseo-input--select">
					<option value=""><?php esc_html_e( 'Tum Islem Tipleri', 'ai-seo-editor' ); ?></option>
					<?php
					$ops = [ 'optimize_title', 'optimize_meta', 'improve_intro', 'improve_structure', 'improve_readability', 'add_faq', 'generate_article', 'suggest_links' ];
					foreach ( $ops as $op ) :
						?>
						<option value="<?php echo esc_attr( $op ); ?>" <?php selected( $filters['operation_type'] ?? '', $op ); ?>><?php echo esc_html( $op ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status" class="aiseo-input aiseo-input--select">
					<option value=""><?php esc_html_e( 'Tum Durumlar', 'ai-seo-editor' ); ?></option>
					<option value="success" <?php selected( $filters['status'] ?? '', 'success' ); ?>><?php esc_html_e( 'Basarili', 'ai-seo-editor' ); ?></option>
					<option value="error" <?php selected( $filters['status'] ?? '', 'error' ); ?>><?php esc_html_e( 'Hatali', 'ai-seo-editor' ); ?></option>
				</select>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>" class="aiseo-input">
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>" class="aiseo-input">
			</div>
			<div class="aiseo-filter-bar__group">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrele', 'ai-seo-editor' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-logs' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Sifirla', 'ai-seo-editor' ); ?></a>
			</div>
		</form>
	</section>

	<section class="aiseo-panel">
		<div class="aiseo-table-shell">
			<table class="aiseo-table aiseo-data-table aiseo-data-table--interactive wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Tarih', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Islem', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Yazi', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Model', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Input', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Output', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Toplam', 'ai-seo-editor' ); ?></th>
						<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs['items'] ) ) : ?>
						<tr><td colspan="8" class="aiseo-empty"><?php esc_html_e( 'Henuz log kaydi yok.', 'ai-seo-editor' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs['items'] as $log ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $log['created_at'] ) ) ); ?></td>
								<td><code><?php echo esc_html( $log['operation_type'] ); ?></code></td>
								<td>
									<?php if ( $log['post_id'] > 0 ) : ?>
										<a href="<?php echo esc_url( get_permalink( $log['post_id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( aiseo_truncate( get_the_title( $log['post_id'] ) ?: '#' . $log['post_id'], 30 ) ); ?></a>
									<?php else : ?>
										--
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $log['model'] ); ?></code></td>
								<td><?php echo esc_html( number_format( $log['input_tokens'] ) ); ?></td>
								<td><?php echo esc_html( number_format( $log['output_tokens'] ) ); ?></td>
								<td><strong><?php echo esc_html( number_format( $log['total_tokens'] ) ); ?></strong></td>
								<td>
									<?php echo wp_kses_post( aiseo_admin_status_badge( 'success' === $log['status'] ? __( 'Basarili', 'ai-seo-editor' ) : __( 'Hata', 'ai-seo-editor' ), 'success' === $log['status'] ? 'success' : 'danger' ) ); ?>
									<?php if ( 'error' === $log['status'] && ! empty( $log['error_message'] ) ) : ?>
										<div class="aiseo-row-meta"><?php echo esc_html( aiseo_truncate( $log['error_message'], 72 ) ); ?></div>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<?php if ( $logs['total_pages'] > 1 ) : ?>
		<nav class="aiseo-pagination-shell">
			<div class="aiseo-pagination">
				<?php for ( $p = 1; $p <= $logs['total_pages']; $p++ ) : ?>
					<?php if ( $p === $logs['page'] ) : ?>
						<span class="aiseo-page-num aiseo-page-num--current"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( [ 'paged' => $p ] ) ); ?>" class="aiseo-page-num"><?php echo esc_html( $p ); ?></a>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
			<p class="aiseo-pagination-info"><?php echo esc_html( sprintf( __( 'Toplam %d kayit', 'ai-seo-editor' ), $logs['total'] ) ); ?></p>
		</nav>
	<?php endif; ?>
</div>
