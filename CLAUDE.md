# djdigi-theme

WordPress plugin for djdigimark.com (Avada 7.16.1, classic header v3). It holds the site's look:
fonts, colors, Avada menu restyle, buttons, and the `[djdigi_hero]` homepage hero with a Mixcloud player.
It is separate from `djdigi-library` (videos, music, VIP downloads, and the upcoming events module).

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
`djdt_is_active()`: the theme and hero only load for logged-in admins until Settings > DigiMark Theme >
"Live for everyone" is checked. Keep every new feature behind this check.

## Files
- `djdigi-theme.php`: settings page (including the hero photo picker), asset loading (filemtime versions, Cloudflare-safe), body classes, Mixcloud API (cached 6h in transient `djdt_mix`), the inline waveform helper, `[djdigi_hero]`.
- `assets/css/site.css`: site-wide, scoped to `body.djdigi-theme`. Avada header and menu selectors: `.fusion-header-wrapper`, `.fusion-secondary-header` (no social icons at all now, see "Dock scoping"), `.fusion-header`, `.fusion-sticky-header-wrapper`, `.fusion-header-sticky-height` (Avada's own spacer, hidden on hero pages, see "Header over the hero" below), `.fusion-main-menu` (the pill bar) `> ul.fusion-menu`, `.sub-menu` (including explicit dropdown/flyout positioning and the `djd-flip-right` class menu.js adds near the viewport edge), `.fusion-mobile-selector` (classic mobile menu), `.fusion-is-sticky` (sticky state). Also owns the footer dock's styling and the opening sequence's shared timing custom properties (on `body.djdigi-has-hero`, see below).
- `assets/css/hero.css`: hero only, loaded only when the shortcode renders. The large right side photo panel and its edge fade, the scroll shrink transform, the grid-stacked name layers, the player, the ticker, the Pride EQ wave, the intro's pause-until-ready gate (see "Intro: fast, overlapping, waits for the photo and fonts" below).
- `assets/css/admin.css`, `assets/js/admin.js`: Settings > DigiMark Theme only, the hero photo picker (wp.media, drag to reorder, click to set focal point).
- `assets/js/hero.js`: opening sequence timing (session quick-intro, `?intro=1` override, the ready gate), header height measurement (the taller of the header wrapper and the mobile nav holder, so the hero always starts below whichever actually extends further down), hero photo rotation, the scroll shrink driver, the ticker's speed, the Mixcloud Widget API player (iframe at player-widget.mixcloud.com, see below).
- `assets/js/menu.js`: the sliding pill behind the current menu item (the only highlight in the menu now), carried across page loads via `sessionStorage`. Also keeps dropdown panels inside the viewport near the right edge.
- `assets/js/dock.js`: the magnetic social dock, footer only (Mark's call: v1.2.1 tried a small one in the header too, v1.2.2 reverted that to Avada's plain icons, v1.2.3 hid those outright, social links live in the footer only now). Reads Avada's own rendered social link hrefs rather than owning them, hides the footer widget area when it has no widgets, and hides the secondary header entirely if that leaves it with nothing else in it.
- `assets/img/hero-photo.jpg`: the bundled fallback hero photo, used when no photos are picked in Settings. `wave.svg`: player waveform, inlined by PHP rather than loaded as an image, see below.
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
Getting the header to truly float transparent over the hero took three rounds. What finally worked,
v1.2.3:
- **Avada's own global header background image** (an Options panel setting, HeaderMenuBG.png,
  reflected in body classes like `avada-has-header-bg-image`) comes from Avada's own generated CSS,
  which beat an external stylesheet rule here even with `!important`, across two earlier rounds,
  regardless of the exact selector or load order tried against it. The only thing that actually
  guarantees it gone is forcing `background-image: none` as an inline style with the `important`
  priority (`el.style.setProperty('background-image', 'none', 'important')`, hero.js,
  `killHeaderBgImage()`): no stylesheet rule, at any specificity, can beat an inline `!important`
  declaration. Only the image is forced this way; background color is left to CSS, which already
  handles the transparent versus sticky distinction correctly, forcing it too would fight that.
- **Avada's header sticky height spacer** (`.fusion-header-sticky-height`, a placeholder div Avada
  renders right before the real header so the page does not jump when its own JS makes the header
  fixed on scroll) still reserves real document flow height even though the header itself floats
  `position: absolute` here. That is what was actually pushing the hero down below the header
  instead of letting it float over it: not a CSS fight, a structural element nobody had targeted
  yet. Hidden entirely on hero pages (site.css): nothing needs that reserved space here, since the
  header is always floating on these pages, sticky or not.
- **The admin bar**: logged in, WP's admin bar is fixed at the very top of the viewport, the same
  reference point the header floats `top: 0` from, so without an offset the header renders
  underneath it instead of below it. `body.admin-bar .fusion-header-wrapper { top: 32px; }` (46px
  under WP's own 782px admin bar breakpoint) fixes that. Check both states when testing.
- **Readability over the photo**: with the header carrying no background of its own, the logo and
  menu pill need their own contrast. A static top-down gradient wash on the hero itself
  (`.djd-hero::before`, about 200px tall, fading out) covers the logo; the menu bar already has its
  own solid background (see "Ticker speed and smoothness" below for why that is solid, not glass).
- **Avada's separate sticky header clone** (`.fusion-sticky-header-wrapper`, if it exists as a
  genuinely separate element rather than a class toggled on the same wrapper) is covered
  everywhere the plain `.fusion-header-wrapper` is, both in CSS and in the JS pass above, since
  v1.2.1 and v1.2.2 only ever targeted the one wrapper.

If the header ever shows a solid background again, check these five first, in this order: the
inline style actually landed (DevTools, computed style, not just the stylesheet rules), the sticky
height spacer is really hidden, the admin bar offset, the gradient still reads over a bright photo,
and whether Avada changed its own markup (a theme update can rename or restructure any of this).

## The opening sequence's timing
Lives as custom properties on `body.djdigi-has-hero` (site.css), not on `.djd-hero`, because the
header's drop-in animation is a sibling of the hero section, not a descendant of it, and a custom
property only cascades to descendants. `body.djdigi-has-hero.djd-quick-intro` (set by hero.js for a
repeat view in the session, or cleared by `?intro=1`) overrides the same properties to shorten
every dependent `animation-delay` at once. Add a new step by adding one `--djd-t-*` variable here,
not by hardcoding a delay where it's used.

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
player, ticker, DJ, each letter): the glow, the photo, the echo and each EQ bar all keep an infinite
companion animation (drift, Ken Burns, the wave) running forever, so will-change stays on those
rather than coming off while they are still actively animating.

Adding a new entrance-animated element: give it its own `--djd-t-*` delay as usual, and add its
selector to both the `body:not(.djd-intro-ready) ...` pause list and the plain `will-change` list
right after it in hero.css, or it will play immediately on page load instead of waiting.

v1.2.3 kept this timing as is and re-confirmed the same lightness rules apply everywhere in the
hero, not just the entrance: no `filter` or `backdrop-filter` animation anywhere, which is also
what turned out to be degrading the ticker, see "Ticker speed and smoothness" below.

## Ticker speed and smoothness
Rebuilt in v1.2.2 after it read as choppy, rebuilt again in v1.2.3 after it still read like a low
frame rate video. The track holds the ticker items twice in a row (PHP) and animates
`transform: translate3d()` from `0` to `-50%`, linear, infinite; since both copies are identical,
the loop point is seamless. `--djd-ticker-dur` (hero.js) is worked out from the track's own
rendered width divided by a steady 60 to 80px per second, instead of a fixed duration that was a
different, inconsistent speed depending on item count and viewport width.

v1.2.2's fix (animating the bulb dots' position with `transform` instead of `background-position`)
was not actually the main cause. The real one was `backdrop-filter` on the player card and on the
non-sticky menu's glass look (both removed in v1.2.3, solid or more opaque backgrounds instead):
`backdrop-filter` forces the browser to resample whatever is behind it on every single frame
regardless of whether that content visually changed, and with the ticker's own transform animation
running continuously in the same hero, that resampling competed with it for frame time constantly,
which is what actually read as a low frame rate. Do not bring `backdrop-filter` back anywhere in
the hero. The bulb dots are now fully static, not animated at all (not even by `transform`): the
instruction that settled this was "nothing behind the track should animate," full stop, not just
"don't force paint while animating."

`.djd-ticker` (the whole strip, not just the track) also carries `contain: layout paint`, isolating
it from the rest of the page both ways: its own internal layout or paint changes cannot force a
recalculation outside it, and vice versa. Do not animate `left`, `margin`, `background-position`,
`filter`, or `backdrop-filter` anywhere in or near the ticker, and do not drive its position from
JS; transform only, same as everything else in the intro.

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
- Display font: Saira Extra Condensed, 700/800. Text font: Archivo. Buttons and the player status tag are sentence case now, not wide-tracked uppercase, except the ticker and the name, which stay uppercase display type.
- Hero layout: the photo is large, the right 55 to 60% of the hero, full height, bleeding to the right edge, with the name overlapping into it on the left. A dedicated fade layer (not the duotone layer) washes the left and bottom edges into night so the photo never shows a hard rectangle. Phone: the photo goes full bleed behind everything with a stronger dark wash for text contrast. No hero labels: the name and slogan carry the hero alone (removed entirely in v1.2.1, markup and CSS, Mark's call).
- Hero intro (about 1.6 to 1.8s, see "Intro: fast, overlapping, waits for the photo and fonts" above): glows bloom first, then the photo fades in with a slight scale in, then "DJ" and "DigiMark" sweep up letter by letter from a full height mask, then slogan, buttons, player, menu and ticker land together, all heavily overlapping rather than running in sequence. Quick version on a repeat view in the session; `?intro=1` forces the full one. Always plays in full motion, see "Motion: always on, by design".
- Hero photo: rotates through whatever is picked in Settings > DigiMark Theme (each with its own focal point), cross fading with a slow zoom, or the bundled photo if none are picked. A CSS duotone (grayscale + a violet to cyan color blend) is applied to every photo uniformly, including the bundled one, so any photo Mark adds reads as part of the same look without manual editing.
- Name layers: echo (the drifting violet ghost) and main (the cyan letters) are stacked with CSS Grid, both in the same grid cell, not positioned by hand. Grid items never margin collapse with their container or each other, which is what actually caused v1.2.0's doubled name bug (the padding and negative margin used to make room for descenders collapsed through on one layer and not the other). Keep them grid stacked rather than going back to relative/absolute arithmetic.
- Scroll: past the hero, it shrinks toward scale 0.85 with growing corner radius (toward 28px). One scroll driven progress value (0 to 1) feeds it; see hero.js. Off for touch devices only. The photo wall that used to slide in around it (v1.2.0 and v1.2.1) was removed entirely in v1.2.2, Mark's call (nothing showed below the hero live, and he had not actually asked for it); if tiles come back later they need rebuilding from scratch, not un-reverting this.
- Pride EQ: a shared sine wave drives all 6 bars (`djd-eq-wave`), each with its own `animation-delay` so the motion rolls left to right through the colors, plus a small separate drift animation for a fabric feel, rather than each bar bouncing independently.

## Hero copy (approved)
- Slogan: "Bringing positive vibes to the dance floor" + pride-colored EQ bars (rainbow flag and music in one mark).
- Ticker items come from settings. Default: House, EDM, Top 40 Remixes, "70s · 80s · 90s · 00s Dance" (one grouped item), Pride/Queer Pop & Dance, Live Video DJ Sets.
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
   Exceptions (sometimes called "Unload CSS/JS"). Add `djdt-site`, `djdt-hero`, `djdt-menu`,
   `djdt-dock` to the handles excluded from combine, minify and defer. This only needs doing
   once; after that Asset CleanUp should leave these four alone.
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
- Queued for live review after v1.2.3, not confirmed yet:
  1. Header and menu fully transparent over the hero at the top of the page, not a solid dark band;
     check both logged in and logged out (the admin bar offset only matters logged in), and both the
     plain and the sticky (scrolled) state. See "Header over the hero: what actually beat it" above
     if it is still showing solid; that section also says what to check first.
  2. Top bar shows no social icons at all now (footer only); if the bar is otherwise empty it should
     not show at all, no gap above the hero.
  3. Ticker reads as smooth at a steady 60 to 80px per second, DevTools Performance shows a steady
     60fps with no long paint tasks while it runs, and Rendering > Paint flashing shows nothing
     repainting near it.
  4. Intro still finishes in about 1.6 to 1.8s with overlapping steps (unchanged from v1.2.2) and
     still plays in full with OS reduce motion on.
  5. Confirm the hero photo rotation actually advances on a fresh (uncached) load with the 3 saved
     photos. If `data-photo-count` in the page source still reads wrong after a purge, see "Hero
     photo rotation depends on a fresh page cache" below.
- Resolved in v1.2.1 and v1.2.2, confirmed live: third-level dropdown overlap and colors, mobile
  header overlapping hero content, reduced motion freezing everything, the intro's own speed, and
  the ticker's first choppiness fix (though that one turned out to be the wrong diagnosis, see
  "Ticker speed and smoothness" above for what the real cause was).
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
