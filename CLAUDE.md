# djdigi-theme

WordPress plugin for djdigimark.com (Avada 7.16.1, classic header v3). It holds the site's look:
fonts, colors, Avada menu restyle, buttons, the `[djdigi_hero]` homepage hero with a Mixcloud
player, and the `[djdigi_venues]` card (clubs and events Mark has played, plus a static genre list)
right below it. It is separate from `djdigi-library` (videos, music, VIP downloads, and the
upcoming events module).

Full redesign decisions: `../site-redesign-plan.md`. Read it before design changes.

## Working rules (from Mark)
- Confirm the plan with Mark before writing or changing code. Collect issues for a batch, then build.
- Never use em dashes or double dashes anywhere: code comments, copy, commit messages, release notes.
- "DigiMark" is one word. Never split it ("DJ Digi / Mark" is wrong).
- Copy should sound like a person wrote it. Plain and direct.
- No offhand Cloudflare or security suggestions.
- Review workflow (from v1.2.1): screenshots are QA for the build itself, not a release gate. Mark
  reviews every round live on the site (admin only mode, motion on) rather than from a screenshot
  gallery. The flow per batch is: plan, wait for OK, build, release, tell Mark to update the plugin
  and purge Bluehost cache and Cloudflare, then he judges it live. Anything he finds goes into the
  next patch version, not a revision of the one just shipped.

## First-time setup (do once)
1. Get the GitHub username: `gh api user -q .login`
2. Replace `GITHUB-USERNAME` in `djdigi-theme.php` (two places: the `Update URI` header and `DJDT_GITHUB_REPO`).
3. `git init -b main`, commit everything, then `gh repo create djdigi-theme --public --source . --push`
4. `git tag v1.0.1` and `git push origin v1.0.1`. The Release workflow builds `djdigi-theme.zip` and publishes the release.
5. On WordPress, install that release zip once over v1.0.0 (Plugins > Add New > Upload, "Replace current"). v1.0.0 has no updater, so this one manual step is needed. After that, updates appear on the Plugins page.

## Releasing an update
1. Make the change. Bump the version in BOTH the `Version:` header and `DJDT_VERSION` (the workflow fails if they differ from the tag).
2. Commit and push to `main`.
3. `git tag vX.Y.Z` and `git push origin vX.Y.Z`.
4. WordPress shows "Update available" within about 12 hours, or right away via Dashboard > Updates > Check again.

Plugin Update Checker (vendor/plugin-update-checker, MIT) reads the latest GitHub release and installs the
attached `djdigi-theme.zip`. Do not edit files in `vendor/`.

## How it is gated
`djdt_is_active()`: the theme and hero only load for logged-in admins until DigiMark > Theme Settings >
"Live for everyone" is checked. Keep every new feature behind this check.

## Files
- `djdigi-theme.php`: the three DigiMark admin screens (Theme Settings, Hero Photos, Venues, see "Admin: three screens" below), asset loading (filemtime versions, Cloudflare-safe), the late header-background wp_head `<style>` block, the hero photo duotone baking (Imagick or GD), body classes, Mixcloud API (cached 6h in transient `djdt_mix`), the inline waveform helper, `[djdigi_hero]`, `[djdigi_venues]`.
- `assets/css/site.css`: site-wide, scoped to `body.djdigi-theme`. Avada header and menu selectors: `.fusion-header-wrapper`, `.fusion-secondary-header` (no social icons at all now, see "Dock scoping"), `.fusion-header`, `.fusion-sticky-header-wrapper`, `.fusion-header-sticky-height` (Avada's own spacer, hidden on hero pages, see "Header over the hero" below), `.fusion-main-menu` (the pill bar) `> ul.fusion-menu`, `.sub-menu` (including explicit dropdown/flyout positioning and the `djd-flip-right` class menu.js adds near the viewport edge), `.fusion-mobile-selector` (classic mobile menu), `.fusion-is-sticky` (sticky state). Also owns the footer dock's styling, the opening sequence's shared timing custom properties (on `body.djdigi-has-hero`, see below), and the hero-page override of `html`'s own background color (see "Header over the hero" below, the cream strip fix).
- `assets/css/hero.css`: hero only, loaded only when the shortcode renders. The large right side photo panel and its edge fade, the waveform wipe transition between rotating photos, the scroll shrink transform, the grid-stacked name layers, the player, the Pride EQ wave, the intro's pause-until-ready gate (see "Intro: fast, overlapping, waits for the photo and fonts" below). No filter, mix-blend-mode or mask-image on the photo itself anymore, see "Smoothness" below. No ticker here, see "Venues" below.
- `assets/css/venues.css`, `assets/js/venues.js`: the `[djdigi_venues]` card, loaded only when that shortcode renders, same pattern as hero.css/hero.js. See "Venues" below.
- `assets/css/admin.css`, `assets/js/admin.js`: the Hero Photos and Venues admin screens only, the hero photo picker and the venues picker (wp.media, drag to reorder; hero photos also set a focal point, venues also have a name, details, a link, a logo color, and an on/off switch).
- `assets/js/hero.js`: opening sequence timing (session quick-intro, `?intro=1` override, the ready gate), header height measurement (the taller of the header wrapper and the mobile nav holder, so the hero always starts below whichever actually extends further down), the waveform wipe's class handoff between rotating photos, the scroll shrink driver, the Mixcloud Widget API player (iframe at player-widget.mixcloud.com, see below). The header's background image fix is no longer here, see "Header over the hero" below.
- `assets/js/menu.js`: the sliding pill behind the current menu item (the only highlight in the menu now), carried across page loads via `sessionStorage`. Also keeps dropdown panels inside the viewport near the right edge.
- `assets/js/dock.js`: the magnetic social dock, footer only (Mark's call: v1.2.1 tried a small one in the header too, v1.2.2 reverted that to Avada's plain icons, v1.2.3 hid those outright, social links live in the footer only now). Reads Avada's own rendered social link hrefs rather than owning them, hides the footer widget area when it has no widgets, and hides the secondary header entirely if that leaves it with nothing else in it.
- `assets/img/hero-photo.jpg`: the original bundled fallback photo (kept as the source for rebaking). `hero-photo-duotone.jpg`: its pre-baked duotone copy, what actually gets served, built the same way `djdt_bake_duotone_imagick()`/`djdt_bake_duotone_gd()` bake an uploaded photo, just done once ahead of time rather than at upload. `hero-wipe-mask.svg`, `hero-wipe-glow.svg`: the waveform wipe's mask shapes, see "Smoothness" below. `wave.svg`: player waveform, inlined by PHP rather than loaded as an image, see below.
- `assets/fonts/`: Archivo (variable, wdth + wght) and Saira Extra Condensed (700, 800), self-hosted, OFL.

## Dock scoping
Avada reuses the exact same `.fusion-social-networks-wrapper` class for the header's small icon
bar and the footer's. An unscoped query for that class finds whichever comes first in the
document, which is what v1.2.0's dock.js fix was for (it was building the footer's dock from the
header's icons). The footer's dock is strictly scoped to its own full real path for exactly that
reason, do not shorten it back to the bare class:
`footer#footer.fusion-footer-copyright-area .fusion-social-links-footer .fusion-social-networks-wrapper`.
v1.2.1 gave the header a small version of the same dock, v1.2.2 removed it and reverted to Avada's
own plain icons there, and v1.2.3 hid those outright (`.fusion-social-links-header` and
`.fusion-social-networks-wrapper` inside `.fusion-secondary-header`, site.css): social links are
footer only now, full stop. If that leaves the secondary header with nothing else in it (checked
against Avada's own secondary menu, dock.js), the whole bar hides too, so it never leaves a gap.

## The waveform is inlined, not an `<img src>`
`djdt_inline_wave()` reads `assets/img/wave.svg` once per request and echoes it directly into the
page, twice (the dim base layer and the clipped cyan progress layer), with the file's own baked in
`fill` stripped so CSS controls color via `currentColor` instead. Two reasons: an externally
loaded SVG's own `preserveAspectRatio` still governs how it fits inside an `<img>`'s box even once
CSS sizes that box (the `preserveAspectRatio="none"` attribute already on the file is not enough
on its own), which is what made it render narrow and centered instead of full width; and a
separate cached file is one more thing a caching layer can serve stale.

## Mixcloud's widget moved to player-widget.mixcloud.com
The iframe source used to be `www.mixcloud.com/widget/iframe/?...`, which now 301s to
`https://player-widget.mixcloud.com?...` (confirmed directly, no `/widget/iframe/` path on the new
domain). Loading the old URL still worked, since the browser follows the redirect, but left the
Widget API's postMessage handshake pointed at the wrong origin, throwing a console warning on
every progress update. hero.js now loads the new domain directly. If Mixcloud moves it again, a
quick `curl -sI` on the old URL shows where it currently redirects.

## Header over the hero: what actually beat it
Getting the header to truly float transparent over the hero, with no animation and no flash on
refresh, took five rounds. What finally worked:
- **No entrance animation at all (v1.4.0).** The header used to drop in with its own `opacity`/
  `transform` animation gated on the same intro-ready check as the rest of the hero (`djd-header-
  drop`, site.css, removed in v1.4.0). Mark kept seeing a black bar on refresh regardless, which
  turned out to be the background-image fix below running late (JS, after load) rather than the
  animation itself; removing the animation entirely was the simpler, equally correct fix once the
  background fix no longer needed JS timing either. The logo and menu pill are simply in place from
  the very first frame now, on every page load, logged in or not.
- **Avada's own global header background image** (an Options panel setting, HeaderMenuBG.png,
  reflected in body classes like `avada-has-header-bg-image`) comes from Avada's own generated
  dynamic CSS, printed in `wp_head` at its own priority. Through v1.2.3 this was forced off with JS
  after load (`el.style.setProperty('background-image', 'none', 'important')`, hero.js), which
  worked, but only once that JS actually ran, after the real first paint, which is what caused the
  black bar flash on every refresh Mark kept seeing. v1.4.0 instead prints a plain `<style>` block
  from PHP, in `wp_head` at priority `9999` so it always lands after Avada's own dynamic stylesheet
  in the page source, forcing `background-image: none !important` on the same selectors the JS used
  to target. Later wins a tie in specificity and `!important` on every single load, so there is no
  JS timing to race and nothing to flash. Only the image is forced this way; background color is
  left to CSS, which already handles the transparent versus sticky distinction correctly through
  the normal cascade, that was never the part Avada's CSS was beating.
- **Avada's header sticky height spacer** (`.fusion-header-sticky-height`, a placeholder div Avada
  renders right before the real header so the page does not jump when its own JS makes the header
  fixed on scroll) still reserves real document flow height even though the header itself floats
  `position: absolute` here. That is what was actually pushing the hero down below the header
  instead of letting it float over it: not a CSS fight, a structural element nobody had targeted
  yet. Hidden entirely on hero pages (site.css): nothing needs that reserved space here, since the
  header is always floating on these pages, sticky or not.
- **The admin bar: do not add an offset for it.** v1.2.3 added `body.admin-bar .fusion-header-wrapper
  { top: 32px; }`, reasoning that WP's admin bar needed clearing. That was wrong and doubled the
  offset: WP already pushes the whole page down with its own margin when an admin is logged in, and
  Avada's `#wrapper` (the element this header's `position: absolute` is actually relative to, not
  the raw viewport) sits inside that already-shifted page, so `top: 0` alone already lands correctly
  below the admin bar. The extra offset on top of that pushed the header a second time, straight
  into the hero, which is exactly the solid band Mark saw live while logged in (v1.2.3 had only been
  tested logged out). v1.3.0 removed it. If the header ever needs a genuine admin bar specific
  offset in the future, confirm `#wrapper`'s own `position` first and work out from there whether
  WP's margin is already accounted for, do not just re-add a flat 32px.
- **The cream strip above the header, logged in (v1.4.0).** WordPress core's own admin bar CSS puts
  `margin-top: 32px` on `html` itself when an admin is logged in (not padding on a wrapper inside
  it), so the admin bar can sit fixed on top without the page jumping. That strip, being margin on
  the root element, paints with whatever background `html` itself resolves to; `html:has(body.djdigi-
  theme)` is deliberately set to the paper color so a page shorter than the viewport never shows a
  gray browser default behind the footer, but that same cream was what was showing through the
  admin bar's own margin strip on hero pages, above the header. Fixed with a second, more specific
  rule, `html:has(body.djdigi-has-hero) { background-color: #0b0820; }` (site.css), right after the
  paper one, so that strip reads as a seamless continuation of the night hero instead. No change was
  needed to the admin bar offset math itself, that part was already correct.
- **Readability over the photo**: with the header carrying no background of its own, the logo and
  menu pill need their own contrast. A static top-down gradient wash on the hero itself
  (`.djd-hero::before`, about 200px tall, fading out) covers the logo; the menu bar already has its
  own solid background (not glass, see "Venues" below for why).
- **Avada's separate sticky header clone** (`.fusion-sticky-header-wrapper`, if it exists as a
  genuinely separate element rather than a class toggled on the same wrapper) is covered everywhere
  the plain `.fusion-header-wrapper` is, both in the wp_head `<style>` block above and in site.css.

If the header ever shows a solid background again, check these in order: whether the `<style
id="djdt-header-bg-kill">` block is actually printing after Avada's own dynamic stylesheet in the
page source (view source, not just DevTools computed style, since load order is the whole point
here), the sticky height spacer is really hidden, whether anything is re-adding an admin bar offset
(it should not need one, see above), the gradient still reads over a bright photo, and whether Avada
changed its own markup (a theme update can rename or restructure any of this). Test both logged in
and logged out; the cream strip and the double admin bar offset were both bugs that only ever
showed up logged in, while the exact same build looked correct logged out.

## The opening sequence's timing
Lives as custom properties on `body.djdigi-has-hero` (site.css), not on `.djd-hero`: it used to
need to be this high up so the header's own drop-in animation (a sibling of the hero section, not
a descendant of it) could read the same variables, and stayed there in v1.4.0 even though the
header no longer animates, since nothing else needs it to move. `body.djdigi-has-hero.djd-quick-
intro` (set by hero.js for a repeat view in the session, or cleared by `?intro=1`) overrides the
same properties to shorten every dependent `animation-delay` at once. Add a new step by adding one
`--djd-t-*` variable here, not by hardcoding a delay where it's used.

## Intro: fast, overlapping, waits for the photo and fonts
Rebuilt in v1.2.2. About 1.6 to 1.8s total now (down from 3.6s), and the steps overlap rather than
running one after another, see the schedule comment above `body.djdigi-has-hero` in site.css.
Entrance animations are transform and opacity only, no filter or blur (a blur animation forces a
repaint every frame, which is what made the photo's old pull-focus slow); the photo now does a
short fade with a slight scale in (1.04 to 1) instead. Easing is `cubic-bezier(0.22, 1, 0.36, 1)`
throughout the entrance, no linear.

The whole entrance is held paused until the hero photo and the fonts are ready (`img.decode()` and
`document.fonts.ready`, hero.js, capped at 1.2s so a slow load never blocks it for long), so nothing
plays against a blank photo or a fallback font. This works by matching every entrance-animated
element with `body:not(.djd-intro-ready) { animation-play-state: paused; }` (hero.css, near the
keyframes): pausing from the very first paint freezes an animation's timeline before its own delay
has even started counting, so the delay only starts once hero.js adds `djd-intro-ready` to body,
not from raw page load. Each element also carries `will-change: transform, opacity` for that same
wait, which hero.js clears afterward, but only on the purely one-shot elements (the slogan, buttons,
player, DJ, each letter): the glow, the photo, the echo and each EQ bar all keep an infinite
companion animation (drift, Ken Burns, the wave) running forever, so will-change stays on those
rather than coming off while they are still actively animating.

Adding a new entrance-animated element: give it its own `--djd-t-*` delay as usual, and add its
selector to both the `body:not(.djd-intro-ready) ...` pause list and the plain `will-change` list
right after it in hero.css, or it will play immediately on page load instead of waiting.

v1.2.3 kept this timing as is and re-confirmed the same lightness rules apply everywhere in the
hero, not just the entrance: no `filter` or `backdrop-filter` animation anywhere, which is also
what turned out to be degrading the old ticker (see "Venues, and why there is no more ticker"
below), and the same rules carried straight into venues.css for the v1.3.0 venues card.

## Venues
The scrolling genre ticker (bulb dots, marquee text) was removed entirely in v1.3.0, Mark's call:
he found it cartoonish. Do not rebuild it if a "ticker" idea comes up again, this is a deliberate
removal, not an oversight; if something like it is wanted later it needs to be designed again from
scratch, not un-reverted. What it taught stays relevant though, and shaped how the new venues card
(`[djdigi_venues]`, venues.css/venues.js) is built:
- `backdrop-filter` (the player card's blur, the non-sticky menu's glass look) was the actual cause
  of the ticker reading like a low frame rate video: it forces the browser to resample whatever is
  behind it on every single frame regardless of whether that content visually changed, which
  competed with the ticker's own transform animation for frame time. Both were switched to solid or
  more opaque backgrounds in v1.2.3 (hero.css, site.css) and must stay that way; do not bring
  `backdrop-filter` back anywhere near an animated element in the hero or the venues card.
- A moving strip should carry `contain: layout paint` so its own internal layout or paint changes
  cannot force a recalculation outside it, and vice versa (`.djd-venues__card`, venues.css).
- A seamless loop is one track holding the set twice, animated `transform: translate3d()` from `0`
  to `-50%`, linear, infinite; since both copies are identical the loop point is invisible. Speed is
  worked out once in JS from the track's own rendered width divided by a steady px per second
  (`--djd-venues-dur`, venues.js), not a fixed duration, which reads as a different speed depending
  on item count and viewport width.
- Never animate `left`, `margin`, or `background-position` on anything behind a moving strip, and
  never drive a strip's position from JS on every frame; transform only, timing worked out once and
  set as a CSS custom property, same rule as the hero's own entrance.

The venues card's traveling beam (`.djd-venues__beam`) is a `conic-gradient` rotated by a custom
property, `--djd-beam-angle`, registered with `@property` (`syntax: '<angle>'`) specifically so the
browser can smoothly animate it: an unregistered custom property used inside a gradient cannot be
interpolated, only swapped instantly between values. The gradient is masked down to just the
border's own ring (`mask-composite: exclude`, content-box cut out of border-box) so only a short arc
shows at a time, not the whole gradient disc. v1.4.0 widened and brightened that arc (Mark could not
really see it in the first pass) and thickened the ring from 1px to 2px. `--djd-beam-dur` is worked
out once in JS from the card's own perimeter (venues.js), same reasoning as the logo row's own
speed. The heading (`.djd-venues__heading`) moved above the card entirely in v1.4.0, a plain centered
label rather than breaking the card's own top border like a fieldset legend; this also meant nothing
can get clipped by the card's own `overflow: hidden` (which the beam needs) anymore. Its cyan to
violet letter sweep (`.djd-venues__letter`, pure CSS keyframes, no JS) still shares the beam's own
duration and is staggered per letter with the same `--i` custom property `djdt_split_letters()`
already sets for the hero name; now that the heading sits outside the card, the sweep is simply
timed to the beam's own loop rather than to a specific point on the card.

Admin (its own "Venues" screen under the DigiMark menu, see "Admin: three screens" below): a venue
is `{ id, name, details, link, on, logo_color }`, the same hidden-JSON-field pattern as hero photos
(`admin.js`, drag to reorder, `wp.media` for the logo). Name and details are two separate fields
since v1.4.0 (a venue saved before that only had one "caption" field like "Necto Nightclub (Ann
Arbor, MI, 10 year weekly residency)"; `djdt_venue_name_details()` splits an old row like that into
name and details at read time, text before the first "(" as the name, text inside the parentheses
as the details, so nothing Mark already entered needed retyping, it just shows up pre-split the
first time the Venues screen loads after updating). The logo is optional: a venue with no logo
shows its name (and details, if any) in the display font instead (`djdt_venue_item()`, PHP). Logo
color is per venue, White (default) or Original: White renders every logo in one flat off-white
regardless of its own source color or format, via a static (never animated) CSS filter,
`brightness(0) invert(0.92)`, brightness(0) crushes any non-transparent pixel to solid black while
keeping its alpha, then invert flips that to a consistent off-white, which is what makes mismatched
logos read as one set; Original keeps the logo's own colors, dimmed to 85% opacity until a visitor's
pointer is over that specific item (`.djd-venues__logo.is-original`, venues.css). SVG logos
(`image/svg+xml`) are accepted (an `upload_mimes` filter, scoped to administrators) and rendered
from their full file URL rather than through `wp_get_attachment_image_url()`, which has no generated
sizes to offer for an SVG in the first place. Every venue logo also carries `skip-lazy` and
`data-no-lazy="1"`, with no `loading="lazy"` of its own and `decoding="async"` instead: it sits in a
constantly moving row that is already on screen, so Avada's lazy loading would either never
trigger or load it visibly late. If there are only a few venues on, PHP repeats the set (not just
the loop-duplicate, the actual displayed set) until it is wide enough to not look like a thin,
obviously-looping row (`$min_rows` in `djdt_venues_shortcode()`). The card itself is narrower now
too (max 1200px, down from 1600, with less padding around the content, v1.4.0), and the whole
`[djdigi_venues]` section breaks out of Avada's own container the same way `.djd-hero` does (`width:
100vw; margin-left: calc(50% - 50vw);`, venues.css), so the night background runs edge to edge with
no cream margin on either side and flows straight out of the hero above it.

The genre list underneath (`.djd-genres`) reuses the exact setting the old ticker used
(`djdt_get('ticker')`, the option key itself is still called `ticker`, intentionally not renamed, so
existing saved values are not silently dropped), just rendered as plain static text now, no motion
at all. `djdt_genre_item()` is the old `djdt_ticker_item()`, renamed for clarity since there is no
ticker anymore, same escaping and `·` grouping behavior.

## Smoothness: baked duotone photos and the waveform wipe
The hero photo panel and the venue strip were both choppy (v1.4.0 report: "like the old ticker").
The likely cause this time was never backdrop-filter (that lesson already shaped venues.css from the
start, see "Venues" above); it was the hero photo itself carrying three repaint-forcing layers at
once under the Ken Burns zoom: a `filter: grayscale(1) contrast(1.05)`, a `mix-blend-mode: color`
duotone overlay, and a `mask-image` edge fade, all three recomputed every frame the photo moved or
crossfaded. Fixed by moving the whole duotone treatment server side:
- `djdt_ensure_duotone()` (djdigi-theme.php) bakes a grayscale-then-violet-to-cyan copy of a hero
  photo once, when it is saved into Hero Photos, not on every front end page load. Imagick first
  (`djdt_bake_duotone_imagick()`, the standard duotone recipe: convert to grayscale, then
  `clutImage()` against a 1px tall gradient from violet to cyan, which maps black to violet, white
  to cyan, and every gray level to the matching point between them), GD as a fallback
  (`djdt_bake_duotone_gd()`, the same lerp done by hand with a 256 entry lookup table, one pass over
  the pixels). Both resize to `DJDT_DUOTONE_MAX_DIM` (2000px) first, plenty for how large the photo
  panel ever renders and far cheaper to bake and serve than the original upload. The baked file's
  path is cached in postmeta (`_djdt_duotone_file`); `djdt_duotone_url()` regenerates it on demand if
  that meta or the file is ever missing, so a photo saved before v1.4.0 self heals the first time it
  is needed, no resave required. If neither Imagick nor GD is available (unusual on a normal host),
  the photo falls back to the plain, unprocessed original rather than a broken image.
- The bundled fallback photo got the same treatment ahead of time: `hero-photo-duotone.jpg` is a
  pre-baked copy of `hero-photo.jpg`, shipped as a file rather than generated at runtime, and is what
  actually gets served when no photos are picked in Settings.
- hero.css no longer has any `filter`, `mix-blend-mode` or `mask-image` on `.djd-hero__photo` or
  `.djd-hero__photos` at all. The left/bottom edge fade that used to be reinforced by the mask is now
  the only fade (`.djd-hero__photos::after`), with its stops pushed a little further out so there is
  still no visible seam without that reinforcement.

The crossfade between rotating photos became a waveform wipe in the same pass (Mark's ask, not a
performance fix on its own, but built with the same no-filter rule): `hero-wipe-mask.svg` is a
jagged vertical-bar boundary, white (visible) on one side, transparent (hidden) on the other,
sized at `mask-size: 200% 100%` so that animating `mask-position` from `100% 0` to `0% 0` sweeps the
boundary left to right across the whole photo over about 1.2s (`@keyframes djd-wipe-reveal`,
hero.css). `mask-position` is a paint-cost property and the general rule elsewhere in this plugin is
to never animate one like it (see "Venues" above); it is fine here specifically because this runs
once per rotation, not in an infinite loop like the old ticker did. The incoming photo gets a
temporary `is-entering` class (hero.js, `photoRotation()`) that applies the mask and runs the reveal
together with Ken Burns as one combined `animation` list on `.is-kenburns.is-entering`, so Ken Burns
keeps running without restarting once `is-entering` is removed again after the wipe (same animation
name still present in the list before and after, which is what keeps the browser from treating it as
a new animation instance). The outgoing photo stays marked `is-active` until the wipe finishes
(hero.js clears it on a timeout, not immediately), since what shows through the not-yet-revealed
part of the mask is simply whatever is underneath, the previous photo, which is the whole effect: an
early removal would show the night background through it instead. `hero-wipe-glow.svg` is a second,
softer mask (a feathered vertical band rather than a hard edge) applied to `.djd-hero__wipe-glow`, a
plain cyan-to-violet gradient div with no filter and no blend mode, riding the exact same
`djd-wipe-reveal` keyframe so the glow tracks the jagged edge without its own transform or any JS.
Falls back to the plain opacity crossfade on a browser with no mask-image support
(`@supports (mask-image: ...) or (-webkit-mask-image: ...)` gates the whole thing).

## Admin: three screens
A top level "DigiMark" item in the WordPress sidebar (v1.4.0, replacing the single page that used to
live under Settings), with three screens: Theme Settings, Hero Photos, Venues
(`add_menu_page()`/`add_submenu_page()`, djdigi-theme.php). All three post to the same
`options.php` and the same option (`djdigi_theme_settings`), so each form carries a hidden
`djdt_page` field (`theme`, `photos`, or `venues`); `djdt_sanitize()` starts from the setting as
currently saved (not just the defaults) and only overwrites the slice of keys that screen's own
`djdt_page` value owns. This matters because splitting one form into three means any one of them,
submitted on its own, would otherwise blank out every field it does not carry, a checkbox the
Theme Settings screen does not even render would look "unchecked" to a plain rebuild-from-POST
sanitizer. The old URL, `wp-admin/options-general.php?page=djdigi-theme`, redirects to the new
Theme Settings page (`admin_init`, checked against `$pagenow` and `$_GET['page']`) so an old
bookmark still lands somewhere real.

## Motion: always on, by design
Nothing on this site checks `prefers-reduced-motion`, anywhere: not in CSS, not in JS, no setting
or toggle. This was removed entirely in v1.2.1 (it previously froze the intro, photo rotation,
ticker, EQ wave, scroll shrink, sliding pill, and the dock for any visitor with that OS setting on,
which is not what Mark wants for this site) and must not be added back. If a future feature is
tempted to add a `@media (prefers-reduced-motion: reduce)` block or a `matchMedia('(prefers-reduced-motion: reduce)')`
check, that is a regression of this decision, not a reasonable default.

One related, real bug this surfaced: the violet echo behind "DigiMark" gets its small offset
entirely from an infinite drift animation; it has no resting position of its own. Killing that
animation (which reduced motion did) made it sit with zero offset exactly on top of the cyan
letters, reading as one washed pale color instead of two legible layers. Fixed with a static
baseline `transform` on `.djd-hero__echo` matching the drift's own resting keyframe, so the layer
is correct whether or not the animation happens to be running at that instant.

## Body classes
- `djdigi-theme`: plugin active for this viewer.
- `djdigi-has-hero`: the page content contains `[djdigi_hero]`. The header becomes absolute and transparent over the hero.
- `djdigi-hero-strip`: `[djdigi_next_up]` exists (from djdigi-library), so the Next Up strip shows above the header and the header shifts down 46px.

## Design tokens
- Paper `#f1ece2`, violet `#5a2e91`, ink `#2a1450`, night `#0b0820`, logo cyan `#24a9e0`, International Orange `#f04e23` (sparingly).
- "DigiMark" in the hero uses logo cyan and is mixed case, not all caps, matching the logo. "DJ" is off-white on the dark hero.
- Display font: Saira Extra Condensed, 700/800. Text font: Archivo. Buttons and the player status tag are sentence case now, not wide-tracked uppercase, except the venues heading and the name, which stay uppercase display type.
- Hero layout: the photo is large, the right 55 to 60% of the hero, full height, bleeding to the right edge, with the name overlapping into it on the left. A fade layer washes the left and bottom edges into night so the photo never shows a hard rectangle. Phone: the photo goes full bleed behind everything with a stronger dark wash for text contrast. No hero labels: the name and slogan carry the hero alone (removed entirely in v1.2.1, markup and CSS, Mark's call).
- Hero intro (about 1.6 to 1.8s, see "Intro: fast, overlapping, waits for the photo and fonts" above): glows bloom first, then the photo fades in with a slight scale in, then "DJ" and "DigiMark" sweep up letter by letter from a full height mask, then slogan, buttons and player land together, all heavily overlapping rather than running in sequence. The header carries no entrance animation of its own (v1.4.0), the logo and menu pill are simply in place from the first frame. Quick version on a repeat view in the session; `?intro=1` forces the full one. Always plays in full motion, see "Motion: always on, by design".
- Hero photo: rotates through whatever is picked in Settings (each with its own focal point), with a slow Ken Burns zoom and a waveform wipe transition between photos (see "Smoothness" above), or the bundled photo if none are picked. The duotone look (grayscale, then a violet to cyan gradient) is baked into the photo file itself server side now, not a live CSS filter or blend mode, so any photo Mark adds reads as part of the same look without manual editing and without costing a repaint every frame.
- Name layers: echo (the drifting violet ghost) and main (the cyan letters) are stacked with CSS Grid, both in the same grid cell, not positioned by hand. Grid items never margin collapse with their container or each other, which is what actually caused v1.2.0's doubled name bug (the padding and negative margin used to make room for descenders collapsed through on one layer and not the other). Keep them grid stacked rather than going back to relative/absolute arithmetic.
- Scroll: past the hero, it shrinks toward scale 0.85 with growing corner radius (toward 28px). One scroll driven progress value (0 to 1) feeds it; see hero.js. Off for touch devices only. The photo wall that used to slide in around it (v1.2.0 and v1.2.1) was removed entirely in v1.2.2, Mark's call (nothing showed below the hero live, and he had not actually asked for it); if tiles come back later they need rebuilding from scratch, not un-reverting this.
- Pride EQ: a shared sine wave drives all 6 bars (`djd-eq-wave`), each with its own `animation-delay` so the motion rolls left to right through the colors, plus a small separate drift animation for a fabric feel, rather than each bar bouncing independently.

## Hero copy (approved)
- Slogan: "Bringing positive vibes to the dance floor" + pride-colored EQ bars (rainbow flag and music in one mark).
- Venues section heading, editable in Settings, default: "Where I've played".
- Genre list (under the venues card, see "Venues" above) comes from
  settings. Default: House, EDM, Top 40 Remixes, "70s · 80s · 90s · 00s Dance" (one grouped item),
  Pride/Queer Pop & Dance, Live Video DJ Sets.
- Keep the hero lean. Background details belong on the About page.

## Navigation
- The menu is the real WordPress menu (Appearance > Menus). Do not rebuild it in code.
- Blog is being removed from the menu (Mark's call, Oct 12 2026). That is a menu edit in WordPress, not code.
- Mark's real menu is named "digimenu" (desktop `ul#menu-digimenu.fusion-menu`, mobile `ul#mobile-menu-digimenu`).
- The sliding pill is the only highlight in the menu: it marks the current page, nothing else. The "Book me" button that used to sit after it (PHP filter, JS fallback, a Settings field, its own CSS) was removed entirely in v1.2.0, Mark's call. If a CTA button in the menu comes back later, it needs rebuilding from scratch rather than un-reverting this, since the old approach (a `wp_nav_menu_items` filter matched on the menu slug or `main_navigation` theme location) is gone along with it.
- Dropdowns open directly below and left aligned to their own top level trigger, not centered (Avada's own default positioning assumed its original non pill header layout and was centering panels over the new flex bar). A nested flyout opens to the right of its parent row, top aligned. menu.js adds `djd-flip-right` to flip either kind to the opposite edge when it would otherwise run past the viewport, which plain CSS has no way to detect on its own. Every nested `<li>` needs its own `position: relative` (site.css), not just the top level ones: without it, a nested panel's absolute position resolves against the top level item for every depth, not its own immediate parent row, which is what let a third level flyout land on top of the first panel in v1.2.0. menu.js also makes a flip state inherit down the chain: once a level flips left, every deeper level flips with it regardless of its own fit, so a chain of flyouts keeps moving one direction instead of zigzagging. On hero pages, dropdown panels are always dark (night with paper text), not just while the header floats non-sticky.

## Caching, every time something changes
Three layers are active on the live site: Asset CleanUp, Bluehost's built in cache, and
Cloudflare. All three can serve a stale copy of djdigi-theme's own CSS/JS or of a whole page after
an update, independently of each other, so after any settings change or plugin update, clear all
three:
1. **Asset CleanUp**: Settings > Asset CleanUp > Settings (or the plugin's own menu) > Load
   Exceptions (sometimes called "Unload CSS/JS"). Add `djdt-site`, `djdt-hero`, `djdt-venues`,
   `djdt-menu`, `djdt-dock` to the handles excluded from combine, minify and defer. This only
   needs doing once; after that Asset CleanUp should leave these alone.
2. **Bluehost cache**: the "Caching" item in the wp-admin top toolbar (admin bar) > Purge All, or
   Bluehost's own hosting dashboard if that control is not present in wp-admin. Do this after
   every settings save or plugin update, not just once.
3. **Cloudflare**: the Cloudflare dashboard for djdigimark.com > Caching > Configuration > Purge
   Everything, or Purge by URL for just the affected page if available. Also after every update.
Confirmed not caused by Asset CleanUp specifically: hero.js was checked live in the browser
console and is loading and running correctly. What looked like it might be Asset CleanUp blocking
the file turned out to be a stale page cache instead, see "Hero photo rotation" below.

## Hero photo rotation depends on a fresh page cache
If the hero shows only the bundled fallback photo (or only one of several configured photos) and
never rotates, check `data-photo-count` on `<section class="djd-hero">` in the page's own HTML
source first. If it reads 1 when more photos are configured in Settings, `djdt_hero_photos()` is
not the problem (it was re-checked and reads the option correctly); the page being served is very
likely a cached snapshot from before the photos were saved. Purge all three cache layers above and
reload. There is no code bug to chase here unless a fresh, uncached load still shows the wrong
count.

## Testing in the static harness
Use Playwright (`page.setViewportSize`, or a mobile device preset), not headless Chrome's own
`--screenshot` CLI flag. That flag has a hard floor around 500px regardless of the requested
window size (confirmed against both new and legacy headless modes and a device-scale-factor
workaround), so it cannot render a true phone width; Playwright's own `chromium.launch()` does
not have this floor and was confirmed down to 390px. Pausing every `document.getAnimations()`
entry and setting `currentTime` to an exact value, same technique as before, worked correctly in
Playwright (unlike the raw Chrome CLI, where it silently did nothing). Since v1.2.1, nothing in the
codebase honors `prefers-reduced-motion` at all (see "Motion: always on, by design"), so an OS or
browser reduced-motion setting on the machine doing the testing is no longer a trap to check for.

Since v1.2.1, screenshots from this harness are QA for the build itself, not something Mark
reviews before release: he judges every round live on the site instead. Still worth running before
calling a batch done.

## Measuring smoothness, not guessing at it
v1.4.0 ("Smoothness" above) was measured, not just reasoned about, before release: headed Chromium
(Playwright, GPU on, not the headless-only path), 1920x1080, a local harness with the hero's Ken
Burns zoom, a waveform wipe transition in progress, the venues beam and the logo marquee all running
at once, `requestAnimationFrame` timestamps collected for 10 seconds starting 3 seconds after load
(past the opening sequence). This sandbox's own display is not locked to a real 60Hz refresh (plain
`requestAnimationFrame` on a blank page here runs at roughly 145 to 165fps on its own, confirmed
separately), so a raw fps number from it is not a meaningful "is this 60fps" claim. What is
meaningful regardless of the display: per-frame cost, how long the browser actually spent per frame
regardless of how often it was allowed to paint. Measured result: 0 frames exceeded even a loose
25ms (well past a 60fps budget's 16.7ms) dropped-frame threshold out of 1650 frames over 10 seconds,
p95 and max frame cost both 6.2ms, comfortably inside the 60fps budget with room to spare. Re-run
this (the harness and `measure.js` are not checked into the plugin, rebuild them the same way:
hero's and venues' real markup and CSS/JS, a few seconds of settle time, then collect rAF deltas)
before claiming a future change is smooth; do not just assert a frame rate without it.

For real Avada markup: the most accurate fixture is a real saved page (View Source > Save As)
with the plugin's own `<link>`/`<script>` tags repointed at the local updated files, rather than a
hand built stand in. The hero section itself still needs to be replaced in that saved copy (it is
a static snapshot of old PHP output), and any `.djd-dock`/`.djd-menu-pill` elements the OLD code
already built into that snapshot need stripping first, or the new JS runs against stale leftover
DOM instead of a clean page load. Two known limitations of testing a saved snapshot rather than a
live WordPress render: Avada's own mobile-vs-desktop menu detection and its dropdown open/close
JS do not fully initialize against a static file, so the mobile selector and all dropdown levels
can render in the wrong state regardless of anything in this plugin.

## Open items
- Queued for live review after v1.4.0, not confirmed yet:
  1. Header and menu fully transparent over the hero, with no black bar flash on refresh, logged in
     AND logged out, plain and sticky (scrolled) state. The cream strip above the header while
     logged in should also be gone. See "Header over the hero: what actually beat it" above if
     anything still shows solid or flashes.
  2. The new DigiMark sidebar menu: Theme Settings, Hero Photos, Venues all save correctly and only
     touch their own fields (saving Hero Photos should never blank out the Mixcloud username, for
     example). The old Settings > DigiMark Theme bookmark redirects to the new Theme Settings page.
  3. Venues: Name and Details render as two lines under a logo (or on their own if a venue has no
     logo); an old venue's single caption shows up correctly pre-split into name and details the
     first time the Venues screen loads. Logo color per venue (White recolors, Original keeps its
     own colors and dims until hovered) looks right for at least one of each. An SVG logo renders.
  4. Venues card layout: the heading reads as a plain label above the card now, not breaking the
     border; the card feels right at its new narrower width; logos read clearly at the larger size;
     the beam is now actually visible riding the border. The whole section should flow directly out
     of the hero with no cream gap on any side.
  5. Hero photo smoothness: the photo panel and the venue strip should both feel smooth now (see
     "Smoothness" above for the baked duotone and what changed); the new waveform wipe between
     rotating photos should read as a left to right reveal with a soft glow on its edge, not a
     crossfade, and Ken Burns should keep zooming through and after it with no stutter or restart.
  6. Intro still finishes in about 1.6 to 1.8s with overlapping steps (unchanged since v1.2.2) and
     still plays in full with OS reduce motion on; the header no longer animates in at all, confirm
     that reads as "just there" rather than missing something.
- Resolved in v1.2.1 through v1.3.0, confirmed live: third-level dropdown overlap and colors, mobile
  header overlapping hero content, reduced motion freezing everything, the intro's own speed, and
  two different diagnoses of what was making the old ticker choppy before it was removed outright
  (see "Venues" above for what the real cause turned out to be).
- After updating, purge Asset CleanUp, Bluehost's cache, and Cloudflare (see "Caching, every time
  something changes") before judging anything on the live page. Most "it didn't change" reports in
  past rounds turned out to be a stale cached copy, not a code issue.
- Confirm the Mixcloud widget still connects (play, pause, seek, progress events) now that the iframe
  src points at `player-widget.mixcloud.com`.
- Avada preloads an unused "petsupplies" icon font. Noted for a later speed cleanup pass, not fixed now.
- Restyle remaining homepage sections and pages (New Stuff, Music, Mailchimp, footer, library, PMPro
  with inline hex only). Avada's own `.fusion-button` sitewide is part of that later pass, not touched
  yet.
- The homepage Blog section in the mockup goes away with the Blog menu item.
- If a hero CTA button returns later, it needs building from scratch; Book me was removed entirely in
  v1.2.0 (PHP filter, JS fallback, setting, styles), not just hidden.
