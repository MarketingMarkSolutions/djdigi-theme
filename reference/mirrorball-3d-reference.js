// Mirror ball 3D reference (approved by Mark in the live demo on Home v2, 2026-10-25).
// This is the demo code as it ran: it drew a three.js ball on top of the
// existing 2D ball canvas (#djd-ball-canvas), copying that canvas's position,
// size and opacity every frame, so the drop, docking and pill hide/show logic
// stayed in mirrorball.js. Port the look (tiles, material, lights, sparkles,
// flash) into the plugin properly; the "follow the 2D canvas" trick is only how
// the demo was wired.
//
// Final tuned values (applied live after the first pass):
//   roughness 0.03, toneMappingExposure 1.0, violet/cyan point lights 25,
//   key2 2.2, envMapIntensity 1.2 (+3 during flash), key 3.2 (+8 during flash)

const T = await import('https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js');

const src = document.getElementById('djd-ball-canvas');
const cv = document.createElement('canvas');
cv.style.cssText = 'position:fixed;left:0;top:0;pointer-events:none;z-index:' + (parseInt(getComputedStyle(src).zIndex) || 100000) + ';';
document.body.appendChild(cv);

const R = new T.WebGLRenderer({ canvas: cv, alpha: true, antialias: true });
R.setPixelRatio(Math.min(devicePixelRatio, 2));
R.outputColorSpace = T.SRGBColorSpace;
R.toneMapping = T.ACESFilmicToneMapping;
R.toneMappingExposure = 1.0;

const S = new T.Scene();
const cam = new T.OrthographicCamera(-1, 1, 1, -1, 0.1, 10);
cam.position.z = 5;

const ball = new T.Group();
ball.rotation.x = 0.3;
ball.rotation.z = -0.12;
S.add(ball);
const spin = new T.Group();
ball.add(spin);

// Dark grout core
spin.add(new T.Mesh(new T.SphereGeometry(0.975, 48, 32), new T.MeshBasicMaterial({ color: 0x05031a })));

// Mirror tiles: 24 latitude bands, tile count per band proportional to cos(lat),
// alternate bands offset by half a tile, tiles at 84% size so grout shows.
const N = 24, tiles = [];
for (let i = 0; i < N; i++) {
	const lat = -Math.PI / 2 + (i + 0.5) * Math.PI / N;
	const cnt = Math.max(1, Math.round(2 * N * Math.cos(lat)));
	for (let j = 0; j < cnt; j++) {
		tiles.push([lat, j * 2 * Math.PI / cnt + (i % 2) * Math.PI / cnt, 2 * Math.PI * Math.cos(lat) / cnt * 0.84, Math.PI / N * 0.84]);
	}
}
const mat = new T.MeshStandardMaterial({ metalness: 1, roughness: 0.03, envMapIntensity: 1.2, color: 0xffffff });
const im = new T.InstancedMesh(new T.PlaneGeometry(1, 1), mat, tiles.length);
const d = new T.Object3D(), col = new T.Color();
tiles.forEach((t, k) => {
	const [la, lo, w, h] = t;
	const p = new T.Vector3(Math.cos(la) * Math.sin(lo), Math.sin(la), Math.cos(la) * Math.cos(lo));
	d.position.copy(p);
	d.lookAt(p.clone().multiplyScalar(2));
	d.rotateX((Math.random() - 0.5) * 0.14); // slight random tilt per tile = sparkle variety
	d.rotateY((Math.random() - 0.5) * 0.14);
	d.scale.set(w, h, 1);
	d.updateMatrix();
	im.setMatrixAt(k, d.matrix);
	const v = 0.82 + Math.random() * 0.18;
	col.setRGB(v, v, v);
	im.setColorAt(k, col);
});
spin.add(im);

// Lights
const key = new T.DirectionalLight(0xffffff, 3.2); key.position.set(-2, 2.6, 3); S.add(key);
const key2 = new T.DirectionalLight(0xf1ece2, 2.2); key2.position.set(2.5, 1.5, 2); S.add(key2);
const vio = new T.PointLight(0x9a6cff, 25, 12); vio.position.set(2.4, -0.8, 2.2); S.add(vio);
const cya = new T.PointLight(0x24a9e0, 25, 12); cya.position.set(-2.4, -1.4, 1.8); S.add(cya);
S.add(new T.HemisphereLight(0xb9c7ff, 0x1a0b3a, 0.5));

// Environment = the active hero photo (halftone bake), refreshed when the photo changes.
const pm = new T.PMREMGenerator(R);
let envRT = null;
function setEnv(url) {
	new T.TextureLoader().load(url, tx => {
		tx.mapping = T.EquirectangularReflectionMapping;
		tx.colorSpace = T.SRGBColorSpace;
		const rt = pm.fromEquirectangular(tx);
		if (envRT) envRT.dispose();
		envRT = rt;
		mat.envMap = rt.texture;
		mat.needsUpdate = true;
		tx.dispose();
	});
}
const act = () => document.querySelector('.djd-hero__photo-frame.is-active img') || document.querySelector('.djd-hero__photo');
let curSrc = '', flash = 0;
new MutationObserver(() => {
	const a = act();
	if (a && a.currentSrc && a.currentSrc !== curSrc) { curSrc = a.currentSrc; setEnv(curSrc); flash = 1; }
}).observe(document.querySelector('.djd-hero__photos'), { subtree: true, attributes: true, attributeFilter: ['class'] });
const a0 = act(); curSrc = a0.currentSrc || a0.src; setEnv(curSrc);

// Star sparkles: 4 additive sprites with a soft dot plus a thin cross, fading in
// and out (sin envelope, 700 to 1400ms) at random spots on the lit upper left
// of the ball, 0.8 to 3.4s apart, rotating slightly while visible.
const sc = document.createElement('canvas'); sc.width = sc.height = 128;
const x = sc.getContext('2d');
const g = x.createRadialGradient(64, 64, 0, 64, 64, 64);
g.addColorStop(0, 'rgba(255,255,255,1)'); g.addColorStop(0.15, 'rgba(255,255,255,.6)'); g.addColorStop(1, 'rgba(255,255,255,0)');
x.fillStyle = g; x.fillRect(0, 0, 128, 128);
x.globalCompositeOperation = 'lighter';
let lg = x.createLinearGradient(64, 0, 64, 128);
lg.addColorStop(0, 'rgba(255,255,255,0)'); lg.addColorStop(0.5, 'rgba(255,255,255,1)'); lg.addColorStop(1, 'rgba(255,255,255,0)');
x.fillStyle = lg; x.fillRect(62, 0, 4, 128);
lg = x.createLinearGradient(0, 64, 128, 64);
lg.addColorStop(0, 'rgba(255,255,255,0)'); lg.addColorStop(0.5, 'rgba(255,255,255,1)'); lg.addColorStop(1, 'rgba(255,255,255,0)');
x.fillStyle = lg; x.fillRect(0, 62, 128, 4);
const tex = new T.CanvasTexture(sc);
const sps = [];
for (let i = 0; i < 4; i++) {
	const s = new T.Sprite(new T.SpriteMaterial({ map: tex, blending: T.AdditiveBlending, transparent: true, depthTest: false, opacity: 0, color: i % 2 ? 0xbfe9ff : 0xffffff }));
	s.scale.set(0.55, 0.55, 1);
	s.position.z = 2;
	S.add(s);
	sps.push({ s, t: Math.random() * 3000, dur: 700 + Math.random() * 700 });
}

let last = performance.now(), sz = 0;
function frame(now) {
	const dt = Math.min(64, now - last); last = now;
	spin.rotation.y += dt * 0.0002; // same spin rate as the 2D ball and the specks
	flash = Math.max(0, flash - dt / 900);

	sps.forEach(o => {
		o.t -= dt;
		if (o.t < 0) {
			if (o.t < -o.dur) {
				o.t = 800 + Math.random() * 2600;
				const a = Math.random() * 6.28, r = Math.sqrt(Math.random()) * 0.75;
				o.s.position.x = -0.25 + Math.cos(a) * r * 0.8;
				o.s.position.y = 0.15 + Math.sin(a) * r * 0.8;
				o.s.material.opacity = 0;
			} else {
				const k = -o.t / o.dur;
				o.s.material.opacity = Math.sin(k * Math.PI);
				o.s.material.rotation = k * 0.6;
			}
		}
	});

	// Follow the 2D ball canvas (demo wiring only)
	const r = src.getBoundingClientRect(), cs = getComputedStyle(src);
	const pad = Math.round(r.width * 0.18), s = Math.round(r.width) + pad * 2;
	if (s !== sz) { sz = s; R.setSize(s, s, false); cv.style.width = s + 'px'; cv.style.height = s + 'px'; }
	const half = (s / 2) / Math.max(1, (r.width - 6) / 2);
	cam.left = -half; cam.right = half; cam.top = half; cam.bottom = -half; cam.updateProjectionMatrix();
	cv.style.transform = 'translate(' + (r.left - pad) + 'px,' + (r.top - pad) + 'px)';
	cv.style.opacity = (r.width < 2 || cs.display === 'none') ? 0 : parseFloat(cs.opacity);

	mat.envMapIntensity = 1.2 + flash * 3;
	key.intensity = 3.2 + flash * 8;
	R.render(S, cam);
	requestAnimationFrame(frame);
}
requestAnimationFrame(frame);
