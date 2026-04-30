<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $result */
/** @var int $detail_post_id */

if ( empty( $result ) || isset( $result['error'] ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html( $result['error'] ?? __( 'Analiz yapılamadı.', 'ai-seo-editor' ) ) . '</p></div>';
	return;
}

$seo_color  = aiseo_get_score_color( $result['seo_score'] );
$read_color = aiseo_get_score_color( $result['readability_score'] );
$post_id    = (int) ( $result['post_id'] ?? $detail_post_id ?? 0 );
?>
<div class="aiseo-detail-wrap" id="aiseo-post-detail" data-post-id="<?php echo esc_attr( $post_id ); ?>">

	<div class="aiseo-detail-header">
		<div>
			<h2 class="aiseo-detail-title"><?php echo esc_html( $result['post_title'] ?? '' ); ?></h2>
			<p class="aiseo-detail-meta">
				<a href="<?php echo esc_url( $result['permalink'] ?? '' ); ?>" target="_blank"><?php echo esc_url( $result['permalink'] ?? '' ); ?></a>
				· <?php echo esc_html( sprintf( __( '%d kelime', 'ai-seo-editor' ), $result['word_count'] ?? 0 ) ); ?>
				· <?php echo esc_html( sprintf( __( 'KW Yoğunluğu: %%%.2f', 'ai-seo-editor' ), $result['keyword_density'] ?? 0 ) ); ?>
			</p>
		</div>
		<div class="aiseo-scores">
			<div class="aiseo-score-circle aiseo-score-circle--<?php echo esc_attr( $seo_color ); ?>" title="<?php esc_attr_e( 'SEO Puanı', 'ai-seo-editor' ); ?>">
				<span class="aiseo-score-circle__num"><?php echo esc_html( $result['seo_score'] ); ?></span>
				<span class="aiseo-score-circle__label"><?php esc_html_e( 'SEO', 'ai-seo-editor' ); ?></span>
			</div>
			<div class="aiseo-score-circle aiseo-score-circle--<?php echo esc_attr( $read_color ); ?>" title="<?php esc_attr_e( 'Okunabilirlik Puanı', 'ai-seo-editor' ); ?>">
				<span class="aiseo-score-circle__num"><?php echo esc_html( $result['readability_score'] ); ?></span>
				<span class="aiseo-score-circle__label"><?php esc_html_e( 'Okunab.', 'ai-seo-editor' ); ?></span>
			</div>
		</div>
	</div>

	<!-- AI İyileştirme Butonları -->
	<div class="aiseo-card" style="margin-bottom:20px">
		<h3><?php esc_html_e( 'AI ile İyileştir', 'ai-seo-editor' ); ?></h3>
		<div class="aiseo-optimize-actions">
			<?php
			$operations = [
				'optimize_title'           => __( 'Başlığı İyileştir', 'ai-seo-editor' ),
				'optimize_meta'            => __( 'Meta Açıklama Üret', 'ai-seo-editor' ),
				'improve_intro'            => __( 'Giriş Paragrafı', 'ai-seo-editor' ),
				'improve_structure'        => __( 'H2/H3 Yapısını Düzelt', 'ai-seo-editor' ),
				'improve_readability'      => __( 'Okunabilirliği Artır', 'ai-seo-editor' ),
				'improve_keyword_density'  => __( 'Keyword Dağılımını Düzelt', 'ai-seo-editor' ),
				'add_faq'                  => __( 'FAQ Ekle', 'ai-seo-editor' ),
				'improve_conclusion'       => __( 'Sonuç Bölümü Ekle', 'ai-seo-editor' ),
				'add_internal_links'       => __( 'İç Link Önerisi', 'ai-seo-editor' ),
				'optimize_image_alts'      => __( 'Görsel Alt Metin Öner', 'ai-seo-editor' ),
			];
			foreach ( $operations as $op => $label ) :
			?>
			<button class="button aiseo-btn-optimize" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-operation="<?php echo esc_attr( $op ); ?>">
				<?php echo esc_html( $label ); ?>
			</button>
			<?php endforeach; ?>
		</div>
		<div id="aiseo-optimize-loading" style="display:none" class="aiseo-loading-text">
			<span class="aiseo-spinner"></span> <?php esc_html_e( 'AI ile üretiliyor, lütfen bekleyin...', 'ai-seo-editor' ); ?>
		</div>
	</div>

	<div class="aiseo-grid aiseo-grid--2">
		<!-- SEO Kriterleri -->
		<div class="aiseo-card">
			<h3 class="aiseo-card__title">
				<?php esc_html_e( 'SEO Kriterleri', 'ai-seo-editor' ); ?>
				<span class="aiseo-score-pill aiseo-score-pill--<?php echo esc_attr( $seo_color ); ?>"><?php echo esc_html( $result['seo_score'] ); ?>/100</span>
			</h3>
			<div class="aiseo-criteria-list" id="aiseo-seo-criteria">
				<?php foreach ( $result['seo_criteria'] as $criterion ) :
					echo wp_kses_post( aiseo_render_criterion( $criterion ) );
				endforeach; ?>
			</div>
		</div>

		<!-- Okunabilirlik Kriterleri -->
		<div class="aiseo-card">
			<h3 class="aiseo-card__title">
				<?php esc_html_e( 'Okunabilirlik Kriterleri', 'ai-seo-editor' ); ?>
				<span class="aiseo-score-pill aiseo-score-pill--<?php echo esc_attr( $read_color ); ?>"><?php echo esc_html( $result['readability_score'] ); ?>/100</span>
			</h3>
			<div class="aiseo-criteria-list" id="aiseo-readability-criteria">
				<?php foreach ( $result['readability_criteria'] as $criterion ) :
					echo wp_kses_post( aiseo_render_criterion( $criterion ) );
				endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Detaylı Bilgiler -->
	<div class="aiseo-grid aiseo-grid--3" style="margin-top:20px">
		<div class="aiseo-card">
			<h4><?php esc_html_e( 'Başlıklar', 'ai-seo-editor' ); ?></h4>
			<?php if ( ! empty( $result['headings'] ) ) : ?>
				<ul class="aiseo-list">
				<?php foreach ( $result['headings'] as $h ) : ?>
					<li><code><?php echo esc_html( strtoupper( $h['level'] ) ); ?></code> <?php echo esc_html( aiseo_truncate( $h['text'], 60 ) ); ?></li>
				<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="aiseo-empty"><?php esc_html_e( 'Başlık bulunamadı.', 'ai-seo-editor' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="aiseo-card">
			<h4><?php esc_html_e( 'İç Linkler', 'ai-seo-editor' ); ?> (<?php echo esc_html( count( $result['internal_links'] ) ); ?>)</h4>
			<?php if ( ! empty( $result['internal_links'] ) ) : ?>
				<ul class="aiseo-list">
				<?php foreach ( array_slice( $result['internal_links'], 0, 5 ) as $link ) : ?>
					<li><a href="<?php echo esc_url( $link['href'] ); ?>" target="_blank"><?php echo esc_html( aiseo_truncate( $link['text'] ?: $link['href'], 50 ) ); ?></a></li>
				<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="aiseo-empty"><?php esc_html_e( 'İç link yok.', 'ai-seo-editor' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="aiseo-card">
			<h4><?php esc_html_e( 'Görseller', 'ai-seo-editor' ); ?> (<?php echo esc_html( count( $result['images'] ) ); ?>)</h4>
			<?php if ( ! empty( $result['images'] ) ) : ?>
				<ul class="aiseo-list">
				<?php foreach ( array_slice( $result['images'], 0, 5 ) as $img ) : ?>
					<li>
						<?php if ( empty( $img['alt'] ) ) : ?>
							<span class="aiseo-badge aiseo-badge--red"><?php esc_html_e( 'Alt Eksik', 'ai-seo-editor' ); ?></span>
						<?php else : ?>
							<span class="aiseo-badge aiseo-badge--green">alt: <?php echo esc_html( aiseo_truncate( $img['alt'], 30 ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="aiseo-empty"><?php esc_html_e( 'Görsel yok.', 'ai-seo-editor' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Optimizasyon Modal -->
<div id="aiseo-modal-overlay" class="aiseo-modal-overlay" style="display:none">
	<div class="aiseo-modal" role="dialog" aria-modal="true">
		<div class="aiseo-modal__header">
			<h3 id="aiseo-modal-title"><?php esc_html_e( 'AI Önerisi', 'ai-seo-editor' ); ?></h3>
			<button id="aiseo-modal-close" class="aiseo-btn-close">&times;</button>
		</div>
		<div class="aiseo-modal__body">
			<div class="aiseo-diff-grid">
				<div>
					<h4><?php esc_html_e( 'Mevcut', 'ai-seo-editor' ); ?></h4>
					<div id="aiseo-modal-before" class="aiseo-diff-before"></div>
				</div>
				<div>
					<h4><?php esc_html_e( 'AI Önerisi', 'ai-seo-editor' ); ?></h4>
					<div id="aiseo-modal-after" class="aiseo-diff-after"></div>
				</div>
			</div>
			<p class="aiseo-modal__note">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Uygula butonuna bastığınızda mevcut yazının revision\'ı otomatik oluşturulur.', 'ai-seo-editor' ); ?>
			</p>
		</div>
		<div class="aiseo-modal__footer">
			<button id="aiseo-modal-apply" class="button button-primary"><?php esc_html_e( 'Değişikliği Uygula', 'ai-seo-editor' ); ?></button>
			<button id="aiseo-modal-cancel" class="button"><?php esc_html_e( 'İptal', 'ai-seo-editor' ); ?></button>
		</div>
	</div>
</div>
