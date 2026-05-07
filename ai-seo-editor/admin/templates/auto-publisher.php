<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var AISEO_Auto_Publisher $auto_publisher */
/** @var array $ap_settings */
/** @var array $categories */
/** @var array $queue */
/** @var array $history */
/** @var string|null $next_run */

$intervals = [
	1   => 'Her 1 saatte bir',
	2   => 'Her 2 saatte bir',
	4   => 'Her 4 saatte bir',
	6   => 'Her 6 saatte bir',
	12  => 'Her 12 saatte bir',
	24  => 'Günde bir kez',
	48  => 'Her 2 günde bir',
	72  => 'Her 3 günde bir',
	168 => 'Haftada bir kez',
];
$tones = [
	'professional' => 'Profesyonel',
	'casual'       => 'Samimi',
	'friendly'     => 'Arkadaşça',
];
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-clock"></span>
		<?php esc_html_e( 'Otomatik Yayın', 'ai-seo-editor' ); ?>
	</h1>

	<div id="aiseo-ap-notice"></div>

	<div class="aiseo-ap-grid">

		<!-- Settings Card -->
		<div class="aiseo-card aiseo-ap-settings-card">
			<h2><?php esc_html_e( 'Ayarlar', 'ai-seo-editor' ); ?></h2>

			<table class="form-table aiseo-form-table">
				<tr>
					<th><?php esc_html_e( 'Otomatik Yayın', 'ai-seo-editor' ); ?></th>
					<td>
						<label class="aiseo-toggle">
							<input type="checkbox" id="aiseo-ap-enabled" <?php checked( $ap_settings['enabled'] ); ?>>
							<span class="aiseo-toggle-slider"></span>
						</label>
						<span id="aiseo-ap-status-label" class="aiseo-ap-status-label <?php echo $ap_settings['enabled'] ? 'active' : 'inactive'; ?>">
							<?php echo $ap_settings['enabled'] ? esc_html__( 'Aktif', 'ai-seo-editor' ) : esc_html__( 'Pasif', 'ai-seo-editor' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Yayın Aralığı', 'ai-seo-editor' ); ?></th>
					<td>
						<select id="aiseo-ap-interval" class="regular-text">
							<?php foreach ( $intervals as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ap_settings['interval_hours'], $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php if ( $next_run ) : ?>
								<?php echo esc_html( sprintf( __( 'Sonraki çalışma: %s', 'ai-seo-editor' ), $next_run ) ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Henüz zamanlanmamış.', 'ai-seo-editor' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Min. SEO Puanı', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" id="aiseo-ap-min-seo" value="<?php echo esc_attr( $ap_settings['min_seo_score'] ); ?>" min="0" max="100" class="small-text">
						<span class="description"><?php esc_html_e( 'Bu puanın altındaki yazılar yayınlanmaz.', 'ai-seo-editor' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Min. Okunabilirlik', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" id="aiseo-ap-min-read" value="<?php echo esc_attr( $ap_settings['min_readability_score'] ); ?>" min="0" max="100" class="small-text">
						<span class="description"><?php esc_html_e( 'Bu puanın altındaki yazılar yayınlanmaz.', 'ai-seo-editor' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Kategori Filtresi', 'ai-seo-editor' ); ?></th>
					<td>
						<select id="aiseo-ap-categories" class="regular-text" multiple style="height:120px">
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>"
									<?php echo in_array( (int) $cat->term_id, array_map( 'intval', $ap_settings['category_ids'] ), true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Boş bırakırsanız tüm kategorilerdeki taslaklar işlenir.', 'ai-seo-editor' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'İç Link Sayısı', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" id="aiseo-ap-links" value="<?php echo esc_attr( $ap_settings['internal_links_count'] ); ?>" min="0" max="10" class="small-text">
						<span class="description"><?php esc_html_e( 'Aynı kategorideki yazılardan eklenecek iç link adedi.', 'ai-seo-editor' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Hedef Kelime Sayısı', 'ai-seo-editor' ); ?></th>
					<td>
						<input type="number" id="aiseo-ap-words" value="<?php echo esc_attr( $ap_settings['target_words'] ); ?>" min="300" max="5000" step="100" class="small-text">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Yazı Tonu', 'ai-seo-editor' ); ?></th>
					<td>
						<select id="aiseo-ap-tone" class="regular-text">
							<?php foreach ( $tones as $val => $label ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ap_settings['tone'], $val ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Seçenekler', 'ai-seo-editor' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="aiseo-ap-faq" <?php checked( $ap_settings['include_faq'] ); ?>>
							<?php esc_html_e( 'FAQ bölümü ekle', 'ai-seo-editor' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" id="aiseo-ap-auto-generate" <?php checked( $ap_settings['auto_generate'] ); ?>>
							<?php esc_html_e( 'Boş taslaklar için başlıktan içerik oluştur', 'ai-seo-editor' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" id="aiseo-ap-optimize" <?php checked( $ap_settings['optimize_before_publish'] ); ?>>
							<?php esc_html_e( 'Yayınlamadan önce tam optimizasyon uygula', 'ai-seo-editor' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<div class="aiseo-ap-actions">
				<button type="button" id="aiseo-ap-save" class="button button-primary">
					<?php esc_html_e( 'Ayarları Kaydet', 'ai-seo-editor' ); ?>
				</button>
				<button type="button" id="aiseo-ap-trigger" class="button">
					<?php esc_html_e( 'Şimdi Çalıştır', 'ai-seo-editor' ); ?>
				</button>
			</div>
		</div>

		<!-- Queue Card -->
		<div class="aiseo-card aiseo-ap-queue-card">
			<div class="aiseo-ap-card-header">
				<h2><?php esc_html_e( 'Taslak Kuyruğu', 'ai-seo-editor' ); ?></h2>
				<button type="button" id="aiseo-ap-refresh-queue" class="button button-small">
					<?php esc_html_e( 'Yenile', 'ai-seo-editor' ); ?>
				</button>
			</div>
			<div id="aiseo-ap-queue-wrap">
				<?php if ( empty( $queue ) ) : ?>
					<p class="aiseo-ap-empty"><?php esc_html_e( 'Kuyrukta taslak yok.', 'ai-seo-editor' ); ?></p>
				<?php else : ?>
					<table class="aiseo-table wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Kategori', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Deneme', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
								<th><?php esc_html_e( 'İşlem', 'ai-seo-editor' ); ?></th>
							</tr>
						</thead>
						<tbody id="aiseo-ap-queue-body">
						<?php foreach ( $queue as $item ) : ?>
							<tr data-post-id="<?php echo esc_attr( $item['id'] ); ?>">
								<td>
									<a href="<?php echo esc_url( $item['edit_url'] ); ?>">
										<?php echo esc_html( $item['title'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( implode( ', ', $item['categories'] ) ?: '—' ); ?></td>
								<td><?php echo esc_html( $item['attempts'] ?: '0' ); ?></td>
								<td>
									<?php if ( $item['score_fail'] ) : ?>
										<span class="aiseo-badge aiseo-badge--red" title="<?php echo esc_attr( $item['score_fail'] ); ?>">
											<?php esc_html_e( 'Puan Düşük', 'ai-seo-editor' ); ?>
										</span>
									<?php else : ?>
										<span class="aiseo-badge aiseo-badge--none"><?php esc_html_e( 'Bekliyor', 'ai-seo-editor' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-small aiseo-ap-skip-btn" data-post-id="<?php echo esc_attr( $item['id'] ); ?>">
										<?php esc_html_e( 'Atla', 'ai-seo-editor' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- History Card -->
		<div class="aiseo-card aiseo-ap-history-card">
			<h2><?php esc_html_e( 'Otomatik Yayınlananlar', 'ai-seo-editor' ); ?></h2>
			<?php if ( empty( $history ) ) : ?>
				<p class="aiseo-ap-empty"><?php esc_html_e( 'Henüz otomatik yayınlanan yazı yok.', 'ai-seo-editor' ); ?></p>
			<?php else : ?>
				<table class="aiseo-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Yayın Tarihi', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
							<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $history as $item ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank">
									<?php echo esc_html( $item['title'] ); ?>
								</a>
								<a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="aiseo-ap-edit-link">
									<?php esc_html_e( 'Düzenle', 'ai-seo-editor' ); ?>
								</a>
							</td>
							<td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item['published_at'] ) ) ); ?></td>
							<td><?php echo $item['seo_score'] > 0 ? wp_kses_post( aiseo_score_badge( $item['seo_score'] ) ) : '<span class="aiseo-badge aiseo-badge--none">—</span>'; ?></td>
							<td><?php echo $item['read_score'] > 0 ? wp_kses_post( aiseo_score_badge( $item['read_score'] ) ) : '<span class="aiseo-badge aiseo-badge--none">—</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div><!-- .aiseo-ap-grid -->
</div>
