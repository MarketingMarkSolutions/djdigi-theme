/* Settings > DigiMark Theme: the hero photo picker (add, reorder, remove, focal point). */
(function ($) {
	'use strict';

	var grid = document.getElementById('djdt-hero-photos');
	var hidden = document.getElementById('djdt-hero-photos-json');
	var addBtn = document.getElementById('djdt-add-photos');
	if (!grid || !hidden || !addBtn) return;

	// Each row: { id, x, y, url }. url is filled in as thumbnails resolve and
	// is never saved, only the hidden field's id/x/y round trip to the server.
	var photos = (window.djdtAdmin && window.djdtAdmin.photos) || [];
	var pickingIndex = -1;

	function persist() {
		var out = photos.map(function (p) { return { id: p.id, x: p.x, y: p.y }; });
		hidden.value = JSON.stringify(out);
	}

	function render() {
		grid.innerHTML = '';
		photos.forEach(function (p, i) {
			var item = document.createElement('div');
			item.className = 'djdt-photo-item' + (i === pickingIndex ? ' is-picking-focal' : '');
			item.draggable = true;
			item.dataset.index = i;

			var frame = document.createElement('div');
			frame.className = 'djdt-photo-item__frame';
			if (p.url) {
				var img = document.createElement('img');
				img.src = p.url;
				img.alt = '';
				frame.appendChild(img);
			}
			var focal = document.createElement('div');
			focal.className = 'djdt-photo-item__focal';
			focal.style.left = p.x + '%';
			focal.style.top = p.y + '%';
			frame.appendChild(focal);
			frame.addEventListener('click', function (e) {
				if (pickingIndex !== i) {
					pickingIndex = i;
					render();
					return;
				}
				var rect = frame.getBoundingClientRect();
				p.x = Math.round(Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100)));
				p.y = Math.round(Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100)));
				pickingIndex = -1;
				persist();
				render();
			});
			item.appendChild(frame);

			var row = document.createElement('div');
			row.className = 'djdt-photo-item__row';
			var hint = document.createElement('span');
			hint.className = 'djdt-photo-item__hint';
			hint.textContent = i === pickingIndex ? 'Click the focal point' : 'Click to set focal point';
			row.appendChild(hint);
			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'djdt-photo-item__remove';
			remove.setAttribute('aria-label', 'Remove from rotation');
			remove.textContent = '×';
			remove.addEventListener('click', function (e) {
				e.stopPropagation();
				photos.splice(i, 1);
				pickingIndex = -1;
				persist();
				render();
			});
			row.appendChild(remove);
			item.appendChild(row);

			item.addEventListener('dragstart', function () {
				item.classList.add('is-dragging');
				item.dataset.dragFrom = i;
			});
			item.addEventListener('dragend', function () {
				item.classList.remove('is-dragging');
			});
			item.addEventListener('dragover', function (e) { e.preventDefault(); });
			item.addEventListener('drop', function (e) {
				e.preventDefault();
				var from = parseInt(item.dataset.dragFrom || grid.querySelector('.is-dragging').dataset.index, 10);
				var to = i;
				if (isNaN(from) || from === to) return;
				var moved = photos.splice(from, 1)[0];
				photos.splice(to, 0, moved);
				pickingIndex = -1;
				persist();
				render();
			});

			grid.appendChild(item);
		});
	}

	function resolveThumbnails() {
		photos.forEach(function (p) {
			if (p.url || !p.id) return;
			var attachment = wp.media.attachment(p.id);
			attachment.fetch().then(function () {
				var sizes = attachment.get('sizes');
				p.url = (sizes && sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url') || '';
				render();
			});
		});
	}

	addBtn.addEventListener('click', function (e) {
		e.preventDefault();
		var frame = wp.media({ title: 'Choose hero photos', multiple: true, library: { type: 'image' } });
		frame.on('select', function () {
			frame.state().get('selection').each(function (attachment) {
				var id = attachment.get('id');
				if (photos.some(function (p) { return p.id === id; })) return;
				var sizes = attachment.get('sizes');
				photos.push({
					id: id,
					x: 50,
					y: 50,
					url: (sizes && sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url') || ''
				});
			});
			persist();
			render();
		});
		frame.open();
	});

	resolveThumbnails();
	render();
})(jQuery);
