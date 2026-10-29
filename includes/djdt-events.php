<?php
/**
 * [djdigi_events]: upcoming events read straight from Modern Events Calendar
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
 * The events page URL: reads the admin configured "Events page link"
 * setting (djdt_get('sets_url'), the hero's old "Upcoming events" button
 * link until v1.14.1,
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
 *
 * v1.8.2, found live: the homepage row showed two single, non repeating
 * events from January 2025 (long past) as upcoming, and was missing "DJ
 * DigiMark at The Cafe" entirely, a real occurrence three days away. The
 * postmeta based parsing below (mec_date's own base start/end, mec_in_days
 * for a "custom_days" repeat) is kept only as a fallback now, for a site
 * where MEC's own occurrence table does not exist; the primary source is
 * MEC's own {$wpdb->prefix}mec_dates table (post_id, dstart, dend, tstart,
 * tend), which MEC itself maintains as the authoritative occurrence list
 * for every repeat type, custom days and exceptions included, so this
 * module no longer has to correctly reverse engineer MEC's own postmeta
 * schema by hand at all, the likely source of both bugs above.
 * ---------------------------------------------------------------------- */

/**
 * All upcoming occurrences across every mec-events post, sorted by start,
 * each one tonight's or later and not yet ended (full date and time, year
 * included: a DateTimeImmutable comparison, never a string one). $limit =
 * 0 means all.
 */
function djdt_events_get_upcoming_occurrences( $limit = 0 ) {
	$now      = current_datetime();
	$upcoming = array();
	foreach ( djdt_events_get_all_occurrences() as $occ ) {
		if ( $occ['end'] >= $now ) {
			$upcoming[] = $occ;
		}
	}

	usort( $upcoming, function ( $a, $b ) {
		return $a['start'] <=> $b['start'];
	} );

	if ( $limit > 0 && count( $upcoming ) > $limit ) {
		$upcoming = array_slice( $upcoming, 0, $limit );
	}

	return $upcoming;
}

/**
 * Every occurrence, past ones included (v1.15.1: a residency needs to know
 * whether its series has already started), from mec_dates or, failing
 * that, postmeta. Cached for the request: the events grid, the residency
 * check and the .ics endpoint all read it.
 */
function djdt_events_get_all_occurrences() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$had_unparsed_rows = false;
	$occurrences        = djdt_events_occurrences_from_mec_dates_table( $had_unparsed_rows );
	if ( null === $occurrences || $had_unparsed_rows ) {
		if ( $had_unparsed_rows ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- deliberate, one line, see the v1.8.3 note on djdt_events_occurrences_from_mec_dates_table().
			error_log( 'djdigi-theme: mec_dates returned rows but none parsed into a usable occurrence, falling back to postmeta.' );
		}
		$occurrences = djdt_events_occurrences_from_postmeta();
	}
	$cache = $occurrences;
	return $cache;
}

/**
 * Every occurrence, read straight from MEC's own {$wpdb->prefix}mec_dates
 * table: one row per occurrence already, covering every repeat type MEC
 * itself supports, not just the single "custom_days" case the postmeta
 * fallback below understands. Returns null (not an empty array) when the
 * table does not exist at all, so the caller knows to fall back rather
 * than treating "no table" the same as "table exists, genuinely no rows".
 *
 * $had_unparsed_rows (by reference, v1.8.3) is set true when the table
 * exists and returned rows but not one of them produced a usable
 * occurrence: found live, this happened for every row through v1.8.2
 * (see the note on djdt_events_datetime_from_mec_row() below) and the
 * table being genuinely queryable but every row failing to parse was
 * silently treated the same as "no rows at all", the empty state shown
 * instead of ever falling back to postmeta. The caller checks this flag
 * explicitly now rather than relying on an empty array alone.
 */
function djdt_events_occurrences_from_mec_dates_table( &$had_unparsed_rows = false ) {
	global $wpdb;
	$table = $wpdb->prefix . 'mec_dates';

	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table !== $exists ) {
		return null;
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table and $wpdb->posts are not user input, the standard WP pattern for a dynamic table name.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT d.post_id, d.dstart, d.dend, d.tstart, d.tend
		FROM {$table} d
		INNER JOIN {$wpdb->posts} p ON p.ID = d.post_id
		WHERE p.post_type = %s AND p.post_status = 'publish'",
		DJDT_EVENTS_MEC_POST_TYPE
	) );
	if ( ! is_array( $rows ) ) {
		return null;
	}

	$posts_by_id = array();
	$occurrences = array();
	foreach ( $rows as $row ) {
		$post_id = (int) $row->post_id;
		if ( ! array_key_exists( $post_id, $posts_by_id ) ) {
			$posts_by_id[ $post_id ] = get_post( $post_id );
		}
		$post = $posts_by_id[ $post_id ];
		if ( ! $post ) {
			continue;
		}
		$dstart = trim( (string) $row->dstart );
		$dend   = trim( (string) $row->dend );

		$start = djdt_events_datetime_from_mec_row( $dstart, $row->tstart );
		if ( ! $start ) {
			$start = djdt_events_wall_time_from_postmeta_time( $dstart, $post, 'start' );
		}
		$end = djdt_events_datetime_from_mec_row( $dend, $row->tend );
		if ( ! $end ) {
			$end = djdt_events_wall_time_from_postmeta_time( $dend, $post, 'end' );
		}
		if ( ! $start || ! $end ) {
			continue;
		}
		$occurrences[] = array( 'start' => $start, 'end' => $end, 'post' => $post );
	}

	if ( ! empty( $rows ) && empty( $occurrences ) ) {
		$had_unparsed_rows = true;
	}

	return $occurrences;
}

/**
 * mec_dates' own dstart/dend columns are plain 'Y-m-d' dates; tstart/tend
 * are Unix timestamps (confirmed live, v1.8.3: this function used to
 * concatenate them onto the date string as if they were a clock time,
 * "2026-10-22 1792...", which threw inside DateTimeImmutable every
 * single time, the catch returned null, and every occurrence from this
 * table was silently dropped). A numeric tstart/tend is now read as a
 * timestamp instead, resolved against the row's own date column by
 * djdt_events_wall_time_from_timestamp() below; anything else (not
 * numeric, or a timestamp that cannot be made to agree with that date at
 * all) returns null here so the caller falls back to the post's own
 * postmeta time instead of guessing.
 */
function djdt_events_datetime_from_mec_row( $date_str, $time_val ) {
	if ( '' === $date_str ) {
		return null;
	}
	if ( is_numeric( $time_val ) ) {
		return djdt_events_wall_time_from_timestamp( (int) $time_val, $date_str );
	}
	$time_str = trim( (string) $time_val );
	$combined = '' !== $time_str ? ( $date_str . ' ' . $time_str ) : $date_str;
	try {
		return new DateTimeImmutable( $combined, wp_timezone() );
	} catch ( \Exception $e ) {
		return null;
	}
}

/**
 * Resolves a mec_dates timestamp column (tstart/tend) against that same
 * row's own date column (dstart/dend), which is never in question, MEC's
 * own stored 'Y-m-d'. The timestamp alone is ambiguous: MEC appears to
 * store a wall clock time encoded as if it were already UTC (no real
 * timezone conversion happened when it was generated, a common pattern
 * for this kind of plugin), so this tries that reading first (gmdate())
 * and a properly converted one (wp_date(), in wp_timezone()) second,
 * trusting whichever one's own calendar date actually lands on
 * $date_str; the matched wall clock time is then rebuilt as a
 * DateTimeImmutable in wp_timezone(). Returns null if neither reading's
 * date agrees with $date_str at all.
 */
function djdt_events_wall_time_from_timestamp( $timestamp, $date_str ) {
	$utc_wall  = gmdate( 'Y-m-d H:i:s', $timestamp );
	$site_wall = wp_date( 'Y-m-d H:i:s', $timestamp, wp_timezone() );

	$candidate = null;
	if ( 0 === strpos( $utc_wall, $date_str ) ) {
		$candidate = $utc_wall;
	} elseif ( $site_wall && 0 === strpos( $site_wall, $date_str ) ) {
		$candidate = $site_wall;
	}
	if ( ! $candidate ) {
		return null;
	}

	try {
		return new DateTimeImmutable( $candidate, wp_timezone() );
	} catch ( \Exception $e ) {
		return null;
	}
}

/**
 * Per occurrence fallback when a mec_dates row's own tstart/tend cannot
 * be resolved into a real wall clock time at all: trusts the row's own
 * date column (dstart/dend, never in question) and reads a time of day
 * from the post's own postmeta instead, the matching mec_in_days entry
 * for that exact date if this post repeats on custom days, otherwise
 * mec_date's own base start/end time. $which is 'start' or 'end'.
 */
function djdt_events_wall_time_from_postmeta_time( $date_str, $post, $which ) {
	$in_days = get_post_meta( $post->ID, 'mec_in_days', true );
	if ( is_array( $in_days ) ) {
		foreach ( $in_days as $entry ) {
			$parts = explode( ':', (string) $entry );
			if ( 4 !== count( $parts ) ) {
				continue;
			}
			list( $entry_start_date, $entry_end_date, $entry_start_time, $entry_end_time ) = $parts;
			$entry_date = ( 'start' === $which ) ? $entry_start_date : $entry_end_date;
			if ( $entry_date === $date_str ) {
				$time_str = ( 'start' === $which ) ? $entry_start_time : $entry_end_time;
				return djdt_events_datetime_from_date_and_hyphen_time( $date_str, $time_str );
			}
		}
	}

	$mec_date = get_post_meta( $post->ID, 'mec_date', true );
	if ( is_array( $mec_date ) && ! empty( $mec_date[ $which ] ) && is_array( $mec_date[ $which ] ) ) {
		$parts   = $mec_date[ $which ];
		$hour    = isset( $parts['hour'] ) ? (int) $parts['hour'] : 12;
		$minutes = isset( $parts['minutes'] ) ? (int) $parts['minutes'] : 0;
		$ampm    = isset( $parts['ampm'] ) ? strtoupper( (string) $parts['ampm'] ) : 'AM';
		$ampm    = ( 'PM' === $ampm ) ? 'PM' : 'AM';
		$str     = sprintf( '%s %02d:%02d %s', $date_str, $hour, $minutes, $ampm );
		$dt      = DateTimeImmutable::createFromFormat( 'Y-m-d h:i A', $str, wp_timezone() );
		return $dt ? $dt : null;
	}

	return null;
}

/**
 * Fallback for a site where MEC's own mec_dates table does not exist:
 * every occurrence built from each post's own mec_date / mec_in_days
 * postmeta instead, the only source this module had through v1.8.1.
 */
function djdt_events_occurrences_from_postmeta() {
	$posts = get_posts( array(
		'post_type'      => DJDT_EVENTS_MEC_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	$occurrences = array();
	foreach ( $posts as $post ) {
		foreach ( djdt_events_build_occurrences_for_post( $post ) as $occ ) {
			$occurrences[] = $occ;
		}
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
		'logo'    => djdt_events_venue_logo_url( $location_id ),
	);
}

/**
 * v1.9.2: the venue logo on the case front (The Edge, term 494, confirmed
 * live to have one set). MEC's own 'thumbnail' term meta on mec_location:
 * read live as an attachment ID on every location checked, handled here as
 * either an attachment ID (the normal case, resolved through WordPress so
 * it benefits from a generated size) or, defensively, an already-absolute
 * URL, since MEC's schema for this field was never directly confirmed
 * beyond the one location Mark pointed at. '' (no extra markup at all,
 * djdt_events_render_card()) when the location has no thumbnail set.
 */
function djdt_events_venue_logo_url( $location_id ) {
	$raw = get_term_meta( $location_id, 'thumbnail', true );
	if ( '' === $raw || null === $raw ) {
		return '';
	}
	if ( is_numeric( $raw ) ) {
		$url = wp_get_attachment_image_url( (int) $raw, 'medium' );
		return $url ? $url : '';
	}
	$raw = (string) $raw;
	return preg_match( '#^https?://#i', $raw ) ? $raw : '';
}

/* ----- Date/time formatting --------------------------------------------- */

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
 * Residencies (v1.15.1)
 *
 * A recurring event (Midnight Sun, every Friday) used to show as one CD per
 * date. Upcoming occurrences are grouped by event post now: a post whose
 * dates come from a real MEC repeat rule (any repeat type except
 * custom_days, which is a hand picked list of dates), or failing that any
 * post with 3 or more upcoming dates, is a residency. Its dates leave the
 * normal CD list and it shows once: as a featured card on the events page,
 * and as one CD at the start of the homepage row.
 * ---------------------------------------------------------------------- */

/** True if the post's dates come from a MEC repeat rule (not custom_days). */
function djdt_events_has_repeat_rule( $post ) {
	$mec_date = get_post_meta( $post->ID, 'mec_date', true );
	$repeat   = ( is_array( $mec_date ) && isset( $mec_date['repeat'] ) && is_array( $mec_date['repeat'] ) ) ? $mec_date['repeat'] : array();
	if ( empty( $repeat['status'] ) ) {
		return false;
	}
	$type = isset( $repeat['type'] ) ? (string) $repeat['type'] : (string) get_post_meta( $post->ID, 'mec_repeat_type', true );
	return 'custom_days' !== $type;
}

/**
 * Splits upcoming occurrences into residencies and one off occurrences.
 * Returns array( 'residencies' => array of residency arrays, sorted by next
 * date, 'singles' => occurrences, sorted by start ). A residency array:
 * post, occs (its upcoming occurrences, sorted), next (the first of them),
 * started (an earlier occurrence has already begun), rule (from
 * djdt_events_recurrence()).
 */
function djdt_events_split( $upcoming ) {
	$by_post = array();
	foreach ( $upcoming as $occ ) {
		$by_post[ $occ['post']->ID ][] = $occ;
	}
	$now         = current_datetime();
	$residencies = array();
	$res_ids     = array();
	foreach ( $by_post as $post_id => $occs ) {
		$post = $occs[0]['post'];
		if ( ! djdt_events_has_repeat_rule( $post ) && count( $occs ) < 3 ) {
			continue;
		}
		$started = false;
		foreach ( djdt_events_get_all_occurrences() as $any ) {
			if ( $any['post']->ID === $post_id && $any['start'] < $now ) {
				$started = true;
				break;
			}
		}
		$residencies[]       = array(
			'post'    => $post,
			'occs'    => $occs,
			'next'    => $occs[0],
			'started' => $started,
			'rule'    => djdt_events_recurrence( $post, $occs ),
		);
		$res_ids[ $post_id ] = true;
	}
	usort( $residencies, function ( $a, $b ) {
		return $a['next']['start'] <=> $b['next']['start'];
	} );
	$singles = array();
	foreach ( $upcoming as $occ ) {
		if ( empty( $res_ids[ $occ['post']->ID ] ) ) {
			$singles[] = $occ;
		}
	}
	return array( 'residencies' => $residencies, 'singles' => $singles );
}

/**
 * The recurrence in plain words, read from the dates themselves rather than
 * from MEC's repeat settings, so it is right whichever repeat type was used.
 * Returns array(
 *   'text'     => 'Every Friday, 9 PM to 1 AM',
 *   'day'      => 'Fri' (the weekday, by the before 6 AM rule),
 *   'label'    => 'Weekly' (the CD cover's second line),
 *   'interval' => 7 (days between dates, when every gap is a whole multiple
 *                 of one base interval of 1 or 7 days, else 0: no RRULE),
 * ).
 * The base interval is the smallest gap; a longer gap (a skipped week)
 * becomes an EXDATE in the .ics. With a single upcoming date, MEC's own
 * repeat type decides.
 */
function djdt_events_recurrence( $post, $occs ) {
	$first   = $occs[0];
	$display = djdt_events_display_date( $first );
	$weekday = $display->format( 'l' );
	$time    = djdt_events_format_time_range( $first['start'], $first['end'] );

	$gaps = array();
	for ( $i = 1; $i < count( $occs ); $i++ ) {
		$gaps[] = (int) round( ( djdt_events_display_date( $occs[ $i ] )->setTime( 0, 0 )->getTimestamp() - djdt_events_display_date( $occs[ $i - 1 ] )->setTime( 0, 0 )->getTimestamp() ) / DAY_IN_SECONDS );
	}
	$base = $gaps ? min( $gaps ) : 0;
	if ( ! $gaps ) {
		$mec_date = get_post_meta( $post->ID, 'mec_date', true );
		$type     = ( is_array( $mec_date ) && isset( $mec_date['repeat']['type'] ) ) ? (string) $mec_date['repeat']['type'] : '';
		$base     = ( 'daily' === $type ) ? 1 : ( in_array( $type, array( 'weekly', 'certain_weekdays' ), true ) ? 7 : 0 );
	}
	$regular = $base > 0;
	foreach ( $gaps as $g ) {
		if ( $base <= 0 || 0 !== $g % $base ) {
			$regular = false;
		}
	}
	$interval = ( $regular && ( 1 === $base || 0 === $base % 7 ) ) ? $base : 0;

	if ( 1 === $interval ) {
		$words = 'Every day';
		$label = 'Daily';
	} elseif ( 7 === $interval ) {
		$words = 'Every ' . $weekday;
		$label = 'Weekly';
	} elseif ( 14 === $interval ) {
		$words = 'Every other ' . $weekday;
		$label = 'Every other week';
	} elseif ( $interval > 14 ) {
		$words = 'Every ' . ( $interval / 7 ) . ' weeks, ' . $weekday;
		$label = 'Every ' . ( $interval / 7 ) . ' weeks';
	} else {
		$same_day = true;
		foreach ( $occs as $o ) {
			if ( djdt_events_display_date( $o )->format( 'l' ) !== $weekday ) {
				$same_day = false;
			}
		}
		$words = $same_day ? $weekday . 's' : 'Regular dates';
		$label = 'Residency';
	}

	return array(
		'text'     => $words . ', ' . $time,
		'day'      => $display->format( 'D' ),
		'label'    => $label,
		'interval' => $interval,
	);
}

/** "Starts Fri, Nov 13" before the series has begun, else "Next: Fri, Nov 20". $short drops the weekday (the CD's corner tag). */
function djdt_events_residency_next_line( $res, $short = false ) {
	$d = djdt_events_display_date( $res['next'] );
	if ( $short ) {
		return ( $res['started'] ? 'Next ' : 'Starts ' ) . $d->format( 'M j' );
	}
	return ( $res['started'] ? 'Next: ' : 'Starts ' ) . $d->format( 'D, M j' );
}

/** The residency's "Add to calendar" link: the .ics endpoint below. */
function djdt_events_ics_url( $post_id ) {
	return add_query_arg( 'djdt_ics', (int) $post_id, home_url( '/' ) );
}

/**
 * ?djdt_ics=<event id>: an .ics file for a residency, so "Add to calendar"
 * adds the whole series, not one date. A regular series (every gap a whole
 * multiple of 1 or 7 days) is one VEVENT with an RRULE from the next date,
 * BYDAY on the real start weekday, EXDATEs for skipped dates, and an UNTIL
 * at the last known date unless MEC says the repeat never ends. Anything
 * else is one VEVENT per upcoming date. Times are local with a TZID and a
 * VTIMEZONE built from PHP's own transitions, so a 9 PM set stays 9 PM
 * across daylight saving; on a site set to a plain UTC offset (no named
 * zone) they are UTC instead. Behind djdt_is_active() like the rest.
 */
add_action( 'template_redirect', 'djdt_events_serve_ics', 1 );

function djdt_events_serve_ics() {
	if ( ! isset( $_GET['djdt_ics'] ) || ! djdt_is_active() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public read only download
		return;
	}
	$post_id = absint( $_GET['djdt_ics'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post    = get_post( $post_id );
	if ( ! $post || DJDT_EVENTS_MEC_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
		status_header( 404 );
		exit;
	}
	$occs = array();
	foreach ( djdt_events_get_upcoming_occurrences() as $occ ) {
		if ( $occ['post']->ID === $post_id ) {
			$occs[] = $occ;
		}
	}
	if ( ! $occs ) {
		status_header( 404 );
		exit;
	}
	$ics = djdt_events_build_ics( $post, $occs );
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'dj-digimark-' . $post->post_name ) . '.ics"' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput -- a calendar file, escaped per RFC 5545 inside
	exit;
}

/** Escapes a TEXT value per RFC 5545. */
function djdt_events_ics_text( $s ) {
	return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), (string) $s );
}

/** Folds a content line at 75 octets per RFC 5545, without splitting a UTF-8 character. */
function djdt_events_ics_fold( $line ) {
	$out = '';
	while ( strlen( $line ) > 75 ) {
		$cut = 75;
		while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
			$cut--;
		}
		$out  .= substr( $line, 0, $cut ) . "\r\n ";
		$line  = substr( $line, $cut );
	}
	return $out . $line;
}

function djdt_events_build_ics( $post, $occs ) {
	$tz      = wp_timezone();
	$tzid    = $tz->getName();
	$named   = (bool) preg_match( '#^[A-Za-z]+/#', $tzid ) || 'UTC' === $tzid;
	$venue   = djdt_events_get_venue( $post->ID );
	$name    = $venue ? $venue['name'] : get_the_title( $post );
	$address = $venue ? $venue['address'] : '';
	$host    = wp_parse_url( home_url(), PHP_URL_HOST );
	$utc     = new DateTimeZone( 'UTC' );
	$stamp   = gmdate( 'Ymd\THis\Z' );
	$rule    = djdt_events_recurrence( $post, $occs );

	$dt = function ( $d ) use ( $named, $tzid, $utc ) {
		return $named ? ';TZID=' . $tzid . ':' . $d->format( 'Ymd\THis' ) : ':' . $d->setTimezone( $utc )->format( 'Ymd\THis\Z' );
	};
	$common = array(
		'SUMMARY:' . djdt_events_ics_text( 'DJ DigiMark at ' . $name ),
		'URL:' . djdt_events_page_url(),
	);
	if ( $address ) {
		$common[] = 'LOCATION:' . djdt_events_ics_text( $address );
	}

	$lines = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DJ DigiMark//djdigi-theme//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . djdt_events_ics_text( 'DJ DigiMark at ' . $name ) );
	if ( $named && 'UTC' !== $tzid ) {
		$lines = array_merge( $lines, djdt_events_ics_vtimezone( $tz, $occs[0]['start'] ) );
	}

	if ( $rule['interval'] > 0 ) {
		$first = $occs[0];
		$by    = strtoupper( substr( $first['start']->format( 'D' ), 0, 2 ) );
		$rrule = 'RRULE:' . ( 1 === $rule['interval'] ? 'FREQ=DAILY' : 'FREQ=WEEKLY;INTERVAL=' . ( $rule['interval'] / 7 ) . ';BYDAY=' . $by );
		if ( 'never' !== (string) get_post_meta( $post->ID, 'mec_repeat_end', true ) ) {
			$rrule .= ';UNTIL=' . end( $occs )['start']->setTimezone( $utc )->format( 'Ymd\THis\Z' );
		}
		$event = array( 'BEGIN:VEVENT', 'UID:djdt-residency-' . $post->ID . '@' . $host, 'DTSTAMP:' . $stamp, 'DTSTART' . $dt( $first['start'] ), 'DTEND' . $dt( $first['end'] ), $rrule );
		// Skipped dates inside the series.
		for ( $i = 1; $i < count( $occs ); $i++ ) {
			$cursor = $occs[ $i - 1 ]['start']->modify( '+' . $rule['interval'] . ' days' );
			while ( $cursor->format( 'Y-m-d' ) < $occs[ $i ]['start']->format( 'Y-m-d' ) ) {
				$event[] = 'EXDATE' . $dt( $cursor );
				$cursor  = $cursor->modify( '+' . $rule['interval'] . ' days' );
			}
		}
		$lines = array_merge( $lines, $event, $common, array( 'END:VEVENT' ) );
	} else {
		foreach ( $occs as $occ ) {
			$lines = array_merge( $lines, array( 'BEGIN:VEVENT', 'UID:djdt-' . $post->ID . '-' . $occ['start']->format( 'Ymd' ) . '@' . $host, 'DTSTAMP:' . $stamp, 'DTSTART' . $dt( $occ['start'] ), 'DTEND' . $dt( $occ['end'] ) ), $common, array( 'END:VEVENT' ) );
		}
	}
	$lines[] = 'END:VCALENDAR';
	return implode( "\r\n", array_map( 'djdt_events_ics_fold', $lines ) ) . "\r\n";
}

/**
 * A VTIMEZONE for $tz: the zone's standard and daylight rules, read from
 * PHP's own transition table for the year around $around, each as a yearly
 * RRULE (BYMONTH plus the nth or last weekday, the same shape as the US and
 * EU rules). A zone without daylight saving gets one STANDARD block.
 */
function djdt_events_ics_vtimezone( $tz, $around ) {
	$from  = $around->getTimestamp() - 400 * DAY_IN_SECONDS;
	$trans = $tz->getTransitions( $from, $around->getTimestamp() + 400 * DAY_IN_SECONDS );
	$out   = array( 'BEGIN:VTIMEZONE', 'TZID:' . $tz->getName() );
	$fmt   = function ( $sec ) {
		$sign = $sec < 0 ? '-' : '+';
		$sec  = abs( $sec );
		return $sign . sprintf( '%02d%02d', floor( $sec / 3600 ), ( $sec % 3600 ) / 60 );
	};
	$seen = array();
	$prev = is_array( $trans ) && $trans ? $trans[0] : null;
	if ( is_array( $trans ) ) {
		for ( $i = 1; $i < count( $trans ); $i++ ) {
			$t    = $trans[ $i ];
			$kind = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';
			if ( isset( $seen[ $kind ] ) ) {
				$prev = $t;
				continue;
			}
			$seen[ $kind ] = true;
			$local         = ( new DateTimeImmutable( '@' . ( $t['ts'] + $prev['offset'] ) ) );
			$day           = (int) $local->format( 'j' );
			$nth           = ( $day + 7 > (int) $local->format( 't' ) ) ? -1 : (int) ceil( $day / 7 );
			$out           = array_merge( $out, array(
				'BEGIN:' . $kind,
				'DTSTART:' . $local->format( 'Ymd\THis' ),
				'TZOFFSETFROM:' . $fmt( $prev['offset'] ),
				'TZOFFSETTO:' . $fmt( $t['offset'] ),
				'TZNAME:' . $t['abbr'],
				'RRULE:FREQ=YEARLY;BYMONTH=' . (int) $local->format( 'n' ) . ';BYDAY=' . $nth . strtoupper( substr( $local->format( 'D' ), 0, 2 ) ),
				'END:' . $kind,
			) );
			$prev = $t;
		}
	}
	if ( ! $seen ) {
		$off = $tz->getOffset( $around );
		$out = array_merge( $out, array( 'BEGIN:STANDARD', 'DTSTART:19700101T000000', 'TZOFFSETFROM:' . $fmt( $off ), 'TZOFFSETTO:' . $fmt( $off ), 'END:STANDARD' ) );
	}
	$out[] = 'END:VTIMEZONE';
	return $out;
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

	// v1.15.1: residencies are grouped out of the date list (see
	// "Residencies" above). The row puts each residency first as one CD,
	// then the next one off dates up to the limit; the events page shows
	// the residencies as featured cards, then "More dates".
	$upcoming = djdt_events_get_upcoming_occurrences();
	$split    = djdt_events_split( $upcoming );

	wp_enqueue_style( 'djdt-events' );
	wp_enqueue_script( 'djdt-events' );

	ob_start();
	if ( empty( $upcoming ) ) {
		djdt_events_render_empty_state();
	} elseif ( $is_row ) {
		$items = array();
		foreach ( $split['residencies'] as $res ) {
			$items[] = array( 'occ' => $res['next'], 'res' => $res );
		}
		foreach ( $split['singles'] as $occ ) {
			$items[] = array( 'occ' => $occ, 'res' => null );
		}
		$items = array_slice( $items, 0, $limit );
		djdt_events_render_grid( $items, true );
		djdt_events_render_json_ld( wp_list_pluck( $items, 'occ' ) );
	} else {
		djdt_events_render_page( $split );
		djdt_events_render_json_ld( $upcoming );
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

/** $items: array( 'occ' => occurrence, 'res' => residency or null ), see djdt_events_shortcode(). */
function djdt_events_render_grid( $items, $is_row ) {
	?>
	<div class="djd-events">
		<?php if ( $is_row ) : ?>
			<h2 class="djd-section-heading"><?php echo djdt_heading_html( 'Upcoming events' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></h2>
		<?php endif; ?>
		<div class="djd-events__track">
			<?php foreach ( $items as $i => $item ) : ?>
				<?php djdt_events_render_card( $item['occ'], $i, $item['res'] ); ?>
			<?php endforeach; ?>
		</div>
		<?php if ( $is_row ) : ?>
			<a class="djd-seeall djd-events__seeall" href="<?php echo esc_url( djdt_events_page_url() ); ?>">See all dates <span class="djd-seeall__arrow" aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The events page (no limit, v1.15.1): one featured card per residency,
 * then "More dates" and the one off dates as CDs in a 4/3/2/1 column grid
 * (events.css, .djd-events__track--page). No residencies: just the grid.
 * No one off dates: no "More dates".
 */
function djdt_events_render_page( $split ) {
	?>
	<div class="djd-events djd-events--page">
		<?php if ( $split['residencies'] ) : ?>
			<div class="djd-events__residencies">
				<?php foreach ( $split['residencies'] as $res ) : ?>
					<?php djdt_events_render_residency( $res ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $split['singles'] ) : ?>
			<?php if ( $split['residencies'] ) : ?>
				<h2 class="djd-section-heading djd-events__more"><?php echo djdt_heading_html( 'More dates' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></h2>
			<?php endif; ?>
			<div class="djd-events__track djd-events__track--page">
				<?php foreach ( $split['singles'] as $i => $occ ) : ?>
					<?php djdt_events_render_card( $occ, $i ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * One residency's featured card (v1.15.1), in the CD case language: a case
 * with the weekday in place of a date and the disc sliding out from behind
 * it, then the venue, the recurrence, the starts/next line, the cyan
 * address block and the three pills (the .ics series, directions, share).
 */
function djdt_events_render_residency( $res ) {
	$post    = $res['post'];
	$venue   = djdt_events_get_venue( $post->ID );
	$name    = $venue ? $venue['name'] : get_the_title( $post );
	$address = $venue ? $venue['address'] : '';
	$logo    = $venue ? $venue['logo'] : '';
	$img     = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
	$next    = djdt_events_residency_next_line( $res );
	?>
	<article class="djd-res" aria-label="<?php echo esc_attr( $name . ', ' . $res['rule']['text'] ); ?>">
		<div class="djd-res__media" aria-hidden="true">
			<div class="djd-res__disc"><div class="djd-cd__disc-face" <?php if ( $img ) : ?>style="--djd-cd-disc-img: url('<?php echo esc_url( $img ); ?>');"<?php endif; ?>></div></div>
			<div class="djd-res__case">
				<?php if ( $img ) : ?>
					<img class="djd-cd__case-photo skip-lazy" src="<?php echo esc_url( $img ); ?>" alt="" data-no-lazy="1" decoding="async">
				<?php endif; ?>
				<div class="djd-cd__case-spine"></div>
				<div class="djd-cd__case-inner">
					<div class="djd-cd__case-date">
						<span class="djd-cd__case-day-num"><?php echo esc_html( strtoupper( $res['rule']['day'] ) ); ?></span><span class="djd-cd__case-day-label"><?php echo esc_html( $res['rule']['label'] ); ?></span>
					</div>
					<?php if ( $logo ) : ?>
						<img class="djd-cd__case-logo skip-lazy" src="<?php echo esc_url( $logo ); ?>" alt="" data-no-lazy="1" decoding="async">
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="djd-res__body">
			<span class="djd-res__tag">Residency</span>
			<h3 class="djd-res__venue"><?php echo esc_html( $name ); ?></h3>
			<p class="djd-res__rule"><?php echo esc_html( $res['rule']['text'] ); ?></p>
			<p class="djd-res__next"><?php echo esc_html( $next ); ?></p>
			<?php if ( $address ) : ?>
				<a class="djd-res__address" href="<?php echo esc_url( djdt_events_maps_search_url( $address ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $address ); ?></a>
			<?php endif; ?>
			<div class="djd-res__pills">
				<a class="djd-res__pill" href="<?php echo esc_url( djdt_events_ics_url( $post->ID ) ); ?>" download><span class="djd-cd__disc-pill-num">01</span>Add to calendar</a>
				<?php if ( $address ) : ?>
					<a class="djd-res__pill" href="<?php echo esc_url( djdt_events_directions_url( $address ) ); ?>" target="_blank" rel="noopener"><span class="djd-cd__disc-pill-num">02</span>Directions</a>
				<?php endif; ?>
				<button type="button" class="djd-res__pill djd-res__share" data-share-title="<?php echo esc_attr( 'DJ DigiMark at ' . $name . ', ' . $res['rule']['text'] ); ?>" data-share-url="<?php echo esc_url( djdt_events_page_url() ); ?>"><span class="djd-cd__disc-pill-num">03</span><span class="djd-cd__disc-pill-label">Share with a friend</span></button>
			</div>
		</div>
	</article>
	<?php
}

/**
 * "Tonight" / "Tomorrow" / "In N days": whole calendar days between today
 * and the occurrence's own display date, site timezone, never the raw
 * start/end used for sorting or the upcoming check.
 */
function djdt_events_when_tag( $display ) {
	$today = current_datetime()->setTime( 0, 0, 0 );
	$day   = $display->setTime( 0, 0, 0 );
	$days  = (int) round( ( $day->getTimestamp() - $today->getTimestamp() ) / DAY_IN_SECONDS );
	if ( $days <= 0 ) {
		return 'Tonight';
	}
	if ( 1 === $days ) {
		return 'Tomorrow';
	}
	return 'In ' . $days . ' days';
}

function djdt_events_calendar_url( $venue_name, $start, $end, $address ) {
	$utc_start = $start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	$utc_end   = $end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	$url       = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
		. '&text=' . rawurlencode( 'DJ DigiMark at ' . $venue_name )
		. '&dates=' . $utc_start . '/' . $utc_end;
	if ( $address ) {
		$url .= '&location=' . rawurlencode( $address );
	}
	return $url;
}

function djdt_events_directions_url( $address ) {
	if ( '' === $address ) {
		return '';
	}
	return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $address );
}

function djdt_events_maps_search_url( $address ) {
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
}

/**
 * v1.9.1: rebuilt to match reference/events-cd-reference.html exactly, the
 * file Mark approved live on Home v2 (see that file's own top comment).
 * v1.9.0 reimplemented the same idea from the written brief alone and got
 * the disc's own positioning wrong (translate(-50%,-50%) stacked with the
 * open/close keyframes, which is what made the disc fail to land anywhere
 * visible) along with several smaller value mismatches (the glow, the
 * case's padding and shadows, the disc label's own insets). This version
 * carries the reference's class names over 1:1 (renamed to this theme's
 * djd-cd prefix) and keeps every size, color, shadow, timing and keyframe
 * exactly as that file has them; events.css and events.js are this
 * function's own counterpart.
 *
 * v1.9.2, found live: site.css's global `body.djdigi-theme a { color:
 * var(--djd-violet); }` was beating the disc's own link colors (a plain
 * class selector loses to an element-plus-class one regardless of load
 * order), so events.css now forces every disc text color with !important,
 * the same pattern already used for the footer dock (djdigi-theme.php,
 * .djd-dock__btn). The disc's own text also now sizes off the disc itself
 * (container-type: inline-size, cqi units) instead of the viewport. Also
 * added here: the venue's own logo, from MEC's own 'thumbnail' location
 * term meta, above the venue name on the case front when that location
 * has one set.
 *
 * v1.10.0: the v1.9.2 solid plate behind the disc's text (.djd-cd__disc-
 * plate, a flat dark circle) is gone; .djd-cd__disc-face's own layered
 * background (events.css) now does the whole job itself, a center hub
 * (hole, silver rings), a faint rainbow sheen, and the photo as printed
 * label art, close enough to the hub's own edge that the top/bottom text
 * blocks (events.css, top 9%/height 27%, bottom from 64%) never overlap
 * it, keeping the disc's own markup here unchanged, no new element.
 */
/**
 * v1.15.1: $res (a residency, djdt_events_split()) makes this the
 * residency's one CD in the homepage row: the weekday ("FRI") and its
 * label ("Weekly") in place of the date, "Starts Nov 13" / "Next Nov 20"
 * in the corner, and on the disc the recurrence, the starts/next line and
 * an "Add to calendar" that downloads the whole series (.ics).
 * Both images carry skip-lazy / data-no-lazy (v1.15.1, found live: covers
 * on the events page stayed empty): Avada's own lazy loader rewrites
 * images in the content to a placeholder, and some covers never got their
 * photo back. The browser's own loading="lazy" still defers them until
 * they scroll near.
 */
function djdt_events_render_card( $occ, $i, $res = null ) {
	$post        = $occ['post'];
	$venue       = djdt_events_get_venue( $post->ID );
	$venue_name  = $venue ? $venue['name'] : get_the_title( $post );
	$address     = $venue ? $venue['address'] : '';
	$logo        = $venue ? $venue['logo'] : '';
	$display     = djdt_events_display_date( $occ );
	$time_line   = djdt_events_format_time_range( $occ['start'], $occ['end'] );
	$when        = djdt_events_when_tag( $display );
	$img         = get_the_post_thumbnail_url( $post->ID, 'medium_large' );
	$play_label  = sprintf( 'Show details for %s, %s', $venue_name, $display->format( 'M j' ) );
	$glow_dur    = array( '2.6s', '2.9s', '3.2s' )[ $i % 3 ];
	$share_title = sprintf( 'DJ DigiMark at %s, %s %s, %s', $venue_name, $display->format( 'D' ), $display->format( 'M j' ), $time_line );
	$day_num     = $display->format( 'j' );
	$day_label   = $display->format( 'D' ) . ' · ' . $display->format( 'M' );
	$disc_when   = $display->format( 'D M j' ) . ' · ' . $time_line;
	$disc_next   = '';
	$cal_url     = djdt_events_calendar_url( $venue_name, $occ['start'], $occ['end'], $address );
	if ( $res ) {
		$day_num     = strtoupper( $res['rule']['day'] );
		$day_label   = $res['rule']['label'];
		$when        = djdt_events_residency_next_line( $res, true );
		$disc_when   = $res['rule']['text'];
		$disc_next   = djdt_events_residency_next_line( $res );
		$cal_url     = djdt_events_ics_url( $post->ID );
		$play_label  = sprintf( 'Show details for %s, %s', $venue_name, $res['rule']['text'] );
		$share_title = sprintf( 'DJ DigiMark at %s, %s', $venue_name, $res['rule']['text'] );
	}
	?>
	<div class="djd-cd<?php echo $res ? ' djd-cd--residency' : ''; ?>" style="--djd-cd-glow-dur: <?php echo esc_attr( $glow_dur ); ?>;">
		<div class="djd-cd__case">
			<?php if ( $img ) : ?>
				<img class="djd-cd__case-photo skip-lazy" src="<?php echo esc_url( $img ); ?>" alt="" data-no-lazy="1" loading="lazy" decoding="async">
			<?php endif; ?>
			<div class="djd-cd__case-spine" aria-hidden="true"></div>
			<div class="djd-cd__case-inner">
				<div class="djd-cd__case-date" aria-hidden="true">
					<span class="djd-cd__case-day-num"><?php echo esc_html( $day_num ); ?></span><span class="djd-cd__case-day-label"><?php echo esc_html( $day_label ); ?></span>
				</div>
				<div>
					<?php if ( $logo ) : ?>
						<img class="djd-cd__case-logo skip-lazy" src="<?php echo esc_url( $logo ); ?>" alt="" data-no-lazy="1" loading="lazy" decoding="async">
					<?php endif; ?>
					<div class="djd-cd__case-venue"><?php echo esc_html( $venue_name ); ?></div>
					<div class="djd-cd__case-time"><?php echo esc_html( $time_line ); ?></div>
				</div>
			</div>
			<span class="djd-cd__case-when"><?php echo esc_html( $when ); ?></span>
			<button
				type="button"
				class="djd-cd__play"
				aria-expanded="false"
				aria-label="<?php echo esc_attr( $play_label ); ?>"
			>
				<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4 L20 12 L6 20 Z" fill="<?php echo esc_attr( djdt_events_ink_on_cyan() ); ?>"/></svg>
			</button>
		</div>
		<div class="djd-cd__disc">
			<div class="djd-cd__disc-face" <?php if ( $img ) : ?>style="--djd-cd-disc-img: url('<?php echo esc_url( $img ); ?>');"<?php endif; ?>></div>
			<div class="djd-cd__disc-text">
				<div class="djd-cd__disc-top">
					<div class="djd-cd__disc-venue"><?php echo esc_html( $venue_name ); ?></div>
					<div class="djd-cd__disc-when"><?php echo esc_html( $disc_when ); ?></div>
					<?php if ( $disc_next ) : ?>
						<div class="djd-cd__disc-when"><?php echo esc_html( $disc_next ); ?></div>
					<?php endif; ?>
					<?php if ( $address ) : ?>
						<a class="djd-cd__disc-address" href="<?php echo esc_url( djdt_events_maps_search_url( $address ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $address ); ?></a>
					<?php endif; ?>
				</div>
				<div class="djd-cd__disc-bottom">
					<a href="<?php echo esc_url( $cal_url ); ?>"<?php echo $res ? ' download' : ' target="_blank" rel="noopener"'; ?>><span class="djd-cd__disc-pill-num">01</span>Add to calendar</a>
					<?php if ( $address ) : ?>
						<a href="<?php echo esc_url( djdt_events_directions_url( $address ) ); ?>" target="_blank" rel="noopener"><span class="djd-cd__disc-pill-num">02</span>Directions</a>
					<?php endif; ?>
					<button type="button" class="djd-cd__disc-share" data-share-title="<?php echo esc_attr( $share_title ); ?>" data-share-url="<?php echo esc_url( djdt_events_page_url() ); ?>"><span class="djd-cd__disc-pill-num">03</span><span class="djd-cd__disc-pill-label">Share with a friend</span></button>
				</div>
			</div>
		</div>
		<?php // v1.14.3: the "put back" button on the open disc's rim (events.css), not inside the disc so it never spins or scales with it. ?>
		<button type="button" class="djd-cd__putback" aria-label="Put the CD back"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5 L19 19 M19 5 L5 19" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/></svg></button>
	</div>
	<?php
}

/**
 * The specific "ink on cyan" color the reference uses for the when-tag
 * text and every pill's hover state (#06121a), a touch darker than
 * --djd-night and used only against the cyan background, never reused as
 * a general night color elsewhere.
 */
function djdt_events_ink_on_cyan() {
	return '#06121a';
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
