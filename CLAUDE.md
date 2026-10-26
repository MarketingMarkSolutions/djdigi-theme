# djdigi-theme

WordPress plugin for djdigimark.com (Avada 7.16.1, classic header v3). It holds the site's look:
fonts, colors, Avada menu restyle, buttons, the `[djdigi_hero]` homepage hero with a Mixcloud
player, the `[djdigi_venues]` card (clubs and events Mark has played, plus a static genre list)
right below it, and (v1.8.0) `[djdigi_events]`, upcoming sets read straight from Modern Events
Calendar Lite. It is separate from `djdigi-library` (videos, music, VIP downloads), which the
events module used to live in through v0.2.0 there: moved here in v1.8.0 because djdigi-library has
no update checker (it can only ever ship as a manual zip upload) while djdigi-theme already updates
itself through a GitHub release, see "Events: moved here from djdigi-library" below. djdigi-library
itself was not touched by that move: its own v0.2.0 working copy (events module included) was left
alone, untouched and unreleased, purely a historical staging ground now.

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
- `djdigi-theme.php`: the three DigiMark admin screens (Theme Settings, Hero Photos, Venues, see "Admin: three screens" below), asset loading (filemtime versions, Cloudflare-safe), the late header-background wp_head `<style>` block, the hero photo halftone baking (Imagick assisted decode, GD for every pixel operation, see "Smoothness" below), body classes, Mixcloud API (cached 6h in transient `djdt_mix`), the inline waveform helper, `[djdigi_hero]` (`djdt_hero_shortcode()`) and the "Latest live set" player section it outputs right after itself (`djdt_player_section()`, v1.6.0, no separate shortcode), `[djdigi_venues]`. `require_once`s `includes/djdt-events.php` and (v1.11.0) `includes/djdt-logo.php` near the top, see below.
- `includes/djdt-events.php` (v1.8.0): `[djdigi_events]`, upcoming sets read straight from Modern Events Calendar Lite's own data, no MEC front end involved. See "Events: moved here from djdigi-library" below for the full detail; this entry is just the file map.
- `includes/djdt-videos.php` (v1.14.0): `[djdigi_videos]`, the 4 newest remix videos through djdigi-library's own `[djdigi_newest]` renderer, styled by `assets/css/videos.css`. See "v1.14.0" below.
- `assets/js/mirrorball-3d.js` (v1.14.0): the 3D mirror ball (ES module, imports the bundled `assets/vendor/three/three.module.min.js`, three.js r160, MIT). mirrorball.js loads it after the page has loaded and falls back to its own 2D `drawBall()`. See "v1.14.0" below.
- `reference/mirrorball-3d-reference.js` (v1.14.0): the approved 3D ball demo, source of truth for the look. Excluded from the zip.
- `includes/djdt-logo.php` (v1.11.0): the mirror ball hero's own "night" logo, baked once with GD from the site's own `/wp-content/uploads/2014/08/VideoLogoBlueTr.fw_.png` (ball erased, white removed, same unique-filename-per-recipe caching pattern as the hero photo halftone bakes), or Mark's own override from Theme Settings. See "Mirror ball hero" below.
- `assets/css/site.css`: site-wide, scoped to `body.djdigi-theme`. Avada header and menu selectors: `.fusion-header-wrapper` (v1.11.0: `position: fixed` on hero pages now, not `absolute`, see "Mirror ball hero" below), `.fusion-secondary-header` (no social icons at all now, see "Dock scoping"), `.fusion-header`, `.fusion-sticky-header-wrapper`, `.fusion-header-sticky-height` (Avada's own spacer, hidden on hero pages, see "Header over the hero" below), `.fusion-main-menu` (the pill bar, one constant style now, not a sticky/non-sticky variant, v1.11.0) `> ul.fusion-menu`, `.sub-menu` (including explicit dropdown/flyout positioning and the `djd-flip-right` class menu.js adds near the viewport edge), `.fusion-mobile-selector` (classic mobile menu). Also owns the footer dock's styling, the hero-page override of `html`'s own background color (see "Header over the hero" below, the cream strip fix), and (v1.8.0) the hero page background/transparency rules that let the hero's own bottom fade show through the sections below it.
- `assets/css/hero.css`: hero, the "Latest live set" player section, and both, loaded only when the shortcode renders. v1.11.0, the mirror ball hero (see "Mirror ball hero" below): the full bleed rotating photo panel, the dark `.djd-hero__shade` wash, the inner block (logo, slogan, both buttons) absolutely centered at 54%, the fixed `#djd-wire`/`#djd-ball-canvas`, the `djd-seq-1` through `djd-seq-4` entrance classes mirrorball.js adds to `body`. The player section is otherwise unchanged (its own full width night background by default, no outlined box, see "Smoothness" below), as is the Pride EQ wave. No filter, mix-blend-mode or mask-image on the photo itself, see "Smoothness" below. No ticker here, see "Venues" below.
- `assets/js/mirrorball.js` (v1.11.0): the mirror ball itself. drawBall(), cluster(), the speck math and every entrance timing/easing function are a direct port of reference/mirrorball-hero-reference.html; see "Mirror ball hero" below for what is genuinely new here versus ported as-is.
- `assets/css/events.css`, `assets/js/events.js` (v1.8.0): `[djdigi_events]`'s cards and the CD tray, loaded only when that shortcode renders, same pattern as hero.css/hero.js. See "Events: moved here from djdigi-library" below.
- `assets/css/venues.css`, `assets/js/venues.js`: the `[djdigi_venues]` card, loaded only when that shortcode renders, same pattern as hero.css/hero.js. See "Venues" below.
- `assets/css/admin.css`, `assets/js/admin.js`: the Hero Photos, Venues and (v1.11.0) the hero logo override field on Theme Settings, all wp.media pickers (drag to reorder for photos/venues; hero photos also set a focal point, venues also have a name, details, a link, a logo color, and an on/off switch; the hero logo override is a single image, PNG or SVG).
- `assets/js/hero.js`: the Mixcloud Widget API player (iframe at player-widget.mixcloud.com, see below; looked up from `document`, not from the hero element, since the player is its own section now, not a descendant) and the Avada breakout host class fallback. v1.11.0: the opening sequence, the photo rotation and the header height measurement all moved out of this file into mirrorball.js, which now owns the hero's own `requestAnimationFrame` loop end to end; see "Mirror ball hero" below.
- `assets/js/menu.js`: the sliding pill behind the current menu item (the only highlight in the menu now), carried across page loads via `sessionStorage`. Also keeps dropdown panels inside the viewport near the right edge.
- `assets/js/dock.js`: the magnetic social dock, footer only (Mark's call: v1.2.1 tried a small one in the header too, v1.2.2 reverted that to Avada's plain icons, v1.2.3 hid those outright, social links live in the footer only now). Reads Avada's own rendered social link hrefs rather than owning them, hides the footer widget area when it has no widgets, and hides the secondary header entirely if that leaves it with nothing else in it. The Mixcloud icon is the official Simple Icons mark (v1.11.0, the old shape read as SoundCloud); hover growth is capped at scale 1.08 so neighbors never overlap (was 1.46).
- `assets/img/hero-photo.jpg`: the original bundled fallback photo (kept as the source for rebaking). `hero-photo-halftone.jpg`: its pre-baked halftone copy (v1.6.0, was `hero-photo-duotone.jpg` through v1.5.4), what actually gets served when no photos are picked in Settings, built the same way `djdt_bake_halftone()` bakes an uploaded photo, just done once ahead of time rather than at upload. `wave.svg`: player waveform, inlined by PHP rather than loaded as an image, see below. `tv-static.jpg` (the TV static crossfade's own noise sprite) was deleted in v1.11.0 along with the transition it was for, see "Mirror ball hero" below.
- `assets/fonts/`: Archivo (variable, wdth + wght) and Saira Extra Condensed (700, 800), self-hosted, OFL.
- `reference/mirrorball-hero-reference.html` (v1.11.0): the approved mirror ball demo, source of truth for the look (see "Mirror ball hero" below). Excluded from the release zip (`.github/workflows/release.yml`), same as `reference/events-cd-reference.html`.

## Mirror ball hero (v1.11.0)
Full rebuild of the hero's own look, replacing the name's letter by letter intro, the TV static
crossfade, and the three fixed glows. `reference/mirrorball-hero-reference.html`, the demo Mark
approved live, is the source of truth for the look: `drawBall()`, `cluster()`, the speck math, and
every entrance timing and easing function in `mirrorball.js` are a direct port of it, classes
renamed to this plugin's own `djd-` prefix, same pattern as the CD events cards' own reference port
(v1.9.1). If a number here ever looks wrong, check the reference file itself before changing it;
this is not a case where the reference and the brief disagree.

**What actually changed, end to end:**
- The hero's photo panel is full bleed now (`.djd-hero__photos`, hero.css), not the old right side
  panel: with the typeset name gone, nothing needs the left side of the photo kept clear any more.
  Readability over it comes from `.djd-hero__shade` (the reference's own `.shade`, a left to right
  plus a short bottom wash) instead of the old edge fade plus fixed glows.
- The inner block (logo, slogan, both buttons) is one absolutely positioned column
  (`.djd-hero__inner`), vertically centered at 54% of the hero's own height (`height: max(640px,
  100vh)`, a new, deliberate number; the hero used to get its height from its own content in normal
  flow, which no longer makes sense once that content is absolutely positioned). The name is a logo
  image now (`.djd-hero__logo`), not typeset text; a visually hidden `<h1>` (`.djd-hero__sr-name`)
  carries the real "DJ DigiMark" text for search engines and screen readers.
- Light specks (`.djd-hero__fx`, a canvas, `mix-blend-mode: screen`) and a black intro overlay
  (`.djd-hero__dark`) sit between the shade and the inner block; see the reference for the exact
  layer order, ported as `z-index: 1` through `5` in hero.css (photos, shade, specks, dark overlay,
  bottom fade, in that order; the inner block is `z-index: 10`).
- The spinning mirror ball itself (`#djd-ball-canvas`) and the wire it drops in on (`#djd-wire`) are
  both `position: fixed`, output by `djdt_hero_shortcode()` after the player section, not as
  descendants of `.djd-hero` (fixed position elements do not care where they sit in the DOM, and
  keeping them out avoids `.djd-hero`'s own `isolation: isolate` ever landing between them and the
  header they dock into). The ball's `z-index` (60) is deliberately set ABOVE the header's (50),
  not the reference's own smaller value (45, below its demo header's 50): the brief asked for this
  explicitly, as a robustness margin regardless of how the header's own transparency happens to
  resolve pixel by pixel (the reference's own 45/50 ordering still happens to work, since the header
  logo's own mirror ball area is erased to full transparency, letting the canvas underneath show
  through regardless of paint order; this just does not rely on that).

**Superseded by "Logo fixes (v1.12.1)" below**: the bundled vector trace is the real default now,
not the GD bake (that is a fallback only), and the ball slot defaults changed. The GD bake recipe
itself, described here, is unchanged.

**The logo**: `includes/djdt-logo.php`, `djdt_logo_url()`. Mark's own override from Theme Settings
(a PNG or SVG with the ball already removed) wins if one is picked; otherwise a baked "night" copy
of the site's own existing logo source
(`/wp-content/uploads/2014/08/VideoLogoBlueTr.fw_.png`, 500x593) is generated with GD and cached in
uploads, same unique-hashed-filename-per-recipe pattern as the hero photo halftone bakes (see
"Smoothness" below): `DJDT_LOGO_RECIPE`, bump it if `djdt_logo_bake_night()` ever changes in a way
that means an already baked file needs regenerating. The bake itself (`djdt_logo_bake_night()`) is a
direct PHP/GD port of the reference's own `buildLogo(m='night')`: erase every pixel within a fixed
52px of (430, 68), the source file's own known mirror ball geometry at its native size (not a
setting, this is specific to that one file), then "remove the white" everywhere else, recovering
each pixel's true ink color by unpremultiplying it against an assumed white backing, and pushing a
pixel that comes out near black to paper (`#f1ece2`) instead, exactly as the reference's own
canvas-based version does. GD's own alpha channel is 7 bit (0 opaque to 127 transparent, the
opposite convention from the reference's 0 to 255, 255-opaque canvas alpha); converted at the read
and write ends of the per-pixel loop only, so the math in between matches the reference exactly, see
the function's own doc comment for the full detail.

**Settings** (Theme Settings screen, `djdt_render_settings_theme()`): "Hero logo override" (a single
`wp.media` image field, `admin.js`, its own small IIFE alongside the existing hero photo and venue
pickers) plus three number fields, the mirror ball's own center X, center Y and radius, each a
percent of the logo's OWN RENDERED WIDTH (not height, even for Y: the logo's aspect ratio is fixed,
so one scale factor, read from the element's own width, is enough for both axes, exactly the
reference's own `slot()`). Defaults 86, 13.6, 9.2, matching the reference's own slot values (430,
68, 46 out of a 500 wide source) exactly. These three numbers are NOT the same as the 52px erase
radius above: the erase step is specific to the one known default source file's own fixed geometry,
while these settings describe where the spinning ball sits on WHICHEVER logo image ends up in use
(the generated one or Mark's own override), read fresh by `mirrorball.js` every frame via
`window.djdtMirrorBall` (`wp_add_inline_script()`, `djdt_hero_shortcode()`).

**Entrance timing**: `mirrorball.js`'s own `play()` adds `body.djd-seq-1` through `djd-seq-4` on a
schedule (1150/1400/1650/1950ms, the reference's own numbers; `djd-seq-2` and `djd-seq-4` currently
drive nothing in CSS, same as the reference's own unused `s2`/`s4`, kept for parity in case a future
element needs one of those two steps). `djd-seq-1` reveals the logo, `djd-seq-3` reveals the slogan
and buttons; see hero.css for both. Session repeat behavior (sessionStorage `djdHeroSeen`, `?intro=1`
forces the full sequence, same contract the old `hero.js` `introTiming()` used to have) now lives in
`mirrorball.js` itself: on a repeat view, `play(true)` sets `start` far enough in the past that every
`t < ...` threshold in the frame loop resolves to its own finished state on the very first frame
(reusing the reference's own math at a large `t`, not a new branch of its own), with a one-frame
`djd-ball-noanim` class (hero.css, forces `transition: none` on the logo/slogan/buttons) so nothing
visibly animates in. This is deliberately NOT the reference's own `prefers-reduced-motion` branch:
see "No reduced motion branch" below.

**Header docking**: the Avada header itself (`.fusion-header-wrapper`, site.css, "Header over the
hero") switched from `position: absolute` to `position: fixed` on hero pages, so it (and the ball
docked into its own logo) stays on screen for the whole page, not just floating over the hero. A
fixed header DOES need its own admin bar offset, unlike the old absolute version (the admin bar's
own `margin-top` on `html` does not shift a fixed element the way it shifts the whole, already-
pushed-down page an absolutely positioned header sits inside); `body.admin-bar` top offsets (32px
desktop, 46px under WP core's own 782px breakpoint) were added for exactly this reason, something
the absolute version never needed, see the CLAUDE.md bullet on that from v1.2.3/v1.3.0, still
correct for what it was. The header's own background, and the pill menu's, are now ONE constant
style regardless of Avada's own `.fusion-is-sticky` class (previously there were two variants, a
transparent header while floating and a solid dark glass bar once "stuck"); the brief's own words,
"no background, no bar, no shadow" at any scroll position, read as a hard requirement to drop the
sticky variant entirely, not soften it.

`mirrorball.js` reuses the Avada header's own logo `<img>` (`.fusion-logo img`) as the reference's
"mini": it swaps that image's own `src` to the generated/override logo URL at startup, then drives
its `opacity` every frame from scroll position (`p = smoothstep(scrollY / (0.55 * heroHeight))`, the
reference's own formula, `opacity = clamp((p - 0.4) / 0.5)`), the ball's own center and radius
interpolating between the hero logo's slot and the header logo's slot by that same `p`. Site.css
sets that image's baseline CSS `opacity: 0` so there is nothing to flash before the script runs, an
inline style from JS always overrides a plain stylesheet rule for the same property regardless of
specificity, so this is purely a "before JS runs" safety net, not a fight with the per-frame opacity
JS sets afterward.

**Performance** (CLAUDE.md "Performance" item in the brief, new work, not in the reference demo,
which is never actually scrolled away from or backgrounded): the whole loop pauses on
`document.hidden` (`visibilitychange`); the specks canvas (not the ball) skips its own per-frame
work while the hero itself is off screen (`IntersectionObserver`, `threshold: 0`), since the ball
still needs to keep drawing for the docked header all the way down the page; the specks canvas
resizes with the hero on window resize, the ball canvas resizes itself per frame inside `drawBall()`
based on the ball's own current radius, same as the reference.

**No reduced motion branch.** The reference demo's own `reduce` variable
(`prefers-reduced-motion`) was deliberately not ported: this plugin has a standing, explicit policy
against checking that media feature anywhere (see "Motion: always on, by design" below), reaffirmed
for this feature specifically rather than carved out as an exception. `mirrorball.js` has no
`matchMedia('(prefers-reduced-motion: reduce)')` check anywhere in it. The session-repeat "quick"
path above looks similar to the reference's own reduced-motion end state (no drop, no wire, no
overlay) but is a different trigger entirely (`sessionStorage`, not an OS setting) kept for a
different, separately specified reason (section 5 of the brief, not section 9), and unlike the
reference's own reduced-motion branch, it does not dim or freeze the specks: they run at full motion
immediately rather than being static.

**The footer dock** (`assets/js/dock.js`, `assets/css/site.css`): the Mixcloud icon is the official
Simple Icons mark now (`simple-icons/simple-icons`, `icons/mixcloud.svg`, CC0), not the old
stroke-drawn cloud shape, which read as SoundCloud to anyone who actually knows either logo. Hover
growth is capped at scale 1.08 (was 1 + 0.46 = 1.46), which never overlaps a neighbor at the dock's
own 14px gap (site.css, already correct, not changed); 1.46 visibly did.

**Self-check before tagging** (the brief's own list, at 2000px, 1280px and 390px wide): verified with
a Playwright harness against the real CSS/JS files (a stand in `.fusion-header-wrapper`/`.fusion-
logo`/`.fusion-main-menu` and a marker SVG drawing the exact (430, 68, r46) circle in place of the
real logo, not the live site itself, no WordPress/GD available from this tool set to bake the real
logo). Confirmed at all three widths: the ball's own rendered center lands within a fraction of a
pixel of that marker circle's center; the ball canvas genuinely redraws frame to frame (`toDataURL()`
sampled twice, 300ms apart, differs); the header wrapper is `position: fixed` with a fully
transparent row background both at the top of the page and after scrolling two thirds of the hero's
own height; the header logo's opacity reaches `1` once docked; zero console or page errors. Facets
and specks sharing one `rot` variable and the `[djdigi_hero]`-only scoping of every new rule were
confirmed by reading the code rather than a separate visual test (both are structural: `rot` is a
single module level variable with exactly two readers, and every new selector is scoped under `.djd-
hero`/`body.djdigi-has-hero`/an element only this shortcode ever outputs). **Not verified against the
real Avada markup or the real baked logo** (same standing caveat as every other header bullet in this
file): confirm live, the same standing review workflow as every other release (see "Working rules"
above: Mark reviews every round live, not from a screenshot gallery).

## Homepage polish (v1.12.0)
Four independent changes to the mirror ball hero and the hero page sections, previewed live on
Home v2 and confirmed before building. Numbers below supersede the matching ones in "Mirror ball
hero" above; that section's structure (the layer stack, the logo bake, the settings fields) is
otherwise unchanged.

**Shorter hero, events on the first screen.** `.djd-hero`'s own `height` is `max(540px, 68vh)` now
(was `max(640px, 100vh)`), `.djd-hero__logo` is `min(34vh, 330px)` (`min(30vh, 260px)` under 700px
wide, was `min(54vh, 500px)`/`40vh`), and `.djd-hero__inner` gained `padding-top: 60px` so the
smaller logo still clears the floating header. A new shortcode, `[djdigi_player]`
(`djdt_player_shortcode()`), outputs the exact same "Latest live set" markup `[djdigi_hero]` has
always output right after itself (`djdt_player_section()`, shared by both), so a page can place it
somewhere other than immediately after the hero; `[djdigi_hero player="no"]` keeps working exactly
as before. Home v2's own content changed to `[djdigi_hero player="no"]`, `[djdigi_events]`,
`[djdigi_player]`, `[djdigi_venues]`, a content edit, not code. `.djd-events` gets a short `40px`
top gap instead of the usual `--djd-section-gap` specifically when it follows the hero with no
player section in between (site.css, `.djd-hero ~ .djd-events`, overridden back to the normal gap
by a second, later rule when `.djd-player-section ~ .djd-events` also matches); the general sibling
combinator, not adjacent, since `mirrorball.js`'s own `#djd-wire`/`#djd-ball-canvas` (fixed
position, always output right after the hero) and whatever empty `<p>`/`<br>` wpautop leaves
between adjacent shortcode lines both sit in between a plain adjacent selector would miss. No
change was needed to `djdFixBreakout` (`djdigi-theme.php`, `wp_footer`) or the `--djd-vw` script
(`wp_head`) for the new shortcode: both already select by class (`.djd-player-section`) or set a
property on `document.documentElement` globally, neither cares which shortcode rendered a given
element. One real fix was needed: `hero.js` used to bail out entirely (`if (!hero) return`) when
there was no `.djd-hero` on the page, which would have silently broken `[djdigi_player]` used on
its own (its Mixcloud player logic lives in that same file); that guard is gone, hero.js now runs
its player logic regardless of whether the hero itself is also on the page.

**Entrance: every page load, slower, bigger landing.** The `sessionStorage`
`djdHeroSeen`/`djd-ball-noanim` "quick repeat visit" skip from v1.11.0 is gone entirely: the full
~3.3s entrance plays on every load of a hero page now, Mark's call. `?intro=1` is accordingly a
no-op, there is nothing left for it to force; the one remaining mention of it is in Theme Settings'
own help text, now saying so. New timeline, all in `mirrorball.js`'s `frame()`/`play()`: 0 to 400ms
black overlay, ball hidden; 400 to 2200ms the drop (`eBack(dp, 0.9)`, was `c1 = 1.25`, now `0.9`, a
smoother settle); 2200 to 2500ms the hang (ball holds at the slot); 2500ms landing (`flash = 1`,
900ms decay, was 650ms); 2450 to 3150ms the black overlay (and the spotlight, see below) fades out;
2550ms the logo; 2900 to 3350ms the wire fades; 3050ms the slogan and buttons; first photo change
at 6600ms (was 5200ms), then every 6s as before. Two new visual elements, both drawn on
`.djd-hero__fx` (the same canvas the specks use, see the z-index note below) via a new, undotted
`softGlow()` helper (unlike `cluster()`, no halftone texture, just a plain radial gradient, paper
color): a **spotlight** (400 to 2500ms, `3 * ball radius`, 12% alpha through the drop swelling to
20% during the hang, then multiplied by the overlay's own fade curve so both finish together,
`overlayFade()`) and a one-time **landing glow ring** (2500 to 3100ms, growing to 40% of the hero's
own width while fading from 35% alpha to 0, independent of the overlay fade). The speck burst
itself also changed: specks now start at 1.6x size and 2x alpha and ease down to normal over the
same 900ms they fly out over (`sizeMul`/`alphaMul`, both derived from the existing `eOut` fly-out
progress, not a separate curve). `.djd-hero__fx`'s own `z-index` moved above `.djd-hero__dark`'s
(was below) specifically so the spotlight can sit above the black overlay as specified; this is
safe for the specks and the ring too, since neither ever draws anything before landing (2500ms), by
which point the overlay is already most of the way through its own fade.

**Docking fix: a real two-logos-visible bug, not just a different formula.** v1.11.0's docking used
a plain scroll fraction (`p = smoothstep(scrollY / (0.55 * heroHeight))`), which had no actual
relationship to where the hero's own logo was on screen, confirmed live: at roughly a third of the
way down the hero, the header logo was already fading in while the hero's own logo was still
clearly visible, both at once. v1.12.0 replaces `p` with `q`, computed every frame from the hero
logo's own live position relative to the header: `q = smoothstep(clamp((headerBottom -
heroLogoRect.top) / (0.6 * heroLogoRect.height)))`, where `headerBottom` is
`.fusion-header-wrapper`'s own `getBoundingClientRect().bottom` (already reflecting the admin bar
offset, since that is CSS on the header itself, not something this script recomputes). `q` is 0
until the hero logo's own top edge reaches the header's own bottom edge, and 1 once 60% of the
logo's own height has passed under it; a crossfade can only happen in between, while the logo is
physically passing under the header, which is what actually guarantees the two are never both
clearly visible. Header logo opacity is `q` directly; the hero logo's opacity is now entirely JS
owned too (`big.style.opacity`, not a CSS class), computed as its own entrance fade
(`clamp((t - 2550) / 600)`) times `(1 - q)`, one driver for that property instead of a CSS
transition and a scroll handler fighting over it; only the hero logo's `transform` (the scale-in)
stayed class driven (`body.djd-seq-1 .djd-hero__logo { transform: none; }`), since `q` never touches
it. The ball's own center/radius still interpolate from the hero slot to the header slot by this
same `q`, exactly as `p` did before; before the handoff (`q = 0`) the ball simply rides with the
hero logo as it scrolls, which falls out of `slot()` re-reading `getBoundingClientRect()` every
frame, no special case needed for it.

**Section headings: outline plus cyan.** Every `.djd-section-heading` ("Upcoming sets", "Latest
live set", the venues heading) is now rendered server side as two spans by one shared helper,
`djdt_heading_html()` (`djdigi-theme.php`): every word but the last in `.djd-hw-outline`, the last
word alone in `.djd-hw-solid`, called from the events grid, `djdt_player_section()`, and
`djdt_venues_shortcode()` (the venues heading is Mark's own editable setting, not a fixed string;
the helper does not care, it just splits on whitespace). `.djd-hw-outline` is `color: transparent;
-webkit-text-stroke: 1.5px var(--djd-paper)`, inside `@supports (-webkit-text-stroke: 1px)`; outside
that block (the fallback, a browser without the property) it is solid paper instead of invisible
transparent text, see site.css. `.djd-hw-solid` is `var(--djd-cyan)`. No `aria-label` needed: the
two spans sit in normal reading order with a plain space between them (`implode(' ', $words)` then
a literal space before the solid span), so the visible and accessible text are the same thing. A
one-word heading (none currently exist, but the function handles it) is all solid.

## Logo fixes (v1.12.1)
Three fixes, confirmed live and built as specified.

**The hero logo was stretched.** `.djd-hero__inner` is a flex column with no `align-items` of its
own, which defaults to `stretch`; `.djd-hero__logo`'s own `width: auto` was never enough on its own
to stop a flex item being stretched on the cross axis, confirmed live (about 392x316 instead of its
real aspect ratio). Fixed with `align-self: flex-start` on `.djd-hero__logo` (hero.css), one
declaration, covers every breakpoint since nothing below it ever touches `align-self`, only
`height`.

**The logo is a bundled vector now, not a GD bake of a raster source.** `assets/img/logo-night.svg`
(Mark's own 2000px master, traced to a vector, `viewBox="240 104 1517 1798"`, aspect ratio
1517:1798, mirror ball already removed, colors baked into the file itself: outline `#00aeef`,
lettering `#24a9e0`, "DJ" `#f1ece2`) is the real default now for both the hero's own logo and the
header's docked logo, crisp at any zoom unlike the old raster bake. `djdt_logo_url()`
(`includes/djdt-logo.php`) checks it first, after Mark's own override and before the old GD bake
(`djdt_logo_ensure_baked()`, unchanged, the v1.11.0 recipe described above), which now only ever
runs as a fallback if that bundled file is somehow missing. The hero's own `<img>` carries
`width="1517" height="1798"` now (was `500`/`593`) for layout stability before it loads; nothing in
`mirrorball.js` needed to change for this, `slot()` was already resolution independent (every
number is a percent of the element's own rendered width, read fresh every frame, never the image's
intrinsic pixel size).

**New ball slot defaults, because the vector's geometry is not identical to the old raster's.**
85.5 / 14.2 / 9.6 (were 86 / 13.6 / 9.2), `djdt_defaults()`. A value saved before this version (the
Theme Settings form pre-filled the old defaults into those three number fields, so a site that ever
saved that screen has them sitting in the database as real, explicit values) is migrated once if it
still exactly equals the old default: `djdt_migrate_ball_defaults()`, `admin_init`, gated on
`get_option('djdt_ball_defaults_v112')` so it only ever runs once, same pattern as
`DJDT_LOGO_COLOR_RECIPE`'s own versioned migration. A value Mark deliberately changed to something
else is left alone; a value that was never saved at all already resolves to the new default on its
own, through `djdt_settings()`'s normal `wp_parse_args()` fallback, no migration needed for that
case.

**Bug: an SVG override was silently ignored.** `wp_attachment_is_image()` (WordPress core) checks
an attachment's mime type against a fixed list that does not include `image/svg+xml`, so it returns
`false` for any SVG, which is what made the "Hero logo override" field quietly do nothing whenever
Mark picked an SVG there: `djdt_logo_url()` would see `wp_attachment_is_image()` return false and
fall straight through to the default logo. Fixed by checking the mime type directly first
(`'image/svg+xml' === get_post_mime_type($id)`) and treating that case as valid on its own, ahead of
and instead of calling `wp_attachment_is_image()`, not in addition to it.

## Homepage cleanup (v1.13.0)
Five items, confirmed by Mark and built as specified. Supersedes the header logo position and size
described in "Header over the hero" and "Mirror ball hero" for hero pages.

**No gap above the hero.** Home v2's hero now sits inside an Avada Builder text element, so the
empty `<p>` wpautop leaves (`<p>[djdigi_hero ...]` closed by the browser at `<section>`) is the first
child of `.fusion-text`, not `.post-content`, and the v1.4.2 CSS never matched it (20px gap, every
visitor). `djdt_strip_hero_autop()` (`the_content`, priority 20, a plain int, well after
`do_shortcode` at 11) removes the `<p>` (plus `<br>`/whitespace/`&nbsp;`) right before
`<section class="djd-hero"`, and any `<br>`/`</p>` right after the `<!-- /djd-hero -->` marker
`djdt_hero_shortcode()` now prints at the end of its output. CSS backup in site.css: any `p` directly
before `.djd-hero` containing nothing but `<br>` is hidden (`p:has(+ .djd-hero):not(:has(:not(br)))`).
Hero detection is one helper now, `djdt_is_hero_page()`, shared by the body class filter and the logo
swap below.

**One pill, logo inside it, hides on scroll down.** No logo at the left gutter on hero pages any more.
- Server side: on a hero page the whole response is buffered from `template_redirect` and
  `djdt_swap_header_logos()` rewrites all three Avada logo imgs (`fusion-standard-logo`,
  `fusion-sticky-logo`, `fusion-mobile-logo`) to `djdt_logo_url()` (the bundled `logo-night.svg`
  unless Mark set an override): `src`, `data-src`, `data-orig-src` and Avada's own
  `data-retina_logo_url` (its JS swaps that in on a high density screen), `srcset`/`sizes` removed,
  `width="1517" height="1798"`. There is no Avada filter for these tags, hence the buffer. The old
  `DigiMarkLogo60.png` is never requested (checked in the harness). mirrorball.js repeats the swap
  client side as a backup for a stale cached page.
- site.css shows only the standard logo img on hero pages (sticky and mobile copies hidden), 40px tall,
  and takes `.fusion-logo` out of flow (`position: absolute`, top left of the header row,
  `z-index: 201`: Avada's own `.fusion-main-menu` is `z-index: 200`, found in the harness, the logo
  rendered dimmed under the pill's background at 2). mirrorball.js `placeLogo(q)` runs every frame:
  the visible pill's `padding-left` grows from its base by 46px with `q` (6px to 52px on the desktop
  menu, set with `!important` since the phone pill's padding is `!important`), and the logo is
  translated so its right edge sits 8px short of the first menu item, vertically centered on the pill.
  The ball still docks by `slot(mini)`, now read after the logo is placed, so it reads the live rect
  with every transform included. Slot math unchanged (85.5 / 14.2 / 9.6).
- `headerBottom` for `q` is now the header's laid out position (CSS `top` plus `offsetHeight`), not its
  live rect, so the hide transform never feeds back into `q`.
- Hide/show: past the hero's bottom edge, scrolling down more than 8px adds `djd-hdr-hidden` to
  `.fusion-header-wrapper` (`translateY(-140%)` plus fade, 250ms ease-in); scrolling up more than 8px
  removes it (300ms ease-out). Always shown above the hero bottom, while the mobile menu is open
  (`aria-expanded="true"` on Avada's selector, or its list is showing), and while focus is inside the
  header. Docked, the ball's opacity follows the header's computed opacity (weighted by `q`).
- **Avada's sticky header script, found in the harness:** once scrolled it adds `.fusion-is-sticky` and
  makes `.fusion-header` itself `position: fixed`, which collapsed the already fixed wrapper to 0px tall
  (the -140% transform then moved nothing, and `headerBottom` broke). `.fusion-header` is forced
  `position: static; top: auto` inside the wrapper on hero pages.
- **Phone (800px and under):** the classic mobile "Go to..." button (`.fusion-mobile-selector`) is the
  pill: same background/border, 40px, on the right; the header row is flex at this width too (the
  v1.7.2 clearfix hiding applies at every width now). The list opens as a dark panel anchored under the
  pill's right edge, `min(300px, 100vw - 2 gutters)` wide. Avada's mobile search box
  (`.fusion-mobile-menu-search`) is hidden on hero pages: it joined the flex row when the menu opened
  and pushed the pill and panel off screen. Inner pages keep Avada's mobile menu untouched. This
  supersedes the earlier "leave Avada's mobile header alone" scoping, for hero pages only.
- menu.js re-places its current item highlight (without animation) whenever the bar's width changes
  (ResizeObserver), since the growing slot shifts every link right; skipped mid click navigation.

**"Upcoming sets" is "Upcoming events"** everywhere visible: the events heading (`UPCOMING` outline,
`EVENTS` cyan), the hero button, the Theme Settings label, and related comments. The option key is
still `sets_url`, intentionally not renamed (saved values would be dropped).

**Headings in capitals.** Avada's own heading rules (e.g. `.post-content h2`) out-ranked
`.djd-section-heading`'s `text-transform: uppercase`; now forced with `!important` on the heading and
both spans.

**Mix title: normal case, two lines.** `djdt_mix_title_lines()`: drop everything up to and including
the first `" - "`, then a trailing `( ... )` becomes line 2. Each step only applies if its part exists,
so a title with neither shows whole on one line. Line 1 `.djd-player__title-main` (Archivo 600,
`clamp(18px, 1.6vw, 24px)`, paper), line 2 `.djd-player__title-sub` (Archivo 400, 15px, paper at 70%),
both `aria-hidden`; the full original title is in a visually hidden `.djd-sr` span and the `title`
attribute.

**Verified** with a Playwright harness built from the saved Home v2 snapshot (real Avada header,
mobile nav and sticky script; the new hero/events/player/venues markup placed inside a builder column
with the wpautop leftover left in, so the CSS backup is what gets tested), at 2000, 1280 and 390,
logged in and out: hero top at 0 (32/46 under the admin bar), headings uppercase, two line title,
header logo hidden at the top, docked pill padding base+46, logo 40px inside the slot and centered,
ball center within 1px of the logo dot (also mid show transition), hide on scroll down with the ball
hidden, show on scroll up, focus keeps it shown, no horizontal scroll, no errors from plugin files,
old logo never requested. The PHP string functions (autop strip, logo swap, title split) were
checked through a line for line Python port, since no PHP interpreter is available. **Not verified**
against the live WordPress render: Avada's mobile menu JS and real builder markup only exist live.

## v1.14.0: 3D mirror ball, live sets playlist, remix videos, pill size, footer links
Nine items, confirmed by Mark (the 3D ball approved live in a demo on Home v2) and built as specified.
Supersedes "Homepage cleanup (v1.13.0)" on the pill's size and the hide on scroll.

**3D mirror ball.** `assets/js/mirrorball-3d.js`, an ES module, ports the look of
`reference/mirrorball-3d-reference.js` (the approved demo with its final tuned values, excluded from
the zip like every `reference/` file) value for value: the ball group tilted (x 0.3, z -0.12) with an
inner spinning group, a `MeshBasicMaterial` core (0.975, `#05031a`), 24 latitude bands of tiles (count
per band by cos(lat), alternate bands offset half a tile, 84% size, random tilt +-0.07 on X and Y,
random gray 0.82 to 1 per tile, one `InstancedMesh`), `MeshStandardMaterial` metalness 1 / roughness
0.03, `mat.envMap` = the active hero photo through `PMREMGenerator` (the `<img>` mirrorball.js already
has, the same file the demo's `TextureLoader` loaded), directional key (3.2, -2/2.6/3) and key2
(`#f1ece2` 2.2), violet `#9a6cff` and cyan `#24a9e0` point lights (25, distance 12), hemisphere 0.5,
ACES at 1.0, and the 4 additive sparkle sprites (dot plus thin cross, sin envelope 700 to 1400ms, 0.8
to 3.4s apart, upper left of the ball). The demo's own environment refresh never actually fired (its
selector always found the first photo); here it refreshes on every photo change, as the brief says.
The demo's own
"follow the 2D canvas" wiring is not ported: mirrorball.js still owns position, size, drop, wire,
spotlight, docking and the pill slot math, and calls `render({ x, y, r, rot, flash, now })` every
frame with the same `rot` the specks use (`ball.rotation.y = rot`, so ball and specks spin together)
and the same `flash`: `envMapIntensity = 1.2 + flash * 3`, key light `3.2 + flash * 8`, on landing and
on every photo change (`setEnv()` is called with the incoming photo when its transition starts).
- three.js r160 is bundled, `assets/vendor/three/three.module.min.js` (+ its MIT `LICENSE`), never a
  CDN. mirrorball.js loads the module on `load`, then `requestIdleCallback` (2s timeout), with a
  dynamic `import()` of `djdtMirrorBall.ball3dUrl` (PHP, `?ver=` filemtime); the module imports three.js
  by relative path. The `import()` is wrapped in `new Function` so a browser without dynamic import
  does not reject all of mirrorball.js (2D ball included) as a syntax error.
- The canvas (`#djd-ball-3d`, fixed, same CSS as `#djd-ball-canvas`) is the ball's diameter plus 18% of
  it on each side (orthographic camera, half extent 1.36 ball radii) so sparkles are never clipped. One
  renderer, DPR capped at 2. The drawing buffer only resizes in 16px steps and is CSS scaled to the
  exact size in between (the radius changes every frame while docking). Rendered only from the drop's
  start (t >= 400ms) and not at all while the tab is hidden (the loop already stops on
  `visibilitychange`).
- **Fallback:** until the module is ready the 2D `drawBall()` draws exactly as in v1.13.0; on the
  first successful 3D render the 2D canvas goes to opacity 0 in the same frame, same center and
  radius, so there is no jump. No WebGL (checked quietly on a throwaway canvas first, since three.js
  itself logs console errors when it cannot get a context), the module or three.js failing to load,
  a render throwing, or `webglcontextlost`: the 3D canvas is removed and the 2D ball stays for the
  rest of the visit. `html[data-djd-ball]` reads `3d` or `2d` (debugging and the harness).

**Latest live sets: one player + 3 item playlist.** Heading "Latest live sets". `djdt_get_mixes()`
fetches `/<user>/cloudcasts/?limit=3` server side, transient `djdt_mixes` for 1 hour; every good fetch
is also saved to option `djdt_mixes_last_good` (with the username), which is served on an error (then
retried after 10 minutes). In "latest" mode `djdt_get_mix()` is simply `djdt_get_mixes()[0]` (so the
player and the playlist never disagree); a pinned mix keeps its own 6 hour `djdt_mix` cache and no row
starts highlighted. `djdt_playlist_html()` prints an `<ol>` of buttons (`.djd-playlist__row`, data
attributes for feed, length, name, both title lines, artwork, URL): thumbnail (Mixcloud `medium`,
shown 56px), the two line title from `djdt_mix_title_lines()`, the length (`m:ss` / `h:mm:ss`). The
selected row has `is-current`, `aria-pressed="true"`, a cyan title and a small "Playing" tag. hero.js
`showMix()` swaps the feed, title lines, artwork, duration, scrub max and the Tracklist link into the
player and moves the highlight; `playCurrent()` then plays it: a plain `play()` if the widget already
has that feed, otherwise the Widget API's `load(feed, true)`, rebuilding the hidden iframe if that
fails, opening the mix on Mixcloud if everything fails. Clicking the selected row toggles play/pause.
Nothing at all prints with fewer than 2 mixes.

**Remix videos: `[djdigi_videos]`.** `includes/djdt-videos.php`. Calls djdigi-library's own
`DjDigi_Library_Plugin::instance()->render_shortcode_newest( array( 'count' => 4 ) )`, the
`[djdigi_newest]` renderer: the library's own card markup, lightbox, VIP modal and data, so it updates
whenever the library's sheet cache does. djdigi-library is never modified (it has no auto-updater).
Library inactive (class or method missing) or empty output: nothing at all. The lightbox and VIP
modal the library prints after the cards are moved after this section's `</section>`: the section is
its own stacking context (`z-index: 1`, like every theme section) and would otherwise trap the fixed
pop-ups under the sections after it. Heading "Remix videos", a "See all videos" pill link to the
`videos_url` setting (default `/video-gallery/`), the library's own "View All Videos" button hidden.
`assets/css/videos.css` (enqueued only when the shortcode renders): full bleed section like the
others, 4 across, 2 under 900px (the library sets its columns with `!important`, so these do too),
night colors scoped to `.djd-videos` only, cyan where the library uses gold. Added to `djdFixBreakout`,
the `:has()` breakout/overflow lists and the hero page transparent background rule.

**Pill: 46px, logo 36px, always visible.** Hero pages only: `.fusion-main-menu` 46px tall
(`box-sizing: border-box`, border included), links `height`/`line-height: 46px`, the current item
highlight `height: 100%` inside the border; the phone `.fusion-mobile-selector` 46px too. Pill logo
36px, which `placeLogo()` already centers (5px above and below; inside the rounded end, checked against
the capsule shape in the harness). Slot math unchanged (6px to 52px, 8px gap). The v1.13.0 hide on
scroll down is gone (class, CSS, scroll/focus listeners, the ball's header opacity weighting).

**Phone menu button reads "Menu".** Avada builds the classic mobile button in its own JS from the
localized string `avadaMenuVars.dropdown_goto` (`__( 'Go to...', 'Avada' )`); a `gettext` filter
returns "Menu" for it on hero pages (string compare first, nothing checked before `did_action( 'wp' )`).
menu.js relabels it too (MutationObserver until the button appears) for a cached combined script.
**Found while building this:** the server sends `.fusion-mobile-nav-holder` empty and Avada fills it
after the plugin's scripts run, so v1.13.0's one time `mobilePill` lookup in mirrorball.js found
nothing live (the phone logo slot could never have worked there; the snapshot harness had the button
pre-rendered, which hid it). mirrorball.js now looks it up when needed (`findMobilePill()`), and the
v1.14.0 harness empties the nav holder so Avada's own JS builds it, as live.

**Footer: Book me + newsletter.** Two Theme Settings fields, `book_url` and `newsletter_url`; empty
hides that item, both empty adds nothing. dock.js builds `.djd-footer-links` right after the dock: a
solid cyan "Book me" pill and an underlined "Join the newsletter" link (styles in site.css, since the
footer is on every themed page). Defaults: `book_url` = `/about/` (the site has no contact page; the
contact form is on About, checked live 2026-10-25: `/contact/`, `/booking/`, `/book-me/` and similar
all 404); `newsletter_url` empty (no newsletter page exists; the "Email Updates" form is a section of
the old homepage with no anchor, and goes away once Home v2 is the homepage).

**SVG logo override saves.** `djdt_sanitize()` checks `'image/svg+xml' === get_post_mime_type()`
ahead of `wp_attachment_is_image()`, same as the read side (v1.12.1). The admin preview uses the full
URL for an SVG (`wp_get_attachment_thumb_url()` is empty for one).

**Home v2 order** (set after release; page content is not edited by the plugin):
`[djdigi_hero player="no"]`, `[djdigi_events limit="5"]`, `[djdigi_player]`, `[djdigi_videos]`,
`[djdigi_venues]`. The Theme Settings help text shows this order.

**Verified** in the snapshot harness (`build_harness14.py`, `test14.js`, the live site's own
djdigi-library markup/data/CSS/JS, a stubbed Mixcloud Widget API that records `play`/`load` calls,
Avada building its own mobile menu) at 2000, 1280 and 390, logged in and out, with WebGL (SwiftShader)
and with WebGL disabled: 3D ball docks within 1px of the logo dot (2D with WebGL off, no console errors
either way), pill 46px, logo 36px with 5px above and below inside the rounded end, links centered,
the pill does not move while scrolling down, "Menu" on the phone, playlist 3 rows with the newest
selected, each row (one by keyboard) plays in the player (`load(feed, true)`) with highlight, title,
duration and Tracklist following, 4 video cards (4 / 2 across), the library lightbox opens on top,
the section full bleed, footer links after the dock, no horizontal scroll. PHP checked with the
`php-parser` npm package (syntax only). **Not verified** live: real Mixcloud widget `load()`, the real
three.js look against the approved demo on Mark's own screens, and Avada/Asset CleanUp's live script
combination.

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
**v1.11.0 changed two things this whole section still assumes: the header is `position: fixed` now,
not `absolute` (so it needed its own admin bar offset for the first time), and the sticky/non-sticky
background split is gone (one constant transparent style now, any scroll position). See "Mirror ball
hero" above for both; everything else below, the background image kill, the sticky height spacer,
the logo/menu alignment numbers, is unaffected and still correct.**

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
- **Logo and menu level with each other (v1.4.1).** Found live: the menu pill sat right at the top
  edge of the header while the logo carried its own 10px margin from Avada, so the two read off
  axis from each other. `body.djdigi-has-hero .fusion-header-wrapper .fusion-row { align-items:
  center; padding-top: 32px; }` (32px, up from 20px in v1.6.1, see below) plus
  `body.djdigi-has-hero .fusion-logo { margin-top: 0 !important; margin-left: 0 !important; }`
  (site.css) centers both on the same line instead. `align-items` only does anything if that row
  is already a flex or grid container, which Avada's own header row is; if a future Avada update
  changes that, the rule is harmless (a no-op) rather than broken, but the actual centering would
  need rebuilding.
- **Logo bigger, more breathing room, lined up with the hero content (v1.6.1).** Found live: the
  logo read about 48px tall, crowded into the top left corner under the admin bar, over the
  busiest part of the background. `body.djdigi-has-hero .fusion-logo img { height: 72px !important;
  width: auto !important; }` (52px on phone, `@media (max-width: 800px)`) forces the size: Avada
  sets width/height as attributes directly on the `<img>`, which beats a plain CSS rule on
  specificity alone, `!important` is what actually wins. `padding-top` on the header row raised
  from 20px to 32px for more room above both the logo and the pill. `.fusion-logo`'s own
  `margin-left: 0 !important` lines its left edge up with `.fusion-header`'s own 40px side padding
  (above), the same 1600px max-width plus 40px padding pattern `.djd-hero__inner` uses (hero.css),
  so the logo reads as the top of the same left hand column as "DJ DigiMark" and the slogan under
  it. No glow, outline or backdrop added, a plain larger mark only. **Not verified against the real
  Avada markup**: no browser or live site access from this tool set, written defensively against
  the standard `.fusion-logo` / `.fusion-logo img` pattern Avada uses elsewhere in this file;
  confirm live that the size and alignment actually land, and that nothing about Avada's own logo
  markup (a possible `.fusion-logo-1x`/`.fusion-logo-2x` retina pair, or similar) needs a more
  specific selector.
- **Logo centered with the menu, left edge lined up with the name, by measured numbers (v1.6.2).**
  Mark measured live, logged in: at 912px wide, the logo's own vertical center sat at 100px while
  the menu pill's center sat at 84px (16px off, even with `align-items: center` on the row, see
  above), and the logo's left edge sat at 40px while "DJ DigiMark"'s own visual left edge sat at
  25px (`margin-left: 0` only lined the logo up with `.fusion-header`'s 40px padding box, not with
  the name itself, which sits 8px further left again from its own negative margin, see
  `.djd-hero__name` in hero.css). Two fixes:
  - **Vertical**: `.fusion-logo` (and its own link) get `display: flex; align-items: center;`
    directly, not just relying on the row's own `align-items: center`. If the row centers its
    columns correctly but the logo's own box (not the row) is taller than the image Avada puts
    inside it, which is the likely cause here, the row's alignment alone cannot fix an offset that
    lives inside the logo's own box; centering the image within its own box directly does, no
    matter which one it actually turns out to be.
  - **Horizontal**: a new shared custom property, `--djd-name-edge: -8px` (site.css, on
    `body.djdigi-theme`), is read by both `.djd-hero__name`'s own margin and `.fusion-logo`'s
    margin-left now, instead of each carrying its own independent value (0 for the logo, -8px for
    the name) that can silently drift apart. This is a deliberate one value, read in two places,
    per Mark's own instruction ("use the same container and padding values the hero content uses,
    not a hardcoded offset"); it only accounts for the name's own explicit negative margin, not
    whatever the Saira Extra Condensed font's own glyph rendering (left side bearing on the bold "D")
    might additionally contribute, which this plugin has no principled way to read or replicate for
    an image. **Not verified against the real Avada markup, same caveat as the v1.6.1 bullet above**:
    written from Mark's measured numbers and the standard `.fusion-logo` pattern, not from seeing it
    render. If the left edges still do not quite line up after this, the gap that is left is most
    likely that font bearing component, not the shared container value.
- **Left-anchored, not a centered 1600px box any more (v1.7.0).** Found live at 2000px wide: the
  hero content sat inside a centered 1600px box, leaving about 400px of empty night background on
  the left while the photo ran to the right edge, reading lopsided. `body.djdigi-has-hero
  .fusion-header { max-width: 1600px; margin-left/right: auto; }` is gone; the header row now just
  spans the page's own full width with `padding-left/right: var(--djd-gutter)` (site.css,
  `clamp(24px, 5vw, 120px)`), the exact same property `.djd-hero__inner` uses for its own side
  padding (hero.css, see "Smoothness" below for the rest of this release). The logo's own
  `margin-left: var(--djd-name-edge)` (v1.6.2, above) did not need to change at all: it was already
  reading its offset relative to the header's own padding box rather than a hardcoded number, so
  swapping that box's padding from a fixed 40px to the gutter kept the logo and the name tied
  together automatically. Same unverified-against-real-Avada-markup caveat as the two bullets above.
- **The v1.6.2 vertical centering fix did not actually work, found by measuring again (v1.7.0).**
  Mark's new numbers: the logo (72px) and the menu pill (40px) start at the exact same top edge,
  not the same center, still 16px off. That is the specific signature of `align-items: center`
  never having applied to these two as flex siblings in the first place: a genuine center would put
  the taller element's top HIGHER than the shorter one's, not level with it. v1.6.2's own fix
  (centering the image inside the logo's own box) assumed the row's alignment just needed
  reinforcing; this new measurement says that assumption itself was wrong, not merely incomplete,
  so repeating the same kind of fix was not going to help a second time. v1.7.0 instead nudges the
  logo down with a plain `margin-top: calc((72px - 40px) / 2)` (`= 16px`, written as the actual
  difference between the two declared heights rather than a bare number pulled from nowhere), which
  works regardless of whether `.fusion-row` is really flex, grid, or something else entirely, since
  it does not depend on that assumption at all. `align-items: center` on the row is left in place as
  a harmless no-op (costs nothing if it is genuinely never applying), not treated as the real fix
  going forward. Reset to `margin-top: 0` on phone (`@media (max-width: 800px)`, site.css), per
  Mark's own scoping ("desktop widths where the main menu shows") and his explicit "do not break the
  mobile menu": at 52px with Avada's own mobile selector standing in for the 40px pill, this specific
  16px figure does not apply down there at all. **Not verified against the real Avada markup**, same
  caveat as every logo bullet above; if this specific number is also wrong once seen live, the "same
  top edge" clue above is the one worth re-checking first, not just trying a third variant of
  `align-items`.

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
player, DJ, each letter, and, since v1.6.2, the glow and the photo frame too): the echo and each EQ
bar are the only two left with an infinite companion animation (drift, the wave) running forever, so
will-change stays on those rather than coming off while they are still actively animating.

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

A space inside a heading built by `djdt_split_letters()` is now rendered as plain text, not its own
letter span (v1.4.1, found live: "Where I've played" rendered as "Wherel'veplayed"). Every letter
used to get wrapped in its own `display: inline-block` span, spaces included, for the per-letter
stagger; a space that is the ONLY content of an inline-block collapses to zero width in every
browser, since leading/trailing whitespace trimming happens within that box's own formatting
context, where a lone space is both the first and last character. Letters still get their own
staggered span as before (`--i` only increments for them, not for spaces), only a space itself
is now output as-is. This applies to any future heading built with this helper, not just the
venues one, even though the hero name ("DJ", "DigiMark") has no spaces to have been hit by it.

Admin (its own "Venues" screen under the DigiMark menu, see "Admin: three screens" below): a venue
is `{ id, name, details, link, on, logo_color }`, the same hidden-JSON-field pattern as hero photos
(`admin.js`, drag to reorder, `wp.media` for the logo). Name and details are two separate fields
since v1.4.0 (a venue saved before that only had one "caption" field like "Necto Nightclub (Ann
Arbor, MI, 10 year weekly residency)"; `djdt_venue_name_details()` splits an old row like that into
name and details at read time, text before the first "(" as the name, text inside the parentheses
as the details, so nothing Mark already entered needed retyping, it just shows up pre-split the
first time the Venues screen loads after updating). The logo is optional: a venue with no logo
shows its name (and details, if any) in the display font instead (`djdt_venue_item()`, PHP). Logo
color is per venue, White or Original: White renders every logo in one flat off-white regardless of
its own source color or format, via a static (never animated) CSS filter, `brightness(0)
invert(0.92)`, brightness(0) crushes any non-transparent pixel to solid black while keeping its
alpha, then invert flips that to a consistent off-white, which is what makes mismatched logos read
as one set; Original keeps the logo's own colors, dimmed to 85% opacity until a visitor's pointer is
over that specific item (`.djd-venues__logo.is-original`, venues.css). The default is no longer
always White (v1.4.1, found live: Necto, a JPG with a solid background, was defaulting to White and
rendering as a flat gray box, since `brightness(0) invert(0.92)` crushes an opaque JPG's own
background the same as any other pixel, there is no transparency in a JPG for it to read through
at all). `djdt_logo_default_color()` picks White only for an SVG (vector, counted as transparent by
convention) or a PNG/WebP that a pixel sampling pass (`djdt_image_has_alpha()`, GD, a grid of
sample points rather than every pixel) actually finds to carry real transparency, not just an alpha
channel that happens to be fully opaque; everything else, a JPG above all, defaults to Original.
This runs once for every existing venue on update (`DJDT_LOGO_COLOR_RECIPE`, a version-gated
`admin_init` pattern; the duotone regeneration below used to share this exact pattern but moved to
a per-photo postmeta check in v1.5.1, see "Smoothness" below for why) so Mark did not have to fix
Necto by hand,
and again for a freshly picked logo in admin.js (a lighter mime-only guess there, since the browser
does not have the file's own pixel data to sample). Mark can still override per venue from the
dropdown either way. SVG logos
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
ticker anymore, same escaping and `·` grouping behavior. The "What I play" label above it was
removed entirely in v1.5.0, Mark's call: a tiny wide tracked uppercase label read as filler next to
the genres themselves. The genres now carry the whole statement on their own: large display type
(`clamp(28px, 3vw, 36px)` desktop, smaller on phones), one color (paper), no per-item accent color
(`.is-accent` is gone from both the PHP and the CSS); separated by a small muted dot rather than the
old orange one. Wraps to two lines on narrow screens without issue, still centered.

## Smoothness: baked halftone photos, the TV static transition, and a fixed glow
**v1.11.0 retired the TV static transition and the fixed glow entirely (the mirror ball's own circle
reveal and light specks replace both, see "Mirror ball hero" above); the halftone photo bake itself
is unchanged and everything below about it is still accurate.** Kept for history.

The hero photo panel and the venue strip were both choppy (v1.4.0 report: "like the old ticker").
The likely cause this time was never backdrop-filter (that lesson already shaped venues.css from the
start, see "Venues" above); it was the hero photo itself carrying three repaint-forcing layers at
once under the Ken Burns zoom: a `filter: grayscale(1) contrast(1.05)`, a `mix-blend-mode: color`
duotone overlay, and a `mask-image` edge fade, all three recomputed every frame the photo moved or
crossfaded. Fixed by moving the tint server side, baked into the file itself once, not applied live:
- `djdt_ensure_halftone()` (djdigi-theme.php) bakes a copy of a hero photo once, when it is saved
  into Hero Photos, not on every front end page load. Through v1.5.4 this was a flat duotone tint
  (grayscale, then a violet to cyan gradient lookup); **v1.6.0 replaced it with a riso halftone
  look** (Mark's reference: his own riso flyer), Mark's call: grayscale with a contrast boost, then
  two rotated dot screens, violet for the shadows (75 degree screen angle) and cyan for the mids (15
  degrees), on a near white paper ground (`#eef6fa`), the violet layer offset 2px right and 1px down
  from the cyan layer so the two screens read slightly off register, like a real riso print.
- **v1.6.1, found live: the dots read big and soft, and the whole photo read as dark texture, face
  and decks hard to make out.** Two separate causes, two separate fixes:
  - **Soft dots**: `djdt_halftone_decode_gd()` only ever downscaled to `DJDT_HALFTONE_MAX_DIM`,
    never up, so an 800px source (two of the three live hero photos) got its dot screen generated
    at 800px and was then stretched up by the browser to fill the photo panel along with everything
    else, dots included, which is what read as big and soft rather than crisp. Fixed by always
    resizing to exactly `DJDT_HALFTONE_MAX_DIM` on the long side now, up or down (Lanczos in
    Imagick, `imagecopyresampled` in GD, both before the halftone screen is drawn), the one
    deliberate exception to "never upscale" anywhere in this plugin: the point is the dot screen
    itself always gets generated at full resolution, and the halftone's own texture hides the
    softness an enlarged photo would otherwise show plainly. Cell size also went finer alongside
    this, about 3 to 4px at `DJDT_HALFTONE_MAX_DIM` now (`$long / 550`), down from about 6px
    (`$long / 333`), since the output is effectively always at that same full resolution now rather
    than sometimes being a smaller, coarser canvas.
  - **Dark overall**: `djdt_halftone_gray()` raised its contrast (`-50`, up from `-32`) and added
    `imagegammacorrect( $gray, 1.0, 1.35 )` after it, a mid gamma lift that brightens the midtones
    specifically while leaving true black and true white exactly where they are (unlike a flat
    brightness shift, which would also wash out the highlights this recipe wants to stay close to
    paper white). The violet "shadow" layer's own cutoff also narrowed (`djdt_halftone_layer()`,
    `$l / 0.38`, down from `$l / 0.55`), so it claims less of the tonal range now and more of what
    used to read as deep violet shadow reads as cyan, or breaks through to paper, instead. On the
    page, hero.css's glow alpha values also came down across all three (`.djd-hero__glow--a/b/c`,
    roughly 0.62/0.5/0.7 down to 0.38/0.42/0.4): the glow sits above the photo on purpose (closing
    the seam, see below), but at the old values it was a second, page level layer of darkening
    stacked on an already dark bake, closer to a general wash across the panel than the left/bottom
    edge fades that are supposed to be the only things softening the photo.
  - `DJDT_HALFTONE_RECIPE` bumped to 2 for both of these, forcing every hero photo to rebake once
    with a fresh, unique file name (see below), verified by baking all three real source files
    again with the actual updated code and looking at the output, not just reasoning about the
    parameters.
- **The decode/render split (v1.6.0) is what actually fixed the "live host only" gray bug below for
  good.** `djdt_halftone_decode_gd()` uses Imagick, when available, only to ping, read, orient and
  resize a source photo (the same memory safe `'jpeg:size'` scaled decode as the v1.5.3 fix),
  nothing procedural; the result is hard right back into a GD resource (`imagecreatefromstring()` on
  a PNG blob). From that point on, both the Imagick assisted path and the Imagick unavailable
  fallback run through the exact same GD code, `djdt_halftone_render()`/`djdt_halftone_layer()`,
  plain pixel operations only (`imagefilledellipse`, `imagecolorat`, `imagecolorallocatealpha`), no
  ImageMagick coder of any kind (`'gradient:'`, `'pattern:'`, `'xc:'` or similar). Two decode paths,
  one rendering path: there is no way for "the same look" to drift apart between Imagick and GD,
  because past the decode step they are not two recipes, they are one.
- **The duotone era's gray bug, in short** (full blow by blow is in git history if it is ever needed
  again): v1.4.0 shipped pure gray (an Imagick colorspace bug, fixed v1.4.1). v1.5.1 to v1.5.3 found
  live that photos could stay stuck on an old bake across several admin page loads with nothing
  visible anywhere, fixed by moving from one global "already ran" option to per-photo
  `'_djdt_bake_ver'` tracking, a memory/time safe decode, one bake per admin page load, and a
  `'_djdt_bake_error'` postmeta plus `admin_notices` hook so a stuck photo has somewhere to show up.
  v1.5.4 found live that the rebake actually ran and still wrote a flat gray file: the prime suspect
  was ImageMagick's `'gradient:'` pseudo coder being silently neutered by a hardened host's
  `policy.xml` instead of throwing. None of that diagnosis could ever be confirmed against the live
  host directly (no shell or browser access to it from any of these sessions' tool sets), which is
  why v1.6.0 does not just patch around a specific coder, it removes Imagick's role in the actual
  pixel output entirely, see above.
- **`djdt_bake_is_gray()` is the permanent backstop, carried forward unchanged in spirit from
  v1.5.4, recalibrated for this recipe.** Samples a 10x10 grid of points in the baked JPEG right
  after any bake that reported success; a halftone bake has large near white paper regions between
  dots (paper itself already has R/G/B differing by up to 12), so the threshold is 30, not the old
  duotone recipe's 8, and at least 3 genuinely colorful points are required, not just 1, so a bake
  that is only partly covered in dots still passes while an all paper, genuinely failed bake does
  not. A bake that fails this check is treated exactly like a caught exception: the bad file is
  deleted, `'_djdt_bake_error'` records why, and the hero shortcode's existing fallback serves the
  unprocessed original instead of a broken file. Keep this check regardless of how solid the decode
  split above seems; it is what would catch a bug like this one again, from any cause.
- **Every baked file gets a unique name now (v1.6.0, `djdt_halftone_filename()`): the recipe version
  plus a short hash of the source file's own mtime and size** (`djdt-halftone-{id}-v{recipe}-
  {hash}.jpg`), not a name keyed on the attachment id alone. Found live after v1.5.4: baking had
  started working correctly on the live host (files rebaked in color, confirmed by timestamp), but
  visitors still saw gray, because the new files kept the OLD file names, so the browser's own cache
  and Cloudflare both kept serving the old bytes under that same URL. A new bake is always a new URL
  now, nothing stale can be served under it; the previous file is deleted (`@unlink()`) once the new
  one is saved and verified by the gray check above, so uploads does not accumulate orphaned bakes.
- Baked files regenerate for any existing hero photo whose own `'_djdt_bake_ver'` postmeta does not
  match the current `DJDT_HALFTONE_RECIPE` (an `admin_init` hook, one photo per admin page load, see
  the duotone era summary above for why both of those design choices exist), so a recipe fix reaches
  photos Mark already picked without him needing to resave Hero Photos. Bump `DJDT_HALFTONE_RECIPE`
  any time `djdt_halftone_render()`/`djdt_halftone_layer()` change in a way that means an already
  baked file would look wrong until regenerated; because the recipe number is baked into the
  filename itself now, bumping it also forces a fresh URL, not just a fresh file, so there is nothing
  left for a cache to serve stale even on the very first page load after the bump.
- The baked file's path is cached in postmeta (`'_djdt_halftone_file'`); `djdt_halftone_url()`
  regenerates it on demand if that meta or the file is ever missing, so a photo saved before this
  recipe existed self heals the first time it is needed, no resave required. If baking is not
  possible at all, the photo falls back to the plain, unprocessed original rather than a broken
  image.
- The bundled fallback photo got the same treatment ahead of time: `hero-photo-halftone.jpg` is a
  pre-baked copy of `hero-photo.jpg`, shipped as a file rather than generated at runtime, and is
  what actually gets served when no photos are picked in Settings.
- hero.css has no `filter`, `mix-blend-mode` or `mask-image` on `.djd-hero__photo` or
  `.djd-hero__photos` at all.

**The seam's real fix (v1.5.0) is layer order, not where the fade is computed, and this is still
true.** Bottom to top: the night background, the photo panel (a plain static image now, see below),
a static CSS gradient overlay on the panel fading its left and bottom edges to night
(`.djd-hero__photos::after`, wide, eased, several stops), then the glow on top of all of that (below
the text and UI). A CSS overlay on the panel always covers the same edge of the panel regardless of
what the photo content underneath is doing, unlike something baked into the file; and with the glow
above the photo instead of behind it, it washes continuously across wherever the panel's actual edge
falls, so no seam can show at any width regardless of photo aspect ratio. Glows stay plain radial
gradients with alpha the whole time, no filter, no blend mode, no mask.

**v1.6.2 stopped both the glow and the hero photo moving on their own, Mark's call after a live
review of v1.6.0.** Two separate, unrelated motions, two separate fixes:
- **The glow no longer drifts.** Each glow used to run `djd-glow-in` (the one time entrance) and
  then `djd-glow-drift` forever after, a slow 16s loop sliding and scaling it. Harmless while the
  glows sat behind the photo, but they have sat above it since v1.5.0 (closing the seam, see
  "Smoothness" above), so a moving color wash sliding over the halftone dots read as a color changer
  rather than a glow, and was tiring to look at. `djd-glow-drift` is removed entirely (the keyframes
  and the second animation on `.djd-hero__glow`, hero.css); each glow now plays its one time
  entrance and sits still. Since the glow is no longer infinitely animating, it is now included in
  hero.js's `will-change: auto` cleanup after the intro finishes too (it used to be deliberately left
  out, since clearing it while the drift was still running would have undone the hint for an
  animation that was still active).
- **No more Ken Burns.** Each hero photo used to run `djd-kenburns` (`scale(1)` to `scale(1.06)`,
  7.6s, restarted on every rotation, `.is-kenburns` toggled in hero.js) continuously while active.
  Scaling a fine halftone dot screen (the riso bake, see "Smoothness" above) causes shimmer and
  moire, an interference pattern between the dot grid and the scaling/resampling, which got more
  visible, not less, once the dots got finer and crisper in v1.6.1. Removed entirely, CSS and JS
  both (`djd-kenburns` keyframes and the `.is-kenburns` rule gone from hero.css, `restartKenBurns()`
  gone from hero.js): the photo now displays still, at scale 1, like a print. The only thing that
  still moves the photo frame at all is the one time `djd-photo-in` fade-and-scale on the very first
  photo on page load, which ends at `scale(1)` and has nothing after it, and the brief 1 to 2px
  `djd-static-jitter` during a rotation transition, kept exactly as it was, see "The crossfade" below.
  The TV static transition itself is unchanged.
- Verified with a Playwright page, not just by reading the CSS: `getComputedStyle()` on both
  `.djd-hero__glow` and `.djd-hero__photo` sampled at 3s, 7s and 13s after load (spanning at least
  one photo rotation) read an unchanged, identity transform (`matrix(1, 0, 0, 1, 0, 0)` or `none`)
  at every sample, and `document.getAnimations()` on the glow shows only `djd-glow-in`, already
  finished. Re-ran the same frame cost measurement as v1.6.0 (see "Measuring smoothness" below)
  afterward: no regression, if anything one less thing animating forever.

**The crossfade between rotating photos is TV static now, not a shutter (v1.6.0, replacing the
shutter entirely, Mark's call).** The shutter (`djd-shutter-open`/`djd-shutter-settle` keyframes,
the frame's own `clip-path`/`scale` animation, `is-entering`) is gone. In its place:
- `.djd-hero__static` (hero.css), a layer above the photo frames inside `.djd-hero__photos`: a
  `.djd-hero__static-scan` child (faint horizontal scanlines, a static `repeating-linear-gradient`,
  not animated on its own) and a `.djd-hero__static-img` child (the noise sprite,
  `assets/img/tv-static.jpg`, 5 frames laid out side by side, procedurally generated, not a photo).
  About 0.5s total: the whole layer's opacity animates 0 to 0.35 to 0 over 500ms
  (`@keyframes djd-static-fade`), while the sprite itself flickers through its 5 frames via
  `transform: translateX()` stepped with `steps(5)` on a 250ms loop, transform only, not a
  `background-position` animation (which would repaint instead of just recomposite).
- hero.js (`photoRotation`) adds `.is-static` to start the fade, waits `PEAK_MS` (220ms, matching
  the fade's own 44% opacity peak), swaps `.is-active` from the outgoing frame to the incoming one
  right then (hidden behind the static, so the swap itself is instant, no crossfade of the photos
  themselves needed), adds `.is-jitter` to the incoming frame for a brief 1 to 2px horizontal
  jitter (`@keyframes djd-static-jitter`, `steps(4)`, about 110ms), then removes `.is-static` at
  `TOTAL_MS` (500ms). This transition is unchanged by v1.6.2's removal of Ken Burns (see
  "Smoothness" above); the jitter is still the only thing that briefly moves the incoming frame.
- Only the very first photo on page load still plays the older fade-and-scale entrance
  (`djd-photo-in`, `.is-first`), unchanged; every later photo arrives via the static transition.

**The glow is one fixed violet and cyan set now, no color cycling (v1.6.0, removing the "room
light" entirely, Mark's call).** The two crossfading glow layers (`.djd-hero__glow-layer`, the
`data-set="a"/"b"/"c"` color sets, `roomLight` in hero.js) are gone; `.djd-hero__glows` now holds
the three glow shapes directly, each with its own fixed `background` (violet `#5a2e91`, cyan
`#24a9e0`). The glow is also allowed to bleed past the hero's own bottom edge now: `.djd-hero__glows`
no longer clips to `inset: 0`/`overflow: hidden` of its own, and `.djd-hero` itself dropped
`overflow: hidden` entirely (`.djd-hero__photos`, the photo panel specifically, keeps its own clip,
it still needs to). See "The hero flows into the page" below for why.

**The hero flows into the page now, no hard bottom edge (v1.6.0, Mark's call).** Two changes:
- The scroll shrink effect (the hero scaling down into a rounded card as you scroll past it,
  `--djd-shrink-scale`/`--djd-shrink-radius` on `.djd-hero`, `--djd-scroll-name`/`--djd-scroll-name-
  op` on `.djd-hero__stage`, the whole `scrollShrink()` function in hero.js) is removed entirely,
  CSS and JS both. The hero no longer transforms at all on scroll; normal document flow continues
  straight into the player section below it.
- `.djd-hero__bottom-fade` (hero.css), a new full width overlay over roughly the hero's own bottom
  25 to 30%, fading the photo panel and the glow into flat night. This is what actually guarantees
  no visible line across the width regardless of what the photo or the now-unclipped glow are doing
  right at the bottom; it extends a little past the hero's own box, harmless since the player
  section right after it shares the exact same night background (see below).

**The Mixcloud player moved out of the hero into its own section (v1.6.0, Mark's call): the hero
now keeps only the name, slogan, the two buttons and the photo.** `djdt_hero_shortcode()` outputs a
`<section class="djd-player-section">` (`djdt_player_section()`) right after the hero's own closing
`</section>`, no separate shortcode, since this section only ever belongs right after the hero.
`player="no"` on `[djdigi_hero]` turns it off entirely (`shortcode_atts()`). Its markup and behavior
(the artwork, the play button, the scrubber, -30/+30, time, the Mixcloud and Tracklist links, the
same Mixcloud Widget API wiring in hero.js) are otherwise unchanged from the old in-hero player,
just relocated; hero.js now looks the player up from `document`, not from the hero element, since it
is a sibling section now, not a descendant, and it no longer has an entrance animation gated on the
hero's own intro-ready sequence (`--djd-t-player` is gone from site.css's timing block) since it is
below the fold on a normal viewport anyway.
- **"No outlined box, space and a gentle tone step only"** (Mark's exact wording) is about
  `.djd-player` itself, not the whole section: `.djd-player-section` and its `__inner` wrapper carry
  the plain, unmodified night background, the same as the hero and the venues section, so all three
  read as one continuous night scene with no seam between them, exactly as asked. The tone step (a
  slightly lighter navy, `#140f30`, no border) sits only on `.djd-player`'s own card width, replacing
  the old bordered/`rgba` background box. Getting the tone step onto the section instead of the card
  was the first draft here and it was wrong: it recreated exactly the kind of seam against venues
  this item was supposed to avoid, caught by looking at a real screenshot, not by re-reading the
  brief carefully enough the first time. **v1.7.0, Mark's call: the card itself now spans gutter to
  gutter too** (`.djd-player`'s own `max-width: 760px; margin: 0 auto;` is gone), not a narrow
  centered box inside the section any more, see "Hero layout" below; the tone step still sits only
  on the card, same reasoning, just a wider card now.

Photos hold about 6 seconds each (`SLOT`, hero.js, unchanged across every version so far). Still
pauses rotation entirely when the tab is hidden (`visibilitychange`, unchanged).

## Hero layout: left anchored, gutter to gutter (v1.7.0)
Found live at 2000px wide: the hero content sat inside a centered 1600px box, so about 400px of
empty night background sat to the left of it while the photo ran all the way to the right edge,
reading lopsided rather than deliberate. Mark's call: drop the centered box entirely and anchor
everything to one shared side gutter instead, a layout that keeps making sense from phone width up
through an ultra-wide monitor rather than looking right only inside one specific box size.

- **The 100vw scrollbar bug.** `.djd-hero`, `.djd-player-section` and `.djd-venues` all broke out of
  Avada's own centered, padded container the same way: `width: 100vw; margin-left: calc(50% -
  50vw);`. `100vw` includes the scrollbar's own width, which `50%` (measured against the actual,
  scrollbar-excluded viewport) does not, so the section sat about 7px too far left and overhung the
  same amount on the right, confirmed live (`.djd-hero__inner` measured starting at -7px at 912px
  wide). Fixed by dropping the trick entirely: `width: 100%; ` with no `margin-left`. This only
  works because the page itself uses Avada's "100% Width" template with a zero padding container
  (readme.txt, "Test page", the documented, required setup for any page carrying `[djdigi_hero]`):
  under that template the section's own parent chain is already exactly the right width with no
  padding of its own, so matching it with a plain percentage needs no scrollbar math, JS
  measurement, or custom property at all, the simpler of the two options Mark offered. If a hero
  page is ever built without that exact template, this assumption needs rechecking.
- **Found while testing the fix above, not something this release caused**: at narrow widths, the
  three fixed pixel sized glows (`.djd-hero__glow--a/b/c`, 820 to 1000px wide, unchanged since
  v1.5.0) spill well past `.djd-hero`'s own box, which has had no `overflow: hidden` of its own
  since v1.6.0 (on purpose, so the glow and the bottom fade can bleed past the hero's bottom edge,
  see "Smoothness" above). `body.djdigi-theme`'s existing `overflow-x: hidden` (site.css, a
  deliberate belt and suspenders rule already) was assumed to suppress this via the CSS spec's own
  body-to-viewport overflow propagation, but testing this release's harness directly (`window.
  scrollTo()` actually moved the page) showed that propagation cannot be relied on. `html:has(body.
  djdigi-theme)` now sets `overflow-x: hidden` explicitly too, removing the ambiguity outright
  rather than trusting propagation to resolve it the same way in every browser. The glows
  themselves are unchanged and still oversized for a phone width panel; this only stops that from
  ever becoming a scrollable page, it does not resize them.
- **`--djd-gutter: clamp(24px, 5vw, 120px)`** (site.css, on `body.djdigi-theme`) is the one value
  that now controls every side edge this release touches: `.djd-hero__inner`'s own padding
  (hero.css), the header row's padding (site.css, "Header over the hero" above), and
  `.djd-player-section__inner`'s padding (hero.css). One fluid number rather than a fixed 40px,
  so it keeps making sense at 390px and at 2560px alike, not just the widths actually measured
  live. `.djd-hero__name`'s own `--djd-name-edge: -8px` offset (v1.6.2) and `.fusion-logo`'s
  matching margin are unaffected by this change, they read their own value independent of
  whatever box they happen to sit inside.
- **The photo panel's left edge moved from 42 to 44% down to a flat 40%** (`.djd-hero__photos`,
  hero.css, the `@media (min-width: 1100px) { left: 44% }` override removed entirely, one number
  now). The name now deliberately overlaps the photo's own left edge a little rather than sitting
  entirely beside it; it stays legible on top purely on z-index (`.djd-hero__inner` is `z-index:
  10`, well above the photo panel's `z-index: 1` and its own edge fade's `z-index: 2`), the same
  left/bottom fade overlay and fixed glow as before, untouched.
- **The name scales with the screen now, one fluid clamp down to phone width.** `.djd-hero__digimark`
  is `clamp(72px, 9vw, 200px)`, Mark's own exact numbers ("on a 2000px screen, the name should fill
  roughly the left half"), replacing the old `clamp(76px, 16.5vw, 240px)` plus a separate
  `clamp(52px, 19vw, 88px)` override at 800px wide. The dedicated phone override is gone entirely:
  the single clamp's own floor (72px) lands almost exactly where that old phone-only value did at
  390px (about 74px) on its own, so the extra rule was no longer doing anything a plain floor
  couldn't. `.djd-hero__dj` (`clamp(34px, 3vw, 70px)`) and `.djd-hero__slogan` (`clamp(19px, 1.1vw,
  22px)`) were scaled to keep the exact same min/max ratio to `.djd-hero__digimark` the old values
  had (DJ about 0.47/0.35, the slogan about 0.26/0.11), not independently guessed, since Mark asked
  for these two to "scale in proportion" without giving exact numbers of their own. The masked
  letter entrance and the violet echo (see "Venues" above, grid stacked spans) are unchanged by any
  of this, font-size does not affect how they animate.
- **The player card spans gutter to gutter now too** (`.djd-player`'s own `max-width: 760px;
  margin: 0 auto;` removed, see "Smoothness" above), the artwork staying a fixed size on the left
  while the title and waveform simply take up whatever width is left, same tone step background, no
  outline.
- Verified locally with a Playwright page at 390, 912, 1440, 1920 and 2560 wide (not just by reading
  the CSS): `.djd-hero`'s own left/right edges exactly match the viewport width at every size (no
  7px overhang, no horizontal scroll, `document.documentElement.scrollWidth` equals the viewport
  width exactly at all five), the photo panel sits at exactly 40.0% at every width from 912 up,
  `.djd-hero__digimark` never wraps to two lines at any of the five, and `.djd-player`'s own left
  edge matches `--djd-gutter`'s own computed value exactly at every width. **What this cannot
  verify**: the logo and menu numbers from the brief (912px: logo center versus menu pill center,
  logo left edge versus the name's), since this harness has no real Avada header markup to render,
  same limitation as the v1.6.1 and v1.6.2 logo work above.

## v1.7.1: #main's own side padding, and the logo/menu row actually made flex
Mark measured live again at 912px wide, logged in, on v1.7.0, and found the v1.7.0 work above had
not actually landed as intended:
- **#main carries its own `padding: 0 30px`**, Avada's own "100% Width" template padding, which
  `.djd-hero`, `.djd-player-section` and `.djd-venues` all sit inside in normal document flow (they
  are not the header, which floats `position: absolute` outside this box entirely). v1.7.0's
  `width: 100%` fix assumed that box was already the full page width purely because the *page's
  own* builder container (readme.txt, "Test page") has zero padding; it does, but `#main` itself,
  one level further out, does not, and was never zeroed. Net effect: a 30px strip of page
  background on each side, on all three sections, the hero no longer full bleed at all, and the
  header no longer lining up with the content below it since the header reads its own `--djd-gutter`
  from the window edge while the content read it from 30px in.
- **The v1.6.2/v1.7.0 vertical logo fix never actually worked either.** New numbers: the logo (72px)
  and the menu pill (40px) start at the exact same TOP edge, not the same center, 16px apart, the
  specific signature of `align-items: center` never applying to these two as flex siblings at all (a
  genuine center puts the taller element's top higher, not level with the shorter one's). v1.7.0's
  own fix (a `margin-top` nudge sized to the measured difference) worked around that without fixing
  it, which is fragile: it only holds for exactly 72px and 40px, and breaks again the moment either
  size changes.

Two fixes, both in site.css:
- **`#main`'s own side padding is zeroed outright on hero pages**: `body.djdigi-has-hero #main {
  padding-left: 0 !important; padding-right: 0 !important; }`, added right next to the existing
  `padding-top: 0 !important` rule (same reasoning, same precedent: v1.4.1 already zeroes this box's
  top padding unconditionally on these pages). Considered the alternative Mark offered, reading
  `#main`'s own padding back per breakpoint into a custom property instead of zeroing it: rejected,
  since that needs Avada's own responsive padding values at breakpoints never actually measured
  live, where zeroing needs nothing guessed at all. Safe specifically because this project's own
  test plan keeps a hero page to just `[djdigi_hero]` then `[djdigi_venues]`, nothing else in `#main`
  on these pages that would want the inset back. **If a hero page ever gets other, non-full-bleed
  content below the venues card, that content needs its own explicit side padding**, since this
  removes the one global inset for the whole page, not just these three sections.
- **The header row is now actually made `display: flex` (`@media (min-width: 801px)`)**, rather than
  assuming Avada's own row already is one: `align-items: center`, `justify-content: space-between`,
  `float: none !important` on `.fusion-logo` and `.fusion-main-menu`. This is the first version of
  this fix that does not depend on that assumption holding, and does not need to know either
  element's exact height to work. v1.7.0's `margin-top` nudge on `.fusion-logo` is removed entirely,
  it would only reintroduce an offset on top of a now-genuine center. Scoped to above the mobile menu
  breakpoint on purpose (Mark's own wording, "leave Avada's mobile header layout alone apart from the
  logo size"): the unconditional `padding-top: 32px` and `align-items: center` v1.6.1/v1.4.1 put on
  this row move inside the same media query, so mobile reverts to Avada's own untouched row layout
  below 801px, only the logo's own size (separately, `@media (max-width: 800px)`, unchanged) differs
  from stock. `.fusion-logo` also gets `margin-bottom: 0 !important` now, canceling whatever default
  spacing Avada's own markup puts there, unrelated to the row's own alignment.
- Both fixes are logically independent, but the first is also what makes the second's own
  `--djd-gutter` reasoning correct: once `#main` carries no padding, `.djd-hero__inner`'s padding and
  `.fusion-header`'s padding both measure from the same window edge, so the logo and name's matching
  `margin-left: var(--djd-name-edge)` (unchanged since v1.6.2) lines up correctly without needing its
  own change here.
- **Verified with a Playwright page reconstructing the measured structure** (`#main` padding 0 30px,
  the header as a sibling absolute wrapper, the real site.css/hero.css/venues.css), not just by
  reading the CSS, at 390/912/1440/1920 wide: hero left edge is exactly 0, hero width exactly matches
  the window width, logo left exactly matches the name's left, and `document.documentElement.scrollWidth`
  equals `clientWidth`, at every one of the four widths. The logo-center-equals-menu-center check
  also passes at 912/1440/1920. **It does not pass at 390 in this harness**, expected and not a
  regression: the harness has no real Avada mobile markup (a hamburger toggle instead of this
  logo/pill pair), and the fix is deliberately scoped to desktop widths only, per Mark's own
  instruction above; what the real mobile header actually looks like there can only be confirmed
  live. **Not verified against the real Avada markup otherwise**, same standing caveat as every
  logo/header bullet above: this harness is built from Mark's own measured numbers and the standard
  `.fusion-row`/`.fusion-logo`/`.fusion-main-menu` pattern, not from rendering the real theme.

## v1.7.2: style A bake recipe, and the header row's real clearfix fix
Two unrelated fixes, confirmed by Mark live.

**The bake recipe is a smooth duotone base plus a light halftone overlay now ("style A"), not
dots-only.** Mark built this live on Home v2 and picked it over the alternatives before this was
written up as a brief. Through v1.7.1 the two ink screens painted straight onto a blank paper
canvas (`djdt_halftone_layer()`, scattered filled ellipses at dot centers): between dots there was
nothing but flat paper, so the dots themselves were the only thing carrying any detail at all.
v1.7.2 replaces the whole render step with a genuinely per pixel one (`djdt_halftone_render()`,
djdigi-theme.php): every output pixel gets its own color from a smooth four stop gradient map
(`djdt_halftone_gradient_rgb()`) driven by that pixel's own luminance (0.299/0.587/0.114 weights, a
contrast curve, then a 0.9 gamma), so a face, headphones and decks stay fully legible everywhere,
not just where a dot happens to land. The two halftone screens (`djdt_halftone_screen_distance()`,
a point rotated into each screen's own grid basis and measured against the nearest cell center, in
cell units) then only nudge a pixel a fixed fraction toward a darker ink color when it falls inside
that screen's own dot (45% toward `#3a1a6e` for violet/shadows, 30% toward `#1f8fc4` for cyan/mids),
never replacing the gradient's own base color outright. The violet screen samples 2px left and 1px
up from the cyan screen's own position (not the output pixel's true position), the off register
look a real riso print's two passes have when they land slightly apart. `djdt_halftone_gray()`,
`djdt_halftone_color()` and the old `djdt_halftone_layer()` are gone entirely, replaced by this one
function; the gray output check, the unique per bake file name, and the per photo bake version
tracking all carry forward unchanged (`DJDT_HALFTONE_RECIPE` bumped 2 to 3, the Imagick-assisted
decode still only pings/reads/orients/resizes before handing off to the one shared GD render path,
see djdt_halftone_decode_gd()'s own doc comment). **Verified with a parallel Node reimplementation
of the exact same formulas** (no PHP interpreter available in this environment to run the real
function), against the one real source photo available locally (the bundled `hero-photo.jpg`, not
the three live source photos Mark's own live demo used, which live in WP's uploads on the server):
the output reads exactly as intended, a smooth tinted photo with the face, headphones, decks and a
forearm tattoo all clearly legible, fine dot texture visible only on a 4x crop. **Not run against
the actual PHP code or the three real source photos**, and the per pixel approach (up to around 3 to
4 million `imagecolorat()`/`imagesetpixel()` calls plus trig and `pow()` per pixel, no native
vectorization available in plain PHP) is real work for a shared host even with the existing raised
memory/time limits; if a bake times out or 500s on the live host, that per pixel cost is the first
thing to profile, not assumed to be some other regression.

**The header row's `display: flex` fix from v1.7.1 was spreading 5 items, not 2.** Mark measured
live again at 912px: `.fusion-header .fusion-row` is flex with `justify-content: space-between` as
v1.7.1 set it, but Avada's own clearfix pattern on this same row (the row's own `::before`/`::after`
pseudo elements, plus a `div.fusion-clearfix` child) count as ordinary flex items too, so
`space-between` was spacing all five of them apart, not just the logo and the menu: the logo
measured at left 106px against the name's own 38px, and the menu sat at left 246px instead of the
gutter's own right edge. A clearfix exists only to force height in normal (float based) flow,
meaningless the moment its container is already flex, so hiding all three outright (`display: none`
on `.fusion-row::before`/`::after` and the `.fusion-clearfix` child, scoped to this row, inside the
same `@media (min-width: 801px)` block v1.7.1 added) is correct, not a workaround. `.fusion-main-menu`
also gets `margin-left: auto` now, pinning it to the row's own right edge directly rather than
depending on `justify-content: space-between` alone to do that correctly if a third invisible item
like this ever reappears. Separately, found in the same live check: `.fusion-logo` still carried
Avada's own default `margin-top: 10px`, never actually overridden (only the v1.7.0 nudge sitting on
top of it had been removed in v1.7.1), which is what put the logo's own center 5px below the menu
pill's. `.fusion-logo`'s margins are now set as one shorthand, `margin: 0 0 0 var(--djd-name-edge,
-8px) !important`, rather than individual properties that leave whichever side nobody has measured
yet to Avada's own default. **Verified with a Playwright page reconstructing Avada's actual clearfix
markup this time** (not just the logo/menu pair as in v1.7.1's own harness, which could not have
caught this bug since it never modeled the clearfix pieces that caused it): at 912/1440/1920 wide,
logo left exactly matches the name's left, logo center exactly matches the menu's center, and the
menu's own right edge exactly matches the gutter's computed value, all within rounding. The 390px
check (mobile) does not apply to the logo-center-equals-menu-center target specifically, same as
v1.7.1: this fix is scoped to desktop widths only, per Mark's own instruction to leave Avada's
mobile header layout untouched apart from the logo's own size.

## v1.8.0: Events moved here from djdigi-library, and the hero's glow now shows through below it
Two unrelated changes, confirmed by Mark live.

**`[djdigi_events]` moved into djdigi-theme, built where it actually ships from now.**
djdigi-library has no update checker at all (the live Plugins page shows no update link for it,
confirmed live), so any code there can only ever ship as a manual FileZilla zip upload, never an
automatic update; djdigi-theme already updates itself through a GitHub release (see "Releasing an
update" above). The events module (reading Modern Events Calendar Lite's own post type, post meta
and location taxonomy directly, no MEC front end involved) was first built and tested in
djdigi-library v0.2.0, never deployed there (see that plugin's own CLAUDE.md); v1.8.0 ports it into
djdigi-theme instead, its own self contained file (`includes/djdt-events.php`,
`require_once` near the top of `djdigi-theme.php`), own CSS/JS (`assets/css/events.css`,
`assets/js/events.js`, registered and enqueued the same way hero.css/hero.js and
venues.css/venues.js already are). Same features, same behavior, same tests already run there:
`[djdigi_events limit="5"]` and `[djdigi_events]`, the cards, the CD tray, the before 6 AM rule
(a start before 6 AM reads as the previous night), "Midnight" formatting, same-AM/PM collapsing,
`mec_in_days` occurrences (used over the base `mec_date` for a repeating event, which can go stale
once later occurrences are added), upcoming-only filtering, the empty state, Event JSON-LD, the 301
redirect of MEC's own single event pages, and the MEC front end asset dequeue (matched by handle
name/path pattern, still not confirmed against MEC's actual registered handles, same caveat as when
this was first built). **djdigi-library itself was not touched**: its own v0.2.0 working copy,
events module included, was left exactly as it was, not uploaded, not released, a historical
staging ground now rather than live code.

What actually changed in the port, not just the file's own location:
- CSS renamed from djdigi-library's own `djl-` prefix to this theme's own `djd-events` (the section
  itself `.djd-events`, the card `.djd-events__card`, its own pieces `.djd-events__card-photo` /
  `-body` / `-venue` / `-date` / `-excerpt` / `-eject` / `-tray` / `-tray-inner` / `-disc` / `-full` /
  `-address` / `-more`, one dash joining the card element name to its own sub-part rather than a
  second BEM block, matching how `.djd-venues__card` is named, not a separate top level block of its
  own). `--djl-gutter` (the library version's own `var(--djd-gutter, clamp(...))` fallback, for
  when djdigi-theme might not be active) is gone: this file reads `--djd-gutter`, `--djd-night` and
  every other `--djd-*` custom property straight from site.css now, no fallback needed, since this
  is djdigi-theme's own variable rather than one plugin reaching across to another one's.
- **Gated with `djdt_is_active()`, the same check every other theme feature already uses, not its
  own separate "Live for everyone" setting.** The djdigi-library version had its own
  `djdigi_events_live` option and its own Settings > DJ Events screen, safe by default the same way
  (logged in admins only, until checked), but a second independent toggle all the same. Dropped
  entirely: `djdt_events_shortcode()` now gates on `djdt_is_active()` directly, exactly like
  `djdt_hero_shortcode()` and `djdt_venues_shortcode()` do, so one "Live for everyone" checkbox
  (Theme Settings) now covers the hero, the venues card and events together, not three independent
  switches that could drift out of sync with each other.
- **The events page URL now reads `djdt_get('sets_url')`**, the same admin configured "Upcoming
  sets" setting the hero's own button already points at (Theme Settings, previously unused by
  anything else in this file), instead of a hardcoded `'upcoming-events'` slug constant: one place
  to change it, not two.
- The MEC dequeue and the single-event redirect stay unconditional, not gated behind
  `djdt_is_active()`: MEC's own front end is not used on this site at all regardless of whether
  djdigi-theme itself is public yet, same behavior the djdigi-library version already had.
- Asset registration lives in its own `add_action('wp_enqueue_scripts', ..., 20)` inside
  `includes/djdt-events.php` itself (self contained, not added to djdigi-theme.php's own existing
  enqueue block), `djdt-site` as a dependency; `djdt_events_shortcode()` enqueues both only once it
  actually has something to render, the same pattern `djdt_venues_shortcode()` already uses rather
  than djdigi-library's own content-scanning pre-check (`shortcode_present_on_request()`), since that
  simpler pattern is already proven live here.
- **Verified with a Playwright page** reconstructing the real Avada structure (same harness as
  v1.7.2's own header fix, extended with the player section, an events row and the venues card in
  Home v2's own real order) at 390/912/1440/1920: hero full bleed still holds, no regression. **Not
  run against a real MEC install or the real PHP code** (no PHP interpreter available in this
  environment, no live/logged-in site access from this session, same standing limitation as the
  halftone recipe work in v1.7.2): the ported date parsing, MEC handle matching and redirect logic
  are carried over unchanged from what was already tested live in djdigi-library's own session, not
  re-verified here.

**Section order on Home v2 needs no code of its own, just shortcode placement**: hero (and the
player section it outputs right after itself automatically) first, then whatever comes next in the
page content. Mark adds `[djdigi_events limit="5"]` after `[djdigi_hero]`, then `[djdigi_venues]`
stays last, giving hero, player, events, venues in that order. Checked for any CSS that assumed a
fixed order (an adjacent sibling selector like `.djd-player-section + .djd-venues`, which would have
broken the moment events sat between them): none exists anywhere in this plugin's CSS, every section
is styled entirely on its own class, order-independent by construction.

**Soft bottom edge: the hero's own glow now actually shows through the sections below it, no hard
line.** Measured live on v1.7.2: `.djd-hero__glows` (hero.css, `overflow: visible` by design, see
"Smoothness" above) genuinely does extend about 260px past the hero's own bottom edge (hero bottom
599px, glow bottom 859px, at the width measured), but `.djd-player-section` painted its own solid
night background right on top of that overflow, cutting the glow off in a straight horizontal line
instead of letting it fade into nothing the way its own radial gradient already does everywhere
else. The fix is not in the glow itself, which is unchanged:
- `#main` carries the night background directly now on hero pages (site.css, the same rule that
  already zeroes its side padding, v1.7.1), not just a transparent box.
- `.djd-player-section`, `.djd-events` and `.djd-venues` all go `background: transparent` on hero
  pages (site.css, one rule covering all three), so the glow (and `#main`'s own flat night beneath
  it, once the glow's own alpha has faded to nothing) show through underneath with nothing painted
  over it. Each of these three sections still carries its own solid night background by default in
  its own stylesheet (hero.css, events.css, venues.css), for when it is used away from the hero
  entirely, for example `[djdigi_events]` on the standalone Events page with no hero above it; the
  hero page override only applies on `body.djdigi-has-hero`.
- `.djd-player-section__inner`, `.djd-events` and `.djd-venues` each got an explicit
  `position: relative; z-index: 1;` (hero.css, events.css, venues.css), so their own content stays
  provably above the glow now that there is nothing solid behind it to begin with, rather than
  relying on `.djd-hero`'s own `isolation: isolate` and plain DOM paint order to keep that true on
  its own, which does already hold today but is not something future CSS here should have to
  re-derive from scratch to avoid breaking.
- **Verified two ways**: computed `background-color` on `#main` (night) and on all three sections
  (`rgba(0, 0, 0, 0)`) via the same Playwright harness, at all four widths; and a real screenshot of
  the hero-to-player seam (glow markup added to the harness, `djd-intro-ready` forced on since
  nothing drives the real intro timing in a static page) showing the glow fading smoothly into flat
  night across the boundary, no visible edge at all. **Not checked against the real live glow sizes
   or exact fade distance**, same standing limitation as the rest of this release: no logged-in
  access to measure the real rendered page from this session.

## v1.8.1 hotfix: every events behavior actually behind djdt_is_active()
Found live, logged out, right after v1.8.0 shipped: `/upcoming-events/` (MEC's own real calendar,
not yet switched to `[djdigi_events]`) rendered MEC's own markup (about 200 `mec-` classes) with
zero MEC CSS/JS actually loaded, and an MEC single event page (`/events/midnight-sun/`) 301
redirected for a logged out visitor even though the theme itself was still admin only. Both bugs
trace back to the same mistake: v1.8.0 kept the MEC dequeue and the single page redirect
unconditional, independent of `djdt_is_active()`, on the reasoning that MEC's front end is never
used on this site regardless of whether djdigi-theme is public yet. That reasoning does not survive
contact with "admin only until Live for everyone is checked": the one guarantee that setting exists
to make is that a logged out visitor sees the site exactly as it was before this plugin, untouched,
and both of these behaviors broke that for a logged out visitor specifically.

Two fixes, both in `includes/djdt-events.php`:
- `djdt_events_redirect_mec_singles()` now returns immediately when `djdt_is_active()` is false, same
  one-line gate every other theme feature already uses.
- `djdt_events_dequeue_mec_assets()` is an allowlist now, not a blocklist, the opposite of v1.8.0's
  own approach. v1.8.0 stripped MEC's assets everywhere EXCEPT a page with a literal MEC shortcode
  in it, which is exactly what misfired on the real `/upcoming-events/` page: its own MEC markup
  apparently does not come from a literal `[MEC...]` shortcode tag the old scan could see, so the old
  logic treated it as "safe to strip" when it was not. The new version only ever dequeues on a page
  it can positively confirm carries `[djdigi_events]` (`djdt_events_current_request_renders_djdigi_events()`,
  the same literal post_content string scan technique the MEC shortcode check already used, done this
  early specifically because `wp_enqueue_scripts` runs before the main content loop has actually
  executed any shortcode yet, so there is no later point to check "did it actually render" instead)
  AND that same page does not also carry a real MEC shortcode of its own. Everywhere else, MEC's own
  assets are left alone, Mark's own instruction: "if unsure, keep MEC assets." Also gated behind
  `djdt_is_active()` itself now, same as the redirect.
- The `djdt-events` style/script REGISTRATION (not just enqueueing) is also now gated behind
  `djdt_is_active()`, matching how `djdt-hero` and `djdt-venues` are already registered in
  djdigi-theme.php's own main asset block: v1.8.0 had left this one unconditional, an inconsistency
  this hotfix also cleaned up while making the "every events behavior behind djdt_is_active()" rule
  actually hold everywhere in this file, not just in the two places the live bugs were found.
- Not verified against the real site in this session (no live or logged in access available): traced
  through by hand instead, both scenarios Mark described (a logged out visit to `/upcoming-events/`,
  a logged out visit to an MEC single page) and the existing homepage case (`[djdigi_events limit="5"]`,
  logged in or live, MEC assets correctly still dequeued there).

## v1.8.2: real viewport width breakout, correct occurrences, sleeker cards, a CD drawer
Four pieces, all confirmed by Mark live.

**Full width sections now read a real measured viewport width (`--djd-vw`), not an assumption about
the page template.** Found live at 2000px wide: Home v2's content now sits inside a real Avada
Builder container, `div.fusion-fullwidth` (padding 30px) `>` `div.fusion-builder-row.fusion-row`
(max-width 1248px) `>` a column `>` `div.fusion-text`, several containers deeper than the flat "100%
Width template, zero padding container" every release through v1.7.2 assumed (see hero.css's own
v1.7.0/v1.7.1 history). `width: 100%` only ever matches a section's own DIRECT parent, so under a
builder row like this one every full bleed section (`.djd-hero`, `.djd-player-section`, `.djd-events`,
`.djd-venues`) rendered at the row's own ~1200px content width, not the window's. Fixed with a small
inline script, printed synchronously in `wp_head` (`djdigi-theme.php`, before the body ever paints,
so nothing to flash) that sets `--djd-vw` to `document.documentElement.clientWidth` in px, refreshed
on resize; every section now uses `width: var(--djd-vw)` plus `margin-left: calc(50% - var(--djd-vw)
/ 2)`, the classic break-out-of-any-container trick, just driven by a real number instead of `100vw`
(which includes the scrollbar, the v1.7.0 bug) or an assumption about the parent. Falls back to the
old, pre v1.8.2 `100%` behavior if JS never runs (`var()`'s own fallback syntax). Also, found and
fixed in the same pass: `.djd-venues` carries its own 40px side padding directly (unlike `.djd-hero`/
`.djd-player-section`, whose padding lives one level down on a child) with no `box-sizing` of its own,
so it had actually been rendering 80px wider than its own parent since v1.3.0, silently clipped by
`overflow-x: hidden` rather than ever showing as a scrollbar; now `box-sizing: border-box`.
Separately, any Avada `fusion-fullwidth` container that actually holds one of these four sections has
its own side padding zeroed (`:has()`, site.css, with a `djd-breakout-host` class fallback each
section's own JS adds for a browser without `:has()` support), and `overflow: visible` is forced on
the likely Avada ancestors in between (`fusion-fullwidth`, `fusion-builder-row`, `fusion-layout-column`,
`fusion-column-wrapper`, the column chain not confirmed live, written defensively). **Verified with a
Playwright harness built two ways**, the real nested Avada structure Mark measured AND a flat,
unwrapped one (the brief's own "with the Avada container... and without one"), at 390/912/1440/2000/
2560: every section's own left and right edge matches the window exactly, zero gap either way,
logo-left still matches the name's left at every width, no horizontal scroll.

**Events occurrences now come from MEC's own occurrence table, not hand parsed postmeta.** Found
live: the homepage row showed two single, non repeating events from January 2025 (long past) as
upcoming, and was missing "DJ DigiMark at The Cafe" entirely, a real occurrence three days away. The
v1.8.0/v1.8.1 postmeta based parsing (`mec_date`'s own base start/end, `mec_in_days` for a
"custom_days" repeat) is kept only as a fallback now, for a site where MEC's own table does not
exist; the primary source is `{$wpdb->prefix}mec_dates` (`post_id`, `dstart`, `dend`, `tstart`,
`tend`), which MEC itself maintains as the authoritative occurrence list for every repeat type,
custom days and exceptions included, so this module no longer has to correctly reverse engineer
MEC's own postmeta schema by hand, the likely source of both bugs (`djdt_events_occurrences_from_mec_dates_table()`,
`includes/djdt-events.php`; falls back to `djdt_events_occurrences_from_postmeta()`, the old logic,
renamed but otherwise unchanged, if the table is missing). The date/time columns are combined and
parsed with a plain `DateTimeImmutable` string constructor rather than a hand written format
specific one (`djdt_events_datetime_from_mec_row()`): their exact stored format was not confirmed
live (no DB access from this session), and guessing it wrong would silently reintroduce the same
kind of bug this table exists to fix. Filtering (end timestamp after now, full date and time, year
included, a `DateTimeImmutable` comparison never a string one) and sorting (by start) both now
happen once, centrally, after occurrences are built, regardless of which source produced them.
**Not verified against the real database or the real MEC install** (no DB or live access from this
session): traced through by hand against Mark's own expected result list (The Cafe Oct 22, Midnight
Sun Oct 23, The Edge Oct 30, The Edge Nov 6, Badlands Nov 13, nothing from 2025), not re-run live.

**Sleeker cards (Mark's call).** Two collapsed lines only now, venue then time; the date moved onto
the photo itself as a stub block (a solid night box, day/number/month stacked in the display font,
the number largest and in cyan); the description, the venue address and the Tickets/Info link all
moved into the drawer, gone from the collapsed card entirely. Every card is now exactly the same
height as a direct result: the venue line is clipped to one line with an ellipsis
(`.djd-events__card-venue`, `white-space: nowrap`) rather than ever wrapping, so a long venue name
can no longer grow one card taller than its neighbors the way the old excerpt line could. The eject
button shrank from 40px to 32px and moved from a circle overlaid on the photo to sitting inline at
the end of the venue line, muted paper, brighter on hover/focus. `--row` and `--grid` are the same
auto-fit grid on desktop now (`repeat(auto-fit, minmax(240px, 1fr))`, wrapping to a second row
rather than ever scrolling sideways, Mark's own instruction this release); they only diverge on
phone (`@media (max-width: 800px)`), where `--row` becomes a horizontal swipe with `scroll-snap` and
a hidden scrollbar (`scrollbar-width: none`, a `::-webkit-scrollbar` rule, Mark's call, v1.8.0's own
"thin, not hidden" choice is gone) and `--grid` just narrows its own columns. "See all dates" is a
button now, matching the hero's own outline button (`.djd-btn--line`, hero.css) by eye, with its own
standalone CSS rather than reusing that literal class: this section has to stand on its own when
used away from the hero entirely (the standalone Events page, where hero.css never loads at all),
and `.djd-btn`'s own base state is `opacity: 0`, normally only ever paired with the hero's own
entrance animation, which would have made a reused button permanently invisible there.

**A new CD drawer, replacing the under card tray entirely.** Press eject: the card lifts
(`translateY(-6px)`, keyed off `.djd-events__card:has(.djd-events__card-drawer.is-open)`, a browser
without `:has()` just skips the lift, the drawer itself still opens correctly either way, nothing
here is load bearing); a full disc slides out from the card's own right side like a slot loading CD
player (`translateX` from fully behind the card to its resting spot, 1100ms opening, 600ms closing,
the asymmetry is just which rule's own `transition` applies when `.is-open` is added versus removed,
not a separate close-specific rule), spinning 540 degrees as it goes, easing to a stop
(`cubic-bezier(0.16, 1, 0.3, 1)`), never spinning back on its own; the details panel unfolds beside
it with `clip-path` plus opacity, delayed 400ms so it reads as a second beat. Positioned absolute,
anchored to the card's own right edge (`left: 100%`), so it takes no track/grid space of its own when
closed; the panel's own `max-width: min(380px, calc(100vw - 48px))` is what keeps an open drawer from
ever actually pushing the page wider than the viewport even near the row's own right edge, confirmed
by opening the LAST card specifically (the worst case) at every tested width with no horizontal
scroll at any of them. On phone the drawer switches from beside the card to below it (`position:
static`, `flex-direction: column`): there is no room to slide a disc sideways on a phone width card,
so it slides down instead, same easing, same spin. One open at a time, Escape closes, focus moves
into the drawer on open and back to its own button on close, same pattern as the v1.8.0/v1.8.1 tray
(`events.js`). The `hidden` attribute's own UA stylesheet rule carries no `!important`, so this
section's own `display: flex` on the drawer would otherwise quietly beat it (author origin always
wins ties with UA origin): `.djd-events__card-drawer[hidden] { display: none; }` is what actually
keeps a closed drawer out of the tab order and the accessibility tree, not the attribute alone, a
latent gap the old tray markup likely also had, fixed here while rebuilding the drawer from scratch.
**Verified with a Playwright page driving the real open/close logic** (`drawer.hidden`, the `is-open`
class, same code events.js runs): disc transform and opacity, panel opacity, and the card's own
raised z-index all confirmed correct once settled; a screenshot of an open drawer on both desktop
(beside the card) and phone (below the card, no horizontal scroll) both read as intended by eye. The
test harness only ever had placeholder discs with no real photo behind them, so the disc's own
spinning label was not checked against a real image.

## v1.8.3 fix: mec_dates' own tstart/tend are timestamps, not clock time strings
Found live, logged in: v1.8.2 showed the empty state on Home v2 even though MEC had five real
upcoming events. Cause: `djdt_events_datetime_from_mec_row()` concatenated `tstart`/`tend` onto
`dstart`/`dend` as if they were a time of day string, but MEC's own `mec_dates` table stores those
two columns as Unix timestamps (confirmed live), so the combined string (a date plus a ten digit
integer) threw inside `DateTimeImmutable` every time, the catch returned null, and every single
occurrence from that table was silently dropped, no fallback, just the empty state.

Fixed in `includes/djdt-events.php`: a numeric `tstart`/`tend` is now read as a timestamp
(`djdt_events_wall_time_from_timestamp()`), resolved against that same row's own `dstart`/`dend`
(MEC's own 'Y-m-d', never in question). The timestamp alone is ambiguous, since MEC appears to store
a wall clock time encoded as if it were already UTC rather than a genuine, correctly converted UTC
instant (no DB access from this session to confirm which, so both are tried): a plain `gmdate()`
reading and a properly converted `wp_date()` one, trusting whichever one's own calendar date
actually lands on `dstart`/`dend`. If neither one agrees with the row's own date at all (or the
column was not numeric to begin with), `djdt_events_wall_time_from_postmeta_time()` falls back to
that same trusted date plus a time of day read from the post's own postmeta instead, the matching
`mec_in_days` entry for that exact date on a custom days post, otherwise `mec_date`'s own base
start/end time. And at the whole list level: if the table exists, returns rows, but not one of them
produces a usable occurrence (`$had_unparsed_rows`, `djdt_events_occurrences_from_mec_dates_table()`,
by reference), the caller now falls back to the postmeta reader for the entire list and logs one
`error_log()` line, rather than ever quietly showing the empty state again the way this exact bug
just did.

**Verified with a pure logic simulation** (no PHP interpreter or DB access in this environment,
consistent with every release this session): JS standing in for `gmdate()`/`wp_date()` against both
storage conventions (MEC's own "fake UTC" quirk, and a genuine correctly converted UTC timestamp,
including an end time crossing midnight), confirming the matching picks the correct wall clock time
either way. **Not run against the real database or the real MEC install**, same standing limitation
as v1.8.2's own occurrence table work; this needs a real live check more than most patches this
session, since a wrong guess here reproduces exactly the kind of silent, total failure this fix
exists to catch.

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

**Never hard code these screens' hook suffixes.** v1.5.2, found live: the `admin_enqueue_scripts`
check that loads `admin.js`/`admin.css` (media frame, the photo and venue pickers) compared `$hook`
against hard coded strings guessed from the usual convention, "{parent_slug}_page_{slug}" ("toplevel_
page_{slug}" for the first one). That guess was wrong: WordPress actually builds a submenu's hook
suffix from the parent menu's sanitized MENU TITLE, not its slug, and the menu title here is
"DigiMark", not "djdigi-theme". The real hooks were `digimark_page_djdigi-theme-photos` and
`digimark_page_djdigi-theme-venues`, so the hard coded strings never matched, admin.js never
loaded on Hero Photos or Venues, and the picker, the rendered rows, and Add all silently did
nothing. Fixed by capturing what `add_menu_page()` and `add_submenu_page()` themselves return
(global `$djdt_admin_hooks`, set in the `admin_menu` callback) and comparing against that instead
of any guessed string. If this menu is ever restructured, never hard code a hook suffix again,
always read it back from what WordPress actually returned.

## Cream gaps from wpautop (v1.4.1, fixed properly in v1.4.2)
Found live: a cream gap above the hero, another between the hero and venues, and another above the
footer. Cause: `wpautop` (WordPress core, runs on the raw post content before shortcodes expand)
has no idea `[djdigi_hero]` and `[djdigi_venues]` become block level markup (a `<section>`), so it
treats the bare shortcode tags the same as any other line of text: wrapping a lone one in its own
`<p>` (which carries its own `margin-bottom`), and inserting a `<br>` between two of them written on
adjacent lines. By the time `do_shortcode` runs and expands the tags, that wrapping is already baked
into the HTML.

v1.4.1 tried `add_filter( 'the_content', 'shortcode_unautop', 10.5 );`, hooked at a float priority
specifically to land between `wpautop` (10) and `do_shortcode` (11). That printed a PHP deprecation
warning ("Implicit conversion from float 10.5 to int") at the top of every page on the live site,
for every visitor, not just in admin: a hook priority is expected to be an int, and WordPress casts
whatever it is given. **Never use a float priority on any hook.** If something genuinely needs to
run between two adjacent integer priorities, there is no number for that; reorder by registration
order at the same priority instead (callbacks at an equal priority run in the order they were
added), or find a different, real integer priority that still lands in the right place. v1.4.2
removed the filter entirely.

The actual fix is CSS alone now: `body.djdigi-has-hero .post-content > p:first-child:not(:has(*)),
> p:empty, > br { display: none; }` (site.css). `:empty` alone does not match the paragraph wpautop
wraps a lone shortcode tag in: that `<p>` still has a whitespace text node inside it (the newline
wpautop left there), which counts as content for `:empty`'s purposes even though nothing visibly
renders. `:not(:has(*))` matches correctly instead, a paragraph with no element children at all,
whitespace included. Scoped to `:first-child` since that is the one actually confirmed still
showing (the hero is always the first thing in that container); a `<br>` between two adjacent
shortcode lines is a real, genuinely empty element on its own, already caught by the plain `> br`
selector, no special casing needed there.

## Motion: always on, by design
**Reaffirmed, not revisited, for the mirror ball hero (v1.11.0): the brief for that release asked
for a `prefers-reduced-motion` branch (no drop, static dim specks, a plain crossfade instead of the
circle reveal) and it was deliberately left out, Mark's call, to keep this policy intact rather than
carve out an exception. See "Mirror ball hero" above, "No reduced motion branch".**

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
**The hero layout, name and photo bullets below describe the pre-v1.11.0 design (right side photo
panel, typeset name, fixed glows). See "Mirror ball hero" above for the current layout; colors,
fonts and the venues/genre bullets below are unaffected and still current.**
- Paper `#f1ece2`, violet `#5a2e91`, ink `#2a1450`, night `#0b0820`, logo cyan `#24a9e0`, International Orange `#f04e23` (sparingly).
- "DigiMark" in the hero uses logo cyan and is mixed case, not all caps, matching the logo. "DJ" is off-white on the dark hero.
- Display font: Saira Extra Condensed, 700/800. Text font: Archivo. Buttons and the player status tag are sentence case now, not wide-tracked uppercase, except the venues heading and the name, which stay uppercase display type.
- Hero layout: the photo is large, the right 60% of the hero (left edge at 40%, v1.7.0, down from 42 to 44%), full height, bleeding to the right edge, with the name deliberately overlapping into it on the left. A fade layer washes the left and bottom edges into night so the photo never shows a hard rectangle. Phone: the photo goes full bleed behind everything with a stronger dark wash for text contrast. No hero labels: the name and slogan carry the hero alone (removed entirely in v1.2.1, markup and CSS, Mark's call). The hero keeps only the name, slogan, the two buttons and the photo now (v1.6.0): the player moved to its own section right after, see "Smoothness" above. Left anchored to a shared `--djd-gutter`, not a centered 1600px box, from phone width up through an ultra-wide monitor (v1.7.0, see "Hero layout: left anchored, gutter to gutter" above).
- Hero intro (about 1.6 to 1.8s, see "Intro: fast, overlapping, waits for the photo and fonts" above): glows bloom first, then the photo fades in with a slight scale in, then "DJ" and "DigiMark" sweep up letter by letter from a full height mask, then slogan and buttons land together, all heavily overlapping rather than running in sequence. The header carries no entrance animation of its own (v1.4.0), the logo and menu pill are simply in place from the first frame. Quick version on a repeat view in the session; `?intro=1` forces the full one. Always plays in full motion, see "Motion: always on, by design". The player's own entrance is no longer part of this gated sequence (v1.6.0, it moved out of the hero, see "Smoothness" above).
- Hero photo: rotates through whatever is picked in Settings (each with its own focal point), with a TV static transition between photos (v1.6.0, replacing the shutter and the room light color shift, see "Smoothness" above), or the bundled photo if none are picked. Each photo displays still at scale 1 while active, no Ken Burns zoom (removed in v1.6.2, Mark's call: it was causing shimmer and moire against the halftone dot screen, see "Smoothness" above). The riso halftone look (grayscale with a contrast boost, then violet and cyan dot screens, v1.6.0, replacing a flat duotone tint) is baked into the photo file itself server side, not a live CSS filter or blend mode, so any photo Mark adds reads as part of the same look without manual editing and without costing a repaint every frame. The edge fade into night is a CSS overlay on the panel, not baked into the file.
- Name layers: echo (the drifting violet ghost) and main (the cyan letters) are stacked with CSS Grid, both in the same grid cell, not positioned by hand. Grid items never margin collapse with their container or each other, which is what actually caused v1.2.0's doubled name bug (the padding and negative margin used to make room for descenders collapsed through on one layer and not the other). Keep them grid stacked rather than going back to relative/absolute arithmetic.
- Scroll: no scroll driven effect on the hero any more (v1.6.0, the scale-into-a-card scroll shrink effect was removed entirely, Mark's call: the hero now flows straight into the player section and venues below it instead, see "Smoothness" above). The photo wall that used to slide in around the old scroll shrink (v1.2.0 and v1.2.1) was already removed entirely in v1.2.2, Mark's call; if tiles come back later they need rebuilding from scratch, not un-reverting either of these.
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
Measured, not just reasoned about, before every release that touches animation: headed Chromium
(Playwright, GPU on, not the headless-only path), 1920x1080, a local harness with the hero's Ken
Burns zoom, a rotation transition in progress, the venues beam and the logo marquee all running at
once, `requestAnimationFrame` timestamps collected for 10 seconds starting 3 seconds after load
(past the opening sequence). This sandbox's own display is not locked to a real 60Hz refresh (plain
`requestAnimationFrame` on a blank page here runs at roughly 145 to 165fps on its own, confirmed
separately), so a raw fps number from it is not a meaningful "is this 60fps" claim. What is
meaningful regardless of the display: per-frame cost, how long the browser actually spent per frame
regardless of how often it was allowed to paint. v1.5.0 result (shutter transition, room light
crossfade, baked duotone with the CSS edge fade, beam, marquee, all running): 0 frames exceeded even
a loose 25ms (well past a 60fps budget's 16.7ms) dropped-frame threshold out of 1646 frames over 10
seconds, p95 6.2ms, max 12.6ms, comfortably inside the 60fps budget. v1.6.0 result (riso halftone
photos, TV static transition, fixed glow with no clipping, no scroll shrink, the player section, the
venues beam and marquee, all running): 1 frame out of 1631 exceeded 25ms (a single ~109ms spike) over
10 seconds, avg 6.13ms, p95 6.20ms; a follow-up pass with network requests to Mixcloud blocked still
showed one isolated spike at a different point in the window, so it is not the player's own widget
preload, more likely a one-off layout or GC pause unrelated to the continuously running animations
rather than sustained jank from anything new in this release. v1.6.2 result (glow drift and Ken
Burns both removed, TV static transition, player section and venues beam/marquee all still running):
2 frames out of 1628 exceeded 25ms (worst 102.9ms) over 10 seconds, avg 6.14ms, p95 6.20ms,
essentially identical to the v1.6.0 numbers; removing two continuously running animations did not
regress anything and the one-off spike pattern looks like the same kind of isolated pause as before,
not sustained jank. First run of this measurement after
v1.6.0 came back far worse (avg 27ms, 164 dropped frames) with `chromium.launch()` given explicit
`--use-gl=desktop --enable-gpu` args; removing those entirely and launching with just `headless:
false` is what produced the clean numbers above, confirmed by a plain blank-page baseline
(163fps raw, 6.1ms avg) matching the same launch config. Do not pass custom GL/GPU flags to
`chromium.launch()` for this measurement again, they measured a software-rendering-adjacent
pathology that has nothing to do with the plugin's own code. Re-run this (the harness and
`measure.js` are not checked into the plugin, rebuild them the same way: hero's and venues' real
markup and CSS/JS, a few seconds of settle time, then collect rAF deltas) before claiming a future
change is smooth; do not just assert a frame rate without it.

**Serve the harness over `http://`, not `file://`.** Found in v1.4.1: a `mask-image: url(...)`
referencing a local SVG from a `file://` page is blocked by Chromium's own CORS rules for the file
scheme (confirmed via a failed request in the page's own console, not silent), so the mask resource
never loads and the mask has no effect at all, same as if it were not there, while everything else
on the page keeps running. That makes a `file://` run of this harness measure a CHEAPER scenario
than production (no mask actually compositing), not a representative one, for anything that uses
`mask-image`, in this plugin that is the venues beam (the hero's own rotation transition moved to
clip-path and transform in v1.5.0, which do not have this issue, but the beam still does). A one
line local server (`python -m http.server` in the harness directory) avoids it entirely and is also
more faithful to the real site, which is always same-origin https.

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
- Queued for live review after v1.7.0, not confirmed live yet:
  1. No horizontal scroll and no empty band on the left at 390, 912, 1440, 1920 and 2560 wide;
     confirmed locally, not just by reading the CSS, via Playwright at all five widths:
     `.djd-hero`'s own edges match the viewport exactly (no 7px overhang either side), and
     `document.documentElement.scrollWidth` equals the viewport width exactly at each one (the
     pre-existing glow overflow this surfaced is also closed now, see "Hero layout" above).
  2. The logo's left edge lines up with "DJ DigiMark"'s own left edge, at every width: still an
     unverified formula (`--djd-name-edge`, from v1.6.2), unchanged in substance this release, just
     now measured against a gutter-based box instead of a fixed one.
  3. The logo is centered with the menu pill, desktop widths only: a different fix than v1.6.2's
     (a direct `margin-top` nudge instead of relying on the row's own `align-items: center`, see
     "Header over the hero" above), after the live numbers showed the v1.6.2 attempt had not
     actually worked. The mobile menu is untouched (checked only by reading the CSS, not rendering
     real Avada markup).
  4. The name scales with the screen, roughly filling the left half at 2000px wide, and the photo
     panel's new 40% left edge with the name overlapping it; confirmed locally that `.djd-hero__digimark`
     never wraps to two lines at any of the five widths, and that `.djd-player`'s own left edge
     matches `--djd-gutter` exactly at each one.
  5. **Still not verified against the real Avada markup, the one recurring gap across v1.6.1,
     v1.6.2 and v1.7.0 now**: the logo's own size, left edge and vertical centering. This harness
     has never had real header markup to render against, only a positioned placeholder; every fix
     in this area so far has been built from Mark's own measured live numbers rather than guessed,
     but none of it has been seen rendering by this tool set. If another round on this specific area
     is needed, that gap is the reason, not a lack of care in the fix itself.
- Resolved in v1.2.1 through v1.6.2, confirmed live: third-level dropdown overlap and colors, mobile
  header overlapping hero content, reduced motion freezing everything, the intro's own speed, two
  different diagnoses of what was making the old ticker choppy before it was removed outright, the
  v1.4.1 duotone color bug, the v1.4.1/v1.4.2 cream gap and float hook priority warning, the header
  alignment, the venues heading's collapsing spaces, the venue logo color default, the Hero
  Photos/Venues admin screens not loading their picker script, the full duotone-era baking saga
  (v1.5.1 through v1.5.4, see "Smoothness" above for the short version), confirmed live on v1.6.1,
  the halftone dots reading crisp rather than big and soft and the photo reading bright and clear
  rather than dark texture, and, confirmed live on v1.7.0 (as the starting point for that batch), the
  glow no longer drifting and the hero photo no longer running Ken Burns (v1.6.2) (see "Venues" and
  "Smoothness" above for what each of those turned out to be).
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
