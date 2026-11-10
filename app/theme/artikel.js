// Artikelseite: Inhaltsverzeichnis-Marker, Fokusmodus, Lightbox, Zwei-Klick-Videos, Seitenspalte neben randlosen Bildern.
// Ohne IntersectionObserver oder Vollbild fällt die jeweilige Funktion einfach weg.
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
	const marker = create('span', 'marker', '');
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

// Neben randlosen Bildern wäre die Seitenspalte im Weg.
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
	// Der Browser beendet das Vollbild selbst mit Esc; dann endet auch der Fokusmodus.
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

// ---- Lightbox: größte Variante, ein Dialog für alle Bilder ----
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

function setUpLightbox() {
	let dialog = null;
	document.addEventListener('click', (event) => {
		const photo = event.target.closest('.abb .foto, .galerie .foto');
		const picture = photo && photo.querySelector('picture');
		if (!picture) return;
		if (!dialog) {
			dialog = create('dialog', 'lightbox', '');
			dialog.setAttribute('aria-label', 'Bildansicht');
			dialog.addEventListener('click', (click) => { if (click.target === dialog) dialog.close(); });
			body.append(dialog);
		}
		const image = document.createElement('img');
		image.src = largestSource(picture);
		image.alt = picture.querySelector('img').alt;
		const close = create('button', 'zu', '×');
		close.type = 'button';
		close.setAttribute('aria-label', 'Schließen');
		close.addEventListener('click', () => dialog.close());
		const caption = photo.closest('figure').querySelector('figcaption');
		dialog.replaceChildren(image, close);
		if (caption) dialog.append(create('p', 'lightbox-text', caption.textContent.trim()));
		dialog.showModal();
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

trackSections();
hideSideColumnAtFullImages();
setUpFocusMode();
setUpLightbox();
setUpEmbeds();
