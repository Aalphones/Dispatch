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

const HELP_RENAME = 'Ändert den Anzeigenamen; die Adresse (Slug) bleibt, damit bestehende Links weiter gehen.';
const HELP_DELETE = 'Löscht die Kategorie endgültig. Artikel darin werden auf eine Ersatz-Kategorie verschoben.';
const HELP_CREATE = 'Der Slug (Adresse) entsteht aus dem Namen. Neue Kategorien starten mit Interesse 3 und sind sofort aktiv.';
const DELETE_DIALOG = 'kategorie-loeschen';

/** Nach einer Aktion mit Neubau: Erfolg melden, ein gescheiterter Neubau wird genannt. */
function reportRebuild(done, result) {
	if (result.rebuilt === false) {
		toast(done + ', aber die Website wurde nicht neu gebaut: ' + result.rebuild_error + '. Der nächste Neubau holt es nach.');
		return;
	}
	toast(done + '.');
}

/** Die Übersicht kennt nur aktive Kategorien; ohne die gelöschte und ihre Unterkategorien. */
function replacements(categories, category) {
	return categories.filter((candidate) => candidate.id !== category.id && candidate.parent_id !== category.id);
}

/** Der Dialog zum Löschen: erst Bestätigung, bei zugeordneten Artikeln danach die Auswahl der Ersatz-Kategorie. */
function deleteDialog(category, categories, reload) {
	const errorLine = fehlerZeile('kategorie-loeschen-fehler');

	async function run(button, moveTo) {
		button.disabled = true;
		zeigeFehler(errorLine, '');
		try {
			const result = await api('DELETE', 'categories/' + category.id, moveTo === undefined ? undefined : { move_to: moveTo });
			document.getElementById(DELETE_DIALOG).close();
			reportRebuild('Kategorie „' + category.name + '“ gelöscht', result);
			reload();
		} catch (error) {
			button.disabled = false;
			if (error.error === 'category_in_use') {
				askReplacement(error.details.count);
				return;
			}
			zeigeFehler(errorLine, error.message);
		}
	}

	function askReplacement(count) {
		const select = el('select', { id: 'kategorie-ersatz', 'aria-label': 'Ersatz-Kategorie' }, replacements(categories, category).map((candidate) => (
			el('option', { value: String(candidate.id), text: (candidate.parent_id === null ? '' : '– ') + candidate.name })
		)));
		const confirm = el('button', { type: 'button', class: 'knopf primaer gefahr klein-k', text: 'Löschen und verschieben', onclick: () => run(confirm, Number(select.value)) });
		dialog(DELETE_DIALOG, rahmen('Kategorie „' + category.name + '“ löschen', [
			el('p', { text: count + (count === 1 ? ' Artikel gehört' : ' Artikel gehören') + ' dazu. Verschieben nach:' }),
			el('div', { class: 'zeile' }, [select, info(HELP_DELETE)]),
			errorLine,
		], [
			el('button', { type: 'button', class: 'knopf klein-k', 'data-close': true, text: 'Abbrechen' }),
			confirm,
		]));
	}

	const confirm = el('button', { type: 'button', class: 'knopf primaer gefahr klein-k', text: 'Löschen', onclick: () => run(confirm) });
	dialog(DELETE_DIALOG, rahmen('Kategorie löschen', [
		el('p', { text: 'Kategorie „' + category.name + '“ wirklich löschen? Das lässt sich nicht rückgängig machen.' }),
		errorLine,
	], [
		el('button', { type: 'button', class: 'knopf klein-k', 'data-close': true, text: 'Abbrechen' }),
		confirm,
	]));
}

/** Name als Eingabefeld: Enter speichert, Escape oder unveränderter Fokusverlust bricht ab. */
function renameField(category, onSave, onCancel) {
	const input = el('input', { class: 'chip-eingabe umbenennen', value: category.name, 'aria-label': 'Neuer Name für ' + category.name, autocomplete: 'off' });
	let isDone = false;
	input.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			event.stopPropagation();
			isDone = true;
			onCancel();
		}
		if (event.key === 'Enter') {
			event.preventDefault();
			const name = input.value.trim();
			isDone = true;
			if (name === '' || name === category.name) {
				onCancel();
				return;
			}
			onSave(name);
		}
	});
	input.addEventListener('blur', () => {
		if (!isDone) {
			isDone = true;
			onCancel();
		}
	});

	return input;
}

/** Eine Zeile; speichert ein Feld und baut sich danach aus der Antwort der API neu. */
function interestRow(category, categories, errorLine, reload) {
	const row = el('div', { class: category.parent_id === null ? 'int' : 'int unter' });
	let isRenaming = false;

	async function rename(name) {
		zeigeFehler(errorLine, '');
		try {
			const { category: updated, ...rebuild } = await api('PATCH', 'categories/' + category.id, { name });
			Object.assign(category, updated);
			isRenaming = false;
			render();
			reportRebuild('Umbenannt in „' + category.name + '“', rebuild);
		} catch (error) {
			isRenaming = false;
			render();
			zeigeFehler(errorLine, error.message);
		}
	}

	function startRename() {
		isRenaming = true;
		render();
		row.querySelector('.umbenennen')?.focus();
	}

	function cancelRename() {
		isRenaming = false;
		render();
	}

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
			isRenaming ? renameField(category, rename, cancelRename) : el('b', { text: category.name }),
			el('button', { type: 'button', class: 'knopf leise klein-k', 'aria-label': category.name + ' umbenennen', text: 'Umbenennen', onclick: startRename }),
			info(HELP_RENAME),
			info(HELP),
			weightGroup(category, save),
			el('span', { class: 'gewicht-text', text: WORDING[category.interest] }),
			signalLine(category, save),
			el('div', { class: 'signale' }, FIELDS.map(([field, label]) => chipEditor(category, field, label, save))),
			el('div', { class: 'signale' }, [
				el('button', { type: 'button', class: 'knopf leise gefahr klein-k', 'aria-label': category.name + ' löschen', text: 'Löschen', onclick: () => deleteDialog(category, categories, reload) }),
				info(HELP_DELETE),
			]),
		);
	}
	render();

	return row;
}

/** Formular „Neue Kategorie“: Name, optional Oberkategorie (nur Hauptkategorien). */
function newCategoryForm(categories, errorLine, reload) {
	const name = el('input', { class: 'chip-eingabe', placeholder: 'Name der neuen Kategorie', 'aria-label': 'Name der neuen Kategorie', autocomplete: 'off' });
	const parent = el('select', { 'aria-label': 'Oberkategorie' }, [
		el('option', { value: '', text: 'Keine Oberkategorie' }),
		categories.filter((category) => category.parent_id === null).map((category) => el('option', { value: String(category.id), text: category.name })),
	]);
	const create = el('button', { type: 'button', class: 'knopf primaer klein-k', text: 'Anlegen' });

	async function submit() {
		const value = name.value.trim();
		if (value === '') {
			zeigeFehler(errorLine, 'Der Name fehlt.');
			name.focus();
			return;
		}
		zeigeFehler(errorLine, '');
		create.disabled = true;
		const body = { name: value };
		if (parent.value !== '') {
			body.parent_id = Number(parent.value);
		}
		try {
			const result = await api('POST', 'categories', body);
			reportRebuild('Kategorie „' + value + '“ angelegt', result);
			reload();
		} catch (error) {
			create.disabled = false;
			zeigeFehler(errorLine, error.message);
		}
	}

	create.addEventListener('click', submit);
	name.addEventListener('keydown', (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			submit();
		}
	});

	return el('div', { class: 'neue-kategorie' }, [name, parent, create, info(HELP_CREATE)]);
}

/** Der Dialog „Deine Interessen“ aus dem Menü. */
export async function interessen() {
	const errorLine = fehlerZeile('interessen-fehler');
	const list = el('div', { class: 'interessen-liste' }, el('p', { class: 'meta', text: 'Lädt …' }));
	const form = el('div', {});
	dialog('interessen', rahmen('Deine Interessen', [el('p', { class: 'hinweis', text: INTRO }), form, errorLine, list], [
		el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' }),
	]), 'breit');

	try {
		const { categories } = await api('GET', 'categories');
		form.replaceChildren(newCategoryForm(categories, errorLine, interessen));
		list.replaceChildren(...inTreeOrder(categories).map((category) => interestRow(category, categories, errorLine, interessen)));
	} catch (error) {
		list.replaceChildren();
		zeigeFehler(errorLine, error.message);
	}
}
