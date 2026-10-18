<?php
/**
 * Plugin Name: DJ DigiMark Theme
 * Description: Site styling, Avada menu restyle, the [djdigi_hero] homepage hero, and [djdigi_venues] for djdigimark.com.
 * Version: 1.5.0
 * Author: DJ DigiMark
 * Update URI: https://github.com/MarketingMarkSolutions/djdigi-theme
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DJDT_VERSION', '1.5.0' );
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
 * Settings
 * ---------------------------------------------------------------------- */

function djdt_defaults() {
	return array(
		'live'           => 0,
		'mixcloud_user'  => 'djdigimark',
		'mix_mode'       => 'latest',
		'mix_url'        => '',
		'hero_photos'    => array(),
		'sets_url'       => '/upcoming-events/',
		'videos_url'     => '/video-gallery/',
		// Reused for the static genre list below the venues card (v1.3.0);
		// the key is still called "ticker" since it is the same setting,
		// just no longer a scrolling marquee. Do not rename the key, that
		// would silently drop whatever Mark already has saved.
		'ticker'         => "House\nEDM\nTop 40 Remixes\n70s · 80s · 90s · 00s Dance\nPride/Queer Pop & Dance\nLive Video DJ Sets",
		'venues'         => array(),
		'venues_heading' => "Where I've played",
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
	add_menu_page( 'DigiMark Theme', 'DigiMark', 'manage_options', 'djdigi-theme', 'djdt_render_settings_theme', 'dashicons-format-audio' );
	add_submenu_page( 'djdigi-theme', 'Theme Settings', 'Theme Settings', 'manage_options', 'djdigi-theme', 'djdt_render_settings_theme' );
	add_submenu_page( 'djdigi-theme', 'Hero Photos', 'Hero Photos', 'manage_options', 'djdigi-theme-photos', 'djdt_render_settings_photos' );
	add_submenu_page( 'djdigi-theme', 'Venues', 'Venues', 'manage_options', 'djdigi-theme-venues', 'djdt_render_settings_venues' );
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
 * add_menu_page's own page and the two add_submenu_page screens each get
 * their own hook suffix (WordPress convention: "{parent_slug}_page_{slug}",
 * except the first one, which is "toplevel_page_{slug}").
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$our_hooks = array( 'toplevel_page_djdigi-theme', 'djdigi-theme_page_djdigi-theme-photos', 'djdigi-theme_page_djdigi-theme-venues' );
	if ( ! in_array( $hook, $our_hooks, true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'djdt-admin', DJDT_URL . 'assets/css/admin.css', array(), djdt_ver( 'assets/css/admin.css' ) );
	wp_enqueue_script( 'djdt-admin', DJDT_URL . 'assets/js/admin.js', array( 'jquery' ), djdt_ver( 'assets/js/admin.js' ), true );
	wp_localize_script( 'djdt-admin', 'djdtAdmin', array(
		'photos' => array_values( djdt_get( 'hero_photos' ) ),
		'venues' => array_values( array_map( 'djdt_venue_admin_row', djdt_get( 'venues' ) ) ),
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
 * DJDT_LOGO_COLOR_RECIPE bump (v1.4.1), the same reasoning as the
 * duotone regeneration above: a venue saved before this version always
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
		$out['ticker']        = sanitize_textarea_field( isset( $in['ticker'] ) ? $in['ticker'] : $d['ticker'] );
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
		// Bake each photo's duotone copy now, while it is already on hand,
		// rather than on every front end page load. A photo removed from
		// rotation keeps its baked copy (it is cheap, tied to the
		// attachment, and removing it here would just mean regenerating it
		// if the same photo comes back).
		foreach ( $out['hero_photos'] as $row ) {
			djdt_ensure_duotone( $row['id'] );
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
			<th scope="row"><label for="djdt-sets">"Upcoming sets" button link</label></th>
			<td><input id="djdt-sets" class="regular-text" name="<?php echo esc_attr( $n ); ?>[sets_url]" value="<?php echo esc_attr( $s['sets_url'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-videos">"Remix videos" button link</label></th>
			<td><input id="djdt-videos" class="regular-text" name="<?php echo esc_attr( $n ); ?>[videos_url]" value="<?php echo esc_attr( $s['videos_url'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="djdt-ticker">Genres ("What I play")</label></th>
			<td>
				<textarea id="djdt-ticker" class="large-text" rows="7" name="<?php echo esc_attr( $n ); ?>[ticker]"><?php echo esc_textarea( $s['ticker'] ); ?></textarea>
				<p class="description">Shown as a static list below the venues card. One item per line. Use " · " inside a line to group things into one item (like the decades).</p>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<p>Hero shortcode: <code>[djdigi_hero]</code>. Put it alone in a 100% width container with no padding, at the top of the page. Add <code>?intro=1</code> to any hero page URL to force the full opening sequence while testing.</p>
				<p>Venues shortcode: <code>[djdigi_venues]</code>. Put it right below the hero shortcode on Home v2.</p>
				<p><strong>After saving:</strong> if a caching plugin or your host's cache is active, purge the cached copy of any page carrying <code>[djdigi_hero]</code> or <code>[djdigi_venues]</code>, or changes here can keep showing the old version. See the plugin's CLAUDE.md for exact steps.</p>
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

	wp_register_style( 'djdt-venues', DJDT_URL . 'assets/css/venues.css', array( 'djdt-site' ), djdt_ver( 'assets/css/venues.css' ) );
	wp_register_script( 'djdt-venues', DJDT_URL . 'assets/js/venues.js', array(), djdt_ver( 'assets/js/venues.js' ), true );

	// Menu and footer dock run on every themed page, hero or not.
	wp_enqueue_script( 'djdt-menu', DJDT_URL . 'assets/js/menu.js', array(), djdt_ver( 'assets/js/menu.js' ), true );

	wp_enqueue_script( 'djdt-dock', DJDT_URL . 'assets/js/dock.js', array(), djdt_ver( 'assets/js/dock.js' ), true );
	wp_add_inline_script( 'djdt-dock', 'window.djdtDock = ' . wp_json_encode( array(
		'mixcloudUrl' => 'https://www.mixcloud.com/' . rawurlencode( djdt_get( 'mixcloud_user' ) ) . '/',
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
 * Hero photo duotone baking (v1.4.0, color fixed in v1.4.1, edge fades
 * moved back out to CSS in v1.5.0)
 * Generated once, when a photo is saved into Hero Photos (djdt_sanitize's
 * 'photos' branch), not on every front end page load: grayscale, then a
 * violet to cyan duotone, baked straight into a plain JPEG. The front end
 * then serves a plain photo with no CSS filter, no mix-blend-mode and no
 * mask-image on the photo itself, all three of which were forcing a
 * repaint every frame together with the Ken Burns zoom (see CLAUDE.md,
 * "Smoothness" / why the hero photo and the venue strip were choppy).
 * Falls back to the unprocessed original if neither Imagick nor GD is
 * available, which would be unusual on a normal WordPress host.
 *
 * v1.4.1 baked the left and bottom edge fades into the file's own alpha
 * channel (a WebP) to fix a seam where the glow behind the photo showed
 * through a flat CSS fade. That moved the seam rather than fixing it,
 * found live in v1.5.0: object-fit: cover plus the Ken Burns scale crops
 * or pushes the baked-in faded strip outside the visible panel at most
 * screen sizes, since where the fade physically sits in the file has no
 * fixed relationship to where the panel's own edge ends up on screen.
 * The fade is back to being a CSS overlay on the panel itself (hero.css),
 * which always covers the same edge of the panel regardless of what the
 * photo content underneath is doing, and the glows now sit above the
 * photo instead of behind it (see "Smoothness" in CLAUDE.md), so nothing
 * baked into the photo file needs to account for the glow at all anymore.
 * ---------------------------------------------------------------------- */

define( 'DJDT_DUOTONE_VIOLET', '#5a2e91' );
define( 'DJDT_DUOTONE_CYAN', '#24a9e0' );
define( 'DJDT_DUOTONE_MAX_DIM', 2000 );
// Bump this whenever djdt_bake_duotone_* changes in a way that means an
// already baked file needs regenerating to look right, so a recipe fix
// reaches photos Mark already picked without him needing to resave Hero
// Photos (see the admin_init hook below).
define( 'DJDT_DUOTONE_RECIPE', 3 );

/**
 * Returns the baked duotone URL for a hero photo attachment, generating
 * it first if it does not exist yet (a photo saved before v1.4.0, or
 * whose baked file was somehow lost, self heals on the next page load
 * that needs it, no resave required). '' if baking was not possible.
 */
function djdt_duotone_url( $id ) {
	$upload_dir = wp_upload_dir();
	$jpg_meta   = get_post_meta( $id, '_djdt_duotone_jpg', true );
	if ( $jpg_meta && file_exists( trailingslashit( $upload_dir['basedir'] ) . $jpg_meta ) ) {
		return trailingslashit( $upload_dir['baseurl'] ) . $jpg_meta;
	}
	return djdt_ensure_duotone( $id );
}

/** Generates (or regenerates) the baked duotone copy for one attachment. Returns its URL, or '' if baking was not possible. */
function djdt_ensure_duotone( $id ) {
	$id = (int) $id;
	if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
		return '';
	}
	$src_path = get_attached_file( $id );
	if ( ! $src_path || ! file_exists( $src_path ) ) {
		return '';
	}
	$upload_dir = wp_upload_dir();
	$rel_dir    = trailingslashit( dirname( str_replace( trailingslashit( $upload_dir['basedir'] ), '', $src_path ) ) );
	$rel_jpg    = $rel_dir . 'djdt-duotone-' . $id . '.jpg';
	$abs_jpg    = trailingslashit( $upload_dir['basedir'] ) . $rel_jpg;

	$ok = false;
	if ( class_exists( 'Imagick' ) ) {
		$ok = djdt_bake_duotone_imagick( $src_path, $abs_jpg );
	}
	if ( ! $ok && function_exists( 'imagecreatetruecolor' ) ) {
		$ok = djdt_bake_duotone_gd( $src_path, $abs_jpg );
	}

	if ( ! $ok ) {
		delete_post_meta( $id, '_djdt_duotone_jpg' );
		// Leftovers from the v1.4.1 WebP recipe, no longer written or read.
		delete_post_meta( $id, '_djdt_duotone_webp' );
		return '';
	}
	update_post_meta( $id, '_djdt_duotone_jpg', $rel_jpg );
	delete_post_meta( $id, '_djdt_duotone_webp' );
	return trailingslashit( $upload_dir['baseurl'] ) . $rel_jpg;
}

/**
 * Imagick: grayscale, back to sRGB (the v1.4.1 color bug: clutImage ran
 * while the image was still in the GRAY colorspace, which has no color
 * channels left for the gradient to write into, so every baked photo
 * came out pure gray, R = G = B, no violet or cyan at all), then the
 * duotone clut. No edge fade baked in, see the note above the recipe
 * version constant: that is a CSS overlay on the panel now (hero.css).
 */
function djdt_bake_duotone_imagick( $src_path, $dest_jpg ) {
	try {
		$img = new Imagick( $src_path );
		if ( method_exists( $img, 'autoOrientImage' ) ) {
			$img->autoOrientImage();
		}
		$w   = $img->getImageWidth();
		$h   = $img->getImageHeight();
		$max = max( $w, $h );
		if ( $max > DJDT_DUOTONE_MAX_DIM ) {
			$scale = DJDT_DUOTONE_MAX_DIM / $max;
			$w     = (int) round( $w * $scale );
			$h     = (int) round( $h * $scale );
			$img->resizeImage( $w, $h, Imagick::FILTER_LANCZOS, 1 );
		}
		$img->transformImageColorspace( Imagick::COLORSPACE_GRAY );
		$img->contrastImage( true );
		// Back to sRGB before the clut: a grayscale-colorspace image has
		// only one real channel, so clutImage has nowhere to write violet
		// or cyan into and the result stays gray regardless of the
		// gradient. This line is the v1.4.1 fix.
		$img->transformImageColorspace( Imagick::COLORSPACE_SRGB );
		// The standard Imagick duotone recipe: a 1px tall gradient from
		// violet to cyan used as a lookup table against the image's own
		// intensity (still grayscale in effect, R = G = B, just with
		// color channels available again), so black maps to violet, white
		// to cyan, and every gray level in between to the matching point
		// on the gradient.
		$gradient = new Imagick();
		$gradient->newPseudoImage( 256, 1, 'gradient:' . DJDT_DUOTONE_VIOLET . '-' . DJDT_DUOTONE_CYAN );
		$img->clutImage( $gradient );
		$gradient->destroy();
		$img->setImageFormat( 'jpeg' );
		$img->setImageCompressionQuality( 86 );
		wp_mkdir_p( dirname( $dest_jpg ) );
		$img->writeImage( $dest_jpg );
		$img->destroy();
		return true;
	} catch ( Exception $e ) {
		return false;
	}
}

/** GD: the same recipe as Imagick, done by hand with a lookup table (GD has no clut of its own). No edge fade, same reasoning as the Imagick version above. */
function djdt_bake_duotone_gd( $src_path, $dest_jpg ) {
	$info = @getimagesize( $src_path );
	if ( ! $info ) {
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

	$w   = imagesx( $src );
	$h   = imagesy( $src );
	$max = max( $w, $h );
	if ( $max > DJDT_DUOTONE_MAX_DIM ) {
		$scale   = DJDT_DUOTONE_MAX_DIM / $max;
		$nw      = max( 1, (int) round( $w * $scale ) );
		$nh      = max( 1, (int) round( $h * $scale ) );
		$resized = imagecreatetruecolor( $nw, $nh );
		imagecopyresampled( $resized, $src, 0, 0, 0, 0, $nw, $nh, $w, $h );
		imagedestroy( $src );
		$src = $resized;
		$w   = $nw;
		$h   = $nh;
	}

	imagefilter( $src, IMG_FILTER_GRAYSCALE );

	list( $vr, $vg, $vb ) = djdt_hex_rgb( DJDT_DUOTONE_VIOLET );
	list( $cr, $cg, $cb ) = djdt_hex_rgb( DJDT_DUOTONE_CYAN );

	// A 256 entry lookup table (lerp violet to cyan by gray level), built
	// once rather than recomputed per pixel: this loop is the whole cost
	// of the bake, so it is worth keeping the per-pixel part cheap.
	$lut = array();
	for ( $i = 0; $i < 256; $i++ ) {
		$t        = $i / 255;
		$lut[ $i ] = array(
			(int) round( $vr + ( $cr - $vr ) * $t ),
			(int) round( $vg + ( $cg - $vg ) * $t ),
			(int) round( $vb + ( $cb - $vb ) * $t ),
		);
	}

	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			// After imagefilter(IMG_FILTER_GRAYSCALE) the R, G and B
			// channels are equal, so any one of them is the gray level.
			$gray = imagecolorat( $src, $x, $y ) & 0xFF;
			$c    = $lut[ $gray ];
			// A packed true color int works directly with imagesetpixel on
			// a true color image; skips an imagecolorallocate call per
			// pixel, which matters here given the pixel count.
			imagesetpixel( $src, $x, $y, ( $c[0] << 16 ) | ( $c[1] << 8 ) | $c[2] );
		}
	}

	wp_mkdir_p( dirname( $dest_jpg ) );
	$ok = imagejpeg( $src, $dest_jpg, 86 );
	imagedestroy( $src );
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
 * Regenerates every already saved hero photo's baked copies once per
 * DJDT_DUOTONE_RECIPE bump, so a recipe fix (like the v1.4.1 color bug)
 * reaches photos Mark already picked without him needing to resave Hero
 * Photos. Runs for an admin on any admin page load, not just the Hero
 * Photos screen, since otherwise it would never run at all until he
 * happened to visit that one screen.
 */
add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( (int) get_option( 'djdt_duotone_recipe_done', 0 ) >= DJDT_DUOTONE_RECIPE ) {
		return;
	}
	$rows = djdt_get( 'hero_photos' );
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( $id > 0 ) {
				djdt_ensure_duotone( $id );
			}
		}
	}
	update_option( 'djdt_duotone_recipe_done', DJDT_DUOTONE_RECIPE );
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
 */

add_filter( 'body_class', function ( $classes ) {
	if ( ! djdt_is_active() ) {
		return $classes;
	}
	$classes[] = 'djdigi-theme';
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && has_shortcode( $post->post_content, 'djdigi_hero' ) ) {
			$classes[] = 'djdigi-has-hero';
			if ( shortcode_exists( 'djdigi_next_up' ) ) {
				$classes[] = 'djdigi-hero-strip';
			}
		}
	}
	return $classes;
} );

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
 * Returns the hero player's mix data, cached for 6 hours: key, name, length
 * (seconds), picture (artwork URL) and url (the mix's own Mixcloud page).
 */
function djdt_get_mix() {
	$cached = get_transient( 'djdt_mix' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$mix  = null;
	$mode = djdt_get( 'mix_mode' );
	if ( 'pinned' === $mode && djdt_get( 'mix_url' ) ) {
		$path = wp_parse_url( djdt_get( 'mix_url' ), PHP_URL_PATH );
		if ( $path ) {
			$key  = '/' . trim( $path, '/' ) . '/';
			$data = djdt_mixcloud_get( $key );
			$mix  = $data ? djdt_mix_from_api( $key, $data ) : null;
		}
	} else {
		$user = djdt_get( 'mixcloud_user' );
		$data = djdt_mixcloud_get( '/' . rawurlencode( $user ) . '/cloudcasts/?limit=1' );
		if ( $data && ! empty( $data['data'][0]['key'] ) ) {
			$c   = $data['data'][0];
			$mix = djdt_mix_from_api( $c['key'], $c );
		}
	}
	// Cache failures briefly so a Mixcloud outage never slows every page.
	set_transient( 'djdt_mix', $mix ? $mix : array(), $mix ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $mix ? $mix : array();
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
	return array(
		'key'     => $key,
		'name'    => isset( $data['name'] ) ? $data['name'] : '',
		'length'  => isset( $data['audio_length'] ) ? (int) $data['audio_length'] : 0,
		'picture' => $picture,
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
			$duotone = djdt_duotone_url( $id );
			$src     = $duotone ? $duotone : wp_get_attachment_image_url( $id, 'full' );
			if ( ! $src ) {
				continue;
			}
			$out[] = array(
				'src' => $src,
				// A baked duotone copy has no WordPress generated responsive
				// sizes of its own, only the one file; it is already capped
				// at DJDT_DUOTONE_MAX_DIM, plenty for how large the photo
				// panel ever renders, so no srcset is needed for it.
				'srcset' => $duotone ? '' : (string) wp_get_attachment_image_srcset( $id, 'full' ),
				'x'      => isset( $row['x'] ) ? (float) $row['x'] : 50,
				'y'      => isset( $row['y'] ) ? (float) $row['y'] : 50,
			);
		}
	}
	if ( empty( $out ) ) {
		$out[] = array(
			'src'    => DJDT_URL . 'assets/img/hero-photo-duotone.jpg',
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

function djdt_hero_shortcode() {
	if ( ! djdt_is_active() ) {
		return '';
	}
	wp_enqueue_style( 'djdt-hero' );
	wp_enqueue_script( 'djdt-hero' );

	$photos = djdt_hero_photos();
	$mix    = djdt_get_mix();
	$sets   = djdt_get( 'sets_url' );
	$videos = djdt_get( 'videos_url' );

	$next_up = shortcode_exists( 'djdigi_next_up' ) ? do_shortcode( '[djdigi_next_up]' ) : '';

	ob_start();
	?>
	<section class="djd-hero" aria-label="DJ DigiMark" data-photo-count="<?php echo (int) count( $photos ); ?>">
		<div class="djd-hero__photos">
			<?php foreach ( $photos as $i => $p ) : ?>
				<div class="djd-hero__photo-frame<?php echo ( 0 === $i ) ? ' is-active is-first' : ''; ?>">
					<img
						class="djd-hero__photo"
						src="<?php echo esc_url( $p['src'] ); ?>"
						<?php if ( ! empty( $p['srcset'] ) ) : ?>srcset="<?php echo esc_attr( $p['srcset'] ); ?>" sizes="60vw"<?php endif; ?>
						style="object-position: <?php echo esc_attr( $p['x'] ); ?>% <?php echo esc_attr( $p['y'] ); ?>%"
						alt="DJ DigiMark mixing under club lights"
						<?php echo ( 0 === $i ) ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
					>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- The "room light": two full glow sets, one lit at a time, crossfading
		opacity between color sets as the photo changes (hero.js, "room light").
		Sits above the photo panel, see CLAUDE.md "Smoothness". -->
		<div class="djd-hero__glows" aria-hidden="true">
			<div class="djd-hero__glow-layer is-lit" data-set="a">
				<div class="djd-hero__glow djd-hero__glow--a djd-in-glow"></div>
				<div class="djd-hero__glow djd-hero__glow--b djd-in-glow"></div>
				<div class="djd-hero__glow djd-hero__glow--c djd-in-glow"></div>
			</div>
			<div class="djd-hero__glow-layer" data-set="b">
				<div class="djd-hero__glow djd-hero__glow--a djd-in-glow"></div>
				<div class="djd-hero__glow djd-hero__glow--b djd-in-glow"></div>
				<div class="djd-hero__glow djd-hero__glow--c djd-in-glow"></div>
			</div>
		</div>

		<?php if ( $next_up ) : ?>
			<div class="djd-hero__strip djd-in-ui"><?php echo $next_up; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted shortcode output ?></div>
		<?php endif; ?>

		<div class="djd-hero__inner">
			<div class="djd-hero__stage">
				<h1 class="djd-hero__name" aria-label="DJ DigiMark">
					<span class="djd-hero__mask djd-hero__mask--dj" aria-hidden="true"><span class="djd-hero__dj djd-in-dj">DJ</span></span>
					<span class="djd-hero__digimark" aria-hidden="true">
						<span class="djd-hero__mask djd-hero__mask--echo"><span class="djd-hero__echo"><?php echo djdt_split_letters( 'DigiMark' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></span></span>
						<span class="djd-hero__mask djd-hero__mask--main"><span class="djd-hero__main"><?php echo djdt_split_letters( 'DigiMark' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></span></span>
					</span>
				</h1>
			</div>

			<div class="djd-hero__row">
				<div class="djd-hero__copy">
					<p class="djd-hero__slogan">Bringing positive vibes to the dance <span class="djd-nowrap">floor <span class="djd-eq" role="img" aria-label="Rainbow flag and music"><span></span><span></span><span></span><span></span><span></span><span></span></span></span></p>
					<div class="djd-hero__buttons">
						<a class="djd-btn djd-btn--solid" href="<?php echo esc_url( $sets ); ?>">Upcoming sets</a>
						<a class="djd-btn djd-btn--line" href="<?php echo esc_url( $videos ); ?>">Remix videos</a>
					</div>
				</div>

				<?php if ( ! empty( $mix['key'] ) ) : ?>
					<div class="djd-player<?php echo $mix['picture'] ? ' has-artwork' : ''; ?>" data-feed="<?php echo esc_attr( $mix['key'] ); ?>" data-length="<?php echo (int) $mix['length']; ?>">
						<div class="djd-player__art">
							<?php if ( $mix['picture'] ) : ?>
								<img class="djd-player__artimg" src="<?php echo esc_url( $mix['picture'] ); ?>" alt="">
							<?php endif; ?>
							<button type="button" class="djd-player__btn" aria-label="<?php echo esc_attr( 'Play ' . $mix['name'] ); ?>" data-label="<?php echo esc_attr( $mix['name'] ); ?>">
								<svg class="djd-player__play" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4 L20 12 L7 20 Z" fill="currentColor"/></svg>
								<svg class="djd-player__pause" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="5" height="16" fill="currentColor"/><rect x="14" y="4" width="5" height="16" fill="currentColor"/></svg>
							</button>
						</div>
						<div class="djd-player__body">
							<div class="djd-player__top">
								<span class="djd-player__status">Latest live set</span>
								<div class="djd-player__skip">
									<button type="button" class="djd-player__skipbtn" data-skip="-30" aria-label="Back 30 seconds">-30</button>
									<button type="button" class="djd-player__skipbtn" data-skip="30" aria-label="Forward 30 seconds">+30</button>
								</div>
							</div>
							<div class="djd-player__title"><?php echo esc_html( $mix['name'] ); ?></div>
							<div class="djd-player__scrub" role="slider" tabindex="0" aria-label="Seek" aria-valuemin="0" aria-valuemax="<?php echo (int) $mix['length']; ?>" aria-valuenow="0">
								<?php djdt_inline_wave( 'djd-player__wave' ); ?>
								<div class="djd-player__progress"><?php djdt_inline_wave( '' ); ?></div>
								<div class="djd-player__tooltip" aria-hidden="true"></div>
							</div>
							<div class="djd-player__meta"><span class="djd-player__pos">0:00</span> / <span class="djd-player__dur"><?php echo esc_html( djdt_format_length( $mix['length'] ) ); ?></span></div>
							<div class="djd-player__links">
								<a href="<?php echo esc_url( 'https://www.mixcloud.com/' . rawurlencode( djdt_get( 'mixcloud_user' ) ) . '/' ); ?>" target="_blank" rel="noopener">Follow &amp; all mixes on Mixcloud ↗</a>
								<a href="<?php echo esc_url( $mix['url'] ); ?>" target="_blank" rel="noopener">Tracklist ↗</a>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/** Escapes a genre line and softens any " · " grouping dots inside it. */
function djdt_genre_item( $text ) {
	$parts = array_map( 'esc_html', array_map( 'trim', explode( '·', $text ) ) );
	return implode( '<span class="djd-genres__sub" aria-hidden="true">·</span>', $parts );
}

/**
 * Splits a word into one span per letter, each carrying its index in --i so
 * CSS can stagger something per letter (the hero name's entrance, or the
 * venues heading's beam sweep, each with their own class and animation).
 * aria-hidden: the parent heading already carries the readable text in its
 * own aria-label.
 *
 * @param string $word  Text to split. Spaces become their own (empty
 *                       looking but real) span, which is fine, inline-block
 *                       spans still lay out like normal inline text.
 * @param string $class Class on each letter span.
 */
function djdt_split_letters( $word, $class = 'djd-letter' ) {
	$out = '';
	$i   = 0;
	foreach ( preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		if ( '' === trim( $ch ) ) {
			// A space rendered as its own inline-block span collapses to
			// zero width (confirmed live, v1.4.1, "Wherel'veplayed"): an
			// inline-block whose only content is whitespace trims that
			// whitespace within its own formatting context, in every
			// browser. Plain text has no such box to collapse, so a space
			// is output as-is instead of wrapped. Not staggered (no --i),
			// so the letter index below only counts actual letters.
			$out .= esc_html( $ch );
			continue;
		}
		$out .= '<span class="' . esc_attr( $class ) . '" style="--i:' . (int) $i . '">' . esc_html( $ch ) . '</span>';
		$i++;
	}
	return $out;
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
		<h2 class="djd-venues__heading"><?php echo djdt_split_letters( $heading, 'djd-venues__letter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></h2>
		<div class="djd-venues__card">
			<div class="djd-venues__beam" aria-hidden="true"></div>
			<div class="djd-venues__window">
				<div class="djd-venues__track">
					<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
						<div class="djd-venues__set"<?php echo $copy ? ' aria-hidden="true"' : ''; ?>>
							<?php foreach ( $padded as $v ) : djdt_venue_item( $v ); endforeach; ?>
						</div>
					<?php endfor; ?>
				</div>
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
