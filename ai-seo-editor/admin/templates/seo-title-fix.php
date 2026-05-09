<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $posts */
/** @var int $total */
/** @var int $paged */
/** @var int $per_page */
/** @var string $search */
/** @var string $filter */
$total_pages = (int) ceil( $total / $per_page );
$base_url    = admin_url( 'admin.php?page=aiseo-seo-title-fix' );
?>
<div class="wrap aiseo-wrap">
	<h1 class="aiseo-page-title">
		<span class="dashicons dashicons-tag"></span>
		<?php esc_html_e( 'SEO Başlık Düzelt', 'ai-seo-editor' ); ?>
	</h1>

	<div class="aiseo-card">
		<div class="aiseo-bulk-controls" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
			<form method="get" style="display:flex;gap:8px;align-items:center;flex:1;">
				<input type="hidden" name="page" value="aiseo-seo-title-fix">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Başlık ara...', 'ai-seo-editor' ); ?>" class="regular-text">
				<select name="filter">
					<option value="" <?php selected( $filter, '' ); ?>><?php esc_html_e( 'Tüm yazılar', 'ai-seo-editor' ); ?></option>
					<option value="missing" <?php selected( $filter, 'missing' ); ?>><?php esc_html_e( 'SEO başlığı eksik / taslakla aynı', 'ai-seo-editor' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filtrele', 'ai-seo-editor' ); ?></button>
			</form>
			<label>
				<input type="checkbox" id="aiseo-stf-select-all">
				<?php esc_html_e( 'Tümünü Seç', 'ai-seo-editor' ); ?>
			</label>
			<button type="button" id="aiseo-stf-fix-selected" class="button button-primary" disabled>
				<?php esc_html_e( 'Seçilenleri Düzelt', 'ai-seo-editor' ); ?>
			</button>
			<span id="aiseo-stf-status" style="font-size:13px;color:#666;"></span>
		</div>
		<div id="aiseo-stf-progress-wrap" style="display:none;margin-top:10px;">
			<div class="aiseo-progress-bar">
				<div class="aiseo-progress-fill" id="aiseo-stf-progress" style="width:0%"></div>
			</div>
		</div>
	</div>

	<div id="aiseo-stf-notice"></div>

	<table class="aiseo-table wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:30px;"><input type="checkbox" id="aiseo-stf-select-all-header"></th>
				<th><?php esc_html_e( 'Yazı Başlığı', 'ai-seo-editor' ); ?></th>
				<th><?php esc_html_e( 'Yoast SEO Başlığı', 'ai-seo-editor' ); ?></th>
				<th style="width:120px;"><?php esc_html_e( 'İşlem', 'ai-seo-editor' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $posts ) ) : ?>
			<tr><td colspan="4" style="text-align:center;padding:20px;"><?php esc_html_e( 'Yazı bulunamadı.', 'ai-seo-editor' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $posts as $post ) :
				$seo_title = (string) get_post_meta( $post->ID, '_yoast_wpseo_title', true );
				$is_same   = empty( $seo_title ) || $seo_title === $post->post_title;
			?>
			<tr data-post-id="<?php echo esc_attr( $post->ID ); ?>" id="aiseo-stf-row-<?php echo esc_attr( $post->ID ); ?>">
				<td><input type="checkbox" class="aiseo-stf-checkbox" value="<?php echo esc_attr( $post->ID ); ?>"></td>
				<td>
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" target="_blank">
						<?php echo esc_html( $post->post_title ); ?>
					</a>
				</td>
				<td>
					<span class="aiseo-stf-seo-title" id="aiseo-stf-title-<?php echo esc_attr( $post->ID ); ?>" style="<?php echo $is_same ? 'color:#b32d2e;' : ''; ?>">
						<?php echo $is_same
							? esc_html__( '(Taslak başlıkla aynı veya boş)', 'ai-seo-editor' )
							: esc_html( $seo_title ); ?>
					</span>
				</td>
				<td>
					<button type="button"
						class="button button-small aiseo-stf-fix-btn"
						data-post-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Düzelt', 'ai-seo-editor' ); ?>
					</button>
					<span class="aiseo-stf-row-status" id="aiseo-stf-row-status-<?php echo esc_attr( $post->ID ); ?>" style="font-size:12px;margin-left:4px;"></span>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
	<div class="tablenav bottom" style="margin-top:12px;">
		<div class="tablenav-pages">
			<?php if ( $paged > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( [ 'paged' => $paged - 1, 's' => $search, 'filter' => $filter ], $base_url ) ); ?>">&laquo; <?php esc_html_e( 'Önceki', 'ai-seo-editor' ); ?></a>
			<?php endif; ?>
			<span style="margin:0 8px;line-height:30px;"><?php echo esc_html( sprintf( '%d / %d', $paged, $total_pages ) ); ?></span>
			<?php if ( $paged < $total_pages ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( [ 'paged' => $paged + 1, 's' => $search, 'filter' => $filter ], $base_url ) ); ?>"><?php esc_html_e( 'Sonraki', 'ai-seo-editor' ); ?> &raquo;</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>
</div>

<script>
(function () {
	const restUrl  = window.aiseoConfig?.restUrl || '';
	const nonce    = window.aiseoConfig?.nonce   || '';

	async function fixPost( postId ) {
		const statusEl = document.getElementById( 'aiseo-stf-row-status-' + postId );
		const titleEl  = document.getElementById( 'aiseo-stf-title-' + postId );
		const btn      = document.querySelector( '.aiseo-stf-fix-btn[data-post-id="' + postId + '"]' );

		if ( statusEl ) statusEl.textContent = '⏳';
		if ( btn ) btn.disabled = true;

		try {
			const res  = await fetch( restUrl + 'aiseo/v1/seo-title/fix/' + postId, {
				method:  'POST',
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
			} );
			const data = await res.json();

			if ( data.success && data.seo_title ) {
				if ( titleEl ) {
					titleEl.textContent = data.seo_title;
					titleEl.style.color = '#2e7d32';
				}
				if ( statusEl ) statusEl.textContent = '✓';
			} else {
				if ( statusEl ) statusEl.textContent = data.message || '—';
				if ( btn ) btn.disabled = false;
			}
		} catch ( e ) {
			if ( statusEl ) statusEl.textContent = '✗';
			if ( btn ) btn.disabled = false;
		}
	}

	// Tek düzelt butonu
	document.querySelectorAll( '.aiseo-stf-fix-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => fixPost( btn.dataset.postId ) );
	} );

	// Checkbox yönetimi
	const allCheck = document.getElementById( 'aiseo-stf-select-all' );
	const allCheckHeader = document.getElementById( 'aiseo-stf-select-all-header' );
	const fixSelectedBtn = document.getElementById( 'aiseo-stf-fix-selected' );

	function syncSelectAll() {
		const checkboxes = document.querySelectorAll( '.aiseo-stf-checkbox' );
		const checked    = document.querySelectorAll( '.aiseo-stf-checkbox:checked' );
		if ( allCheck ) allCheck.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
		if ( allCheckHeader ) allCheckHeader.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
		if ( fixSelectedBtn ) fixSelectedBtn.disabled = checked.length === 0;
	}

	document.querySelectorAll( '.aiseo-stf-checkbox' ).forEach( cb => {
		cb.addEventListener( 'change', syncSelectAll );
	} );

	[ allCheck, allCheckHeader ].forEach( el => {
		if ( ! el ) return;
		el.addEventListener( 'change', () => {
			document.querySelectorAll( '.aiseo-stf-checkbox' ).forEach( cb => {
				cb.checked = el.checked;
			} );
			syncSelectAll();
		} );
	} );

	// Toplu düzelt
	if ( fixSelectedBtn ) {
		fixSelectedBtn.addEventListener( 'click', async () => {
			const selected = [ ...document.querySelectorAll( '.aiseo-stf-checkbox:checked' ) ].map( cb => cb.value );
			if ( ! selected.length ) return;

			fixSelectedBtn.disabled = true;
			const progressWrap = document.getElementById( 'aiseo-stf-progress-wrap' );
			const progressBar  = document.getElementById( 'aiseo-stf-progress' );
			const statusEl     = document.getElementById( 'aiseo-stf-status' );
			if ( progressWrap ) progressWrap.style.display = 'block';

			let done = 0;
			for ( const postId of selected ) {
				if ( statusEl ) statusEl.textContent = done + ' / ' + selected.length + ' tamamlandı';
				await fixPost( postId );
				done++;
				if ( progressBar ) progressBar.style.width = Math.round( ( done / selected.length ) * 100 ) + '%';
			}

			if ( statusEl ) statusEl.textContent = selected.length + ' yazı işlendi.';
			fixSelectedBtn.disabled = false;
		} );
	}
}());
</script>
