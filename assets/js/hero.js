/* [djdigi_hero] behavior: short intro on repeat visits, and the Mixcloud player. */
(function () {
	'use strict';

	var hero = document.querySelector('.djd-hero');
	if (!hero) return;

	// Full opening sequence once per browser session, a quick version after that.
	try {
		if (sessionStorage.getItem('djdHeroSeen')) {
			hero.classList.add('is-quick');
		} else {
			sessionStorage.setItem('djdHeroSeen', '1');
		}
	} catch (e) { /* storage blocked: always show the full intro */ }

	var player = hero.querySelector('.djd-player');
	if (!player) return;

	var btn = player.querySelector('.djd-player__btn');
	var status = player.querySelector('.djd-player__status');
	var meta = player.querySelector('.djd-player__meta');
	var title = btn.getAttribute('data-label') || 'this mix';
	var feed = player.getAttribute('data-feed');
	var totalText = meta ? meta.textContent : '';

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
	function prepare() {
		if (loading) return loading;
		var iframe = document.createElement('iframe');
		iframe.className = 'djd-player__frame';
		iframe.title = 'Mixcloud player';
		iframe.setAttribute('allow', 'autoplay');
		iframe.setAttribute('tabindex', '-1');
		iframe.setAttribute('aria-hidden', 'true');
		iframe.src = 'https://www.mixcloud.com/widget/iframe/?hide_cover=1&mini=1&hide_artwork=1&autoplay=0&feed=' + encodeURIComponent(feed);
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
					player.style.setProperty('--djd-p', (position / duration * 100).toFixed(2) + '%');
					if (meta) meta.textContent = fmt(position) + ' / ' + fmt(duration);
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

	if (meta && !totalText) meta.textContent = '';
})();
