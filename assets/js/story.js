/* [djdigi_story] (v1.16.0): the Story timeline's behavior. Markup and the
   lane layout come from PHP (includes/djdt-story.php); this draws the
   waveform, keeps one card active (the cyan inset border), moves the
   playhead to that card's chapter, scrolls the track from the arrows, the
   chapter buttons and the keyboard, and lets a mouse drag the track
   (touch scrolls it natively). Opens on the newest residency. */
(function () {
	'use strict';

	var DPR = Math.min(window.devicePixelRatio || 1, 2);

	function hash(a) {
		var x = Math.sin(a * 12.9898 + 78.233) * 43758.5453;
		return x - Math.floor(x);
	}

	function init(root) {
		var n = parseInt(root.getAttribute('data-chapters'), 10) || 0;
		var strip = root.querySelector('.djd-story__strip');
		var stage = root.querySelector('.djd-story__stage');
		var wave = root.querySelector('.djd-story__wave');
		var head = root.querySelector('.djd-story__playhead');
		var track = root.querySelector('.djd-story__track');
		var prev = root.querySelector('.djd-story__arrow.is-prev');
		var next = root.querySelector('.djd-story__arrow.is-next');
		if (!n || !strip || !stage || !wave || !track) return;
		var chapters = Array.prototype.slice.call(root.querySelectorAll('.djd-story__chapter'));
		var bars = Array.prototype.slice.call(root.querySelectorAll('.djd-story__bar'));
		var cards = Array.prototype.slice.call(track.querySelectorAll('.djd-story__card'));
		if (!cards.length) return;
		var ctx = wave.getContext('2d');
		var active = -1, chapter = -1, hover = -1;
		var auto = false, autoTimer = 0;

		function chapterOf(i) { return parseInt(cards[i].getAttribute('data-chapter'), 10) || 0; }

		// The waveform: one block of bars per chapter with a small gap
		// between chapters. Bar heights are fixed per chapter (a seeded
		// hash, so it never changes between visits). Played chapters are
		// paper, the active one cyan, the rest dim.
		function draw() {
			var w = stage.clientWidth, h = wave.clientHeight;
			if (!w || !h) return;
			var pw = Math.round(w * DPR), ph = Math.round(h * DPR);
			if (wave.width !== pw || wave.height !== ph) { wave.width = pw; wave.height = ph; }
			ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
			ctx.clearRect(0, 0, w, h);
			var cw = w / n, gap = 10, step = 4.5, bw = 2.5, mid = h / 2;
			for (var k = 0; k < n; k++) {
				var x0 = k * cw + gap / 2, x1 = (k + 1) * cw - gap / 2;
				var count = Math.max(1, Math.floor((x1 - x0) / step));
				if (k === chapter) ctx.fillStyle = '#24a9e0';
				else if (k === hover) ctx.fillStyle = 'rgba(241,236,226,.8)';
				else if (k < chapter) ctx.fillStyle = 'rgba(241,236,226,.5)';
				else ctx.fillStyle = 'rgba(241,236,226,.2)';
				var lift = 0.55 + 0.45 * hash(k + 1);
				for (var i = 0; i < count; i++) {
					var t = (i + 0.5) / count;
					var env = Math.pow(Math.sin(Math.PI * t), 0.55);
					var a = env * lift * (0.35 + 0.65 * hash(k * 997 + i * 13 + 7)) * (0.7 + 0.3 * Math.sin(i * 0.37 + k * 1.7));
					var bh = Math.max(3, a * (h - 6));
					ctx.fillRect(x0 + i * step, mid - bh / 2, bw, bh);
				}
			}
		}

		function setActive(i) {
			i = Math.max(0, Math.min(cards.length - 1, i));
			if (i === active) return;
			active = i;
			cards.forEach(function (c, j) { c.classList.toggle('is-active', j === i); });
			var k = chapterOf(i);
			if (prev) prev.disabled = i === 0;
			if (next) next.disabled = i === cards.length - 1;
			if (k === chapter) return;
			chapter = k;
			chapters.forEach(function (b, j) {
				b.classList.toggle('is-active', j === k);
				if (j === k) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
			});
			bars.forEach(function (b) { b.classList.toggle('is-active', parseInt(b.getAttribute('data-chapter'), 10) === k); });
			if (head) head.style.left = ((k + 0.5) / n * 100) + '%';
			// Keep the active chapter in view when the strip scrolls.
			if (strip.scrollWidth > strip.clientWidth + 1) {
				var cx = (k + 0.5) / n * stage.offsetWidth;
				var to = Math.max(0, Math.min(strip.scrollWidth - strip.clientWidth, cx - strip.clientWidth / 2));
				if (Math.abs(to - strip.scrollLeft) > 1) strip.scrollTo({ left: to, behavior: 'smooth' });
			}
			draw();
		}

		function maxScroll() { return track.scrollWidth - track.clientWidth; }
		function leftOf(i) { return cards[i].offsetLeft - cards[0].offsetLeft; }

		// Scrolls card i to the track's left edge (or as far as the track
		// goes, for the last few) and makes it active right away; scroll
		// events from this scroll do not change the active card.
		function goTo(i, smooth) {
			i = Math.max(0, Math.min(cards.length - 1, i));
			setActive(i);
			var to = Math.min(maxScroll(), Math.max(0, leftOf(i)));
			auto = true;
			clearTimeout(autoTimer);
			autoTimer = setTimeout(function () { auto = false; }, smooth ? 900 : 80);
			if (Math.abs(to - track.scrollLeft) > 1) track.scrollTo({ left: to, behavior: smooth ? 'smooth' : 'auto' });
		}

		// The active card after a scroll by hand: the card nearest the
		// left edge, or at the very end the current one if it is fully in
		// view (else the last card), since the last cards can never reach
		// the left edge.
		function fromScroll() {
			var x = track.scrollLeft;
			if (x >= maxScroll() - 2) {
				var a = cards[active];
				if (a && leftOf(active) >= x - 2) return;
				setActive(cards.length - 1);
				return;
			}
			var best = 0, bd = Infinity;
			for (var i = 0; i < cards.length; i++) {
				var d = Math.abs(leftOf(i) - x);
				if (d < bd) { bd = d; best = i; }
			}
			setActive(best);
		}
		var queued = false;
		track.addEventListener('scroll', function () {
			if (auto) {
				clearTimeout(autoTimer);
				autoTimer = setTimeout(function () { auto = false; }, 150);
				return;
			}
			if (queued) return;
			queued = true;
			requestAnimationFrame(function () { queued = false; fromScroll(); });
		}, { passive: true });

		if (prev) prev.addEventListener('click', function () { goTo(active - 1, true); });
		if (next) next.addEventListener('click', function () { goTo(active + 1, true); });

		chapters.forEach(function (b, k) {
			b.addEventListener('click', function () {
				for (var i = 0; i < cards.length; i++) {
					if (chapterOf(i) === k && cards[i].classList.contains('is-residency')) { goTo(i, true); return; }
				}
			});
			b.addEventListener('mouseenter', function () { hover = k; draw(); });
			b.addEventListener('mouseleave', function () { hover = -1; draw(); });
		});

		track.addEventListener('keydown', function (e) {
			if (e.target !== track) return;
			if (e.key === 'ArrowRight') { e.preventDefault(); goTo(active + 1, true); }
			else if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(active - 1, true); }
			else if (e.key === 'Home') { e.preventDefault(); goTo(0, true); }
			else if (e.key === 'End') { e.preventDefault(); goTo(cards.length - 1, true); }
		});

		// Mouse drag (touch and pens scroll natively). Snapping is off while
		// dragging; on release the nearest card snaps to the left edge.
		var drag = null;
		track.addEventListener('pointerdown', function (e) {
			if (e.pointerType !== 'mouse' || e.button !== 0) return;
			drag = { x: e.clientX, left: track.scrollLeft, moved: false, id: e.pointerId };
		});
		track.addEventListener('pointermove', function (e) {
			if (!drag || e.pointerId !== drag.id) return;
			var dx = e.clientX - drag.x;
			if (!drag.moved) {
				if (Math.abs(dx) < 5) return;
				drag.moved = true;
				track.classList.add('is-drag');
				try { track.setPointerCapture(e.pointerId); } catch (err) {}
			}
			track.scrollLeft = drag.left - dx;
		});
		function endDrag() {
			if (!drag) return;
			var moved = drag.moved, startLeft = drag.left;
			drag = null;
			if (!moved) return;
			track.classList.remove('is-drag');
			var x = track.scrollLeft;
			if (x >= maxScroll() - 2) { fromScroll(); return; }
			// Nearest card, leaning in the drag's direction.
			var best = 0, bd = Infinity, dir = x > startLeft ? 1 : -1;
			for (var i = 0; i < cards.length; i++) {
				var d = Math.abs(leftOf(i) - x - dir * 40);
				if (d < bd) { bd = d; best = i; }
			}
			goTo(best, true);
		}
		track.addEventListener('pointerup', endDrag);
		track.addEventListener('pointercancel', endDrag);
		track.addEventListener('lostpointercapture', endDrag);
		track.addEventListener('dragstart', function (e) { e.preventDefault(); });

		var start = parseInt(root.getAttribute('data-start'), 10) || 0;
		var first = 0;
		for (var i = 0; i < cards.length; i++) {
			if (chapterOf(i) === start && cards[i].classList.contains('is-residency')) { first = i; break; }
		}
		goTo(first, false);
		draw();

		var lastW = stage.clientWidth;
		window.addEventListener('resize', function () {
			if (stage.clientWidth === lastW) return;
			lastW = stage.clientWidth;
			draw();
			var keep = active;
			active = -1;
			goTo(keep, false);
		});
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.djd-story'), init);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
