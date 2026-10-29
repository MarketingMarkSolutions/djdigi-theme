/* Avada menu restyle: the sliding pill behind the current item (the only
   highlight in the menu), and keeping dropdown panels inside the viewport. */
(function () {
	'use strict';

	/* -----------------------------------------------------------------------
	 * Sliding pill
	 * -------------------------------------------------------------------- */
	var bar = document.querySelector('.fusion-main-menu');
	var list = bar ? bar.querySelector(':scope > ul.fusion-menu') : null;

	if (bar && list) {
		var pill = document.createElement('div');
		pill.className = 'djd-menu-pill';
		bar.appendChild(pill);

		var topLevelLinks = function () {
			return Array.prototype.slice.call(list.children)
				.map(function (li) { return li.querySelector(':scope > a'); })
				.filter(Boolean);
		};

		// Measured from the bar's padding box (inside its border), the box the
		// absolutely positioned highlight is placed in (v1.15.1: was the
		// border box, 1px off on the bordered night pill).
		var rectWithin = function (el) {
			var a = el.getBoundingClientRect();
			var b = bar.getBoundingClientRect();
			return { left: a.left - b.left - bar.clientLeft, width: a.width };
		};

		var placed = { left: NaN, width: NaN };
		var place = function (el, animate) {
			if (!el) return;
			var r = rectWithin(el);
			placed = r;
			if (!animate) pill.style.transition = 'none';
			pill.style.transform = 'translateX(' + r.left.toFixed(1) + 'px)';
			pill.style.width = r.width.toFixed(1) + 'px';
			if (!animate) {
				// Force the "none" transition to apply before restoring it, so the
				// next change (an animated one) does not inherit this jump.
				void pill.offsetWidth;
				pill.style.transition = '';
			}
			pill.classList.add('is-ready');
		};

		var currentLink = function () {
			var li = list.querySelector(':scope > li.current-menu-item, :scope > li.current-menu-ancestor');
			return li ? li.querySelector(':scope > a') : null;
		};

		var current = currentLink();
		var fromHref = null;
		try { fromHref = sessionStorage.getItem('djdMenuFrom'); sessionStorage.removeItem('djdMenuFrom'); } catch (e) { /* ignore */ }

		if (fromHref) {
			var fromLink = topLevelLinks().filter(function (a) { return a.href === fromHref; })[0];
			if (fromLink) {
				place(fromLink, false);
				// Let the instant placement paint, then slide to the real current item.
				requestAnimationFrame(function () { requestAnimationFrame(function () { place(current, true); }); });
			} else {
				place(current, false);
			}
		} else {
			place(current, false);
		}

		var resizeTimer;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(function () { place(currentLink(), false); }, 150);
		});

		// The highlight has to follow the current link whenever the bar's
		// size or padding changes: on hero pages mirrorball.js grows the
		// bar's left padding (the logo slot) every frame while the logo
		// docks, and fonts loading or a resize change the link widths.
		// v1.15.1, found live: v1.13.0 only watched the bar with a plain
		// ResizeObserver, which reports the CONTENT box, and growing the
		// padding leaves that unchanged, so the highlight stayed behind and
		// ended up under the logo, past the pill's left end. Now any of
		// these re-measures, once per frame at most, and moves it (no
		// animation) only if the link actually moved: a ResizeObserver on
		// the bar's border box and on the current link, a MutationObserver
		// on the bar's style attribute (the padding mirrorball.js sets),
		// transitionend on the bar, font loading and resize. Skipped mid
		// click (the slide toward the clicked item is already running).
		// The observers measure right away, in the same frame as the change
		// and before it paints: the padding is set inside mirrorball.js's
		// own animation frame, and a re-measure queued for the next frame
		// left the highlight one frame behind the whole way (found in the
		// harness). The other triggers, which have no such timing, go
		// through remeasure(), at most once per frame.
		var navigating = false;
		var queued = false;
		var measureNow = function () {
			if (navigating) return;
			var el = currentLink();
			if (!el) return;
			var r = rectWithin(el);
			if (Math.abs(r.left - placed.left) > 0.5 || Math.abs(r.width - placed.width) > 0.5) place(el, false);
		};
		var remeasure = function () {
			if (queued) return;
			queued = true;
			requestAnimationFrame(function () {
				queued = false;
				measureNow();
			});
		};
		if (window.ResizeObserver) {
			var ro = new ResizeObserver(measureNow);
			try { ro.observe(bar, { box: 'border-box' }); } catch (e) { ro.observe(bar); }
			if (current) ro.observe(current);
		}
		if (window.MutationObserver) {
			new MutationObserver(measureNow).observe(bar, { attributes: true, attributeFilter: ['style', 'class'] });
		}
		bar.addEventListener('transitionend', remeasure);
		if (document.fonts && document.fonts.ready) document.fonts.ready.then(remeasure);
		window.addEventListener('load', remeasure);

		topLevelLinks().forEach(function (a) {
			a.addEventListener('click', function (e) {
				if (e.metaKey || e.ctrlKey || e.shiftKey || 1 === e.button) {
					return; // new tab: let it navigate plainly
				}
				e.preventDefault();
				navigating = true;
				place(a, true);
				var href = a.href;
				setTimeout(function () {
					try { sessionStorage.setItem('djdMenuFrom', href); } catch (err) { /* ignore */ }
					window.location.href = href;
				}, 420);
			});
		});
	}

	/* -----------------------------------------------------------------------
	 * Dropdown panels: CSS positions every panel below and left aligned to
	 * its own trigger (or to the right for a nested flyout). This only adds
	 * djd-flip-right when that would run the panel past the viewport's right
	 * edge, which plain CSS has no way to detect on its own. A trigger whose
	 * own parent has already flipped left inherits that regardless of its
	 * own fit, so a chain of nested flyouts keeps moving the same direction
	 * instead of zigzagging back toward the panel it is trying to clear.
	 * -------------------------------------------------------------------- */
	(function keepDropdownsOnscreen() {
		var triggerSelector = '.fusion-main-menu li.menu-item-has-children, .fusion-main-menu li.fusion-dropdown-menu';
		var triggers = document.querySelectorAll(triggerSelector);
		if (!triggers.length) return;

		function checkOne(li) {
			var panel = li.querySelector(':scope > ul.sub-menu');
			if (!panel) return;
			var parentLi = li.parentElement ? li.parentElement.closest(triggerSelector) : null;
			var ancestorFlipped = !!(parentLi && parentLi.classList.contains('djd-flip-right'));
			li.classList.remove('djd-flip-right');
			var overflowsRight = false;
			if (!ancestorFlipped) {
				var wasHidden = panel.style.visibility;
				// A closed panel (display:none via Avada, or just not hovered) can
				// still be measured by briefly forcing it visible off the clickable
				// path, since getBoundingClientRect on a non-rendered box is 0.
				panel.style.visibility = 'hidden';
				panel.style.display = 'block';
				var r = panel.getBoundingClientRect();
				overflowsRight = r.right > window.innerWidth - 8;
				panel.style.display = '';
				panel.style.visibility = wasHidden;
			}
			if (ancestorFlipped || overflowsRight) {
				li.classList.add('djd-flip-right');
			}
		}

		function checkAll() {
			triggers.forEach(checkOne);
		}

		checkAll();
		var resizeTimer;
		window.addEventListener('resize', function () {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(checkAll, 150);
		});
		triggers.forEach(function (li) {
			li.addEventListener('mouseenter', function () { checkOne(li); });
			li.addEventListener('focusin', function () { checkOne(li); });
		});
	})();

	/* -----------------------------------------------------------------------
	 * Phone menu pill text (v1.14.0): "Menu", not Avada's "Go to...", on
	 * every themed page since v1.15.0. PHP already changes the string Avada builds the button
	 * from (the gettext filter in djdigi-theme.php); this covers a cached
	 * copy of Avada's script still carrying the old one. Avada builds that
	 * button in its own JS after this file runs, hence the observer, which
	 * stops once the button has been seen.
	 * -------------------------------------------------------------------- */
	(function mobileMenuLabel() {
		if (!document.body.classList.contains('djdigi-theme')) return;
		var holder = document.querySelector('.fusion-mobile-nav-holder');
		if (!holder) return;
		function relabel() {
			var span = holder.querySelector('.fusion-mobile-selector > span');
			if (!span) return false;
			if (span.textContent.trim() === 'Go to...') span.textContent = 'Menu';
			return true;
		}
		if (relabel() || !window.MutationObserver) return;
		var mo = new MutationObserver(function () {
			if (relabel()) mo.disconnect();
		});
		mo.observe(holder, { childList: true, subtree: true });
	})();
})();
