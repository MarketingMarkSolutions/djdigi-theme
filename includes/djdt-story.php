<?php
/**
 * The Story timeline (v1.16.0): DigiMark > Story (residencies and moments)
 * and [djdigi_story], which renders it as a waveform strip split into one
 * chapter per residency (labeled by venue, never by year), lane bars under
 * it where residencies overlap in time, and a snap scrolling track of
 * cards below. assets/css/story.css, assets/js/story.js. See CLAUDE.md,
 * "v1.16.0".
 *
 * Saved in the main settings option under 'story' (the Story screen posts
 * one hidden JSON field per list, built by admin.js, the same pattern as
 * Venues). The default is the seed: the seven residencies from the live
 * About page, word for word, and no moments. Once the Story screen (or
 * any screen) is saved, the saved copy is what shows.
 *
 * @package djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'djdigi_story', 'djdt_story_shortcode' );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	wp_register_style( 'djdt-story', DJDT_URL . 'assets/css/story.css', array( 'djdt-site' ), djdt_ver( 'assets/css/story.css' ) );
	wp_register_script( 'djdt-story', DJDT_URL . 'assets/js/story.js', array(), djdt_ver( 'assets/js/story.js' ), true );
}, 20 );

/** The seed: the Residencies list on the live About page (page 11), names, years and text as they appear there. */
function djdt_story_seed() {
	$r = function ( $id, $name, $location, $start, $end, $text, $p2 = array() ) {
		return array(
			'id'       => $id,
			'name'     => $name,
			'location' => $location,
			'start'    => $start,
			'end'      => 'present' === $end ? 0 : $end,
			'present'  => 'present' === $end ? 1 : 0,
			'p2_label' => isset( $p2[0] ) ? $p2[0] : '',
			'p2_start' => isset( $p2[1] ) ? $p2[1] : 0,
			'p2_end'   => isset( $p2[2] ) ? $p2[2] : 0,
			'text'     => $text,
			'photo'    => 0,
		);
	};
	return array(
		'residencies' => array(
			$r( 'throwbacks', 'Throwbacks Nightclub', 'Grand Rapids, MI', 2008, 2009, 'Stepped in as resident DJ after my good friend DJ MichaelAngelo moved on, and played every weekend for a year until the club suddenly closed.' ),
			$r( 'diversions', 'Diversions Nightclub', 'Grand Rapids, MI', 2010, 2010, "Resident video DJ in the video bar for six months, at one of Grand Rapids\u{2019} most iconic and longest running LGBTQ+ nightclubs." ),
			$r( 'metro', 'Metro Nightclub', 'Kalamazoo, MI', 2010, 2016, 'Resident DJ every Friday and Saturday from 2010 to 2014, until the club temporarily closed, then part of the DJ rotation through 2016 after it reopened.', array( 'DJ rotation', 2014, 2016 ) ),
			$r( 'necto', 'Necto Nightclub', 'Ann Arbor, MI', 2016, 2026, "Resident DJ in the Red Room, one of Michigan\u{2019}s premier nightclubs, home to the longest running Pride night in Michigan, with a history dating back to the early 80s. I played the final night in the Red Room on July 3, 2026." ),
			$r( 'dunes', 'The Dunes Resort', 'Saugatuck, MI', 2022, 2026, "Regular summer DJ for pool parties, tea dances and the indoor dance floor at one of the Midwest\u{2019}s top LGBTQ+ resorts." ),
			$r( 'apartment', 'Apartment Lounge', 'Grand Rapids, MI', 2023, 2026, "Resident video DJ, playing several Saturdays a month at Michigan\u{2019}s oldest gay bar." ),
			$r( 'midnight-sun', 'Midnight Sun', 'San Francisco', 2026, 'present', 'Resident DJ every Friday, 9 PM to 1 AM.' ),
		),
		'moments'     => array(),
	);
}

/** The saved story (or the seed), both lists always present. */
function djdt_story() {
	$story = djdt_get( 'story' );
	if ( ! is_array( $story ) ) {
		$story = djdt_story_seed();
	}
	return array(
		'residencies' => isset( $story['residencies'] ) && is_array( $story['residencies'] ) ? array_values( $story['residencies'] ) : array(),
		'moments'     => isset( $story['moments'] ) && is_array( $story['moments'] ) ? array_values( $story['moments'] ) : array(),
	);
}

/** A year field: 1900 to 2100, else 0 (empty). */
function djdt_story_year( $v ) {
	$v = (int) $v;
	return ( $v >= 1900 && $v <= 2100 ) ? $v : 0;
}

/** A photo field: an image attachment id, else 0. */
function djdt_story_photo( $v ) {
	$id = (int) $v;
	return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/** Sanitizes the Story screen's two hidden JSON fields (admin.js) into the 'story' setting. */
function djdt_story_sanitize( $in ) {
	$out  = array( 'residencies' => array(), 'moments' => array() );
	$ids  = array();
	$raw  = isset( $in['story_res_json'] ) ? json_decode( $in['story_res_json'], true ) : array();
	foreach ( is_array( $raw ) ? array_slice( $raw, 0, 60 ) : array() as $row ) {
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		if ( '' === $name ) {
			continue;
		}
		$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
		if ( '' === $id || isset( $ids[ $id ] ) ) {
			$id = 'r' . substr( md5( $name . count( $ids ) . wp_rand() ), 0, 8 );
		}
		$ids[ $id ] = true;
		$start      = djdt_story_year( isset( $row['start'] ) ? $row['start'] : 0 );
		$present    = empty( $row['present'] ) ? 0 : 1;
		$end        = $present ? 0 : djdt_story_year( isset( $row['end'] ) ? $row['end'] : 0 );
		if ( ! $present && $start && $end && $end < $start ) {
			$end = $start;
		}
		$out['residencies'][] = array(
			'id'       => $id,
			'name'     => $name,
			'location' => isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '',
			'start'    => $start,
			'end'      => $end,
			'present'  => $present,
			'p2_label' => isset( $row['p2_label'] ) ? sanitize_text_field( $row['p2_label'] ) : '',
			'p2_start' => djdt_story_year( isset( $row['p2_start'] ) ? $row['p2_start'] : 0 ),
			'p2_end'   => djdt_story_year( isset( $row['p2_end'] ) ? $row['p2_end'] : 0 ),
			'text'     => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
			'photo'    => djdt_story_photo( isset( $row['photo'] ) ? $row['photo'] : 0 ),
		);
	}
	$raw = isset( $in['story_mom_json'] ) ? json_decode( $in['story_mom_json'], true ) : array();
	foreach ( is_array( $raw ) ? array_slice( $raw, 0, 200 ) : array() as $i => $row ) {
		$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
		$text  = isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '';
		if ( '' === $title && '' === $text ) {
			continue;
		}
		$res = isset( $row['res'] ) ? sanitize_key( $row['res'] ) : '';
		$out['moments'][] = array(
			'id'    => 'm' . $i,
			'title' => $title,
			'text'  => $text,
			'photo' => djdt_story_photo( isset( $row['photo'] ) ? $row['photo'] : 0 ),
			'year'  => djdt_story_year( isset( $row['year'] ) ? $row['year'] : 0 ),
			'res'   => isset( $ids[ $res ] ) ? $res : '',
		);
	}
	return $out;
}

/** The rows as admin.js edits them: the saved fields plus each photo's thumbnail URL (never saved). */
function djdt_story_admin_rows( $rows ) {
	return array_map( function ( $row ) {
		$row['photo_url'] = ! empty( $row['photo'] ) ? (string) wp_get_attachment_thumb_url( (int) $row['photo'] ) : '';
		return $row;
	}, $rows );
}

function djdt_render_settings_story() {
	djdt_render_settings_page( 'djdigi-theme-story', 'story', function () {
		$story = djdt_story();
		$n     = DJDT_OPTION;
		?>
		<tr>
			<th scope="row">Residencies</th>
			<td>
				<div id="djdt-story-res" class="djdt-rows"></div>
				<p><button type="button" class="button" id="djdt-story-res-add">Add residency</button></p>
				<input type="hidden" id="djdt-story-res-json" name="<?php echo esc_attr( $n ); ?>[story_res_json]" value="<?php echo esc_attr( wp_json_encode( djdt_story_admin_rows( $story['residencies'] ) ) ); ?>">
				<p class="description">One chapter each on the Story strip, labeled with the venue name. The strip runs oldest to newest by start year; for the same start year, the order here decides (use the arrows). End year empty with "present" checked means it is still going. Second phase: an optional later part of the same residency, for example "DJ rotation" 2014 to 2016, shown as a lighter bar on the same lane. Years only show small on the cards.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Moments</th>
			<td>
				<div id="djdt-story-mom" class="djdt-rows"></div>
				<p><button type="button" class="button" id="djdt-story-mom-add">Add moment</button></p>
				<input type="hidden" id="djdt-story-mom-json" name="<?php echo esc_attr( $n ); ?>[story_mom_json]" value="<?php echo esc_attr( wp_json_encode( djdt_story_admin_rows( $story['moments'] ) ) ); ?>">
				<p class="description">Smaller cards that follow their residency's card, in the order here. A moment with no residency goes after the residency it falls in by year (no year: at the end). Save after adding a residency to pick it here.</p>
			</td>
		</tr>
		<tr>
			<td colspan="2"><p>Shortcode: <code>[djdigi_story]</code>. <code>[djdigi_about]</code> already includes it.</p></td>
		</tr>
		<?php
	} );
}

/** "2008–2009", "2010" (one year), "2026–present", '' if no start year. */
function djdt_story_years( $start, $end, $present = 0 ) {
	$start = (int) $start;
	$end   = (int) $end;
	if ( ! $start ) {
		return '';
	}
	if ( $present ) {
		return $start . "\u{2013}present";
	}
	return ( ! $end || $end === $start ) ? (string) $start : $start . "\u{2013}" . $end;
}

/**
 * The strip's layout. Chapters are equal width, one per residency in time
 * order (start year, then the saved order). Time is mapped onto them piece
 * by piece: chapter k covers the years from its own start to the next
 * chapter's start, so a bar can end part way into a later chapter. Ends
 * are year boundaries (Metro 2010 to 2014 then 2014 to 2016); a residency
 * inside one year (start = end) is drawn as half a year; "present" runs to
 * the strip's end. Bars go into the first lane that is free at their
 * start, so overlapping residencies get parallel lanes.
 *
 * Returns array( residencies in strip order, bars: array of
 * array( chapter, lane, x0, x1, p2x ) in chapter units, lane count ).
 */
function djdt_story_layout( $residencies ) {
	$list = array();
	foreach ( array_values( $residencies ) as $i => $r ) {
		$r['_i'] = $i;
		$list[]  = $r;
	}
	usort( $list, function ( $a, $b ) {
		$sa = (int) $a['start'] ? (int) $a['start'] : 9999;
		$sb = (int) $b['start'] ? (int) $b['start'] : 9999;
		return $sa === $sb ? $a['_i'] - $b['_i'] : $sa - $sb;
	} );
	$n = count( $list );
	if ( ! $n ) {
		return array( array(), array(), 0 );
	}
	$starts = array();
	$last   = 0;
	foreach ( $list as $k => $r ) {
		$starts[ $k ] = (float) ( (int) $r['start'] ? (int) $r['start'] : ( $k ? $starts[ $k - 1 ] : 2000 ) );
		$last         = max( $last, $starts[ $k ], (float) (int) $r['end'] );
	}
	$last = max( $last, $starts[ $n - 1 ] + 1 );
	$x    = function ( $t, $from ) use ( $starts, $n, $last ) {
		if ( INF === $t ) {
			return (float) $n;
		}
		for ( $k = $from; $k < $n; $k++ ) {
			$lo = $starts[ $k ];
			$hi = $k + 1 < $n ? $starts[ $k + 1 ] : $last;
			if ( $k === $n - 1 || $t <= $hi ) {
				$span = $hi - $lo;
				$f    = $span > 0 ? ( $t - $lo ) / $span : 1;
				return $k + max( 0, min( 1, $f ) );
			}
		}
		return (float) $n;
	};
	$bars = array();
	foreach ( $list as $k => $r ) {
		$s   = $starts[ $k ];
		$end = ! empty( $r['present'] ) ? INF : (float) (int) $r['end'];
		if ( INF !== $end && $end <= $s ) {
			$end = $s + 0.5;
		}
		$p2x = null;
		if ( '' !== trim( (string) $r['p2_label'] ) && (int) $r['p2_start'] ) {
			$p2x = $x( max( $s, (float) (int) $r['p2_start'] ), $k );
			if ( (int) $r['p2_end'] && INF !== $end ) {
				$end = max( $end, (float) (int) $r['p2_end'] );
			}
		}
		$bars[] = array(
			'chapter' => $k,
			'x0'      => (float) $k,
			'x1'      => max( $k + 0.12, $x( $end, $k ) ),
			'p2x'     => $p2x,
		);
	}
	$lanes = array();
	foreach ( $bars as $b => $bar ) {
		$lane = 0;
		while ( isset( $lanes[ $lane ] ) && $lanes[ $lane ] > $bar['x0'] + 0.001 ) {
			$lane++;
		}
		$lanes[ $lane ]      = $bar['x1'];
		$bars[ $b ]['lane'] = $lane;
	}
	return array( $list, $bars, count( $lanes ) );
}

/** The cards in track order: each residency, then its moments; moments with no residency after the residency they fall in by year. */
function djdt_story_cards( $list, $moments ) {
	$by_res = array();
	$loose  = array();
	$ids    = wp_list_pluck( $list, 'id' );
	foreach ( $moments as $m ) {
		if ( '' !== $m['res'] && in_array( $m['res'], $ids, true ) ) {
			$by_res[ $m['res'] ][] = $m;
		} else {
			$loose[] = $m;
		}
	}
	$after = array();
	foreach ( $loose as $m ) {
		$k = count( $list ) - 1;
		if ( (int) $m['year'] ) {
			$k = -1;
			foreach ( $list as $i => $r ) {
				if ( (int) $r['start'] && (int) $r['start'] <= (int) $m['year'] ) {
					$k = $i;
				}
			}
		}
		$after[ $k ][] = $m;
	}
	$cards = array();
	foreach ( isset( $after[-1] ) ? $after[-1] : array() as $m ) {
		$cards[] = array( 'moment', $m, 0 );
	}
	foreach ( $list as $k => $r ) {
		$cards[] = array( 'residency', $r, $k );
		$mine    = array_merge( isset( $by_res[ $r['id'] ] ) ? $by_res[ $r['id'] ] : array(), isset( $after[ $k ] ) ? $after[ $k ] : array() );
		foreach ( $mine as $m ) {
			$cards[] = array( 'moment', $m, $k );
		}
	}
	return $cards;
}

function djdt_story_shortcode() {
	if ( ! djdt_is_active() ) {
		return '';
	}
	$story = djdt_story();
	list( $list, $bars, $lane_count ) = djdt_story_layout( $story['residencies'] );
	if ( ! $list ) {
		return '';
	}
	wp_enqueue_style( 'djdt-story' );
	wp_enqueue_script( 'djdt-story' );

	$n      = count( $list );
	$cards  = djdt_story_cards( $list, $story['moments'] );
	$newest = $n - 1;
	$pct    = function ( $v ) use ( $n ) {
		return round( $v / $n * 100, 4 ) . '%';
	};
	static $instance = 0;
	$instance++;
	$uid = 'djd-story-' . $instance;

	ob_start();
	?>
	<div class="djd-story" id="<?php echo esc_attr( $uid ); ?>" data-chapters="<?php echo (int) $n; ?>" data-start="<?php echo (int) $newest; ?>">
		<div class="djd-story__strip">
			<div class="djd-story__stage" style="--djd-story-n: <?php echo (int) $n; ?>; --djd-story-lanes: <?php echo (int) $lane_count; ?>;">
				<canvas class="djd-story__wave" aria-hidden="true"></canvas>
				<div class="djd-story__chapters">
					<?php foreach ( $list as $k => $r ) : ?>
						<button type="button" class="djd-story__chapter" data-chapter="<?php echo (int) $k; ?>" style="left: <?php echo esc_attr( $pct( $k ) ); ?>; width: <?php echo esc_attr( $pct( 1 ) ); ?>;" aria-controls="<?php echo esc_attr( $uid . '-track' ); ?>">
							<span class="djd-story__chapter-name"><?php echo esc_html( $r['name'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="djd-story__lanes" aria-hidden="true">
					<?php foreach ( $bars as $bar ) : ?>
						<?php
						$color = $bar['chapter'] % 3;
						$top   = 'top: calc(' . (int) $bar['lane'] . ' * var(--djd-story-lane));';
						$p1end = null !== $bar['p2x'] ? $bar['p2x'] : $bar['x1'];
						?>
						<span class="djd-story__bar is-c<?php echo (int) $color; ?>" data-chapter="<?php echo (int) $bar['chapter']; ?>" style="<?php echo esc_attr( $top . ' left: ' . $pct( $bar['x0'] ) . '; width: ' . $pct( $p1end - $bar['x0'] ) . ';' ); ?>"></span>
						<?php if ( null !== $bar['p2x'] && $bar['x1'] > $bar['p2x'] ) : ?>
							<span class="djd-story__bar is-c<?php echo (int) $color; ?> is-phase2" data-chapter="<?php echo (int) $bar['chapter']; ?>" style="<?php echo esc_attr( $top . ' left: ' . $pct( $bar['p2x'] ) . '; width: ' . $pct( $bar['x1'] - $bar['p2x'] ) . ';' ); ?>"></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<span class="djd-story__playhead" aria-hidden="true"><span class="djd-story__cap"></span></span>
			</div>
		</div>
		<div class="djd-story__nav">
			<button type="button" class="djd-story__arrow is-prev" aria-label="Previous card" aria-controls="<?php echo esc_attr( $uid . '-track' ); ?>"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5 8 12l7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
			<button type="button" class="djd-story__arrow is-next" aria-label="Next card" aria-controls="<?php echo esc_attr( $uid . '-track' ); ?>"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="m9 5 7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
		</div>
		<div class="djd-story__track" id="<?php echo esc_attr( $uid . '-track' ); ?>" tabindex="0" role="region" aria-label="My story, oldest to newest">
			<?php foreach ( $cards as $card ) : ?>
				<?php
				list( $kind, $row, $chapter ) = $card;
				$photo                        = ! empty( $row['photo'] ) ? wp_get_attachment_image_url( (int) $row['photo'], 'large' ) : '';
				?>
				<?php if ( 'residency' === $kind ) : ?>
					<article class="djd-story__card is-residency" data-chapter="<?php echo (int) $chapter; ?>">
						<?php if ( $photo ) : ?>
							<img class="djd-story__photo skip-lazy" src="<?php echo esc_url( $photo ); ?>" alt="" loading="lazy" decoding="async" data-no-lazy="1">
						<?php endif; ?>
						<div class="djd-story__body">
							<h3 class="djd-story__venue"><?php echo esc_html( $row['name'] ); ?></h3>
							<?php
							$meta = array_filter( array( $row['location'], djdt_story_years( $row['start'], $row['end'], $row['present'] ) ) );
							?>
							<?php if ( $meta ) : ?>
								<p class="djd-story__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $row['p2_label'] ) ) : ?>
								<p class="djd-story__meta is-phase2"><?php echo esc_html( trim( $row['p2_label'] . ' ' . djdt_story_years( $row['p2_start'], $row['p2_end'] ) ) ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $row['text'] ) ) : ?>
								<p class="djd-story__text"><?php echo esc_html( $row['text'] ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php else : ?>
					<article class="djd-story__card is-moment" data-chapter="<?php echo (int) $chapter; ?>">
						<?php if ( $photo ) : ?>
							<img class="djd-story__photo skip-lazy" src="<?php echo esc_url( $photo ); ?>" alt="" loading="lazy" decoding="async" data-no-lazy="1">
						<?php endif; ?>
						<div class="djd-story__body">
							<?php if ( (int) $row['year'] ) : ?>
								<p class="djd-story__meta"><?php echo (int) $row['year']; ?></p>
							<?php endif; ?>
							<?php if ( '' !== $row['title'] ) : ?>
								<h4 class="djd-story__title"><?php echo esc_html( $row['title'] ); ?></h4>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $row['text'] ) ) : ?>
								<p class="djd-story__text"><?php echo esc_html( $row['text'] ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
