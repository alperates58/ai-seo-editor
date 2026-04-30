<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $logs */
/** @var array $filters */

$monthly_usage = AISEO_Plugin::get_instance()->get_logger()->get_monthly_token_usage();
$monthly_limit = (int) AISEO_Plugin::get_instance()->get_settings()->get( 'monthly_token_limit' );
$usage_pct     = $monthly_limit > 0 ? min( 100, round( $monthly_usage / $monthly_limit * 100 ) ) : 0;
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-chart-line"></span>
		<?php esc_html_e( 'Kullanım / Loglar', 'ai-seo-editor' ); ?>
	</h1>

	<!-- Token Kullanım Özeti -->
	<div class="aiseo-grid aiseo-grid--3" style="margin-bottom:20px">
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-performance"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( number_format( $monthly_usage ) ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Bu Ay Token', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-controls-forward"></div>
			<div class="aiseo-stat__value"><?php echo esc_html( number_format( $monthly_limit ) ); ?></div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Aylık Limit', 'ai-seo-editor' ); ?></div>
		</div>
		<div class="aiseo-card aiseo-card--stat">
			<div class="aiseo-stat__icon dashicons dashicons-chart-bar"></div>
			<div>
				<div class="aiseo-stat__value"><?php echo esc_html( $usage_pct ); ?>%</div>
				<div class="aiseo-progress-bar" style="margin-top:8px">
					<div class="aiseo-progress-fill <?php echo $usage_pct >= 90 ? 'aiseo-progress-fill--danger' : ''; ?>" style="width:<?php echo esc_attr( $usage_pct ); ?>%"></div>
				</div>
			</div>
			<div class="aiseo-stat__label"><?php esc_html_e( 'Kullanım', 'ai-seo-editor' ); ?></div>
		</div>
	</div>

	<!-- Filtreler -->
	<div class="aiseo-card" style="margin-bottom:20px">
		<form method="get" action="">
			<input type="hidden" name="page" value="aiseo-logs">
			<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
				<div>
					<label><?php esc_html_e( 'İşlem Tipi', 'ai-seo-editor' ); ?></label><br>
					<select name="operation">
						<option value=""><?php esc_html_e( 'Tümü', 'ai-seo-editor' ); ?></option>
						<?php
						$ops = [ 'optimize_title', 'optimize_meta', 'improve_intro', 'improve_structure', 'improve_readability', 'add_faq', 'generate_article', 'suggest_links' ];
						foreach ( $ops as $op ) : ?>
						<option value="<?php echo esc_attr( $op ); ?>" <?php selected( $filters['operation_type'] ?? '', $op ); ?>><?php echo esc_html( $op ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></label><br>
					<select name="status">
						<option value=""><?php esc_html_e( 'Tümü', 'ai-seo-editor' ); ?></option>
						<option value="success" <?php selected( $filters['status'] ?? '', 'success' ); ?>><?php esc_html_e( 'Başarılı', 'ai-seo-editor' ); ?></option>
						<option value="error" <?php selected( $filters['status'] ?? '', 'error' ); ?>><?php esc_html_e( 'Hatalı', 'ai-seo-editor' ); ?></option>
					</select>
				</div>
				<div>
					<label><?php esc_html_e( 'Başlangıç', 'ai-seo-editor' ); ?></label><br>
					<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>">
				</div>
				<div>
					<label><?php esc_html_e( 'Bitiş', 'ai-seo-editor' ); ?></label><br>
					<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>">
				</div>
				<div>
					<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'ai-seo-editor' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-logs' ) ); ?>" class="button"><?php esc_html_e( 'Sıfırla', 'ai-seo-editor' ); ?></a>
				</div>
			</div>
		</form>
	</div>

	<table class="aiseo-table wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Tarih', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'İşlem', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Yazı', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Model', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Giriş Token', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Çıkış Token', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Toplam', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $logs['items'] ) ) : ?>
			<tr><td colspan="8" class="aiseo-empty"><?php esc_html_e( 'Henüz log kaydı yok.', 'ai-seo-editor' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $logs['items'] as $log ) : ?>
			<tr>
				<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $log['created_at'] ) ) ); ?></td>
				<td><code><?php echo esc_html( $log['operation_type'] ); ?></code></td>
				<td>
					<?php if ( $log['post_id'] > 0 ) : ?>
						<a href="<?php echo esc_url( get_permalink( $log['post_id'] ) ); ?>" target="_blank">
							<?php echo esc_html( aiseo_truncate( get_the_title( $log['post_id'] ) ?: '#' . $log['post_id'], 30 ) ); ?>
						</a>
					<?php else : ?>
						—
					<?php endif; ?>
				</td>
				<td><code><?php echo esc_html( $log['model'] ); ?></code></td>
				<td><?php echo esc_html( number_format( $log['input_tokens'] ) ); ?></td>
				<td><?php echo esc_html( number_format( $log['output_tokens'] ) ); ?></td>
				<td><strong><?php echo esc_html( number_format( $log['total_tokens'] ) ); ?></strong></td>
				<td>
					<span class="aiseo-status aiseo-status--<?php echo esc_attr( $log['status'] ); ?>">
						<?php echo esc_html( $log['status'] === 'success' ? __( 'Başarılı', 'ai-seo-editor' ) : __( 'Hata', 'ai-seo-editor' ) ); ?>
					</span>
					<?php if ( $log['status'] === 'error' && ! empty( $log['error_message'] ) ) : ?>
					<span class="aiseo-error-tip" title="<?php echo esc_attr( __( 'İşlem başarısız oldu', 'ai-seo-editor' ) ); ?>">?</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $logs['total_pages'] > 1 ) : ?>
	<div class="aiseo-pagination">
		<?php for ( $p = 1; $p <= $logs['total_pages']; $p++ ) : ?>
			<?php if ( $p === $logs['page'] ) : ?>
				<span class="aiseo-page-num aiseo-page-num--current"><?php echo esc_html( $p ); ?></span>
			<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( [ 'paged' => $p ] ) ); ?>" class="aiseo-page-num"><?php echo esc_html( $p ); ?></a>
			<?php endif; ?>
		<?php endfor; ?>
	</div>
	<p class="aiseo-pagination-info">
		<?php echo esc_html( sprintf( __( 'Toplam %d kayıt', 'ai-seo-editor' ), $logs['total'] ) ); ?>
	</p>
	<?php endif; ?>
</div>
