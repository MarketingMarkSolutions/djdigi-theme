/* [djdigi_hero] behavior: opening sequence timing, photo rotation (a TV
   static transition, v1.6.0), header height measurement, and the Mixcloud
   player (now its own section right after the hero, still found and
   driven from here). The header's background image is killed by a late
   wp_head <style> block now (djdigi-theme.php), not from here, and it
   carries no entrance animation to gate on readiness either (v1.4.0, see
   CLAUDE.md). The scroll shrink effect is gone entirely (v1.6.0, Mark's
   call: the hero flows into the page now instead of shrinking into a
   card), so there is no scroll handling left in this file at all. */
(function () {
	'use strict';

	var hero = document.querySelector('.djd-hero');
	if (!hero) return;

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
	 * Rotating hero photos: a TV static transition (hero.css, v1.6.0,
	 * replacing the shutter and the room light both), Ken Burns per photo
	 * (always running on the img itself, independent of the static layer),
	 * pause when the tab is hidden, and only ever warm the one photo
	 * coming up next. The static layer fades in, the active photo swaps
	 * underneath it right at the peak (with a brief jitter), then it fades
	 * back out: see PEAK_MS/TOTAL_MS below, matched to hero.css's own
	 * djd-static-fade timing (0 to 44% to 100% over 500ms).
	 * -------------------------------------------------------------------- */
	(function photoRotation() {
		var wrap = hero.querySelector('.djd-hero__photos');
		if (!wrap) return;
		var frames = Array.prototype.slice.call(wrap.querySelectorAll('.djd-hero__photo-frame'));
		if (!frames.length) return;
		var staticLayer = wrap.querySelector('.djd-hero__static');

		function restartKenBurns(frame) {
			var img = frame.querySelector('.djd-hero__photo');
			if (!img) return;
			img.classList.remove('is-kenburns');
			void img.offsetWidth; // force reflow so the animation restarts
			img.classList.add('is-kenburns');
		}

		var active = frames.filter(function (f) { return f.classList.contains('is-active'); })[0] || frames[0];
		restartKenBurns(active);

		if (frames.length < 2) return;

		var index = frames.indexOf(active);
		var timer = null;
		var SLOT = 6000;
		var PEAK_MS = 220; // matches hero.css's djd-static-fade 44% point (0.35 opacity)
		var TOTAL_MS = 500; // matches hero.css's djd-static-fade full duration
		var JITTER_MS = 120; // just past hero.css's 110ms djd-static-jitter

		function warm(i) {
			var img = frames[i].querySelector('.djd-hero__photo');
			if (!img) return;
			var src = img.getAttribute('srcset') ? img.currentSrc : img.src;
			if (src) { var preload = new Image(); preload.src = src; }
		}

		function advance() {
			var prevFrame = frames[index];
			var next = (index + 1) % frames.length;
			var nextFrame = frames[next];
			if (staticLayer) staticLayer.classList.add('is-static');
			setTimeout(function () {
				prevFrame.classList.remove('is-active');
				nextFrame.classList.add('is-active');
				restartKenBurns(nextFrame);
				nextFrame.classList.add('is-jitter');
				setTimeout(function () {
					nextFrame.classList.remove('is-jitter');
				}, JITTER_MS);
			}, PEAK_MS);
			setTimeout(function () {
				if (staticLayer) staticLayer.classList.remove('is-static');
			}, TOTAL_MS);
			index = next;
			warm((index + 1) % frames.length);
		}

		function start() {
			if (timer) return;
			timer = setInterval(advance, SLOT);
		}
		function stop() {
			clearInterval(timer);
			timer = null;
		}

		warm((index + 1) % frames.length);
		start();
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) stop(); else start();
		});
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
		// The first photo frame in DOM order is always the one marked
		// is-first/is-active on page load (djdigi-theme.php); its own img
		// is what the intro actually waits on.
		var activePhoto = hero.querySelector('.djd-hero__photo-frame.is-first .djd-hero__photo') || hero.querySelector('.djd-hero__photo');
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
					'.djd-in-dj, .djd-letter, .djd-hero__slogan, .djd-eq, .djd-btn, .djd-hero__photo-frame.is-first'
				);
				animated.forEach(function (el) { el.style.willChange = 'auto'; });
			}, introDone);
		});
	})();

	/* -----------------------------------------------------------------------
	 * Mixcloud player. Its own section now, right after the hero, not a
	 * descendant of it any more (v1.6.0, djd-player-section,
	 * djdigi-theme.php djdt_player_section()), so this looks it up from
	 * document, not from hero. The player="no" shortcode attribute can
	 * leave it out of the page entirely, in which case this is a no-op.
	 * -------------------------------------------------------------------- */
	var player = document.querySelector('.djd-player');
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
