<?php
/**
 * [djdigi_events]: upcoming sets read straight from Modern Events Calendar
 * Lite's own data. MEC's front end (its shortcodes, its calendar/list
 * views, its CSS/JS) is never used; this reads MEC's post type, post meta
 * and location taxonomy with plain WP APIs and renders its own cards.
 *
 * v1.8.0: ported into djdigi-theme from djdigi-library v0.2.0, where this
 * module was first built and tested (see that plugin's own CLAUDE.md for
 * the session it was built in). djdigi-library has no update checker (the
 * live Plugins page shows no update link for it), so it can only ever ship
 * as a manual zip upload; djdigi-theme already updates itself through a
 * GitHub release, so this module moved here instead, gated the same way
 * every other theme feature is (djdt_is_active(), below) rather than
 * carrying its own separate "Live for everyone" setting the library
 * version had. Same features, same behavior, same tests already run
 * there: [djdigi_events limit="5"] and [djdigi_events], the cards, the CD
 * tray, the before 6 AM rule, Midnight formatting, mec_in_days
 * occurrences, upcoming only, the empty state, JSON-LD, the 301 redirect
 * of MEC's own single event pages, and the MEC front end asset dequeue.
 * CSS renamed from djdigi-library's own djl- prefix to this theme's own
 * djd-events, and the gutter now reads straight from --djd-gutter
 * (site.css) with no fallback, since this is djdigi-theme's own variable
 * now rather than one plugin reaching for another's.
 *
 * MEC's real schema here (read live from the site, not guessed):
 *  - Post type 'mec-events'.
 *  - Postmeta 'mec_date': array with start/end (each date/hour/minutes/
 *    ampm), allday, and repeat (status/type). The only repeat type seen
 *    live is 'custom_days'.
 *  - Postmeta 'mec_in_days': when repeat.status and repeat.type ===
 *    'custom_days', an array of strings 'Y-m-d:Y-m-d:hh-mm-AMPM:hh-mm-AMPM'
 *    (start date, end date, start time, end time), one per occurrence. This
 *    is the authoritative occurrence list for a repeating event, used
 *    instead of the single base 'mec_date', which can go stale once an
 *    event has repeat days added after its own original date (seen live:
 *    one event's base date was stale while its real upcoming date only
 *    existed in mec_in_days).
 *  - Postmeta 'mec_location_id': term ID into the 'mec_location' taxonomy.
 *    Term meta on it: address, latitude, longitude, url, tel, thumbnail.
 *  - Postmeta 'mec_more_info', 'mec_more_info_title', 'mec_more_info_target':
 *    the optional "more info" link, often empty.
 *
 * @package DJ_DigiMark_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DJDT_EVENTS_MEC_POST_TYPE', 'mec-events' );
define( 'DJDT_EVENTS_MEC_TAXONOMY', 'mec_location' );

/* -------------------------------------------------------------------------
 * Shortcode + assets
 * ---------------------------------------------------------------------- */

add_shortcode( 'djdigi_events', 'djdt_events_shortcode' );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	wp_register_style(
		'djdt-events',
		DJDT_URL . 'assets/css/events.css',
		array( 'djdt-site' ),
		djdt_ver( 'assets/css/events.css' )
	);
	wp_register_script(
		'djdt-events',
		DJDT_URL . 'assets/js/events.js',
		array(),
		djdt_ver( 'assets/js/events.js' ),
		true
	);
}, 20 );

/* -------------------------------------------------------------------------
 * Stop MEC's own front end assets from loading on this module's own
 * [djdigi_events] page, and send MEC's own single event pages to the
 * events page instead. v1.8.1, found live, logged out: both of these ran
 * unconditionally through v1.8.0, independent of djdt_is_active(), on the
 * reasoning that MEC's front end is never used on this site regardless of
 * whether djdigi-theme itself is public yet. Two real bugs that reasoning
 * missed: the dequeue is a blocklist (strip MEC's assets everywhere
 * unless this exact page has a literal MEC shortcode in it), which
 * misfired on the real /upcoming-events/ page itself, MEC's own calendar
 * still live there and genuinely needing its own CSS/JS, which the
 * literal shortcode scan below did not detect it as having (confirmed
 * live: MEC's own markup rendered, about 200 mec- classes, with zero MEC
 * CSS/JS actually loaded); and the redirect fired for a logged out
 * visitor even while the theme itself is still admin only, which breaks
 * the one guarantee djdt_is_active() exists to make, a logged out visitor
 * sees the site exactly as it was before this plugin, untouched, until
 * "Live for everyone" is checked.
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'djdt_events_dequeue_mec_assets', 9999 );
add_action( 'template_redirect', 'djdt_events_redirect_mec_singles' );

/**
 * Stops MEC's own front end CSS/JS from loading, but only on this
 * module's own [djdigi_events] page, and only once that page is
 * confirmed not to also render real MEC output of its own. v1.8.1: an
 * allowlist now, not a blocklist, the opposite of v1.8.0's own
 * reasoning. Dequeuing MEC's assets is dead weight exactly one place,
 * the page carrying [djdigi_events]; everywhere else, including the
 * cases this cannot positively confirm, MEC's assets are left alone.
 * "If unsure, keep MEC assets" (Mark's own wording): the cost of a false
 * negative here (MEC loads its own CSS/JS on a page that did not
 * actually need it) is a few unused kilobytes; the cost of a false
 * positive (a page that did need MEC's styling loses it) is what
 * actually happened live.
 */
function djdt_events_dequeue_mec_assets() {
	if ( ! djdt_is_active() ) {
		return;
	}
	if ( ! djdt_events_current_request_renders_djdigi_events() ) {
		return;
	}
	if ( djdt_events_current_request_has_mec_shortcode() ) {
		return;
	}
	global $wp_styles, $wp_scripts;
	foreach ( array( $wp_styles, $wp_scripts ) as $registry ) {
		if ( ! $registry || empty( $registry->queue ) ) {
			continue;
		}
		foreach ( (array) $registry->queue as $handle ) {
			if ( djdt_events_looks_like_mec_handle( $handle, $registry ) ) {
				$registry->dequeue( $handle );
			}
		}
	}
}

/**
 * Literal string scan for '[djdigi_events' in the current request's own
 * post content, the same technique djdt_events_current_request_has_mec_shortcode()
 * already uses for MEC's own shortcode below: cheap, and available this
 * early (wp_enqueue_scripts, before the main content loop has actually
 * run do_shortcode() on anything), unlike checking whether the shortcode
 * itself has already executed, which it has not yet at this point in the
 * request.
 */
function djdt_events_current_request_renders_djdigi_events() {
	global $post;
	if ( ! $post ) {
		return false;
	}
	return false !== strpos( (string) $post->post_content, '[djdigi_events' );
}

function djdt_events_looks_like_mec_handle( $handle, $registry ) {
	if ( 0 === strpos( $handle, 'mec' ) || false !== strpos( $handle, 'modern-events-calendar' ) ) {
		return true;
	}
	if ( isset( $registry->registered[ $handle ] ) ) {
		$src = (string) $registry->registered[ $handle ]->src;
		if ( false !== strpos( $src, 'modern-events-calendar' ) ) {
			return true;
		}
	}
	return false;
}

function djdt_events_current_request_has_mec_shortcode() {
	global $post;
	if ( ! $post ) {
		return false;
	}
	$content = (string) $post->post_content;
	// MEC's own shortcodes all start with "MEC" (its documented family:
	// MEC, MEC_bootstrap_calendar, MEC_monthly_calendar, and similar).
	return (bool) preg_match( '/\[MEC[_\s\]]/i', $content );
}

/**
 * MEC's own single event pages 301 to the events page instead: this
 * module renders every upcoming set as its own card, MEC's own single
 * template is never used. v1.8.1: gated behind djdt_is_active() now,
 * found live redirecting a logged out visitor even while the theme
 * itself was still admin only, see the section comment above.
 */
function djdt_events_redirect_mec_singles() {
	if ( ! djdt_is_active() ) {
		return;
	}
	if ( is_singular( DJDT_EVENTS_MEC_POST_TYPE ) ) {
		wp_safe_redirect( djdt_events_page_url(), 301 );
		exit;
	}
}

/**
 * The events page URL: reads the same admin configured "Upcoming sets"
 * setting the hero's own button already points at (djdt_get('sets_url'),
 * Theme Settings), rather than a separate hardcoded slug, so there is one
 * place to change it, not two that can drift apart.
 */
function djdt_events_page_url() {
	$path = trim( (string) djdt_get( 'sets_url' ) );
	if ( '' === $path ) {
		$path = '/upcoming-events/';
	}
	if ( preg_match( '#^https?://#i', $path ) ) {
		return $path;
	}
	return home_url( $path );
}

/* -------------------------------------------------------------------------
 * Reading MEC's data
 * ---------------------------------------------------------------------- */

/**
 * All upcoming occurrences across every mec-events post, sorted by start,
 * each one tonight's or later and not yet ended. $limit = 0 means all.
 */
function djdt_events_get_upcoming_occurrences( $limit = 0 ) {
	$posts = get_posts( array(
		'post_type'      => DJDT_EVENTS_MEC_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	$now         = current_datetime();
	$occurrences = array();

	foreach ( $posts as $post ) {
		foreach ( djdt_events_build_occurrences_for_post( $post ) as $occ ) {
			if ( $occ['end'] >= $now ) {
				$occurrences[] = $occ;
			}
		}
	}

	usort( $occurrences, function ( $a, $b ) {
		return $a['start'] <=> $b['start'];
	} );

	if ( $limit > 0 && count( $occurrences ) > $limit ) {
		$occurrences = array_slice( $occurrences, 0, $limit );
	}

	return $occurrences;
}

/**
 * One post can be several occurrences (a repeating "custom days" event):
 * each occurrence carries its own start/end DateTimeImmutable plus the
 * post it came from, so the card renderer never needs to re-derive dates.
 */
function djdt_events_build_occurrences_for_post( $post ) {
	$mec_date = get_post_meta( $post->ID, 'mec_date', true );
	if ( ! is_array( $mec_date ) ) {
		return array();
	}

	$repeat                   = isset( $mec_date['repeat'] ) && is_array( $mec_date['repeat'] ) ? $mec_date['repeat'] : array();
	$is_repeating_custom_days = ! empty( $repeat['status'] ) && isset( $repeat['type'] ) && 'custom_days' === $repeat['type'];

	$occurrences = array();

	if ( $is_repeating_custom_days ) {
		$in_days = get_post_meta( $post->ID, 'mec_in_days', true );
		if ( is_array( $in_days ) ) {
			foreach ( $in_days as $entry ) {
				$occ = djdt_events_parse_in_days_entry( (string) $entry, $post );
				if ( $occ ) {
					$occurrences[] = $occ;
				}
			}
		}
	}

	// Either not repeating, or repeating but mec_in_days came back empty
	// (defensive: do not just silently drop the event in that case).
	if ( empty( $occurrences ) ) {
		$occ = djdt_events_occurrence_from_mec_date( $mec_date, $post );
		if ( $occ ) {
			$occurrences[] = $occ;
		}
	}

	return $occurrences;
}

function djdt_events_occurrence_from_mec_date( $mec_date, $post ) {
	if ( empty( $mec_date['start'] ) || empty( $mec_date['end'] ) ) {
		return null;
	}
	$start = djdt_events_datetime_from_parts( $mec_date['start'] );
	$end   = djdt_events_datetime_from_parts( $mec_date['end'] );
	if ( ! $start || ! $end ) {
		return null;
	}
	return array( 'start' => $start, 'end' => $end, 'post' => $post );
}

function djdt_events_datetime_from_parts( $parts ) {
	if ( empty( $parts['date'] ) ) {
		return null;
	}
	$date    = (string) $parts['date'];
	$hour    = isset( $parts['hour'] ) ? (int) $parts['hour'] : 12;
	$minutes = isset( $parts['minutes'] ) ? (int) $parts['minutes'] : 0;
	$ampm    = isset( $parts['ampm'] ) ? strtoupper( (string) $parts['ampm'] ) : 'AM';
	$ampm    = ( 'PM' === $ampm ) ? 'PM' : 'AM';

	$str = sprintf( '%s %02d:%02d %s', $date, $hour, $minutes, $ampm );
	$dt  = DateTimeImmutable::createFromFormat( 'Y-m-d h:i A', $str, wp_timezone() );
	return $dt ? $dt : null;
}

/**
 * 'Y-m-d:Y-m-d:hh-mm-AMPM:hh-mm-AMPM', e.g.
 * '2026-10-30:2026-10-30:05-00-PM:09-00-PM', confirmed live.
 */
function djdt_events_parse_in_days_entry( $entry, $post ) {
	$parts = explode( ':', $entry );
	if ( 4 !== count( $parts ) ) {
		return null;
	}
	list( $start_date, $end_date, $start_time, $end_time ) = $parts;

	$start = djdt_events_datetime_from_date_and_hyphen_time( $start_date, $start_time );
	$end   = djdt_events_datetime_from_date_and_hyphen_time( $end_date, $end_time );
	if ( ! $start || ! $end ) {
		return null;
	}
	return array( 'start' => $start, 'end' => $end, 'post' => $post );
}

function djdt_events_datetime_from_date_and_hyphen_time( $date, $time ) {
	// 'hh-mm-AMPM' -> 'hh:mm AMPM' so DateTimeImmutable can parse it.
	if ( ! preg_match( '/^(\d{1,2})-(\d{2})-(AM|PM)$/i', trim( $time ), $m ) ) {
		return null;
	}
	$str = sprintf( '%s %02d:%02d %s', $date, (int) $m[1], (int) $m[2], strtoupper( $m[3] ) );
	$dt  = DateTimeImmutable::createFromFormat( 'Y-m-d h:i A', $str, wp_timezone() );
	return $dt ? $dt : null;
}

/**
 * The date an occurrence should be LABELED under: a start before 6 AM
 * belongs to the previous night (a 1 AM Saturday set reads as Friday
 * night). This only changes the label, never the real start/end used
 * for sorting or the "is it still upcoming" check above.
 */
function djdt_events_display_date( $occurrence ) {
	$start = $occurrence['start'];
	if ( (int) $start->format( 'G' ) < 6 ) {
		return $start->modify( '-1 day' );
	}
	return $start;
}

/* ----- Venue + more-info lookups --------------------------------------- */

function djdt_events_get_venue( $post_id ) {
	$location_id = (int) get_post_meta( $post_id, 'mec_location_id', true );
	if ( ! $location_id ) {
		return null;
	}
	$term = get_term( $location_id, DJDT_EVENTS_MEC_TAXONOMY );
	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}
	return array(
		'name'    => $term->name,
		'address' => (string) get_term_meta( $location_id, 'address', true ),
		'url'     => (string) get_term_meta( $location_id, 'url', true ),
		'tel'     => (string) get_term_meta( $location_id, 'tel', true ),
		'lat'     => get_term_meta( $location_id, 'latitude', true ),
		'lng'     => get_term_meta( $location_id, 'longitude', true ),
	);
}

function djdt_events_get_more_info( $post_id ) {
	$url = trim( (string) get_post_meta( $post_id, 'mec_more_info', true ) );
	if ( '' === $url ) {
		return null;
	}
	$title  = trim( (string) get_post_meta( $post_id, 'mec_more_info_title', true ) );
	$target = trim( (string) get_post_meta( $post_id, 'mec_more_info_target', true ) );
	return array(
		'url'    => $url,
		'title'  => '' !== $title ? $title : 'Info',
		'target' => '' !== $target ? $target : '_blank',
	);
}

/* ----- Date/time formatting --------------------------------------------- */

function djdt_events_format_date_line( $occurrence ) {
	$display   = djdt_events_display_date( $occurrence );
	$date_part = $display->format( 'D M j' );
	$time_part = djdt_events_format_time_range( $occurrence['start'], $occurrence['end'] );
	return $date_part . ' · ' . $time_part;
}

function djdt_events_format_time_range( $start, $end ) {
	$start_ampm = $start->format( 'A' );
	$end_ampm   = $end->format( 'A' );
	$same_ampm  = ( $start_ampm === $end_ampm );

	$start_str = djdt_events_format_single_time( $start, $same_ampm );
	$end_str   = djdt_events_format_single_time( $end, false );

	return $start_str . ' to ' . $end_str;
}

function djdt_events_format_single_time( $dt, $omit_meridiem ) {
	$hour12 = (int) $dt->format( 'g' );
	$minute = (int) $dt->format( 'i' );
	$ampm   = $dt->format( 'A' );

	if ( 12 === $hour12 && 0 === $minute && 'AM' === $ampm ) {
		return 'Midnight';
	}

	$time = ( 0 === $minute ) ? (string) $hour12 : sprintf( '%d:%02d', $hour12, $minute );
	return $omit_meridiem ? $time : $time . ' ' . $ampm;
}

/* -------------------------------------------------------------------------
 * Rendering
 * ---------------------------------------------------------------------- */

function djdt_events_shortcode( $atts ) {
	if ( ! djdt_is_active() ) {
		return '';
	}
	$atts   = shortcode_atts( array( 'limit' => 0 ), (array) $atts, 'djdigi_events' );
	$limit  = (int) $atts['limit'];
	$is_row = $limit > 0;

	$occurrences = djdt_events_get_upcoming_occurrences( $limit );

	wp_enqueue_style( 'djdt-events' );
	wp_enqueue_script( 'djdt-events' );

	ob_start();
	if ( empty( $occurrences ) ) {
		djdt_events_render_empty_state();
	} else {
		djdt_events_render_grid( $occurrences, $is_row );
		djdt_events_render_json_ld( $occurrences );
	}
	return ob_get_clean();
}

function djdt_events_render_empty_state() {
	?>
	<div class="djd-events__empty">
		<p>New dates coming soon</p>
		<a href="https://www.instagram.com/DJDigiMark" target="_blank" rel="noopener">Follow on Instagram ↗</a>
	</div>
	<?php
}

function djdt_events_render_grid( $occurrences, $is_row ) {
	$wrap_class = $is_row ? 'djd-events djd-events--row' : 'djd-events djd-events--grid';
	?>
	<div class="<?php echo esc_attr( $wrap_class ); ?>">
		<?php if ( $is_row ) : ?>
			<h2 class="djd-events__heading">Upcoming sets</h2>
		<?php endif; ?>
		<div class="djd-events__track">
			<?php foreach ( $occurrences as $i => $occ ) : ?>
				<?php djdt_events_render_card( $occ, $i ); ?>
			<?php endforeach; ?>
		</div>
		<?php if ( $is_row ) : ?>
			<a class="djd-events__seeall" href="<?php echo esc_url( djdt_events_page_url() ); ?>">See all dates ↗</a>
		<?php endif; ?>
	</div>
	<?php
}

function djdt_events_render_card( $occ, $i ) {
	$post       = $occ['post'];
	$venue      = djdt_events_get_venue( $post->ID );
	$more_info  = djdt_events_get_more_info( $post->ID );
	$venue_name = $venue ? $venue['name'] : get_the_title( $post );
	$date_line  = djdt_events_format_date_line( $occ );
	$card_id    = 'djd-events-tray-' . $post->ID . '-' . $i;
	$btn_id     = 'djd-events-eject-' . $post->ID . '-' . $i;
	$img        = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
	$excerpt    = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24, '…' );
	$label      = sprintf( 'More info for %s, %s', $venue_name, $occ['start']->format( 'M j' ) );
	?>
	<div class="djd-events__card">
		<div class="djd-events__card-collapsed">
			<?php if ( $img ) : ?>
				<div class="djd-events__card-photo">
					<img src="<?php echo esc_url( $img ); ?>" alt="" loading="lazy" decoding="async">
				</div>
			<?php endif; ?>
			<div class="djd-events__card-body">
				<div class="djd-events__card-venue"><?php echo esc_html( $venue_name ); ?></div>
				<div class="djd-events__card-date"><?php echo esc_html( $date_line ); ?></div>
				<?php if ( $excerpt ) : ?>
					<p class="djd-events__card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
			</div>
			<button
				type="button"
				id="<?php echo esc_attr( $btn_id ); ?>"
				class="djd-events__card-eject"
				aria-expanded="false"
				aria-controls="<?php echo esc_attr( $card_id ); ?>"
				aria-label="<?php echo esc_attr( $label ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
					<path d="M12 4 L20 15 L4 15 Z" fill="currentColor"/>
					<rect x="4" y="18" width="16" height="3" fill="currentColor"/>
				</svg>
			</button>
		</div>
		<div class="djd-events__card-tray" id="<?php echo esc_attr( $card_id ); ?>" role="region" aria-labelledby="<?php echo esc_attr( $btn_id ); ?>" hidden>
			<div class="djd-events__card-tray-inner">
				<?php if ( $img ) : ?>
					<div class="djd-events__card-disc" style="--djd-events-disc-img: url('<?php echo esc_url( $img ); ?>');" aria-hidden="true"></div>
				<?php endif; ?>
				<div class="djd-events__card-full"><?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); ?></div>
				<?php if ( $venue && $venue['address'] ) : ?>
					<p class="djd-events__card-address">
						<a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $venue['address'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $venue['address'] ); ?> ↗</a>
					</p>
				<?php endif; ?>
				<?php if ( $more_info ) : ?>
					<p class="djd-events__card-more">
						<a href="<?php echo esc_url( $more_info['url'] ); ?>" target="<?php echo esc_attr( $more_info['target'] ); ?>" rel="noopener"><?php echo esc_html( $more_info['title'] ); ?> ↗</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

function djdt_events_render_json_ld( $occurrences ) {
	$events_url = djdt_events_page_url();
	$items      = array();
	foreach ( $occurrences as $occ ) {
		$post  = $occ['post'];
		$venue = djdt_events_get_venue( $post->ID );
		$img   = get_the_post_thumbnail_url( $post->ID, 'large' );

		$location = array( '@type' => 'Place', 'name' => $venue ? $venue['name'] : get_the_title( $post ) );
		if ( $venue && $venue['address'] ) {
			$location['address'] = $venue['address'];
		}

		$item = array(
			'@type'       => 'Event',
			'name'        => get_the_title( $post ),
			'startDate'   => $occ['start']->format( DateTimeImmutable::ATOM ),
			'endDate'     => $occ['end']->format( DateTimeImmutable::ATOM ),
			'location'    => $location,
			'url'         => $events_url,
			'eventStatus' => 'https://schema.org/EventScheduled',
		);
		if ( $img ) {
			$item['image'] = $img;
		}
		$items[] = $item;
	}
	if ( empty( $items ) ) {
		return;
	}
	$graph = array( '@context' => 'https://schema.org', '@graph' => $items );
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $graph ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded, not HTML
}
