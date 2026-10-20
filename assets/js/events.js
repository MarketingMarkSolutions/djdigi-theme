/* [djdigi_events]: the eject button opens a CD drawer beside its card (v1.8.2,
   replacing the v1.8.0/v1.8.1 under card tray). All of the actual motion is
   CSS (the disc's own slide and spin, the panel's own clip-path unfold, see
   events.css); this only toggles state: hidden -> visible before the
   transition starts, hidden -> true again only after the close animation has
   had time to finish, one drawer open at a time, Escape closes, focus moves
   into the drawer on open and back to its own button on close. */
(function () {
	'use strict';

	/* v1.8.2: a plain class fallback for the :has() rule in site.css that
	   zeroes an Avada fusion-fullwidth container's own padding when it
	   holds this section (see the var(--djd-vw) breakout note on .djd-hero,
	   hero.css). Only matters in a browser without :has() support. */
	document.querySelectorAll('.djd-events').forEach(function (section) {
		var node = section.parentElement;
		while (node && node !== document.body) {
			if (node.classList.contains('fusion-fullwidth')) {
				node.classList.add('djd-breakout-host');
				break;
			}
			node = node.parentElement;
		}
	});

	// A little past the close transition's own longest piece (the disc's
	// 600ms spin back in, events.css): long enough the close animation is
	// never visibly cut off, short enough the drawer does not sit in the
	// accessibility tree any longer than it has to once actually closed.
	var CLOSE_FALLBACK_MS = 650;

	var openDrawer = null; // the currently open .djd-events__card-drawer, or null
	var openBtn = null;

	function drawerFor( btn ) {
		var id = btn.getAttribute( 'aria-controls' );
		return id ? document.getElementById( id ) : null;
	}

	function openCard( btn, drawer ) {
		if ( openDrawer && openDrawer !== drawer ) {
			closeCard( openBtn, openDrawer, false );
		}
		btn.setAttribute( 'aria-expanded', 'true' );
		drawer.hidden = false;
		// Force a reflow so the browser registers the hidden -> visible
		// change before adding the class that starts the slide/spin/unfold
		// transitions; without this the two can collapse into one paint and
		// the transition never plays.
		void drawer.offsetHeight;
		drawer.classList.add( 'is-open' );
		openDrawer = drawer;
		openBtn = btn;

		var focusTarget = drawer.querySelector( 'a, button' ) || drawer;
		if ( focusTarget !== drawer ) {
			focusTarget.focus();
		} else {
			drawer.setAttribute( 'tabindex', '-1' );
			drawer.focus();
		}
	}

	function closeCard( btn, drawer, restoreFocus ) {
		btn.setAttribute( 'aria-expanded', 'false' );
		drawer.classList.remove( 'is-open' );

		setTimeout( function () {
			drawer.hidden = true;
		}, CLOSE_FALLBACK_MS );

		if ( openDrawer === drawer ) {
			openDrawer = null;
			openBtn = null;
		}
		if ( restoreFocus ) {
			btn.focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.djd-events__card-eject' ) : null;
		if ( ! btn ) return;
		var drawer = drawerFor( btn );
		if ( ! drawer ) return;

		var isOpen = 'true' === btn.getAttribute( 'aria-expanded' );
		if ( isOpen ) {
			closeCard( btn, drawer, false );
		} else {
			openCard( btn, drawer );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key || ! openDrawer ) return;
		closeCard( openBtn, openDrawer, true );
	} );
})();
