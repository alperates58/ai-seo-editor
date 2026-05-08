<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var AISEO_Auto_Publisher $auto_publisher */
/** @var array $ap_settings */
/** @var array $categories */
/** @var array $queue */
/** @var int $total_queue_count */
/** @var array $history */
/** @var string|null $next_run */

$intervals = [
	'0.5' => 'Her 30 dakikada bir',
	1   => 'Her 1 saatte bir',
	2   => 'Her 2 saatte bir',
	4   => 'Her 4 saatte bir',
	6   => 'Her 6 saatte bir',
	12  => 'Her 12 saatte bir',
	24  => 'Gunde bir kez',
	48  => 'Her 2 gunde bir',
	72  => 'Her 3 gunde bir',
	168 => 'Haftada bir kez',
];
$tones = [
	'professional' => 'Profesyonel',
	'casual'       => 'Samimi',
	'friendly'     => 'Arkadasca',
];
$selected_category_ids = array_map( 'intval', $ap_settings['category_ids'] ?? [] );
$today                 = current_time( 'Y-m-d' );
$published_today       = 0;
$score_pool            = [];
$read_pool             = [];
$failed_count          = 0;
$estimated_traffic     = 0;
$activity_items        = [];

$build_badge_class = static function ( int $score ): string {
	if ( $score >= 80 ) {
		return 'good';
	}
	if ( $score >= 60 ) {
		return 'warn';
	}
	if ( $score > 0 ) {
		return 'bad';
	}
	return 'idle';
};

$estimate_traffic = static function ( int $seo_score, int $read_score ): int {
	if ( $seo_score <= 0 && $read_score <= 0 ) {
		return 0;
	}
	return max( 15, (int) round( ( max( $seo_score, 0 ) * 0.9 ) + ( max( $read_score, 0 ) * 0.55 ) ) );
};

$estimate_keyword_volume = static function ( string $keyword, array $item_categories ): int {
	$keyword = trim( $keyword );
	if ( '' === $keyword ) {
		return 0;
	}
	$word_count = max( 1, count( preg_split( '/\s+/', $keyword ) ) );
	return min( 9800, (int) round( ( mb_strlen( $keyword ) * 42 ) + ( $word_count * 180 ) + ( count( $item_categories ) * 65 ) ) );
};

$estimate_confidence = static function ( array $item ): array {
	if ( ! empty( $item['score_fail'] ) ) {
		return [ 'Dusuk', 'bad' ];
	}
	if ( (int) ( $item['seo_score'] ?? 0 ) >= 80 && (int) ( $item['read_score'] ?? 0 ) >= 70 ) {
		return [ 'Yuksek', 'good' ];
	}
	if ( (int) ( $item['attempts'] ?? 0 ) > 0 ) {
		return [ 'Orta', 'warn' ];
	}
	return [ 'Hazirlaniyor', 'idle' ];
};

$build_preview_payload = static function ( array $item, string $type = 'queue' ) use ( $estimate_traffic, $estimate_keyword_volume, $estimate_confidence ) : array {
	$post_id          = (int) ( $item['id'] ?? 0 );
	$post             = $post_id ? get_post( $post_id ) : null;
	$title            = (string) ( $item['title'] ?? ( $post ? $post->post_title : '' ) );
	$content          = $post ? (string) $post->post_content : '';
	$plain_content    = trim( wp_strip_all_tags( $content ) );
	$excerpt          = $plain_content ? wp_trim_words( $plain_content, 48, '...' ) : 'Icerik onizlemesi henuz olusmadi.';
	$keyword          = trim( (string) get_post_meta( $post_id, '_aiseo_focus_keyword', true ) );
	$meta_description = trim( (string) get_post_meta( $post_id, '_aiseo_meta_description', true ) );
	if ( '' === $meta_description ) {
		$meta_description = trim( (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) );
	}
	$faq_count        = 0;
	$internal_links   = [];

	if ( $content ) {
		$faq_count = preg_match_all( '/<h[23][^>]*>.*?\?<\/h[23]>/i', $content );
		if ( preg_match_all( '/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( array_slice( $matches, 0, 3 ) as $match ) {
				$internal_links[] = trim( wp_strip_all_tags( $match[2] ) );
			}
		}
	}

	$seo_score       = (int) ( $item['seo_score'] ?? 0 );
	$read_score      = (int) ( $item['read_score'] ?? 0 );
	$traffic         = $estimate_traffic( $seo_score, $read_score );
	$keyword_volume  = $estimate_keyword_volume( $keyword ?: $title, (array) ( $item['categories'] ?? [] ) );
	$confidence_data = $estimate_confidence( $item );
	$last_action     = ! empty( $item['published_at'] ) ? (string) $item['published_at'] : (string) get_post_meta( $post_id, '_aiseo_auto_publish_last_attempt', true );
	if ( '' === $last_action && ! empty( $item['date'] ) ) {
		$last_action = (string) $item['date'];
	}

	return [
		'id'               => $post_id,
		'type'             => $type,
		'title'            => $title ?: 'Basliksiz taslak',
		'excerpt'          => $excerpt,
		'meta'             => $meta_description ?: 'Meta description henuz olusturulmadi.',
		'seoScore'         => $seo_score,
		'readScore'        => $read_score,
		'traffic'          => $traffic,
		'keyword'          => $keyword ?: $title,
		'keywordVolume'    => $keyword_volume,
		'confidence'       => $confidence_data[0],
		'confidenceTone'   => $confidence_data[1],
		'faqCount'         => (int) $faq_count,
		'internalLinks'    => $internal_links,
		'categories'       => array_values( (array) ( $item['categories'] ?? [] ) ),
		'lastAction'       => $last_action,
		'editUrl'          => (string) ( $item['edit_url'] ?? '' ),
		'previewUrl'       => $post_id ? get_preview_post_link( $post_id ) : '',
		'status'           => ! empty( $item['score_fail'] ) ? 'Basarisiz' : ( 'history' === $type ? 'Yayinlandi' : 'Bekliyor' ),
		'statusDetail'     => (string) ( $item['score_fail'] ?? '' ),
		'canPublishDirect' => 'queue' === $type,
	];
};

foreach ( $queue as $item ) {
	if ( ! empty( $item['score_fail'] ) ) {
		$failed_count++;
	}
	if ( ! empty( $item['seo_score'] ) ) {
		$score_pool[] = (int) $item['seo_score'];
	}
	if ( ! empty( $item['read_score'] ) ) {
		$read_pool[] = (int) $item['read_score'];
	}
	$estimated_traffic += $estimate_traffic( (int) ( $item['seo_score'] ?? 0 ), (int) ( $item['read_score'] ?? 0 ) );
}

foreach ( $history as $item ) {
	if ( ! empty( $item['published_at'] ) && gmdate( 'Y-m-d', strtotime( $item['published_at'] ) ) === $today ) {
		$published_today++;
	}
	if ( ! empty( $item['seo_score'] ) ) {
		$score_pool[] = (int) $item['seo_score'];
	}
	if ( ! empty( $item['read_score'] ) ) {
		$read_pool[] = (int) $item['read_score'];
	}
	$estimated_traffic += $estimate_traffic( (int) ( $item['seo_score'] ?? 0 ), (int) ( $item['read_score'] ?? 0 ) );
}

$average_seo         = ! empty( $score_pool ) ? (int) round( array_sum( $score_pool ) / count( $score_pool ) ) : 0;
$average_readability = ! empty( $read_pool ) ? (int) round( array_sum( $read_pool ) / count( $read_pool ) ) : 0;

$kpi_cards = [
	[
		'label'    => 'Bekleyen Taslak',
		'value'    => $total_queue_count,
		'accent'   => 'violet',
		'mini'     => empty( $total_queue_count ) ? 'Kuyruk temiz' : 'Sirada islenmeyi bekliyor',
		'icon'     => 'dashicons-edit-page',
	],
	[
		'label'    => 'Bugun Yayinlanan',
		'value'    => $published_today,
		'accent'   => 'blue',
		'mini'     => $published_today > 0 ? 'Otomasyon aktif ilerliyor' : 'Yeni publish bekleniyor',
		'icon'     => 'dashicons-yes-alt',
	],
	[
		'label'    => 'Ortalama SEO',
		'value'    => $average_seo ? $average_seo . '/100' : '--',
		'accent'   => 'emerald',
		'mini'     => $average_seo >= 80 ? 'Guclu optimizasyon' : 'Iyilestirme alani var',
		'icon'     => 'dashicons-chart-line',
	],
	[
		'label'    => 'Ortalama Okunabilirlik',
		'value'    => $average_readability ? $average_readability . '/100' : '--',
		'accent'   => 'amber',
		'mini'     => $average_readability >= 70 ? 'Akici icerik' : 'Daha sade metin gerekir',
		'icon'     => 'dashicons-welcome-write-blog',
	],
	[
		'label'    => 'Basarisiz Islem',
		'value'    => $failed_count,
		'accent'   => 'rose',
		'mini'     => $failed_count > 0 ? 'Esik altinda bekleyenler var' : 'Hata sinyali yok',
		'icon'     => 'dashicons-warning',
	],
	[
		'label'    => 'Tahmini Trafik',
		'value'    => $estimated_traffic > 0 ? '~' . number_format_i18n( $estimated_traffic ) : '--',
		'accent'   => 'sky',
		'mini'     => 'Skor bazli operasyon tahmini',
		'icon'     => 'dashicons-chart-area',
	],
];

foreach ( array_slice( $history, 0, 3 ) as $item ) {
	$activity_items[] = [
		'icon'        => 'dashicons-megaphone',
		'title'       => 'Icerik yayinlandi',
		'description' => $item['title'],
		'time'        => ! empty( $item['published_at'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $item['published_at'] ) ) : 'Az once',
		'tone'        => 'good',
	];
}
foreach ( array_slice( $queue, 0, 3 ) as $item ) {
	$activity_items[] = [
		'icon'        => ! empty( $item['score_fail'] ) ? 'dashicons-warning' : 'dashicons-update',
		'title'       => ! empty( $item['score_fail'] ) ? 'SEO esigi kontrol edildi' : 'AI kuyrugu hazirlandi',
		'description' => $item['title'],
		'time'        => ! empty( $item['date'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $item['date'] ) ) : 'Bekliyor',
		'tone'        => ! empty( $item['score_fail'] ) ? 'bad' : 'info',
	];
}

if ( empty( $activity_items ) ) {
	$activity_items[] = [
		'icon'        => 'dashicons-superhero',
		'title'       => 'AI pipeline hazir',
		'description' => 'Ilk otomatik icerik akisiniz burada gorunecek.',
		'time'        => 'Beklemede',
		'tone'        => 'info',
	];
}
?>
<div class="wrap aiseo-wrap aiseo-ap-shell">
	<div class="aiseo-ap-hero">
		<div>
			<div class="aiseo-ap-eyebrow">AI-powered publishing ops</div>
			<h1 class="aiseo-page-title aiseo-ap-title">
				<span class="dashicons dashicons-clock"></span>
				<?php esc_html_e( 'Otomatik Yayin', 'ai-seo-editor' ); ?>
			</h1>
			<p class="aiseo-ap-subtitle">
				<?php esc_html_e( 'Taslak kuyruğunu, AI içerik akışını ve yayın operasyonunu tek ekranda yönetin.', 'ai-seo-editor' ); ?>
			</p>
		</div>
		<div class="aiseo-ap-hero-status">
			<div class="aiseo-ap-live-pill">
				<span class="aiseo-ap-live-dot"></span>
				<?php echo $ap_settings['enabled'] ? esc_html__( 'Otomasyon aktif', 'ai-seo-editor' ) : esc_html__( 'Otomasyon pasif', 'ai-seo-editor' ); ?>
			</div>
			<div class="aiseo-ap-next-run">
				<span class="dashicons dashicons-backup"></span>
				<span id="aiseo-ap-next-run-text">
					<?php echo $next_run ? esc_html( sprintf( __( 'Sonraki calisma: %s', 'ai-seo-editor' ), $next_run ) ) : esc_html__( 'Henuz zamanlanmamis.', 'ai-seo-editor' ); ?>
				</span>
			</div>
		</div>
	</div>

	<div id="aiseo-ap-notice"></div>

	<div class="aiseo-ap-kpis">
		<?php foreach ( $kpi_cards as $card ) : ?>
			<div class="aiseo-ap-kpi aiseo-ap-kpi--<?php echo esc_attr( $card['accent'] ); ?>">
				<div class="aiseo-ap-kpi__icon">
					<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span>
				</div>
				<div class="aiseo-ap-kpi__body">
					<div class="aiseo-ap-kpi__label"><?php echo esc_html( $card['label'] ); ?></div>
					<div class="aiseo-ap-kpi__value<?php echo 'Bekleyen Taslak' === $card['label'] ? ' aiseo-ap-kpi__value--queue-count' : ''; ?>" data-counter-target="<?php echo esc_attr( is_numeric( $card['value'] ) ? (string) $card['value'] : preg_replace( '/[^0-9]/', '', (string) $card['value'] ) ); ?>">
						<?php echo esc_html( (string) $card['value'] ); ?>
					</div>
					<div class="aiseo-ap-kpi__mini"><?php echo esc_html( $card['mini'] ); ?></div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="aiseo-ap-dashboard">
		<section class="aiseo-card aiseo-ap-panel aiseo-ap-config">
			<div class="aiseo-ap-panel__header">
				<div>
					<h2><?php esc_html_e( 'AI Configuration', 'ai-seo-editor' ); ?></h2>
					<p><?php esc_html_e( 'Mevcut yayın akışını bozmadan kriterleri ve içerik kalıplarını yönetin.', 'ai-seo-editor' ); ?></p>
				</div>
				<span id="aiseo-ap-status-label" class="aiseo-ap-status-label <?php echo $ap_settings['enabled'] ? 'active' : 'inactive'; ?>">
					<?php echo $ap_settings['enabled'] ? esc_html__( 'Aktif', 'ai-seo-editor' ) : esc_html__( 'Pasif', 'ai-seo-editor' ); ?>
				</span>
			</div>

			<div class="aiseo-ap-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Otomatik yayın sekmeleri', 'ai-seo-editor' ); ?>">
				<button type="button" class="aiseo-ap-tab is-active" data-ap-tab="general" role="tab" aria-selected="true"><?php esc_html_e( 'Genel', 'ai-seo-editor' ); ?></button>
				<button type="button" class="aiseo-ap-tab" data-ap-tab="seo" role="tab" aria-selected="false"><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></button>
				<button type="button" class="aiseo-ap-tab" data-ap-tab="content" role="tab" aria-selected="false"><?php esc_html_e( 'AI Icerik', 'ai-seo-editor' ); ?></button>
				<button type="button" class="aiseo-ap-tab" data-ap-tab="publish" role="tab" aria-selected="false"><?php esc_html_e( 'Yayinlama', 'ai-seo-editor' ); ?></button>
				<button type="button" class="aiseo-ap-tab" data-ap-tab="categories" role="tab" aria-selected="false"><?php esc_html_e( 'Kategori Filtreleri', 'ai-seo-editor' ); ?></button>
			</div>

			<div class="aiseo-ap-tab-panels">
				<div class="aiseo-ap-tab-panel is-active" data-ap-panel="general">
					<div class="aiseo-ap-field-grid">
						<div class="aiseo-ap-field aiseo-ap-field--toggle">
							<div class="aiseo-ap-field__copy">
								<label for="aiseo-ap-enabled"><?php esc_html_e( 'Otomatik Yayin', 'ai-seo-editor' ); ?></label>
								<p><?php esc_html_e( 'Cron tabanli publish akışını açıp kapatın.', 'ai-seo-editor' ); ?></p>
							</div>
							<label class="aiseo-toggle">
								<input type="checkbox" id="aiseo-ap-enabled" <?php checked( $ap_settings['enabled'] ); ?>>
								<span class="aiseo-toggle-slider"></span>
							</label>
						</div>

						<div class="aiseo-ap-field">
							<label for="aiseo-ap-interval"><?php esc_html_e( 'Yayin Araligi', 'ai-seo-editor' ); ?></label>
							<select id="aiseo-ap-interval" class="regular-text">
								<?php foreach ( $intervals as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( (float) $ap_settings['interval_hours'], (float) $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Planli publish ritmini seçin.', 'ai-seo-editor' ); ?></p>
						</div>

						<div class="aiseo-ap-field">
							<label for="aiseo-ap-tone"><?php esc_html_e( 'Yazi Tonu', 'ai-seo-editor' ); ?></label>
							<select id="aiseo-ap-tone" class="regular-text">
								<?php foreach ( $tones as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $ap_settings['tone'], $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'AI yazim tarzını marka tonunuza yaklaştırın.', 'ai-seo-editor' ); ?></p>
						</div>
					</div>
				</div>

				<div class="aiseo-ap-tab-panel" data-ap-panel="seo" hidden>
					<div class="aiseo-ap-field-grid">
						<div class="aiseo-ap-field">
							<label for="aiseo-ap-min-seo"><?php esc_html_e( 'Min. SEO Puani', 'ai-seo-editor' ); ?></label>
							<input type="number" id="aiseo-ap-min-seo" value="<?php echo esc_attr( $ap_settings['min_seo_score'] ); ?>" min="0" max="100" class="small-text">
							<p class="description"><?php esc_html_e( 'Bu puanin altindaki yazilar yayinlanmaz.', 'ai-seo-editor' ); ?></p>
							<div class="aiseo-ap-progress"><span style="width: <?php echo esc_attr( min( 100, max( 0, (int) $ap_settings['min_seo_score'] ) ) ); ?>%"></span></div>
						</div>

						<div class="aiseo-ap-field">
							<label for="aiseo-ap-min-read"><?php esc_html_e( 'Min. Okunabilirlik', 'ai-seo-editor' ); ?></label>
							<input type="number" id="aiseo-ap-min-read" value="<?php echo esc_attr( $ap_settings['min_readability_score'] ); ?>" min="0" max="100" class="small-text">
							<p class="description"><?php esc_html_e( 'Akici olmayan icerikler otomatik yayina girmez.', 'ai-seo-editor' ); ?></p>
							<div class="aiseo-ap-progress"><span style="width: <?php echo esc_attr( min( 100, max( 0, (int) $ap_settings['min_readability_score'] ) ) ); ?>%"></span></div>
						</div>
					</div>
				</div>

				<div class="aiseo-ap-tab-panel" data-ap-panel="content" hidden>
					<div class="aiseo-ap-field-grid">
						<div class="aiseo-ap-field">
							<label for="aiseo-ap-words"><?php esc_html_e( 'Hedef Kelime Sayisi', 'ai-seo-editor' ); ?></label>
							<input type="number" id="aiseo-ap-words" value="<?php echo esc_attr( $ap_settings['target_words'] ); ?>" min="300" max="5000" step="100" class="small-text">
							<p class="description"><?php esc_html_e( 'AI iceriginin uzunluk hedefini belirleyin.', 'ai-seo-editor' ); ?></p>
						</div>

						<div class="aiseo-ap-field">
							<label for="aiseo-ap-links"><?php esc_html_e( 'Ic Link Sayisi', 'ai-seo-editor' ); ?></label>
							<input type="number" id="aiseo-ap-links" value="<?php echo esc_attr( $ap_settings['internal_links_count'] ); ?>" min="0" max="10" class="small-text">
							<p class="description"><?php esc_html_e( 'Ayni kategoriden eklenecek ic link adedi.', 'ai-seo-editor' ); ?></p>
						</div>

						<div class="aiseo-ap-option-stack">
							<label class="aiseo-ap-check">
								<input type="checkbox" id="aiseo-ap-faq" <?php checked( $ap_settings['include_faq'] ); ?>>
								<span><?php esc_html_e( 'FAQ bolumu ekle', 'ai-seo-editor' ); ?></span>
							</label>
							<label class="aiseo-ap-check">
								<input type="checkbox" id="aiseo-ap-auto-generate" <?php checked( $ap_settings['auto_generate'] ); ?>>
								<span><?php esc_html_e( 'Bos taslaklar icin basliktan icerik olustur', 'ai-seo-editor' ); ?></span>
							</label>
							<label class="aiseo-ap-check">
								<input type="checkbox" id="aiseo-ap-optimize" <?php checked( $ap_settings['optimize_before_publish'] ); ?>>
								<span><?php esc_html_e( 'Yayinlamadan once tam optimizasyon uygula', 'ai-seo-editor' ); ?></span>
							</label>
						</div>
					</div>
				</div>

				<div class="aiseo-ap-tab-panel" data-ap-panel="publish" hidden>
					<div class="aiseo-ap-highlight">
						<div>
							<strong><?php esc_html_e( 'Publish Flow', 'ai-seo-editor' ); ?></strong>
							<p><?php esc_html_e( 'Draft seçimi, AI üretimi, tam optimizasyon, ic linkleme, analiz ve publish adımları aynı business logic ile çalışmaya devam eder.', 'ai-seo-editor' ); ?></p>
						</div>
						<ul class="aiseo-ap-flow">
							<li><?php esc_html_e( 'Draft secimi', 'ai-seo-editor' ); ?></li>
							<li><?php esc_html_e( 'AI icerik uretimi', 'ai-seo-editor' ); ?></li>
							<li><?php esc_html_e( 'SEO + readability kontrolu', 'ai-seo-editor' ); ?></li>
							<li><?php esc_html_e( 'Publish veya hata kuyrugu', 'ai-seo-editor' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="aiseo-ap-tab-panel" data-ap-panel="categories" hidden>
					<div class="aiseo-ap-category-picker">
						<div class="aiseo-ap-category-toolbar">
							<label for="aiseo-ap-category-search" class="screen-reader-text"><?php esc_html_e( 'Kategori ara', 'ai-seo-editor' ); ?></label>
							<input type="search" id="aiseo-ap-category-search" class="regular-text" placeholder="<?php esc_attr_e( 'Kategori ara...', 'ai-seo-editor' ); ?>">
							<button type="button" class="button button-secondary" id="aiseo-ap-clear-categories"><?php esc_html_e( 'Temizle', 'ai-seo-editor' ); ?></button>
						</div>
						<div class="aiseo-ap-category-summary">
							<span id="aiseo-ap-category-count"><?php echo esc_html( count( $selected_category_ids ) ); ?></span>
							<?php esc_html_e( 'kategori secili', 'ai-seo-editor' ); ?>
						</div>
						<div id="aiseo-ap-category-chips" class="aiseo-ap-category-chips"></div>
						<div id="aiseo-ap-category-options" class="aiseo-ap-category-options">
							<?php foreach ( $categories as $cat ) : ?>
								<?php $is_selected = in_array( (int) $cat->term_id, $selected_category_ids, true ); ?>
								<button
									type="button"
									class="aiseo-ap-category-option<?php echo $is_selected ? ' is-selected' : ''; ?>"
									data-term-id="<?php echo esc_attr( $cat->term_id ); ?>"
									data-term-name="<?php echo esc_attr( $cat->name ); ?>"
									data-term-count="<?php echo esc_attr( $cat->count ); ?>"
								>
									<span><?php echo esc_html( $cat->name ); ?></span>
									<small><?php echo esc_html( $cat->count ); ?></small>
								</button>
							<?php endforeach; ?>
						</div>
						<select id="aiseo-ap-categories" class="regular-text aiseo-ap-native-select" multiple>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( (int) $cat->term_id, $selected_category_ids, true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Bos birakirsaniz tum kategorilerdeki taslaklar islenir.', 'ai-seo-editor' ); ?></p>
					</div>
				</div>
			</div>

			<div class="aiseo-ap-actions">
				<button type="button" id="aiseo-ap-save" class="button button-primary">
					<?php esc_html_e( 'Ayarlari Kaydet', 'ai-seo-editor' ); ?>
				</button>
				<button type="button" id="aiseo-ap-trigger" class="button">
					<?php esc_html_e( 'Simdi Calistir', 'ai-seo-editor' ); ?>
				</button>
			</div>
		</section>

		<section class="aiseo-card aiseo-ap-panel aiseo-ap-queue">
			<div class="aiseo-ap-panel__header">
				<div>
					<h2><?php esc_html_e( 'Content Pipeline / Queue', 'ai-seo-editor' ); ?></h2>
					<p><?php esc_html_e( 'Draft sirasi, kalite sinyalleri ve row-level aksiyonlar tek tabloda.', 'ai-seo-editor' ); ?></p>
				</div>
				<div class="aiseo-ap-inline-actions">
					<button type="button" id="aiseo-ap-refresh-queue" class="button button-secondary"><?php esc_html_e( 'Yenile', 'ai-seo-editor' ); ?></button>
				</div>
			</div>

			<div id="aiseo-ap-queue-wrap" class="aiseo-ap-queue-wrap">
				<?php if ( empty( $queue ) ) : ?>
					<div class="aiseo-ap-empty-state">
						<div class="aiseo-ap-empty-state__icon"><span class="dashicons dashicons-saved"></span></div>
						<h3><?php esc_html_e( 'Kuyruk temiz gorunuyor', 'ai-seo-editor' ); ?></h3>
						<p><?php esc_html_e( 'Yeni draft olusturuldugunda burada otomatik olarak pipeline kartlari gorunecek.', 'ai-seo-editor' ); ?></p>
						<button type="button" class="button button-primary aiseo-ap-proxy-action" data-target="aiseo-ap-trigger"><?php esc_html_e( 'AI Queue Baslat', 'ai-seo-editor' ); ?></button>
					</div>
				<?php else : ?>
					<div class="aiseo-ap-table-scroller">
						<table class="aiseo-table aiseo-ap-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Baslik', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Kategori', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Tahmini Trafik', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Keyword Volume', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'AI Confidence', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Son Islem', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Durum', 'ai-seo-editor' ); ?></th>
									<th><?php esc_html_e( 'Aksiyonlar', 'ai-seo-editor' ); ?></th>
								</tr>
							</thead>
							<tbody id="aiseo-ap-queue-body">
								<?php foreach ( $queue as $index => $item ) : ?>
									<?php
									$preview          = $build_preview_payload( $item, 'queue' );
									$keyword_volume   = $preview['keywordVolume'];
									$traffic          = $preview['traffic'];
									$confidence_tone  = $preview['confidenceTone'];
									$badge_class      = $build_badge_class( (int) $item['seo_score'] );
									$read_badge_class = $build_badge_class( (int) $item['read_score'] );
									$status_label     = ! empty( $item['score_fail'] ) ? 'Basarisiz' : ( (int) $item['attempts'] > 0 ? 'SEO Optimize' : 'Bekliyor' );
									$status_tone      = ! empty( $item['score_fail'] ) ? 'bad' : ( (int) $item['attempts'] > 0 ? 'warn' : 'idle' );
									$last_action      = $preview['lastAction'] ? date_i18n( 'd.m.Y H:i', strtotime( $preview['lastAction'] ) ) : 'Henuz yok';
									?>
									<tr data-post-id="<?php echo esc_attr( $item['id'] ); ?>">
										<td>
											<div class="aiseo-ap-row-title">
												<a href="<?php echo esc_url( $item['edit_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
												<span class="aiseo-ap-row-meta"><?php echo esc_html( sprintf( __( '%d. sirada', 'ai-seo-editor' ), $index + 1 ) ); ?></span>
											</div>
										</td>
										<td><?php echo esc_html( implode( ', ', $item['categories'] ) ?: '--' ); ?></td>
										<td><span class="aiseo-ap-soft-badge aiseo-ap-soft-badge--<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $item['seo_score'] ? (string) $item['seo_score'] : '--' ); ?></span></td>
										<td><span class="aiseo-ap-soft-badge aiseo-ap-soft-badge--<?php echo esc_attr( $read_badge_class ); ?>"><?php echo esc_html( $item['read_score'] ? (string) $item['read_score'] : '--' ); ?></span></td>
										<td><?php echo esc_html( $traffic ? '~' . number_format_i18n( $traffic ) : '--' ); ?></td>
										<td><?php echo esc_html( $keyword_volume ? number_format_i18n( $keyword_volume ) : '--' ); ?></td>
										<td><span class="aiseo-ap-soft-badge aiseo-ap-soft-badge--<?php echo esc_attr( $confidence_tone ); ?>"><?php echo esc_html( $preview['confidence'] ); ?></span></td>
										<td><?php echo esc_html( $last_action ); ?></td>
										<td>
											<span class="aiseo-ap-status-badge aiseo-ap-status-badge--<?php echo esc_attr( $status_tone ); ?>"<?php echo ! empty( $item['score_fail'] ) ? ' title="' . esc_attr( $item['score_fail'] ) . '"' : ''; ?>>
												<?php echo esc_html( $status_label ); ?>
											</span>
										</td>
										<td>
											<div class="aiseo-ap-row-actions">
												<button type="button" class="aiseo-ap-icon-btn aiseo-ap-preview-btn" title="<?php esc_attr_e( 'Onizle', 'ai-seo-editor' ); ?>" data-preview="<?php echo esc_attr( wp_json_encode( $preview ) ); ?>">
													<span class="dashicons dashicons-visibility"></span>
												</button>
												<a href="<?php echo esc_url( $item['edit_url'] ); ?>" class="aiseo-ap-icon-btn" title="<?php esc_attr_e( 'Duzenle', 'ai-seo-editor' ); ?>">
													<span class="dashicons dashicons-edit"></span>
												</a>
												<button type="button" class="aiseo-ap-icon-btn aiseo-ap-regenerate-btn" data-post-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Yeniden Uret', 'ai-seo-editor' ); ?>">
													<span class="dashicons dashicons-update"></span>
												</button>
												<button type="button" class="aiseo-ap-icon-btn aiseo-ap-publish-btn<?php echo 0 === $index ? '' : ' is-disabled'; ?>" data-post-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php echo esc_attr( 0 === $index ? __( 'Hemen Yayinla', 'ai-seo-editor' ) : __( 'Sadece ilk siradaki draft manuel calistirilabilir', 'ai-seo-editor' ) ); ?>" <?php disabled( 0 !== $index ); ?>>
													<span class="dashicons dashicons-megaphone"></span>
												</button>
												<button type="button" class="aiseo-ap-icon-btn aiseo-ap-skip-btn" data-post-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Kuyruktan Cikar', 'ai-seo-editor' ); ?>">
													<span class="dashicons dashicons-dismiss"></span>
												</button>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</section>

	</div>

	<section class="aiseo-card aiseo-ap-panel aiseo-ap-analytics">
			<div class="aiseo-ap-panel__header">
				<div>
					<h2><?php esc_html_e( 'Activity & Analytics', 'ai-seo-editor' ); ?></h2>
					<p><?php esc_html_e( 'Pipeline hareketleri, quality sinyalleri ve son publish akışı.', 'ai-seo-editor' ); ?></p>
				</div>
			</div>

			<div class="aiseo-ap-analytics-grid">
				<div class="aiseo-ap-side-block">
				<h3><?php esc_html_e( 'AI Activity Feed', 'ai-seo-editor' ); ?></h3>
				<div class="aiseo-ap-timeline">
					<?php foreach ( $activity_items as $activity ) : ?>
						<div class="aiseo-ap-timeline__item aiseo-ap-timeline__item--<?php echo esc_attr( $activity['tone'] ); ?>">
							<div class="aiseo-ap-timeline__icon"><span class="dashicons <?php echo esc_attr( $activity['icon'] ); ?>"></span></div>
							<div class="aiseo-ap-timeline__body">
								<strong><?php echo esc_html( $activity['title'] ); ?></strong>
								<p><?php echo esc_html( $activity['description'] ); ?></p>
								<time><?php echo esc_html( $activity['time'] ); ?></time>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="aiseo-ap-side-block">
				<div class="aiseo-ap-side-block__header">
					<h3><?php esc_html_e( 'Yayinlananlar', 'ai-seo-editor' ); ?></h3>
					<span class="aiseo-ap-soft-badge aiseo-ap-soft-badge--good"><?php echo esc_html( count( $history ) ); ?></span>
				</div>
				<?php if ( empty( $history ) ) : ?>
					<div class="aiseo-ap-empty-state aiseo-ap-empty-state--compact">
						<div class="aiseo-ap-empty-state__icon"><span class="dashicons dashicons-format-status"></span></div>
						<h3><?php esc_html_e( 'Henuz yayinlanan yazi yok', 'ai-seo-editor' ); ?></h3>
						<p><?php esc_html_e( 'Ilk otomatik publish sonrasinda performans ozetleri burada listelenecek.', 'ai-seo-editor' ); ?></p>
					</div>
				<?php else : ?>
					<div class="aiseo-ap-history-list">
						<?php foreach ( $history as $item ) : ?>
							<?php $preview = $build_preview_payload( $item, 'history' ); ?>
							<article class="aiseo-ap-history-item">
								<div class="aiseo-ap-history-item__top">
									<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['title'] ); ?></a>
									<button type="button" class="aiseo-ap-link-button aiseo-ap-preview-btn" data-preview="<?php echo esc_attr( wp_json_encode( $preview ) ); ?>"><?php esc_html_e( 'Onizle', 'ai-seo-editor' ); ?></button>
								</div>
								<div class="aiseo-ap-history-item__meta">
									<span><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item['published_at'] ) ) ); ?></span>
									<span class="aiseo-ap-soft-badge aiseo-ap-soft-badge--<?php echo esc_attr( $build_badge_class( (int) $item['seo_score'] ) ); ?>">SEO <?php echo esc_html( $item['seo_score'] ?: '--' ); ?></span>
									<span class="aiseo-ap-soft-badge aiseo-ap-soft-badge--<?php echo esc_attr( $build_badge_class( (int) $item['read_score'] ) ); ?>">Read <?php echo esc_html( $item['read_score'] ?: '--' ); ?></span>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<div class="aiseo-ap-floating-bar">
		<button type="button" class="button button-primary aiseo-ap-proxy-action" data-target="aiseo-ap-trigger"><?php esc_html_e( 'Simdi Calistir', 'ai-seo-editor' ); ?></button>
		<button type="button" class="button button-secondary aiseo-ap-proxy-action" data-target="aiseo-ap-refresh-queue"><?php esc_html_e( 'AI Queue Baslat', 'ai-seo-editor' ); ?></button>
		<button type="button" class="button aiseo-ap-proxy-action" data-target="aiseo-ap-save"><?php esc_html_e( 'Toplu Optimize', 'ai-seo-editor' ); ?></button>
	</div>

	<div id="aiseo-ap-preview-drawer" class="aiseo-ap-drawer" hidden aria-hidden="true">
		<div class="aiseo-ap-drawer__backdrop" data-ap-drawer-close></div>
		<div class="aiseo-ap-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="aiseo-ap-drawer-title">
			<div class="aiseo-ap-drawer__header">
				<div>
					<div class="aiseo-ap-eyebrow"><?php esc_html_e( 'Icerik Onizleme', 'ai-seo-editor' ); ?></div>
					<h2 id="aiseo-ap-drawer-title"><?php esc_html_e( 'Taslak onizlemesi', 'ai-seo-editor' ); ?></h2>
				</div>
				<button type="button" class="aiseo-ap-icon-btn" data-ap-drawer-close title="<?php esc_attr_e( 'Kapat', 'ai-seo-editor' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<div class="aiseo-ap-drawer__body">
				<div class="aiseo-ap-drawer__metrics">
					<div><span><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></span><strong id="aiseo-ap-drawer-seo">--</strong></div>
					<div><span><?php esc_html_e( 'Readability', 'ai-seo-editor' ); ?></span><strong id="aiseo-ap-drawer-read">--</strong></div>
					<div><span><?php esc_html_e( 'Traffic', 'ai-seo-editor' ); ?></span><strong id="aiseo-ap-drawer-traffic">--</strong></div>
					<div><span><?php esc_html_e( 'AI', 'ai-seo-editor' ); ?></span><strong id="aiseo-ap-drawer-confidence">--</strong></div>
				</div>
				<div class="aiseo-ap-drawer__section">
					<h3><?php esc_html_e( 'Icerik Onizleme', 'ai-seo-editor' ); ?></h3>
					<p id="aiseo-ap-drawer-excerpt"></p>
				</div>
				<div class="aiseo-ap-drawer__section">
					<h3><?php esc_html_e( 'Meta Description', 'ai-seo-editor' ); ?></h3>
					<p id="aiseo-ap-drawer-meta"></p>
				</div>
				<div class="aiseo-ap-drawer__split">
					<div class="aiseo-ap-drawer__section">
						<h3><?php esc_html_e( 'FAQ Preview', 'ai-seo-editor' ); ?></h3>
						<p id="aiseo-ap-drawer-faq"></p>
					</div>
					<div class="aiseo-ap-drawer__section">
						<h3><?php esc_html_e( 'Internal Links Preview', 'ai-seo-editor' ); ?></h3>
						<ul id="aiseo-ap-drawer-links" class="aiseo-list"></ul>
					</div>
				</div>
			</div>
			<div class="aiseo-ap-drawer__footer">
				<a href="#" id="aiseo-ap-drawer-edit" class="button button-primary"><?php esc_html_e( 'Duzenlemeye Git', 'ai-seo-editor' ); ?></a>
			</div>
		</div>
	</div>
</div>
