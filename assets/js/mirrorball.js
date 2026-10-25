/* The mirror ball hero (v1.11.0): a direct port of drawBall(), cluster(),
   the speck math, the entrance timings and every easing function in
   reference/mirrorball-hero-reference.html, the approved demo, with
   classes renamed to this theme's own djd- prefix (see CLAUDE.md, "Mirror
   ball hero"). Do not reinterpret the look here; if a number in this file
   looks odd, check the reference file first, it is the source of truth.

   Two things the reference demo did not need, both added here:
   - "Once per browser session" (play(true) below): the reference always
     plays the full drop on its own Replay button; a real page load needs
     to skip the drop, wire and overlay on a repeat visit in the same
     session instead (sessionStorage, ?intro=1 forces it, same contract
     hero.js's old introTiming used to have). This reuses the exact timeline
     math the reference already has for a late/instant `t`, it is not a new
     branch of its own: setting `start` far enough in the past makes every
     `t < ...` threshold in frame() resolve to its own end state on the very
     first frame, with `djd-ball-noanim` suppressing the logo/slogan CSS
     transitions for that one frame so nothing visibly animates in.
   - Pausing the loop on document.hidden, skipping the specks canvas while
     the hero is off screen, and resizing on resize (CLAUDE.md,
     "Performance"); the demo never needed any of these since it is never
     actually scrolled away from or backgrounded as a tab.

   No prefers-reduced-motion branch anywhere in this file: Mark's call,
   keeping the existing sitewide "motion always on" policy (CLAUDE.md,
   "Motion: always on, by design") rather than carving out an exception
   for this one feature. */
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
	// The Avada header's own logo image, outside the hero entirely: this is
	// the reference's "mini", docked into on scroll. Not every page this
	// script loads on necessarily has one (a test harness, a future Avada
	// change), so every use of it below is null-guarded rather than
	// assumed.
	var mini = document.querySelector('.fusion-logo img');
	var wire = document.getElementById('djd-wire');
	var bc = document.getElementById('djd-ball-canvas');
	if (!fx || !dark || !big || !wire || !bc) return;

	if (mini && cfg.logoUrl) {
		mini.src = cfg.logoUrl;
	}

	var fc = fx.getContext('2d');
	var bx = bc.getContext('2d');
	var imgs = Array.prototype.slice.call(hero.querySelectorAll('.djd-hero__photo'));
	var curGuess = -1;
	for (var gi = 0; gi < imgs.length; gi++) {
		if (imgs[gi].classList.contains('is-on')) { curGuess = gi; break; }
	}

	var DPR = Math.min(window.devicePixelRatio || 1, 2);
	var W = 0, H = 0, cur = curGuess >= 0 ? curGuess : 0;
	var mouse = { x: -9999, y: -9999 };
	var start = 0, rot = 0, flash = 0, last = performance.now(), landed = false;
	var specks = [], trans = null, nextAt = 0, timers = [];
	var running = false, heroOnScreen = true;
	var ang = 75 * Math.PI / 180, ca = Math.cos(ang), sa = Math.sin(ang), SP = 7;

	function clamp(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }
	function eOut(t) { return 1 - Math.pow(1 - t, 3); }
	function eIO(t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
	function eBack(t) { var c1 = 1.25, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); }

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

	function frame(now) {
		if (!running) return;
		var dt = Math.min(64, now - last); last = now;
		var t = now - start;
		rot += dt * .0002; flash = Math.max(0, flash - dt / 650);
		var hr = hero.getBoundingClientRect();
		var sb = slot(big), sm = mini ? slot(mini) : sb;
		var p = clamp(window.scrollY / (H * .55)); p = p * p * (3 - 2 * p);
		if (mini) mini.style.opacity = clamp((p - .4) / .5);
		var tx = sb.x + (sm.x - sb.x) * p, ty = sb.y + (sm.y - sb.y) * p, tr = sb.r + (sm.r - sb.r) * p, by = ty;
		if (t < 1100) {
			var y0 = -tr * 2.2, dp = clamp((t - 250) / 850);
			by = y0 + (ty - y0) * eBack(dp);
		} else if (!landed) {
			landed = true; flash = 1;
		}
		bc.style.opacity = t < 250 ? 0 : 1;
		var lx = (mouse.x - tx) / Math.max(W, 1), ly = (mouse.y - by) / Math.max(H, 1);
		if (mouse.x < -999) { lx = -.4; ly = -.5; }
		var sz = drawBall(tr, lx * 2, ly * 2, now);
		bc.style.transform = 'translate(' + (tx - sz / 2) + 'px,' + (by - sz / 2) + 'px)';
		var wa = t < 250 ? 0 : t < 1500 ? 1 : clamp(1 - (t - 1500) / 450);
		wire.style.opacity = wa; wire.style.left = tx + 'px'; wire.style.height = Math.max(0, by - tr) + 'px';
		dark.style.opacity = t < 1050 ? 1 : clamp(1 - (t - 1050) / 650);

		if (heroOnScreen) {
			fc.clearRect(0, 0, fx.width, fx.height);
			var ox = tx - hr.left, oy = by - hr.top, mx = mouse.x - hr.left, my = mouse.y - hr.top;
			if (t > 1100) {
				var bp = eOut(clamp((t - 1100) / 900));
				for (var i = 0; i < specks.length; i++) {
					var s = specks[i], ph = s.lon + rot, z = Math.cos(ph), zf = (z + 1) / 2, Rx = Math.max(ox, W - ox) * 1.05;
					var dxp = ox + Rx * Math.sin(ph), dyp = oy + H * 1.05 * Math.sin(s.lat);
					var x = ox + (dxp - ox) * bp, y = oy + (dyp - oy) * bp;
					var al = (.3 + .5 * Math.pow(.5 + .5 * Math.sin(now * .0014 + s.h), 3)) * bp * (.25 + .75 * zf);
					var dx = x - mx, dy = y - my, b = Math.exp(-(dx * dx + dy * dy) / (180 * 180));
					al += b * .8 + flash * .5;
					s.zs = .65 + .55 * zf;
					cluster(x, y, s.s * 13 * s.zs * (1 + b * .7), al, s.c);
				}
			}
			if (!trans && t > 2600 && now > nextAt && imgs.length > 1) {
				var to = (cur + 1) % imgs.length;
				trans = { t0: now, from: cur, to: to };
				imgs[to].classList.add('is-next');
				imgs[to].style.clipPath = 'circle(0px at ' + ox + 'px ' + oy + 'px)';
				flash = 1;
			}
			if (trans) {
				var k = clamp((now - trans.t0) / 1300), R = eIO(k) * Math.hypot(W + Math.abs(ox), H + Math.abs(oy));
				imgs[trans.to].style.clipPath = 'circle(' + R + 'px at ' + ox + 'px ' + oy + 'px)';
				var n = Math.min(220, Math.floor(6.2832 * R / 24));
				for (var q = 0; q < n; q++) {
					var a = q / n * 6.2832 + rot, qx = ox + Math.cos(a) * R, qy = oy + Math.sin(a) * R;
					if (qx > -20 && qx < W + 20 && qy > -20 && qy < H + 20) cluster(qx, qy, 9, .85 * (1 - k), q % 3 ? '241,236,226' : '36,169,224');
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
	 * quick: a repeat view in the same browser session (or any time
	 * forceFull is false and sessionStorage already says the hero has been
	 * seen). start is set far enough in the past that every `t < ...`
	 * threshold in frame() above resolves to its own finished state on the
	 * very first frame, exactly the reference's own math, just evaluated at
	 * a large t instead of waiting wall clock time to get there. djd-ball-
	 * noanim (hero.css) suppresses the logo/slogan CSS transitions for that
	 * one frame so the content does not visibly animate in on a page nobody
	 * is seeing the hero on for the first time.
	 */
	function play(quick) {
		timers.forEach(clearTimeout); timers = [];
		trans = null; landed = !!quick; flash = 0;
		size(); makeSpecks();
		var nowStart = performance.now();
		start = quick ? nowStart - 99999 : nowStart;
		nextAt = nowStart + 5200;
		var seq = ['djd-seq-1', 'djd-seq-2', 'djd-seq-3', 'djd-seq-4'];
		if (quick) {
			document.body.classList.add('djd-ball-noanim');
			seq.forEach(function (c) { document.body.classList.add(c); });
			requestAnimationFrame(function () {
				void document.body.offsetWidth; // forces the no-transition state to apply before it is removed
				document.body.classList.remove('djd-ball-noanim');
			});
		} else {
			var steps = [1150, 1400, 1650, 1950];
			seq.forEach(function (c, i) {
				timers.push(setTimeout(function () { document.body.classList.add(c); }, steps[i]));
			});
		}
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
	window.addEventListener('resize', size);
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) stopLoop(); else startLoop();
	});
	if (window.IntersectionObserver) {
		new IntersectionObserver(function (entries) {
			heroOnScreen = entries[0].isIntersecting;
		}, { threshold: 0 }).observe(hero);
	}

	var forceFull = /(^|[?&])intro=1(&|$)/.test(location.search);
	var seen = false;
	try { seen = !!sessionStorage.getItem('djdHeroSeen'); } catch (e) { /* storage blocked */ }
	try { sessionStorage.setItem('djdHeroSeen', '1'); } catch (e) { /* ignore */ }
	var quick = seen && !forceFull;

	var firstPhoto = imgs[cur];
	var photoReady = (firstPhoto && firstPhoto.decode) ? firstPhoto.decode().catch(function () {}) : Promise.resolve();
	var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready.catch(function () {}) : Promise.resolve();
	var timeout = new Promise(function (resolve) { setTimeout(resolve, 1200); });
	Promise.race([Promise.all([photoReady, fontsReady]), timeout]).then(function () {
		play(quick);
		startLoop();
	});
})();
