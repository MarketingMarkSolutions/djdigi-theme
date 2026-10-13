/* Avada menu restyle: the sliding pill behind the current item, and a JS
   fallback for the "Book me" button if the PHP filter's menu slug or theme
   location did not match this install's menu. */
(function () {
	'use strict';

	var cfg = window.djdtMenu || {};
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* -----------------------------------------------------------------------
	 * Book me fallback
	 * -------------------------------------------------------------------- */
	(function bookMeFallback() {
		if (document.querySelector('.djdt-book-me')) return; // the PHP filter already added it
		if (!cfg.bookingUrl) return;

		function buildItem() {
			var li = document.createElement('li');
			li.id = 'djdt-book-me';
			li.className = 'menu-item djdt-book-me';
			var a = document.createElement('a');
			a.href = cfg.bookingUrl;
			var text = document.createElement('span');
			text.className = 'djdt-book-me__text';
			text.textContent = 'Book me';
			var arrow = document.createElement('span');
			arrow.className = 'djdt-book-me__arrow';
			arrow.setAttribute('aria-hidden', 'true');
			arrow.textContent = '↗';
			a.appendChild(text);
			a.appendChild(arrow);
			li.appendChild(a);
			return li;
		}

		var desktopList = document.querySelector('.fusion-main-menu > ul.fusion-menu');
		if (desktopList) desktopList.appendChild(buildItem());

		var mobileList = document.querySelector('.fusion-mobile-nav-holder ul');
		if (mobileList) mobileList.appendChild(buildItem());
	})();

	/* -----------------------------------------------------------------------
	 * Sliding pill
	 * -------------------------------------------------------------------- */
	var bar = document.querySelector('.fusion-main-menu');
	var list = bar ? bar.querySelector(':scope > ul.fusion-menu') : null;
	if (!bar || !list) return;

	var pill = document.createElement('div');
	pill.className = 'djd-menu-pill';
	bar.appendChild(pill);

	function topLevelLinks() {
		return Array.prototype.slice.call(list.children)
			.filter(function (li) { return !li.classList.contains('djdt-book-me'); })
			.map(function (li) { return li.querySelector(':scope > a'); })
			.filter(Boolean);
	}

	function rectWithin(el) {
		var a = el.getBoundingClientRect();
		var b = bar.getBoundingClientRect();
		return { left: a.left - b.left, width: a.width };
	}

	function place(el, animate) {
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
	}

	function currentLink() {
		var li = list.querySelector(':scope > li.current-menu-item, :scope > li.current-menu-ancestor');
		return li && !li.classList.contains('djdt-book-me') ? li.querySelector(':scope > a') : null;
	}

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
})();
