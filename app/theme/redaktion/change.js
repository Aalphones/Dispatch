// Vorschlagskarten in „Wartet auf dich“: Änderungen, die ein Agent vorschlägt (Übernehmen oder Ablehnen).
import { api } from './api.js';
import { el, toast } from './ui.js';

const LABELS = { setting: 'Einstellung', document: 'Seite', category: 'Kategorie', media: 'Medium' };
const VALUE_MAX = 80;
const FIELDS_MAX = 3;

function cut(value, max) {
	const text = typeof value === 'string' ? value : (JSON.stringify(value) ?? '');

	return text.length > max ? text.slice(0, max - 1) + '…' : text;
}

function fieldLine(field, before, after) {
	const hasBefore = before !== undefined && before !== null && before !== '';

	return el('div', {}, [
		el('strong', { text: field + ': ' }),
		hasBefore ? [el('del', { text: cut(before, VALUE_MAX) }), ' → '] : '+ ',
		el('ins', { text: cut(after, VALUE_MAX) }),
	]);
}

function beforeAfter(change) {
	const fields = Object.entries(change.payload ?? {});
	const lines = fields.slice(0, FIELDS_MAX).map(([field, value]) => fieldLine(field, change.before?.[field], value));
	if (fields.length > FIELDS_MAX) {
		lines.push(el('div', { text: '… und ' + (fields.length - FIELDS_MAX) + ' weitere Felder' }));
	}

	return el('div', { class: 'vorher-nachher' }, lines);
}

/** Die Karte eines Vorschlags; `done` läuft, wenn er übernommen oder abgelehnt wurde. */
export function karte(change, done) {
	const label = LABELS[change.entity] ?? change.entity;
	const apply = el('button', { type: 'button', class: 'knopf primaer klein-k', text: 'Übernehmen' });
	const reject = el('button', { type: 'button', class: 'knopf klein-k', text: 'Ablehnen' });
	const card = el('article', { class: 'wartekarte', 'data-art': 'change' }, [
		el('span', { class: 'status vorschlag', text: 'Vorschlag · ' + label }),
		el('h3', { text: label + ': ' + change.key }),
		beforeAfter(change),
		el('div', { class: 'zeile' }, [apply, reject]),
	]);

	async function run(action) {
		apply.disabled = true;
		reject.disabled = true;
		try {
			await action();
		} catch (error) {
			apply.disabled = false;
			reject.disabled = false;
			toast(error.message);

			return;
		}
		card.remove();
		done();
	}

	apply.addEventListener('click', () => run(async () => {
		const result = await api('POST', 'changes/' + change.id + '/apply');
		toast(result.rebuilt ? 'Übernommen. Die Website wird neu gebaut.' : 'Übernommen, aber die Website wurde nicht neu gebaut: ' + result.rebuild_error + '. Der nächste Neubau holt es nach.');
	}));
	reject.addEventListener('click', () => run(async () => {
		await api('POST', 'changes/' + change.id + '/reject');
		toast('Abgelehnt.');
	}));

	return card;
}
