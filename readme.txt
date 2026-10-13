=== DJ DigiMark Theme ===
Version: 1.1.0

Site styling for djdigimark.com on top of Avada, plus the [djdigi_hero] homepage hero.

== Safe by default ==
Until you check "Live for everyone" (Settings > DigiMark Theme), the new design and the hero
only show to logged-in administrators. Visitors keep seeing the current site.

== Install ==
1. Upload the djdigi-theme folder to /wp-content/plugins/ (FileZilla), or upload the zip at Plugins > Add New > Upload.
2. Activate "DJ DigiMark Theme".
3. Settings > DigiMark Theme: check the Mixcloud username, hero photos, booking link and ticker items. Leave "Live for everyone" unchecked.

== Test page ==
1. Pages > Add New, title "Home v2", set Visibility to Private.
2. Avada Page Options: Template "100% Width", hide the Page Title Bar, Slider: none.
3. Add one 100% width container (padding 0 on all sides) with a Text Block or Code Block containing:
   [djdigi_hero]
4. View the page while logged in.

== Going live ==
1. Put [djdigi_hero] at the top of the real homepage the same way (replacing the current hero container).
2. Check "Live for everyone" and save.
3. Purge the homepage in Cloudflare.

== Next Up strip ==
The strip above the menu appears automatically once djdigi-library provides the [djdigi_next_up]
shortcode (events module, in progress). Until then the hero shows without it.

== Files ==
assets/css/site.css   Site-wide colors, fonts, Avada header and the pill menu, footer dock
assets/css/hero.css   Hero only (loaded only on pages that use [djdigi_hero])
assets/css/admin.css  Settings page only (the hero photo picker)
assets/js/hero.js     Opening sequence timing, photo rotation, scroll depth, the Mixcloud player
assets/js/menu.js     The sliding pill behind the current menu item, and the Book me fallback
assets/js/dock.js     The footer social dock
assets/js/admin.js    Settings page only (the hero photo picker)
assets/img/           Bundled fallback hero photo and player waveform
assets/fonts/         Archivo and Saira Extra Condensed (SIL Open Font License, see LICENSE.txt)

CSS and JS are versioned by file date, so new uploads bust the browser and Cloudflare cache automatically.

== Changelog ==

= 1.1.0 =
* Header never shows Avada's background image over the hero or the paper background, on any page.
* The real header height is measured at runtime instead of a fixed guess.
* Wider hero content box (1600px) so the name and photo do not leave a gap on very wide screens.
* More space between "DJ" and "DigiMark", and "DigiMark" is mixed case like the logo, not all caps.
* The paper background now reaches the bottom of short pages.
* Display font changed from Big Shoulders Display to Saira Extra Condensed.
* Hero labels, the player status tag and the hero buttons dropped the wide uppercase tracking for a plainer look. Avada's own buttons sitewide are unchanged, that is a later pass.
* The menu is now a rounded pill bar with a sliding highlight behind the current item, carried smoothly across page loads. Dropdowns are rounded panels. A "Book me" button was added after the menu, linking to a new Booking link setting.
* The Mixcloud player gained cover artwork, back/forward 30 second buttons, a full width scrubbable progress bar with a hover tooltip and keyboard control, and links to follow on Mixcloud and see the tracklist.
* New: rotating hero photos. Add any number of photos in Settings, each with its own focal point, and the hero cross fades between them with a slow zoom. One photo means no rotation, no photos falls back to the bundled one.
* The opening sequence was rebuilt: glows bloom, the photo sharpens in, the name rises letter by letter from a mask, then the slogan, buttons, player, menu and ticker follow in sequence. Add ?intro=1 to a hero page URL to force the full sequence while testing.
* The hero now has scroll depth: the photo and the name drift at different speeds as the page scrolls past. Off for touch devices and reduced motion.
* New: a magnetic footer icon dock replacing Avada's plain social icons, built from the same links Avada already has. The empty footer widget columns are hidden when there are no widgets in them.
