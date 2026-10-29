<?php
/**
 * Inner pages (v1.15.0): every themed page without [djdigi_hero]. The title
 * banner under the header, its two per page fields ("Banner title", "Hide
 * banner"), baking a page's featured image to the hero halftone look, and
 * stripping a leading emoji from content headings. The header, the docked
 * mirror ball and the night styles for these pages live in
 * djdigi-theme.php, site.css and mirrorball.js. See CLAUDE.md, "v1.15.0".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True for a themed page that is not a hero page (singular or not: archives, search and 404 count too). */
function djdt_is_inner_page() {
	return djdt_is_active() && ! djdt_is_hero_page();
}

/** True when this inner page shows the banner: everything except a page or post with "Hide banner" checked. */
function djdt_banner_shown() {
	if ( ! djdt_is_inner_page() ) {
		return false;
	}
	return ! ( is_singular() && get_post_meta( get_queried_object_id(), '_djdt_banner_hide', true ) );
}

/** The banner's title text: the "Banner title" field, else the page title (or the archive, search or 404 equivalent). */
function djdt_banner_title() {
	if ( is_singular() ) {
		$id       = get_queried_object_id();
		$override = trim( (string) get_post_meta( $id, '_djdt_banner_title', true ) );
		return '' !== $override ? $override : wp_strip_all_tags( get_the_title( $id ) );
	}
	if ( is_home() ) {
		$blog = (int) get_option( 'page_for_posts' );
		return $blog ? wp_strip_all_tags( get_the_title( $blog ) ) : 'Blog';
	}
	if ( is_search() ) {
		return 'Search results';
	}
	if ( is_404() ) {
		return 'Page not found';
	}
	return wp_strip_all_tags( get_the_archive_title() );
}

/**
 * The banner photo, first match wins: the page's featured image, the Theme
 * Settings "Default banner photo", the first hero photo. The first two are
 * served baked (djdt_banner_baked_url()); a photo not baked yet is skipped
 * for this page load while it bakes in the background. Cached per request,
 * since both the banner and the mirror ball's reflection read it.
 */
function djdt_banner_photo_url() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}
	$ids = array();
	if ( is_singular() ) {
		$ids[] = (int) get_post_thumbnail_id( get_queried_object_id() );
	} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
		$ids[] = (int) get_post_thumbnail_id( (int) get_option( 'page_for_posts' ) );
	}
	$ids[] = (int) djdt_get( 'banner_photo' );
	foreach ( $ids as $id ) {
		$baked = $id > 0 ? djdt_banner_baked_url( $id ) : '';
		if ( '' !== $baked ) {
			$url = $baked;
			return $url;
		}
	}
	$photos = djdt_hero_photos();
	$url    = $photos[0]['src'];
	return $url;
}

/**
 * The baked halftone URL for an attachment, only if it is already baked
 * with the current recipe. Baking a 2000px photo pixel by pixel takes
 * seconds, too slow for a visitor's page load, so a missing bake is queued
 * as a one off cron job instead (djdt_bake_banner below) and '' returned.
 * A photo whose last bake failed is retried at most once an hour.
 */
function djdt_banner_baked_url( $id ) {
	$id = (int) $id;
	if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
		return '';
	}
	$upload_dir = wp_upload_dir();
	$rel        = get_post_meta( $id, '_djdt_halftone_file', true );
	if ( $rel && (int) get_post_meta( $id, '_djdt_bake_ver', true ) === DJDT_HALFTONE_RECIPE && file_exists( trailingslashit( $upload_dir['basedir'] ) . $rel ) ) {
		return trailingslashit( $upload_dir['baseurl'] ) . $rel;
	}
	djdt_queue_banner_bake( $id );
	return '';
}

function djdt_queue_banner_bake( $id ) {
	$error = get_post_meta( $id, '_djdt_bake_error', true );
	if ( is_array( $error ) && ! empty( $error['time'] ) && time() - (int) $error['time'] < HOUR_IN_SECONDS ) {
		return;
	}
	if ( ! wp_next_scheduled( 'djdt_bake_banner', array( (int) $id ) ) ) {
		wp_schedule_single_event( time(), 'djdt_bake_banner', array( (int) $id ) );
	}
}

add_action( 'djdt_bake_banner', 'djdt_ensure_halftone' );

/** A page or post saved with a featured image queues its bake right away, so the banner is usually ready before anyone views it. */
add_action( 'save_post', function ( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$thumb = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb > 0 ) {
		djdt_banner_baked_url( $thumb );
	}
}, 20 );

/** The banner markup, '' when this page shows none. Inserted right before Avada's <main id="main">, see djdigi-theme.php. */
function djdt_banner_html() {
	if ( ! djdt_banner_shown() ) {
		return '';
	}
	$title = djdt_banner_title();
	if ( '' === trim( $title ) ) {
		return '';
	}
	$photo = djdt_banner_photo_url();
	$out   = '<div class="djd-banner">';
	if ( $photo ) {
		$out .= '<img class="djd-banner__photo skip-lazy" src="' . esc_url( $photo ) . '" alt="" data-no-lazy="1" fetchpriority="high" decoding="async">';
	}
	$out .= '<div class="djd-banner__shade" aria-hidden="true"></div>';
	$out .= '<h1 class="djd-banner__title">' . djdt_heading_html( $title ) . '</h1>';
	$out .= '</div>';
	return $out;
}

/** Puts $banner right before Avada's <main id="main">. Unchanged if that tag is missing. */
function djdt_insert_banner( $html, $banner ) {
	if ( ! is_string( $html ) || ! preg_match( '#<main\b[^>]*\bid=["\']main["\']#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	return substr( $html, 0, $m[0][1] ) . $banner . substr( $html, $m[0][1] );
}

/* -------------------------------------------------------------------------
 * The two per page fields, a small box in the editor sidebar.
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'djdt-banner', 'DigiMark banner', 'djdt_banner_meta_box', array( 'page', 'post' ), 'side' );
} );

function djdt_banner_meta_box( $post ) {
	wp_nonce_field( 'djdt_banner_save', 'djdt_banner_nonce' );
	$title = (string) get_post_meta( $post->ID, '_djdt_banner_title', true );
	$hide  = (bool) get_post_meta( $post->ID, '_djdt_banner_hide', true );
	?>
	<p>
		<label for="djdt-banner-title"><strong>Banner title</strong></label><br>
		<input type="text" id="djdt-banner-title" name="djdt_banner_title" value="<?php echo esc_attr( $title ); ?>" class="widefat" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>">
	</p>
	<p class="description">Leave empty to use the page title. The last word shows in cyan ("DJ DigiMark" counts as one word). Use | to choose where the cyan starts, e.g. About | DJ DigiMark</p>
	<p>
		<label><input type="checkbox" name="djdt_banner_hide" value="1" <?php checked( $hide ); ?>> Hide banner</label>
	</p>
	<p class="description">The banner photo is the featured image, or the default banner photo in DigiMark &gt; Theme Settings.</p>
	<?php
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['djdt_banner_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['djdt_banner_nonce'] ) ), 'djdt_banner_save' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$title = isset( $_POST['djdt_banner_title'] ) ? sanitize_text_field( wp_unslash( $_POST['djdt_banner_title'] ) ) : '';
	if ( '' !== $title ) {
		update_post_meta( $post_id, '_djdt_banner_title', $title );
	} else {
		delete_post_meta( $post_id, '_djdt_banner_title' );
	}
	if ( ! empty( $_POST['djdt_banner_hide'] ) ) {
		update_post_meta( $post_id, '_djdt_banner_hide', 1 );
	} else {
		delete_post_meta( $post_id, '_djdt_banner_hide' );
	}
} );

/* -------------------------------------------------------------------------
 * Leading emoji in content headings
 * ---------------------------------------------------------------------- */

/**
 * Removes a leading emoji (and the space after it) from every h1 to h6 in
 * the content, on output only; the saved content is never touched. Formatting
 * tags right after the heading's opening tag (strong, span, a, or the <p>
 * Avada's title element wraps its text in) are skipped over first. The run
 * of non-ASCII characters and numeric entities at the start of the text is
 * decoded, the emoji are stripped from its start, and the rest goes back
 * escaped; a heading that does not start with an emoji is left as is.
 * Runs at priority 20, after do_shortcode (11), so Avada Builder elements
 * are already expanded.
 */
function djdt_strip_heading_emoji( $content ) {
	if ( ! djdt_is_active() || ! is_string( $content ) || false === stripos( $content, '<h' ) ) {
		return $content;
	}
	return preg_replace_callback(
		'#(<h[1-6]\b[^>]*>(?:\s|&nbsp;|<(?:strong|b|em|i|span|a|p|mark)\b[^>]*>)*)((?:[^\x{0}-\x{7F}]|&\#x?[0-9a-f]+;|&nbsp;|\s)+)#iu',
		function ( $m ) {
			$run  = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$base = '\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{203C}\x{2049}\x{3030}\x{303D}\x{3297}\x{3299}';
			$mod  = '(?:[\x{FE0E}\x{FE0F}\x{20E3}\x{1F3FB}-\x{1F3FF}\x{E0020}-\x{E007F}])*';
			$one  = '[' . $base . ']' . $mod . '(?:\x{200D}[' . $base . ']' . $mod . ')*';
			$rest = preg_replace( '/^(?:' . $one . '[\s\x{A0}]*)+/u', '', $run, 1, $count );
			if ( ! $count || null === $rest ) {
				return $m[0];
			}
			return $m[1] . esc_html( $rest );
		},
		$content
	);
}
add_filter( 'the_content', 'djdt_strip_heading_emoji', 20 );
