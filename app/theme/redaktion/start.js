// Einstieg des Redaktionsmodus: Sitzung herstellen, Stile laden, Konto zeigen. `site.js` ruft `start(token?)`.
import { start as startKonto } from './konto.js';
import { onEnde, setToken, token } from './sitzung.js';

let isStarted = false;

// Nach dem Abmelden muss ein erneutes Anmelden auf derselben Seite wieder starten dürfen.
onEnde(() => {
	isStarted = false;
	document.querySelectorAll('[data-red]').forEach((node) => node.remove());
	import('./reaction.js').then((module) => module.zuruecksetzen());
});

export async function start(jwt) {
	if (isStarted) {
		return;
	}
	isStarted = true;

	if (jwt) {
		setToken(jwt);
	} else {
		try {
			await token();
		} catch {
			isStarted = false;
			return;
		}
	}

	document.head.append(Object.assign(document.createElement('link'), { rel: 'stylesheet', href: new URL('redaktion.css', import.meta.url).href }));
	document.body.classList.add('angemeldet');
	await startKonto();
	await startPage(document.body.dataset.seite);
}

/** Je Seitenart nur laden, was sie braucht; ein Fehler dort darf die Seite nicht stören. */
async function startPage(page) {
	try {
		if (page === 'vorschau') {
			await (await import('./post.js')).vorschau();
		} else if (page === 'start') {
			await (await import('./post.js')).startseite();
		} else if (page === 'artikel') {
			await (await import('./post.js')).artikel();
		} else if (page === 'kategorien') {
			await (await import('./category.js')).gewichte();
		} else if (page === 'zustimmung') {
			await (await import('./zustimmung.js')).seite();
		} else if (page === 'kategorie' || page === 'tag') {
			await (await import('./reaction.js')).karten();
		}
	} catch {}
}
