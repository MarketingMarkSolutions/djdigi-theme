/* [djdigi_hero] behavior: opening sequence timing, photo rotation, scroll
   shrink, the header fix, and the Mixcloud player. */
(function () {
	'use strict';

	var hero = document.querySelector('.djd-hero');
	if (!hero) return;

	var isTouch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

	/* -----------------------------------------------------------------------
	 * Header over the hero: two separate guarantees neither CSS nor the
	 * normal intro timing can make on their own.
	 *
	 * Avada's own global header background image (an Options panel setting,
	 * set site wide) comes from Avada's own generated CSS, which has beaten
	 * an external stylesheet rule here even with !important across two
	 * earlier rounds, whatever its exact selector or load order turns out
	 * to be. An inline style set with the important priority cannot be
	 * beaten by any stylesheet rule regardless of its own specificity, so
	 * this is the one way to actually guarantee it is gone. Only the image
	 * is forced here; background color and position are left to CSS, which
	 * already handle those correctly (see site.css for the v1.3.0 fix to a
	 * doubled admin bar offset that was pushing the header into the hero).
	 *
	 * Separately, the header's own entrance depends on the intro ready gate
	 * below ever firing. If it never does for any reason (a script error
	 * elsewhere, a promise that never settles), the header must not stay
	 * invisible forever: there would be no way to navigate the site at all.
	 * A timeout well past the normal intro forces it to its final visible
	 * state regardless of whether the animation ever actually played.
	 * -------------------------------------------------------------------- */
	(function fixHeaderOverHero() {
		if (!document.body.classList.contains('djdigi-has-hero')) return;
		var wrapper = document.querySelector('.fusion-header-wrapper');
		var selector = [
			'.fusion-header-wrapper',
			'.fusion-header',
			'.fusion-secondary-header',
			'.fusion-sticky-header-wrapper',
			'.fusion-header-wrapper .fusion-row',
			'.fusion-secondary-header .fusion-row',
			'.fusion-sticky-header-wrapper .fusion-header',
			'.fusion-sticky-header-wrapper .fusion-row'
		].join(', ');
		function killBgImage() {
			document.querySelectorAll(selector).forEach(function (el) {
				el.style.setProperty('background-image', 'none', 'important');
			});
		}
		killBgImage();
		// Avada's sticky header clone, if it is a separate element, can be
		// built slightly after initial render; a couple of follow up passes
		// catch that without an open ended observer.
		window.addEventListener('load', killBgImage);
		setTimeout(killBgImage, 1500);

		setTimeout(function () {
			if (!wrapper) return;
			wrapper.style.setProperty('opacity', '1', 'important');
			wrapper.style.setProperty('transform', 'none', 'important');
			wrapper.style.willChange = 'auto';
		}, 3000);
	})();

	/* -----------------------------------------------------------------------
	 * Full intro once per session, quick after that. ?intro=1 forces the full
	 * sequence for testing, but still marks the session as seen afterward, so
	 * normal navigation right after a forced preview goes back to quick.
	 * -------------------------------------------------------------------- */
	(function introTiming() {
		var forceFull = /(^|[?&])intro=1(&|$)/.test(location.search);
		var seen = false;
		try { seen = !!sessionStorage.getItem('djdHeroSeen'); } catch (e) { /* storage blocked */ }
		if (!forceFull && seen) {
			document.body.classList.add('djd-quick-intro');
		}
		try { sessionStorage.setItem('djdHeroSeen', '1'); } catch (e) { /* ignore */ }
	})();

	/* -----------------------------------------------------------------------
	 * Real header height, since Avada's header is not a fixed size across
	 * browsers or zoom levels. The CSS value is only the fallback for the
	 * instant before this runs. On phones this also has to clear the mobile
	 * nav holder (the "Go to..." selector), not just the wrapper itself: the
	 * wrapper's own box does not always already include it, which is what
	 * let the mobile menu button sit on top of the hero content.
	 * -------------------------------------------------------------------- */
	(function headerHeight() {
		var wrapper = document.querySelector('.fusion-header-wrapper');
		if (!wrapper) return;
		var mobileNav = document.querySelector('.fusion-mobile-nav-holder');
		function measure() {
			var h = wrapper.getBoundingClientRect().bottom;
			if (mobileNav) {
				var mr = mobileNav.getBoundingClientRect();
				if (mr.height > 0) h = Math.max(h, mr.bottom);
			}
			if (h > 0) document.body.style.setProperty('--djd-head-h', h + 'px');
		}
		measure();
		var resizeTimer;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(measure, 150);
		});
		// The header can change height after fonts/images load or the sticky
		// state kicks in; one more pass once everything has settled.
		window.addEventListener('load', measure);
	})();

	/* -----------------------------------------------------------------------
	 * Rotating hero photos: cross-fade, Ken Burns per photo, pause when the
	 * tab is hidden, and only ever warm the one photo coming up next.
	 * -------------------------------------------------------------------- */
	(function photoRotation() {
		var wrap = hero.querySelector('.djd-hero__photos');
		if (!wrap) return;
		var photos = Array.prototype.slice.call(wrap.querySelectorAll('.djd-hero__photo'));
		if (!photos.length) return;

		function restartKenBurns(img) {
			img.classList.remove('is-kenburns');
			void img.offsetWidth; // force reflow so the animation restarts
			img.classList.add('is-kenburns');
		}

		var active = photos.filter(function (p) { return p.classList.contains('is-active'); })[0] || photos[0];
		restartKenBurns(active);

		if (photos.length < 2) return;

		var index = photos.indexOf(active);
		var timer = null;
		var SLOT = 6500;

		function warm(i) {
			var src = photos[i].getAttribute('srcset') ? photos[i].currentSrc : photos[i].src;
			if (src) { var img = new Image(); img.src = src; }
		}

		function advance() {
			var next = (index + 1) % photos.length;
			photos[index].classList.remove('is-active');
			photos[next].classList.add('is-active');
			restartKenBurns(photos[next]);
			index = next;
			warm((index + 1) % photos.length);
		}

		function start() {
			if (timer) return;
			timer = setInterval(advance, SLOT);
		}
		function stop() {
			clearInterval(timer);
			timer = null;
		}

		warm((index + 1) % photos.length);
		start();
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) stop(); else start();
		});
	})();

	/* -----------------------------------------------------------------------
	 * Scroll: the hero shrinks into a rounded card, then normal scrolling
	 * continues below. One progress value (0 to 1) across a zone the
	 * height of the hero itself drives it: transform/opacity only, rAF
	 * throttled, off for touch.
	 * -------------------------------------------------------------------- */
	(function scrollShrink() {
		if (isTouch) return;
		var stage = hero.querySelector('.djd-hero__stage');

		var SCALE_MIN = 0.85;
		var RADIUS_MAX = 28; // px

		var ticking = false;
		function update() {
			ticking = false;
			var zone = hero.offsetHeight || 1;
			var progress = Math.max(0, Math.min(1, -hero.getBoundingClientRect().top / zone));

			hero.style.setProperty('--djd-shrink-scale', (1 - progress * (1 - SCALE_MIN)).toFixed(3));
			hero.style.setProperty('--djd-shrink-radius', (progress * RADIUS_MAX).toFixed(1) + 'px');

			if (stage) {
				hero.style.setProperty('--djd-scroll-name', (-progress * zone * 0.08).toFixed(1) + 'px');
				hero.style.setProperty('--djd-scroll-name-op', Math.max(0.2, 1 - progress * 1.4).toFixed(2));
			}
		}
		window.addEventListener('scroll', function () {
			if (ticking) return;
			ticking = true;
			requestAnimationFrame(update);
		}, { passive: true });
		update();
	})();

	/* -----------------------------------------------------------------------
	 * Intro: waits for the hero photo and the fonts to be ready (capped at
	 * 1.2s so a slow load never blocks it) before letting the entrance
	 * animations start, so nothing plays against a blank photo or a
	 * fallback font. hero.css pauses every entrance animation until body
	 * carries djd-intro-ready; adding it here is what starts each one's
	 * own delay counting. Once the whole entrance is done, will-change
	 * comes back off those elements, since leaving it on forever just
	 * reserves GPU memory nothing is still animating.
	 * -------------------------------------------------------------------- */
	(function introReady() {
		var activePhoto = hero.querySelector('.djd-hero__photo.is-active') || hero.querySelector('.djd-hero__photo');
		var photoReady = (activePhoto && activePhoto.decode) ? activePhoto.decode().catch(function () {}) : Promise.resolve();
		var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready.catch(function () {}) : Promise.resolve();
		var timeout = new Promise(function (resolve) { setTimeout(resolve, 1200); });

		Promise.race([Promise.all([photoReady, fontsReady]), timeout]).then(function () {
			document.body.classList.add('djd-intro-ready');
			var introDone = document.body.classList.contains('djd-quick-intro') ? 900 : 1800;
			setTimeout(function () {
				// Only the purely one-shot elements: the glow, the photo,
				// the echo and each EQ bar all keep an infinite companion
				// animation running forever (drift, Ken Burns, the wave),
				// so will-change stays on those rather than coming off
				// right as they are still actively animating.
				var animated = hero.querySelectorAll(
					'.djd-in-dj, .djd-letter, .djd-hero__slogan, .djd-eq, .djd-btn, .djd-player'
				);
				animated.forEach(function (el) { el.style.willChange = 'auto'; });
				var headerWrapper = document.querySelector('.fusion-header-wrapper');
				if (headerWrapper) headerWrapper.style.willChange = 'auto';
			}, introDone);
		});
	})();

	/* -----------------------------------------------------------------------
	 * Mixcloud player
	 * -------------------------------------------------------------------- */
	var player = hero.querySelector('.djd-player');
	if (!player) return;

	var btn = player.querySelector('.djd-player__btn');
	var status = player.querySelector('.djd-player__status');
	var scrub = player.querySelector('.djd-player__scrub');
	var tooltip = player.querySelector('.djd-player__tooltip');
	var posEl = player.querySelector('.djd-player__pos');
	var title = btn.getAttribute('data-label') || 'this mix';
	var feed = player.getAttribute('data-feed');
	var knownLength = parseInt(player.getAttribute('data-length'), 10) || 0;
	var lastDuration = knownLength;

	var widget = null;
	var loading = null;
	var wantPlay = false;

	function fmt(sec) {
		sec = Math.max(0, Math.floor(sec));
		var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
		var mm = (h && m < 10 ? '0' : '') + m, ss = (s < 10 ? '0' : '') + s;
		return (h ? h + ':' : '') + mm + ':' + ss;
	}

	function setPlaying(on) {
		player.classList.toggle('is-playing', on);
		btn.setAttribute('aria-label', (on ? 'Pause ' : 'Play ') + title);
		status.textContent = on ? 'Now playing' : 'Latest live set';
	}

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			if (window.Mixcloud && window.Mixcloud.PlayerWidget) return resolve();
			var s = document.createElement('script');
			s.src = src;
			s.async = true;
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild(s);
		});
	}

	// Builds the hidden Mixcloud widget once. Started early (on idle, hover or focus)
	// so the click that presses play still counts as the user's gesture.
	// The widget now lives at player-widget.mixcloud.com: the old
	// www.mixcloud.com/widget/iframe/ URL still works but 301s there, and
	// loading it directly is what the Widget API now expects as the
	// iframe's origin for its postMessage handshake.
	function prepare() {
		if (loading) return loading;
		var iframe = document.createElement('iframe');
		iframe.className = 'djd-player__frame';
		iframe.title = 'Mixcloud player';
		iframe.setAttribute('allow', 'autoplay');
		iframe.setAttribute('tabindex', '-1');
		iframe.setAttribute('aria-hidden', 'true');
		iframe.src = 'https://player-widget.mixcloud.com/?hide_cover=1&mini=1&hide_artwork=1&autoplay=0&feed=' + encodeURIComponent(feed);
		player.appendChild(iframe);

		loading = loadScript('https://widget.mixcloud.com/media/js/widgetApi.js').then(function () {
			var w = window.Mixcloud.PlayerWidget(iframe);
			return w.ready.then(function () {
				widget = w;
				w.events.play.on(function () { player.classList.remove('is-loading'); setPlaying(true); });
				w.events.pause.on(function () { setPlaying(false); });
				w.events.ended.on(function () { setPlaying(false); });
				w.events.progress.on(function (position, duration) {
					if (!duration) return;
					lastDuration = duration;
					var pct = (position / duration * 100).toFixed(2);
					player.style.setProperty('--djd-p', pct + '%');
					if (posEl) posEl.textContent = fmt(position);
					if (scrub) scrub.setAttribute('aria-valuenow', Math.round(position));
				});
				return w;
			});
		}).catch(function () {
			// Widget blocked or offline: fall back to opening the mix on Mixcloud.
			loading = null;
			throw new Error('mixcloud');
		});
		return loading;
	}

	function openOnMixcloud() {
		window.open('https://www.mixcloud.com' + feed, '_blank', 'noopener');
	}

	btn.addEventListener('click', function () {
		if (widget) {
			widget.togglePlay();
			return;
		}
		wantPlay = true;
		player.classList.add('is-loading');
		prepare().then(function (w) {
			if (wantPlay) w.play();
			// If the browser blocked playback, clear the spinner so a second tap plays directly.
			setTimeout(function () { player.classList.remove('is-loading'); }, 4000);
		}).catch(function () {
			player.classList.remove('is-loading');
			openOnMixcloud();
		});
	});

	['pointerenter', 'focusin'].forEach(function (ev) {
		player.addEventListener(ev, function () { prepare().catch(function () {}); }, { once: true });
	});
	var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 2500); };
	window.addEventListener('load', function () {
		idle(function () { prepare().catch(function () {}); });
	});

	// Back / forward 30 seconds.
	player.querySelectorAll('.djd-player__skipbtn').forEach(function (skipBtn) {
		skipBtn.addEventListener('click', function () {
			var delta = parseInt(skipBtn.getAttribute('data-skip'), 10) || 0;
			if (widget) {
				widget.getPosition().then(function (pos) {
					widget.seek(Math.max(0, Math.min(lastDuration, pos + delta)));
				});
			} else {
				openOnMixcloud();
			}
		});
	});

	// Scrub bar: click or drag to seek, keyboard arrows, hover tooltip.
	if (scrub) {
		function fractionFromEvent(e) {
			var rect = scrub.getBoundingClientRect();
			var x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
			return Math.max(0, Math.min(1, x / rect.width));
		}
		function showPreview(fraction) {
			var dur = lastDuration || knownLength;
			player.style.setProperty('--djd-p', (fraction * 100).toFixed(2) + '%');
			if (tooltip) {
				tooltip.style.left = (fraction * 100).toFixed(2) + '%';
				tooltip.textContent = fmt(fraction * dur);
			}
		}
		function commitSeek(fraction) {
			var dur = lastDuration || knownLength;
			var target = fraction * dur;
			if (widget) {
				widget.seek(target);
			} else {
				wantPlay = true;
				player.classList.add('is-loading');
				prepare().then(function (w) {
					w.seek(target);
					w.play();
				}).catch(function () {
					player.classList.remove('is-loading');
					openOnMixcloud();
				});
			}
		}

		var dragging = false;
		scrub.addEventListener('pointerdown', function (e) {
			dragging = true;
			showPreview(fractionFromEvent(e));
			e.preventDefault();
		});
		window.addEventListener('pointermove', function (e) {
			if (!dragging) return;
			showPreview(fractionFromEvent(e));
		});
		window.addEventListener('pointerup', function (e) {
			if (!dragging) return;
			dragging = false;
			commitSeek(fractionFromEvent(e));
		});
		scrub.addEventListener('mousemove', function (e) {
			if (dragging) return;
			var dur = lastDuration || knownLength;
			if (tooltip) {
				var fraction = fractionFromEvent(e);
				tooltip.style.left = (fraction * 100).toFixed(2) + '%';
				tooltip.textContent = fmt(fraction * dur);
			}
		});
		scrub.addEventListener('keydown', function (e) {
			var dur = lastDuration || knownLength;
			if (!dur) return;
			if ('ArrowLeft' === e.key || 'ArrowRight' === e.key) {
				e.preventDefault();
				var step = 10 * ('ArrowLeft' === e.key ? -1 : 1);
				if (widget) {
					widget.getPosition().then(function (pos) {
						widget.seek(Math.max(0, Math.min(dur, pos + step)));
					});
				}
			}
		});
	}
})();
