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
- `assets/css/site.css`: site-wide, scoped to `body.djdigi-theme`. Avada header and menu selectors: `.fusion-header-wrapper`, `.fusion-secondary-header` (small plain social icons, aligned to the 1600px box, NOT the dock), `.fusion-header`, `.fusion-main-menu` (the pill bar) `> ul.fusion-menu`, `.sub-menu` (including explicit dropdown/flyout positioning and the `djd-flip-right` class menu.js adds near the viewport edge), `.fusion-mobile-selector` (classic mobile menu), `.fusion-is-sticky` (sticky state). Also owns the footer dock's styling and the opening sequence's shared timing custom properties (on `body.djdigi-has-hero`, see below).
- `assets/css/hero.css`: hero only, loaded only when the shortcode renders. The large right side photo panel and its edge fade, the photo wall, the scroll shrink transform, the grid-stacked name layers, the player, the ticker, the Pride EQ wave.
- `assets/css/admin.css`, `assets/js/admin.js`: Settings > DigiMark Theme only, the hero photo picker (wp.media, drag to reorder, click to set focal point).
- `assets/js/hero.js`: opening sequence timing (session quick-intro, `?intro=1` override), header height measurement (the taller of the header wrapper and the mobile nav holder, so the hero always starts below whichever actually extends further down), hero photo rotation, the scroll shrink and photo wall driver, the Mixcloud Widget API player (iframe at player-widget.mixcloud.com, see below).
- `assets/js/menu.js`: the sliding pill behind the current menu item (the only highlight in the menu now), carried across page loads via `sessionStorage`. Also keeps dropdown panels inside the viewport near the right edge.
- `assets/js/dock.js`: the magnetic social dock, built once for the footer (full size, plus Mixcloud) and once for the header (small, `.djd-dock--small`, whatever Avada icons are actually there). Each is strictly scoped to its own wrapper's full ancestor path, see "Dock scoping" below. Reads Avada's own rendered social link hrefs rather than owning them, and hides the footer widget area when it has no widgets.
- `assets/img/hero-photo.jpg`: the bundled fallback hero photo, used when no photos are picked in Settings. `wave.svg`: player waveform, inlined by PHP rather than loaded as an image, see below.
- `assets/fonts/`: Archivo (variable, wdth + wght) and Saira Extra Condensed (700, 800), self-hosted, OFL.

## Dock scoping
Avada reuses the exact same `.fusion-social-networks-wrapper` class for the header's small icon
bar and the footer's. An unscoped query for that class finds whichever comes first in the
document, which is what v1.2.0's dock.js fix was for (it was building the footer's dock from the
header's icons). Both docks are built now (v1.2.1 adds a small one to the header, see "Design
tokens"), each from its own full real path, scoped by the distinct middle class Avada gives each
context:
- Footer: `footer#footer.fusion-footer-copyright-area .fusion-social-links-footer .fusion-social-networks-wrapper`
- Header: `.fusion-secondary-header .fusion-social-links-header .fusion-social-networks-wrapper`
Do not shorten either one back to the bare class.

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

## The opening sequence's timing
Lives as custom properties on `body.djdigi-has-hero` (site.css), not on `.djd-hero`, because the
header's drop-in animation is a sibling of the hero section, not a descendant of it, and a custom
property only cascades to descendants. `body.djdigi-has-hero.djd-quick-intro` (set by hero.js for a
repeat view in the session, or cleared by `?intro=1`) overrides the same properties to shorten
every dependent `animation-delay` at once. Add a new step by adding one `--djd-t-*` variable here,
not by hardcoding a delay where it's used.

## Motion: always on, by design
Nothing on this site checks `prefers-reduced-motion`, anywhere: not in CSS, not in JS, no setting
or toggle. This was removed entirely in v1.2.1 (it previously froze the intro, photo rotation,
ticker, EQ wave, scroll shrink, sliding pill, and both docks for any visitor with that OS setting
on, which is not what Mark wants for this site) and must not be added back. If a future feature is
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
- Hero intro (about 3.6s, see "The opening sequence's timing" above): glows bloom alone first, then the photo pulls into focus from a heavy zoom and blur, then "DJ" and "DigiMark" sweep up letter by letter from a full height mask, then slogan, buttons, player, menu and ticker land together at the end. Quick version on a repeat view in the session; `?intro=1` forces the full one. Always plays in full motion, see "Motion: always on, by design".
- Hero photo: rotates through whatever is picked in Settings > DigiMark Theme (each with its own focal point), cross fading with a slow zoom, or the bundled photo if none are picked. A CSS duotone (grayscale + a violet to cyan color blend) is applied to every photo uniformly, including the bundled one, so any photo Mark adds reads as part of the same look without manual editing.
- Name layers: echo (the drifting violet ghost) and main (the cyan letters) are stacked with CSS Grid, both in the same grid cell, not positioned by hand. Grid items never margin collapse with their container or each other, which is what actually caused v1.2.0's doubled name bug (the padding and negative margin used to make room for descenders collapsed through on one layer and not the other). Keep them grid stacked rather than going back to relative/absolute arithmetic.
- Scroll: past the hero, it shrinks toward scale 0.85 with growing corner radius (toward 28px), while the same rotation photos slide in around it as a loose photo wall (large tiles, not a dense grid, since there are usually only 3 or 4). One scroll driven progress value (0 to 1) feeds both; see hero.js. Off for touch devices only.
- Pride EQ: a shared sine wave drives all 6 bars (`djd-eq-wave`), each with its own `animation-delay` so the motion rolls left to right through the colors, plus a small separate drift animation for a fabric feel, rather than each bar bouncing independently.
- Header dock: the header's own social icons (`.fusion-secondary-header`) get the same magnetic dock as the footer, at a smaller size (`.djd-dock--small`, 28px growing toward 40px near the cursor). Same icons Avada already renders there, no Mixcloud added (that is footer only). Left aligned to the same 1600px box as the logo, since the dock replaces the original icons in place.

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
- Resolved in v1.2.1, confirm live after purge: third-level dropdown overlap and colors (position
  on every nested li, flip inheritance down the chain, always-dark panels), mobile header
  overlapping hero content (now measures the taller of the header wrapper and the mobile nav
  holder), and reduced motion freezing everything for visitors with that OS setting on (removed
  entirely). The header-solid-over-hero item from the v1.2.0 round was confirmed fine live; it only
  ever showed solid in the Playwright test fixture, not on the real site. Closed, no code change.
- Confirm the hero photo rotation actually advances on a fresh (uncached) load with the 3 saved
  photos, now that reduced motion (which was also killing the crossfade transition and Ken Burns
  zoom on Mark's own machine) is gone. If `data-photo-count` in the page source still reads wrong
  after a purge, see "Hero photo rotation depends on a fresh page cache" below.
- Confirm the new small header dock looks right at its grown size (28px toward 40px) within the
  secondary header's actual height on the live site; `.djd-dock--small` has some padding as a guess
  at clearance, not measured against the real bar.
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
