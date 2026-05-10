<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aiseo_strip_html( string $html ): string {
	$text = wp_strip_all_tags( $html );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	return trim( $text );
}

function aiseo_count_words( string $text ): int {
	$text = aiseo_strip_html( $text );
	if ( empty( $text ) ) {
		return 0;
	}
	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $words ) ? count( $words ) : 0;
}

function aiseo_keyword_in_text( string $text, string $keyword ): bool {
	if ( empty( $keyword ) ) {
		return false;
	}
	return mb_stripos( $text, $keyword ) !== false;
}

function aiseo_keyword_density( string $text, string $keyword ): float {
	if ( empty( $keyword ) || empty( $text ) ) {
		return 0.0;
	}
	$word_count = aiseo_count_words( $text );
	if ( $word_count === 0 ) {
		return 0.0;
	}
	$keyword_words = aiseo_count_words( $keyword );
	$pattern       = '/' . preg_quote( $keyword, '/' ) . '/iu';
	$occurrences   = preg_match_all( $pattern, $text );
	if ( $occurrences === false || $occurrences === 0 ) {
		return 0.0;
	}
	return round( ( $occurrences * $keyword_words / $word_count ) * 100, 2 );
}

function aiseo_get_score_color( int $score ): string {
	if ( $score >= 80 ) {
		return 'green';
	}
	if ( $score >= 60 ) {
		return 'orange';
	}
	return 'red';
}

function aiseo_get_score_label( int $score ): string {
	if ( $score >= 80 ) {
		return __( 'Iyi', 'ai-seo-editor' );
	}
	if ( $score >= 60 ) {
		return __( 'Gelistirilebilir', 'ai-seo-editor' );
	}
	return __( 'Zayif', 'ai-seo-editor' );
}

function aiseo_score_badge( int $score ): string {
	$color = aiseo_get_score_color( $score );
	$label = aiseo_get_score_label( $score );
	return sprintf(
		'<span class="aiseo-badge aiseo-badge--%s">%d - %s</span>',
		esc_attr( $color ),
		$score,
		esc_html( $label )
	);
}

function aiseo_admin_tone_class( string $tone ): string {
	$map = [
		'success' => 'success',
		'good'    => 'success',
		'green'   => 'success',
		'warning' => 'warning',
		'warn'    => 'warning',
		'orange'  => 'warning',
		'danger'  => 'danger',
		'error'   => 'danger',
		'bad'     => 'danger',
		'red'     => 'danger',
		'info'    => 'info',
		'blue'    => 'info',
		'muted'   => 'muted',
		'idle'    => 'muted',
		'none'    => 'muted',
	];

	return $map[ $tone ] ?? 'muted';
}

function aiseo_admin_status_badge( string $label, string $tone = 'muted', string $extra_class = '' ): string {
	$classes = trim( 'aiseo-ui-badge aiseo-ui-badge--' . aiseo_admin_tone_class( $tone ) . ' ' . $extra_class );
	return '<span class="' . esc_attr( $classes ) . '">' . esc_html( $label ) . '</span>';
}

function aiseo_admin_page_header( array $args = [] ): void {
	$icon    = (string) ( $args['icon'] ?? 'dashicons-admin-generic' );
	$title   = (string) ( $args['title'] ?? '' );
	$subtitle = (string) ( $args['subtitle'] ?? '' );
	$actions = is_array( $args['actions'] ?? null ) ? $args['actions'] : [];
	$badges  = is_array( $args['badges'] ?? null ) ? $args['badges'] : [];
	$eyebrow = (string) ( $args['eyebrow'] ?? '' );
	?>
	<header class="aiseo-page-header">
		<div class="aiseo-page-header__main">
			<div class="aiseo-page-header__icon">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
			</div>
			<div class="aiseo-page-header__copy">
				<?php if ( '' !== $eyebrow ) : ?>
					<div class="aiseo-page-header__eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
				<?php endif; ?>
				<h1 class="aiseo-page-header__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $subtitle ) : ?>
					<p class="aiseo-page-header__subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $badges ) ) : ?>
					<div class="aiseo-page-header__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<?php
							$label = (string) ( $badge['label'] ?? '' );
							if ( '' === $label ) {
								continue;
							}
							echo wp_kses_post( aiseo_admin_status_badge( $label, (string) ( $badge['tone'] ?? 'muted' ) ) );
							?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( ! empty( $actions ) ) : ?>
			<div class="aiseo-page-header__actions">
				<?php foreach ( $actions as $action ) : ?>
					<?php
					$tag     = ! empty( $action['href'] ) ? 'a' : 'button';
					$classes = trim( 'button ' . (string) ( $action['class'] ?? 'button-secondary' ) );
					$label   = (string) ( $action['label'] ?? '' );
					$attrs   = '';

					foreach ( (array) ( $action['attrs'] ?? [] ) as $name => $value ) {
						$attrs .= ' ' . sanitize_key( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
					}

					if ( 'a' === $tag ) :
						?>
						<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( (string) $action['href'] ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo esc_html( $label ); ?>
						</a>
					<?php else : ?>
						<button type="<?php echo esc_attr( (string) ( $action['type'] ?? 'button' ) ); ?>" class="<?php echo esc_attr( $classes ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo esc_html( $label ); ?>
						</button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</header>
	<?php
}

function aiseo_admin_stat_card( array $args = [] ): void {
	$label   = (string) ( $args['label'] ?? '' );
	$value   = (string) ( $args['value'] ?? '--' );
	$meta    = (string) ( $args['meta'] ?? '' );
	$icon    = (string) ( $args['icon'] ?? 'dashicons-chart-bar' );
	$tone    = aiseo_admin_tone_class( (string) ( $args['tone'] ?? 'info' ) );
	$href    = (string) ( $args['href'] ?? '' );
	$counter = isset( $args['counter'] ) ? (string) $args['counter'] : '';
	$attrs   = is_array( $args['attrs'] ?? null ) ? $args['attrs'] : [];
	$tag     = '' !== $href ? 'a' : 'div';
	$attr_html = '';
	foreach ( $attrs as $attr_key => $attr_value ) {
		if ( '' === (string) $attr_key ) {
			continue;
		}
		$attr_html .= sprintf( ' %s="%s"', esc_attr( (string) $attr_key ), esc_attr( (string) $attr_value ) );
	}
	?>
	<<?php echo esc_html( $tag ); ?> class="aiseo-stat-card aiseo-stat-card--<?php echo esc_attr( $tone ); ?><?php echo '' !== $href ? ' aiseo-stat-card--link' : ''; ?>"<?php echo '' !== $href ? ' href="' . esc_url( $href ) . '"' : ''; ?><?php echo $attr_html; ?>>
		<div class="aiseo-stat-card__icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></div>
		<div class="aiseo-stat-card__body">
			<div class="aiseo-stat-card__label"><?php echo esc_html( $label ); ?></div>
			<div class="aiseo-stat-card__value"<?php echo '' !== $counter ? ' data-counter-target="' . esc_attr( $counter ) . '"' : ''; ?>><?php echo esc_html( $value ); ?></div>
			<?php if ( '' !== $meta ) : ?>
				<div class="aiseo-stat-card__meta"><?php echo esc_html( $meta ); ?></div>
			<?php endif; ?>
		</div>
	</<?php echo esc_html( $tag ); ?>>
	<?php
}

function aiseo_admin_empty_state( array $args = [] ): void {
	$icon        = (string) ( $args['icon'] ?? 'dashicons-info-outline' );
	$title       = (string) ( $args['title'] ?? '' );
	$description = (string) ( $args['description'] ?? '' );
	$actions     = is_array( $args['actions'] ?? null ) ? $args['actions'] : [];
	?>
	<div class="aiseo-empty-state">
		<div class="aiseo-empty-state__icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></div>
		<?php if ( '' !== $title ) : ?>
			<h3 class="aiseo-empty-state__title"><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>
		<?php if ( '' !== $description ) : ?>
			<p class="aiseo-empty-state__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $actions ) ) : ?>
			<div class="aiseo-empty-state__actions">
				<?php foreach ( $actions as $action ) : ?>
					<?php
					$href    = (string) ( $action['href'] ?? '' );
					$label   = (string) ( $action['label'] ?? '' );
					$classes = trim( 'button ' . (string) ( $action['class'] ?? 'button-secondary' ) );
					if ( '' === $label ) {
						continue;
					}
					?>
					<?php if ( '' !== $href ) : ?>
						<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php else : ?>
						<button type="button" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

function aiseo_get_first_paragraph( string $html ): string {
	if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $matches ) ) {
		return wp_strip_all_tags( $matches[1] );
	}
	$text  = aiseo_strip_html( $html );
	$paras = preg_split( '/\n\s*\n/', $text, 2 );
	return $paras[0] ?? '';
}

function aiseo_extract_headings( string $html ): array {
	$headings = [];
	preg_match_all( '/<(h[23456])[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$headings[] = [
			'level' => strtolower( $match[1] ),
			'text'  => wp_strip_all_tags( $match[2] ),
		];
	}
	return $headings;
}

function aiseo_extract_links( string $html, string $site_url = '' ): array {
	if ( empty( $site_url ) ) {
		$site_url = home_url();
	}
	$site_host = wp_parse_url( $site_url, PHP_URL_HOST );

	$internal = [];
	$external = [];

	preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$href = $match[1];
		$text = wp_strip_all_tags( $match[2] );

		if ( strpos( $href, '#' ) === 0 || strpos( $href, 'mailto:' ) === 0 ) {
			continue;
		}

		$href_host = wp_parse_url( $href, PHP_URL_HOST );
		if ( empty( $href_host ) || $href_host === $site_host ) {
			$internal[] = [ 'href' => $href, 'text' => $text ];
		} else {
			$external[] = [ 'href' => $href, 'text' => $text ];
		}
	}

	return [ 'internal' => $internal, 'external' => $external ];
}

function aiseo_extract_images( string $html ): array {
	$images = [];
	preg_match_all( '/<img[^>]+>/i', $html, $matches );
	foreach ( $matches[0] as $img_tag ) {
		$alt = '';
		if ( preg_match( '/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match ) ) {
			$alt = $alt_match[1];
		}
		$src = '';
		if ( preg_match( '/src=["\']([^"\']+)["\']/', $img_tag, $src_match ) ) {
			$src = $src_match[1];
		}
		$images[] = [ 'src' => $src, 'alt' => $alt ];
	}
	return $images;
}

function aiseo_truncate( string $text, int $length = 160 ): string {
	if ( mb_strlen( $text ) <= $length ) {
		return $text;
	}
	return mb_substr( $text, 0, $length - 3 ) . '...';
}

function aiseo_normalize_seo_title( string $title, int $max_length = 58 ): string {
	$title = sanitize_text_field( $title );
	$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? $title );

	if ( $title === '' ) {
		return '';
	}

	if ( mb_strlen( $title ) <= $max_length ) {
		return $title;
	}

	$trimmed = trim( mb_substr( $title, 0, $max_length + 1 ) );
	$last_space = mb_strrpos( $trimmed, ' ' );

	if ( false !== $last_space && $last_space >= (int) floor( $max_length * 0.65 ) ) {
		$trimmed = trim( mb_substr( $trimmed, 0, $last_space ) );
	} else {
		$trimmed = trim( mb_substr( $trimmed, 0, $max_length ) );
	}

	return rtrim( $trimmed, " \t\n\r\0\x0B-:|,;/\\" );
}

function aiseo_normalize_meta_description( string $meta, int $max_length = 155 ): string {
	$meta = sanitize_textarea_field( $meta );
	$meta = trim( preg_replace( '/\s+/u', ' ', $meta ) ?? $meta );

	if ( $meta === '' || mb_strlen( $meta ) <= $max_length ) {
		return $meta;
	}

	$trimmed = trim( mb_substr( $meta, 0, $max_length + 1 ) );
	$sentence_positions = array_filter(
		[
			mb_strrpos( $trimmed, '.' ),
			mb_strrpos( $trimmed, '!' ),
			mb_strrpos( $trimmed, '?' ),
		],
		static fn( $position ) => false !== $position
	);
	$last_sentence = ! empty( $sentence_positions ) ? max( $sentence_positions ) : false;

	if ( false !== $last_sentence && $last_sentence >= 100 ) {
		$trimmed = trim( mb_substr( $trimmed, 0, $last_sentence + 1 ) );
	} else {
		$last_space = mb_strrpos( $trimmed, ' ' );
		if ( false !== $last_space && $last_space >= 100 ) {
			$trimmed = trim( mb_substr( $trimmed, 0, $last_space ) );
		} else {
			$trimmed = trim( mb_substr( $trimmed, 0, $max_length ) );
		}
	}

	return rtrim( $trimmed, " \t\n\r\0\x0B-:|,;/" );
}

function aiseo_preserve_bracket_blocks( string $source, string $target ): string {
	if ( ! preg_match_all( '/\[[^\[\]\r\n]{1,800}\]/u', $source, $matches ) ) {
		return $target;
	}

	$missing = [];
	foreach ( array_unique( $matches[0] ) as $block ) {
		if ( strpos( $target, $block ) === false ) {
			$missing[] = $block;
		}
	}

	if ( empty( $missing ) ) {
		return $target;
	}

	return implode( "\n\n", $missing ) . "\n\n" . $target;
}

function aiseo_get_criterion_fix_action( array $criterion ): array {
	$id = (string) ( $criterion['id'] ?? '' );

	$operation_map = [
		'keyword_in_title'            => 'optimize_title',
		'keyword_in_meta_description' => 'optimize_meta',
		'meta_description_length'     => 'optimize_meta',
		'keyword_in_first_paragraph'  => 'improve_intro',
		'keyword_density'             => 'improve_keyword_density',
		'headings_structure'          => 'improve_structure',
		'keyword_in_headings'         => 'improve_structure',
		'image_alt_text'              => 'optimize_image_alts',
		'sentence_length'             => 'improve_readability',
		'paragraph_length'            => 'improve_readability',
		'passive_voice'               => 'improve_readability',
		'transition_words'            => 'improve_readability',
		'consecutive_sentences'       => 'improve_readability',
		'subheading_distribution'     => 'improve_structure',
		'flesch_reading_ease'         => 'improve_readability',
		'text_complexity'             => 'improve_readability',
	];

	if ( isset( $operation_map[ $id ] ) ) {
		return [
			'action'    => 'operation',
			'operation' => $operation_map[ $id ],
			'label'     => __( 'Duzelt', 'ai-seo-editor' ),
		];
	}

	if ( in_array( $id, [ 'internal_links', 'content_length', 'focus_keyword_present' ], true ) ) {
		return [
			'action' => 'full',
			'label'  => __( 'Duzelt', 'ai-seo-editor' ),
		];
	}

	if ( in_array( $id, [ 'images_present', 'external_links', 'outbound_link_quality', 'keyword_in_url', 'schema_markup', 'canonical_tag', 'og_tags' ], true ) ) {
		return [
			'action' => 'unsupported',
			'label'  => __( 'Duzelt', 'ai-seo-editor' ),
			'reason' => __( 'Bu uyari icerik yerine eklenti ayari veya manuel baglanti gerektiriyor.', 'ai-seo-editor' ),
		];
	}

	return [];
}

function aiseo_render_criterion( array $criterion ): string {
	$status  = esc_attr( $criterion['status'] ?? 'error' );
	$id      = esc_attr( $criterion['id'] ?? '' );
	$label   = esc_html( $criterion['label'] ?? '' );
	$message = esc_html( $criterion['message'] ?? '' );
	$icons   = [ 'good' => 'OK', 'warning' => '!', 'error' => 'x' ];
	$icon    = $icons[ $criterion['status'] ?? 'error' ] ?? '?';
	$fix     = aiseo_get_criterion_fix_action( $criterion );
	$button  = '';

	if ( ( $criterion['status'] ?? 'error' ) !== 'good' && ! empty( $fix['label'] ) ) {
		$button = sprintf(
			'<button type="button" class="button button-secondary button-small aiseo-criterion__fix" data-criterion-id="%1$s" data-fix-action="%2$s" data-operation="%3$s" data-reason="%4$s">%5$s</button>',
			$id,
			esc_attr( $fix['action'] ?? '' ),
			esc_attr( $fix['operation'] ?? '' ),
			esc_attr( $fix['reason'] ?? '' ),
			esc_html( $fix['label'] )
		);
	}

	return sprintf(
		'<div class="aiseo-criterion aiseo-criterion--%s">
			<span class="aiseo-criterion__icon">%s</span>
			<div class="aiseo-criterion__body">
				<strong class="aiseo-criterion__label">%s</strong>
				<p class="aiseo-criterion__message">%s</p>
				%s
			</div>
		</div>',
		$status,
		esc_html( $icon ),
		$label,
		$message,
		$button
	);
}
