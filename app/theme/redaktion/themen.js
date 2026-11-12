// Dialog „Vorschläge“: die eingereichten Themen, die noch kein Agent aufgegriffen hat.
import { api } from './api.js';
import { dialog, el, fehlerZeile, rahmen, zeigeFehler } from './ui.js';

const PRIORITY = { 1: 'Sofort', 2: 'Bald', 3: 'Normal' };
const DEPTH = { standard: 'Standard', deep: 'Tiefenrecherche' };
const INTRO = 'Diese Themen sind eingereicht und warten darauf, dass ein Agent sie aufgreift. Die Reihenfolge ist die, in der sie drankommen.';
const EMPTY = 'Gerade wartet kein Thema. Reiche eins ein, dann taucht es hier auf.';

/** Eingangsdatum der Datenbank (UTC, ohne Zone) als „12. Nov.“. */
function submitted(value) {
	const date = new Date(String(value).replace(' ', 'T') + 'Z');

	return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('de-DE', { day: 'numeric', month: 'short' });
}

function row(item) {
	const meta = [PRIORITY[item.priority] ?? 'Normal', DEPTH[item.depth] ?? DEPTH.standard];
	if (item.category) {
		meta.push(item.category);
	}
	meta.push('eingereicht ' + submitted(item.created_at));
	const children = [el('b', { text: item.title }), el('small', { class: 'meta', text: meta.join(' · ') })];
	if (item.brief) {
		children.push(el('small', { text: item.brief.length > 160 ? item.brief.slice(0, 159) + '…' : item.brief }));
	}

	return el('li', {}, el('span', { class: 't' }, children));
}

export async function oeffnen() {
	const errorLine = fehlerZeile('themen-fehler');
	const list = el('ul', { class: 'liste' });
	const status = el('p', { class: 'meta', text: 'Lädt …' });
	dialog('themen', rahmen('Vorschläge', [el('p', { class: 'hinweis', text: INTRO }), status, list, errorLine], [
		el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' }),
	]), 'breit');

	try {
		const { items } = await api('GET', 'queue');
		list.replaceChildren(...items.map(row));
		status.textContent = items.length === 0 ? EMPTY : '';
		status.hidden = items.length > 0;
	} catch (error) {
		status.hidden = true;
		zeigeFehler(errorLine, error.message);
	}
}
