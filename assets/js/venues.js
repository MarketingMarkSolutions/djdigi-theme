/* [djdigi_venues] behavior: works out two timings once from the rendered
   page, each as a CSS custom property that the actual animation (pure CSS
   from there on) reads, recalculated on resize, never set on every frame. */
(function () {
	'use strict';

	var section = document.querySelector('.djd-venues');
	if (!section) return;

	/* v1.8.2: :has() fallback class, same as hero.js, see its own comment. */
	(function () {
		var node = section.parentElement;
		while (node && node !== document.body) {
			if (node.classList.contains('fusion-fullwidth')) {
				node.classList.add('djd-breakout-host');
				break;
			}
			node = node.parentElement;
		}
	})();

	var track = section.querySelector('.djd-venues__track');

	var resizeTimer;
	function onResize(fn) {
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(fn, 150);
		});
	}

	/* Logo row: steady px per second from the track's own rendered width,
	   same technique as the hero ticker used to use for its speed. */
	(function trackTiming() {
		if (!track) return;
		var PX_PER_SECOND = 40;
		function measure() {
			var setWidth = track.scrollWidth / 2;
			if (!setWidth) return;
			var duration = Math.max(10, setWidth / PX_PER_SECOND);
			track.style.setProperty('--djd-venues-dur', duration.toFixed(2) + 's');
		}
		measure();
		window.addEventListener('load', measure);
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(measure).catch(function () {});
		}
		onResize(measure);
	})();
})();
