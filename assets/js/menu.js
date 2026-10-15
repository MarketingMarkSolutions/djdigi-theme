/* Avada menu restyle: the sliding pill behind the current item (the only
   highlight in the menu), and keeping dropdown panels inside the viewport. */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

		var rectWithin = function (el) {
			var a = el.getBoundingClientRect();
			var b = bar.getBoundingClientRect();
			return { left: a.left - b.left, width: a.width };
		};

		var place = function (el, animate) {
			if (!el) return;
			var r = rectWithin(el);
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

		if (fromHref && !reduceMotion) {
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

		topLevelLinks().forEach(function (a) {
			a.addEventListener('click', function (e) {
				if (e.metaKey || e.ctrlKey || e.shiftKey || 1 === e.button || reduceMotion) {
					return; // new tab, or motion is off: let it navigate plainly
				}
				e.preventDefault();
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
	 * edge, which plain CSS has no way to detect on its own.
	 * -------------------------------------------------------------------- */
	(function keepDropdownsOnscreen() {
		var triggers = document.querySelectorAll('.fusion-main-menu li.menu-item-has-children, .fusion-main-menu li.fusion-dropdown-menu');
		if (!triggers.length) return;

		function checkOne(li) {
			var panel = li.querySelector(':scope > ul.sub-menu');
			if (!panel) return;
			li.classList.remove('djd-flip-right');
			var wasHidden = panel.style.visibility;
			// A closed panel (display:none via Avada, or just not hovered) can
			// still be measured by briefly forcing it visible off the clickable
			// path, since getBoundingClientRect on a non-rendered box is 0.
			panel.style.visibility = 'hidden';
			panel.style.display = 'block';
			var r = panel.getBoundingClientRect();
			if (r.right > window.innerWidth - 8) {
				li.classList.add('djd-flip-right');
			}
			panel.style.display = '';
			panel.style.visibility = wasHidden;
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
})();
