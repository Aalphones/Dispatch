// Einstieg des Redaktionsmodus: Sitzung herstellen, Stile laden, Konto zeigen. `site.js` ruft `start(token?)`.
import { start as startKonto } from './konto.js';
import { onEnde, setToken, token } from './sitzung.js';

let isStarted = false;

// Nach dem Abmelden muss ein erneutes Anmelden auf derselben Seite wieder starten dürfen.
onEnde(() => {
	isStarted = false;
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
	if (document.body.dataset.seite === 'vorschau') {
		(await import('./post.js')).vorschau();
	}
}
