<?php
/**
 * The About page (v1.16.0): DigiMark > About (every piece of copy, editable)
 * and [djdigi_about], which outputs the whole page: intro (photo, text, the
 * Contact / Book me record button), stats, video, where it started, remixes
 * and mashups, my story ([djdigi_story]), guest spots and radio, the Contact / Book me strip (.djx-cta) and the FAQ.
 * assets/css/about.css. The banner above it is the v1.15.0 inner page banner.
 * See CLAUDE.md, "v1.16.0".
 *
 * Saved in the main settings option under 'about'. The defaults are the
 * live About page's own copy, word for word (the intro text is Mark's new
 * text from the brief), so nothing changes until Mark edits it.
 *
 * @package djdigi-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'djdigi_about', 'djdt_about_shortcode' );

define( 'DJDT_ABOUT_PHOTO', 'https://djdigimark.com/wp-content/uploads/2026/05/DigiMarkRedRoom2026.jpg' );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! djdt_is_active() ) {
		return;
	}
	wp_register_style( 'djdt-about', DJDT_URL . 'assets/css/about.css', array( 'djdt-site' ), djdt_ver( 'assets/css/about.css' ) );
}, 20 );

function djdt_about_defaults() {
	return array(
		'photo'         => 0,
		'intro'         => "I'm DJ DigiMark, a video DJ based in San Francisco, with nearly two decades of club residencies, pride festivals and private events behind me. I mix Top 40 remixes, queer pop, house, EDM and 70s to 00s throwbacks, with custom remix videos I make myself playing on the screens in sync with the music.\n\nI play nightclubs, pride events, private parties and corporate events, and I'm the resident DJ at Midnight Sun every Friday starting November 13.",
		'stats'         => array(
			array( 'number' => '', 'label' => '' ),
			array( 'number' => '', 'label' => '' ),
			array( 'number' => '', 'label' => '' ),
		),
		'video_heading' => 'Video DJ',
		'video_text'    => "Video DJing is what sets my sets apart. I mix custom remix videos live alongside the music, so the screens move with the dance floor. I started making my own in 2009, back when good remix videos were hard to find, and I\u{2019}ve made more than 1,200 since. They\u{2019}re played in clubs from coast to coast and internationally, so if you\u{2019}ve been out dancing, there\u{2019}s a good chance you\u{2019}ve seen one.",
		'started_heading' => 'Where it started',
		'started_text'  => 'I started DJing in 2008 at Throwbacks Nightclub in Grand Rapids and spent nearly two decades in the Michigan and Midwest club scene, from local residencies to pride festivals and guest spots across the country, before heading west.',
		'remixes_heading' => 'Remixes & mashups',
		'remixes_lead'  => 'Alongside the videos, I make mashups and re-edits. A couple took off:',
		'remixes_list'  => implode( "\n", array(
			"Adam Lambert \u{201C}Trespassing\u{201D} (Remix) vs. Queen \u{201C}Another One Bites The Dust\u{201D}: retweeted by Adam Lambert, shared by Brian May of Queen, and featured on Ryan Seacrest\u{2019}s website.",
			"DJ MichaelAngelo\u{2019}s Glee vs. Katy Perry \u{201C}Teenage Dream\u{201D} mashup: shared by Perez Hilton on his blog, one of the top pop culture sites at the time.",
		) ),
		'story_heading' => 'My story',
		'guest_title'   => 'Guest spots',
		'guest_list'    => implode( "\n", array(
			'San Francisco (2026): The Cafe, Badlands, The Edge, Lookout, Beaux',
			"Billy\u{2019}s Nightclub, Grand Rapids, MI: Pride Night (2021), and resident DJ for Funky Electric Disco Night (2015)",
			"Creekridge Campground\u{2019}s 5-Year Anniversary (2021): Headlining DJ at one of Michigan\u{2019}s top LGBTQ+ campgrounds",
			"Roscoe\u{2019}s Tavern, Chicago (2015): Multiple guest appearances",
			'Miami Winter Music Conference (2014)',
			"Mixx Nightclub, Saginaw, MI (2013\u{2013}2014): Guest DJ",
			'Pride North Chicago: Annual DJ appearance for multiple years',
			'Motor City Pride, Detroit: Several performances over the years',
			'Parliament House, Orlando, FL: A couple of nights at the legendary club',
			'Rumors Nightclub, Grand Rapids, MI: Occasional guest DJ',
		) ),
		'radio_title'   => 'On the radio',
		'radio_lead'    => 'For more than 10 years I hosted a weekly radio show of high-energy house, EDM and Top 40 remixes, on stations including:',
		'radio_list'    => implode( "\n", array(
			'Nexus Radio (formerly Fusion Radio)',
			'DanceMixUSA',
			'Rhythm 105.9',
			'Power90.1',
			'Mix93FM',
			"Regular guest mixes on WSNX 104.5, Grand Rapids (2013\u{2013}2015)",
			"A retro dance show on B91 Classic, New York (2013\u{2013}2014)",
		) ),
		'cta_heading'   => 'Book DJ DigiMark',
		'cta_text'      => 'Available for nightclubs, pride events, private parties, and corporate events in San Francisco and beyond. Specializing in video DJing with custom remix visuals.',
		'faq_heading'   => 'Frequently Asked Questions',
		'faq'           => array(
			array( 'q' => 'Who is DJ DigiMark?', 'a' => 'DJ DigiMark is a San Francisco-based DJ specializing in video DJing, where custom remix videos are mixed live alongside the music. He started DJing in 2008 and spent nearly two decades in the Michigan and Midwest club scene before moving to San Francisco in 2026. His sets blend Top 40 remixes, queer pop, house, EDM and classic throwbacks.' ),
			array( 'q' => 'Where does DJ DigiMark perform?', 'a' => 'Starting November 13, 2026, DJ DigiMark is the resident DJ at Midnight Sun in San Francisco, every Friday from 9 PM to 1 AM. In San Francisco he has also played The Cafe, Badlands, The Edge, Lookout and Beaux. See Upcoming Events at djdigimark.com for all dates.' ),
			array( 'q' => 'What is video DJing?', 'a' => 'Video DJing combines live music mixing with custom remix videos shown on screens at the venue. The visuals sync with the music in real time, creating an immersive experience for the crowd. DJ DigiMark has been making custom remix videos since 2009 and has produced more than 1,200 of them.' ),
			array( 'q' => 'How do I book DJ DigiMark?', 'a' => "Use the contact form on this page. DJ DigiMark is available for nightclubs, pride events, private parties, corporate events and festivals. He's based in San Francisco and open to events in other locations." ),
			array( 'q' => 'What kind of music does DJ DigiMark play?', 'a' => 'Top 40 remixes, queer pop, house, EDM and 70s to 00s throwbacks. He also produces mashups and re-edits, blending familiar tracks into fresh mixes, and keeps the energy high across a wide range of styles.' ),
			array( 'q' => 'Does DJ DigiMark travel for events?', 'a' => "Yes. He's based in San Francisco and open to traveling for events. He has performed in San Francisco, Chicago, Detroit, Orlando and Miami, among others, along with nearly two decades of gigs across Michigan." ),
			array( 'q' => "What are DJ DigiMark's remix videos?", 'a' => "DJ DigiMark has produced custom remix videos since 2009, with an archive of more than 1,200. They're played in clubs across the country and internationally. Some of his mashups have been shared by major artists, including Adam Lambert retweeting his remix and Brian May of Queen sharing it, with a feature on Ryan Seacrest's website." ),
		),
	);
}

/** The About settings, saved values over the defaults (a key added later still gets its default). */
function djdt_about() {
	$saved = djdt_get( 'about' );
	$about = wp_parse_args( is_array( $saved ) ? $saved : array(), djdt_about_defaults() );
	$stats = array();
	for ( $i = 0; $i < 3; $i++ ) {
		$row     = isset( $about['stats'][ $i ] ) && is_array( $about['stats'][ $i ] ) ? $about['stats'][ $i ] : array();
		$stats[] = array(
			'number' => isset( $row['number'] ) ? (string) $row['number'] : '',
			'label'  => isset( $row['label'] ) ? (string) $row['label'] : '',
		);
	}
	$about['stats'] = $stats;
	$about['faq']   = is_array( $about['faq'] ) ? array_values( $about['faq'] ) : array();
	return $about;
}

/** Sanitizes the About screen into the 'about' setting. */
function djdt_about_sanitize( $in ) {
	$d   = djdt_about_defaults();
	$out = array();
	foreach ( array( 'video_heading', 'started_heading', 'remixes_heading', 'story_heading', 'guest_title', 'radio_title', 'cta_heading', 'faq_heading' ) as $key ) {
		$out[ $key ] = isset( $in[ 'about_' . $key ] ) ? sanitize_text_field( $in[ 'about_' . $key ] ) : $d[ $key ];
	}
	foreach ( array( 'intro', 'video_text', 'started_text', 'remixes_lead', 'remixes_list', 'guest_list', 'radio_lead', 'radio_list', 'cta_text' ) as $key ) {
		$out[ $key ] = isset( $in[ 'about_' . $key ] ) ? sanitize_textarea_field( $in[ 'about_' . $key ] ) : $d[ $key ];
	}
	$photo        = isset( $in['about_photo'] ) ? (int) $in['about_photo'] : 0;
	$out['photo'] = ( $photo > 0 && wp_attachment_is_image( $photo ) ) ? $photo : 0;
	$out['stats'] = array();
	for ( $i = 0; $i < 3; $i++ ) {
		$out['stats'][] = array(
			'number' => isset( $in['about_stat_number'][ $i ] ) ? sanitize_text_field( $in['about_stat_number'][ $i ] ) : '',
			'label'  => isset( $in['about_stat_label'][ $i ] ) ? sanitize_text_field( $in['about_stat_label'][ $i ] ) : '',
		);
	}
	$out['faq'] = array();
	$raw        = isset( $in['about_faq_json'] ) ? json_decode( $in['about_faq_json'], true ) : array();
	foreach ( is_array( $raw ) ? array_slice( $raw, 0, 40 ) : array() as $row ) {
		$q = isset( $row['q'] ) ? sanitize_text_field( $row['q'] ) : '';
		$a = isset( $row['a'] ) ? sanitize_textarea_field( $row['a'] ) : '';
		if ( '' !== $q && '' !== $a ) {
			$out['faq'][] = array( 'q' => $q, 'a' => $a );
		}
	}
	return $out;
}

/** The intro photo: the picked attachment, else the bundled default URL. */
function djdt_about_photo() {
	$about = djdt_about();
	if ( $about['photo'] ) {
		$src = wp_get_attachment_image_src( (int) $about['photo'], 'large' );
		if ( $src ) {
			$alt = trim( (string) get_post_meta( (int) $about['photo'], '_wp_attachment_image_alt', true ) );
			return array( $src[0], (int) $src[1], (int) $src[2], '' !== $alt ? $alt : 'DJ DigiMark' );
		}
	}
	return array( DJDT_ABOUT_PHOTO, 771, 755, 'DJ DigiMark' );
}

function djdt_render_settings_about() {
	djdt_render_settings_page( 'djdigi-theme-about', 'about', function () {
		$a = djdt_about();
		$n = DJDT_OPTION;
		$text = function ( $key, $label, $help = '' ) use ( $a, $n ) {
			?>
			<tr>
				<th scope="row"><label for="djdt-about-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<input id="djdt-about-<?php echo esc_attr( $key ); ?>" class="large-text" name="<?php echo esc_attr( $n ); ?>[about_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $a[ $key ] ); ?>">
					<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
				</td>
			</tr>
			<?php
		};
		$area = function ( $key, $label, $rows, $help = '' ) use ( $a, $n ) {
			?>
			<tr>
				<th scope="row"><label for="djdt-about-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<textarea id="djdt-about-<?php echo esc_attr( $key ); ?>" class="large-text" rows="<?php echo (int) $rows; ?>" name="<?php echo esc_attr( $n ); ?>[about_<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $a[ $key ] ); ?></textarea>
					<?php if ( $help ) : ?><p class="description"><?php echo esc_html( $help ); ?></p><?php endif; ?>
				</td>
			</tr>
			<?php
		};
		?>
		<tr>
			<td colspan="2"><p>Everything <code>[djdigi_about]</code> shows, top to bottom. Headings: the last word shows in cyan ("DJ DigiMark" counts as one word); use | to choose where the cyan starts.</p></td>
		</tr>
		<tr>
			<th scope="row">Intro photo</th>
			<td>
				<div id="djdt-about-photo"></div>
				<p>
					<button type="button" class="button" id="djdt-about-photo-pick">Select image</button>
					<button type="button" class="button" id="djdt-about-photo-remove">Remove</button>
				</p>
				<input type="hidden" id="djdt-about-photo-id" name="<?php echo esc_attr( $n ); ?>[about_photo]" value="<?php echo esc_attr( (int) $a['photo'] ); ?>">
				<p class="description">Leave empty to use DigiMarkRedRoom2026.jpg.</p>
			</td>
		</tr>
		<?php $area( 'intro', 'Intro text', 6, 'A blank line starts a new paragraph. The Contact / Book me button follows it.' ); ?>
		<tr>
			<th scope="row">Stats</th>
			<td>
				<?php foreach ( $a['stats'] as $i => $stat ) : ?>
					<p>
						<input class="small-text" style="width: 8em" name="<?php echo esc_attr( $n ); ?>[about_stat_number][<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $stat['number'] ); ?>" placeholder="Number" aria-label="Stat <?php echo (int) $i + 1; ?> number">
						<input class="regular-text" name="<?php echo esc_attr( $n ); ?>[about_stat_label][<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $stat['label'] ); ?>" placeholder="Label" aria-label="Stat <?php echo (int) $i + 1; ?> label">
					</p>
				<?php endforeach; ?>
				<p class="description">Up to three, for example 1,200+ and "remix videos". The row is hidden while all three are empty.</p>
			</td>
		</tr>
		<?php
		$text( 'video_heading', 'Video heading' );
		$area( 'video_text', 'Video text', 4, 'Shown above the 4 newest videos (the same videos as [djdigi_videos]).' );
		$text( 'started_heading', 'Where it started heading' );
		$area( 'started_text', 'Where it started text', 4, 'A blank line starts a new paragraph. Leave heading and text empty to hide this section.' );
		$text( 'remixes_heading', 'Remixes heading' );
		$area( 'remixes_lead', 'Remixes intro line', 2 );
		$area( 'remixes_list', 'Remixes, one per line', 4, 'The text before the first colon shows in bold (a line with no colon is all bold). Leave heading, intro and list empty to hide this section.' );
		$text( 'story_heading', 'Story heading', 'Above the Story timeline (DigiMark > Story).' );
		$text( 'guest_title', 'Guest spots title' );
		$area( 'guest_list', 'Guest spots, one per line', 10, 'The text before the first colon shows in bold (a line with no colon is all bold).' );
		$text( 'radio_title', 'On the radio title' );
		$area( 'radio_lead', 'On the radio intro line', 2 );
		$area( 'radio_list', 'On the radio, one per line', 7 );
		$text( 'cta_heading', 'Contact strip heading' );
		$area( 'cta_text', 'Contact strip text', 3, 'Next to the Contact / Book me button, above the FAQ.' );
		$text( 'faq_heading', 'FAQ heading' );
		?>
		<tr>
			<th scope="row">FAQ</th>
			<td>
				<div id="djdt-about-faq" class="djdt-rows"></div>
				<p><button type="button" class="button" id="djdt-about-faq-add">Add question</button></p>
				<input type="hidden" id="djdt-about-faq-json" name="<?php echo esc_attr( $n ); ?>[about_faq_json]" value="<?php echo esc_attr( wp_json_encode( $a['faq'] ) ); ?>">
				<p class="description">A question with no answer is not saved.</p>
			</td>
		</tr>
		<?php
	} );
}

/** Paragraphs from text with blank lines between them. */
function djdt_about_paragraphs( $text ) {
	$out = '';
	foreach ( preg_split( '/\n\s*\n/', trim( (string) $text ) ) as $para ) {
		$para = trim( $para );
		if ( '' !== $para ) {
			$out .= '<p>' . nl2br( esc_html( $para ), false ) . '</p>';
		}
	}
	return $out;
}

/** A one item per line list; $bold: the text up to the first colon is bold (a line with no colon all bold). */
function djdt_about_list( $text, $bold = false ) {
	$out = '';
	foreach ( preg_split( '/\r?\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( $bold ) {
			$at   = strpos( $line, ':' );
			$item = false === $at
				? '<strong>' . esc_html( $line ) . '</strong>'
				: '<strong>' . esc_html( substr( $line, 0, $at + 1 ) ) . '</strong> ' . esc_html( ltrim( substr( $line, $at + 1 ) ) );
		} else {
			$item = esc_html( $line );
		}
		$out .= '<li>' . $item . '</li>';
	}
	return '' !== $out ? '<ul>' . $out . '</ul>' : '';
}

function djdt_about_shortcode() {
	if ( ! djdt_is_active() ) {
		return '';
	}
	wp_enqueue_style( 'djdt-about' );
	$a      = djdt_about();
	$book   = trim( (string) djdt_get( 'book_url' ) );
	$button = djdt_record_button_html( '' !== $book ? $book : '#djx-pop' );
	list( $src, $w, $h, $alt ) = djdt_about_photo();
	$heading = function ( $tag, $text, $class ) {
		return '' === trim( $text ) ? '' : '<' . $tag . ' class="' . esc_attr( $class ) . '">' . djdt_heading_html( $text ) . '</' . $tag . '>';
	};
	$stats = array_filter( $a['stats'], function ( $s ) {
		return '' !== trim( $s['number'] ) || '' !== trim( $s['label'] );
	} );
	$videos   = function_exists( 'djdt_videos_cards' ) ? djdt_videos_cards() : null;
	$overlays = '';

	ob_start();
	?>
	<div class="djd-about">
		<section class="djd-about__intro">
			<figure class="djd-about__photo">
				<img class="skip-lazy" src="<?php echo esc_url( $src ); ?>" width="<?php echo (int) $w; ?>" height="<?php echo (int) $h; ?>" alt="<?php echo esc_attr( $alt ); ?>" data-no-lazy="1" decoding="async" fetchpriority="high">
			</figure>
			<div class="djd-about__lede">
				<?php echo djdt_about_paragraphs( $a['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<div class="djd-about__book"><?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
			</div>
		</section>

		<?php if ( $stats ) : ?>
			<section class="djd-about__stats" aria-label="In numbers">
				<?php foreach ( $stats as $stat ) : ?>
					<div class="djd-about__stat">
						<span class="djd-about__stat-number"><?php echo esc_html( $stat['number'] ); ?></span>
						<span class="djd-about__stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<section class="djd-about__video">
			<?php echo $heading( 'h2', $a['video_heading'], 'djd-section-heading djd-about__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<?php echo djdt_about_paragraphs( $a['video_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<?php if ( $videos ) : ?>
				<?php
				wp_enqueue_style( 'djdt-videos' );
				$overlays = $videos[1];
				?>
				<div class="djd-videos djd-videos--inline">
					<div class="djd-videos__cards"><?php echo $videos[0]; // phpcs:ignore WordPress.Security.EscapeOutput -- djdigi-library's own escaped renderer output ?></div>
					<a class="djd-seeall djd-videos__seeall" href="<?php echo esc_url( djdt_get( 'videos_url' ) ); ?>">See all videos <span class="djd-seeall__arrow" aria-hidden="true">→</span></a>
				</div>
			<?php endif; ?>
		</section>

		<?php if ( '' !== trim( $a['started_heading'] . $a['started_text'] ) ) : ?>
			<section class="djd-about__started">
				<?php echo $heading( 'h2', $a['started_heading'], 'djd-section-heading djd-about__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_paragraphs( $a['started_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			</section>
		<?php endif; ?>

		<?php if ( '' !== trim( $a['remixes_heading'] . $a['remixes_lead'] . $a['remixes_list'] ) ) : ?>
			<section class="djd-about__remixes">
				<?php echo $heading( 'h2', $a['remixes_heading'], 'djd-section-heading djd-about__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_paragraphs( $a['remixes_lead'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_list( $a['remixes_list'], true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			</section>
		<?php endif; ?>

		<section class="djd-about__story">
			<?php echo $heading( 'h2', $a['story_heading'], 'djd-section-heading djd-about__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<?php echo djdt_story_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</section>

		<section class="djd-about__cols">
			<div class="djd-about__col">
				<?php echo $heading( 'h3', $a['guest_title'], 'djd-about__subhead' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_list( $a['guest_list'], true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			</div>
			<div class="djd-about__col">
				<?php echo $heading( 'h3', $a['radio_title'], 'djd-about__subhead' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_paragraphs( $a['radio_lead'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_list( $a['radio_list'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			</div>
		</section>

		<section class="djx-cta">
			<div class="djx-cta__text">
				<?php echo $heading( 'h2', $a['cta_heading'], 'djd-section-heading djx-cta__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<?php echo djdt_about_paragraphs( $a['cta_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			</div>
			<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</section>

		<?php if ( $a['faq'] ) : ?>
			<section class="djd-about__faq">
				<?php echo $heading( 'h2', $a['faq_heading'], 'djd-section-heading djd-about__heading' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<div class="djd-faq">
					<?php foreach ( $a['faq'] as $item ) : ?>
						<details class="djd-faq__item" name="djd-faq">
							<summary class="djd-faq__q"><span><?php echo esc_html( $item['q'] ); ?></span><svg class="djd-faq__chevron" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
							<div class="djd-faq__a"><?php echo djdt_about_paragraphs( $item['a'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
						</details>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
	<?php
	echo $overlays; // phpcs:ignore WordPress.Security.EscapeOutput -- djdigi-library's own escaped renderer output
	return ob_get_clean();
}
