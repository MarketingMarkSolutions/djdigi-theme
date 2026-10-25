<?php
/**
 * The "night" DJ DigiMark mark used by the mirror ball hero (v1.11.0): the
 * same source PNG the site has always used for its logo
 * (/wp-content/uploads/2014/08/VideoLogoBlueTr.fw_.png, 500x593, transparent),
 * baked once into a copy with its own mirror ball erased (the spinning
 * canvas ball takes its place) and every white pixel removed so it reads
 * correctly against the night background, rather than a flat white box.
 * Generated with GD and cached in uploads, the same pattern as the hero
 * photo halftone bakes in djdigi-theme.php (a unique, hashed filename per
 * recipe so nothing stale can ever be served under an old URL).
 *
 * Mark can also override this entirely from Theme Settings with his own
 * PNG or SVG (ball already removed in that file), in which case this bake
 * is never used; either way, the ball's own center/radius (as a percent of
 * the logo's own rendered width, read by mirrorball.js every frame) are
 * separate settings fields, since they describe where the spinning ball
 * sits on whichever logo image is actually in use, not how the default
 * source file itself gets erased.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bump this whenever djdt_logo_bake_night() changes in a way that means an
// already baked file needs regenerating; baked into the filename itself
// (djdt_logo_filename()), so a bump also forces a fresh URL, nothing for a
// cache to ever serve stale.
define( 'DJDT_LOGO_RECIPE', 1 );

/** The known source file every site install already has; not configurable, this is the logo. */
function djdt_logo_source_path() {
	return trailingslashit( WP_CONTENT_DIR ) . 'uploads/2014/08/VideoLogoBlueTr.fw_.png';
}

/** Unique filename for this bake: the recipe plus a short hash of the source file's own mtime and size. */
function djdt_logo_filename( $src_path ) {
	$sig = DJDT_LOGO_RECIPE . '|' . (string) @filemtime( $src_path ) . '|' . (string) @filesize( $src_path );
	return 'djdt-logo-night-v' . DJDT_LOGO_RECIPE . '-' . substr( md5( $sig ), 0, 8 ) . '.png';
}

/**
 * Returns the logo URL to actually use: Mark's own override from Theme
 * Settings if one is picked, otherwise the baked night copy of the default
 * source file (generating it first if it does not exist yet, so a fresh
 * install or a recipe bump self heals on the next page load that needs it,
 * same contract as djdt_halftone_url()). '' if neither is possible.
 */
function djdt_logo_url() {
	$override_id = (int) djdt_get( 'logo_override' );
	if ( $override_id > 0 && wp_attachment_is_image( $override_id ) ) {
		$mime = get_post_mime_type( $override_id );
		$url  = ( 'image/svg+xml' === $mime )
			? wp_get_attachment_url( $override_id )
			: wp_get_attachment_image_url( $override_id, 'full' );
		if ( $url ) {
			return $url;
		}
	}

	$upload_dir = wp_upload_dir();
	$cached_rel = get_option( 'djdt_logo_baked_file', '' );
	if ( $cached_rel && file_exists( trailingslashit( $upload_dir['basedir'] ) . $cached_rel ) ) {
		return trailingslashit( $upload_dir['baseurl'] ) . $cached_rel;
	}
	return djdt_logo_ensure_baked();
}

/** Bakes (or rebakes) the night copy of the default source file. Returns its URL, or '' if not possible. */
function djdt_logo_ensure_baked() {
	$src_path = djdt_logo_source_path();
	if ( ! file_exists( $src_path ) || ! function_exists( 'imagecreatefrompng' ) ) {
		return '';
	}
	$upload_dir = wp_upload_dir();
	$rel_file   = 'djdigi-logo/' . djdt_logo_filename( $src_path );
	$abs_file   = trailingslashit( $upload_dir['basedir'] ) . $rel_file;
	$old_rel    = get_option( 'djdt_logo_baked_file', '' );

	if ( ! djdt_logo_bake_night( $src_path, $abs_file ) ) {
		return '';
	}
	if ( $old_rel && $old_rel !== $rel_file ) {
		@unlink( trailingslashit( $upload_dir['basedir'] ) . $old_rel );
	}
	update_option( 'djdt_logo_baked_file', $rel_file );
	return trailingslashit( $upload_dir['baseurl'] ) . $rel_file;
}

/**
 * The actual pixel work: a direct PHP/GD port of the reference demo's
 * buildLogo(m='night') (reference/mirrorball-hero-reference.html). Two
 * passes in one loop, same as the original:
 *
 * a) Erase the mirror ball: every pixel within 52px of (430, 68), the
 *    source file's own known, fixed geometry at its native 500x593 size
 *    (not the configurable ball settings fields, which only ever describe
 *    where the spinning canvas ball sits, on whichever logo image ends up
 *    in use, see the file doc comment above), goes fully transparent.
 * b) Night recolor everywhere else: the source file encodes its ink by
 *    blending it against an assumed white backing (so a letterform's own
 *    anti-aliased edge reads as part white, part ink, with full alpha
 *    throughout, not as partial transparency), so a straight copy would
 *    show solid white boxes wherever that backing actually mattered. This
 *    unpremultiplies each pixel against that assumption instead: `a` here
 *    is not real transparency, it is "how far this pixel's own color sits
 *    from white", used to recover the true ink color and push the leftover
 *    whiteness out into real alpha, so the night background shows through
 *    exactly where the source was relying on a white backing to read
 *    correctly. A pixel that comes out of that the recipe would read as
 *    near black is pushed to paper (#f1ece2) instead, matching the cyan
 *    lettering and outline, which keep their own color untouched.
 *
 * GD's own alpha channel is 7 bit (0 opaque to 127 transparent), the
 * opposite convention from the 0 to 255, 255-opaque alpha the reference's
 * own canvas ImageData works in; converted at the read and write end of
 * this loop, nowhere in between, so the per-pixel math itself matches the
 * reference exactly.
 */
function djdt_logo_bake_night( $src_path, $dest_abs ) {
	$src = @imagecreatefrompng( $src_path );
	if ( ! $src ) {
		return false;
	}
	imagealphablending( $src, false );
	imagesavealpha( $src, true );

	$w = imagesx( $src );
	$h = imagesy( $src );
	if ( $w <= 0 || $h <= 0 ) {
		imagedestroy( $src );
		return false;
	}

	$out = imagecreatetruecolor( $w, $h );
	imagealphablending( $out, false );
	imagesavealpha( $out, true );

	$cx   = 430.0;
	$cy   = 68.0;
	$rad2 = 52.0 * 52.0;

	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$rgba = imagecolorat( $src, $x, $y );
			$a_gd = ( $rgba >> 24 ) & 0x7F;
			$r    = ( $rgba >> 16 ) & 0xFF;
			$g    = ( $rgba >> 8 ) & 0xFF;
			$b    = $rgba & 0xFF;

			$ex = $x - $cx;
			$ey = $y - $cy;
			if ( $ex * $ex + $ey * $ey < $rad2 ) {
				imagesetpixel( $out, $x, $y, 127 << 24 );
				continue;
			}

			$alpha255 = (int) round( ( 127 - $a_gd ) / 127 * 255 );
			if ( $alpha255 <= 0 ) {
				imagesetpixel( $out, $x, $y, ( $a_gd << 24 ) | ( $r << 16 ) | ( $g << 8 ) | $b );
				continue;
			}

			$a = max( 255 - $r, 255 - $g, 255 - $b ) / 255.0;
			if ( $a < 0.03 ) {
				imagesetpixel( $out, $x, $y, 127 << 24 );
				continue;
			}
			$rr = ( $r - 255 * ( 1 - $a ) ) / $a;
			$gg = ( $g - 255 * ( 1 - $a ) ) / $a;
			$bb = ( $b - 255 * ( 1 - $a ) ) / $a;
			if ( max( $rr, $gg, $bb ) - min( $rr, $gg, $bb ) < 60 && ( $rr + $gg + $bb ) / 3 < 120 ) {
				$rr = 241;
				$gg = 236;
				$bb = 226;
			}
			$rr = (int) max( 0, min( 255, round( $rr ) ) );
			$gg = (int) max( 0, min( 255, round( $gg ) ) );
			$bb = (int) max( 0, min( 255, round( $bb ) ) );

			$new_alpha255 = (int) max( 0, min( 255, round( $alpha255 * $a ) ) );
			$new_a_gd     = (int) round( 127 - ( $new_alpha255 / 255 ) * 127 );
			imagesetpixel( $out, $x, $y, ( $new_a_gd << 24 ) | ( $rr << 16 ) | ( $gg << 8 ) | $bb );
		}
	}

	imagedestroy( $src );
	wp_mkdir_p( dirname( $dest_abs ) );
	$ok = imagepng( $out, $dest_abs );
	imagedestroy( $out );
	return $ok;
}
