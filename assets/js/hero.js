/* The Mixcloud player ("Latest live set", its own section, found here
   whether it was output by [djdigi_hero] or by the standalone
   [djdigi_player], v1.12.0) and the Avada breakout host class fallback.
   The header's background image is killed by a late wp_head <style>
   block (djdigi-theme.php), not from here. The opening sequence, the
   photo rotation and the header docking all moved to mirrorball.js in
   v1.11.0, see CLAUDE.md, "Mirror ball hero": the ball needs its own
   requestAnimationFrame loop regardless of whether this hero shortcode's
   player section exists on a given page, so it owns all of that now,
   leaving this file with only the player and the breakout host fallback.

   v1.12.0: no longer bails out when there is no .djd-hero on the page.
   [djdigi_player] can appear on its own (djdt_player_shortcode(),
   djdigi-theme.php, enqueues this same file), and the player logic below
   has to run for that case too; only the breakout host fallback actually
   cares about .djd-hero specifically, and it already tolerates there
   being none (querySelectorAll on an empty match is a no-op). */
(function () {
	'use strict';

	/* v1.8.2: a plain class fallback for the :has() rule in site.css that
	   zeroes an Avada fusion-fullwidth container's own padding when it
	   holds one of this theme's full bleed sections (see the var(--djd-vw)
	   breakout note on .djd-hero, hero.css). Only matters in a browser
	   without :has() support; the CSS selector already does the same job
	   on its own everywhere else. */
	function djdAddBreakoutHostClass(selector) {
		document.querySelectorAll(selector).forEach(function (el) {
			var node = el.parentElement;
			while (node && node !== document.body) {
				if (node.classList.contains('fusion-fullwidth')) {
					node.classList.add('djd-breakout-host');
					break;
				}
				node = node.parentElement;
			}
		});
	}
	djdAddBreakoutHostClass('.djd-hero, .djd-player-section');

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
	// The feed the hidden widget iframe currently has loaded (v1.14.0): a
	// playlist row can change `feed` before or after the widget exists.
	var iframeFeed = null;

	function fmt(sec) {
		sec = Math.max(0, Math.floor(sec));
		var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
		var mm = (h && m < 10 ? '0' : '') + m, ss = (s < 10 ? '0' : '') + s;
		return (h ? h + ':' : '') + mm + ':' + ss;
	}

	function setPlaying(on) {
		player.classList.toggle('is-playing', on);
		btn.setAttribute('aria-label', (on ? 'Pause ' : 'Play ') + title);
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
		iframeFeed = feed;
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

	// Plays whatever `feed` currently is: a plain play if the widget already
	// has it loaded, otherwise the Widget API's load(feed, true) (v1.14.0,
	// playlist rows). If load() fails, the hidden iframe is rebuilt for the
	// new feed instead, and if that fails too the mix opens on Mixcloud.
	function playCurrent() {
		wantPlay = true;
		player.classList.add('is-loading');
		prepare().then(function (w) {
			if (!wantPlay) return;
			if (iframeFeed === feed) return w.play();
			var target = feed;
			return Promise.resolve(w.load(target, true)).then(function () {
				iframeFeed = target;
			}, function () {
				return rebuild().then(function (w2) { w2.play(); });
			});
		}).then(function () {
			// If the browser blocked playback, clear the spinner so a second tap plays directly.
			setTimeout(function () { player.classList.remove('is-loading'); }, 4000);
		}).catch(function () {
			player.classList.remove('is-loading');
			openOnMixcloud();
		});
	}

	function rebuild() {
		var old = player.querySelector('.djd-player__frame');
		if (old) old.parentNode.removeChild(old);
		widget = null;
		loading = null;
		return prepare();
	}

	btn.addEventListener('click', function () {
		if (widget && iframeFeed === feed) {
			widget.togglePlay();
			return;
		}
		playCurrent();
	});

	/* -----------------------------------------------------------------------
	 * Playlist (v1.14.0): the 3 newest mixes under the player, each row a
	 * button carrying its own feed key, length, title lines, artwork and
	 * Mixcloud URL (djdt_playlist_html()). Choosing one swaps all of that
	 * into this same player, moves the highlight, and starts it playing.
	 * -------------------------------------------------------------------- */
	var rows = Array.prototype.slice.call(document.querySelectorAll('.djd-playlist__row'));
	var artImg = player.querySelector('.djd-player__artimg');
	var titleBox = player.querySelector('.djd-player__title');
	var durEl = player.querySelector('.djd-player__dur');
	var trackLink = player.querySelector('.djd-player__tracklist');

	function showMix(row) {
		var name = row.getAttribute('data-name') || '';
		var main = row.getAttribute('data-main') || name;
		var sub = row.getAttribute('data-sub') || '';
		var pic = row.getAttribute('data-picture') || '';
		feed = row.getAttribute('data-feed');
		knownLength = parseInt(row.getAttribute('data-length'), 10) || 0;
		lastDuration = knownLength;
		title = name || 'this mix';
		btn.setAttribute('data-label', title);
		setPlaying(false);
		player.setAttribute('data-feed', feed);
		player.setAttribute('data-length', knownLength);
		player.style.setProperty('--djd-p', '0%');
		if (posEl) posEl.textContent = fmt(0);
		if (durEl) durEl.textContent = knownLength ? fmt(knownLength) : '';
		if (scrub) {
			scrub.setAttribute('aria-valuemax', knownLength);
			scrub.setAttribute('aria-valuenow', 0);
		}
		if (artImg) {
			if (pic) { artImg.src = pic; artImg.hidden = false; } else { artImg.hidden = true; }
			player.classList.toggle('has-artwork', !!pic);
		}
		if (titleBox) {
			titleBox.setAttribute('title', name);
			titleBox.innerHTML = '';
			var sr = document.createElement('span');
			sr.className = 'djd-sr';
			sr.textContent = name;
			titleBox.appendChild(sr);
			var m = document.createElement('span');
			m.className = 'djd-player__title-main';
			m.setAttribute('aria-hidden', 'true');
			m.textContent = main;
			titleBox.appendChild(m);
			if (sub) {
				var sb = document.createElement('span');
				sb.className = 'djd-player__title-sub';
				sb.setAttribute('aria-hidden', 'true');
				sb.textContent = sub;
				titleBox.appendChild(sb);
			}
		}
		if (trackLink && row.getAttribute('data-url')) trackLink.href = row.getAttribute('data-url');
		rows.forEach(function (r) {
			var on = r === row;
			r.classList.toggle('is-current', on);
			r.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
	}

	rows.forEach(function (row) {
		row.addEventListener('click', function () {
			if (row.getAttribute('data-feed') === feed && widget && iframeFeed === feed) {
				widget.togglePlay();
				return;
			}
			showMix(row);
			playCurrent();
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
