/* Footer: swaps Avada's rendered social icons for a magnetic dock built from
   the same hrefs, and hides the footer widget area when it has no widgets.
   The header keeps its own plain Avada icons untouched (Mark's call, v1.2.2:
   only the footer gets a dock), hidden entirely if empty on hero pages.
   Avada keeps owning the actual social link settings; this only reads them. */
(function () {
	'use strict';

	var cfg = window.djdtDock || {};
	var isTouch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

	var ICONS = {
		instagram: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465a4.902 4.902 0 0 1 1.772 1.153 4.902 4.902 0 0 1 1.153 1.772c.248.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.217 1.79-.465 2.428a4.902 4.902 0 0 1-1.153 1.772 4.902 4.902 0 0 1-1.772 1.153c-.637.248-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06-2.717 0-3.056-.01-4.122-.06-1.065-.05-1.79-.217-2.428-.465a4.902 4.902 0 0 1-1.772-1.153 4.902 4.902 0 0 1-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.065.217-1.79.465-2.428a4.902 4.902 0 0 1 1.153-1.772A4.902 4.902 0 0 1 5.45 2.525c.637-.248 1.363-.415 2.428-.465C8.944 2.013 9.283 2 12 2Zm0 1.802c-2.67 0-2.986.01-4.04.059-.976.045-1.505.207-1.858.344-.467.182-.8.4-1.15.748a3.098 3.098 0 0 0-.748 1.15c-.137.353-.3.882-.344 1.858-.05 1.054-.059 1.37-.059 4.04 0 2.67.01 2.986.059 4.04.045.976.207 1.505.344 1.858.182.467.4.8.748 1.15.35.347.683.566 1.15.748.353.137.882.3 1.858.344 1.053.05 1.37.059 4.04.059 2.67 0 2.987-.01 4.04-.059.976-.045 1.505-.207 1.858-.344.467-.182.8-.4 1.15-.748.347-.35.566-.683.748-1.15.137-.353.3-.882.344-1.858.05-1.054.059-1.37.059-4.04 0-2.67-.01-2.986-.059-4.04-.045-.976-.207-1.505-.344-1.858a3.098 3.098 0 0 0-.748-1.15 3.098 3.098 0 0 0-1.15-.748c-.353-.137-.882-.3-1.858-.344-1.053-.05-1.37-.059-4.04-.059Zm0 3.06a5.138 5.138 0 1 1 0 10.276A5.138 5.138 0 0 1 12 6.862Zm0 1.802a3.336 3.336 0 1 0 0 6.672 3.336 3.336 0 0 0 0-6.672Zm5.338-3.561a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z"/></svg>',
		facebook: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.5h2.5l.5-3h-3V8.5c0-.9.25-1.5 1.6-1.5H16.5V4.2c-.3-.05-1.3-.2-2.5-.2-2.5 0-4.2 1.5-4.2 4.3V10.5H7v3h2.8V21h3.7Z"/></svg>',
		bluesky: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 10.8C10.86 8.2 8.2 5.1 5.7 3.7 4.3 2.9 3 3.4 3 5.1c0 .5.3 4.4.5 5.1.6 2.3 2.9 2.9 5 2.6-3.6.5-6.8 2-2.6 4.5 4.6 2.7 6.3-1 6.1-3 -.2 2 1.5 5.7 6.1 3 4.2-2.5 1-4-2.6-4.5 2.1.3 4.4-.3 5-2.6.2-.7.5-4.6.5-5.1 0-1.7-1.3-2.2-2.7-1.4-2.5 1.4-5.16 4.5-6.3 7.1Z"/></svg>',
		tiktok: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 1 1-1.83-2.48V9.8a5.65 5.65 0 0 0-1.4-.18A5.61 5.61 0 1 0 14.9 15.4a5.6 5.6 0 0 0 .21-1.53v-6.2a7.17 7.17 0 0 0 4.2 1.35V5.95a4.2 4.2 0 0 1-2.71-.13Z"/></svg>',
		snapchat: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.4c3.2 0 5 2.3 5.1 4.9l.05 1.9c.02.4.1.5.4.6.3.1 1 .3 1.3.4.3.1.5.5.2.9-.2.3-.6.5-.9.7.2.5.1 1-.2 1.3-.2.2-.2.3-.1.6.1.4.3.8.1 1.1-.2.3-.7.4-1.1.5-.3.1-.4.2-.5.5-.1.4-.3.9-1 1-.5.1-1.1-.1-1.7-.1-.7 0-1.2.6-2.6 1.6-.3.2-.7.4-1 .4s-.7-.2-1-.4c-1.4-1-1.9-1.6-2.6-1.6-.6 0-1.2.2-1.7.1-.7-.1-.9-.6-1-1-.1-.3-.2-.4-.5-.5-.4-.1-.9-.2-1.1-.5-.2-.3 0-.7.1-1.1.1-.3.1-.4-.1-.6-.3-.3-.4-.8-.2-1.3-.3-.2-.7-.4-.9-.7-.3-.4-.1-.8.2-.9.3-.1 1-.3 1.3-.4.3-.1.38-.2.4-.6l.05-1.9C7 4.7 8.8 2.4 12 2.4Z"/></svg>',
		// The official Mixcloud mark (Simple Icons, CC0), not a generic cloud:
		// the old path here (a cloud outline with four sound bars under it)
		// read as SoundCloud to anyone who actually knows either logo, found
		// live (v1.11.0). Sourced directly from simple-icons/simple-icons,
		// icons/mixcloud.svg, fill="currentColor" like every other icon in
		// this file rather than the stroke-based shape it replaces.
		mixcloud: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.462 8.596l1.372 6.49h.319l1.372-6.49h2.462v6.808H6.742v-5.68l.232-.81h-.402l-1.43 6.49H2.854l-1.44-6.49h-.391l.222.81v5.68H0V8.596zM24 8.63v1.429L21.257 12 24 13.941v1.43l-3.235-2.329h-.348l-3.226 2.329v-1.43l2.734-1.94-2.733-1.942V8.63l3.225 2.338h.348zm-7.869 2.75v1.24H9.304v-1.24z"/></svg>'
	};

	function labelFor(key) {
		return key.charAt(0).toUpperCase() + key.slice(1);
	}

	function detectNetwork(a) {
		var hay = (a.className + ' ' + a.innerHTML + ' ' + (a.getAttribute('data-title') || '')).toLowerCase();
		var child = a.querySelector('[class], [data-title]');
		if (child) hay += ' ' + (child.className || '').toLowerCase() + ' ' + (child.getAttribute('data-title') || '').toLowerCase();
		if (hay.indexOf('instagram') > -1) return 'instagram';
		if (hay.indexOf('facebook') > -1) return 'facebook';
		if (hay.indexOf('tiktok') > -1) return 'tiktok';
		if (hay.indexOf('snapchat') > -1) return 'snapchat';
		if (hay.indexOf('bluesky') > -1) return 'bluesky';
		return null;
	}

	(function buildDock() {
		// Scoped strictly to the footer's own copy of this markup: Avada
		// reuses the exact same .fusion-social-networks-wrapper class for the
		// header's small icon bar too, and an unscoped query here previously
		// grabbed that one instead, since it comes first in the document.
		var wrapper = document.querySelector('footer#footer.fusion-footer-copyright-area .fusion-social-links-footer .fusion-social-networks-wrapper');
		if (!wrapper) return;

		var found = {};
		Array.prototype.forEach.call(wrapper.querySelectorAll('a[href]'), function (a) {
			var network = detectNetwork(a);
			if (network && !found[network]) found[network] = a.href;
		});
		if (cfg.mixcloudUrl) found.mixcloud = cfg.mixcloudUrl;

		var order = ['instagram', 'tiktok', 'facebook', 'bluesky', 'snapchat', 'mixcloud'];
		var dock = document.createElement('div');
		dock.className = 'djd-dock';
		if (isTouch) dock.classList.add('is-static');

		order.forEach(function (key) {
			if (!found[key]) return;
			var a = document.createElement('a');
			a.className = 'djd-dock__btn';
			a.href = found[key];
			a.target = '_blank';
			a.rel = 'noopener';
			a.setAttribute('aria-label', labelFor(key));
			a.innerHTML = ICONS[key];
			dock.appendChild(a);
		});

		if (!dock.children.length) return;
		wrapper.style.display = 'none';
		wrapper.insertAdjacentElement('afterend', dock);

		if (dock.classList.contains('is-static')) return;

		var buttons = Array.prototype.slice.call(dock.children);
		var FALLOFF = 120;
		var NEAR = 80;
		// v1.11.0, found live: 0.46 let the nearest button grow to scale
		// 1.46, big enough that it visibly overlapped its neighbors (the
		// row has no extra spacing budget for that). Capped to 1.08, which
		// never does, matching the actual gap between buttons (site.css).
		var MAX_GROW = 0.08;
		document.addEventListener('pointermove', function (e) {
			var nearest = null;
			var nearestDist = Infinity;
			buttons.forEach(function (btn) {
				var r = btn.getBoundingClientRect();
				var cx = r.left + r.width / 2;
				var dist = Math.abs(e.clientX - cx);
				var scale = 1 + MAX_GROW * Math.max(0, 1 - dist / FALLOFF);
				btn.style.setProperty('--djd-dock-scale', scale.toFixed(3));
				if (dist < nearestDist) { nearestDist = dist; nearest = btn; }
			});
			buttons.forEach(function (btn) {
				btn.classList.toggle('is-near', btn === nearest && nearestDist < NEAR);
			});
		});
	})();

	// "Book me" button and "Join the newsletter" link beside the dock
	// (v1.14.0). Both URLs are Theme Settings fields; an empty one hides
	// its item, and with both empty nothing is added at all. Placed right
	// after the dock, or where the dock would be if it was not built.
	(function buildFooterLinks() {
		if (!cfg.bookUrl && !cfg.newsletterUrl) return;
		var anchor = document.querySelector('footer#footer.fusion-footer-copyright-area .djd-dock') ||
			document.querySelector('footer#footer.fusion-footer-copyright-area .fusion-social-links-footer');
		if (!anchor) return;
		var box = document.createElement('div');
		box.className = 'djd-footer-links';
		if (cfg.bookUrl) {
			// The mini record button (v1.14.2), same markup as the hero's
			// djdt_record_button_html() in djdigi-theme.php.
			var book = document.createElement('a');
			book.className = 'djd-rec-btn djd-footer-links__book';
			book.href = cfg.bookUrl;
			book.innerHTML = '<span class="djd-rec" aria-hidden="true"><span class="djd-rec__disc"></span><span class="djd-rec__sheen"></span></span><span class="djd-rec-btn__text"></span>';
			book.lastChild.textContent = 'Contact / Book me';
			box.appendChild(book);
		}
		if (cfg.newsletterUrl) {
			var news = document.createElement('a');
			news.className = 'djd-footer-links__news';
			news.href = cfg.newsletterUrl;
			news.textContent = 'Join the newsletter';
			box.appendChild(news);
		}
		anchor.insertAdjacentElement('afterend', box);
	})();

	// Mini record buttons (v1.14.2, hero and footer): the disc's CSS spin
	// (one turn per 4s) ramps to 4x over 300ms on hover/focus and back to 1x
	// over 400ms when both end. Ramped through the running animation's own
	// playbackRate, which keeps its current angle, so the speed changes and
	// the record never jumps (swapping animation-duration would). Browsers
	// without getAnimations() keep the steady spin.
	(function recordButtons() {
		var FAST = 4, UP = 300, DOWN = 400;
		document.querySelectorAll('.djd-rec-btn').forEach(function (btn) {
			var disc = btn.querySelector('.djd-rec__disc');
			if (!disc || !disc.getAnimations) return;
			var hover = false, focus = false, raf = 0;
			function ramp() {
				var anim = disc.getAnimations()[0];
				if (!anim) return;
				var to = hover || focus ? FAST : 1;
				var from = anim.playbackRate;
				if (from === to) return;
				var ms = to > from ? UP : DOWN, t0 = performance.now();
				cancelAnimationFrame(raf);
				(function step(now) {
					var k = Math.min(1, Math.max(0, (now - t0) / ms));
					k = k * k * (3 - 2 * k);
					anim.playbackRate = from + (to - from) * k;
					if (k < 1) raf = requestAnimationFrame(step);
				})(t0);
			}
			btn.addEventListener('mouseenter', function () { hover = true; ramp(); });
			btn.addEventListener('mouseleave', function () { hover = false; ramp(); });
			btn.addEventListener('focus', function () { focus = true; ramp(); });
			btn.addEventListener('blur', function () { focus = false; ramp(); });
		});
	})();

	(function hideEmptyWidgetArea() {
		var area = document.querySelector('.fusion-footer-widget-area');
		if (!area) return;
		var hasContent = area.textContent.trim() !== '' || area.querySelector('img, a, svg, iframe, form, input, textarea');
		if (!hasContent) area.classList.add('djdt-is-empty');
	})();

	// The header's social icons are hidden outright now (site.css, v1.2.3:
	// social links live in the footer only). Checked against Avada's own
	// secondary menu, the only other thing that bar can hold, not the
	// icons, since those are always hidden regardless of whether any are
	// configured.
	(function hideEmptySecondaryHeader() {
		var bar = document.querySelector('.fusion-secondary-header');
		if (!bar) return;
		var hasMenu = bar.querySelector('.fusion-secondary-menu li');
		if (!hasMenu) bar.classList.add('djdt-is-empty');
	})();
})();
