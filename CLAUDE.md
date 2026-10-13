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
- `djdigi-theme.php`: settings page (including the hero photo picker), asset loading (filemtime versions, Cloudflare-safe), body classes, the "Book me" `wp_nav_menu_items` filter, Mixcloud API (cached 6h in transient `djdt_mix`), `[djdigi_hero]`.
- `assets/css/site.css`: site-wide, scoped to `body.djdigi-theme`. Avada header and menu selectors: `.fusion-header-wrapper`, `.fusion-secondary-header` (social bar), `.fusion-header`, `.fusion-main-menu` (now the pill bar itself) `> ul.fusion-menu`, `.sub-menu`, `.fusion-mobile-selector` (classic mobile menu), `.fusion-is-sticky` (sticky state). Also owns the footer dock's styling and the opening sequence's shared timing custom properties (on `body.djdigi-has-hero`, see below).
- `assets/css/hero.css`: hero only, loaded only when the shortcode renders. Rotating photos, the letter-by-letter name reveal, the player, the ticker.
- `assets/css/admin.css`, `assets/js/admin.js`: Settings > DigiMark Theme only, the hero photo picker (wp.media, drag to reorder, click to set focal point).
- `assets/js/hero.js`: opening sequence timing (session quick-intro, `?intro=1` override), header height measurement, hero photo rotation, scroll depth, the Mixcloud Widget API player.
- `assets/js/menu.js`: the sliding pill behind the current menu item, carried across page loads via `sessionStorage`. Also the JS fallback for the "Book me" button, used only if the PHP filter's menu slug or theme location did not match.
- `assets/js/dock.js`: the footer social dock. Reads Avada's own rendered social link hrefs rather than owning them, and hides the footer widget area when it has no widgets.
- `assets/img/hero-photo.jpg`: the bundled fallback hero photo, used when no photos are picked in Settings. `wave.svg`: player waveform.
- `assets/fonts/`: Archivo (variable, wdth + wght) and Saira Extra Condensed (700, 800), self-hosted, OFL.

## The opening sequence's timing
Lives as custom properties on `body.djdigi-has-hero` (site.css), not on `.djd-hero`, because the
header's drop-in animation is a sibling of the hero section, not a descendant of it, and a custom
property only cascades to descendants. `body.djdigi-has-hero.djd-quick-intro` (set by hero.js for a
repeat view in the session, or cleared by `?intro=1`) overrides the same properties to shorten
every dependent `animation-delay` at once. Add a new step by adding one `--djd-t-*` variable here,
not by hardcoding a delay where it's used.

## Reduced motion scope
The blanket reduced-motion rule in site.css matches `[class*="djd-"]` and `[class*="djdt-"]`, on
purpose, so it only ever disables animations and transitions on elements we built, never Avada's
own. The one exception is the header drop-in animation, which we put directly on `.fusion-header-wrapper`
(an Avada class) because that's the element that needs to drop in; it has its own narrow
reduced-motion override naming it specifically, right next to where the animation is defined.

## Body classes
- `djdigi-theme`: plugin active for this viewer.
- `djdigi-has-hero`: the page content contains `[djdigi_hero]`. The header becomes absolute and transparent over the hero.
- `djdigi-hero-strip`: `[djdigi_next_up]` exists (from djdigi-library), so the Next Up strip shows above the header and the header shifts down 46px.

## Design tokens
- Paper `#f1ece2`, violet `#5a2e91`, ink `#2a1450`, night `#0b0820`, logo cyan `#24a9e0`, International Orange `#f04e23` (sparingly).
- "DigiMark" in the hero uses logo cyan and is mixed case, not all caps, matching the logo. "DJ" is off-white on the dark hero.
- Display font: Saira Extra Condensed, 700/800. Text font: Archivo. Buttons, hero labels and the player status tag are sentence case now, not wide-tracked uppercase, except the ticker and the name, which stay uppercase display type.
- Hero intro (about 3.5s, see "The opening sequence's timing" above): glows bloom, the photo sharpens in, "DJ" then "DigiMark" rise letter by letter from a mask, slogan and EQ, buttons and the player, then the menu drops in and the ticker slides up. Quick version on a repeat view in the session; `?intro=1` forces the full one. Respect `prefers-reduced-motion`.
- Hero photo: rotates through whatever is picked in Settings > DigiMark Theme (each with its own focal point), cross fading with a slow zoom, or the bundled photo if none are picked. A CSS duotone (grayscale + a violet to cyan color blend) is applied to every photo uniformly, including the bundled one, so any photo Mark adds reads as part of the same look without manual editing.

## Hero copy (approved)
- Labels: "Video DJ since 2008" / "San Francisco". No "Castro", no "Remix Producer".
- Slogan: "Bringing positive vibes to the dance floor" + pride-colored EQ bars (rainbow flag and music in one mark).
- Ticker items come from settings. Default: House, EDM, Top 40 Remixes, "70s · 80s · 90s · 00s Dance" (one grouped item), Pride/Queer Pop & Dance, Live Video DJ Sets.
- Keep the hero lean. Background details belong on the About page.

## Navigation
- The menu is the real WordPress menu (Appearance > Menus). Do not rebuild it in code.
- Blog is being removed from the menu (Mark's call, Oct 12 2026). That is a menu edit in WordPress, not code.
- Mark's real menu is named "digimenu" (desktop `ul#menu-digimenu.fusion-menu`, mobile `ul#mobile-menu-digimenu`). The "Book me" button is added by a `wp_nav_menu_items` filter matched on that menu slug or the `main_navigation` theme location, whichever the current `wp_nav_menu()` call used, so it works with no JS and shows in the mobile menu too. menu.js only adds it itself if neither matched.

## Testing in the static harness
Use Playwright (`page.setViewportSize`, or a mobile device preset), not headless Chrome's own
`--screenshot` CLI flag. That flag has a hard floor around 500px regardless of the requested
window size (confirmed against both new and legacy headless modes and a device-scale-factor
workaround), so it cannot render a true phone width. Playwright also has `page.clock` and can
pause a Web Animations API animation at an exact `currentTime`, which the CLI flag's single-shot
capture could not do reliably, for pulling an exact intro-timestamp or mid-crossfade frame.
Headless Chrome also reports `prefers-reduced-motion: reduce` by default, which silently settles
every CSS animation to its end state regardless of any seeking; check for that before trusting a
timed capture.

## Open items
- Mark's test of v1.0.0 through v1.1.0, tagged and released, on a private "Home v2" page (or the staging install at djdigimark.com/staging/). v1.1.0's phone width fix (removing the name's forced `white-space: nowrap`, a tighter DigiMark clamp under 800px) could not be confirmed below 500px in the harness, see "Testing in the static harness" above; a real phone or DevTools responsive mode is the first thing to check.
- v1.1.0 is built against assumed Avada markup beyond what Mark already confirmed by testing (header/menu selectors) and the real footer markup Mark supplied (social links). The footer widget area emptiness check and the exact header background image source are the two places most likely to need a selector adjustment after a real test.
- Restyle remaining homepage sections and pages (New Stuff, Music, Mailchimp, footer, library, PMPro with inline hex only). Avada's own `.fusion-button` sitewide is part of that later pass, not touched in 1.1.0.
- The homepage Blog section in the mockup goes away with the Blog menu item.
