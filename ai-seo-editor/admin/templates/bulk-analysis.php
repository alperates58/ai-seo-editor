<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Toplu Analiz', 'ai-seo-editor' ); ?>
	</h1>

	<div class="aiseo-card">
		<div class="aiseo-bulk-controls">
			<label>
				<input type="checkbox" id="aiseo-select-all">
				<?php esc_html_e( 'Tümünü Seç', 'ai-seo-editor' ); ?>
			</label>
			<button type="button" id="aiseo-bulk-start" class="button button-primary">
				<?php esc_html_e( 'Seçilenleri Analiz Et', 'ai-seo-editor' ); ?>
			</button>
			<div id="aiseo-bulk-progress-wrap" style="display:none">
				<div class="aiseo-progress-bar">
					<div class="aiseo-progress-fill" id="aiseo-bulk-progress" style="width:0%"></div>
				</div>
				<span id="aiseo-bulk-status"></span>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Toplu analiz yalnızca SEO ve okunabilirlik puanlarını hesaplar. Otomatik değişiklik yapmaz.', 'ai-seo-editor' ); ?></p>
	</div>

	<div id="aiseo-bulk-notice"></div>

	<!-- Filtreler -->
	<div class="aiseo-toolbar">
		<input type="text" id="aiseo-bulk-search" placeholder="<?php esc_attr_e( 'Yazı ara...', 'ai-seo-editor' ); ?>" class="aiseo-search-input">
		<select id="aiseo-bulk-filter">
			<option value=""><?php esc_html_e( 'Tüm Skorlar', 'ai-seo-editor' ); ?></option>
			<option value="red"><?php esc_html_e( 'Kırmızı (&lt;60)', 'ai-seo-editor' ); ?></option>
			<option value="orange"><?php esc_html_e( 'Sarı (60-79)', 'ai-seo-editor' ); ?></option>
			<option value="green"><?php esc_html_e( 'Yeşil (80+)', 'ai-seo-editor' ); ?></option>
			<option value="none"><?php esc_html_e( 'Analiz Edilmemiş', 'ai-seo-editor' ); ?></option>
		</select>
	</div>

	<table class="aiseo-table wp-list-table widefat fixed striped" id="aiseo-bulk-table">
		<thead>
			<tr>
				<th style="width:30px"><input type="checkbox" id="aiseo-select-all-header"></th>
				<th><?php esc_html_e( 'Başlık', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Tarih', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'SEO Skoru', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Okunabilirlik', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Son Analiz', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'İşlem', 'ai-seo-editor' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $posts as $post ) :
			$seo_score  = (int) get_post_meta( $post->ID, '_aiseo_seo_score', true );
			$read_score = (int) get_post_meta( $post->ID, '_aiseo_readability_score', true );
			$last_anal  = get_post_meta( $post->ID, '_aiseo_last_analysis', true );
			$color      = $seo_score > 0 ? aiseo_get_score_color( $seo_score ) : 'none';
		?>
			<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-score-color="<?php echo esc_attr( $color ); ?>" data-title="<?php echo esc_attr( strtolower( $post->post_title ) ); ?>">
				<td><input type="checkbox" class="aiseo-post-select" value="<?php echo esc_attr( $post->ID ); ?>"></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts&action=detail&post_id=' . $post->ID ) ); ?>">
						<?php echo esc_html( $post->post_title ); ?>
					</a>
				</td>
				<td><?php echo esc_html( get_the_date( 'd.m.Y', $post ) ); ?></td>
				<td class="aiseo-score-cell" id="seo-score-<?php echo esc_attr( $post->ID ); ?>">
					<?php if ( $seo_score > 0 ) : ?>
						<?php echo wp_kses_post( aiseo_score_badge( $seo_score ) ); ?>
					<?php else : ?>
						<span class="aiseo-badge aiseo-badge--none">—</span>
					<?php endif; ?>
				</td>
				<td class="aiseo-score-cell" id="read-score-<?php echo esc_attr( $post->ID ); ?>">
					<?php if ( $read_score > 0 ) : ?>
						<?php echo wp_kses_post( aiseo_score_badge( $read_score ) ); ?>
					<?php else : ?>
						<span class="aiseo-badge aiseo-badge--none">—</span>
					<?php endif; ?>
				</td>
				<td>
					<?php echo $last_anal ? esc_html( human_time_diff( strtotime( $last_anal ) ) . ' önce' ) : '—'; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiseo-posts&action=detail&post_id=' . $post->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Detay', 'ai-seo-editor' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
