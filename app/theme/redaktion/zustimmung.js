// Zustimmungsseite /anmelden/zustimmung/ (Entwurf: docs/planning/…/artifacts/entwurf-zustimmung.html).
// `start.js` ruft `seite()` nach der Anmeldung; sie ersetzt die Gast-Hülle in `#seite`.
import { api } from './api.js';
import { el, icon } from './ui.js';

const REDIRECT_DELAY_MS = 1200;
const SECONDS_PER_MINUTE = 60;
const SERVER_ERROR_STATUS = 500;

const RIGHTS = {
	'blog:read': ['Lesen', 'Briefing, Artikel, Statistik', 'Briefing, Artikel, Queue und Statistik lesen. Ohne Lesen kann die App nicht arbeiten.'],
	'blog:write': ['Schreiben und einreichen', 'Entwürfe, Bilder, Themen, Einreichen', 'Entwürfe anlegen, Bilder hochladen, Themen einreichen, zur Freigabe einreichen. Veröffentlichen kann keine App.'],
	'blog:admin': ['Einstellungen und Kategorien ändern', 'Richtlinien, Präferenzen, Kategorien', 'Richtlinien, Präferenzen und Kategorien. Öffentlich Sichtbares wird nur vorgeschlagen und wartet auf dich.'],
};

const NO_RIGHT_TEXT = 'Wähle mindestens ein Recht – oder lehne ab.';
const SEND_FAILED_TEXT = 'Die Antwort kam nicht an. Prüfe die Verbindung und tippe noch einmal auf „Erlauben“.';

/** Ende-Zustand der Seite; `kind` ist `gut`, `aus` oder `fehler`. */
function endState(kind, iconName, title, text, extra) {
	return el('div', { class: 'zust-ende ' + kind }, [
		icon(iconName),
		el('h1', { text: title }),
		el('p', {}, text),
		extra ? el('p', { class: 'meta' }, extra) : null,
	]);
}

function bold(text) {
	return el('b', { text });
}

function show(page, content) {
	const section = el('section', { class: 'zust stand', 'aria-live': 'polite' }, content);
	page.replaceChildren(section);
}

function showLoading(page) {
	show(page, [
		el('div', { class: 'lader kurz' }),
		el('div', { class: 'lader titel' }),
		el('div', { class: 'lader' }),
		el('div', { class: 'lader' }),
		el('div', { class: 'lader kurz' }),
	]);
}

function showExpired(page) {
	show(page, endState('fehler', 'uhr', 'Anfrage abgelaufen', 'Eine Verbindungsanfrage gilt 10 Minuten. Starte die Verbindung in der App neu – die neue Anfrage öffnet diese Seite wieder.'));
}

function showGenericError(page) {
	show(page, endState('fehler', 'warn', 'Anfrage nicht lesbar', 'Die Anfrage konnte nicht geladen werden. Starte die Verbindung in der App neu.'));
}

/** Zustand nach einer Abweisung durch `/authorize` (`?fehler=…`). */
function showRejection(page, code, host) {
	if (code === 'unbekannte_app') {
		const reported = host ? [' (gemeldet: ', bold(host), ')'] : [];
		show(page, endState('fehler', 'sperre', 'Unbekannte App abgewiesen',
			['Die App hat sich nicht gültig ausgewiesen', reported, '. Starte die Verbindung in der App neu.'],
			'Nichts wurde gespeichert, du musst nichts tun.'));
		return;
	}
	if (code === 'app_nicht_erreichbar') {
		show(page, endState('fehler', 'uhr', 'App nicht erreichbar', 'Die App konnte nicht geprüft werden. Versuche es in ein paar Minuten noch einmal.'));
		return;
	}
	if (code === 'falsche_rueckadresse') {
		show(page, endState('fehler', 'sperre', 'Unbekannte Rückadresse', 'Die Anfrage wollte die Antwort an eine Adresse schicken, die die App nicht angemeldet hat. Nichts wurde gespeichert.'));
		return;
	}
	showGenericError(page);
}

function minutesText(seconds) {
	const minutes = Math.ceil(seconds / SECONDS_PER_MINUTE);
	if (minutes <= 1) {
		return 'Anfrage gilt noch weniger als eine Minute.';
	}

	return 'Anfrage gilt noch ' + minutes + ' Minuten.';
}

function whoList(request) {
	const client = request.client;
	const appInfo = client.kind === 'manual'
		? el('small', { text: 'von dir angelegt' })
		: el('span', { class: 'geprueft' }, [icon('check'), 'ausgewiesen von ' + client.host]);
	const reply = [el('span', { class: 'host', text: request.redirect_host })];
	if (request.loopback) {
		reply.push(el('small', { text: 'ein Programm auf diesem Gerät' }));
	}

	return el('dl', { class: 'wer' }, [
		el('dt', { text: 'App' }),
		el('dd', {}, [el('span', { class: 'host', text: client.name }), appInfo]),
		el('dt', { text: 'Antwort geht an' }),
		el('dd', {}, reply),
	]);
}

function rightChoice(scope) {
	const [title, hint, help] = RIGHTS[scope];

	return el('label', { class: 'wahl' }, [
		el('input', { type: 'checkbox', name: 'scope', value: scope, checked: scope !== 'blog:admin' }),
		el('b', { text: title }),
		el('span', { class: 'info', tabindex: '0', role: 'note', 'aria-label': help, 'data-tipp': help, text: 'i' }),
		el('small', { text: hint }),
	]);
}

function errorLine(text) {
	return [icon('warn'), el('span', { text })];
}

/** Nach dem Erlauben oder Ablehnen: Zustand zeigen, dann zur App zurück. */
function leave(page, redirect, allowed, request) {
	const target = request.redirect_host;
	const link = el('a', { href: redirect, text: 'hier weiter' });
	if (allowed) {
		show(page, endState('gut', 'check', 'Erlaubt', ['Weiter zu ', bold(target), ' …'], ['Falls nichts passiert: ', link, '.']));
	} else {
		show(page, endState('aus', 'sperre', 'Abgelehnt', [request.client.name + ' bekommt keinen Zugriff. Zurück zu ', bold(target), ' …'], ['Falls nichts passiert: ', link, '.']));
	}
	setTimeout(() => location.assign(redirect), REDIRECT_DELAY_MS);
}

async function decide(page, request, allow, scopes, fail) {
	try {
		const { redirect } = await api('POST', 'oauth/consent', { request_id: request.request_id, decision: allow ? 'allow' : 'deny', scopes });
		leave(page, redirect, allow, request);
	} catch (error) {
		if (error.status === 404 || error.status === 410) {
			showExpired(page);
			return;
		}
		// Netzfehler werfen kein API-Fehlerobjekt (kein `status`).
		const isTransport = !error.status || error.status >= SERVER_ERROR_STATUS || error.error === 'http_error';
		fail(isTransport ? SEND_FAILED_TEXT : error.message);
	}
}

function showConsent(page, request) {
	const name = request.client.name;
	const fault = el('p', { class: 'fehlerzeile', role: 'alert', hidden: true });
	const yes = el('button', { type: 'submit', class: 'knopf primaer', text: 'Erlauben' });
	const no = el('button', { type: 'button', class: 'knopf', text: 'Ablehnen' });
	const form = el('form', { novalidate: true }, [
		el('fieldset', { class: 'rechtewahl' }, [el('legend', { text: name + ' darf' }), request.scopes.filter((scope) => scope in RIGHTS).map(rightChoice)]),
		el('p', { class: 'hinweis', text: 'Veröffentlichen kann keine App – das bleibt deine Freigabe. Die Verbindung hält, bis du sie unter „Zugänge“ trennst oder sie 60 Tage nicht benutzt wird.' }),
		fault,
		el('div', { class: 'aktionen' }, [no, yes]),
		el('p', { class: 'meta rest' }, [icon('uhr'), ' ' + minutesText(request.expires_in)]),
	]);

	function fail(text) {
		fault.replaceChildren(...errorLine(text));
		fault.hidden = false;
		yes.disabled = false;
		no.disabled = false;
	}

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		const scopes = [...form.querySelectorAll('input[name="scope"]:checked')].map((input) => input.value);
		fault.hidden = true;
		if (scopes.length === 0) {
			fail(NO_RIGHT_TEXT);
			return;
		}
		yes.disabled = true;
		no.disabled = true;
		decide(page, request, true, scopes, fail);
	});
	no.addEventListener('click', () => {
		fault.hidden = true;
		yes.disabled = true;
		no.disabled = true;
		decide(page, request, false, [], fail);
	});

	show(page, [
		el('p', { class: 'klein', text: 'Verbindung anfragen' }),
		el('h1', { text: name + ' möchte auf deinen Blog zugreifen' }),
		whoList(request),
		request.loopback ? el('p', { class: 'hinweis warn-hinweis' }, [icon('warn'), el('span', {}, ['Die Antwort geht an ein Programm auf diesem Gerät. Erlaube nur, wenn du gerade selbst in Claude Code die Anmeldung gestartet hast (', el('code', { text: '/mcp' }), ').'])]) : null,
		form,
	]);
}

export async function seite() {
	const page = document.getElementById('seite');
	if (page === null) {
		return;
	}
	const params = new URLSearchParams(location.search);
	if (params.has('fehler')) {
		showRejection(page, params.get('fehler'), params.get('host'));
		return;
	}
	const requestId = params.get('anfrage');
	if (!requestId || !/^[a-z0-9-]+$/.test(requestId)) {
		showGenericError(page);
		return;
	}

	showLoading(page);
	try {
		showConsent(page, await api('GET', 'oauth/requests/' + requestId));
	} catch (error) {
		if (error.status === 404 || error.status === 410) {
			showExpired(page);
			return;
		}
		showGenericError(page);
	}
}
