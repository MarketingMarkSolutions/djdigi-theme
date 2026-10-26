/* The mirror ball hero: a port of drawBall(), cluster() and the speck math
   from reference/mirrorball-hero-reference.html (the originally approved
   demo, v1.11.0), classes renamed to this theme's own djd- prefix (see
   CLAUDE.md, "Mirror ball hero"). The entrance timeline, the docking
   formula and the landing flourish below are v1.12.0's own numbers, not
   the reference's: Mark reviewed and confirmed this exact timeline live on
   Home v2, see CLAUDE.md for the full detail. drawBall()/cluster()
   themselves are unchanged from the v1.11.0 port.

   v1.12.0 also removed the v1.11.0 "quick repeat visit" path entirely
   (sessionStorage, djd-ball-noanim): the full entrance now plays on every
   load of a hero page, Mark's call. ?intro=1 is accordingly a no-op now,
   there is nothing left for it to force.

   v1.14.0: the ball itself is drawn in 3D (mirrorball-3d.js, three.js)
   once that module has loaded, after the page has; drawBall() below is
   what shows until then, and the fallback for good if WebGL is missing,
   the module fails to load, or the WebGL context is lost. Everything
   else here (position, size, drop, wire, spotlight, docking, the pill
   slot, rot and flash) is unchanged and drives both.

   No prefers-reduced-motion branch anywhere in this file: keeping the
   sitewide "motion always on" policy intact (CLAUDE.md, "Motion: always
   on, by design") rather than carving out an exception for this feature. */
(function () {
	'use strict';

	var hero = document.querySelector('.djd-hero');
	if (!hero) return;

	var cfg = window.djdtMirrorBall || {};
	var BALL_X = typeof cfg.ballX === 'number' ? cfg.ballX : 86;
	var BALL_Y = typeof cfg.ballY === 'number' ? cfg.ballY : 13.6;
	var BALL_R = typeof cfg.ballR === 'number' ? cfg.ballR : 9.2;

	var fx = hero.querySelector('.djd-hero__fx');
	var dark = hero.querySelector('.djd-hero__dark');
	var big = hero.querySelector('.djd-hero__logo');
	// The Avada header's own logo image and the header row itself, outside
	// the hero entirely: this is the reference's "mini", docked into on
	// scroll. Not every page this script loads on necessarily has one (a
	// test harness, a future Avada change), so every use of either below is
	// null-guarded rather than assumed.
	//
	// v1.13.0: the standard logo img specifically (site.css hides the
	// sticky and mobile copies on hero pages), and the logo now lives in a
	// slot on the left end of the menu pill, not at the left gutter: see
	// placeLogo() below.
	var mini = document.querySelector('.fusion-logo img.fusion-standard-logo') || document.querySelector('.fusion-logo img');
	var logoBox = mini ? mini.closest('.fusion-logo') : null;
	var logoRow = logoBox ? logoBox.parentElement : null;
	var headerWrapper = document.querySelector('.fusion-header-wrapper');
	var desktopPill = document.querySelector('.fusion-main-menu');
	// v1.14.0: looked up when needed, not once here. Avada builds its
	// classic mobile menu button in its own JS after this file has run
	// (the server sends an empty .fusion-mobile-nav-holder), so a one time
	// lookup found nothing live and the phone pill never got its logo slot.
	var mobilePill = null;
	function findMobilePill() {
		if (!mobilePill || !mobilePill.isConnected) mobilePill = document.querySelector('.fusion-mobile-selector');
		return mobilePill;
	}
	var wire = document.getElementById('djd-wire');
	var bc = document.getElementById('djd-ball-canvas');
	if (!fx || !dark || !big || !wire || !bc) return;

	// PHP already rewrites all three Avada logo imgs to the night logo
	// (djdt_swap_header_logos()); this is the backup if a cache layer ever
	// serves a page from before that, srcset and Avada's retina swap
	// attribute included so neither can bring the old PNG back.
	if (cfg.logoUrl) {
		Array.prototype.forEach.call(document.querySelectorAll('.fusion-logo img'), function (img) {
			img.removeAttribute('srcset');
			img.setAttribute('data-retina_logo_url', cfg.logoUrl);
			if (img.getAttribute('src') !== cfg.logoUrl) img.src = cfg.logoUrl;
		});
	}

	// The logo slot (v1.13.0): the pill's own left padding grows by
	// SLOT_GROW px as q goes 0 to 1 (6px to 52px on the desktop menu), and
	// the logo is moved so its right edge sits SLOT_GAP px short of where
	// the menu's first item now starts. At q = 0 that puts it just outside
	// the pill's left end, invisible anyway (opacity follows q). The pill
	// is whichever of Avada's desktop menu or classic mobile button is
	// actually showing at this width.
	var SLOT_GROW = 46, SLOT_GAP = 8;
	function activePill() {
		if (desktopPill && desktopPill.offsetWidth > 0) return desktopPill;
		var mp = findMobilePill();
		if (mp && mp.offsetWidth > 0) return mp;
		return null;
	}
	function basePad(el) {
		if (el.djdBasePad === undefined) el.djdBasePad = parseFloat(getComputedStyle(el).paddingLeft) || 0;
		return el.djdBasePad;
	}
	var shownPill = null;
	function placeLogo(q) {
		if (!mini || !logoBox || !logoRow) return;
		var pill = activePill();
		if (pill !== shownPill) {
			if (shownPill) shownPill.style.removeProperty('padding-left');
			shownPill = pill;
		}
		if (!pill) return;
		var pad = basePad(pill) + SLOT_GROW * q;
		pill.style.setProperty('padding-left', pad.toFixed(2) + 'px', 'important');
		var pr = pill.getBoundingClientRect(), rr = logoRow.getBoundingClientRect();
		var border = parseFloat(getComputedStyle(pill).borderLeftWidth) || 0;
		var x = pr.left + border + pad - SLOT_GAP - mini.offsetWidth - rr.left;
		var y = pr.top + (pr.height - mini.offsetHeight) / 2 - rr.top;
		logoBox.style.transform = 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)';
		logoBox.style.pointerEvents = q > 0.5 ? 'auto' : 'none';
	}

	// The header's own laid out bottom edge (v1.13.0): its CSS top (0, or
	// the admin bar offset) is read once and again on resize. The v1.13.0
	// hide on scroll down was removed in v1.14.0; the pill always shows.
	var headerTop = 0;
	function readHeaderTop() {
		headerTop = headerWrapper ? (parseFloat(getComputedStyle(headerWrapper).top) || 0) : 0;
	}
	readHeaderTop();

	var fc = fx.getContext('2d');
	var bx = bc.getContext('2d');

	// The 3D ball (v1.14.0): its own fixed canvas beside #djd-ball-canvas
	// (same CSS, hero.css), filled in by mirrorball-3d.js once loaded. ball3d
	// stays null (2D drawBall()) until then, and goes back to null for good
	// on any failure, see load3d() below.
	var ball3d = null, c3 = null;
	function drop3d() {
		if (ball3d) { try { ball3d.dispose(); } catch (e) {} }
		ball3d = null;
		if (c3 && c3.parentNode) c3.parentNode.removeChild(c3);
		c3 = null;
		document.documentElement.setAttribute('data-djd-ball', '2d');
	}
	var imgs = Array.prototype.slice.call(hero.querySelectorAll('.djd-hero__photo'));
	var curGuess = -1;
	for (var gi = 0; gi < imgs.length; gi++) {
		if (imgs[gi].classList.contains('is-on')) { curGuess = gi; break; }
	}

	var DPR = Math.min(window.devicePixelRatio || 1, 2);
	var W = 0, H = 0, cur = curGuess >= 0 ? curGuess : 0;
	var mouse = { x: -9999, y: -9999 };
	var start = 0, rot = 0, flash = 0, last = performance.now(), landed = false;
	var specks = [], trans = null, ring = null, nextAt = 0, timers = [];
	var running = false, heroOnScreen = true;
	var ang = 75 * Math.PI / 180, ca = Math.cos(ang), sa = Math.sin(ang), SP = 7;

	function clamp(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }
	function eOut(t) { return 1 - Math.pow(1 - t, 3); }
	function eIO(t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
	function eBack(t, c1) { var c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); }
	// How much of the black intro overlay is left, 1 (full) down to 0: both
	// the overlay itself and the spotlight (v1.12.0, "removed with it") read
	// this one curve, so they always finish fading together.
	function overlayFade(t) { return t < 2450 ? 1 : clamp(1 - (t - 2450) / 700); }

	function size() {
		var r = hero.getBoundingClientRect();
		W = r.width; H = r.height;
		fx.width = Math.round(W * DPR); fx.height = Math.round(H * DPR);
		fx.style.width = W + 'px'; fx.style.height = H + 'px';
	}

	// The slot an element's own mirror ball sits in: BALL_X/Y/R are all a
	// percent of the element's own rendered WIDTH (not height, even for Y),
	// since the logo's aspect ratio is fixed and that is the one scale
	// factor the reference itself uses throughout (its own slot()).
	function slot(el) {
		var r = el.getBoundingClientRect();
		return { x: r.left + (BALL_X / 100) * r.width, y: r.top + (BALL_Y / 100) * r.width, r: (BALL_R / 100) * r.width };
	}

	function makeSpecks() {
		specks = [];
		var n = W < 700 ? 36 : 80;
		for (var i = 0; i < n; i++) {
			var q = Math.random();
			specks.push({
				lon: Math.random() * 6.2832,
				lat: -.25 + Math.random() * 1.45,
				s: .6 + Math.random() * 1.1,
				h: Math.random() * 6.283,
				c: q < .5 ? '241,236,226' : (q < .8 ? '36,169,224' : '170,130,240')
			});
		}
	}

	var PAL = [[0, [30, 14, 62]], [.35, [90, 46, 145]], [.62, [36, 169, 224]], [.85, [241, 236, 226]], [1.2, [255, 255, 255]]];
	function pal(v) {
		if (v <= 0) v = 0;
		for (var i = 1; i < PAL.length; i++) {
			if (v <= PAL[i][0] || i === PAL.length - 1) {
				var a = PAL[i - 1], b = PAL[i], t = clamp((v - a[0]) / (b[0] - a[0]));
				return 'rgb(' + Math.round(a[1][0] + (b[1][0] - a[1][0]) * t) + ',' + Math.round(a[1][1] + (b[1][1] - a[1][1]) * t) + ',' + Math.round(a[1][2] + (b[1][2] - a[1][2]) * t) + ')';
			}
		}
	}
	function hash(i, j) { var x = Math.sin(i * 12.9898 + j * 78.233) * 43758.5453; return x - Math.floor(x); }

	function drawBall(r, lx, ly, now) {
		var pad = 3, sz = Math.ceil(r * 2 + pad * 2), px = Math.round(sz * DPR);
		if (bc.width !== px) { bc.width = px; bc.height = px; bc.style.width = sz + 'px'; bc.style.height = sz + 'px'; }
		bx.setTransform(DPR, 0, 0, DPR, 0, 0); bx.clearRect(0, 0, sz, sz);
		var c = sz / 2, g = bx.createRadialGradient(c - r * .3, c - r * .35, r * .1, c, c, r);
		g.addColorStop(0, '#2e1760'); g.addColorStop(1, '#05031a');
		bx.fillStyle = g; bx.beginPath(); bx.arc(c, c, r, 0, 6.2832); bx.fill();
		bx.save(); bx.beginPath(); bx.arc(c, c, r, 0, 6.2832); bx.clip();
		var rows = r > 30 ? 14 : 9, cols = rows * 2, ll = Math.sqrt(lx * lx + ly * ly + .8), Lx = lx / ll, Ly = ly / ll, Lz = .9 / ll;
		for (var i = 0; i < rows; i++) {
			var la0 = -Math.PI / 2 + i * Math.PI / rows, la1 = la0 + Math.PI / rows, lac = (la0 + la1) / 2;
			for (var j = 0; j < cols; j++) {
				var lo0 = j * 6.2832 / cols + rot, lo1 = lo0 + 6.2832 / cols, loc = (lo0 + lo1) / 2;
				var nx = Math.cos(lac) * Math.sin(loc), ny = Math.sin(lac), nz = Math.cos(lac) * Math.cos(loc);
				if (nz < .03) continue;
				var pts = [[la0, lo0], [la0, lo1], [la1, lo1], [la1, lo0]], cx = c + r * nx, cy = c + r * ny;
				var h = hash(i, j), lam = Math.max(0, nx * Lx + ny * Ly + nz * Lz);
				var sp = .5 * Math.pow(Math.max(0, Math.sin(now * .0028 + h * 60)), 26);
				var v = (.12 + .6 * Math.pow(lam, 2.2) + sp * .9 + flash * (.4 + .6 * h)) * (.35 + .65 * nz);
				bx.fillStyle = pal(v); bx.beginPath();
				for (var k = 0; k < 4; k++) {
					var la = pts[k][0], lo = pts[k][1], X = c + r * Math.cos(la) * Math.sin(lo), Y = c + r * Math.sin(la);
					X = cx + (X - cx) * .8; Y = cy + (Y - cy) * .8;
					if (k) bx.lineTo(X, Y); else bx.moveTo(X, Y);
				}
				bx.fill();
			}
		}
		bx.restore();
		bx.strokeStyle = 'rgba(241,236,226,.22)'; bx.lineWidth = 1; bx.beginPath(); bx.arc(c, c, r - .5, 0, 6.2832); bx.stroke();
		return sz;
	}

	function cluster(x, y, R, al, col) {
		if (al < .02 || R < 1) return;
		var g = fc.createRadialGradient(x * DPR, y * DPR, 0, x * DPR, y * DPR, R * .9 * DPR);
		g.addColorStop(0, 'rgba(' + col + ',' + Math.min(1, al * .45) + ')'); g.addColorStop(1, 'rgba(' + col + ',0)');
		fc.fillStyle = g; fc.beginPath(); fc.arc(x * DPR, y * DPR, R * .9 * DPR, 0, 6.2832); fc.fill();
		var u0 = x * ca + y * sa, v0 = -x * sa + y * ca, k = Math.ceil(R / SP), ub = Math.round(u0 / SP), vb = Math.round(v0 / SP);
		fc.fillStyle = 'rgb(' + col + ')';
		for (var i = -k; i <= k; i++) for (var j = -k; j <= k; j++) {
			var u = (ub + i) * SP, v = (vb + j) * SP, du = u - u0, dv = v - v0, d = Math.sqrt(du * du + dv * dv);
			if (d > R) continue;
			var f = 1 - d / R, px = u * ca - v * sa, py = u * sa + v * ca;
			fc.globalAlpha = Math.min(1, al * f * 1.5); fc.beginPath(); fc.arc(px * DPR, py * DPR, (.5 + 1.7 * f) * DPR, 0, 6.2832); fc.fill();
		}
		fc.globalAlpha = 1;
	}

	// A plain soft radial glow, no halftone dots (unlike cluster()): used for
	// both the entrance spotlight and the one-time landing glow ring
	// (v1.12.0), which are washes of light, not individual specks.
	function softGlow(x, y, r, al) {
		if (al < .002 || r < 1) return;
		var g = fc.createRadialGradient(x * DPR, y * DPR, 0, x * DPR, y * DPR, r * DPR);
		g.addColorStop(0, 'rgba(241,236,226,' + al + ')');
		g.addColorStop(1, 'rgba(241,236,226,0)');
		fc.fillStyle = g; fc.beginPath(); fc.arc(x * DPR, y * DPR, r * DPR, 0, 6.2832); fc.fill();
	}

	function frame(now) {
		if (!running) return;
		var dt = Math.min(64, now - last); last = now;
		var t = now - start;
		rot += dt * .0002; flash = Math.max(0, flash - dt / 900);
		var hr = hero.getBoundingClientRect();

		// Docking handoff (v1.12.0, replacing a plain scroll fraction): q is
		// how far the hero logo's own top edge has passed under the fixed
		// header's own bottom edge, 0 (not yet) to 1 (60% of the logo's own
		// height has gone under). headerBottom already reflects the admin
		// bar offset, since it is read from the header's own live, laid out
		// rect (site.css sets that offset on the header itself when logged
		// in), not recomputed here. Before the handoff this is 0 and the
		// ball simply rides with the hero logo as it scrolls, since sm/sb
		// below both still resolve to the hero's own slot.
		//
		// v1.13.0: headerBottom is the header's laid out position (CSS top
		// plus its own height). The logo is moved into the pill's slot
		// first, then both slots are read, slot(mini) from the logo's own
		// live rect.
		var heroLogoRect = big.getBoundingClientRect();
		var headerBottom = headerWrapper ? headerTop + headerWrapper.offsetHeight : 0;
		var heroLogoH = Math.max(1, heroLogoRect.height);
		var q = clamp((headerBottom - heroLogoRect.top) / (0.6 * heroLogoH));
		q = q * q * (3 - 2 * q);
		placeLogo(q);
		if (mini) mini.style.opacity = q;
		var sb = slot(big), sm = mini ? slot(mini) : sb;
		// The hero logo's own entrance fade (same 600ms window the djd-seq-1
		// class's transform transition uses, see hero.css) combined with the
		// docking fade: one driver, not two fighting over the same property,
		// since this script also owns the logo's opacity for the docking
		// crossfade, the transform stays CSS/class driven (unaffected by q).
		var entranceOpacity = t < 2550 ? 0 : clamp((t - 2550) / 600);
		big.style.opacity = entranceOpacity * (1 - q);

		var tx = sb.x + (sm.x - sb.x) * q, ty = sb.y + (sm.y - sb.y) * q, tr = sb.r + (sm.r - sb.r) * q, by = ty;
		if (t < 2200) {
			var y0 = -tr * 2.2, dp = clamp((t - 400) / 1800);
			by = y0 + (ty - y0) * eBack(dp, 0.9);
		} else {
			by = ty;
			if (!landed) { landed = true; flash = 1; ring = { t0: now }; }
		}
		bc.style.opacity = t < 400 ? 0 : 1;
		var shown3d = false;
		if (ball3d) {
			// Rendered only while the ball is actually visible.
			if (t >= 400) {
				try {
					shown3d = ball3d.render({ x: tx, y: by, r: tr, rot: rot, flash: flash, now: now });
				} catch (e) {
					drop3d();
				}
			}
			if (c3) c3.style.opacity = shown3d ? 1 : 0;
		}
		if (shown3d) {
			bc.style.opacity = 0;
		} else {
			var lx = (mouse.x - tx) / Math.max(W, 1), ly = (mouse.y - by) / Math.max(H, 1);
			if (mouse.x < -999) { lx = -.4; ly = -.5; }
			var sz = drawBall(tr, lx * 2, ly * 2, now);
			bc.style.transform = 'translate(' + (tx - sz / 2) + 'px,' + (by - sz / 2) + 'px)';
		}
		var wa = t < 400 ? 0 : t < 2900 ? 1 : clamp(1 - (t - 2900) / 450);
		wire.style.opacity = wa; wire.style.left = tx + 'px'; wire.style.height = Math.max(0, by - tr) + 'px';
		dark.style.opacity = overlayFade(t);

		if (heroOnScreen) {
			fc.clearRect(0, 0, fx.width, fx.height);
			var ox = tx - hr.left, oy = by - hr.top, mx = mouse.x - hr.left, my = mouse.y - hr.top;

			// Spotlight: a soft pool of light above the black overlay,
			// following the ball, v1.12.0. 12% alpha through the drop,
			// swelling to 20% during the hang (2200 to 2500), then fading
			// out together with the overlay itself (overlayFade above).
			if (t >= 400) {
				var spotBase = t < 2200 ? 0.12 : (t < 2500 ? 0.12 + 0.08 * clamp((t - 2200) / 300) : 0.20);
				softGlow(ox, oy, tr * 3, spotBase * overlayFade(t));
			}

			if (t >= 2500) {
				// Burst: specks start oversized and overbright, easing down
				// to their normal size/alpha over the same 900ms they fly
				// out over (v1.12.0, was flat from the start).
				var bp = eOut(clamp((t - 2500) / 900));
				var sizeMul = 1 + 0.6 * (1 - bp), alphaMul = 1 + 1 * (1 - bp);
				for (var i = 0; i < specks.length; i++) {
					var s = specks[i], ph = s.lon + rot, z = Math.cos(ph), zf = (z + 1) / 2, Rx = Math.max(ox, W - ox) * 1.05;
					var dxp = ox + Rx * Math.sin(ph), dyp = oy + H * 1.05 * Math.sin(s.lat);
					var x = ox + (dxp - ox) * bp, y = oy + (dyp - oy) * bp;
					var al = (.3 + .5 * Math.pow(.5 + .5 * Math.sin(now * .0014 + s.h), 3)) * bp * (.25 + .75 * zf);
					var dx = x - mx, dy = y - my, b = Math.exp(-(dx * dx + dy * dy) / (180 * 180));
					al += b * .8 + flash * .5;
					s.zs = .65 + .55 * zf;
					cluster(x, y, s.s * 13 * s.zs * (1 + b * .7) * sizeMul, al * alphaMul, s.c);
				}
			}

			// One-time glow ring on landing, v1.12.0: grows to 40% of the
			// hero's own width over 600ms while fading out, independent of
			// the overlay/spotlight fade above.
			if (ring) {
				var age = now - ring.t0;
				if (age <= 600) {
					var rk = clamp(age / 600);
					softGlow(ox, oy, rk * 0.4 * W, 0.35 * (1 - rk));
				} else {
					ring = null;
				}
			}

			if (!trans && t > 2500 && now > nextAt && imgs.length > 1) {
				var to = (cur + 1) % imgs.length;
				trans = { t0: now, from: cur, to: to };
				imgs[to].classList.add('is-next');
				imgs[to].style.clipPath = 'circle(0px at ' + ox + 'px ' + oy + 'px)';
				flash = 1;
				if (ball3d) ball3d.setEnv(imgs[to]);
			}
			if (trans) {
				var k = clamp((now - trans.t0) / 1300), R = eIO(k) * Math.hypot(W + Math.abs(ox), H + Math.abs(oy));
				imgs[trans.to].style.clipPath = 'circle(' + R + 'px at ' + ox + 'px ' + oy + 'px)';
				var n = Math.min(220, Math.floor(6.2832 * R / 24));
				for (var q2 = 0; q2 < n; q2++) {
					var a = q2 / n * 6.2832 + rot, qx = ox + Math.cos(a) * R, qy = oy + Math.sin(a) * R;
					if (qx > -20 && qx < W + 20 && qy > -20 && qy < H + 20) cluster(qx, qy, 9, .85 * (1 - k), q2 % 3 ? '241,236,226' : '36,169,224');
				}
				if (k >= 1) {
					imgs[trans.from].classList.remove('is-on');
					imgs[trans.to].classList.add('is-on');
					imgs[trans.to].classList.remove('is-next');
					imgs[trans.to].style.clipPath = '';
					cur = trans.to; trans = null; nextAt = now + 6000;
				}
			}
		}
		requestAnimationFrame(frame);
	}

	/**
	 * Schedules the whole entrance, every page load (v1.12.0, no more
	 * sessionStorage skip): the drop, the hang and the landing flourish all
	 * live in frame() above, keyed off `start`; this just resets the state
	 * and schedules the four djd-seq-* classes that reveal the logo
	 * (djd-seq-1) and the slogan/buttons (djd-seq-3). djd-seq-2/4 currently
	 * drive nothing in CSS, kept for parity with the v1.11.0 reference port
	 * in case a future element needs one of those two steps.
	 */
	function play() {
		timers.forEach(clearTimeout); timers = [];
		trans = null; ring = null; landed = false; flash = 0;
		size(); makeSpecks();
		var nowStart = performance.now();
		start = nowStart;
		nextAt = nowStart + 6600;
		var seq = ['djd-seq-1', 'djd-seq-2', 'djd-seq-3', 'djd-seq-4'];
		var steps = [2550, 2800, 3050, 3350];
		seq.forEach(function (c, i) {
			timers.push(setTimeout(function () { document.body.classList.add(c); }, steps[i]));
		});
	}

	function startLoop() {
		if (running) return;
		running = true;
		last = performance.now();
		requestAnimationFrame(frame);
	}
	function stopLoop() { running = false; }

	window.addEventListener('mousemove', function (e) { mouse.x = e.clientX; mouse.y = e.clientY; });
	window.addEventListener('mouseleave', function () { mouse.x = -9999; });
	window.addEventListener('resize', function () { size(); readHeaderTop(); });
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) stopLoop(); else startLoop();
	});
	if (window.IntersectionObserver) {
		new IntersectionObserver(function (entries) {
			heroOnScreen = entries[0].isIntersecting;
		}, { threshold: 0 }).observe(hero);
	}

	// Loads the 3D ball after the page has finished loading, on idle, as an
	// ES module (dynamic import(), cfg.ball3dUrl from PHP, which itself
	// imports the bundled three.js). Any failure (no WebGL, the module or
	// three.js not loading, a lost context) leaves the 2D ball in place.
	function load3d() {
		if (!cfg.ball3dUrl) { document.documentElement.setAttribute('data-djd-ball', '2d'); return; }
		// import() wrapped in a Function: written directly, a browser
		// without dynamic import would reject this whole file as a syntax
		// error, 2D ball included. A blocked Function (strict CSP) lands in
		// the catch, same as any other failure.
		var importer;
		try { importer = new Function('u', 'return import(u)'); } catch (e) { drop3d(); return; }
		importer(cfg.ball3dUrl).then(function (mod) {
			c3 = document.createElement('canvas');
			c3.id = 'djd-ball-3d';
			c3.setAttribute('aria-hidden', 'true');
			bc.parentNode.insertBefore(c3, bc.nextSibling);
			var b = mod.createBall(c3, { onLost: drop3d });
			var photo = imgs[cur];
			if (photo && photo.complete) b.setEnv(photo);
			else if (photo) photo.addEventListener('load', function () { if (ball3d) ball3d.setEnv(photo); }, { once: true });
			ball3d = b;
			document.documentElement.setAttribute('data-djd-ball', '3d');
		}).catch(function () { drop3d(); });
	}
	function whenIdle(fn) {
		if (window.requestIdleCallback) window.requestIdleCallback(fn, { timeout: 2000 });
		else setTimeout(fn, 200);
	}
	if (document.readyState === 'complete') whenIdle(load3d);
	else window.addEventListener('load', function () { whenIdle(load3d); }, { once: true });

	var firstPhoto = imgs[cur];
	var photoReady = (firstPhoto && firstPhoto.decode) ? firstPhoto.decode().catch(function () {}) : Promise.resolve();
	var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready.catch(function () {}) : Promise.resolve();
	var timeout = new Promise(function (resolve) { setTimeout(resolve, 1200); });
	Promise.race([Promise.all([photoReady, fontsReady]), timeout]).then(function () {
		play();
		startLoop();
	});
})();
