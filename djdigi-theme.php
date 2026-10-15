<?php
/**
 * Plugin Name: DJ DigiMark Theme
 * Description: Site styling, Avada menu restyle and the [djdigi_hero] homepage hero for djdigimark.com.
 * Version: 1.2.1
 * Author: DJ DigiMark
 * Update URI: https://github.com/MarketingMarkSolutions/djdigi-theme
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DJDT_VERSION', '1.2.1' );
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
		'live'          => 0,
		'mixcloud_user' => 'djdigimark',
		'mix_mode'      => 'latest',
		'mix_url'       => '',
		'hero_photos'   => array(),
		'sets_url'      => '/upcoming-events/',
		'videos_url'    => '/video-gallery/',
		'ticker'        => "House\nEDM\nTop 40 Remixes\n70s · 80s · 90s · 00s Dance\nPride/Queer Pop & Dance\nLive Video DJ Sets",
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

add_action( 'admin_menu', function () {
	add_options_page( 'DigiMark Theme', 'DigiMark Theme', 'manage_options', 'djdigi-theme', 'djdt_render_settings' );
} );

add_action( 'admin_init', function () {
	register_setting( 'djdigi_theme', DJDT_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'djdt_sanitize',
		'default'           => djdt_defaults(),
	) );
} );

/**
 * Only our own settings screen needs the media frame and the picker script.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'settings_page_djdigi-theme' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'djdt-admin', DJDT_URL . 'assets/css/admin.css', array(), djdt_ver( 'assets/css/admin.css' ) );
	wp_enqueue_script( 'djdt-admin', DJDT_URL . 'assets/js/admin.js', array( 'jquery' ), djdt_ver( 'assets/js/admin.js' ), true );
	wp_localize_script( 'djdt-admin', 'djdtAdmin', array(
		'photos' => array_values( djdt_get( 'hero_photos' ) ),
	) );
} );

function djdt_sanitize( $in ) {
	$d   = djdt_defaults();
	$out = array();
	$out['live']          = empty( $in['live'] ) ? 0 : 1;
	$out['mixcloud_user'] = sanitize_title( isset( $in['mixcloud_user'] ) ? $in['mixcloud_user'] : $d['mixcloud_user'] );
	$out['mix_mode']      = ( isset( $in['mix_mode'] ) && 'pinned' === $in['mix_mode'] ) ? 'pinned' : 'latest';
	$out['mix_url']       = esc_url_raw( isset( $in['mix_url'] ) ? trim( $in['mix_url'] ) : '' );
	$out['sets_url']      = sanitize_text_field( isset( $in['sets_url'] ) ? $in['sets_url'] : $d['sets_url'] );
	$out['videos_url']    = sanitize_text_field( isset( $in['videos_url'] ) ? $in['videos_url'] : $d['videos_url'] );
	$out['ticker']        = sanitize_textarea_field( isset( $in['ticker'] ) ? $in['ticker'] : $d['ticker'] );

	// Hero photos: one hidden field carrying JSON built by admin.js (order, focal points).
	// Each row is re-validated here rather than trusted, same as anything else arriving by POST.
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

	delete_transient( 'djdt_mix' );
	return $out;
}

function djdt_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = djdt_settings();
	$n = DJDT_OPTION;
	?>
	<div class="wrap">
		<h1>DigiMark Theme</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'djdigi_theme' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Live for everyone</th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[live]" value="1" <?php checked( $s['live'], 1 ); ?>> Show the new design to all visitors</label>
						<p class="description">Unchecked: only logged-in administrators see the new design and the hero. Visitors see the site as it is today.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Hero photos</th>
					<td>
						<div id="djdt-hero-photos" class="djdt-photo-grid"></div>
						<p>
							<button type="button" class="button" id="djdt-add-photos">Add photos</button>
						</p>
						<input type="hidden" id="djdt-hero-photos-json" name="<?php echo esc_attr( $n ); ?>[hero_photos_json]" value="<?php echo esc_attr( wp_json_encode( array_values( $s['hero_photos'] ) ) ); ?>">
						<p class="description">Drag a photo to reorder. Click a photo once to pick its focal point, then click where the subject is. The x removes it from rotation only, it stays in the Media Library. Leave empty to use the built in photo. One photo plays with no rotation. 3 to 4 photos also appear as a small photo wall once a visitor scrolls past the hero.</p>
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
					<th scope="row"><label for="djdt-ticker">Ticker items</label></th>
					<td>
						<textarea id="djdt-ticker" class="large-text" rows="7" name="<?php echo esc_attr( $n ); ?>[ticker]"><?php echo esc_textarea( $s['ticker'] ); ?></textarea>
						<p class="description">One item per line. Use " · " inside a line to group things into one item (like the decades).</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>Hero shortcode: <code>[djdigi_hero]</code>. Put it alone in a 100% width container with no padding, at the top of the page. Add <code>?intro=1</code> to any hero page URL to force the full opening sequence while testing.</p>
		<p><strong>After saving:</strong> if a caching plugin or your host's cache is active, purge the cached copy of any page carrying <code>[djdigi_hero]</code>, or changes here (hero photos especially) can keep showing the old version. See the plugin's CLAUDE.md for exact steps.</p>
	</div>
	<?php
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
 * Returns an array of render-ready photos: src, srcset, sizes, x, y.
 * Always returns at least one row (the bundled photo as a fallback).
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
			$src = wp_get_attachment_image_url( $id, 'full' );
			if ( ! $src ) {
				continue;
			}
			$out[] = array(
				'src'    => $src,
				'srcset' => (string) wp_get_attachment_image_srcset( $id, 'full' ),
				'x'      => isset( $row['x'] ) ? (float) $row['x'] : 50,
				'y'      => isset( $row['y'] ) ? (float) $row['y'] : 50,
			);
		}
	}
	if ( empty( $out ) ) {
		$out[] = array(
			'src'    => DJDT_URL . 'assets/img/hero-photo.jpg',
			'srcset' => '',
			'x'      => 50,
			'y'      => 50,
		);
	}
	return $out;
}

/**
 * Fixed scatter slots for the scroll photo wall: large, loosely placed
 * tiles rather than a dense grid, since there are usually only 3 or 4
 * photos. Each tile anchors to one corner outside the shrinking card
 * (hero.css computes its actual position from the corner and the live
 * shrink progress, so it always tracks the card's real edges rather than
 * a fixed guess); w/h/r are its size and a little rotation for variety.
 * Bottom corners come first: they sit in the most reliably clear margin
 * (below the card, where nothing else renders), so a count of 2 or 3
 * still looks intentional rather than lopsided.
 */
function djdt_wall_slots() {
	return array(
		array( 'corner' => 'bl', 'w' => '180px', 'h' => '230px', 'r' => '4deg' ),
		array( 'corner' => 'br', 'w' => '200px', 'h' => '250px', 'r' => '-5deg' ),
		array( 'corner' => 'tl', 'w' => '190px', 'h' => '240px', 'r' => '-6deg' ),
		array( 'corner' => 'tr', 'w' => '170px', 'h' => '220px', 'r' => '5deg' ),
	);
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

	$items = array_filter( array_map( 'trim', explode( "\n", (string) djdt_get( 'ticker' ) ) ) );

	$wall_slots = djdt_wall_slots();
	$wall_count = min( count( $photos ), count( $wall_slots ) );

	ob_start();
	?>
	<div class="djd-hero-scene">
	<section class="djd-hero" aria-label="DJ DigiMark" data-photo-count="<?php echo (int) count( $photos ); ?>">
		<div class="djd-hero__glows" aria-hidden="true">
			<div class="djd-hero__glow djd-hero__glow--a djd-in-glow"></div>
			<div class="djd-hero__glow djd-hero__glow--b djd-in-glow"></div>
			<div class="djd-hero__glow djd-hero__glow--c djd-in-glow"></div>
		</div>

		<div class="djd-hero__photos">
			<?php foreach ( $photos as $i => $p ) : ?>
				<img
					class="djd-hero__photo<?php echo ( 0 === $i ) ? ' is-active' : ''; ?>"
					src="<?php echo esc_url( $p['src'] ); ?>"
					<?php if ( $p['srcset'] ) : ?>srcset="<?php echo esc_attr( $p['srcset'] ); ?>" sizes="60vw"<?php endif; ?>
					style="object-position: <?php echo esc_attr( $p['x'] ); ?>% <?php echo esc_attr( $p['y'] ); ?>%"
					alt="DJ DigiMark mixing under club lights"
					<?php echo ( 0 === $i ) ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
				>
			<?php endforeach; ?>
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

		<?php if ( $items ) : ?>
			<div class="djd-ticker" aria-label="What I play">
				<div class="djd-ticker__bulbs" aria-hidden="true"></div>
				<div class="djd-ticker__window">
					<div class="djd-ticker__track">
						<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
							<span class="djd-ticker__set"<?php echo $copy ? ' aria-hidden="true"' : ''; ?>>
								<?php foreach ( array_values( $items ) as $i => $item ) : ?>
									<span class="djd-ticker__item<?php echo ( 2 === $i % 3 ) ? ' is-accent' : ''; ?>"><?php echo djdt_ticker_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></span>
									<span class="djd-ticker__dot" aria-hidden="true">●</span>
								<?php endforeach; ?>
							</span>
						<?php endfor; ?>
					</div>
				</div>
				<div class="djd-ticker__bulbs" aria-hidden="true"></div>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $wall_count > 1 ) : ?>
		<div class="djd-hero__wall" aria-hidden="true">
			<?php for ( $i = 0; $i < $wall_count; $i++ ) : $slot = $wall_slots[ $i ]; $p = $photos[ $i ]; ?>
				<div class="djd-hero__wall-tile djd-hero__wall-tile--<?php echo esc_attr( $slot['corner'] ); ?>" style="--wall-w: <?php echo esc_attr( $slot['w'] ); ?>; --wall-h: <?php echo esc_attr( $slot['h'] ); ?>; --wall-r: <?php echo esc_attr( $slot['r'] ); ?>;">
					<img src="<?php echo esc_url( $p['src'] ); ?>" style="object-position: <?php echo esc_attr( $p['x'] ); ?>% <?php echo esc_attr( $p['y'] ); ?>%" alt="" loading="lazy">
				</div>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Escapes a ticker line and softens any " · " grouping dots inside it. */
function djdt_ticker_item( $text ) {
	$parts = array_map( 'esc_html', array_map( 'trim', explode( '·', $text ) ) );
	return implode( '<span class="djd-ticker__sub" aria-hidden="true">·</span>', $parts );
}

/**
 * Splits a word into one span per letter, each carrying its index in --i so
 * the CSS stagger can read it. aria-hidden: the parent heading already
 * carries the readable name in its own aria-label.
 */
function djdt_split_letters( $word ) {
	$out = '';
	$i   = 0;
	foreach ( preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$out .= '<span class="djd-letter" style="--i:' . (int) $i . '">' . esc_html( $ch ) . '</span>';
		$i++;
	}
	return $out;
}
