<?php
/**
 * Plugin Name: DJ DigiMark Theme
 * Description: Site styling, Avada menu restyle and the [djdigi_hero] homepage hero for djdigimark.com.
 * Version: 1.0.1
 * Author: DJ DigiMark
 * Update URI: https://github.com/MarketingMarkSolutions/djdigi-theme
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DJDT_VERSION', '1.0.1' );
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
		'hero_photo'    => '',
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

function djdt_sanitize( $in ) {
	$d   = djdt_defaults();
	$out = array();
	$out['live']          = empty( $in['live'] ) ? 0 : 1;
	$out['mixcloud_user'] = sanitize_title( isset( $in['mixcloud_user'] ) ? $in['mixcloud_user'] : $d['mixcloud_user'] );
	$out['mix_mode']      = ( isset( $in['mix_mode'] ) && 'pinned' === $in['mix_mode'] ) ? 'pinned' : 'latest';
	$out['mix_url']       = esc_url_raw( isset( $in['mix_url'] ) ? trim( $in['mix_url'] ) : '' );
	$out['hero_photo']    = esc_url_raw( isset( $in['hero_photo'] ) ? trim( $in['hero_photo'] ) : '' );
	$out['sets_url']      = sanitize_text_field( isset( $in['sets_url'] ) ? $in['sets_url'] : $d['sets_url'] );
	$out['videos_url']    = sanitize_text_field( isset( $in['videos_url'] ) ? $in['videos_url'] : $d['videos_url'] );
	$out['ticker']        = sanitize_textarea_field( isset( $in['ticker'] ) ? $in['ticker'] : $d['ticker'] );
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
					<th scope="row"><label for="djdt-photo">Hero photo URL</label></th>
					<td>
						<input id="djdt-photo" class="large-text" name="<?php echo esc_attr( $n ); ?>[hero_photo]" value="<?php echo esc_attr( $s['hero_photo'] ); ?>" placeholder="Leave blank to use the built-in photo">
						<p class="description">Paste a Media Library image URL to swap the photo. Portrait shots with club lighting work best.</p>
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
		<p>Hero shortcode: <code>[djdigi_hero]</code>. Put it alone in a 100% width container with no padding, at the top of the page.</p>
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
}, 20 );

function djdt_font_face_css() {
	$f = DJDT_URL . 'assets/fonts/';
	return "@font-face{font-family:'DJD Archivo';src:url('{$f}archivo-wdth.woff2') format('woff2');font-weight:100 900;font-stretch:62% 125%;font-display:swap;}"
		. "@font-face{font-family:'DJD Big Shoulders';src:url('{$f}big-shoulders-800.woff2') format('woff2');font-weight:800;font-display:swap;}";
}

add_action( 'wp_head', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	$f = DJDT_URL . 'assets/fonts/';
	echo '<link rel="preload" href="' . esc_url( $f . 'big-shoulders-800.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
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
 * Returns array( key, name, length ) for the hero player, cached for 6 hours.
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
			$mix  = array(
				'key'    => $key,
				'name'   => $data && ! empty( $data['name'] ) ? $data['name'] : '',
				'length' => $data && ! empty( $data['audio_length'] ) ? (int) $data['audio_length'] : 0,
			);
		}
	} else {
		$user = djdt_get( 'mixcloud_user' );
		$data = djdt_mixcloud_get( '/' . rawurlencode( $user ) . '/cloudcasts/?limit=1' );
		if ( $data && ! empty( $data['data'][0]['key'] ) ) {
			$c   = $data['data'][0];
			$mix = array(
				'key'    => $c['key'],
				'name'   => isset( $c['name'] ) ? $c['name'] : '',
				'length' => isset( $c['audio_length'] ) ? (int) $c['audio_length'] : 0,
			);
		}
	}
	// Cache failures briefly so a Mixcloud outage never slows every page.
	set_transient( 'djdt_mix', $mix ? $mix : array(), $mix ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $mix ? $mix : array();
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

	$photo  = djdt_get( 'hero_photo' ) ? djdt_get( 'hero_photo' ) : DJDT_URL . 'assets/img/hero-photo.jpg';
	$wave   = DJDT_URL . 'assets/img/wave.svg';
	$mix    = djdt_get_mix();
	$sets   = djdt_get( 'sets_url' );
	$videos = djdt_get( 'videos_url' );

	$next_up = shortcode_exists( 'djdigi_next_up' ) ? do_shortcode( '[djdigi_next_up]' ) : '';

	$items = array_filter( array_map( 'trim', explode( "\n", (string) djdt_get( 'ticker' ) ) ) );

	ob_start();
	?>
	<section class="djd-hero" aria-label="DJ DigiMark">
		<div class="djd-hero__glow djd-hero__glow--a djd-in-glow" aria-hidden="true"></div>
		<div class="djd-hero__glow djd-hero__glow--b djd-in-glow" aria-hidden="true"></div>
		<div class="djd-hero__glow djd-hero__glow--c djd-in-glow" aria-hidden="true"></div>
		<img class="djd-hero__photo djd-in-photo" src="<?php echo esc_url( $photo ); ?>" alt="DJ DigiMark mixing under club lights" fetchpriority="high">

		<?php if ( $next_up ) : ?>
			<div class="djd-hero__strip djd-in-ui"><?php echo $next_up; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted shortcode output ?></div>
		<?php endif; ?>

		<div class="djd-hero__inner">
			<div class="djd-hero__labels djd-in-ui">
				<span class="djd-hero__label"><span class="djd-hero__rule" aria-hidden="true"></span>Video DJ since 2008</span>
				<span>San Francisco</span>
			</div>

			<div class="djd-hero__stage">
				<h1 class="djd-hero__name djd-in-head" aria-label="DJ DigiMark">
					<span class="djd-hero__dj" aria-hidden="true">DJ</span>
					<span class="djd-hero__digimark" aria-hidden="true">
						<span class="djd-hero__echo">DigiMark</span>
						<span class="djd-hero__main">DigiMark</span>
					</span>
				</h1>
			</div>

			<div class="djd-hero__row djd-in-ui">
				<div class="djd-hero__copy">
					<p class="djd-hero__slogan">Bringing positive vibes to the dance <span class="djd-nowrap">floor <span class="djd-eq" role="img" aria-label="Rainbow flag and music"><span></span><span></span><span></span><span></span><span></span><span></span></span></span></p>
					<div class="djd-hero__buttons">
						<a class="djd-btn djd-btn--solid" href="<?php echo esc_url( $sets ); ?>">Upcoming sets</a>
						<a class="djd-btn djd-btn--line" href="<?php echo esc_url( $videos ); ?>">Remix videos</a>
					</div>
				</div>

				<?php if ( ! empty( $mix['key'] ) ) : ?>
					<div class="djd-player" data-feed="<?php echo esc_attr( $mix['key'] ); ?>">
						<button type="button" class="djd-player__btn" aria-label="<?php echo esc_attr( 'Play ' . $mix['name'] ); ?>" data-label="<?php echo esc_attr( $mix['name'] ); ?>">
							<svg class="djd-player__play" width="26" height="26" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4 L20 12 L7 20 Z" fill="currentColor"/></svg>
							<svg class="djd-player__pause" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="5" height="16" fill="currentColor"/><rect x="14" y="4" width="5" height="16" fill="currentColor"/></svg>
						</button>
						<div class="djd-player__body">
							<div class="djd-player__top"><span class="djd-player__status">Latest live set</span><span>Mixcloud</span></div>
							<div class="djd-player__title"><?php echo esc_html( $mix['name'] ); ?></div>
							<div class="djd-player__wave">
								<img src="<?php echo esc_url( $wave ); ?>" alt="">
								<div class="djd-player__progress"><img src="<?php echo esc_url( $wave ); ?>" alt=""></div>
							</div>
							<div class="djd-player__meta"><?php echo esc_html( djdt_format_length( $mix['length'] ) ); ?></div>
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
	<?php
	return ob_get_clean();
}

/** Escapes a ticker line and softens any " · " grouping dots inside it. */
function djdt_ticker_item( $text ) {
	$parts = array_map( 'esc_html', array_map( 'trim', explode( '·', $text ) ) );
	return implode( '<span class="djd-ticker__sub" aria-hidden="true">·</span>', $parts );
}
