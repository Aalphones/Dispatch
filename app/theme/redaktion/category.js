// Kategorien-Seite: Interesse 0–5 an jeder Karte (Entwurf: .gewicht-inline).
import { api } from './api.js';
import { el, info, toast } from './ui.js';

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
