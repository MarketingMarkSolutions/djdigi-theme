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
- `djdigi-theme.php`: settings page, asset loading (filemtime versions, Cloudflare-safe), body classes, Mixcloud API (cached 6h in transient `djdt_mix`), `[djdigi_hero]`.
- `assets/css/site.css`: site-wide, scoped to `body.djdigi-theme`. Avada header and menu selectors: `.fusion-header-wrapper`, `.fusion-secondary-header` (social bar), `.fusion-header`, `.fusion-main-menu > ul.fusion-menu`, `.sub-menu`, `.fusion-mobile-selector` (classic mobile menu), `.fusion-is-sticky` (sticky state).
- `assets/css/hero.css`: hero only, loaded only when the shortcode renders.
- `assets/js/hero.js`: quick intro on repeat visits (sessionStorage), Mixcloud Widget API player.
- `assets/img/hero-photo.jpg`: color-graded hero photo. `wave.svg`: player waveform.
- `assets/fonts/`: Archivo (variable, wdth + wght) and Big Shoulders Display 800, self-hosted, OFL.

## Body classes
- `djdigi-theme`: plugin active for this viewer.
- `djdigi-has-hero`: the page content contains `[djdigi_hero]`. The header becomes absolute and transparent over the hero.
- `djdigi-hero-strip`: `[djdigi_next_up]` exists (from djdigi-library), so the Next Up strip shows above the header and the header shifts down 46px.

## Design tokens
- Paper `#f1ece2`, violet `#5a2e91`, ink `#2a1450`, night `#0b0820`, logo cyan `#24a9e0`, International Orange `#f04e23` (sparingly).
- "DigiMark" in the hero uses logo cyan. "DJ" is off-white on the dark hero.
- Display font: Big Shoulders Display 800, uppercase. Text font: Archivo, wide uppercase tracking for labels.
- Hero intro: glows fade in, photo un-blurs, name rises, then UI. Respect `prefers-reduced-motion`.

## Hero copy (approved)
- Labels: "Video DJ since 2008" / "San Francisco". No "Castro", no "Remix Producer".
- Slogan: "Bringing positive vibes to the dance floor" + pride-colored EQ bars (rainbow flag and music in one mark).
- Ticker items come from settings. Default: House, EDM, Top 40 Remixes, "70s · 80s · 90s · 00s Dance" (one grouped item), Pride/Queer Pop & Dance, Live Video DJ Sets.
- Keep the hero lean. Background details belong on the About page.

## Navigation
- The menu is the real WordPress menu (Appearance > Menus). Do not rebuild it in code.
- Blog is being removed from the menu (Mark's call, Oct 12 2026). That is a menu edit in WordPress, not code.

## Open items
- Mark's test of v1.0.0/1.0.1 on a private "Home v2" page (or the staging install at djdigimark.com/staging/).
- Restyle remaining homepage sections and pages (New Stuff, Music, Mailchimp, footer, library, PMPro with inline hex only).
- The homepage Blog section in the mockup goes away with the Blog menu item.
