// Kategorien-Seite: Interesse 0–5 an jeder Karte (Entwurf: .gewicht-inline).
import { api } from './api.js';
import { dialog, el, fehlerZeile, info, rahmen, toast, zeigeFehler } from './ui.js';

const LEVELS = [0, 1, 2, 3, 4, 5];
const HINT = 'Angemeldet: Die Zahl oben rechts an jeder Karte ist dein Interesse. Antippen ändert es sofort.';
const HELP = '0 = Agenten schreiben nie von selbst darüber, 5 = bevorzugt. Selbst eingereichte Themen schreiben sie immer.';

function nameOf(card) {
	return (card.querySelector('h2')?.firstChild?.textContent ?? '').trim();
}

function paint(group, value) {
	for (const button of group.querySelectorAll('button')) {
		button.setAttribute('aria-pressed', String(Number(button.textContent) === value));
	}
}

function group(card, id, value) {
	const name = nameOf(card);
	const box = el('span', { class: 'gewicht', role: 'group', 'aria-label': 'Interesse ' + name });
	for (const level of LEVELS) {
		box.append(el('button', {
			type: 'button',
			'aria-pressed': String(level === value),
			text: String(level),
			onclick: async (event) => {
				event.preventDefault();
				event.stopPropagation();
				box.querySelectorAll('button').forEach((button) => {
					button.disabled = true;
				});
				try {
					await api('PATCH', 'categories/' + id, { interest: level });
					paint(box, level);
					toast('Interesse für ' + name + ': ' + level);
				} catch (error) {
					toast(error.message);
				} finally {
					box.querySelectorAll('button').forEach((button) => {
						button.disabled = false;
					});
				}
			},
		}));
	}

	return el('span', { class: 'gewicht-inline', 'data-red': true }, box);
}

export async function gewichte() {
	const grid = document.querySelector('.katgrid');
	if (grid === null) {
		return;
	}
	const { categories } = await api('GET', 'categories');
	const interests = new Map(categories.map((category) => [category.id, category.interest]));

	grid.before(el('p', { class: 'hinweis', 'data-red': true }, [HINT + ' ', info(HELP)]));
	for (const card of grid.querySelectorAll('.katkarte[data-category-id]')) {
		const id = Number(card.dataset.categoryId);
		if (interests.has(id)) {
			card.prepend(group(card, id, interests.get(id)));
		}
	}
}

const WORDING = ['nie von selbst', 'selten', 'ab und zu', 'gelegentlich', 'oft', 'bevorzugt'];
const FIELDS = [['keywords', 'Gern über'], ['avoid', 'Bitte nicht']];
const INTRO = 'Steuert, worüber die Agenten von sich aus schreiben. Deine „Mehr davon / Weniger“-Klicks fließen mit ein; die Zahl stellst du hier oder direkt auf der Seite „Alle Kategorien“.';

/** Hauptkategorien in Reihenfolge, jede gefolgt von ihren Unterkategorien. */
function inTreeOrder(categories) {
	const ordered = [];
	for (const root of categories.filter((category) => category.parent_id === null)) {
		ordered.push(root, ...categories.filter((category) => category.parent_id === root.id));
	}

	return ordered;
}

function chipEditor(category, field, label, save) {
	const input = el('input', { class: 'chip-eingabe', placeholder: 'Stichwort + Enter', 'aria-label': label + ' – Stichwort hinzufügen', autocomplete: 'off' });
	input.addEventListener('keydown', (event) => {
		const word = input.value.trim();
		if (event.key !== 'Enter') {
			return;
		}
		event.preventDefault();
		if (word !== '' && !category[field].includes(word)) {
			save(field, [...category[field], word]);
		}
	});
	const chips = category[field].map((word) => el('span', { class: 'chip' }, [
		word,
		el('button', {
			type: 'button',
			class: 'x',
			'aria-label': word + ' entfernen',
			text: '×',
			onclick: () => save(field, category[field].filter((entry) => entry !== word)),
		}),
	]));

	return el('div', { class: 'stichwoerter', 'data-feld': field }, [el('span', { class: 'meta', text: label + ':' }), chips, input]);
}

function weightGroup(category, save) {
	const box = el('span', { class: 'gewicht hell', role: 'group', 'aria-label': 'Interesse ' + category.name });
	for (const level of LEVELS) {
		box.append(el('button', {
			type: 'button',
			'aria-pressed': String(level === category.interest),
			text: String(level),
			onclick: () => save('interest', level),
		}));
	}

	return box;
}

function signalLine(category, save) {
	const { more, less } = category.signals;
	const parts = [];
	if (more + less > 0) {
		parts.push(el('span', { class: 'mehr-s', text: '▲ ' + more + '× mehr davon' }), el('span', { class: 'weniger-s', text: '▼ ' + less + '× weniger' }));
	}
	if (category.suggestion !== null) {
		parts.push(
			el('span', { text: 'Vorschlag aus deinen Klicks: auf ' + category.suggestion + '?' }),
			el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Übernehmen', onclick: () => save('interest', category.suggestion) }),
		);
	}

	return el('div', { class: 'signale' }, parts);
}

/** Eine Zeile; speichert ein Feld und baut sich danach aus der Antwort der API neu. */
function interestRow(category, errorLine) {
	const row = el('div', { class: category.parent_id === null ? 'int' : 'int unter' });

	async function save(field, value) {
		zeigeFehler(errorLine, '');
		try {
			const { category: updated } = await api('PATCH', 'categories/' + category.id, { [field]: value });
			Object.assign(category, updated);
			render();
			// Der Neubau der Zeile nimmt dem bedienten Element den Fokus.
			const target = field === 'interest' ? '[aria-pressed="true"]' : '[data-feld="' + field + '"] input';
			row.querySelector(target)?.focus({ preventScroll: true });
		} catch (error) {
			zeigeFehler(errorLine, error.message);
		}
	}

	function render() {
		row.replaceChildren(
			el('b', { text: category.name }),
			info(HELP),
			weightGroup(category, save),
			el('span', { class: 'gewicht-text', text: WORDING[category.interest] }),
			signalLine(category, save),
			el('div', { class: 'signale' }, FIELDS.map(([field, label]) => chipEditor(category, field, label, save))),
		);
	}
	render();

	return row;
}

/** Der Dialog „Deine Interessen“ aus dem Menü. */
export async function interessen() {
	const errorLine = fehlerZeile('interessen-fehler');
	const list = el('div', { class: 'interessen-liste' }, el('p', { class: 'meta', text: 'Lädt …' }));
	dialog('interessen', rahmen('Deine Interessen', [el('p', { class: 'hinweis', text: INTRO }), errorLine, list], [
		el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' }),
	]), 'breit');

	try {
		const { categories } = await api('GET', 'categories');
		list.replaceChildren(...inTreeOrder(categories).map((category) => interestRow(category, errorLine)));
	} catch (error) {
		list.replaceChildren();
		zeigeFehler(errorLine, error.message);
	}
}
