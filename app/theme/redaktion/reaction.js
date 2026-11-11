// „Mehr davon / Weniger“: Knöpfe an Aufmacher, Karten und Redaktionskasten, ein Zustand je Artikel.
import { api } from './api.js';
import { el, icon, toast } from './ui.js';

const widgets = new Map();
const current = new Map();
let isLoaded = false;

function paint(id) {
	const value = current.get(id) ?? null;
	for (const box of widgets.get(id) ?? []) {
		box.querySelector('.mehr').setAttribute('aria-pressed', String(value === 'more'));
		box.querySelector('.weniger').setAttribute('aria-pressed', String(value === 'less'));
		box.classList.toggle('gesetzt', value !== null);
	}
}

function topic(node) {
	const label = node.closest('.karte, .aufmacher, .artikel')?.querySelector('.dach')?.textContent ?? '';

	return label.split('·')[0].trim() || 'diesem Thema';
}

async function save(id, value) {
	try {
		await api('POST', 'posts/' + id + '/reaction', { reaction: value });
	} catch (error) {
		toast(error.message);

		return false;
	}
	current.set(id, value);
	paint(id);

	return true;
}

async function choose(id, kind, box) {
	const before = current.get(id) ?? null;
	const next = before === kind ? null : kind;
	if (!await save(id, next)) {
		return;
	}
	const name = topic(box);
	const text = next === 'more' ? 'Notiert: mehr zu ' + name + '. Fließt ins nächste Briefing.' : next === 'less' ? 'Notiert: weniger zu ' + name + '.' : 'Reaktion entfernt.';
	toast(text, async () => {
		if (await save(id, before)) {
			toast('Zurückgenommen.');
		}
	});
}

/** Die Knöpfe für einen Artikel; `mini` = klein an der Karte. Der Zustand folgt dem gemeinsamen Stand. */
export function widget(id, mini = false) {
	const more = el('button', { type: 'button', class: 'mehr', 'aria-pressed': 'false', title: 'Mehr davon', 'aria-label': 'Mehr davon' }, [icon('hoch'), mini ? false : 'Mehr davon']);
	const less = el('button', { type: 'button', class: 'weniger', 'aria-pressed': 'false', title: 'Weniger', 'aria-label': 'Weniger' }, [icon('runter'), mini ? false : 'Weniger']);
	const box = el('div', { class: mini ? 'reaktion mini' : 'reaktion', role: 'group', 'aria-label': 'Reaktion auf diesen Artikel', 'data-red': true }, [more, less]);
	more.addEventListener('click', (event) => {
		event.preventDefault();
		choose(id, 'more', box);
	});
	less.addEventListener('click', (event) => {
		event.preventDefault();
		choose(id, 'less', box);
	});
	widgets.set(id, [...(widgets.get(id) ?? []), box]);
	paint(id);

	return box;
}

/** Setzt den Stand eines Artikels (aus `posts/by-slug`), ohne zu speichern. */
export function setzen(id, value) {
	current.set(id, value ?? null);
	paint(id);
}

/** Holt alle Reaktionen einmal und malt bereits vorhandene Knöpfe nach. */
export async function laden() {
	if (isLoaded) {
		return;
	}
	const { reactions } = await api('GET', 'reactions');
	isLoaded = true;
	for (const [id, value] of Object.entries(reactions)) {
		current.set(Number(id), value);
	}
	for (const id of widgets.keys()) {
		paint(id);
	}
}

/** Hängt an jede Artikelkarte der Seite die kleinen Knöpfe. */
export async function karten() {
	await laden();
	for (const card of document.querySelectorAll('.karte[data-post-id]')) {
		if (card.querySelector(':scope > .reaktion') === null) {
			card.append(widget(Number(card.dataset.postId), true));
		}
	}
}

export function zuruecksetzen() {
	widgets.clear();
	current.clear();
	isLoaded = false;
}
