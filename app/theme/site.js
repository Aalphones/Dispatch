// Auf jeder Seite: Tastenkürzel, Kopfhöhe, Nachladen der Suche. Alles andere liegt in suche.js und artikel.js.
const root = document.documentElement;
const searchField = document.getElementById('suchfeld');
let searchModule = null;

// Klebende Leisten setzen genau unter der Kategorie-Leiste an: ihre echte Höhe messen statt schätzen.
const nav = document.querySelector('.nav');
if (nav && 'ResizeObserver' in window) {
	new ResizeObserver(() => root.style.setProperty('--nav-h', nav.offsetHeight + 'px')).observe(nav);
}

const isApple = /Mac|iPhone|iPad/.test(navigator.platform);
document.querySelectorAll('.taste').forEach((key) => {
	key.textContent = isApple ? '⌘ K' : 'Strg K';
});

// Das Suchmodul wird erst gebraucht, wenn jemand sucht; die Adresse steht im <html>-Tag.
function loadSearch() {
	if (searchModule === null) {
		const address = root.dataset.suche;
		if (!address) {
			return Promise.resolve(null);
		}
		searchModule = import(address).catch(() => null);
	}
	return searchModule;
}

function isTyping() {
	const tag = document.activeElement ? document.activeElement.tagName : '';
	return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || Boolean(document.activeElement && document.activeElement.isContentEditable);
}

if (searchField) {
	searchField.addEventListener('focus', () => {
		loadSearch().then((module) => module && module.start(searchField));
	}, { once: true });

	document.addEventListener('keydown', (event) => {
		const isShortcut = (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey))
			|| (event.key === '/' && !isTyping() && !event.ctrlKey && !event.metaKey && !event.altKey);
		if (!isShortcut) {
			return;
		}
		event.preventDefault();
		searchField.focus();
		searchField.select();
	});
}

// Gezählt wird nur, wer nicht widerspricht (DNT, GPC), nicht angemeldet ist und keine Vorschau sieht.
function isCounted() {
	try {
		return navigator.doNotTrack !== '1' && navigator.globalPrivacyControl !== true
			&& !localStorage.getItem('dispatch.angemeldet') && document.body.dataset.seite !== 'vorschau';
	} catch {
		return false;
	}
}

if (root.dataset.zaehler && isCounted()) {
	import(root.dataset.zaehler).catch(() => null);
}

if (document.body.dataset.seite === 'suche') {
	loadSearch().then((module) => module && module.seite());
}
