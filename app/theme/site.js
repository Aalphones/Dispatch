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

// Redaktionsmodus (ADR-023): Ohne Merkzeichen kein Aufruf außer dem Anmelden; danach lädt start.js alles Weitere.
const MARK = 'dispatch.angemeldet';
const loginDialog = document.getElementById('login');
const loginForm = document.getElementById('login-form');
const loginError = document.getElementById('login-fehler');

function startEditorial(token) {
	return root.dataset.redaktion ? import(root.dataset.redaktion).then((module) => module.start(token)).catch(() => null) : null;
}

function isMarked() {
	try {
		return localStorage.getItem(MARK) === '1';
	} catch {
		return false;
	}
}

function openLogin() {
	loginError.hidden = true;
	if (!loginDialog.open) loginDialog.showModal();
}

async function login(event) {
	event.preventDefault();
	const form = new FormData(loginForm);
	let message = 'Anmeldung gerade nicht möglich.';
	try {
		const response = await fetch('/dispatch/api/auth/login', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ user: form.get('user'), password: form.get('password') }),
		});
		if (response.ok) {
			const { access_token: token } = await response.json();
			localStorage.setItem(MARK, '1');
			loginForm.reset();
			loginDialog.close();
			startEditorial(token);
			return;
		}
		if (response.status === 401) message = 'Name oder Passwort stimmt nicht.';
		if (response.status === 429) message = 'Zu viele Versuche. Bitte in 15 Minuten erneut.';
	} catch {}
	loginError.textContent = message;
	loginError.hidden = false;
	loginForm.elements.password.select();
}

if (loginDialog) {
	document.getElementById('anmelden').addEventListener('click', openLogin);
	loginForm.addEventListener('submit', login);
	loginDialog.addEventListener('click', (event) => event.target.closest('[data-close]') && loginDialog.close());
	if (!isMarked() && location.hash === '#anmelden') openLogin();
}

if (isMarked()) startEditorial();

if (document.body.dataset.seite === 'suche') {
	loadSearch().then((module) => module && module.seite());
}
