/* The Contact / Book me pop-up (v1.16.0), every themed page. Every
   .djd-rec-btn (the hero's, the footer's from dock.js, the About page's)
   opens it instead of following its link. Closes on the x, Escape and a
   click outside the sleeve; focus moves into it on open (the sleeve itself,
   so a phone keyboard does not pop up) and back to the button on close,
   and Tab stays inside while it is open; the page does not scroll behind
   it. The form is the real Avada form 63559: printed inside the pop-up by
   PHP, or, on a page whose own content already has it, borrowed from the
   page while open and put back on close (includes/djdt-contact.php). No
   form anywhere: the buttons stay plain links. */
(function () {
	'use strict';

	var pop = document.getElementById('djx-pop');
	if (!pop) return;
	var sleeve = pop.querySelector('.djx-pop__sleeve');
	var slot = pop.querySelector('.djx-pop__form');
	var id = pop.getAttribute('data-form-id');
	if (!sleeve || !slot || !id) return;

	function pageForm() {
		var all = document.querySelectorAll('.fusion-form-form-wrapper.fusion-form-' + id + ', div.fusion-form-' + id);
		for (var i = 0; i < all.length; i++) {
			if (!pop.contains(all[i])) return all[i];
		}
		return null;
	}
	function hasForm() {
		return !!slot.querySelector('.fusion-form-' + id) || !!pageForm();
	}

	var opener = null, isOpen = false, closeTimer = 0;
	var borrowed = null, home = null;

	function borrow() {
		if (slot.querySelector('.fusion-form-' + id)) return;
		var f = pageForm();
		if (!f) return;
		// Holds the form's space, or the page would get shorter and jump.
		home = document.createElement('div');
		home.style.height = f.getBoundingClientRect().height + 'px';
		f.parentNode.insertBefore(home, f);
		slot.appendChild(f);
		borrowed = f;
	}
	function giveBack() {
		if (borrowed && home && home.parentNode) {
			home.parentNode.insertBefore(borrowed, home);
			home.parentNode.removeChild(home);
		}
		borrowed = null;
		home = null;
	}

	function lock() {
		var bar = window.innerWidth - document.documentElement.clientWidth;
		if (bar > 0) document.body.style.paddingRight = bar + 'px';
		document.documentElement.classList.add('djx-pop-lock');
	}
	function unlock() {
		document.documentElement.classList.remove('djx-pop-lock');
		document.body.style.paddingRight = '';
	}

	function open(btn) {
		clearTimeout(closeTimer);
		opener = btn || null;
		if (!isOpen) {
			borrow();
			pop.hidden = false;
			lock();
			void pop.offsetWidth; // start the slide from the closed state
			pop.classList.add('is-open');
			isOpen = true;
		}
		sleeve.scrollTop = 0;
		try { sleeve.focus({ preventScroll: true }); } catch (e) { sleeve.focus(); }
	}
	function close() {
		if (!isOpen) return;
		isOpen = false;
		pop.classList.remove('is-open');
		unlock();
		closeTimer = setTimeout(function () {
			pop.hidden = true;
			giveBack();
		}, 750);
		if (opener && opener.focus) {
			try { opener.focus({ preventScroll: true }); } catch (e) { opener.focus(); }
		}
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		if (isOpen && t.closest && t.closest('[data-djx-close]') && pop.contains(t)) {
			e.preventDefault();
			close();
			return;
		}
		var btn = t.closest ? t.closest('.djd-rec-btn') : null;
		if (!btn || pop.contains(btn)) return;
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		if (!hasForm()) return;
		e.preventDefault();
		open(btn);
	});

	function focusables() {
		return Array.prototype.filter.call(
			pop.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])'),
			function (el) { return el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0; }
		);
	}
	document.addEventListener('keydown', function (e) {
		if (!isOpen) return;
		if (e.key === 'Escape' || e.key === 'Esc') {
			e.preventDefault();
			close();
			return;
		}
		if (e.key !== 'Tab') return;
		var list = focusables();
		if (!list.length) { e.preventDefault(); sleeve.focus(); return; }
		var first = list[0], last = list[list.length - 1], a = document.activeElement;
		if (!pop.contains(a) || a === sleeve) {
			e.preventDefault();
			(e.shiftKey ? last : first).focus();
		} else if (e.shiftKey && a === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && a === last) {
			e.preventDefault();
			first.focus();
		}
	});

	// Safety net (v1.16.1): with no Avada form script on the page, submitting
	// would be a native GET, its "name" field a WordPress query var, and a 404.
	// Capture phase, ahead of everything else.
	document.addEventListener('submit', function (e) {
		var f = e.target;
		if (window.formCreatorConfig || !f.classList || !f.classList.contains('fusion-form-' + id)) return;
		e.preventDefault();
		e.stopImmediatePropagation();
		window.location.href = pop.getAttribute('data-fallback') || '/about/';
	}, true);

	// Screen readers: these buttons open a dialog now.
	function mark() {
		if (!hasForm()) return;
		Array.prototype.forEach.call(document.querySelectorAll('.djd-rec-btn'), function (b) {
			b.setAttribute('aria-haspopup', 'dialog');
			b.setAttribute('aria-controls', 'djx-pop');
		});
	}
	mark();
	// The footer's button is built by dock.js, which may run after this.
	if (document.readyState !== 'complete') window.addEventListener('load', mark, { once: true });
})();
