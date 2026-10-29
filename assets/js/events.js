/* [djdigi_events]: the CD case cards. Ported straight from
   reference/events-cd-reference.html (the file Mark approved live on Home
   v2), same behavior, same timings, classes renamed to this theme's is-
   prefix (open/closing/hint -> is-open/is-closing/is-hint). All of the
   actual motion is CSS (events.css); this only toggles state: which card
   is open (one at a time), focus, Escape, the Share pill, and the one
   time peek when the section first scrolls into view.

   v1.14.3: an open CD also goes back from its "put back" button
   (.djd-cd__putback, on the disc's rim) and from a click anywhere outside
   it, besides the case click and Escape. Links and pills on the disc still
   never close it. */
(function () {
	'use strict';

	document.querySelectorAll( '.djd-events__track' ).forEach( function ( grid ) {
		var cards = [].slice.call( grid.querySelectorAll( '.djd-cd' ) );

		// keepFocus: closed by a click elsewhere on the page, so focus stays
		// on whatever was clicked instead of jumping back to the case.
		function close( el, keepFocus ) {
			if ( ! el.classList.contains( 'is-open' ) ) {
				return;
			}
			el.classList.remove( 'is-open' );
			el.classList.add( 'is-closing' );
			var play = el.querySelector( '.djd-cd__play' );
			play.setAttribute( 'aria-expanded', 'false' );
			setTimeout( function () {
				el.classList.remove( 'is-closing' );
			}, 850 );
			if ( ! keepFocus ) {
				play.focus( { preventScroll: true } );
			}
		}

		function open( el ) {
			cards.forEach( function ( other ) {
				if ( other !== el ) {
					close( other );
				}
			} );
			el.classList.add( 'is-open' );
			el.querySelector( '.djd-cd__play' ).setAttribute( 'aria-expanded', 'true' );
			setTimeout( function () {
				var first = el.querySelector( '.djd-cd__disc-address' );
				if ( first ) {
					first.focus( { preventScroll: true } );
				}
			}, 900 );
		}

		cards.forEach( function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				if ( e.target.closest && e.target.closest( '.djd-cd__putback' ) ) {
					close( el );
					return;
				}
				var share = e.target.closest ? e.target.closest( '.djd-cd__disc-share' ) : null;
				if ( share ) {
					e.preventDefault();
					e.stopPropagation();
					var title = share.getAttribute( 'data-share-title' );
					var url = share.getAttribute( 'data-share-url' );
					if ( navigator.share ) {
						navigator.share( { title: title, url: url } ).catch( function () {} );
					} else if ( navigator.clipboard ) {
						navigator.clipboard.writeText( title + ' ' + url );
						share.querySelector( '.djd-cd__disc-pill-label' ).textContent = 'Copied';
					}
					return;
				}
				if ( e.target.closest && e.target.closest( 'a' ) ) {
					return; // links on the disc work normally.
				}
				if ( el.classList.contains( 'is-open' ) ) {
					close( el );
				} else {
					open( el );
				}
			} );
		} );

		// A click outside the open CD puts it back. A click on another case
		// has already opened that one by the time this runs (it bubbles up
		// through that case first), so the open card then contains the
		// target and nothing happens here.
		document.addEventListener( 'click', function ( e ) {
			var openCard = grid.querySelector( '.djd-cd.is-open' );
			if ( openCard && ! openCard.contains( e.target ) ) {
				close( openCard, true );
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' !== e.key ) {
				return;
			}
			var openCard = grid.querySelector( '.djd-cd.is-open' );
			if ( openCard ) {
				close( openCard );
			}
		} );

		// One time peek, staggered 150ms per case, when the grid first
		// scrolls into view.
		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}
					cards.forEach( function ( card, i ) {
						setTimeout( function () {
							card.classList.add( 'is-hint' );
							setTimeout( function () {
								card.classList.remove( 'is-hint' );
							}, 1000 );
						}, 300 + i * 150 );
					} );
					io.disconnect();
				} );
			}, { threshold: 0.3 } );
			io.observe( grid );
		}
	} );

	// v1.15.1: the featured residency cards' Share pill, same behavior as
	// the CD's.
	document.querySelectorAll( '.djd-res__share' ).forEach( function ( share ) {
		share.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var title = share.getAttribute( 'data-share-title' );
			var url = share.getAttribute( 'data-share-url' );
			if ( navigator.share ) {
				navigator.share( { title: title, url: url } ).catch( function () {} );
			} else if ( navigator.clipboard ) {
				navigator.clipboard.writeText( title + ' ' + url );
				share.querySelector( '.djd-cd__disc-pill-label' ).textContent = 'Copied';
			}
		} );
	} );
})();
