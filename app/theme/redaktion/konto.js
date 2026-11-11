// Das Konto im Kopf: „Redaktion“ mit Zähler und das Menü darunter (Entwurf: .konto, #menue).
import { api } from './api.js';
import { abmelden, onEnde } from './sitzung.js';
import { el, icon } from './ui.js';

let button = null;
let menu = null;

// Einträge, deren Dialog noch nicht gebaut ist, stehen auf `ready: false` und bleiben ausgeblendet.
function entries(overview) {
	const waiting = overview.review + overview.returned;

	return [
		{ ready: true, href: '/#wartet', icon: 'check', label: 'Freigaben', count: waiting },
		{ ready: true, href: '/#wartet', icon: 'stift', label: 'Vorschläge', count: overview.changes },
		{ ready: false, dialog: 'einreichen', icon: 'plus', label: 'Thema einreichen', separatorAfter: true },
		{ ready: false, dialog: 'interessen', icon: 'herz', label: 'Interessen' },
		{ ready: false, dialog: 'statistik', icon: 'chart', label: 'Statistik' },
		{ ready: false, dialog: 'zugaenge', icon: 'schluessel', label: 'Zugänge für Agenten', separatorAfter: true },
		{ ready: true, action: abmelden, icon: 'tuer', label: 'Abmelden' },
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
	for (const entry of entries(overview).filter((candidate) => candidate.ready)) {
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
	menu = el('div', { id: 'menue', popover: 'auto', class: 'menue' });
	button = el('button', { type: 'button', class: 'konto', popovertarget: 'menue', 'aria-label': 'Redaktionsmenü' });
	document.body.append(menu);
	document.querySelector('.kopf-rechts').append(button);
	onEnde(() => {
		button.remove();
		menu.remove();
		button = null;
		menu = null;
	});

	const empty = { review: 0, returned: 0, changes: 0 };
	renderButton(empty);
	renderMenu(empty);

	return aktualisieren().catch(() => null);
}
