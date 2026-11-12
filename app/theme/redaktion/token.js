// Dialog „Zugänge für Agenten“ samt „Neuer Zugang“ (Entwurf: artifacts/entwurf-zugaenge.html).
import { api } from './api.js';
import { ueberallAbmelden } from './sitzung.js';
import { dialog, el, fehlerZeile, icon, info, rahmen, toast, zeigeFehler } from './ui.js';

const DIALOG_ID = 'zugaenge';
const NAME_PATTERN = /^[a-z0-9][a-z0-9-]{0,39}$/;
const SCOPES = [
	{ value: 'blog:read', label: 'Lesen', title: 'Lesen', help: 'Briefing, Artikel, Statistik lesen', hint: 'Briefing, Artikel, Statistik', checked: true },
	{ value: 'blog:write', label: 'Schreiben', title: 'Schreiben und einreichen', help: 'Entwürfe anlegen, Bilder hochladen, zur Freigabe einreichen. Veröffentlichen kann kein Zugang.', hint: 'Entwürfe, Bilder, Einreichen', checked: true },
	{ value: 'blog:admin', label: 'Einstellungen', title: 'Einstellungen und Kategorien ändern', help: 'Richtlinien, Präferenzen, Kategorien. Öffentliches wird nur vorgeschlagen.', hint: 'Richtlinien, Präferenzen, Kategorien', checked: false },
];
const EXPIRY_OPTIONS = [['', 'nie'], ['30', 'in 30 Tagen'], ['90', 'in 90 Tagen'], ['365', 'in einem Jahr']];
const INTRO = 'Mit einem Zugang dürfen Claude Code, eine geplante Aufgabe oder die Claude-App Artikel einreichen. Veröffentlichen kann kein Zugang – das bleibt deine Freigabe.';
const TOKEN_GROUP = 'Zugänge mit Token · Claude Code, geplante Aufgaben';
const APP_GROUP = 'Verbundene Apps · per Zustimmung';
const LEAVE_ANIMATION_MS = 260;

function view(title, body, footer) {
	const box = dialog(DIALOG_ID, rahmen(title, body, footer), 'breit');
	box.querySelector('h2').focus({ preventScroll: true });

	return box;
}

function formatDate(value) {
	return new Date(value.replace(' ', 'T')).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTime(value) {
	return new Date(value.replace(' ', 'T')).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

/** Eine aufklappende Nachfrage im Dialog; `ask` öffnet sie, `onYes` bestätigt. */
function confirmRow(question, confirmLabel, onYes) {
	const no = el('button', { type: 'button', class: 'knopf klein-k', text: 'Abbrechen' });
	const yes = el('button', { type: 'button', class: 'knopf primaer klein-k', text: confirmLabel });
	const row = el('div', { class: 'nachfrage' }, el('div', {}, [
		el('p', { text: question }),
		el('div', { class: 'zeile' }, [no, yes]),
	]));
	const ask = () => {
		row.classList.add('offen');
		no.focus();
	};
	no.addEventListener('click', () => row.classList.remove('offen'));
	yes.addEventListener('click', () => onYes(yes));

	return { row, ask };
}

function scopeLabels(scopes) {
	return el('span', { class: 'rechte' }, SCOPES.filter((scope) => scopes.includes(scope.value)).map((scope) => el('span', {
		class: scope.value === 'blog:admin' ? 'recht admin' : 'recht',
		text: scope.label,
	})));
}

function tokenRow(token, errorLine, reload) {
	const used = token.last_used_at ? 'zuletzt ' + formatDateTime(token.last_used_at) : 'noch nie benutzt';
	const expiry = token.expires_at ? 'läuft ab ' + formatDate(token.expires_at) : 'kein Ablauf';
	const item = el('li', { class: 'zugang' });
	const confirmation = confirmRow('„' + token.name + '“ widerrufen? Agenten mit diesem Zugang verlieren sofort den Zugriff.', 'Widerrufen', async (button) => {
		button.disabled = true;
		zeigeFehler(errorLine, '');
		try {
			await api('DELETE', 'tokens/' + token.id);
			item.classList.add('geht');
			toast('Zugang „' + token.name + '“ widerrufen.');
			setTimeout(reload, LEAVE_ANIMATION_MS);
		} catch (error) {
			button.disabled = false;
			zeigeFehler(errorLine, error.message);
		}
	});
	item.append(
		icon('schluessel'),
		el('span', { class: 't' }, [el('b', { text: token.name }), el('small', { text: used + ' · ' + expiry }), scopeLabels(token.scopes)]),
		el('button', { type: 'button', class: 'knopf leise gefahr klein-k', 'aria-label': token.name + ' widerrufen', text: 'Widerrufen', onclick: confirmation.ask }),
		confirmation.row,
	);

	return item;
}

function grantRow(grant, errorLine, reload) {
	const used = grant.last_used_at ? 'zuletzt ' + formatDateTime(grant.last_used_at) : 'noch nie benutzt';
	const item = el('li', { class: 'zugang' });
	const confirmation = confirmRow('„' + grant.client_name + '“ trennen? Die App verliert sofort den Zugriff und muss sich neu verbinden.', 'Trennen', async (button) => {
		button.disabled = true;
		zeigeFehler(errorLine, '');
		try {
			await api('DELETE', 'oauth/grants/' + grant.family);
			item.classList.add('geht');
			toast('„' + grant.client_name + '“ getrennt.');
			setTimeout(reload, LEAVE_ANIMATION_MS);
		} catch (error) {
			button.disabled = false;
			zeigeFehler(errorLine, error.message);
		}
	});
	item.append(
		icon('ki'),
		el('span', { class: 't' }, [
			el('b', {}, [grant.client_name + ' ', el('span', { class: 'host', text: '· ' + grant.client_host })]),
			el('small', { text: 'verbunden seit ' + formatDate(grant.connected_at) + ' · ' + used }),
			scopeLabels(grant.scopes),
		]),
		el('button', { type: 'button', class: 'knopf leise gefahr klein-k', 'aria-label': grant.client_name + ' trennen', text: 'Trennen', onclick: confirmation.ask }),
		confirmation.row,
	);

	return item;
}

/** Anleitung, solange keine App verbunden ist: drei Schritte und die Adresse zum Kopieren. */
function appGuide() {
	const address = el('code', { id: 'mcp-url', text: location.origin + '/dispatch/mcp' });
	const button = el('button', { type: 'button', class: 'knopf klein-k', text: 'Kopieren' });
	button.addEventListener('click', async () => {
		try {
			await navigator.clipboard.writeText(address.textContent);
		} catch {
			getSelection().selectAllChildren(address);
			toast('Kopieren ist hier nicht erlaubt – der Text ist markiert.');

			return;
		}
		button.textContent = 'Kopiert';
		button.classList.add('ok');
		toast('Kopiert.');
	});

	return el('div', { class: 'leer anleitung' }, [
		el('b', { text: 'Keine App verbunden.' }),
		' So verbindest du die Claude-App (Handy, Web, Desktop):',
		el('ol', {}, [
			el('li', { text: 'In Claude: Einstellungen → Connectors → „Eigenen Connector hinzufügen“.' }),
			el('li', { text: 'Diese Adresse eintragen, dann „Verbinden“.' }),
			el('li', { text: 'Auf der Seite, die sich öffnet, „Erlauben“.' }),
		]),
		el('div', { class: 'kopierfeld' }, [address, button]),
	]);
}

function emptyList() {
	return el('div', { class: 'leer' }, [icon('schluessel'), 'Noch kein Zugang.', el('br'), 'Lege einen an, damit Claude Code Artikel für dich einreichen kann.']);
}

async function logoutEverywhere(button, errorLine) {
	button.disabled = true;
	try {
		await ueberallAbmelden();
		document.getElementById(DIALOG_ID)?.close();
	} catch (error) {
		button.disabled = false;
		zeigeFehler(errorLine, error.message);
	}
}

async function showList() {
	const errorLine = fehlerZeile('zugaenge-fehler');
	const tokenList = el('div', { class: 'stand' }, el('p', { class: 'meta', text: 'Lädt …' }));
	const appList = el('div', { class: 'stand' }, el('p', { class: 'meta', text: 'Lädt …' }));
	const everywhere = confirmRow('Überall abmelden? Du wirst auch hier abgemeldet. Die Zugänge der Agenten bleiben bestehen.', 'Überall abmelden', (button) => logoutEverywhere(button, errorLine));
	view('Zugänge für Agenten', [
		el('p', { class: 'hinweis', text: INTRO }),
		errorLine,
		el('h3', { class: 'gruppe-titel', text: TOKEN_GROUP }),
		tokenList,
		el('h3', { class: 'gruppe-titel', text: APP_GROUP }),
		appList,
		everywhere.row,
	], [
		el('button', { type: 'button', class: 'knopf', text: 'Überall abmelden', onclick: everywhere.ask }),
		el('button', { type: 'button', class: 'knopf primaer', onclick: showForm }, [icon('plus'), 'Neuer Zugang …']),
	]);

	await Promise.all([fillTokens(tokenList, errorLine), fillApps(appList, errorLine)]);
}

async function fillTokens(list, errorLine) {
	try {
		const { tokens } = await api('GET', 'tokens');
		if (tokens.length === 0) {
			list.replaceChildren(emptyList());

			return;
		}
		list.replaceChildren(el('ul', { class: 'liste' }, tokens.map((token) => tokenRow(token, errorLine, showList))));
	} catch (error) {
		list.replaceChildren();
		zeigeFehler(errorLine, error.message);
	}
}

async function fillApps(list, errorLine) {
	try {
		const { grants } = await api('GET', 'oauth/grants');
		if (grants.length === 0) {
			list.replaceChildren(appGuide());

			return;
		}
		list.replaceChildren(el('ul', { class: 'liste' }, grants.map((grant) => grantRow(grant, errorLine, showList))));
	} catch (error) {
		list.replaceChildren();
		zeigeFehler(errorLine, error.message);
	}
}

function scopeChoice(scope) {
	return el('label', { class: 'wahl' }, [
		el('input', { type: 'checkbox', name: 'scope', value: scope.value, checked: scope.checked }),
		el('b', { text: scope.title }),
		info(scope.help),
		el('small', { text: scope.hint }),
	]);
}

function readForm(form) {
	const days = form.elements.expires_days.value;

	return {
		name: form.elements.name.value.trim(),
		scopes: [...form.querySelectorAll('input[name="scope"]:checked')].map((input) => input.value),
		expires_days: days === '' ? null : Number(days),
	};
}

function showForm() {
	const nameInput = el('input', { id: 'zugang-name', name: 'name', autocomplete: 'off', autocapitalize: 'none', spellcheck: 'false', placeholder: 'z. B. laptop oder geplante-aufgabe', 'aria-describedby': 'zugang-name-hilfe zugang-name-fehler' });
	const nameError = fehlerZeile('zugang-name-fehler');
	const scopeError = fehlerZeile('zugang-recht-fehler');
	const errorLine = fehlerZeile('zugang-fehler');
	const form = el('form', { id: 'zugang-form', novalidate: true }, [
		el('label', { class: 'feld' }, ['Name', nameInput, el('span', { class: 'feldhilfe', id: 'zugang-name-hilfe', text: 'Kleinbuchstaben, Ziffern und Bindestrich. Erscheint im Protokoll als Urheber der Änderungen.' })]),
		nameError,
		el('fieldset', { class: 'rechtewahl' }, [el('legend', { text: 'Darf' }), SCOPES.map(scopeChoice)]),
		scopeError,
		el('label', { class: 'feld' }, ['Läuft ab', el('select', { name: 'expires_days' }, EXPIRY_OPTIONS.map(([value, text]) => el('option', { value, text })))]),
		errorLine,
	]);
	const submit = el('button', { type: 'submit', class: 'knopf primaer', form: 'zugang-form', text: 'Zugang anlegen' });

	function invalidate(message) {
		zeigeFehler(nameError, message);
		nameInput.setAttribute('aria-invalid', 'true');
		nameInput.focus();
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const values = readForm(form);
		zeigeFehler(errorLine, '');
		zeigeFehler(nameError, '');
		nameInput.removeAttribute('aria-invalid');
		zeigeFehler(scopeError, values.scopes.length === 0 ? 'Wähle mindestens ein Recht.' : '');
		if (!NAME_PATTERN.test(values.name)) {
			invalidate(values.name === '' ? 'Gib dem Zugang einen Namen.' : 'Nur Kleinbuchstaben, Ziffern und Bindestrich, höchstens 40 Zeichen, nicht mit Bindestrich beginnen.');

			return;
		}
		if (values.scopes.length === 0) {
			return;
		}

		submit.disabled = true;
		try {
			showResult(await api('POST', 'tokens', values));
		} catch (error) {
			submit.disabled = false;
			if (error.error === 'name_taken') {
				invalidate('Einen Zugang „' + values.name + '“ gibt es schon. Wähle einen anderen Namen oder widerrufe den alten.');

				return;
			}
			zeigeFehler(errorLine, error.message);
		}
	});

	view('Neuer Zugang', form, [
		el('button', { type: 'button', class: 'knopf', text: 'Zurück', onclick: showList }),
		submit,
	]);
	nameInput.focus();
}

function copyField(label, node) {
	const button = el('button', { type: 'button', class: 'knopf klein-k', text: 'Kopieren' });
	button.addEventListener('click', async () => {
		try {
			await navigator.clipboard.writeText(node.textContent);
		} catch {
			getSelection().selectAllChildren(node);
			zeigeFehler(document.getElementById('zugang-kopier-fehler'), 'Kopieren ist hier nicht erlaubt – der Text ist markiert, bitte mit Strg+C kopieren.');

			return;
		}
		button.textContent = 'Kopiert';
		button.classList.add('ok');
		toast('Kopiert.');
	});

	return [el('p', { class: 'ergebnis-titel', text: label }), el('div', { class: 'kopierfeld' }, [node, button])];
}

function showResult(created) {
	const command = el('pre', { 'aria-label': 'Befehl für Claude Code', text: created.command });
	const token = el('code', { 'aria-label': 'Token', text: created.token });
	const box = view('Zugang angelegt', [
		el('p', { class: 'erfolg' }, [icon('check'), el('span', { text: 'Zugang „' + created.name + '“ angelegt' })]),
		el('p', { class: 'hinweis warn-hinweis', text: 'Das Token siehst du nur jetzt. Kopiere es oder gleich den fertigen Befehl – danach ist es nicht mehr abrufbar.' }),
		fehlerZeile('zugang-kopier-fehler'),
		copyField('Befehl für Claude Code', command),
		copyField('Nur das Token', token),
	], [el('button', { type: 'button', class: 'knopf primaer', text: 'Fertig', onclick: () => {
		discardSecret(command, token);
		showList();
	} })]);
	box.addEventListener('close', () => discardSecret(command, token), { once: true });
}

// Der Klartext steht nur im Ergebnis; danach ist er aus der Seite verschwunden.
function discardSecret(...nodes) {
	for (const node of nodes) {
		node.textContent = '';
	}
}

/** Öffnet die Liste der Zugänge (Menü „Zugänge für Agenten“). */
export function oeffnen() {
	return showList();
}
