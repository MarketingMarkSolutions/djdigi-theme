/* Settings > DigiMark Theme: the hero photo picker (add, reorder, remove,
   focal point) and the venues list (add, reorder, remove, logo, caption,
   link, on/off). Two independent pieces in one file since both only ever
   run together, on this one settings page. */
(function ($) {
	'use strict';

	(function heroPhotos() {

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

	})();

	(function venues() {

	var grid = document.getElementById('djdt-venues');
	var hidden = document.getElementById('djdt-venues-json');
	var addBtn = document.getElementById('djdt-add-venue');
	if (!grid || !hidden || !addBtn) return;

	// Each row: { id, caption, link, on, url }. url is filled in as
	// thumbnails resolve and is never saved, only id/caption/link/on round
	// trip to the server, same pattern as the hero photos above.
	var rows = (window.djdtAdmin && window.djdtAdmin.venues) || [];

	function persist() {
		var out = rows.map(function (r) {
			return { id: r.id, caption: r.caption, link: r.link, on: r.on ? 1 : 0 };
		});
		hidden.value = JSON.stringify(out);
	}

	function render() {
		grid.innerHTML = '';
		rows.forEach(function (r, i) {
			var item = document.createElement('div');
			item.className = 'djdt-venue-item';
			item.draggable = true;
			item.dataset.index = i;

			var logo = document.createElement('div');
			logo.className = 'djdt-venue-item__logo';
			if (r.url) {
				var img = document.createElement('img');
				img.src = r.url;
				img.alt = '';
				logo.appendChild(img);
			} else {
				var hint = document.createElement('span');
				hint.textContent = 'No logo';
				logo.appendChild(hint);
			}
			logo.addEventListener('click', function () {
				var picker = wp.media({ title: 'Choose a venue logo', multiple: false, library: { type: 'image' } });
				picker.on('select', function () {
					var attachment = picker.state().get('selection').first();
					r.id = attachment.get('id');
					var sizes = attachment.get('sizes');
					r.url = (sizes && sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url') || '';
					persist();
					render();
				});
				picker.open();
			});
			item.appendChild(logo);

			var fields = document.createElement('div');
			fields.className = 'djdt-venue-item__fields';

			var caption = document.createElement('input');
			caption.type = 'text';
			caption.className = 'regular-text';
			caption.placeholder = 'Caption (club or event name)';
			caption.value = r.caption || '';
			caption.addEventListener('input', function () { r.caption = caption.value; persist(); });
			fields.appendChild(caption);

			var link = document.createElement('input');
			link.type = 'text';
			link.className = 'regular-text';
			link.placeholder = 'Link (optional)';
			link.value = r.link || '';
			link.addEventListener('input', function () { r.link = link.value; persist(); });
			fields.appendChild(link);

			item.appendChild(fields);

			var toggle = document.createElement('label');
			toggle.className = 'djdt-venue-item__toggle';
			var checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.checked = 0 !== r.on;
			checkbox.addEventListener('change', function () { r.on = checkbox.checked ? 1 : 0; persist(); });
			toggle.appendChild(checkbox);
			toggle.appendChild(document.createTextNode(' On'));
			item.appendChild(toggle);

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'djdt-venue-item__remove';
			remove.setAttribute('aria-label', 'Remove venue');
			remove.textContent = '×';
			remove.addEventListener('click', function (e) {
				e.stopPropagation();
				rows.splice(i, 1);
				persist();
				render();
			});
			item.appendChild(remove);

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
				var dragging = grid.querySelector('.is-dragging');
				var from = parseInt(item.dataset.dragFrom || (dragging && dragging.dataset.index), 10);
				var to = i;
				if (isNaN(from) || from === to) return;
				var moved = rows.splice(from, 1)[0];
				rows.splice(to, 0, moved);
				persist();
				render();
			});

			grid.appendChild(item);
		});
	}

	function resolveThumbnails() {
		rows.forEach(function (r) {
			if (r.url || !r.id) return;
			var attachment = wp.media.attachment(r.id);
			attachment.fetch().then(function () {
				var sizes = attachment.get('sizes');
				r.url = (sizes && sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url') || '';
				render();
			});
		});
	}

	addBtn.addEventListener('click', function (e) {
		e.preventDefault();
		rows.push({ id: 0, caption: '', link: '', on: 1, url: '' });
		persist();
		render();
	});

	resolveThumbnails();
	render();

	})();

})(jQuery);
