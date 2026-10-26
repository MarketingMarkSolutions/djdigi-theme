<?php
/**
 * [djdigi_videos] (v1.14.0): the 4 newest remix videos on the homepage,
 * "Remix videos" heading plus a "See all videos" link, same section system
 * as the events, player and venues sections.
 *
 * The cards, their lightbox and its VIP rules all come from djdigi-library
 * itself, through its own [djdigi_newest] renderer
 * (DjDigi_Library_Plugin::render_shortcode_newest()), so this always shows
 * exactly what the library's own gallery would and updates the moment the
 * library's sheet cache does. djdigi-library has no auto-updater, so it is
 * never modified from here: its markup is only restyled, scoped to
 * .djd-videos (assets/css/videos.css), and its own "View All Videos" button
 * is hidden in favor of this section's own link. If the library is not
 * active this outputs nothing at all.
 *
 * @package djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'djdigi_videos', 'djdt_videos_shortcode' );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	wp_register_style( 'djdt-videos', DJDT_URL . 'assets/css/videos.css', array( 'djdt-site' ), djdt_ver( 'assets/css/videos.css' ) );
}, 20 );

function djdt_videos_shortcode() {
	if ( ! djdt_is_active() ) {
		return '';
	}
	if ( ! class_exists( 'DjDigi_Library_Plugin' ) || ! method_exists( 'DjDigi_Library_Plugin', 'instance' ) ) {
		return '';
	}
	$library = DjDigi_Library_Plugin::instance();
	if ( ! method_exists( $library, 'render_shortcode_newest' ) ) {
		return '';
	}
	$cards = (string) $library->render_shortcode_newest( array( 'count' => 4 ) );
	if ( '' === trim( $cards ) ) {
		return '';
	}

	// The library prints its lightbox and VIP modal (each once per request)
	// right after the cards. Both are position: fixed pop-ups, so they go
	// after this section, not inside it: the section is its own stacking
	// context (z-index: 1, like every theme section), which would otherwise
	// trap them under the sections that follow it.
	$overlays = '';
	$cut      = false;
	foreach ( array( '<div class="djdigi-lightbox"', '<div class="djdigi-vip-modal"' ) as $needle ) {
		$at = strpos( $cards, $needle );
		if ( false !== $at && ( false === $cut || $at < $cut ) ) {
			$cut = $at;
		}
	}
	if ( false !== $cut ) {
		$overlays = substr( $cards, $cut );
		$cards    = substr( $cards, 0, $cut );
	}

	wp_enqueue_style( 'djdt-videos' );

	ob_start();
	?>
	<section class="djd-videos" aria-label="Remix videos">
		<h2 class="djd-section-heading"><?php echo djdt_heading_html( 'Remix videos' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></h2>
		<div class="djd-videos__cards">
			<?php echo $cards; // phpcs:ignore WordPress.Security.EscapeOutput -- djdigi-library's own escaped renderer output ?>
		</div>
		<a class="djd-seeall djd-videos__seeall" href="<?php echo esc_url( djdt_get( 'videos_url' ) ); ?>">See all videos <span class="djd-seeall__arrow" aria-hidden="true">→</span></a>
	</section>
	<?php
	echo $overlays; // phpcs:ignore WordPress.Security.EscapeOutput -- djdigi-library's own escaped renderer output
	return ob_get_clean();
}
