/* [djdigi_events]: the CD case cards (v1.9.0). All of the actual motion is
   CSS (the disc's own open/close keyframes, the hover lift, the play
   button glow, see events.css); this only toggles state: which card is
   open (one at a time), the one-shot peek when the section first scrolls
   into view, focus management, Escape to close, and the Share pill
   (Add to calendar and Directions are plain links, no JS needed for them).

   LABEL_MS below (when the label fades in after opening) is not read from
   events.css; it must stay less than that file's own djd-cd-open duration
   if the two are ever changed together. */
(function () {
	'use strict';

	var LABEL_MS = 850;
	var COPIED_MS = 2000;

	var openCard = null;
	var labelTimer = null;

	function discFor( card ) {
		return card.querySelector( '.djd-cd__disc' );
	}

	function playButtonFor( card ) {
		return card.querySelector( '.djd-cd__play' );
	}

	function firstFocusable( card ) {
		return card.querySelector( '.djd-cd__disc-label a, .djd-cd__disc-label button' );
	}

	function openTheCard( card ) {
		if ( openCard && openCard !== card ) {
			closeTheCard( openCard, false );
		}
		if ( labelTimer ) {
			clearTimeout( labelTimer );
			labelTimer = null;
		}

		var disc = discFor( card );
		var btn = playButtonFor( card );

		card.classList.add( 'is-open' );
		disc.classList.remove( 'is-closing' );
		disc.classList.add( 'is-opening' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', 'true' );
		}

		disc.addEventListener( 'animationend', function onEnd() {
			disc.removeEventListener( 'animationend', onEnd );
			disc.classList.remove( 'is-opening' );
		} );

		labelTimer = setTimeout( function () {
			card.classList.add( 'is-label-visible' );
			var target = firstFocusable( card );
			if ( target ) {
				target.focus();
			}
			labelTimer = null;
		}, LABEL_MS );

		openCard = card;
	}

	function closeTheCard( card, restoreFocus ) {
		var disc = discFor( card );
		var btn = playButtonFor( card );

		if ( labelTimer ) {
			clearTimeout( labelTimer );
			labelTimer = null;
		}

		card.classList.remove( 'is-open', 'is-label-visible' );
		disc.classList.remove( 'is-opening' );
		disc.classList.add( 'is-closing' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', 'false' );
		}

		disc.addEventListener( 'animationend', function onEnd() {
			disc.removeEventListener( 'animationend', onEnd );
			disc.classList.remove( 'is-closing' );
		} );

		if ( openCard === card ) {
			openCard = null;
		}
		if ( restoreFocus && btn ) {
			btn.focus();
		}
	}

	function handleShare( btn ) {
		var title = btn.getAttribute( 'data-share-title' ) || '';
		var url = btn.getAttribute( 'data-share-url' ) || '';

		if ( navigator.share ) {
			navigator.share( { title: title, url: url } ).catch( function () {} );
			return;
		}

		var text = title + ' ' + url;
		var done = function () { showCopied( btn ); };

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( done ).catch( function () {
				copyWithFallback( text );
				done();
			} );
		} else {
			copyWithFallback( text );
			done();
		}
	}

	function copyWithFallback( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.left = '-9999px';
		document.body.appendChild( area );
		area.select();
		try {
			document.execCommand( 'copy' );
		} catch ( e ) {}
		document.body.removeChild( area );
	}

	function showCopied( btn ) {
		var label = btn.querySelector( '.djd-cd__disc-pill-label' );
		if ( ! label ) {
			return;
		}
		var original = label.textContent;
		label.textContent = 'Copied';
		setTimeout( function () {
			label.textContent = original;
		}, COPIED_MS );
	}

	document.addEventListener( 'click', function ( e ) {
		var shareBtn = e.target.closest ? e.target.closest( '.djd-cd__disc-share' ) : null;
		if ( shareBtn ) {
			handleShare( shareBtn );
			return;
		}
		if ( e.target.closest && e.target.closest( 'a' ) ) {
			return; // let the calendar/directions/address links behave normally.
		}
		var card = e.target.closest ? e.target.closest( '.djd-cd' ) : null;
		if ( ! card ) {
			return;
		}
		if ( card.classList.contains( 'is-open' ) ) {
			closeTheCard( card, false );
		} else {
			openTheCard( card );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key || ! openCard ) {
			return;
		}
		closeTheCard( openCard, true );
	} );

	/* One quick peek per disc the first time the section scrolls into
	   view, staggered 150ms per card, never repeated. */
	if ( 'IntersectionObserver' in window ) {
		document.querySelectorAll( '.djd-events' ).forEach( function ( section ) {
			var peeked = false;
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting || peeked ) {
						return;
					}
					peeked = true;
					var cards = section.querySelectorAll( '.djd-cd' );
					cards.forEach( function ( card, i ) {
						setTimeout( function () {
							var disc = discFor( card );
							disc.classList.add( 'is-firstpeek' );
							disc.addEventListener( 'animationend', function onEnd() {
								disc.removeEventListener( 'animationend', onEnd );
								disc.classList.remove( 'is-firstpeek' );
							} );
						}, i * 150 );
					} );
					io.disconnect();
				} );
			}, { threshold: 0.2 } );
			io.observe( section );
		} );
	}
})();
