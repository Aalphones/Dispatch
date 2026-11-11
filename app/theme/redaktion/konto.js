// Das Konto im Kopf: „Redaktion“ mit Zähler und das Menü darunter (Entwurf: .konto, #menue).
import { api } from './api.js';
import { abmelden, onEnde } from './sitzung.js';
import { el, icon } from './ui.js';

let button = null;
let menu = null;
let fab = null;

// Die Dialoge laden ihr Modul erst beim ersten Öffnen.
const DIALOGS = {
	einreichen: async () => (await import('./queue.js')).oeffnen(),
	interessen: async () => (await import('./category.js')).interessen(),
	statistik: async () => (await import('./stats.js')).dialogOeffnen(),
	zugaenge: async () => (await import('./token.js')).oeffnen(),
};

function entries(overview) {
	const waiting = overview.review + overview.returned;

	return [
		{ href: '/#wartet', icon: 'check', label: 'Freigaben', count: waiting },
		{ href: '/#wartet', icon: 'stift', label: 'Vorschläge', count: overview.changes },
		{ action: DIALOGS.einreichen, icon: 'plus', label: 'Thema einreichen', separatorAfter: true },
		{ action: DIALOGS.interessen, icon: 'herz', label: 'Interessen' },
		{ action: DIALOGS.statistik, icon: 'chart', label: 'Statistik' },
		{ action: DIALOGS.zugaenge, icon: 'schluessel', label: 'Zugänge für Agenten', separatorAfter: true },
		{ action: abmelden, icon: 'tuer', label: 'Abmelden' },
	];
}

function counter(count) {
	return count > 0 ? el('span', { class: 'zaehler', text: String(count) }) : false;
}

function menuItem(entry) {
	const content = [icon(entry.icon), entry.label, counter(entry.count || 0)];
	if (entry.href) {
		return el('a', { href: entry.href, onclick: () => menu.hidePopover() }, content);
	}

	return el('button', {
		type: 'button',
		onclick: () => {
			menu.hidePopover();
			entry.action();
		},
	}, content);
}

function renderMenu(overview) {
	const items = [el('div', { class: 'kopfzeile', text: 'Angemeldet als Herausgeber' })];
	for (const entry of entries(overview)) {
		items.push(menuItem(entry));
		if (entry.separatorAfter) {
			items.push(el('hr'));
		}
	}
	menu.replaceChildren(...items);
}

function renderButton(overview) {
	const count = overview.review + overview.returned + overview.changes;
	const content = [
		el('span', { class: 'avatar' }, icon('person')),
		el('span', { class: 'text', text: 'Redaktion' }),
		counter(count),
	];
	button.replaceChildren(...content);
	button.setAttribute('aria-label', count > 0 ? 'Redaktionsmenü, ' + count + ' offen' : 'Redaktionsmenü');
}

/** Zähler und Menü neu aus der Übersicht füllen. */
export async function aktualisieren() {
	if (button === null) {
		return;
	}
	const overview = await api('GET', 'overview');
	renderButton(overview);
	renderMenu(overview);
}

export function start() {
	fab = el('button', { type: 'button', class: 'fab', 'data-red': true, onclick: () => DIALOGS.einreichen() }, [icon('plus'), el('span', { class: 'text', text: 'Thema einreichen' })]);
	menu = el('div', { id: 'menue', popover: 'auto', class: 'menue' });
	button = el('button', { type: 'button', class: 'konto', popovertarget: 'menue', 'aria-label': 'Redaktionsmenü' });
	document.body.append(menu);
	if (document.body.dataset.seite !== 'vorschau') {
		document.body.append(fab);
	}
	document.querySelector('.kopf-rechts').append(button);
	onEnde(() => {
		button.remove();
		menu.remove();
		fab.remove();
		button = null;
		menu = null;
	});

	const empty = { review: 0, returned: 0, changes: 0 };
	renderButton(empty);
	renderMenu(empty);

	return aktualisieren().catch(() => null);
}
