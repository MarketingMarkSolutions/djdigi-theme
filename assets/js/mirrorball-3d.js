/* The 3D mirror ball (v1.14.0): three.js r160 (assets/vendor/three/,
   bundled, never a CDN), a port of the look in
   reference/mirrorball-3d-reference.js, the demo Mark approved live on
   Home v2, with its final tuned values. Every look value below (tiles,
   material, lights, environment, sparkles, flash) is the reference's own;
   check against that file before changing one. That demo's own "follow the
   2D canvas" wiring is not ported: mirrorball.js still owns every
   position, size, timing and handoff number and simply calls render() each
   frame with the ball's center, radius and its own shared rot and flash, so
   the 3D ball spins with the specks (the reference's own spin rate was that
   same rot rate) and flashes on landing and on every photo change.

   Loaded by mirrorball.js with a dynamic import() after the page has
   finished loading (on idle), so it never delays the page appearing; the
   2D ball draws until this is ready. createBall() throws if WebGL is not
   available, and onLost fires if the context is lost later; either way
   mirrorball.js goes back to the 2D drawBall().

   One renderer, device pixel ratio capped at 2. The canvas is the ball's
   own diameter plus 18% of it on each side (the reference's own padding),
   so sparkles reaching past the ball's edge are never clipped. The drawing
   buffer is resized only in steps (BUF_STEP) and CSS scaled to the exact
   size in between, since the radius changes every frame while docking and
   a WebGL resize per frame would reallocate the buffer every frame. */
import * as THREE from '../vendor/three/three.module.min.js';

var BUF_STEP = 16;
var PAD = 0.18;              // canvas padding on each side, fraction of the ball's diameter
var HALF = 1 + 2 * PAD;      // the orthographic camera's half extent, in ball radii

// The reference's sparkle texture: a soft dot plus a thin cross.
function sparkleTexture() {
	var sc = document.createElement('canvas');
	sc.width = sc.height = 128;
	var x = sc.getContext('2d');
	var g = x.createRadialGradient(64, 64, 0, 64, 64, 64);
	g.addColorStop(0, 'rgba(255,255,255,1)'); g.addColorStop(0.15, 'rgba(255,255,255,.6)'); g.addColorStop(1, 'rgba(255,255,255,0)');
	x.fillStyle = g; x.fillRect(0, 0, 128, 128);
	x.globalCompositeOperation = 'lighter';
	var lg = x.createLinearGradient(64, 0, 64, 128);
	lg.addColorStop(0, 'rgba(255,255,255,0)'); lg.addColorStop(0.5, 'rgba(255,255,255,1)'); lg.addColorStop(1, 'rgba(255,255,255,0)');
	x.fillStyle = lg; x.fillRect(62, 0, 4, 128);
	lg = x.createLinearGradient(0, 64, 128, 64);
	lg.addColorStop(0, 'rgba(255,255,255,0)'); lg.addColorStop(0.5, 'rgba(255,255,255,1)'); lg.addColorStop(1, 'rgba(255,255,255,0)');
	x.fillStyle = lg; x.fillRect(0, 62, 128, 4);
	return new THREE.CanvasTexture(sc);
}

/**
 * Builds the ball on `canvas`. Throws if WebGL is unavailable.
 * opts.onLost: called once if the WebGL context is lost.
 * Returns { render(state), setEnv(img), dispose() }.
 */
export function createBall(canvas, opts) {
	opts = opts || {};
	// Checked quietly first: three.js itself logs console errors when it
	// cannot get a context, and a browser without WebGL is an expected
	// case here (mirrorball.js keeps the 2D ball), not an error.
	var probe = document.createElement('canvas');
	var gl = probe.getContext('webgl2') || probe.getContext('webgl');
	if (!gl) throw new Error('no-webgl');
	var lose = gl.getExtension('WEBGL_lose_context');
	if (lose) lose.loseContext();

	var renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
	renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
	renderer.outputColorSpace = THREE.SRGBColorSpace;
	renderer.toneMapping = THREE.ACESFilmicToneMapping;
	renderer.toneMappingExposure = 1.0;

	var dead = false;
	canvas.addEventListener('webglcontextlost', function (e) {
		e.preventDefault();
		if (dead) return;
		dead = true;
		if (opts.onLost) opts.onLost();
	});

	var scene = new THREE.Scene();
	var camera = new THREE.OrthographicCamera(-HALF, HALF, HALF, -HALF, 0.1, 10);
	camera.position.z = 5;

	// The ball sits tilted toward the viewer; only the inner group spins.
	var ball = new THREE.Group();
	ball.rotation.x = 0.3;
	ball.rotation.z = -0.12;
	scene.add(ball);
	var spin = new THREE.Group();
	ball.add(spin);

	// Dark grout core.
	spin.add(new THREE.Mesh(new THREE.SphereGeometry(0.975, 48, 32), new THREE.MeshBasicMaterial({ color: 0x05031a })));

	// Mirror tiles: 24 latitude bands, tile count per band proportional to
	// cos(lat), alternate bands offset by half a tile, tiles at 84% size so
	// the grout shows, a slight random tilt per tile and a slight random
	// gray per tile for sparkle variety.
	var N = 24, tiles = [];
	for (var i = 0; i < N; i++) {
		var lat = -Math.PI / 2 + (i + 0.5) * Math.PI / N;
		var cnt = Math.max(1, Math.round(2 * N * Math.cos(lat)));
		for (var j = 0; j < cnt; j++) {
			tiles.push([lat, j * 2 * Math.PI / cnt + (i % 2) * Math.PI / cnt, 2 * Math.PI * Math.cos(lat) / cnt * 0.84, Math.PI / N * 0.84]);
		}
	}
	var mat = new THREE.MeshStandardMaterial({ metalness: 1, roughness: 0.03, envMapIntensity: 1.2, color: 0xffffff });
	var im = new THREE.InstancedMesh(new THREE.PlaneGeometry(1, 1), mat, tiles.length);
	var d = new THREE.Object3D(), col = new THREE.Color();
	tiles.forEach(function (t, k) {
		var la = t[0], lo = t[1];
		var p = new THREE.Vector3(Math.cos(la) * Math.sin(lo), Math.sin(la), Math.cos(la) * Math.cos(lo));
		d.position.copy(p);
		d.lookAt(p.clone().multiplyScalar(2));
		d.rotateX((Math.random() - 0.5) * 0.14);
		d.rotateY((Math.random() - 0.5) * 0.14);
		d.scale.set(t[2], t[3], 1);
		d.updateMatrix();
		im.setMatrixAt(k, d.matrix);
		var v = 0.82 + Math.random() * 0.18;
		col.setRGB(v, v, v);
		im.setColorAt(k, col);
	});
	spin.add(im);

	// Lights.
	var key = new THREE.DirectionalLight(0xffffff, 3.2); key.position.set(-2, 2.6, 3); scene.add(key);
	var key2 = new THREE.DirectionalLight(0xf1ece2, 2.2); key2.position.set(2.5, 1.5, 2); scene.add(key2);
	var vio = new THREE.PointLight(0x9a6cff, 25, 12); vio.position.set(2.4, -0.8, 2.2); scene.add(vio);
	var cya = new THREE.PointLight(0x24a9e0, 25, 12); cya.position.set(-2.4, -1.4, 1.8); scene.add(cya);
	scene.add(new THREE.HemisphereLight(0xb9c7ff, 0x1a0b3a, 0.5));

	// Star sparkles: 4 additive sprites fading in and out (sin envelope,
	// 700 to 1400ms) at random spots on the lit upper left of the ball, 0.8
	// to 3.4s apart, rotating slightly while visible.
	var tex = sparkleTexture();
	var sps = [];
	for (var s = 0; s < 4; s++) {
		var sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, blending: THREE.AdditiveBlending, transparent: true, depthTest: false, opacity: 0, color: s % 2 ? 0xbfe9ff : 0xffffff }));
		sp.scale.set(0.55, 0.55, 1);
		sp.position.z = 2;
		scene.add(sp);
		sps.push({ s: sp, t: Math.random() * 3000, dur: 700 + Math.random() * 700 });
	}

	// Environment: the active hero photo through PMREM, set straight on the
	// mirror material, loaded by URL with TextureLoader as in the reference
	// (already in the browser cache). Not the page's own <img> element: for
	// an attached <img> three.js reads its laid out CSS size, not its
	// pixel size, which uploads a broken texture (found in the harness: a
	// black ball and "Offset overflows texture dimensions").
	var pm = new THREE.PMREMGenerator(renderer);
	var envRT = null, envWant = '';
	function setEnv(img) {
		var url = img && (img.currentSrc || img.src);
		if (dead || !url) return;
		envWant = url;
		new THREE.TextureLoader().load(url, function (tx) {
			if (dead || url !== envWant) { tx.dispose(); return; }
			tx.mapping = THREE.EquirectangularReflectionMapping;
			tx.colorSpace = THREE.SRGBColorSpace;
			var rt = pm.fromEquirectangular(tx);
			if (envRT) envRT.dispose();
			envRT = rt;
			mat.envMap = rt.texture;
			mat.needsUpdate = true;
			tx.dispose();
		});
	}

	var bufCss = 0, last = 0;
	function render(st) {
		if (dead) return false;
		var dt = last ? Math.min(64, st.now - last) : 0;
		last = st.now;

		var side = 2 * st.r * HALF;
		var want = Math.max(BUF_STEP, Math.ceil(side / BUF_STEP) * BUF_STEP);
		if (want !== bufCss) {
			bufCss = want;
			renderer.setSize(want, want, false);
			canvas.style.width = want + 'px';
			canvas.style.height = want + 'px';
		}
		canvas.style.transform = 'translate(' + (st.x - side / 2).toFixed(2) + 'px,' + (st.y - side / 2).toFixed(2) + 'px) scale(' + (side / want).toFixed(4) + ')';

		spin.rotation.y = st.rot;

		sps.forEach(function (o) {
			o.t -= dt;
			if (o.t < 0) {
				if (o.t < -o.dur) {
					o.t = 800 + Math.random() * 2600;
					var a = Math.random() * 6.28, r = Math.sqrt(Math.random()) * 0.75;
					o.s.position.x = -0.25 + Math.cos(a) * r * 0.8;
					o.s.position.y = 0.15 + Math.sin(a) * r * 0.8;
					o.s.material.opacity = 0;
				} else {
					var k = -o.t / o.dur;
					o.s.material.opacity = Math.sin(k * Math.PI);
					o.s.material.rotation = k * 0.6;
				}
			}
		});

		mat.envMapIntensity = 1.2 + st.flash * 3;
		key.intensity = 3.2 + st.flash * 8;
		renderer.render(scene, camera);
		return true;
	}

	function dispose() {
		dead = true;
		if (envRT) envRT.dispose();
		pm.dispose();
		renderer.dispose();
	}

	return { render: render, setEnv: setEnv, dispose: dispose };
}
