=== DJ DigiMark Theme ===
Version: 1.0.1

Site styling for djdigimark.com on top of Avada, plus the [djdigi_hero] homepage hero.

== Safe by default ==
Until you check "Live for everyone" (Settings > DigiMark Theme), the new design and the hero
only show to logged-in administrators. Visitors keep seeing the current site.

== Install ==
1. Upload the djdigi-theme folder to /wp-content/plugins/ (FileZilla), or upload the zip at Plugins > Add New > Upload.
2. Activate "DJ DigiMark Theme".
3. Settings > DigiMark Theme: check the Mixcloud username and ticker items. Leave "Live for everyone" unchecked.

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
assets/css/site.css   Site-wide colors, fonts, Avada header and menu, buttons
assets/css/hero.css   Hero only (loaded only on pages that use [djdigi_hero])
assets/js/hero.js     Intro timing and the Mixcloud player
assets/img/           Hero photo and player waveform
assets/fonts/         Archivo and Big Shoulders Display (SIL Open Font License, see LICENSE.txt)

CSS and JS are versioned by file date, so new uploads bust the browser and Cloudflare cache automatically.
