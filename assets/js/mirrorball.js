/* The mirror ball hero: a port of drawBall(), cluster() and the speck math
   from reference/mirrorball-hero-reference.html (the originally approved
   demo, v1.11.0), classes renamed to this theme's own djd- prefix (see
   CLAUDE.md, "Mirror ball hero"). The entrance timeline
   and the landing flourish below are v1.12.0's own numbers, not
   the reference's: Mark reviewed and confirmed this exact timeline live on
   Home v2, see CLAUDE.md for the full detail. drawBall()/cluster()
   themselves are unchanged from the v1.11.0 port.

   v1.12.0 removed the v1.11.0 "quick repeat visit" path (sessionStorage,
   djd-ball-noanim). v1.15.2 brings a skip back, Mark's rule: the full
   entrance plays at most once per 15 minutes. The inline wp_head script
   (djdigi-theme.php) decides it and adds html.ddm-intro-seen; then play()
   starts with every step already finished (see skipIntro below) and
   hero.css shows the settled hero from the first paint. ?intro=1 always
   plays it.

   v1.14.0: the ball itself is drawn in 3D (mirrorball-3d.js, three.js)
   once that module has loaded, after the page has; drawBall() below is
   what shows until then, and the fallback for good if WebGL is missing,
   the module fails to load, or the WebGL context is lost. Everything
   else here (position, size, drop, wire, spotlight, rot and flash)
   is unchanged and drives both. The 3D ball is the hero ball only.

   v1.16.0: two balls, and no docking any more. The logo is in the menu
   pill on every page at all times, with its own small ball (the "pill
   ball", the 2D drawBall() on #djd-pill-ball, see pillFrame()). The hero
   ball stays in the hero logo and scrolls away with it. The v1.12.0
   docking (q, the slot growing with scroll, the logo crossfade) is gone;
   the slot is always open in CSS (site.css). Inner pages (no .djd-hero)
   only run the pill ball. The 3D ball (three.js) is the hero ball's only:
   the pill ball is about 6px across (radius 9.6% of a 30px wide logo),
   too small for the 3D tiles to show, and it would cost a second WebGL
   context on the homepage and the 670 KB three.js on every inner page.

   No prefers-reduced-motion branch anywhere in this file: keeping the
   sitewide "motion always on" policy intact (CLAUDE.md, "Motion: always
   on, by design") rather than carving out an exception for this feature. */
(function () {
	'use strict';

	var hero = document.querySelector('.djd-hero');

	var cfg = window.djdtMirrorBall || {};
	var BALL_X = typeof cfg.ballX === 'number' ? cfg.ballX : 86;
	var BALL_Y = typeof cfg.ballY === 'number' ? cfg.ballY : 13.6;
	var BALL_R = typeof cfg.ballR === 'number' ? cfg.ballR : 9.2;

	var fx = hero ? hero.querySelector('.djd-hero__fx') : null;
	var dark = hero ? hero.querySelector('.djd-hero__dark') : null;
	var big = hero ? hero.querySelector('.djd-hero__logo') : null;
	// The Avada header's own logo image and the header row itself, outside
	// the hero entirely (the reference's "mini"): the pill logo. Not every
	// page this script loads on necessarily has one (a test harness, a
	// future Avada change), so every use of either below is null-guarded
	// rather than assumed.
	//
	// v1.13.0: the standard logo img specifically (site.css hides the
	// sticky and mobile copies on hero pages), and the logo now lives in a
	// slot on the left end of the menu pill, not at the left gutter: see
	// placeLogo() below.
	var mini = document.querySelector('.fusion-logo img.fusion-standard-logo') || document.querySelector('.fusion-logo img');
	var logoBox = mini ? mini.closest('.fusion-logo') : null;
	var logoRow = logoBox ? logoBox.parentElement : null;
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
	if (!(hero && fx && dark && big && wire && bc)) hero = null;
	if (!hero && !mini) return;
	// v1.15.2: the intro already played in the last 15 minutes.
	var skipIntro = !!hero && document.documentElement.classList.contains('ddm-intro-seen');

	// The pill ball's own canvas (v1.16.0), made here on every page.
	var pc = null;
	if (mini) {
		pc = document.createElement('canvas');
		pc.id = 'djd-pill-ball';
		pc.setAttribute('aria-hidden', 'true');
		document.body.appendChild(pc);
	}

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

	// The logo slot (v1.13.0; always open since v1.16.0): the pill's left
	// padding is set in CSS (site.css, 52px desktop, 62px phone), and the
	// logo is moved so its right edge sits SLOT_GAP px short of where the
	// menu's first item starts. The pill is whichever of Avada's desktop
	// menu or classic mobile button is actually showing at this width.
	var SLOT_GAP = 8;
	function activePill() {
		if (desktopPill && desktopPill.offsetWidth > 0) return desktopPill;
		var mp = findMobilePill();
		if (mp && mp.offsetWidth > 0) return mp;
		return null;
	}
	// Places the logo in the slot and shows it. Returns false while there
	// is no visible pill to place it in (the logo then stays hidden).
	function placeLogo() {
		if (!mini || !logoBox || !logoRow) return false;
		var pill = activePill();
		if (!pill) { mini.style.opacity = 0; return false; }
		var cs = getComputedStyle(pill);
		var pad = parseFloat(cs.paddingLeft) || 0;
		var border = parseFloat(cs.borderLeftWidth) || 0;
		var pr = pill.getBoundingClientRect(), rr = logoRow.getBoundingClientRect();
		var x = pr.left + border + pad - SLOT_GAP - mini.offsetWidth - rr.left;
		var y = pr.top + (pr.height - mini.offsetHeight) / 2 - rr.top;
		logoBox.style.transform = 'translate(' + x.toFixed(2) + 'px,' + y.toFixed(2) + 'px)';
		logoBox.style.pointerEvents = 'auto';
		mini.style.opacity = 1;
		return true;
	}
	// Right away too, not only from the first animation frame, so the logo
	// is in the pill as early as this script runs.
	placeLogo();

	var fc = fx ? fx.getContext('2d') : null;
	var bx = hero ? bc.getContext('2d') : null;
	var px2 = pc ? pc.getContext('2d') : null;

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
	var imgs = hero ? Array.prototype.slice.call(hero.querySelectorAll('.djd-hero__photo')) : [];
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

	// The intro timeline (v1.16.5: about 30% faster than v1.12.0's, Mark's
	// numbers, same choreography). Every time in frame() and play() reads
	// these, so the overlay, spotlight, wire, specks and logo stay in step.
	var T = {
		drop: 250,        // black until here, then the ball starts dropping (was 400)
		land: 1550,       // the ball lands: flash and glow ring (was 2200)
		burst: 1750,      // the specks burst out, photo swaps allowed from here (was 2500)
		burstDur: 630,    // the burst's fly out (was 900)
		overlay: 1700,    // the black overlay starts fading (was 2450)
		overlayDur: 490,  // (was 700)
		logo: 1800,       // the logo fades in, djd-seq-1 (was 2550)
		logoDur: 420,     // (was 600)
		wireOut: 2050,    // the wire starts fading (was 2900)
		wireDur: 315,     // (was 450)
		seq: [1800, 1975, 2150, 2360], // djd-seq-1..4: logo, -, slogan/styles/button, - (was 2550, 2800, 3050, 3350)
		firstSwap: 5700   // the first photo change (was 6600, the same gap after the slogan)
	};

	function clamp(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }
	function eOut(t) { return 1 - Math.pow(1 - t, 3); }
	function eIO(t) { return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
	function eBack(t, c1) { var c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); }
	// How much of the black intro overlay is left, 1 (full) down to 0: both
	// the overlay itself and the spotlight (v1.12.0, "removed with it") read
	// this one curve, so they always finish fading together.
	function overlayFade(t) { return t < T.overlay ? 1 : clamp(1 - (t - T.overlay) / T.overlayDur); }

	function size() {
		if (!hero) { W = window.innerWidth; H = window.innerHeight; return; }
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
		return { x: r.left + (BALL_X / 100) * r.width, y: r.top + (BALL_Y / 100) * r.width, r: (BALL_R / 100) * r.width, rect: r };
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

	// v1.16.0: draws on canvas cv (2D context bx), shared by the hero ball
	// and the pill ball; fl is that ball's own flash.
	function drawBall(cv, bx, r, lx, ly, now, fl) {
		var pad = 3, sz = Math.ceil(r * 2 + pad * 2), px = Math.round(sz * DPR);
		if (cv.width !== px) { cv.width = px; cv.height = px; cv.style.width = sz + 'px'; cv.style.height = sz + 'px'; }
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
				var v = (.12 + .6 * Math.pow(lam, 2.2) + sp * .9 + fl * (.4 + .6 * h)) * (.35 + .65 * nz);
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

	// v1.16.5: draws on ctx (the photo's fx canvas, or the logo's, below),
	// always in hero coordinates, so a speck crossing onto the logo keeps
	// its exact dot grid on both canvases.
	function clusterOn(cx, x, y, R, al, col, cap) {
		if (al < .02 || R < 1) return;
		var g = cx.createRadialGradient(x * DPR, y * DPR, 0, x * DPR, y * DPR, R * .9 * DPR);
		g.addColorStop(0, 'rgba(' + col + ',' + Math.min(cap, al * .45) + ')'); g.addColorStop(1, 'rgba(' + col + ',0)');
		cx.fillStyle = g; cx.beginPath(); cx.arc(x * DPR, y * DPR, R * .9 * DPR, 0, 6.2832); cx.fill();
		var u0 = x * ca + y * sa, v0 = -x * sa + y * ca, k = Math.ceil(R / SP), ub = Math.round(u0 / SP), vb = Math.round(v0 / SP);
		cx.fillStyle = 'rgb(' + col + ')';
		for (var i = -k; i <= k; i++) for (var j = -k; j <= k; j++) {
			var u = (ub + i) * SP, v = (vb + j) * SP, du = u - u0, dv = v - v0, d = Math.sqrt(du * du + dv * dv);
			if (d > R) continue;
			var f = 1 - d / R, px = u * ca - v * sa, py = u * sa + v * ca;
			cx.globalAlpha = Math.min(cap, al * f * 1.5); cx.beginPath(); cx.arc(px * DPR, py * DPR, (.5 + 1.7 * f) * DPR, 0, 6.2832); cx.fill();
		}
		cx.globalAlpha = 1;
	}
	// A speck of light: on the photo, and (v1.16.5) again on the logo when
	// it reaches the logo's box. There it is 70% as bright (LOGO_AL) and no
	// dot goes past 60% opacity (LOGO_CAP), so the cyan lettering never
	// gets a hard white hot spot, even in the landing burst.
	var LOGO_AL = .7, LOGO_CAP = .6;
	function cluster(x, y, R, al, col) {
		clusterOn(fc, x, y, R, al, col, 1);
		if (lb && x + R > lb.x && x - R < lb.x + lb.w && y + R > lb.y && y - R < lb.y + lb.h) {
			clusterOn(lc, x, y, R, al * LOGO_AL, col, LOGO_CAP);
			lbUsed = true;
		}
	}

	// v1.16.5: light over the logo. The specks canvas (.djd-hero__fx) sits
	// under .djd-hero__inner, so it only lit the photo. This second canvas
	// sits over the logo's box only, inside the inner block (above the logo,
	// below nothing else: the slogan, styles line and button never overlap
	// the logo), screen blended like the first. Each frame it gets just the
	// specks that touch the logo's box (cluster() above, no extra specks),
	// then is cut to the logo's own shape (destination-in with the logo
	// image), so only the lettering and the ball brighten, never the
	// transparent space around them.
	var lfx = null, lc = null, lb = null, lbUsed = false, lbDrawn = false;
	// The logo as a bitmap at the canvas's own size, made once per size:
	// cutting with the <img> itself re-rasterized the SVG every frame,
	// which measured as a real frame time cost.
	var mask = null, maskKey = '', lfxT = '', lfxO = '';
	if (hero && big && big.parentNode) {
		lfx = document.createElement('canvas');
		lfx.className = 'djd-hero__fx-logo';
		lfx.setAttribute('aria-hidden', 'true');
		big.parentNode.insertBefore(lfx, big.nextSibling);
		lc = lfx.getContext('2d');
	}
	function clearLogoFx() {
		if (lbDrawn) { lc.setTransform(1, 0, 0, 1, 0, 0); lc.clearRect(0, 0, lfx.width, lfx.height); lbDrawn = false; }
	}
	// Before the specks are drawn: the logo's box in hero coordinates, the
	// canvas moved and sized onto it, set up for hero coordinates.
	function logoFxBegin(hr, sb) {
		lb = null; lbUsed = false;
		if (!lc) return;
		var op = big.style.opacity;
		if (!(big.complete && big.naturalWidth) || op === '0') { clearLogoFx(); return; }
		var r = sb.rect, pr = lfx.parentNode.getBoundingClientRect();
		if (r.width < 2 || r.height < 2) return;
		var w = Math.round(r.width * DPR), h = Math.round(r.height * DPR);
		if (lfx.width !== w || lfx.height !== h) { lfx.width = w; lfx.height = h; lfx.style.width = r.width + 'px'; lfx.style.height = r.height + 'px'; lbDrawn = false; }
		var tf = 'translate(' + (r.left - pr.left).toFixed(1) + 'px,' + (r.top - pr.top).toFixed(1) + 'px)';
		if (tf !== lfxT) { lfx.style.transform = tf; lfxT = tf; }
		if (op !== lfxO) { lfx.style.opacity = op; lfxO = op; }
		lb = { x: r.left - hr.left, y: r.top - hr.top, w: r.width, h: r.height };
		clearLogoFx();
		lc.setTransform(1, 0, 0, 1, -lb.x * DPR, -lb.y * DPR);
	}
	// After: keep only what falls on the logo's own pixels.
	function logoFxEnd() {
		if (!lb || !lbUsed) return;
		var key = lfx.width + 'x' + lfx.height + ' ' + big.currentSrc;
		if (key !== maskKey) {
			mask = mask || document.createElement('canvas');
			mask.width = lfx.width; mask.height = lfx.height;
			var mx = mask.getContext('2d');
			mx.clearRect(0, 0, mask.width, mask.height);
			mx.drawImage(big, 0, 0, mask.width, mask.height);
			maskKey = key;
		}
		lc.setTransform(1, 0, 0, 1, 0, 0);
		lc.globalCompositeOperation = 'destination-in';
		lc.drawImage(mask, 0, 0);
		lc.globalCompositeOperation = 'source-over';
		lbDrawn = true;
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

	// The hero ball at (x, y), radius r: the 3D ball once loaded, else the
	// 2D drawBall(). Hidden (visible false) before the drop starts and
	// while the hero is off screen.
	function paintBall(x, y, r, now, visible) {
		bc.style.opacity = visible ? 1 : 0;
		var shown3d = false;
		if (ball3d) {
			// Rendered only while the ball is actually visible.
			if (visible) {
				try {
					shown3d = ball3d.render({ x: x, y: y, r: r, rot: rot, flash: flash, now: now });
				} catch (e) {
					drop3d();
				}
			}
			if (c3) c3.style.opacity = shown3d ? 1 : 0;
		}
		if (shown3d) {
			bc.style.opacity = 0;
		} else {
			var lx = (mouse.x - x) / Math.max(W, 1), ly = (mouse.y - y) / Math.max(H, 1);
			if (mouse.x < -999) { lx = -.4; ly = -.5; }
			if (!visible) return;
			var sz = drawBall(bc, bx, r, lx * 2, ly * 2, now, flash);
			bc.style.transform = 'translate(' + (x - sz / 2) + 'px,' + (y - sz / 2) + 'px)';
		}
	}

	// The pill ball (v1.16.0): the logo in its slot and its own small 2D
	// ball in the logo's dot, turning with the same rot as the hero ball.
	// No flash, that belongs to the hero's landing and photo changes.
	function pillFrame(now) {
		if (!pc) return;
		if (!placeLogo()) { pc.style.opacity = 0; return; }
		var s = slot(mini);
		var lx = (mouse.x - s.x) / Math.max(window.innerWidth, 1), ly = (mouse.y - s.y) / Math.max(window.innerHeight, 1);
		if (mouse.x < -999) { lx = -.4; ly = -.5; }
		var sz = drawBall(pc, px2, s.r, lx * 2, ly * 2, now, 0);
		pc.style.transform = 'translate(' + (s.x - sz / 2) + 'px,' + (s.y - sz / 2) + 'px)';
		pc.style.opacity = 1;
	}

	function frame(now) {
		if (!running) return;
		var dt = Math.min(64, now - last); last = now;
		var t = now - start;
		rot += dt * .0002; flash = Math.max(0, flash - dt / 900);
		pillFrame(now);
		if (!hero) { requestAnimationFrame(frame); return; }
		var hr = hero.getBoundingClientRect();

		// v1.16.0: no docking. The hero ball stays in the hero logo's slot
		// and scrolls away with it. This script owns the logo's opacity for
		// its entrance fade (the same 600ms window as the djd-seq-1
		// transform transition, hero.css).
		var sb = slot(big);
		big.style.opacity = t < T.logo ? 0 : clamp((t - T.logo) / T.logoDur);

		var tx = sb.x, ty = sb.y, tr = sb.r, by = ty;
		if (t < T.land) {
			var y0 = -tr * 2.2, dp = clamp((t - T.drop) / (T.land - T.drop));
			by = y0 + (ty - y0) * eBack(dp, 0.9);
		} else {
			by = ty;
			if (!landed) { landed = true; flash = 1; ring = { t0: now }; }
		}
		paintBall(tx, by, tr, now, t >= T.drop && heroOnScreen);
		var wa = t < T.drop ? 0 : t < T.wireOut ? 1 : clamp(1 - (t - T.wireOut) / T.wireDur);
		wire.style.opacity = wa; wire.style.left = tx + 'px'; wire.style.height = Math.max(0, by - tr) + 'px';
		dark.style.opacity = overlayFade(t);

		if (heroOnScreen) {
			fc.clearRect(0, 0, fx.width, fx.height);
			logoFxBegin(hr, sb);
			var ox = tx - hr.left, oy = by - hr.top, mx = mouse.x - hr.left, my = mouse.y - hr.top;

			// Spotlight: a soft pool of light above the black overlay,
			// following the ball, v1.12.0. 12% alpha through the drop,
			// swelling to 20% during the hang (T.land to T.burst), then fading
			// out together with the overlay itself (overlayFade above).
			if (t >= T.drop) {
				var spotBase = t < T.land ? 0.12 : (t < T.burst ? 0.12 + 0.08 * clamp((t - T.land) / (T.burst - T.land)) : 0.20);
				softGlow(ox, oy, tr * 3, spotBase * overlayFade(t));
			}

			if (t >= T.burst) {
				// Burst: specks start oversized and overbright, easing down
				// to their normal size/alpha over the same time they fly
				// out over (v1.12.0, was flat from the start).
				var bp = eOut(clamp((t - T.burst) / T.burstDur));
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

			if (!trans && t > T.burst && now > nextAt && imgs.length > 1) {
				var to = (cur + 1) % imgs.length;
				if (!photoLoaded(imgs[to])) {
					// v1.16.5: the next photo loads after the first is
					// showing (loadRest() below); the swap waits for it.
					loadRest();
					nextAt = now + 500;
				} else {
					trans = { t0: now, from: cur, to: to };
					imgs[to].classList.add('is-next');
					imgs[to].style.clipPath = 'circle(0px at ' + ox + 'px ' + oy + 'px)';
					flash = 1;
					if (ball3d) ball3d.setEnv(imgs[to]);
				}
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
			logoFxEnd();
		}
		requestAnimationFrame(frame);
	}

	/**
	 * Schedules the whole entrance (v1.15.2: unless skipIntro): the drop,
	 * the hang and the landing flourish all
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
		var seq = ['djd-seq-1', 'djd-seq-2', 'djd-seq-3', 'djd-seq-4'];
		if (skipIntro) {
			// Settled from the first frame: start far enough back that every
			// `t < ...` step in frame() has finished (overlay gone, ball at
			// its slot, wire hidden, specks out, logo shown), no landing
			// flash or ring (landed already), every djd-seq class on now,
			// and the photos rotate on their normal 6s rhythm from here.
			start = nowStart - 60000;
			landed = true;
			nextAt = nowStart + 6000;
			seq.forEach(function (c) { document.body.classList.add(c); });
			return;
		}
		start = nowStart;
		nextAt = nowStart + T.firstSwap;
		seq.forEach(function (c, i) {
			timers.push(setTimeout(function () { document.body.classList.add(c); }, T.seq[i]));
		});
	}

	// v1.16.5: the other hero photos (data-src / data-srcset from PHP,
	// djdt_hero_photo_html()) are only requested once the first one has
	// loaded, so the first photo, and the rest of the page, are not
	// competing with three more full screen photos for the connection.
	function photoLoaded(img) { return !!img && !img.hasAttribute('data-src') && img.complete && img.naturalWidth > 0; }
	var restLoaded = false;
	function loadRest() {
		if (restLoaded) return;
		restLoaded = true;
		imgs.forEach(function (img) {
			if (!img.hasAttribute('data-src')) return;
			var pic = img.parentNode && img.parentNode.tagName === 'PICTURE' ? img.parentNode : null;
			if (pic) Array.prototype.forEach.call(pic.querySelectorAll('source[data-srcset]'), function (s) { s.srcset = s.getAttribute('data-srcset'); s.removeAttribute('data-srcset'); });
			if (img.hasAttribute('data-srcset')) { img.srcset = img.getAttribute('data-srcset'); img.removeAttribute('data-srcset'); }
			img.src = img.getAttribute('data-src');
			img.removeAttribute('data-src');
		});
	}
	function afterFirstPhoto() {
		var f = imgs[cur];
		if (!f || photoLoaded(f)) { loadRest(); return; }
		f.addEventListener('load', loadRest, { once: true });
		f.addEventListener('error', loadRest, { once: true });
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
	window.addEventListener('resize', function () { size(); placeLogo(); });
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) stopLoop(); else startLoop();
	});
	if (hero && window.IntersectionObserver) {
		new IntersectionObserver(function (entries) {
			heroOnScreen = entries[0].isIntersecting;
		}, { threshold: 0 }).observe(hero);
	}

	// Loads the 3D ball after the page has finished loading, on idle, as an
	// ES module (dynamic import(), cfg.ball3dUrl from PHP, which itself
	// imports the bundled three.js). Any failure (no WebGL, the module or
	// three.js not loading, a lost context) leaves the 2D ball in place.
	function load3d() {
		if (!hero) return;
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

	if (!hero) {
		size();
		startLoop();
		return;
	}

	afterFirstPhoto();

	// Nothing to time when the intro is skipped: start right away.
	if (skipIntro) {
		play();
		startLoop();
		return;
	}

	var firstPhoto = imgs[cur];
	var photoReady = (firstPhoto && firstPhoto.decode) ? firstPhoto.decode().catch(function () {}) : Promise.resolve();
	var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready.catch(function () {}) : Promise.resolve();
	var timeout = new Promise(function (resolve) { setTimeout(resolve, 1200); });
	Promise.race([Promise.all([photoReady, fontsReady]), timeout]).then(function () {
		play();
		startLoop();
	});
})();
