// Artikelseite: Inhaltsverzeichnis, Fokusmodus, Bildansicht, Zwei-Klick-Videos, Randnotizen, Baustein-Laden.
const EMBEDS = {
	youtube: 'https://www.youtube-nocookie.com/embed/',
	vimeo: 'https://player.vimeo.com/video/',
};
const body = document.body;

function create(tag, className, text) {
	const node = document.createElement(tag);
	node.className = className;
	node.textContent = text;
	return node;
}

// ---- Inhaltsverzeichnis: Marker folgt dem gelesenen Abschnitt ----
function trackSections() {
	const links = [...document.querySelectorAll('.iv a')];
	const headings = [...document.querySelectorAll('.text h2[id]')];
	const current = document.getElementById('iv-jetzt');
	if (!links.length || !headings.length || !('IntersectionObserver' in window)) return;

	const list = links[0].closest('ol');
	const marker = create('li', 'marker', '');
	marker.setAttribute('aria-hidden', 'true');
	list.append(marker);
	const select = (id) => {
		const link = links.find((anchor) => anchor.getAttribute('href') === '#' + id);
		if (!link) return;
		links.forEach((anchor) => anchor.toggleAttribute('aria-current', anchor === link));
		marker.style.transform = 'translateY(' + link.offsetTop + 'px)';
		marker.style.height = link.offsetHeight + 'px';
		if (current) current.textContent = link.textContent;
	};
	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => entry.isIntersecting && select(entry.target.id));
	}, { rootMargin: '-15% 0px -70% 0px' });
	headings.forEach((heading) => observer.observe(heading));
	select(headings[0].id);

	const mobile = document.getElementById('iv-mobil');
	if (mobile) mobile.addEventListener('click', (event) => { if (event.target.closest('a')) mobile.open = false; });
}

function hideSideColumnAtFullImages() {
	const column = document.getElementById('seitenspalte');
	const images = document.querySelectorAll('.abb[data-layout="full"]');
	if (!column || !images.length || !('IntersectionObserver' in window)) return;
	const visible = new Set();
	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
		column.classList.toggle('weg', visible.size > 0);
	}, { rootMargin: '-10% 0px -10% 0px' });
	images.forEach((image) => observer.observe(image));
}

// Randnotizen stehen per CSS neben ihrer Stelle; hier nur die Kollisionen (unter Vorgängerin und randlose Bilder).
function placeMarginNotes() {
	const text = document.querySelector('.text');
	const notes = text ? [...text.querySelectorAll(':scope > .tldr, :scope > .sidenote')] : [];
	if (!notes.length) return;
	const full = [...text.querySelectorAll(':scope > .abb[data-layout="full"]')];
	const spacing = 16;
	const overlaps = (element, top, bottom) => element.offsetTop < bottom && element.offsetTop + element.offsetHeight > top;

	const layout = () => {
		notes.forEach((note) => { note.style.marginTop = ''; });
		if (getComputedStyle(notes[0]).position !== 'absolute') return;
		let floor = 0;
		notes.forEach((note) => {
			const anchor = note.offsetTop;
			let top = Math.max(anchor, floor);
			full.forEach((element) => { if (overlaps(element, top, top + note.offsetHeight)) top = element.offsetTop + element.offsetHeight + spacing; });
			if (top > anchor) note.style.marginTop = (top - anchor + parseFloat(getComputedStyle(note).marginTop)) + 'px';
			floor = top + note.offsetHeight + spacing;
		});
	};
	let pending = 0;
	const schedule = () => { cancelAnimationFrame(pending); pending = requestAnimationFrame(layout); };
	schedule();
	window.addEventListener('resize', schedule);
	window.addEventListener('load', schedule);
	if (document.fonts) document.fonts.ready.then(schedule);
}

// ---- Fokusmodus: nur der Text, im Vollbild ----
function setUpFocusMode() {
	const start = document.getElementById('fokus-an');
	const leave = create('button', 'fokus-raus', 'Fokus beenden ');
	leave.type = 'button';
	leave.append(create('kbd', '', 'Esc'));
	body.append(leave);

	const focus = (on) => {
		body.classList.toggle('fokus', on);
		if (on) {
			if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {});
			const head = document.querySelector('.artikelkopf');
			if (head) head.scrollIntoView({ block: 'start' });
		} else if (document.fullscreenElement) {
			document.exitFullscreen().catch(() => {});
		}
	};
	if (start) {
		start.hidden = false;
		start.addEventListener('click', () => focus(true));
	}
	leave.addEventListener('click', () => focus(false));
	document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) body.classList.remove('fokus'); });
	document.addEventListener('keydown', (event) => {
		const tag = document.activeElement ? document.activeElement.tagName : '';
		const isTyping = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
		if (event.key === 'Escape' && body.classList.contains('fokus')) focus(false);
		if (event.key.toLowerCase() === 'f' && !isTyping && !event.ctrlKey && !event.metaKey && !event.altKey) focus(!body.classList.contains('fokus'));
	});
	let timer;
	document.addEventListener('mousemove', () => {
		if (!body.classList.contains('fokus')) return;
		body.classList.add('maus');
		clearTimeout(timer);
		timer = setTimeout(() => body.classList.remove('maus'), 1500);
	});
}

// ---- Bildansicht: größte Variante, ein Dialog für alle Bilder ----
function largestSource(picture) {
	const source = picture.querySelector('source[type="image/webp"]');
	const image = picture.querySelector('img');
	if (!source || !source.srcset) return image.currentSrc || image.src;
	let best = { width: 0, url: image.src };
	source.srcset.split(',').forEach((candidate) => {
		const [url, width] = candidate.trim().split(/\s+/);
		if (parseInt(width, 10) > best.width) best = { width: parseInt(width, 10), url };
	});
	return best.url;
}

function button(className, text, label) {
	const node = create('button', className, text);
	node.type = 'button';
	node.setAttribute('aria-label', label);
	return node;
}

// Ein Dialog für alle Bilder (Delegation): Zähler, Pfeile, Unterschrift, Vorschaubilder; ←/→/Pos1/Ende, Esc.
function setUpLightbox() {
	const SELECTOR = '.abb .foto, .galerie .foto';
	const parts = {};
	let dialog = null;
	let list = [];
	let index = 0;
	let trigger = null;
	const show = () => {
		const photo = list[index];
		const picture = photo.querySelector('picture');
		const image = document.createElement('img');
		image.src = picture ? largestSource(picture) : photo.querySelector('img').src;
		image.alt = photo.querySelector('img').alt;
		parts.image.replaceWith(image);
		parts.image = image;
		const figcaption = photo.closest('figure').querySelector('figcaption');
		parts.text.textContent = (photo.dataset.unterschrift || (figcaption ? figcaption.textContent : '')).trim();
		parts.count.textContent = list.length > 1 ? (index + 1) + ' / ' + list.length : '';
		parts.prev.disabled = index === 0;
		parts.next.disabled = index === list.length - 1;
		[...parts.strip.children].forEach((thumb, i) => thumb.setAttribute('aria-current', String(i === index)));
		if (parts.strip.children[index]) parts.strip.children[index].scrollIntoView({ block: 'nearest', inline: 'center' });
	};
	const go = (target) => {
		if (target < 0 || target >= list.length || target === index) return;
		index = target;
		show();
	};
	const build = () => {
		dialog = create('dialog', 'lightbox', '');
		dialog.setAttribute('aria-label', 'Bildansicht');
		const head = create('div', 'lightbox-kopf', '');
		parts.count = create('span', 'lightbox-zahl', '');
		const close = button('zu', '×', 'Schließen');
		head.append(parts.count, close);
		const stage = create('div', 'lightbox-buehne', '');
		parts.image = document.createElement('img');
		parts.prev = button('blatt zurueck', '‹', 'Vorheriges Bild');
		parts.next = button('blatt vor', '›', 'Nächstes Bild');
		stage.append(parts.image, parts.prev, parts.next);
		parts.text = create('p', 'lightbox-text', '');
		parts.strip = create('div', 'lightbox-leiste', '');
		close.addEventListener('click', () => dialog.close());
		parts.prev.addEventListener('click', () => go(index - 1));
		parts.next.addEventListener('click', () => go(index + 1));
		dialog.addEventListener('click', (event) => { if (event.target === dialog || event.target === stage) dialog.close(); });
		dialog.addEventListener('keydown', (event) => {
			const target = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: list.length - 1 }[event.key];
			if (target === undefined) return;
			event.preventDefault();
			go(target);
		});
		let startX = null;
		stage.addEventListener('pointerdown', (event) => { startX = event.clientX; });
		stage.addEventListener('pointerup', (event) => {
			if (startX === null) return;
			const distance = event.clientX - startX;
			startX = null;
			if (Math.abs(distance) > 50) go(index + (distance < 0 ? 1 : -1));
		});
		dialog.addEventListener('close', () => { if (trigger) trigger.focus(); });
		dialog.append(head, stage, parts.text, parts.strip);
		body.append(dialog);
	};
	const open = (photo) => {
		const gallery = photo.closest('.galerie');
		list = gallery ? [...gallery.querySelectorAll('.foto')] : [photo];
		index = list.indexOf(photo);
		trigger = photo;
		if (!dialog) build();
		dialog.classList.toggle('einzeln', list.length < 2);
		parts.strip.replaceChildren(...(list.length < 2 ? [] : list.map((entry, i) => {
			const thumb = button('', '', 'Bild ' + (i + 1) + ' von ' + list.length);
			const small = document.createElement('img');
			small.src = entry.querySelector('img').currentSrc || entry.querySelector('img').src;
			small.alt = '';
			thumb.append(small);
			thumb.addEventListener('click', () => go(i));
			return thumb;
		})));
		dialog.showModal();
		show();
	};
	// Jedes vergrößerbare Bild ist ein Knopf, auch später eingefügt (Vorschau).
	const prepare = () => document.querySelectorAll(SELECTOR).forEach((photo) => {
		if (photo.tabIndex === 0 || !photo.querySelector('img')) return;
		const gallery = photo.closest('.galerie');
		const photos = gallery ? [...gallery.querySelectorAll('.foto')] : [photo];
		photo.tabIndex = 0;
		photo.setAttribute('role', 'button');
		photo.setAttribute('aria-label', photos.length > 1 ? 'Bild ' + (photos.indexOf(photo) + 1) + ' von ' + photos.length + ' vergrößern' : 'Bild vergrößern');
	});
	prepare();
	new MutationObserver(prepare).observe(document.querySelector('.text') || body, { childList: true, subtree: true });
	document.addEventListener('click', (event) => {
		const photo = event.target.closest(SELECTOR);
		if (photo && photo.querySelector('img')) open(photo);
	});
	document.addEventListener('keydown', (event) => {
		const photo = event.target.closest && event.target.closest(SELECTOR);
		if (photo && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); open(photo); }
	});
}

// ---- Videos: erst nach dem Klick wird etwas vom Anbieter geladen ----
function setUpEmbeds() {
	document.addEventListener('click', (event) => {
		const start = event.target.closest('.einbettung-start');
		const figure = start && start.closest('.einbettung');
		const base = figure && EMBEDS[figure.dataset.embed];
		if (!base) return;
		const caption = figure.querySelector('figcaption');
		const frame = document.createElement('iframe');
		frame.src = base + encodeURIComponent(figure.dataset.id) + '?autoplay=1';
		frame.allow = 'autoplay; fullscreen; picture-in-picture';
		frame.allowFullscreen = true;
		frame.title = caption && caption.textContent.trim() ? caption.textContent.trim() : 'Video';
		start.replaceWith(frame);
	});
}

// ---- Gestaltungs-Bausteine: eigene Dateien, nur bei Bedarf ----
function loadComponents() {
	const { bausteine, bausteinestil } = document.documentElement.dataset;
	if (!document.querySelector('.galerie[data-variante], .vergleich, .erklaerbild, .wertung, .zeitstrahl, .akkordeon')) return;
	if (!document.querySelector('link[href="' + bausteinestil + '"]')) document.head.append(Object.assign(document.createElement('link'), { rel: 'stylesheet', href: bausteinestil }));
	if (document.querySelector('.galerie[data-variante="karussell"], .galerie[data-variante="streifen"], .vergleich, .erklaerbild')) import(bausteine).then((module) => module.start());
}

trackSections();
hideSideColumnAtFullImages();
placeMarginNotes();
setUpFocusMode();
setUpLightbox();
setUpEmbeds();
loadComponents();
