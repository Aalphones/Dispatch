// Dialog „Vorschläge“: eingereichte Themen bearbeiten und in der Agenten-Reihenfolge sortieren.
import { api } from './api.js';
import { dialog, el, fehlerZeile, rahmen, zeigeFehler } from './ui.js';

const PRIORITY = { 1: 'Sofort', 2: 'Bald', 3: 'Normal' };
const DEPTH = { standard: 'Standard', deep: 'Tiefenrecherche' };
const INTRO = 'Diese Themen warten auf einen Agenten. Ziehe sie in die gewünschte Reihenfolge oder verschiebe sie mit den Pfeiltasten. Die Priorität bleibt ein zusätzlicher Hinweis.';
const EMPTY = 'Gerade wartet kein Thema. Reiche eins ein, dann taucht es hier auf.';

function submitted(value) {
	const date = new Date(String(value).replace(' ', 'T') + 'Z');

	return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('de-DE', { day: 'numeric', month: 'short' });
}

function textField(name, label, value, multiline = false) {
	const field = multiline
		? el('textarea', { name, rows: name === 'brief' ? 4 : 3 })
		: el('input', { name, type: 'text', maxlength: name === 'title' ? 200 : undefined });
	field.value = value ?? '';
	return el('label', { class: 'feld' }, [el('span', { text: label }), field]);
}

function selectField(name, label, current, choices) {
	const control = el('select', { name }, choices.map(([value, title]) => el('option', { value, text: title, selected: String(current) === value })));
	return el('label', { class: 'feld' }, [el('span', { text: label }), control]);
}

function row(item, index, count, move, edit) {
	const meta = [PRIORITY[item.priority] ?? 'Normal', DEPTH[item.depth] ?? DEPTH.standard];
	if (item.category) {
		meta.push(item.category);
	}
	meta.push('eingereicht ' + submitted(item.created_at));
	const children = [el('b', { text: item.title }), el('small', { class: 'meta', text: meta.join(' · ') })];
	if (item.brief) {
		children.push(el('small', { text: item.brief.length > 160 ? item.brief.slice(0, 159) + '…' : item.brief }));
	}
	const itemRow = el('li', { 'data-id': String(item.id), draggable: 'true' }, [
		el('span', { class: 'vorschlag-griff', 'aria-hidden': 'true', text: '⠿' }),
		el('span', { class: 't' }, children),
		el('span', { class: 'vorschlag-aktionen' }, [
			el('button', { type: 'button', class: 'knopf leise klein-k', 'aria-label': 'Nach oben: ' + item.title, disabled: index === 0, text: '↑', onclick: () => move(index, -1) }),
			el('button', { type: 'button', class: 'knopf leise klein-k', 'aria-label': 'Nach unten: ' + item.title, disabled: index === count - 1, text: '↓', onclick: () => move(index, 1) }),
			el('button', { type: 'button', class: 'knopf klein-k', text: 'Bearbeiten', onclick: () => edit(item) }),
		]),
	]);

	return itemRow;
}

export async function oeffnen() {
	const errorLine = fehlerZeile('themen-fehler');
	const list = el('ul', { class: 'liste vorschlaege' });
	const status = el('p', { class: 'meta', text: 'Lädt …' });
	const state = { items: [], categories: [], saving: false, dragged: null };
	const box = dialog('themen', rahmen('Vorschläge', [el('p', { class: 'hinweis', text: INTRO }), status, list, errorLine], [
		el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' }),
	]), 'breit');

	function draw() {
		list.replaceChildren(...state.items.map((item, index) => row(item, index, state.items.length, move, edit)));
		status.textContent = state.items.length === 0 ? EMPTY : '';
		status.hidden = state.items.length > 0;
	}

	async function reload() {
		const result = await api('GET', 'queue');
		state.items = result.items;
		try {
			state.categories = (await api('GET', 'categories')).categories;
		} catch {}
		draw();
	}

	async function saveOrder() {
		if (state.saving) {
			return;
		}
		state.saving = true;
		const ids = [...list.children].map((item) => Number(item.dataset.id));
		try {
			const result = await api('PATCH', 'queue/order', { ids });
			state.items = result.items;
			draw();
			zeigeFehler(errorLine, '');
		} catch (error) {
			zeigeFehler(errorLine, error.message);
			try { await reload(); } catch {}
		} finally {
			state.saving = false;
		}
	}

	function move(index, direction) {
		const target = index + direction;
		if (target < 0 || target >= list.children.length || state.saving) {
			return;
		}
		const item = list.children[index];
		const before = list.children[target];
		list.insertBefore(item, direction < 0 ? before : before.nextSibling);
		void saveOrder();
	}

	list.addEventListener('dragstart', (event) => {
		const item = event.target.closest('li[data-id]');
		if (item === null || state.saving) {
			e.preventDefault();
			return;
		}
		state.dragged = item;
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', item.dataset.id);
	});
	list.addEventListener('dragover', (event) => {
		if (state.dragged === null || state.saving) {
			return;
		}
		const target = event.target.closest('li[data-id]');
		if (target === null || target === state.dragged) {
			return;
		}
		event.preventDefault();
		const after = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
		list.insertBefore(state.dragged, after ? target.nextSibling : target);
	});
	list.addEventListener('drop', (event) => {
		if (state.dragged !== null) {
			event.preventDefault();
			state.dragged = null;
			void saveOrder();
		}
	});
	list.addEventListener('dragend', () => { state.dragged = null; });

	function edit(item) {
		const id = 'vorschlag-' + item.id;
		const error = fehlerZeile(id + '-fehler');
		const links = Array.isArray(item.links) ? item.links.join('\n') : '';
		const categoryChoices = [['', 'Keine Kategorie'], ...state.categories.map((category) => [category.slug, category.name])];
		if (item.category && !categoryChoices.some(([slug]) => slug === item.category)) {
			categoryChoices.push([item.category, item.category]);
		}
		const form = el('form', { class: 'vorschlag-form', onsubmit: async (event) => {
			event.preventDefault();
			const fields = new FormData(form);
			const submit = form.querySelector('[type="submit"]');
			submit.disabled = true;
			try {
				const result = await api('PATCH', 'queue/' + item.id, {
					title: String(fields.get('title')).trim(),
					brief: String(fields.get('brief')).trim(),
					links: String(fields.get('links')).split(/\R/).map((link) => link.trim()).filter(Boolean),
					priority: Number(fields.get('priority')),
					depth: String(fields.get('depth')),
					category: String(fields.get('category') ?? '') || null,
				});
				state.items = state.items.map((entry) => entry.id === item.id ? result.item : entry);
				draw();
				document.getElementById(id)?.close();
			} catch (saveError) {
				zeigeFehler(error, saveError.message);
				submit.disabled = false;
			}
		} }, [
			textField('title', 'Thema', item.title),
			textField('brief', 'Auftrag und Blickwinkel', item.brief, true),
			textField('links', 'Links (eine Adresse je Zeile)', links, true),
			selectField('priority', 'Priorität', item.priority, [['1', 'Sofort'], ['2', 'Bald'], ['3', 'Normal']]),
			selectField('depth', 'Recherche', item.depth, [['standard', 'Standard'], ['deep', 'Tiefenrecherche']]),
			selectField('category', 'Kategorie', item.category ?? '', categoryChoices),
			error,
			el('div', { class: 'knopfzeile' }, [
				el('button', { type: 'button', class: 'knopf leise', 'data-close': true, text: 'Abbrechen' }),
				el('button', { type: 'submit', class: 'knopf primaer', text: 'Änderungen speichern' }),
			]),
		]);
		dialog(id, rahmen('Vorschlag bearbeiten', form, []), 'breit');
	}

	try {
		await reload();
	} catch (error) {
		status.hidden = true;
		zeigeFehler(errorLine, error.message);
	}
	box.addEventListener('close', () => { state.saving = true; }, { once: true });
}
