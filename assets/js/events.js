/* [djdigi_events]: the eject button slides a CD tray out from under its
   card. All of the actual motion is CSS (grid-template-rows on the tray,
   a translateY/opacity on its inner wrapper, a rotate on the disc, see
   events.css); this only toggles state: hidden -> visible before the
   transition starts, hidden -> true again only after it has finished
   closing, one tray open at a time, Escape closes, focus moves into the
   tray on open and back to its own button on close. v1.8.0: ported from
   djdigi-library v0.2.0 unchanged apart from the djl- to djd-events__card
   class rename, see includes/djdt-events.php. */
(function () {
	'use strict';

	var CLOSE_FALLBACK_MS = 500; // a little past the 450ms CSS transition

	var openTray = null; // the currently open .djd-events__card-tray, or null
	var openBtn = null;

	function trayFor( btn ) {
		var id = btn.getAttribute( 'aria-controls' );
		return id ? document.getElementById( id ) : null;
	}

	function openCard( btn, tray ) {
		if ( openTray && openTray !== tray ) {
			closeCard( openBtn, openTray, false );
		}
		btn.setAttribute( 'aria-expanded', 'true' );
		tray.hidden = false;
		// Force a reflow so the browser registers the hidden -> visible
		// change before adding the class that starts the 0fr -> 1fr
		// transition; without this the two can collapse into one paint
		// and the transition never plays.
		void tray.offsetHeight;
		tray.classList.add( 'is-open' );
		openTray = tray;
		openBtn = btn;

		var focusTarget = tray.querySelector( 'a, button' ) || tray;
		if ( focusTarget !== tray ) {
			focusTarget.focus();
		} else {
			tray.setAttribute( 'tabindex', '-1' );
			tray.focus();
		}
	}

	function closeCard( btn, tray, restoreFocus ) {
		btn.setAttribute( 'aria-expanded', 'false' );
		tray.classList.remove( 'is-open' );

		var done = false;
		function finish() {
			if ( done ) return;
			done = true;
			tray.hidden = true;
			tray.removeEventListener( 'transitionend', onEnd );
		}
		function onEnd( e ) {
			if ( e.target === tray && 'grid-template-rows' === e.propertyName ) {
				finish();
			}
		}
		tray.addEventListener( 'transitionend', onEnd );
		setTimeout( finish, CLOSE_FALLBACK_MS );

		if ( openTray === tray ) {
			openTray = null;
			openBtn = null;
		}
		if ( restoreFocus ) {
			btn.focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.djd-events__card-eject' ) : null;
		if ( ! btn ) return;
		var tray = trayFor( btn );
		if ( ! tray ) return;

		var isOpen = 'true' === btn.getAttribute( 'aria-expanded' );
		if ( isOpen ) {
			closeCard( btn, tray, false );
		} else {
			openCard( btn, tray );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key || ! openTray ) return;
		closeCard( openBtn, openTray, true );
	} );
})();
