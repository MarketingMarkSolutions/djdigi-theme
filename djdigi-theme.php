<?php
/**
 * Plugin Name: DJ DigiMark Theme
 * Description: Site styling, Avada menu restyle, the [djdigi_hero] homepage hero, [djdigi_player], [djdigi_events], [djdigi_videos] and [djdigi_venues] for djdigimark.com.
 * Version: 1.14.1
 * Author: DJ DigiMark
 * Update URI: https://github.com/MarketingMarkSolutions/djdigi-theme
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DJDT_VERSION', '1.14.1' );
define( 'DJDT_DIR', plugin_dir_path( __FILE__ ) );
define( 'DJDT_URL', plugin_dir_url( __FILE__ ) );
define( 'DJDT_OPTION', 'djdigi_theme_settings' );
define( 'DJDT_GITHUB_REPO', 'https://github.com/MarketingMarkSolutions/djdigi-theme/' );

/* -------------------------------------------------------------------------
 * Updates from GitHub releases (Plugin Update Checker)
 * New versions show up under Plugins like any other plugin update.
 * ---------------------------------------------------------------------- */

if ( false === strpos( DJDT_GITHUB_REPO, 'GITHUB-USERNAME' ) && file_exists( DJDT_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once DJDT_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	$djdt_updates = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( DJDT_GITHUB_REPO, __FILE__, 'djdigi-theme' );
	$djdt_updates->setBranch( 'main' );
	// Install the zip the release workflow attaches, so the folder stays named djdigi-theme.
	$djdt_updates->getVcsApi()->enableReleaseAssets( '/djdigi-theme\.zip($|[?&#])/i' );
}

/* -------------------------------------------------------------------------
 * Events module: [djdigi_events], reading Modern Events Calendar Lite's own
 * data directly. v1.8.0: moved here from djdigi-library (which has no
 * update checker, so it can only ever ship as a manual zip upload), its
 * own self contained file, see includes/djdt-events.php.
 * ---------------------------------------------------------------------- */

require_once DJDT_DIR . 'includes/djdt-events.php';

/* -------------------------------------------------------------------------
 * The mirror ball hero's own logo (baking, caching, Mark's override), its
 * own self contained file for the same reason djdt-events.php is, see
 * includes/djdt-logo.php.
 * ---------------------------------------------------------------------- */

require_once DJDT_DIR . 'includes/djdt-logo.php';

/* -------------------------------------------------------------------------
 * [djdigi_videos] (v1.14.0): the newest remix videos, rendered by
 * djdigi-library's own card and lightbox code, see includes/djdt-videos.php.
 * ---------------------------------------------------------------------- */

require_once DJDT_DIR . 'includes/djdt-videos.php';

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

function djdt_defaults() {
	return array(
		'live'           => 0,
		'mixcloud_user'  => 'djdigimark',
		'mix_mode'       => 'latest',
		'mix_url'        => '',
		'hero_photos'    => array(),
		// v1.14.1: the hero no longer has buttons for these two, but both
		// are still read: sets_url is the events page ("See all dates",
		// djdt_events_page_url()) and videos_url the video gallery ("See all
		// videos", [djdigi_videos]). Keys unchanged so saved values survive.
		'sets_url'       => '/upcoming-events/',
		'videos_url'     => '/video-gallery/',
		// Footer links beside the dock (v1.14.0); book_url is also the
		// hero's one "Book me" button since v1.14.1. The site has no separate
		// contact page; the contact form lives on About. There is no
		// newsletter page either (the "Email Updates" form is a section of
		// the old homepage with no anchor), so that one starts empty and
		// its link stays hidden until a URL is saved.
		'book_url'       => '/about/',
		'newsletter_url' => '',
		// Reused for the static genre list below the venues card (v1.3.0);
		// the key is still called "ticker" since it is the same setting,
		// just no longer a scrolling marquee. Do not rename the key, that
		// would silently drop whatever Mark already has saved.
		'ticker'         => "House\nEDM\nTop 40 Remixes\n70s · 80s · 90s · 00s Dance\nPride/Queer Pop & Dance\nLive Video DJ Sets",
		'venues'         => array(),
		'venues_heading' => "Where I've played",
		// Mirror ball hero (v1.11.0). logo_override: an attachment id for a
		// PNG or SVG Mark supplies himself, ball already removed; 0 means
		// use the bundled night logo instead (includes/djdt-logo.php,
		// assets/img/logo-night.svg since v1.12.1, a GD bake of the old
		// raster source only as a fallback if that file is ever missing).
		// The three ball_* fields are percentages of the logo's own
		// rendered width (both x and y, since the logo's aspect ratio is
		// fixed, see djdt_logo_ball_settings() below); they describe where
		// the spinning canvas ball sits on whichever logo image is
		// actually in use, read fresh every frame by mirrorball.js, not a
		// one time layout decision.
		//
		// v1.12.1: these three numbers changed (were 86, 13.6, 9.2) when
		// the hero logo switched to the vector trace, which has slightly
		// different geometry than the old raster source; any saved value
		// still sitting at the old default is migrated once, see
		// djdt_migrate_ball_defaults() below.
		'logo_override'  => 0,
		'logo_ball_x'    => 85.5,
		'logo_ball_y'    => 14.2,
		'logo_ball_r'    => 9.6,
	);
}

/**
 * One time migration (v1.12.1): the ball slot defaults changed (86/13.6/9.2
 * to 85.5/14.2/9.6, see djdt_defaults() above) when the hero logo switched
 * to a vector trace with slightly different geometry than the old raster
 * source. djdt_defaults() alone only ever fills in a value that was never
 * saved at all (wp_parse_args); a site that saved the option at any point
 * before this version has the OLD numbers sitting in the database as real,
 * explicit values, which would otherwise keep reading back forever
 * regardless of this version bump. This runs once, only touches a saved
 * value that still exactly matches the old default (Mark's own deliberate
 * custom value, if he ever set one, is left alone), and never runs again
 * once done.
 */
function djdt_migrate_ball_defaults() {
	if ( get_option( 'djdt_ball_defaults_v112', 0 ) ) {
		return;
	}
	$settings = djdt_settings();
	$old      = array( 'logo_ball_x' => 86.0, 'logo_ball_y' => 13.6, 'logo_ball_r' => 9.2 );
	$new      = array( 'logo_ball_x' => 85.5, 'logo_ball_y' => 14.2, 'logo_ball_r' => 9.6 );
	$changed  = false;
	foreach ( $old as $key => $old_value ) {
		if ( isset( $settings[ $key ] ) && abs( (float) $settings[ $key ] - $old_value ) < 0.001 ) {
			$settings[ $key ] = $new[ $key ];
			$changed          = true;
		}
	}
	if ( $changed ) {
		update_option( DJDT_OPTION, $settings );
	}
	update_option( 'djdt_ball_defaults_v112', 1 );
}
add_action( 'admin_init', 'djdt_migrate_ball_defaults' );

/** The mirror ball's slot settings, each clamped to a sane 0 to 100 range. */
function djdt_logo_ball_settings() {
	return array(
		'x' => max( 0, min( 100, (float) djdt_get( 'logo_ball_x' ) ) ),
		'y' => max( 0, min( 100, (float) djdt_get( 'logo_ball_y' ) ) ),
		'r' => max( 0.5, min( 50, (float) djdt_get( 'logo_ball_r' ) ) ),
	);
}

function djdt_settings() {
	$saved = get_option( DJDT_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), djdt_defaults() );
}

function djdt_get( $key ) {
	$s = djdt_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : null;
}

/**
 * The theme only switches on for everyone once "Live" is checked.
 * Until then it shows only to logged-in administrators.
 */
function djdt_is_active() {
	if ( is_admin() ) {
		return false;
	}
	if ( djdt_get( 'live' ) ) {
		return true;
	}
	return is_user_logged_in() && current_user_can( 'manage_options' );
}

/**
 * Top level "DigiMark" sidebar menu (v1.4.0, replacing the single page under
 * Settings): Theme Settings, Hero Photos, Venues. The first submenu shares
 * its slug with add_menu_page on purpose, that is what makes it the item
 * WordPress shows for the top level link itself rather than a 4th entry.
 */
add_action( 'admin_menu', function () {
	global $djdt_admin_hooks;
	$djdt_admin_hooks = array(
		add_menu_page( 'DigiMark Theme', 'DigiMark', 'manage_options', 'djdigi-theme', 'djdt_render_settings_theme', 'dashicons-format-audio' ),
		add_submenu_page( 'djdigi-theme', 'Theme Settings', 'Theme Settings', 'manage_options', 'djdigi-theme', 'djdt_render_settings_theme' ),
		add_submenu_page( 'djdigi-theme', 'Hero Photos', 'Hero Photos', 'manage_options', 'djdigi-theme-photos', 'djdt_render_settings_photos' ),
		add_submenu_page( 'djdigi-theme', 'Venues', 'Venues', 'manage_options', 'djdigi-theme-venues', 'djdt_render_settings_venues' ),
	);
} );

/**
 * The settings page used to live at Settings > DigiMark Theme
 * (options-general.php?page=djdigi-theme). Keep that URL working by
 * sending it straight to the new Theme Settings page under the DigiMark
 * top level menu, so an old bookmark or browser history entry still lands
 * somewhere real instead of a blank "you do not have permission" screen.
 */
add_action( 'admin_init', function () {
	global $pagenow;
	if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && 'djdigi-theme' === $_GET['page'] ) {
		wp_safe_redirect( admin_url( 'admin.php?page=djdigi-theme' ) );
		exit;
	}
} );

add_action( 'admin_init', function () {
	register_setting( 'djdigi_theme', DJDT_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'djdt_sanitize',
		'default'           => djdt_defaults(),
	) );
} );

/**
 * Only our own settings screens need the media frame and the picker script.
 *
 * v1.5.2, found live: this used to compare $hook against hard coded
 * strings guessed from the usual WordPress convention ("{parent_slug}_page_
 * {slug}", or "toplevel_page_{slug}" for the first one). That guess was
 * wrong: WordPress actually builds a submenu's hook suffix from the parent
 * menu's sanitized MENU TITLE, not its slug, so with a parent menu titled
 * "DigiMark" the real hooks were "digimark_page_djdigi-theme-photos" and
 * "digimark_page_djdigi-theme-venues", not "djdigi-theme_page_...". The
 * hard coded strings never matched, so admin.js never loaded on Hero Photos
 * or Venues: no media picker, no rendered rows, Add did nothing. Fixed by
 * reading the real hook suffixes straight from what add_menu_page() and
 * add_submenu_page() themselves returned (global $djdt_admin_hooks, set in
 * the admin_menu callback above), never guessed again.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $djdt_admin_hooks;
	if ( ! in_array( $hook, (array) $djdt_admin_hooks, true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'djdt-admin', DJDT_URL . 'assets/css/admin.css', array(), djdt_ver( 'assets/css/admin.css' ) );
	wp_enqueue_script( 'djdt-admin', DJDT_URL . 'assets/js/admin.js', array( 'jquery' ), djdt_ver( 'assets/js/admin.js' ), true );
	$logo_id = (int) djdt_get( 'logo_override' );
	wp_localize_script( 'djdt-admin', 'djdtAdmin', array(
		'photos'   => array_values( djdt_get( 'hero_photos' ) ),
		'venues'   => array_values( array_map( 'djdt_venue_admin_row', djdt_get( 'venues' ) ) ),
		'heroLogo' => array(
			'id'  => $logo_id,
			// The thumb URL is empty for an SVG (no generated sizes).
			'url' => $logo_id ? (string) ( 'image/svg+xml' === get_post_mime_type( $logo_id ) ? wp_get_attachment_url( $logo_id ) : wp_get_attachment_thumb_url( $logo_id ) ) : '',
		),
	) );
} );

/**
 * Lets an already uploaded SVG attachment be picked as a venue logo
 * (v1.4.0). WordPress blocks SVG uploads by default; this only widens what
 * the Media Library will accept, nothing about validating a specific file's
 * contents, which stays WordPress core's own job once the mime type is
 * allowed. Scoped to administrators, the only people with access to this
 * settings screen in the first place.
 */
add_filter( 'upload_mimes', function ( $mimes ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}
	return $mimes;
} );

/**
 * Splits a venue row's text into Name and Details (v1.4.0). New rows carry
 * both fields directly, saved from the Venues screen; an old row saved
 * before this version only has a single "caption" like
 * "Necto Nightclub (Ann Arbor, MI, 10 year weekly residency)", in which
 * case the text before the first "(" becomes the name and the text inside
 * the parentheses becomes the details, a one time read-time conversion
 * rather than a destructive migration: nothing is written back until the
 * row is actually saved again from the new admin UI.
 */
function djdt_venue_name_details( $row ) {
	if ( isset( $row['name'] ) || isset( $row['details'] ) ) {
		return array(
			'name'    => isset( $row['name'] ) ? (string) $row['name'] : '',
			'details' => isset( $row['details'] ) ? (string) $row['details'] : '',
		);
	}
	$caption = isset( $row['caption'] ) ? (string) $row['caption'] : '';
	if ( preg_match( '/^(.*?)\s*\(([^)]*)\)\s*$/', $caption, $m ) ) {
		return array( 'name' => trim( $m[1] ), 'details' => trim( $m[2] ) );
	}
	return array( 'name' => trim( $caption ), 'details' => '' );
}

/** A venue row shaped for admin.js: id, name, details, link, on, logo_color, each always present. */
function djdt_venue_admin_row( $row ) {
	$nd  = djdt_venue_name_details( $row );
	$id  = isset( $row['id'] ) ? (int) $row['id'] : 0;
	return array(
		'id'         => $id,
		'name'       => $nd['name'],
		'details'    => $nd['details'],
		'link'       => isset( $row['link'] ) ? (string) $row['link'] : '',
		'on'         => isset( $row['on'] ) ? (int) $row['on'] : 1,
		'logo_color' => isset( $row['logo_color'] ) ? ( 'original' === $row['logo_color'] ? 'original' : 'white' ) : djdt_logo_default_color( $id ),
	);
}

/**
 * Picks a sensible default logo color for a venue (v1.4.1, found live: a
 * JPG with a solid background, Necto, was defaulting to White and coming
 * out a gray box, since brightness(0) invert(0.92) crushes a JPG's own
 * opaque background the same as any other pixel, there is no transparency
 * for it to read through). White only makes sense when the file actually
 * has something transparent for the recolor to apply through: an SVG
 * (vector, counted as transparent by convention here) or a PNG/WebP that
 * genuinely carries alpha. Anything else, a JPG above all, defaults to
 * Original. Mark can still override per venue from the dropdown.
 */
function djdt_logo_default_color( $id ) {
	$id = (int) $id;
	if ( $id <= 0 ) {
		return 'white';
	}
	$mime = get_post_mime_type( $id );
	if ( 'image/svg+xml' === $mime ) {
		return 'white';
	}
	if ( ! in_array( $mime, array( 'image/png', 'image/webp' ), true ) ) {
		return 'original';
	}
	return djdt_image_has_alpha( $id ) ? 'white' : 'original';
}

/**
 * A PNG or WebP can carry an alpha channel while still being fully
 * opaque; this samples a grid of pixels (not every one, a logo is small
 * and this only ever needs to run once per venue) looking for any pixel
 * that is actually, visibly transparent. GD specifically, not Imagick:
 * simpler to get exactly right here and virtually always available
 * regardless of which library ends up baking hero photos.
 */
function djdt_image_has_alpha( $id ) {
	$path = get_attached_file( $id );
	$mime = get_post_mime_type( $id );
	if ( ! $path || ! file_exists( $path ) || ! function_exists( 'imagecreatefrompng' ) ) {
		return false;
	}
	$im = ( 'image/webp' === $mime )
		? ( function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false )
		: @imagecreatefrompng( $path );
	if ( ! $im ) {
		return false;
	}
	$w     = imagesx( $im );
	$h     = imagesy( $im );
	$step  = max( 1, (int) floor( min( $w, $h ) / 40 ) );
	$found = false;
	for ( $y = 0; $y < $h && ! $found; $y += $step ) {
		for ( $x = 0; $x < $w; $x += $step ) {
			$alpha = ( imagecolorat( $im, $x, $y ) >> 24 ) & 0x7F;
			if ( $alpha > 3 ) { // a little slack for near opaque antialiasing
				$found = true;
				break;
			}
		}
	}
	imagedestroy( $im );
	return $found;
}

/**
 * Recomputes logo_color for every existing venue once per
 * DJDT_LOGO_COLOR_RECIPE bump (v1.4.1), the same reasoning as the hero
 * photo bake regeneration below: a venue saved before this version always
 * got White by default regardless of the file, which is what made a JPG
 * logo like Necto's render as a gray box. This overwrites whatever is
 * currently saved for every venue with a logo, once; a value Mark sets
 * afterward from the Venues screen is untouched by this (it only runs
 * again if DJDT_LOGO_COLOR_RECIPE is bumped again).
 */
define( 'DJDT_LOGO_COLOR_RECIPE', 1 );
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( (int) get_option( 'djdt_logo_color_done', 0 ) >= DJDT_LOGO_COLOR_RECIPE ) {
		return;
	}
	$settings = djdt_settings();
	$venues   = is_array( $settings['venues'] ) ? $settings['venues'] : array();
	foreach ( $venues as $i => $row ) {
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		if ( $id > 0 ) {
			$venues[ $i ]['logo_color'] = djdt_logo_default_color( $id );
		}
	}
	$settings['venues'] = $venues;
	update_option( DJDT_OPTION, $settings );
	update_option( 'djdt_logo_color_done', DJDT_LOGO_COLOR_RECIPE );
} );

/**
 * Three separate screens (Theme Settings, Hero Photos, Venues, see
 * add_menu_page above, v1.4.0) all post to the same options.php and the
 * same option, so each form carries a hidden djdt_page field naming which
 * one it is. Sanitizing starts from the setting as currently saved, not
 * just the defaults, and only overwrites the slice of keys that screen
 * actually owns, so saving one screen can never blank out another's
 * fields it never had in its own form.
 */
function djdt_sanitize( $in ) {
	$d     = djdt_defaults();
	$out   = djdt_settings();
	$page  = isset( $in['djdt_page'] ) ? sanitize_key( $in['djdt_page'] ) : '';

	if ( 'theme' === $page ) {
		$out['live']          = empty( $in['live'] ) ? 0 : 1;
		$out['mixcloud_user'] = sanitize_title( isset( $in['mixcloud_user'] ) ? $in['mixcloud_user'] : $d['mixcloud_user'] );
		$out['mix_mode']      = ( isset( $in['mix_mode'] ) && 'pinned' === $in['mix_mode'] ) ? 'pinned' : 'latest';
		$out['mix_url']       = esc_url_raw( isset( $in['mix_url'] ) ? trim( $in['mix_url'] ) : '' );
		$out['sets_url']      = sanitize_text_field( isset( $in['sets_url'] ) ? $in['sets_url'] : $d['sets_url'] );
		$out['videos_url']    = sanitize_text_field( isset( $in['videos_url'] ) ? $in['videos_url'] : $d['videos_url'] );
		$out['book_url']      = sanitize_text_field( isset( $in['book_url'] ) ? trim( $in['book_url'] ) : $d['book_url'] );
		$out['newsletter_url'] = sanitize_text_field( isset( $in['newsletter_url'] ) ? trim( $in['newsletter_url'] ) : $d['newsletter_url'] );
		$out['ticker']        = sanitize_textarea_field( isset( $in['ticker'] ) ? $in['ticker'] : $d['ticker'] );

		// v1.14.0: wp_attachment_is_image() rejects SVGs, which dropped an
		// SVG override on save; same explicit mime check as the read side
		// in djdt_logo_url() (v1.12.1).
		$logo_id              = isset( $in['logo_override'] ) ? (int) $in['logo_override'] : 0;
		$logo_ok              = $logo_id > 0 && ( 'image/svg+xml' === get_post_mime_type( $logo_id ) || wp_attachment_is_image( $logo_id ) );
		$out['logo_override'] = $logo_ok ? $logo_id : 0;
		$out['logo_ball_x']   = isset( $in['logo_ball_x'] ) ? max( 0, min( 100, (float) $in['logo_ball_x'] ) ) : $d['logo_ball_x'];
		$out['logo_ball_y']   = isset( $in['logo_ball_y'] ) ? max( 0, min( 100, (float) $in['logo_ball_y'] ) ) : $d['logo_ball_y'];
		$out['logo_ball_r']   = isset( $in['logo_ball_r'] ) ? max( 0.5, min( 50, (float) $in['logo_ball_r'] ) ) : $d['logo_ball_r'];
	}

	if ( 'photos' === $page ) {
		// One hidden field carrying JSON built by admin.js (order, focal
		// points). Each row is re-validated here rather than trusted, same
		// as anything else arriving by POST.
		$out['hero_photos'] = array();
		$raw = isset( $in['hero_photos_json'] ) ? json_decode( wp_unslash( $in['hero_photos_json'] ), true ) : array();
		if ( is_array( $raw ) ) {
			foreach ( array_slice( $raw, 0, 20 ) as $row ) {
				$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
					continue;
				}
				$out['hero_photos'][] = array(
					'id' => $id,
					'x'  => isset( $row['x'] ) ? max( 0, min( 100, (float) $row['x'] ) ) : 50,
					'y'  => isset( $row['y'] ) ? max( 0, min( 100, (float) $row['y'] ) ) : 50,
				);
			}
		}
		// Bake each photo's halftone copy now, while it is already on hand,
		// rather than on every front end page load. A photo removed from
		// rotation keeps its baked copy (it is cheap, tied to the
		// attachment, and removing it here would just mean regenerating it
		// if the same photo comes back).
		foreach ( $out['hero_photos'] as $row ) {
			djdt_ensure_halftone( $row['id'] );
		}
	}

	if ( 'venues' === $page ) {
		$out['venues_heading'] = sanitize_text_field( isset( $in['venues_heading'] ) && '' !== trim( (string) $in['venues_heading'] ) ? $in['venues_heading'] : $d['venues_heading'] );

		// Same hidden JSON field pattern as hero photos, see admin.js. The
		// logo is optional (a row can be name/details and link only),
		// unlike a hero photo, which is why id is not required here.
		$out['venues'] = array();
		$raw = isset( $in['venues_json'] ) ? json_decode( wp_unslash( $in['venues_json'] ), true ) : array();
		if ( is_array( $raw ) ) {
			foreach ( array_slice( $raw, 0, 60 ) as $row ) {
				$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				if ( $id > 0 && ! wp_attachment_is_image( $id ) ) {
					$id = 0;
				}
				$name       = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
				$details    = isset( $row['details'] ) ? sanitize_text_field( $row['details'] ) : '';
				$link       = isset( $row['link'] ) ? esc_url_raw( trim( $row['link'] ) ) : '';
				$logo_color = ( isset( $row['logo_color'] ) && 'original' === $row['logo_color'] ) ? 'original' : 'white';
				if ( ! $id && '' === $name && '' === $details ) {
					continue; // nothing to show for this row at all
				}
				$out['venues'][] = array(
					'id'         => $id,
					'name'       => $name,
					'details'    => $details,
					'link'       => $link,
					'on'         => empty( $row['on'] ) ? 0 : 1,
					'logo_color' => $logo_color,
				);
			}
		}
	}

	delete_transient( 'djdt_mix' );
	delete_transient( 'djdt_mixes' );
	return $out;
}

/**
 * Thin wrapper shared by the three settings screens: the page chrome, a nav
 * tab row, and the save form. $page_key is the hidden djdt_page value
 * djdt_sanitize() branches on, so each screen's save only ever touches the
 * slice of settings that screen actually owns.
 */
function djdt_render_settings_page( $active_slug, $page_key, $render_fields ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = array(
		'djdigi-theme'        => 'Theme Settings',
		'djdigi-theme-photos' => 'Hero Photos',
		'djdigi-theme-venues' => 'Venues',
	);
	?>
	<div class="wrap">
		<h1>DigiMark Theme</h1>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="nav-tab<?php echo ( $slug === $active_slug ) ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>
		<form method="post" action="options.php" style="margin-top: 20px;">
			<?php settings_fields( 'djdigi_theme' ); ?>
			<input type="hidden" name="<?php echo esc_attr( DJDT_OPTION ); ?>[djdt_page]" value="<?php echo esc_attr( $page_key ); ?>">
			<table class="form-table" role="presentation">
				<?php call_user_func( $render_fields ); ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

function djdt_render_settings_theme() {
	djdt_render_settings_page( 'djdigi-theme', 'theme', function () {
		$s = djdt_settings();
		$n = DJDT_OPTION;
		?>
		<tr>
			<th scope="row">Live for everyone</th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[live]" value="1" <?php checked( $s['live'], 1 ); ?>> Show the new design to all visitors</label>
				<p class="description">Unchecked: only logged-in administrators see the new design and the hero. Visitors see the site as it is today.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-user">Mixcloud username</label></th>
			<td><input id="djdt-user" class="regular-text" name="<?php echo esc_attr( $n ); ?>[mixcloud_user]" value="<?php echo esc_attr( $s['mixcloud_user'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row">Hero player plays</th>
			<td>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mix_mode]" value="latest" <?php checked( $s['mix_mode'], 'latest' ); ?>> My newest Mixcloud upload</label><br>
				<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mix_mode]" value="pinned" <?php checked( $s['mix_mode'], 'pinned' ); ?>> This mix:</label>
				<input class="regular-text" name="<?php echo esc_attr( $n ); ?>[mix_url]" value="<?php echo esc_attr( $s['mix_url'] ); ?>" placeholder="https://www.mixcloud.com/djdigimark/...">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-sets">Events page link</label></th>
			<td>
				<input id="djdt-sets" class="regular-text" name="<?php echo esc_attr( $n ); ?>[sets_url]" value="<?php echo esc_attr( $s['sets_url'] ); ?>">
				<p class="description">Where "See all dates" under the homepage events goes.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-videos">Video gallery link</label></th>
			<td>
				<input id="djdt-videos" class="regular-text" name="<?php echo esc_attr( $n ); ?>[videos_url]" value="<?php echo esc_attr( $s['videos_url'] ); ?>">
				<p class="description">Where "See all videos" under the homepage remix videos goes.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-book">"Book me" link</label></th>
			<td>
				<input id="djdt-book" class="regular-text" name="<?php echo esc_attr( $n ); ?>[book_url]" value="<?php echo esc_attr( $s['book_url'] ); ?>">
				<p class="description">The solid "Book me" button in the hero and in the footer, one link for both. Leave empty to hide both.
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-newsletter">Footer "Join the newsletter" link</label></th>
			<td>
				<input id="djdt-newsletter" class="regular-text" name="<?php echo esc_attr( $n ); ?>[newsletter_url]" value="<?php echo esc_attr( $s['newsletter_url'] ); ?>">
				<p class="description">The text link beside the "Book me" button. Leave empty to hide it.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-ticker">Genres ("What I play")</label></th>
			<td>
				<textarea id="djdt-ticker" class="large-text" rows="7" name="<?php echo esc_attr( $n ); ?>[ticker]"><?php echo esc_textarea( $s['ticker'] ); ?></textarea>
				<p class="description">Shown as a static list below the venues card. One item per line. Use " · " inside a line to group things into one item (like the decades).</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Hero logo override</th>
			<td>
				<div id="djdt-hero-logo"></div>
				<p>
					<button type="button" class="button" id="djdt-hero-logo-pick">Select image</button>
					<button type="button" class="button" id="djdt-hero-logo-remove">Remove</button>
				</p>
				<input type="hidden" id="djdt-hero-logo-id" name="<?php echo esc_attr( $n ); ?>[logo_override]" value="<?php echo esc_attr( (int) $s['logo_override'] ); ?>">
				<p class="description">PNG or SVG, with the mirror ball already removed from the artwork. Leave empty to use the bundled logo (the site's own mark, ball erased, for the night hero and header).</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Mirror ball position</th>
			<td>
				<label>Center X <input type="number" step="0.1" min="0" max="100" class="small-text" name="<?php echo esc_attr( $n ); ?>[logo_ball_x]" value="<?php echo esc_attr( $s['logo_ball_x'] ); ?>">%</label>
				&nbsp; <label>Center Y <input type="number" step="0.1" min="0" max="100" class="small-text" name="<?php echo esc_attr( $n ); ?>[logo_ball_y]" value="<?php echo esc_attr( $s['logo_ball_y'] ); ?>">%</label>
				&nbsp; <label>Radius <input type="number" step="0.1" min="0.5" max="50" class="small-text" name="<?php echo esc_attr( $n ); ?>[logo_ball_r]" value="<?php echo esc_attr( $s['logo_ball_r'] ); ?>">%</label>
				<p class="description">Where the spinning mirror ball sits on the logo, each as a percent of the logo's own width. Defaults match the logo's own mirror ball: 85.5, 14.2, 9.6.</p>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<p><strong>Home v2 order</strong> (v1.14.0), each shortcode on its own line in this order:<br>
					<code>[djdigi_hero player="no"]</code><br>
					<code>[djdigi_events limit="5"]</code><br>
					<code>[djdigi_player]</code><br>
					<code>[djdigi_videos]</code><br>
					<code>[djdigi_venues]</code></p>
				<p>Hero: <code>[djdigi_hero]</code>, alone in a 100% width container with no padding, at the top of the page. Without <code>player="no"</code> it also outputs the "Latest live sets" player right after itself. Player: <code>[djdigi_player]</code>, the player plus a playlist of the 3 newest Mixcloud uploads. Remix videos: <code>[djdigi_videos]</code>, the 4 newest videos from the DJ DigiMark Library plugin (shows nothing if that plugin is inactive). Venues: <code>[djdigi_venues]</code>. The mirror ball's full entrance plays on every page load.</p>
				<p><strong>After saving:</strong> if a caching plugin or your host's cache is active, purge the cached copy of any page carrying these shortcodes, or changes here can keep showing the old version. See the plugin's CLAUDE.md for exact steps.</p>
			</td>
		</tr>
		<?php
	} );
}

function djdt_render_settings_photos() {
	djdt_render_settings_page( 'djdigi-theme-photos', 'photos', function () {
		$s = djdt_settings();
		$n = DJDT_OPTION;
		?>
		<tr>
			<th scope="row">Hero photos</th>
			<td>
				<div id="djdt-hero-photos" class="djdt-photo-grid"></div>
				<p>
					<button type="button" class="button" id="djdt-add-photos">Add photos</button>
				</p>
				<input type="hidden" id="djdt-hero-photos-json" name="<?php echo esc_attr( $n ); ?>[hero_photos_json]" value="<?php echo esc_attr( wp_json_encode( array_values( $s['hero_photos'] ) ) ); ?>">
				<p class="description">Drag a photo to reorder. Click a photo once to pick its focal point, then click where the subject is. The x removes it from rotation only, it stays in the Media Library. Leave empty to use the built in photo. One photo plays with no rotation. Saving here also bakes a tinted copy of each photo for the hero, which can take a few seconds for a lot of photos.</p>
			</td>
		</tr>
		<?php
	} );
}

function djdt_render_settings_venues() {
	djdt_render_settings_page( 'djdigi-theme-venues', 'venues', function () {
		$s = djdt_settings();
		$n = DJDT_OPTION;
		?>
		<tr>
			<th scope="row"><label for="djdt-venues-heading">Venues section heading</label></th>
			<td><input id="djdt-venues-heading" class="regular-text" name="<?php echo esc_attr( $n ); ?>[venues_heading]" value="<?php echo esc_attr( $s['venues_heading'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row">Venues</th>
			<td>
				<div id="djdt-venues" class="djdt-venue-grid"></div>
				<p>
					<button type="button" class="button" id="djdt-add-venue">Add venue</button>
				</p>
				<input type="hidden" id="djdt-venues-json" name="<?php echo esc_attr( $n ); ?>[venues_json]" value="<?php echo esc_attr( wp_json_encode( array_values( array_map( 'djdt_venue_admin_row', $s['venues'] ) ) ) ); ?>">
				<p class="description">Drag a row to reorder. Name and details (for example "Ann Arbor, MI &middot; 10 year weekly residency") are two separate lines on the card. Logo is optional, a row with no logo shows its name and details in the display font instead. Logo color: White recolors any logo to one flat off-white so a mismatched set reads as one; Original keeps the logo's own colors, dimmed slightly until hovered. SVG logos are accepted. The switch turns a venue on or off without losing anything saved for it.</p>
			</td>
		</tr>
		<?php
	} );
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

function djdt_ver( $rel ) {
	$path = DJDT_DIR . $rel;
	return file_exists( $path ) ? (string) filemtime( $path ) : DJDT_VERSION;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	wp_enqueue_style( 'djdt-site', DJDT_URL . 'assets/css/site.css', array(), djdt_ver( 'assets/css/site.css' ) );
	wp_add_inline_style( 'djdt-site', djdt_font_face_css() );

	wp_register_style( 'djdt-hero', DJDT_URL . 'assets/css/hero.css', array( 'djdt-site' ), djdt_ver( 'assets/css/hero.css' ) );
	wp_register_script( 'djdt-hero', DJDT_URL . 'assets/js/hero.js', array(), djdt_ver( 'assets/js/hero.js' ), true );
	wp_register_script( 'djdt-mirrorball', DJDT_URL . 'assets/js/mirrorball.js', array(), djdt_ver( 'assets/js/mirrorball.js' ), true );

	wp_register_style( 'djdt-venues', DJDT_URL . 'assets/css/venues.css', array( 'djdt-site' ), djdt_ver( 'assets/css/venues.css' ) );
	wp_register_script( 'djdt-venues', DJDT_URL . 'assets/js/venues.js', array(), djdt_ver( 'assets/js/venues.js' ), true );

	// Menu and footer dock run on every themed page, hero or not.
	wp_enqueue_script( 'djdt-menu', DJDT_URL . 'assets/js/menu.js', array(), djdt_ver( 'assets/js/menu.js' ), true );

	wp_enqueue_script( 'djdt-dock', DJDT_URL . 'assets/js/dock.js', array(), djdt_ver( 'assets/js/dock.js' ), true );
	wp_add_inline_script( 'djdt-dock', 'window.djdtDock = ' . wp_json_encode( array(
		'mixcloudUrl'   => 'https://www.mixcloud.com/' . rawurlencode( djdt_get( 'mixcloud_user' ) ) . '/',
		'bookUrl'       => '' !== trim( (string) djdt_get( 'book_url' ) ) ? esc_url_raw( djdt_get( 'book_url' ) ) : '',
		'newsletterUrl' => '' !== trim( (string) djdt_get( 'newsletter_url' ) ) ? esc_url_raw( djdt_get( 'newsletter_url' ) ) : '',
	) ) . ';', 'before' );
}, 20 );

function djdt_font_face_css() {
	$f = DJDT_URL . 'assets/fonts/';
	return "@font-face{font-family:'DJD Archivo';src:url('{$f}archivo-wdth.woff2') format('woff2');font-weight:100 900;font-stretch:62% 125%;font-display:swap;}"
		. "@font-face{font-family:'DJD Saira';src:url('{$f}saira-extra-condensed-700.woff2') format('woff2');font-weight:700;font-display:swap;}"
		. "@font-face{font-family:'DJD Saira';src:url('{$f}saira-extra-condensed-800.woff2') format('woff2');font-weight:800;font-display:swap;}";
}

add_action( 'wp_head', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	$f = DJDT_URL . 'assets/fonts/';
	echo '<link rel="preload" href="' . esc_url( $f . 'saira-extra-condensed-800.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	echo '<link rel="preload" href="' . esc_url( $f . 'archivo-wdth.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}, 2 );

/**
 * --djd-vw: the real viewport width in px, read from document.documentElement.
 * clientWidth (unlike 100vw, this never includes the scrollbar's own width,
 * the same bug that sat the hero 7px too far left back in v1.7.0), set on
 * html itself so every full bleed theme section (.djd-hero,
 * .djd-player-section, .djd-events, .djd-videos, .djd-venues, each in their own
 * stylesheet) can read it with width: var(--djd-vw) and break out to the
 * true window width regardless of how many Avada containers deep their own
 * direct parent happens to sit (v1.8.2, found live at 2000px wide: Home v2's
 * content now sits inside a real Avada Builder row, not the flat template
 * every earlier release assumed, see hero.css for the full history).
 * Printed as a plain inline script, synchronous, in wp_head, specifically so
 * it runs and finishes before the page body is ever parsed or painted: this
 * project does not ship a flash of the wrong width for a moment and then
 * correct it, same reasoning as the header background kill block below.
 */
add_action( 'wp_head', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	?>
	<script>
	(function () {
		function djdSetVw() {
			document.documentElement.style.setProperty( '--djd-vw', document.documentElement.clientWidth + 'px' );
		}
		djdSetVw();
		window.addEventListener( 'resize', djdSetVw );
	})();
	</script>
	<?php
}, 1 );

/**
 * v1.10.0, found live at 2000px wide: every full bleed section (.djd-hero,
 * .djd-player-section, .djd-events, .djd-venues; .djd-videos since v1.14.0) still sat about 7px off
 * the true left edge even with width: var(--djd-vw) in place. The CSS
 * breakout formula (margin-left: calc(50% - var(--djd-vw) / 2), each
 * section's own stylesheet) only lands exactly on 0 if the section's own
 * direct parent is itself perfectly centered in the viewport; any small
 * asymmetry anywhere in Avada's own container chain throws it off by
 * whatever that asymmetry is, which is what the measured 7px actually
 * was, not a fixed, guessable number. This stops guessing: for each of
 * these sections, zero its margin, read its own real distance from the
 * viewport's left edge (getBoundingClientRect, after the zero so nothing
 * upstream is still pushing it over), then set margin-left to exactly
 * cancel that out, whatever it turns out to be. Printed in wp_footer, not
 * wp_head like djdSetVw above: this needs the sections to actually exist
 * in the DOM and be laid out, which they are not yet this early. The
 * trade off is a brief correction right as the page finishes loading
 * rather than nothing to correct at all; the CSS formula above is still
 * what paints first, this only fixes whatever small residual it leaves.
 */
add_action( 'wp_footer', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	?>
	<script>
	(function () {
		function djdFixBreakout() {
			document.querySelectorAll( '.djd-hero, .djd-player-section, .djd-events, .djd-videos, .djd-venues' ).forEach( function ( el ) {
				el.style.marginLeft = '0px';
				var left = el.getBoundingClientRect().left;
				el.style.marginLeft = ( -left ) + 'px';
			} );
		}
		djdFixBreakout();
		window.addEventListener( 'resize', djdFixBreakout );
	})();
	</script>
	<?php
}, 100 );

/**
 * Avada's own global header background image (an Options panel setting)
 * comes from Avada's own generated dynamic CSS, printed in wp_head at its
 * own priority. Across three earlier releases (v1.2.1 through v1.2.3)
 * nothing in djdt-site's own stylesheet reliably beat it regardless of
 * selector or !important; v1.2.3 ended up forcing it off with JS after
 * load instead (hero.js, killBgImage), which is what caused a brief flash
 * of the old solid header band on every refresh, right before that JS ran.
 * v1.4.0 drops the JS patch for this one and instead prints this block
 * from PHP as late as possible (priority 9999), so it always lands after
 * Avada's own dynamic stylesheet in the page source: later wins a tie in
 * specificity and !important, on every single load, with no JS timing
 * involved and nothing to flash. Only the image is forced here, not
 * background color: djdt-site.css already handles the transparent versus
 * sticky distinction correctly through the normal cascade, that was never
 * the part Avada's own CSS was beating.
 */
add_action( 'wp_head', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	?>
	<style id="djdt-header-bg-kill">
	html body.djdigi-has-hero .fusion-header-wrapper,
	html body.djdigi-has-hero .fusion-header-wrapper .fusion-row,
	html body.djdigi-has-hero .fusion-header,
	html body.djdigi-has-hero .fusion-secondary-header,
	html body.djdigi-has-hero .fusion-secondary-header .fusion-row,
	html body.djdigi-has-hero .fusion-sticky-header-wrapper,
	html body.djdigi-has-hero .fusion-sticky-header-wrapper .fusion-header,
	html body.djdigi-has-hero .fusion-sticky-header-wrapper .fusion-row {
		background-image: none !important;
	}
	</style>
	<?php
}, 9999 );

/* -------------------------------------------------------------------------
 * Hero photo baking: smooth duotone base plus a light halftone overlay
 * (v1.7.2, "style A", built live on Home v2 and picked by Mark over the
 * alternatives). Generated once, when a photo is saved into Hero Photos
 * (djdt_sanitize's 'photos' branch), not on every front end page load, and
 * baked straight into a plain JPEG. The front end then serves a plain
 * photo with no CSS filter, no mix-blend-mode and no mask-image on the
 * photo itself, all three of which were forcing a repaint every frame
 * together with the Ken Burns zoom (see CLAUDE.md, "Smoothness" / why the
 * hero photo and the venue strip were choppy). Falls back to the
 * unprocessed original if baking is not possible or comes out wrong, see
 * djdt_bake_is_gray() below.
 *
 * v1.6.0 through v1.7.1's recipe painted two rotated dot screens straight
 * onto a blank paper canvas: between dots there was nothing but flat
 * paper, so the dots themselves were the only thing carrying any detail
 * at all. v1.7.2 replaces that with a continuous tone map instead: every
 * pixel gets its own color from a smooth gradient driven by that pixel's
 * own luminance (djdt_halftone_gradient_rgb() below), so a face, headphones
 * and decks stay fully legible on their own; the two halftone screens
 * (djdt_halftone_screen_distance() below) then blend a small fraction of
 * each pixel toward a darker ink color wherever that pixel falls inside a
 * screen's own rotated dot grid, which is what reads as fine dot texture
 * up close rather than the sole source of the image's own detail. See
 * djdt_halftone_render() for the exact per pixel math.
 *
 * Every baked file gets a unique name (the recipe version plus a short
 * hash of the source file, djdt_halftone_filename() below), not a name
 * keyed on the attachment id alone: a rebake that reuses the old photo's
 * exact filename is what let browser and Cloudflare caches keep serving
 * the stale file after v1.5.4's fix actually worked server side (found
 * live: files rebaked in color, visitors still saw gray). A new bake is
 * always a new URL now, so there is nothing stale left to serve; the
 * previous file is deleted once the new one is written and verified.
 *
 * Earlier history: v1.4.1 baked edge fades into the file itself (fixed a
 * seam, moved it instead, reverted in v1.5.0, see CLAUDE.md
 * "Smoothness"); v1.5.1 to v1.5.4 fixed the rebake mechanism itself
 * (per-photo version tracking, crash safety, and a gray output actually
 * being caught and rejected). All of that groundwork, the per-photo
 * '_djdt_bake_ver' tracking, the memory and time safety, and the pixel
 * level gray check, carries forward unchanged into this recipe.
 * ---------------------------------------------------------------------- */

define( 'DJDT_HALFTONE_INK_DARK', '#160a33' );
define( 'DJDT_HALFTONE_VIOLET', '#5a2e91' );
define( 'DJDT_HALFTONE_CYAN', '#24a9e0' );
define( 'DJDT_HALFTONE_PAPER', '#eef6fa' );
// v1.7.2: the two halftone screens no longer paint a flat, fully opaque
// dot; each one blends a pixel a fixed fraction of the way toward its own
// ink color instead (see djdt_halftone_render()), a touch darker than the
// gradient's own violet/cyan stops above so the screens still read as a
// distinct texture on top of the smooth tone, not a repeat of it.
define( 'DJDT_HALFTONE_SCREEN_VIOLET', '#3a1a6e' );
define( 'DJDT_HALFTONE_SCREEN_CYAN', '#1f8fc4' );
define( 'DJDT_HALFTONE_MAX_DIM', 2000 );
// Bump this whenever djdt_bake_halftone() changes in a way that means an
// already baked file needs regenerating to look right, so a recipe fix
// reaches photos Mark already picked without him needing to resave Hero
// Photos (see the admin_init hook below). Baked into the filename itself
// now (djdt_halftone_filename()), so a bump also forces a fresh URL, not
// just a fresh file.
//
// v1.6.1: bumped to 2. Found live: the bake was running at the source
// photo's own size (800px for two of the three hero photos), then the
// browser stretched that up to fill the photo panel, which is what read
// as soft, oversized dots; and the recipe was reading too dark overall.
// Fixed below, see the note on djdt_halftone_decode_gd().
//
// v1.7.2: bumped to 3, the "style A" smooth duotone plus light halftone
// overlay recipe, replacing the dots-only look entirely (see the section
// comment above and djdt_halftone_render() below).
define( 'DJDT_HALFTONE_RECIPE', 3 );

/**
 * Returns the baked halftone URL for a hero photo attachment, generating
 * it first if it does not exist yet (a photo saved before this recipe
 * existed, or whose baked file was somehow lost, self heals on the next
 * page load that needs it, no resave required). '' if baking was not
 * possible.
 */
function djdt_halftone_url( $id ) {
	$upload_dir = wp_upload_dir();
	$rel        = get_post_meta( $id, '_djdt_halftone_file', true );
	if ( $rel && file_exists( trailingslashit( $upload_dir['basedir'] ) . $rel ) ) {
		return trailingslashit( $upload_dir['baseurl'] ) . $rel;
	}
	return djdt_ensure_halftone( $id );
}

/**
 * Builds this bake's unique file name: the recipe version plus a short
 * hash of the source file's own mtime and size, so a recipe bump AND a
 * photo re-uploaded over the same attachment id both land on a new file
 * name, never the one a cache (browser or Cloudflare) already has.
 */
function djdt_halftone_filename( $id, $src_path ) {
	$sig  = $id . '|' . DJDT_HALFTONE_RECIPE . '|' . (string) @filemtime( $src_path ) . '|' . (string) @filesize( $src_path );
	$hash = substr( md5( $sig ), 0, 6 );
	return 'djdt-halftone-' . $id . '-v' . DJDT_HALFTONE_RECIPE . '-' . $hash . '.jpg';
}

/**
 * Generates (or regenerates) the baked halftone copy for one attachment.
 * Returns its URL, or '' if baking was not possible. Carries forward the
 * v1.5.3/v1.5.4 safety work unchanged: raised memory and time limits
 * before baking (a PHP fatal from either one is not catchable, so
 * prevention matters more than a try/catch here), and a post bake pixel
 * check (djdt_bake_is_gray()) that rejects a file with no real ink color
 * in it, falling back to the unprocessed original exactly as a caught
 * exception would.
 */
function djdt_ensure_halftone( $id ) {
	$id = (int) $id;
	if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
		return '';
	}
	$src_path = get_attached_file( $id );
	if ( ! $src_path || ! file_exists( $src_path ) ) {
		djdt_set_bake_error( $id, 'source file missing' );
		return '';
	}
	$upload_dir = wp_upload_dir();
	$rel_dir    = trailingslashit( dirname( str_replace( trailingslashit( $upload_dir['basedir'] ), '', $src_path ) ) );
	$filename   = djdt_halftone_filename( $id, $src_path );
	$rel_file   = $rel_dir . $filename;
	$abs_file   = trailingslashit( $upload_dir['basedir'] ) . $rel_file;
	$old_rel    = get_post_meta( $id, '_djdt_halftone_file', true );

	// Best effort, silently ignored on a host that will not allow raising
	// either one, which is no worse than leaving them at the default.
	if ( function_exists( 'ini_set' ) ) {
		@ini_set( 'memory_limit', '512M' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 );
	}

	$ok = djdt_bake_halftone( $src_path, $abs_file );

	$gray_reason = '';
	if ( $ok && djdt_bake_is_gray( $abs_file ) ) {
		// Same safety net as v1.5.4's duotone recipe: a bake that reports
		// success but writes a file with no real violet or cyan ink in it
		// (missing dots, a decode that silently failed partway) is caught
		// here and rejected rather than trusted, falling through to the
		// ! $ok branch below exactly as if baking had failed outright,
		// which is what makes the hero shortcode's existing fallback serve
		// the unprocessed original instead of a broken file.
		@unlink( $abs_file );
		$ok          = false;
		$gray_reason = 'baked with no violet or cyan ink at all';
	}

	if ( ! $ok ) {
		delete_post_meta( $id, '_djdt_halftone_file' );
		delete_post_meta( $id, '_djdt_bake_ver' );
		djdt_set_bake_error( $id, $gray_reason ? $gray_reason : 'halftone bake failed' );
		return '';
	}

	// Delete the previous baked file now that the new one is saved and
	// verified: every bake gets its own unique name (see
	// djdt_halftone_filename()), so nothing else still points at the old
	// one once this meta is overwritten below, and leaving it on disk
	// would just be dead weight in uploads.
	if ( $old_rel && $old_rel !== $rel_file ) {
		@unlink( trailingslashit( $upload_dir['basedir'] ) . $old_rel );
	}

	delete_post_meta( $id, '_djdt_bake_error' );
	update_post_meta( $id, '_djdt_halftone_file', $rel_file );
	// Stamped with the recipe that produced this file, so the admin_init
	// pass below can tell a correctly baked photo from one still waiting
	// on a rebake, per photo, see the note there.
	update_post_meta( $id, '_djdt_bake_ver', DJDT_HALFTONE_RECIPE );
	return trailingslashit( $upload_dir['baseurl'] ) . $rel_file;
}

/**
 * Records why a hero photo's bake failed, timestamped, so djdt_halftone_url()
 * never has to guess and the admin_notices hook below can tell Mark about
 * a stuck photo directly instead of it failing invisibly forever (v1.5.3).
 * Cleared the moment a later attempt actually succeeds.
 */
function djdt_set_bake_error( $id, $reason ) {
	update_post_meta( $id, '_djdt_bake_error', array( 'reason' => $reason, 'time' => time() ) );
}

/**
 * Samples a grid of pixels in a baked JPEG and reports whether it is
 * missing real violet or cyan ink, rather than a genuine halftone (v1.6.0,
 * carried forward from the v1.5.4 duotone version of this same check).
 * Used right after a bake that reported success, since "the library
 * returned true" and "the file is actually tinted" turned out live not to
 * be the same thing, see the note on djdt_ensure_halftone() above. Uses GD
 * purely to read pixels back, on purpose, regardless of which library
 * produced the file: this is a sanity check on the output, not another
 * encode path to trust.
 *
 * v1.6.0: the threshold and sample count both changed from the flat
 * duotone version. A halftone bake has large near white paper regions
 * between dots (DJDT_HALFTONE_PAPER, #eef6fa, R/G/B already differ by up
 * to 12 on paper alone), where the old duotone threshold (greater than 8)
 * would misread plain paper as "colorful" and let a genuinely failed,
 * all paper bake pass. 30 sits well above paper's own spread and well
 * below real violet or cyan ink's (close to 100 and 190). A denser 10x10
 * grid with at least 3 genuinely colorful points required, not just 1,
 * fits a bake where only part of the image is covered in dots, while
 * still being a low bar for any real halftone output to clear.
 */
function djdt_bake_is_gray( $path ) {
	$img = @imagecreatefromjpeg( $path );
	if ( ! $img ) {
		return true;
	}
	$w        = imagesx( $img );
	$h        = imagesy( $img );
	$colorful = 0;
	for ( $gy = 1; $gy <= 10; $gy++ ) {
		for ( $gx = 1; $gx <= 10; $gx++ ) {
			$x   = (int) ( $w * $gx / 11 );
			$y   = (int) ( $h * $gy / 11 );
			$rgb = imagecolorat( $img, $x, $y );
			$r   = ( $rgb >> 16 ) & 0xFF;
			$g   = ( $rgb >> 8 ) & 0xFF;
			$b   = $rgb & 0xFF;
			if ( max( $r, $g, $b ) - min( $r, $g, $b ) > 30 ) {
				$colorful++;
			}
		}
	}
	imagedestroy( $img );
	return $colorful < 3;
}

/**
 * Decodes a source photo into a plain GD truecolor resource, oriented and
 * resized so its long side is exactly DJDT_HALFTONE_MAX_DIM, up or down
 * (v1.6.1: previously only ever downscaled; see the note below). Imagick
 * assisted when available, purely for a safe, memory bounded decode: pings
 * the file for its real dimensions first and, for a source larger than
 * needed, sets 'jpeg:size' before the real read so libjpeg decodes at a
 * reduced scale directly instead of decoding the original at full
 * resolution only to immediately throw most of it away in the resize
 * below (the same reasoning as the old duotone recipe's v1.5.3 fix). Once
 * decoded and resized, Imagick's own job ends: the image is handed to GD
 * (imagecreatefromstring on a PNG blob) and every pixel operation from
 * there on, for both the Imagick assisted path and the Imagick
 * unavailable fallback below, runs through the exact same GD code in
 * djdt_halftone_render(), so there are two decode paths but only one
 * rendering path, not two recipes that could quietly drift apart or
 * disagree on what "the same look" means.
 *
 * v1.6.0: no ImageMagick pseudo coder of any kind is used anywhere in this
 * recipe ('gradient:', 'pattern:', 'xc:' and similar), on purpose. v1.5.4
 * found live that a hardened host's policy.xml can silently neuter a
 * pseudo coder (ImageMagick's 'gradient:') instead of throwing, which is
 * what let a bake report success while writing a flat, wrong result; the
 * fix then was to stop depending on that one coder. This recipe goes
 * further: Imagick here only pings, reads, orients and resizes, the same
 * handful of core operations already proven to work on the live host, and
 * does not touch a clut, a pseudo image, or any other procedural coder at
 * all.
 *
 * v1.6.1, found live: a source photo smaller than DJDT_HALFTONE_MAX_DIM
 * (two of the three hero photos are 800px) was baked at its own, smaller
 * size, then stretched up by the browser to fill the photo panel, which
 * is what read as big, soft dots rather than crisp ones: the dot screen
 * itself was generated small and then enlarged along with everything
 * else, instead of being generated at the final size to begin with. This
 * is the one deliberate exception to "never upscale" anywhere in this
 * plugin: a smooth Lanczos (Imagick) or bicubic-equivalent
 * (imagecopyresampled, GD) upscale of the grayscale source, before the
 * halftone screen is drawn, so the dot screen itself is always generated
 * at the full target resolution regardless of the source's own size, and
 * the halftone's own dot pattern hides the softness an enlarged photo
 * would otherwise show plainly. Still capped at DJDT_HALFTONE_MAX_DIM on
 * the long side either way, just no longer only ever scaling down to it.
 */
function djdt_halftone_decode_gd( $src_path ) {
	if ( class_exists( 'Imagick' ) ) {
		try {
			$img = new Imagick();
			$img->setResourceLimit( Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
			$img->setResourceLimit( Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
			$img->pingImage( $src_path );
			$pw   = $img->getImageWidth();
			$ph   = $img->getImageHeight();
			$pmax = max( $pw, $ph );
			if ( $pmax > DJDT_HALFTONE_MAX_DIM ) {
				$pscale = ( DJDT_HALFTONE_MAX_DIM * 2 ) / $pmax;
				$img->setOption( 'jpeg:size', (int) round( $pw * $pscale ) . 'x' . (int) round( $ph * $pscale ) );
			}
			$img->readImage( $src_path );
			if ( method_exists( $img, 'autoOrientImage' ) ) {
				$img->autoOrientImage();
			}
			$w     = $img->getImageWidth();
			$h     = $img->getImageHeight();
			$max   = max( $w, $h );
			$scale = DJDT_HALFTONE_MAX_DIM / $max;
			$w     = (int) round( $w * $scale );
			$h     = (int) round( $h * $scale );
			$img->resizeImage( $w, $h, Imagick::FILTER_LANCZOS, 1 );
			$img->setImageFormat( 'png' );
			$blob = $img->getImageBlob();
			$img->destroy();
			$gd = @imagecreatefromstring( $blob );
			if ( $gd ) {
				return $gd;
			}
		} catch ( \Throwable $e ) {
			// Falls through to the plain GD decode below.
		}
	}
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return false;
	}
	$info = @getimagesize( $src_path );
	if ( ! $info ) {
		return false;
	}
	// A full GD decode loads the whole original into memory before any
	// resize happens, with no scaled decode option of its own (unlike
	// Imagick's 'jpeg:size' above); an original past a sane pixel budget
	// is skipped outright, a clean and loggable "no GD fallback either"
	// outcome, rather than risking a PHP memory fatal that no catch block
	// here could intercept.
	if ( $info[0] * $info[1] > 40000000 ) {
		return false;
	}
	switch ( $info[2] ) {
		case IMAGETYPE_JPEG:
			$src = @imagecreatefromjpeg( $src_path );
			break;
		case IMAGETYPE_PNG:
			$src = @imagecreatefrompng( $src_path );
			break;
		case IMAGETYPE_WEBP:
			$src = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $src_path ) : false;
			break;
		default:
			$src = false;
	}
	if ( ! $src ) {
		return false;
	}
	// GD has no equivalent of Imagick's autoOrientImage; correct a plain
	// JPEG's EXIF rotation by hand the same way, or a photo shot on a
	// phone can come out baked sideways.
	if ( IMAGETYPE_JPEG === $info[2] && function_exists( 'exif_read_data' ) ) {
		$exif = @exif_read_data( $src_path );
		if ( ! empty( $exif['Orientation'] ) ) {
			$rotated = djdt_gd_rotate_by_exif( $src, (int) $exif['Orientation'] );
			if ( $rotated ) {
				imagedestroy( $src );
				$src = $rotated;
			}
		}
	}
	$w       = imagesx( $src );
	$h       = imagesy( $src );
	$max     = max( $w, $h );
	$scale   = DJDT_HALFTONE_MAX_DIM / $max;
	$nw      = max( 1, (int) round( $w * $scale ) );
	$nh      = max( 1, (int) round( $h * $scale ) );
	$resized = imagecreatetruecolor( $nw, $nh );
	imagecopyresampled( $resized, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
	imagedestroy( $src );
	return $resized;
}

/**
 * Smooth gradient map: turns one luminance value (0 = black, 1 = white)
 * into an [r, g, b] color by linearly interpolating between four fixed
 * stops (0.00 near black violet, 0.35 violet, 0.72 cyan, 1.00 paper).
 * This is what carries a photo's own detail now (v1.7.2): every pixel
 * gets its own color from this curve, not just the pixels a halftone dot
 * happens to land on, see the section comment above
 * djdt_halftone_decode_gd() / DJDT_HALFTONE_VIOLET for why that matters.
 */
function djdt_halftone_gradient_rgb( $l, $stop0, $stop35, $stop72, $stop100 ) {
	if ( $l <= 0.35 ) {
		$t = $l / 0.35;
		$a = $stop0;
		$b = $stop35;
	} elseif ( $l <= 0.72 ) {
		$t = ( $l - 0.35 ) / ( 0.72 - 0.35 );
		$a = $stop35;
		$b = $stop72;
	} else {
		$t = ( $l - 0.72 ) / ( 1 - 0.72 );
		$a = $stop72;
		$b = $stop100;
	}
	return array(
		$a[0] + ( $b[0] - $a[0] ) * $t,
		$a[1] + ( $b[1] - $a[1] ) * $t,
		$a[2] + ( $b[2] - $a[2] ) * $t,
	);
}

/**
 * Distance, in cell units, from (x, y) to the nearest center of a dot
 * screen's own rotated grid: rotates the point into the screen's own
 * axis aligned basis first ($cos/$sin, precomputed once per screen
 * outside the per pixel loop that calls this), divides by the pitch so
 * one grid cell is exactly 1.0 unit across, then measures how far that
 * lands from the nearest whole grid coordinate in both axes. 0 means
 * sitting exactly on a grid point, a little over 0.7 is the farthest a
 * point can sit from the nearest one (dead center of a cell).
 */
function djdt_halftone_screen_distance( $x, $y, $cos, $sin, $pitch ) {
	$u  = ( $x * $cos + $y * $sin ) / $pitch;
	$v  = ( -$x * $sin + $y * $cos ) / $pitch;
	$du = $u - round( $u );
	$dv = $v - round( $v );
	return sqrt( $du * $du + $dv * $dv );
}

/**
 * The style A look itself (v1.7.2, built live on Home v2, Mark's pick over
 * the alternatives): a smooth duotone base, from the gradient map above,
 * with two rotated halftone screens blended lightly on top, violet for
 * the shadows and cyan for the mids, so the dots read as a texture over
 * real detail rather than being the only thing carrying any detail at
 * all (the v1.6.0 to v1.7.1 recipe's own failure mode). Genuinely per
 * pixel, not per dot center painted onto blank paper: every output pixel
 * gets its own luminance, its own gradient mapped base color, and its own
 * pass/fail test against each screen's rotated grid.
 *
 * Both screens use a fixed 3px pitch regardless of the output's own size
 * (v1.6.1's dot screen instead derived a cell size from DJDT_HALFTONE_MAX_DIM,
 * grown or shrunk with it; this recipe's dots are meant to read as a fine,
 * fixed grain close up rather than a size that tracks the photo panel,
 * per the brief). The violet screen samples 2px left and 1px up from
 * where the cyan screen samples (not the output pixel's own true
 * position), the off register look a real riso print's two passes have
 * when they do not land in exact registration; each screen's own ink
 * blend only ever nudges a pixel a fixed fraction toward that screen's
 * ink color (45% violet, 30% cyan) rather than replacing it outright, so
 * the gradient's own base color is never fully lost under a dot.
 *
 * Plain pixel operations throughout (imagecolorat, imagesetpixel), no
 * procedural coder of any kind, see the note on djdt_halftone_decode_gd()
 * above for why that matters on this host.
 */
function djdt_halftone_render( $src, $w, $h ) {
	$out = imagecreatetruecolor( $w, $h );

	$stop0   = djdt_hex_rgb( DJDT_HALFTONE_INK_DARK );
	$stop35  = djdt_hex_rgb( DJDT_HALFTONE_VIOLET );
	$stop72  = djdt_hex_rgb( DJDT_HALFTONE_CYAN );
	$stop100 = djdt_hex_rgb( DJDT_HALFTONE_PAPER );

	$ink_violet = djdt_hex_rgb( DJDT_HALFTONE_SCREEN_VIOLET );
	$ink_cyan   = djdt_hex_rgb( DJDT_HALFTONE_SCREEN_CYAN );

	$pitch = 3.0;

	$violet_angle = deg2rad( 75 );
	$violet_cos   = cos( $violet_angle );
	$violet_sin   = sin( $violet_angle );

	$cyan_angle = deg2rad( 15 );
	$cyan_cos   = cos( $cyan_angle );
	$cyan_sin   = sin( $cyan_angle );

	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$rgb = imagecolorat( $src, $x, $y );
			$r   = ( $rgb >> 16 ) & 0xFF;
			$g   = ( $rgb >> 8 ) & 0xFF;
			$b   = $rgb & 0xFF;

			$l = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
			$l = ( $l - 0.5 ) * 1.15 + 0.5;
			$l = $l < 0 ? 0 : ( $l > 1 ? 1 : $l );
			$l = pow( $l, 0.9 );

			list( $cr, $cg, $cb ) = djdt_halftone_gradient_rgb( $l, $stop0, $stop35, $stop72, $stop100 );

			// Violet screen (shadows): sampled 2px left, 1px up, for the
			// off register look, see the function doc comment above.
			$vdist = djdt_halftone_screen_distance( $x - 2, $y - 1, $violet_cos, $violet_sin, $pitch );
			if ( $vdist < sqrt( max( 0.0, 1 - $l ) ) * 0.62 ) {
				$cr += ( $ink_violet[0] - $cr ) * 0.45;
				$cg += ( $ink_violet[1] - $cg ) * 0.45;
				$cb += ( $ink_violet[2] - $cb ) * 0.45;
			}

			// Cyan screen (mids), no offset: a triangle peaking at 0.55
			// luminance, zero at both ends of the tonal range.
			$mid   = 1 - abs( $l - 0.55 ) * 2;
			$cdist = djdt_halftone_screen_distance( $x, $y, $cyan_cos, $cyan_sin, $pitch );
			if ( $cdist < sqrt( max( 0.0, $mid ) ) * 0.5 ) {
				$cr += ( $ink_cyan[0] - $cr ) * 0.30;
				$cg += ( $ink_cyan[1] - $cg ) * 0.30;
				$cb += ( $ink_cyan[2] - $cb ) * 0.30;
			}

			imagesetpixel(
				$out,
				$x,
				$y,
				( ( (int) round( $cr ) ) << 16 ) | ( ( (int) round( $cg ) ) << 8 ) | (int) round( $cb )
			);
		}
	}

	return $out;
}

/** Decodes, renders and writes the halftone bake for one source file. Returns true/false, same contract as the old per-library bake functions. */
function djdt_bake_halftone( $src_path, $dest_jpg ) {
	$src = djdt_halftone_decode_gd( $src_path );
	if ( ! $src ) {
		return false;
	}
	$w = imagesx( $src );
	$h = imagesy( $src );
	if ( $w <= 0 || $h <= 0 ) {
		imagedestroy( $src );
		return false;
	}
	$out = djdt_halftone_render( $src, $w, $h );
	imagedestroy( $src );
	wp_mkdir_p( dirname( $dest_jpg ) );
	$ok = imagejpeg( $out, $dest_jpg, 90 );
	imagedestroy( $out );
	return $ok;
}
function djdt_gd_rotate_by_exif( $img, $orientation ) {
	switch ( $orientation ) {
		case 3:
			return imagerotate( $img, 180, 0 );
		case 6:
			return imagerotate( $img, -90, 0 );
		case 8:
			return imagerotate( $img, 90, 0 );
		default:
			return false;
	}
}

function djdt_hex_rgb( $hex ) {
	$hex = ltrim( $hex, '#' );
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Rebakes any saved hero photo whose own '_djdt_bake_ver' postmeta does not
 * match the current DJDT_HALFTONE_RECIPE, so a recipe fix reaches photos
 * Mark already picked without him needing to resave Hero Photos. Runs for
 * an admin on any admin page load, not just the Hero Photos screen
 * (otherwise it would never run until he happened to visit that one
 * screen), and not on the front end, since baking is too slow to do on a
 * visitor's page load. One photo per load, not every mismatched one in a
 * single pass: baking all of them together is exactly the kind of
 * per-request cost that can run into a shared host's memory or time
 * limit (see djdt_ensure_halftone()), and a limit hit kills the whole
 * request before even the first photo's result gets written. This whole
 * mechanism, the per-photo version tracking, the one-per-load pacing, and
 * the postmeta based error reporting below, carries forward unchanged
 * from the v1.5.1 through v1.5.4 duotone recipe; only the bake function
 * it calls changed for v1.6.0's halftone recipe.
 */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rows = djdt_get( 'hero_photos' );
	if ( ! is_array( $rows ) ) {
		return;
	}
	foreach ( $rows as $row ) {
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		if ( $id > 0 && (int) get_post_meta( $id, '_djdt_bake_ver', true ) !== DJDT_HALFTONE_RECIPE ) {
			djdt_ensure_halftone( $id );
			break;
		}
	}
} );

/**
 * Surfaces a hero photo whose bake is still stuck as a visible admin
 * notice, so a repeated failure has somewhere to show up instead of
 * failing the same way forever with nothing to see. Reads the
 * '_djdt_bake_error' postmeta djdt_set_bake_error() writes; cleared the
 * moment a later attempt actually succeeds.
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rows = djdt_get( 'hero_photos' );
	if ( ! is_array( $rows ) ) {
		return;
	}
	$stuck = array();
	foreach ( $rows as $row ) {
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		if ( $id <= 0 ) {
			continue;
		}
		$error = get_post_meta( $id, '_djdt_bake_error', true );
		if ( is_array( $error ) && ! empty( $error['reason'] ) ) {
			$stuck[] = '#' . $id . ' (' . $error['reason'] . ')';
		}
	}
	if ( empty( $stuck ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p><strong>DigiMark Theme:</strong> could not bake the halftone hero photo copy for ' . esc_html( implode( ', ', $stuck ) ) . '. This retries automatically on the next admin page load.</p></div>';
} );

/**
 * Cream gaps above the hero, between the hero and venues, and above the
 * footer (v1.4.1, found live): wpautop runs on the raw post content before
 * do_shortcode expands anything, so it has no idea [djdigi_hero] and
 * [djdigi_venues] become block level markup (a <section>) and wraps the
 * bare shortcode tags the same as any other line, in an empty <p> (with
 * its own margin) and a <br> between them when they are on adjacent
 * lines. v1.4.1 hooked shortcode_unautop() onto 'the_content' at priority
 * 10.5 to strip that wrapping. v1.4.2 removes it: a float priority is not
 * a valid WordPress hook priority (an int is expected), and passing one
 * printed a PHP deprecation warning at the top of every single page on
 * the live site, for every visitor, not just here in admin. Never use a
 * float priority on any hook, a plain int between two integer priorities
 * does not exist, pick a real whole number or reorder by registration
 * timing instead. The fix for this is the CSS backup alone now, see
 * site.css (body.djdigi-has-hero .post-content > p, > br).
 *
 * v1.13.0, found live: a 20px gap above the hero again, for every
 * visitor. Home v2's content now sits inside an Avada Builder text
 * element, so the empty <p> wpautop leaves is the first child of
 * .fusion-text, not of .post-content, and the v1.4.2 CSS never matched
 * it. It is stripped server side now, see djdt_strip_hero_autop() below
 * (priority 20, a plain int, well after do_shortcode at 11), with a
 * CSS backup in site.css.
 */

/** True on a singular page whose content carries [djdigi_hero], for a viewer the theme is active for. */
function djdt_is_hero_page() {
	if ( ! djdt_is_active() || ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post && has_shortcode( $post->post_content, 'djdigi_hero' );
}

/**
 * The phone menu pill's button reads "Menu", not Avada's "Go to..."
 * (v1.14.0). Avada's classic mobile menu builds that button in its own
 * JS from a translated string it localizes (avadaMenuVars.dropdown_goto),
 * so it is changed at the source here, hero pages only, where that button
 * is the pill. A combined/minified copy cached by Asset CleanUp can still
 * carry the old string until purged, so menu.js swaps it on the page too.
 * The string compare comes first, this filter sees every translation; and
 * nothing is checked before the main query has run (did_action( 'wp' )).
 */
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'Go to...' === $text && 'Avada' === $domain && did_action( 'wp' ) && djdt_is_hero_page() ) {
		return 'Menu';
	}
	return $translation;
}, 10, 3 );

add_filter( 'body_class', function ( $classes ) {
	if ( ! djdt_is_active() ) {
		return $classes;
	}
	$classes[] = 'djdigi-theme';
	if ( djdt_is_hero_page() ) {
		$classes[] = 'djdigi-has-hero';
		if ( shortcode_exists( 'djdigi_next_up' ) ) {
			$classes[] = 'djdigi-hero-strip';
		}
	}
	return $classes;
} );

/**
 * Removes the wpautop leftovers around the hero (v1.13.0): the opening
 * <p> (plus any <br> or whitespace) right before <section class="djd-hero">,
 * which the browser otherwise closes into an empty paragraph with a 20px
 * bottom margin above the hero, and any <br> or closing </p> right after
 * the hero's own end marker (djdt_hero_shortcode()). Runs once the whole
 * page content, Avada Builder elements included, has been expanded.
 */
function djdt_strip_hero_autop( $content ) {
	if ( ! djdt_is_active() || false === strpos( $content, 'djd-hero' ) ) {
		return $content;
	}
	$content = preg_replace( '#<p>(?:\s|&nbsp;|<br\s*/?>)*(?=<section class="djd-hero")#i', '', $content );
	$content = preg_replace( '#(<!-- /djd-hero -->)(?:\s|<br\s*/?>)*(?:</p>)?#i', '$1', $content );
	return $content;
}
add_filter( 'the_content', 'djdt_strip_hero_autop', 20 );

/**
 * The header logo on hero pages (v1.13.0): Avada prints three logo <img>s
 * (standard, sticky, mobile), all pointing at the old DigiMarkLogo60.png
 * with a 1x/2x srcset and a data-retina_logo_url its own JS swaps in on a
 * high density screen. On a hero page every one of them is rewritten to
 * the night logo (djdt_logo_url(), the bundled SVG unless Mark picked an
 * override) before the page leaves the server, srcset cleared, so the old
 * PNG never shows, not even for one frame before mirrorball.js runs.
 * There is no Avada filter for these tags, so the page is buffered from
 * template_redirect and the tags rewritten in the buffer's callback; the
 * buffer is only ever started on a hero page.
 */
add_action( 'template_redirect', function () {
	if ( ! djdt_is_hero_page() ) {
		return;
	}
	$url = djdt_logo_url();
	if ( '' === $url ) {
		return;
	}
	ob_start( function ( $html ) use ( $url ) {
		return djdt_swap_header_logos( $html, $url );
	} );
} );

/** Rewrites every Avada header logo <img> in $html to point at $url, see above. */
function djdt_swap_header_logos( $html, $url ) {
	if ( ! is_string( $html ) || false === strpos( $html, '-logo' ) ) {
		return $html;
	}
	$src = esc_url( $url );
	return preg_replace_callback(
		'#<img\b[^>]*\bclass="[^"]*\bfusion-(?:standard|sticky|mobile)-logo\b[^"]*"[^>]*>#i',
		function ( $m ) use ( $src ) {
			$tag = preg_replace( '#\s(?:srcset|sizes|data-srcset|data-orig-srcset)=(?:"[^"]*"|\'[^\']*\')#i', '', $m[0] );
			$tag = preg_replace_callback(
				'#\s(src|data-src|data-orig-src|data-retina_logo_url)=(?:"[^"]*"|\'[^\']*\')#i',
				function ( $a ) use ( $src ) {
					return ' ' . $a[1] . '="' . $src . '"';
				},
				$tag
			);
			$tag = preg_replace( '#\swidth=(?:"[^"]*"|\'[^\']*\')#i', ' width="1517"', $tag );
			$tag = preg_replace( '#\sheight=(?:"[^"]*"|\'[^\']*\')#i', ' height="1798"', $tag );
			return $tag;
		},
		$html
	);
}

/* -------------------------------------------------------------------------
 * Mixcloud
 * ---------------------------------------------------------------------- */

function djdt_mixcloud_get( $path ) {
	$res = wp_remote_get( 'https://api.mixcloud.com' . $path, array( 'timeout' => 6 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * Returns the player's default mix: key, name, length (seconds), picture
 * (artwork URL), thumb and url (the mix's own Mixcloud page). A pinned mix
 * is cached for 6 hours.
 */
function djdt_get_mix() {
	// v1.14.0: "latest" mode is simply the first row of the playlist
	// (djdt_get_mixes(), its own 1 hour cache and last good fallback), so
	// the player and the playlist can never disagree about which is newest.
	if ( 'pinned' !== djdt_get( 'mix_mode' ) || ! djdt_get( 'mix_url' ) ) {
		$mixes = djdt_get_mixes();
		return ! empty( $mixes[0]['key'] ) ? $mixes[0] : array();
	}
	$cached = get_transient( 'djdt_mix' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$mix  = null;
	$path = wp_parse_url( djdt_get( 'mix_url' ), PHP_URL_PATH );
	if ( $path ) {
		$key  = '/' . trim( $path, '/' ) . '/';
		$data = djdt_mixcloud_get( $key );
		$mix  = $data ? djdt_mix_from_api( $key, $data ) : null;
	}
	// Cache failures briefly so a Mixcloud outage never slows every page.
	set_transient( 'djdt_mix', $mix ? $mix : array(), $mix ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $mix ? $mix : array();
}

/**
 * The 3 newest cloudcasts for the "Latest live sets" playlist (v1.14.0),
 * newest first, each in djdt_mix_from_api()'s shape. Cached for an hour.
 * Every good fetch is also kept as a "last good" copy with no expiry, so a
 * Mixcloud error serves that instead of an empty playlist (re-tried after
 * 10 minutes rather than on every page load). array() if there has never
 * been a good fetch for the current username.
 */
function djdt_get_mixes() {
	$cached = get_transient( 'djdt_mixes' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$user  = (string) djdt_get( 'mixcloud_user' );
	$mixes = array();
	$data  = djdt_mixcloud_get( '/' . rawurlencode( $user ) . '/cloudcasts/?limit=3' );
	if ( $data && ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
		foreach ( array_slice( $data['data'], 0, 3 ) as $c ) {
			if ( ! empty( $c['key'] ) ) {
				$mixes[] = djdt_mix_from_api( $c['key'], $c );
			}
		}
	}
	if ( $mixes ) {
		set_transient( 'djdt_mixes', $mixes, HOUR_IN_SECONDS );
		update_option( 'djdt_mixes_last_good', array( 'user' => $user, 'mixes' => $mixes ), false );
		return $mixes;
	}
	$last  = get_option( 'djdt_mixes_last_good', array() );
	$mixes = ( is_array( $last ) && isset( $last['user'], $last['mixes'] ) && $last['user'] === $user && is_array( $last['mixes'] ) ) ? $last['mixes'] : array();
	set_transient( 'djdt_mixes', $mixes, 10 * MINUTE_IN_SECONDS );
	return $mixes;
}

function djdt_mix_from_api( $key, $data ) {
	$pictures = isset( $data['pictures'] ) && is_array( $data['pictures'] ) ? $data['pictures'] : array();
	$picture  = '';
	foreach ( array( 'extra_large', 'large' ) as $size ) {
		if ( ! empty( $pictures[ $size ] ) ) {
			$picture = $pictures[ $size ];
			break;
		}
	}
	// The playlist's small row thumbnail (v1.14.0), shown 56px wide.
	$thumb = '';
	foreach ( array( 'medium', 'medium_mobile', 'large', 'thumbnail' ) as $size ) {
		if ( ! empty( $pictures[ $size ] ) ) {
			$thumb = $pictures[ $size ];
			break;
		}
	}
	return array(
		'key'     => $key,
		'name'    => isset( $data['name'] ) ? $data['name'] : '',
		'length'  => isset( $data['audio_length'] ) ? (int) $data['audio_length'] : 0,
		'picture' => $picture,
		'thumb'   => $thumb ? $thumb : $picture,
		'url'     => ! empty( $data['url'] ) ? $data['url'] : 'https://www.mixcloud.com' . $key,
	);
}

function djdt_format_length( $sec ) {
	if ( $sec <= 0 ) {
		return '';
	}
	$h = floor( $sec / 3600 );
	$m = floor( ( $sec % 3600 ) / 60 );
	$s = $sec % 60;
	return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
}

/**
 * The player waveform, inlined directly into the page rather than loaded as
 * an <img src>. Two reasons: an externally loaded SVG's own internal
 * preserveAspectRatio still governs how it fits inside an <img>'s box even
 * once CSS sizes that box, which is what made it render letterboxed instead
 * of full width; and a separate cached file is one more thing a caching
 * plugin can serve stale. Inline, the clip-path progress overlay and the
 * bar colors are both plain CSS against currentColor.
 *
 * @param string $class Added to the <svg> root, for CSS to target.
 */
function djdt_inline_wave( $class ) {
	static $svg = null;
	if ( null === $svg ) {
		$path = DJDT_DIR . 'assets/img/wave.svg';
		$svg  = file_exists( $path ) ? file_get_contents( $path ) : '';
		// The file carries its own fill for standalone use, replaced here with
		// currentColor so the CSS classes above control color instead. Just
		// stripping the attribute would leave the SVG's own default fill
		// (black), not currentColor, since those are not the same thing.
		$svg = preg_replace( '/\sfill="[^"]*"/', ' fill="currentColor"', $svg, 1 );
	}
	if ( '' === $svg ) {
		return;
	}
	echo preg_replace( '/<svg /', '<svg class="' . esc_attr( $class ) . '" ', $svg, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput -- a local file this plugin ships, not user input
}

/* -------------------------------------------------------------------------
 * Hero photo rotation: the attachments picked in Settings, each with its
 * own focal point, or the bundled photo when none are picked.
 * ---------------------------------------------------------------------- */

/**
 * Returns an array of render-ready photos: src, srcset, x, y. Always
 * returns at least one row (the bundled photo as a fallback).
 */
function djdt_hero_photos() {
	$rows = djdt_get( 'hero_photos' );
	$out  = array();
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$halftone = djdt_halftone_url( $id );
			$src      = $halftone ? $halftone : wp_get_attachment_image_url( $id, 'full' );
			if ( ! $src ) {
				continue;
			}
			$out[] = array(
				'src' => $src,
				// A baked halftone copy has no WordPress generated responsive
				// sizes of its own, only the one file; it is already capped
				// at DJDT_HALFTONE_MAX_DIM, plenty for how large the photo
				// panel ever renders, so no srcset is needed for it.
				'srcset' => $halftone ? '' : (string) wp_get_attachment_image_srcset( $id, 'full' ),
				'x'      => isset( $row['x'] ) ? (float) $row['x'] : 50,
				'y'      => isset( $row['y'] ) ? (float) $row['y'] : 50,
			);
		}
	}
	if ( empty( $out ) ) {
		$out[] = array(
			'src'    => DJDT_URL . 'assets/img/hero-photo-halftone.jpg',
			'srcset' => '',
			'x'      => 50,
			'y'      => 50,
		);
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * [djdigi_hero]
 * ---------------------------------------------------------------------- */

add_shortcode( 'djdigi_hero', 'djdt_hero_shortcode' );

/**
 * $atts: player="no" turns off the "Latest live set" section this
 * shortcode outputs right after the hero itself (v1.6.0, the player
 * moved out of the hero; see djdt_player_section() below). No separate
 * shortcode for it, by design, since the hero is the only place it is
 * ever meant to appear.
 *
 * v1.11.0: rebuilt as the mirror ball hero (reference/mirrorball-hero-
 * reference.html, the approved demo, is the source of truth for the look;
 * see mirrorball.js for the port of its drawBall()/cluster()/speck math).
 * The name's own letter intro, the TV static crossfade and the three fixed
 * glows are gone; the photo panel is full bleed now, not a right side
 * panel, with a dark left side wash (`.djd-hero__shade`) doing the same
 * readability job the old left/bottom edge fade and glow used to. The ball
 * canvas and its wire (`#djd-ball-canvas`, `#djd-wire`) are fixed position,
 * output after the player section below rather than inside the hero
 * itself, same as the reference: fixed position elements do not care where
 * they sit in the DOM, and keeping them out of `.djd-hero` means nothing
 * about the hero's own stacking context (`isolation: isolate`) can ever
 * end up between them and the header they dock into.
 *
 * v1.12.0: shorter hero, smaller logo, and a new entrance timeline (plays
 * on every page load now, not just once per session), all in hero.css and
 * mirrorball.js, see CLAUDE.md "Homepage polish". `player="no"` pairs
 * with the new `[djdigi_player]` shortcode below, for a page (like the
 * new Home v2) that wants the player somewhere other than immediately
 * after the hero.
 */
function djdt_hero_shortcode( $atts = array() ) {
	if ( ! djdt_is_active() ) {
		return '';
	}
	$atts = shortcode_atts( array( 'player' => 'yes' ), (array) $atts, 'djdigi_hero' );

	wp_enqueue_style( 'djdt-hero' );
	wp_enqueue_script( 'djdt-hero' );
	wp_enqueue_script( 'djdt-mirrorball' );

	$photos   = djdt_hero_photos();
	$book     = trim( (string) djdt_get( 'book_url' ) );
	$ball     = djdt_logo_ball_settings();
	$logo_url = djdt_logo_url();

	wp_add_inline_script( 'djdt-mirrorball', 'window.djdtMirrorBall = ' . wp_json_encode( array(
		'logoUrl'   => $logo_url,
		'ballX'     => $ball['x'],
		'ballY'     => $ball['y'],
		'ballR'     => $ball['r'],
		// v1.14.0: the 3D ball, an ES module mirrorball.js imports after
		// the page has loaded (it imports the bundled three.js itself).
		'ball3dUrl' => add_query_arg( 'ver', djdt_ver( 'assets/js/mirrorball-3d.js' ), DJDT_URL . 'assets/js/mirrorball-3d.js' ),
	) ) . ';', 'before' );

	$next_up = shortcode_exists( 'djdigi_next_up' ) ? do_shortcode( '[djdigi_next_up]' ) : '';

	ob_start();
	?>
	<section class="djd-hero" aria-label="DJ DigiMark" data-photo-count="<?php echo (int) count( $photos ); ?>">
		<div class="djd-hero__photos">
			<?php foreach ( $photos as $i => $p ) : ?>
				<img
					class="djd-hero__photo<?php echo ( 0 === $i ) ? ' is-on' : ''; ?>"
					src="<?php echo esc_url( $p['src'] ); ?>"
					<?php if ( ! empty( $p['srcset'] ) ) : ?>srcset="<?php echo esc_attr( $p['srcset'] ); ?>" sizes="100vw"<?php endif; ?>
					style="object-position: <?php echo esc_attr( $p['x'] ); ?>% <?php echo esc_attr( $p['y'] ); ?>%"
					alt="DJ DigiMark mixing under club lights"
					<?php echo ( 0 === $i ) ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
				>
			<?php endforeach; ?>
		</div>

		<!-- Dark left to right wash, the reference's own .shade: readability
		for the inner block over the photo, replacing the old right side
		panel's own left/bottom edge fade (the photo is full bleed now). -->
		<div class="djd-hero__shade" aria-hidden="true"></div>

		<!-- Light specks: mirrorball.js draws these every frame, screen
		blended so they only ever brighten, never darken, the photo. -->
		<canvas class="djd-hero__fx" aria-hidden="true"></canvas>

		<!-- Black intro overlay: hides the first frame's own flash of a bare
		photo and ball drop, then fades out once the ball has landed. -->
		<div class="djd-hero__dark" aria-hidden="true"></div>

		<?php if ( $next_up ) : ?>
			<div class="djd-hero__strip djd-in-ui"><?php echo $next_up; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted shortcode output ?></div>
		<?php endif; ?>

		<div class="djd-hero__inner">
			<!-- Visually hidden: the real heading text for search engines and
			screen readers now that the name is drawn as a logo image, not
			typeset letters (v1.11.0). -->
			<h1 class="djd-hero__sr-name">DJ DigiMark</h1>
			<img class="djd-hero__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="DJ DigiMark" width="1517" height="1798">
			<p class="djd-hero__slogan">Bringing positive vibes to the dance <span class="djd-nowrap">floor <span class="djd-eq" role="img" aria-label="Rainbow flag and music"><span></span><span></span><span></span><span></span><span></span><span></span></span></span></p>
			<?php // v1.14.1: one "Book me" button (the shared book_url setting, also the footer's), none if empty. ?>
			<?php if ( '' !== $book ) : ?>
				<div class="djd-hero__buttons">
					<a class="djd-btn djd-btn--solid" href="<?php echo esc_url( $book ); ?>">Book me</a>
				</div>
			<?php endif; ?>
		</div>

		<!-- Fades the hero's own lower edge into the page, full width (v1.6.0,
		unchanged). See CLAUDE.md "Smoothness". -->
		<div class="djd-hero__bottom-fade" aria-hidden="true"></div>
	</section>
	<?php
	if ( 'no' !== $atts['player'] ) {
		echo djdt_player_section(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
	?>
	<!-- Fixed position: the wire the ball drops in on, and the ball itself.
	Kept outside the hero and player sections, see the function doc comment
	above; mirrorball.js positions both every frame regardless of where
	they sit in the DOM. -->
	<div id="djd-wire" aria-hidden="true"></div>
	<canvas id="djd-ball-canvas" aria-hidden="true"></canvas>
	<!-- /djd-hero -->
	<?php
	return ob_get_clean();
}

/**
 * The "Latest live set" section (v1.6.0, moved out of the hero itself:
 * Mark's call, the hero keeps only the name, slogan, the two buttons and
 * the photo). Same Mixcloud data and the same Widget API behavior as
 * before (hero.js), just its own section now, right after the hero, on
 * the same night background with no seam between them (see CLAUDE.md
 * "Smoothness"). No outlined box, a plain background on a gentle tone
 * step instead of a bordered card.
 */
function djdt_player_section() {
	$mix   = djdt_get_mix();
	$mixes = djdt_get_mixes();
	if ( empty( $mix['key'] ) ) {
		return '';
	}
	ob_start();
	?>
	<section class="djd-player-section" aria-label="Latest live sets">
		<div class="djd-player-section__inner">
			<h2 class="djd-section-heading"><?php echo djdt_heading_html( 'Latest live sets' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></h2>
			<div class="djd-player<?php echo $mix['picture'] ? ' has-artwork' : ''; ?>" data-feed="<?php echo esc_attr( $mix['key'] ); ?>" data-length="<?php echo (int) $mix['length']; ?>">
				<div class="djd-player__art">
					<?php // Always printed (v1.14.0): a playlist row can bring artwork to a mix that had none. ?>
					<img class="djd-player__artimg" src="<?php echo esc_url( $mix['picture'] ); ?>" alt=""<?php echo $mix['picture'] ? '' : ' hidden'; ?>>
					<button type="button" class="djd-player__btn" aria-label="<?php echo esc_attr( 'Play ' . $mix['name'] ); ?>" data-label="<?php echo esc_attr( $mix['name'] ); ?>">
						<svg class="djd-player__play" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4 L20 12 L7 20 Z" fill="currentColor"/></svg>
						<svg class="djd-player__pause" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="5" height="16" fill="currentColor"/><rect x="14" y="4" width="5" height="16" fill="currentColor"/></svg>
					</button>
				</div>
				<div class="djd-player__body">
					<div class="djd-player__top">
						<div class="djd-player__skip">
							<button type="button" class="djd-player__skipbtn" data-skip="-30" aria-label="Back 30 seconds">-30</button>
							<button type="button" class="djd-player__skipbtn" data-skip="30" aria-label="Forward 30 seconds">+30</button>
						</div>
					</div>
					<?php $title_lines = djdt_mix_title_lines( $mix['name'] ); ?>
					<div class="djd-player__title" title="<?php echo esc_attr( $mix['name'] ); ?>">
						<span class="djd-sr"><?php echo esc_html( $mix['name'] ); ?></span>
						<span class="djd-player__title-main" aria-hidden="true"><?php echo esc_html( $title_lines[0] ); ?></span>
						<?php if ( '' !== $title_lines[1] ) : ?>
							<span class="djd-player__title-sub" aria-hidden="true"><?php echo esc_html( $title_lines[1] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="djd-player__scrub" role="slider" tabindex="0" aria-label="Seek" aria-valuemin="0" aria-valuemax="<?php echo (int) $mix['length']; ?>" aria-valuenow="0">
						<?php djdt_inline_wave( 'djd-player__wave' ); ?>
						<div class="djd-player__progress"><?php djdt_inline_wave( '' ); ?></div>
						<div class="djd-player__tooltip" aria-hidden="true"></div>
					</div>
					<div class="djd-player__meta"><span class="djd-player__pos">0:00</span> / <span class="djd-player__dur"><?php echo esc_html( djdt_format_length( $mix['length'] ) ); ?></span></div>
					<div class="djd-player__links">
						<a href="<?php echo esc_url( 'https://www.mixcloud.com/' . rawurlencode( djdt_get( 'mixcloud_user' ) ) . '/' ); ?>" target="_blank" rel="noopener">Follow &amp; all mixes on Mixcloud ↗</a>
						<a class="djd-player__tracklist" href="<?php echo esc_url( $mix['url'] ); ?>" target="_blank" rel="noopener">Tracklist ↗</a>
					</div>
				</div>
			</div>
			<?php echo djdt_playlist_html( $mixes, $mix['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Splits a Mixcloud title into the player's two display lines (v1.13.0):
 * everything up to and including the first " - " is dropped (the "DJ
 * DigiMark Live" prefix every upload carries), and a trailing "( ... )"
 * becomes the second line. Either step simply does not apply when its
 * part is missing, so a title with neither shows whole on one line.
 * Returns array( line 1, line 2 or '' ). The full original title stays
 * the accessible name, see djdt_player_section().
 */
function djdt_mix_title_lines( $name ) {
	$name = trim( (string) $name );
	$main = $name;
	$pos  = strpos( $main, ' - ' );
	if ( false !== $pos ) {
		$main = trim( substr( $main, $pos + 3 ) );
	}
	$sub = '';
	if ( preg_match( '/^(.*\S)\s*\(([^()]+)\)\s*$/u', $main, $m ) ) {
		$main = trim( $m[1] );
		$sub  = trim( $m[2] );
	}
	if ( '' === $main ) {
		return array( $name, '' );
	}
	return array( $main, $sub );
}

/**
 * The 3 row playlist under the player (v1.14.0). Each row is a button that
 * carries everything hero.js needs to load that mix into the same player
 * (feed key, length, artwork, Mixcloud URL); the row whose key is already
 * loaded starts selected. Nothing at all with fewer than 2 mixes, a one row
 * list would only repeat the player above it.
 */
function djdt_playlist_html( $mixes, $current_key ) {
	if ( ! is_array( $mixes ) || count( $mixes ) < 2 ) {
		return '';
	}
	ob_start();
	?>
	<ol class="djd-playlist" aria-label="Choose a live set">
		<?php foreach ( $mixes as $m ) : ?>
			<?php
			$lines = djdt_mix_title_lines( $m['name'] );
			$on    = ( $m['key'] === $current_key );
			$len   = djdt_format_length( $m['length'] );
			$thumb = ! empty( $m['thumb'] ) ? $m['thumb'] : $m['picture'];
			?>
			<li class="djd-playlist__item">
				<button type="button" class="djd-playlist__row<?php echo $on ? ' is-current' : ''; ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
					data-feed="<?php echo esc_attr( $m['key'] ); ?>"
					data-length="<?php echo (int) $m['length']; ?>"
					data-name="<?php echo esc_attr( $m['name'] ); ?>"
					data-main="<?php echo esc_attr( $lines[0] ); ?>"
					data-sub="<?php echo esc_attr( $lines[1] ); ?>"
					data-picture="<?php echo esc_url( $m['picture'] ); ?>"
					data-url="<?php echo esc_url( $m['url'] ); ?>">
					<span class="djd-playlist__thumb" aria-hidden="true">
						<?php if ( $thumb ) : ?>
							<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" width="56" height="56">
						<?php endif; ?>
					</span>
					<span class="djd-playlist__text">
						<span class="djd-sr"><?php echo esc_html( $m['name'] ); ?></span>
						<span class="djd-playlist__main" aria-hidden="true"><?php echo esc_html( $lines[0] ); ?></span>
						<?php if ( '' !== $lines[1] ) : ?>
							<span class="djd-playlist__sub" aria-hidden="true"><?php echo esc_html( $lines[1] ); ?></span>
						<?php endif; ?>
					</span>
					<span class="djd-playlist__mark">Playing</span>
					<?php if ( $len ) : ?>
						<span class="djd-playlist__len"><span class="djd-sr">, length </span><?php echo esc_html( $len ); ?></span>
					<?php endif; ?>
				</button>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
	return ob_get_clean();
}

add_shortcode( 'djdigi_player', 'djdt_player_shortcode' );

/**
 * [djdigi_player] (v1.12.0): the same "Latest live set" markup
 * [djdigi_hero] has always output right after itself, as its own
 * shortcode now, for pages that want it somewhere other than immediately
 * after the hero (Home v2's new order: [djdigi_hero player="no"],
 * [djdigi_events], [djdigi_player], [djdigi_venues]). [djdigi_hero
 * player="no"] still works exactly as before, this is purely additive.
 * Enqueues djdt-hero (the stylesheet and script this section's own CSS
 * and its Mixcloud player logic live in) directly, rather than assuming
 * [djdigi_hero] is also on the page to have already done it: hero.js's
 * own early return only bails when neither this section nor the hero
 * itself is present (see hero.js), so this works standalone too.
 */
function djdt_player_shortcode() {
	if ( ! djdt_is_active() ) {
		return '';
	}
	wp_enqueue_style( 'djdt-hero' );
	wp_enqueue_script( 'djdt-hero' );
	return djdt_player_section();
}

/**
 * Renders a section heading's text as two spans (v1.12.0): every word but
 * the last in .djd-hw-outline, the last word alone in .djd-hw-solid (see
 * site.css for the look: an outline plus a solid cyan word). A one-word
 * heading is all solid, nothing to split. Shared by the events grid, the
 * player section and the venues heading, all three via this one function
 * rather than three independent copies of the same word-splitting logic.
 * No aria-label needed: the two spans sit in normal reading order with a
 * plain space between them, so the visible text and the accessible text
 * are the same thing.
 */
function djdt_heading_html( $text ) {
	$words = preg_split( '/\s+/', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) <= 1 ) {
		return '<span class="djd-hw-solid">' . esc_html( $text ) . '</span>';
	}
	$last = array_pop( $words );
	return '<span class="djd-hw-outline">' . esc_html( implode( ' ', $words ) ) . '</span> <span class="djd-hw-solid">' . esc_html( $last ) . '</span>';
}

/** Escapes a genre line and softens any " · " grouping dots inside it. */
function djdt_genre_item( $text ) {
	$parts = array_map( 'esc_html', array_map( 'trim', explode( '·', $text ) ) );
	return implode( '<span class="djd-genres__sub" aria-hidden="true">·</span>', $parts );
}

/* -------------------------------------------------------------------------
 * [djdigi_venues]: clubs and events Mark has played, plus the static
 * genre list underneath (reusing the old ticker's settings and sanitizing,
 * just rendered as plain static text now, not a scrolling marquee).
 * ---------------------------------------------------------------------- */

/**
 * Returns only the venues switched on, each with a render-ready logo src
 * (empty string if that row has no logo, or its attachment was deleted
 * since being picked, either way a valid state, not an error). An SVG
 * logo uses its own full file URL (wp_get_attachment_url): it has no
 * WordPress-generated sizes of its own for wp_get_attachment_image_url to
 * fall back to anyway, so asking for one directly is both correct and one
 * less thing to resolve.
 */
function djdt_venues() {
	$rows = djdt_get( 'venues' );
	$out  = array();
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			if ( empty( $row['on'] ) ) {
				continue;
			}
			$nd  = djdt_venue_name_details( $row );
			$id  = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$src = '';
			if ( $id > 0 && wp_attachment_is_image( $id ) ) {
				$src = ( 'image/svg+xml' === get_post_mime_type( $id ) )
					? wp_get_attachment_url( $id )
					: wp_get_attachment_image_url( $id, 'medium' );
			}
			$out[] = array(
				'src'        => (string) $src,
				'name'       => $nd['name'],
				'details'    => $nd['details'],
				'link'       => isset( $row['link'] ) ? $row['link'] : '',
				'logo_color' => isset( $row['logo_color'] ) ? ( 'original' === $row['logo_color'] ? 'original' : 'white' ) : djdt_logo_default_color( $id ),
			);
		}
	}
	return $out;
}

/**
 * One venue's markup: a logo with its name and details underneath, or just
 * the name (and details) in the display font if it has no logo. The logo
 * carries skip-lazy plus data-no-lazy="1" and no loading="lazy" of its
 * own, so Avada's lazy loading leaves it with its real src from the first
 * paint, the same reasoning as the always-visible hero photo: a lazily
 * loaded image inside a constantly moving row would either never trigger
 * (never scrolls into view on its own, it is already on screen moving
 * sideways) or load visibly late.
 */
function djdt_venue_item( $v ) {
	$logo_class = ( 'original' === $v['logo_color'] ) ? ' is-original' : '';
	if ( $v['src'] ) {
		$inner = '<span class="djd-venues__logo-wrap"><img class="djd-venues__logo skip-lazy' . esc_attr( $logo_class ) . '" src="' . esc_url( $v['src'] ) . '" alt="" data-no-lazy="1" decoding="async"></span>';
		if ( $v['name'] || $v['details'] ) {
			$inner .= '<span class="djd-venues__text">';
			if ( $v['name'] ) {
				$inner .= '<span class="djd-venues__caption">' . esc_html( $v['name'] ) . '</span>';
			}
			if ( $v['details'] ) {
				$inner .= '<span class="djd-venues__details">' . esc_html( $v['details'] ) . '</span>';
			}
			$inner .= '</span>';
		}
	} else {
		$inner = '<span class="djd-venues__text djd-venues__text--standalone">';
		$inner .= '<span class="djd-venues__name">' . esc_html( $v['name'] ) . '</span>';
		if ( $v['details'] ) {
			$inner .= '<span class="djd-venues__details">' . esc_html( $v['details'] ) . '</span>';
		}
		$inner .= '</span>';
	}
	if ( $v['link'] ) {
		echo '<a class="djd-venues__item" href="' . esc_url( $v['link'] ) . '" target="_blank" rel="noopener">' . $inner . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	} else {
		echo '<div class="djd-venues__item">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}

add_shortcode( 'djdigi_venues', 'djdt_venues_shortcode' );

function djdt_venues_shortcode() {
	if ( ! djdt_is_active() ) {
		return '';
	}
	$venues = djdt_venues();
	if ( ! $venues ) {
		return '';
	}
	wp_enqueue_style( 'djdt-venues' );
	wp_enqueue_script( 'djdt-venues' );

	$heading = (string) djdt_get( 'venues_heading' );
	$genres  = array_filter( array_map( 'trim', explode( "\n", (string) djdt_get( 'ticker' ) ) ) );

	// A handful of venues would make a thin, obviously-looping row; repeat
	// the set until it is wide enough to feel intentional, same idea as
	// the old ticker had for a short genre list.
	$padded   = $venues;
	$min_rows = 8;
	while ( count( $padded ) > 0 && count( $padded ) < $min_rows ) {
		$padded = array_merge( $padded, $venues );
	}

	ob_start();
	?>
	<section class="djd-venues" aria-label="<?php echo esc_attr( $heading ); ?>">
		<h2 class="djd-section-heading"><?php echo djdt_heading_html( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></h2>
		<div class="djd-venues__window">
			<div class="djd-venues__track">
				<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
					<div class="djd-venues__set"<?php echo $copy ? ' aria-hidden="true"' : ''; ?>>
						<?php foreach ( $padded as $v ) : djdt_venue_item( $v ); endforeach; ?>
					</div>
				<?php endfor; ?>
			</div>
		</div>
		<?php if ( $genres ) : ?>
			<div class="djd-genres">
				<?php foreach ( array_values( $genres ) as $i => $item ) : ?>
					<?php if ( $i > 0 ) : ?><span class="djd-genres__dot" aria-hidden="true">●</span><?php endif; ?>
					<span class="djd-genres__item"><?php echo djdt_genre_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
