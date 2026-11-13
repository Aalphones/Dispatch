// Zeitversetzt veröffentlichen: Freigeben mit Zeitpunkt, „Geplant“ auf der Startseite, Dialog „Veröffentlichungsplan“,
// Leiste eines geplanten Artikels in der Vorschau (Entwurf: docs/archive/2026-11/2026-11-12_zeitversetzt-veroeffentlichen/artifacts).
import { api } from './api.js';
import { aktualisieren } from './konto.js';
import { meldeFreigabe } from './post.js';
import { dialog, el, fehlerZeile, icon, info, rahmen, toast, zeigeFehler } from './ui.js';

const ZONE = 'Europe/Berlin';
const DAY_MS = 864e5;
const REDIRECT_MS = 1400;
const MAX_PER_DAY = 6;
const COMPACT_ROWS = 3;
const SUGGESTED_TIME = 'T18:00';
const MODE_SLOT = 'slot';
const MODE_FIXED = 'fixed';
const MODE_NOW = 'now';

// ---------- Zeit (alles in Europe/Berlin, unabhängig vom Gerät) ----------

/** UTC-Zeichenkette `Y-m-d H:i:s` der API → Date. */
function parseStamp(stamp) {
	return new Date(String(stamp).replace(' ', 'T') + 'Z');
}

function berlinDay(date) {
	return date.toLocaleDateString('en-CA', { timeZone: ZONE });
}

/** Tage von heute bis zum Berliner Kalendertag `YYYY-MM-DD`: 0 = heute, 1 = morgen. */
function daysUntil(day) {
	const today = Date.parse(berlinDay(new Date()) + 'T00:00:00Z');

	return Math.round((Date.parse(day + 'T00:00:00Z') - today) / DAY_MS);
}

function dayOffset(date) {
	return daysUntil(berlinDay(date));
}

function clock(date) {
	return date.toLocaleTimeString('de-DE', { timeZone: ZONE, hour: '2-digit', minute: '2-digit' });
}

function shortDay(date) {
	const offset = dayOffset(date);
	if (offset === 0) {
		return 'heute';
	}
	if (offset === 1) {
		return 'morgen';
	}

	return date.toLocaleDateString('de-DE', { timeZone: ZONE, weekday: 'short', day: '2-digit', month: '2-digit' });
}

function when(date) {
	return shortDay(date) + ', ' + clock(date);
}

function dayTitle(date) {
	const long = date.toLocaleDateString('de-DE', { timeZone: ZONE, weekday: 'long', day: 'numeric', month: 'long' });
	const offset = dayOffset(date);
	if (offset === 0) {
		return 'Heute · ' + long;
	}
	if (offset === 1) {
		return 'Morgen · ' + long;
	}

	return long;
}

/** Berliner Wandzeit im Format eines `datetime-local`-Feldes. */
function toInputValue(date) {
	const parts = {};
	const formatter = new Intl.DateTimeFormat('en-CA', { timeZone: ZONE, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
	for (const part of formatter.formatToParts(date)) {
		parts[part.type] = part.value;
	}

	return parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute;
}

function tomorrowEvening() {
	const tomorrow = new Date(Date.parse(berlinDay(new Date()) + 'T00:00:00Z') + DAY_MS);

	return tomorrow.toISOString().slice(0, 10) + SUGGESTED_TIME;
}

/** Berliner Wandzeit `YYYY-MM-DDTHH:MM` (Wert eines `datetime-local`-Feldes) wie `when`: „morgen, 18:00“. */
function wallWhen(value) {
	const day = value.slice(0, 10);
	const offset = daysUntil(day);
	let label = new Date(day + 'T12:00:00Z').toLocaleDateString('de-DE', { timeZone: 'UTC', weekday: 'short', day: '2-digit', month: '2-digit' });
	if (offset === 0) {
		label = 'heute';
	} else if (offset === 1) {
		label = 'morgen';
	}

	return label + ', ' + value.slice(11, 16);
}

function count(amount, one, many) {
	return amount + ' ' + (amount === 1 ? one : many);
}

/** ['08:00', '14:00'] → „um 08:00 und 14:00“. */
function timesText(times) {
	if (times.length === 1) {
		return 'um ' + times[0];
	}

	return 'um ' + times.slice(0, -1).join(', ') + ' und ' + times.at(-1);
}

// ---------- Zeitpunkt wählen (Freigeben, Termin ändern) ----------

/**
 * Drei Wahlen: Warteschlange, fester Termin, sofort. `item` ist der Eintrag des Plans (bei „Freigeben“ `null`),
 * `modus` `'freigeben'` oder `'aendern'`; `button` ist der Hauptknopf, dessen Beschriftung der Wahl folgt.
 * `lesen()` liefert `{ when, at? }` oder `null` bei ungültiger Eingabe; `fehler(text)` zeigt Serverfehler zum Feld.
 */
function zeitpunktWahl(item, modus, button, plan) {
	const name = 'zp-' + Math.random().toString(36).slice(2, 8);
	const keepsSlot = modus === 'aendern' && item !== null && item.mode === MODE_SLOT;
	const ahead = plan.items.filter((other) => other.mode === MODE_SLOT && (item === null || other.id !== item.id)).length;
	let queueText;
	if (keepsSlot) {
		queueText = ['Platz ' + item.position + ' · ', el('b', { text: when(parseStamp(item.expected_at)) })];
	} else {
		queueText = ['Voraussichtlich ', el('b', { text: when(parseStamp(plan.next_free)) }), ' · ' + (ahead === 0 ? 'nichts davor' : count(ahead, 'Artikel', 'Artikel') + ' davor')];
	}
	let suggestion = tomorrowEvening();
	if (item !== null && item.mode === MODE_FIXED) {
		suggestion = toInputValue(parseStamp(item.expected_at));
	}
	const input = el('input', { type: 'datetime-local', id: name + '-termin', value: suggestion, min: toInputValue(new Date()), step: '300', 'aria-describedby': name + '-fehler' });
	const errorLine = fehlerZeile(name + '-fehler');
	const dateField = el('div', { class: 'termin-feld', hidden: true }, [el('label', { for: name + '-termin', text: 'Datum und Uhrzeit' }), input, errorLine]);
	const options = [
		{
			value: MODE_SLOT,
			title: keepsSlot ? 'In der Warteschlange lassen' : 'Nächster freier Platz',
			text: queueText,
			tip: 'Die Warteschlange veröffentlicht höchstens ' + count(plan.per_day, 'Artikel', 'Artikel') + ' am Tag, ' + timesText(plan.times) + ' Uhr, in der Reihenfolge der Freigabe. Einstellen im Veröffentlichungsplan.',
		},
		{ value: MODE_FIXED, title: 'Fester Termin', text: ['Erscheint genau dann, zusätzlich zum Tageslimit'], tip: 'Ein fester Termin belegt keinen Platz der Warteschlange.' },
		{ value: MODE_NOW, title: 'Sofort', text: [modus === 'freigeben' ? 'Erscheint jetzt; die Website wird neu gebaut' : 'Erscheint jetzt statt zum Termin'], tip: false },
	];
	let startValue = MODE_SLOT;
	if (modus === 'aendern' && item !== null) {
		startValue = item.mode;
	}
	const radios = [];
	const choices = options.map((option) => {
		const radio = el('input', { type: 'radio', name, value: option.value, checked: option.value === startValue });
		radios.push(radio);

		return el('label', { class: 'wahl' }, [radio, el('b', { text: option.title }), option.tip ? info(option.tip) : false, el('small', {}, option.text)]);
	});
	const fieldset = el('fieldset', { class: 'rechtewahl zeitpunkt' }, [el('legend', { text: 'Wann erscheint der Artikel?' }), choices[0], choices[1], dateField, choices[2]]);

	const chosen = () => radios.find((radio) => radio.checked).value;
	const buttonText = (mode) => {
		if (modus === 'freigeben') {
			if (mode === MODE_NOW) {
				return 'Freigeben und veröffentlichen';
			}
			if (mode === MODE_FIXED) {
				return 'Freigeben für ' + (input.value === '' ? '…' : wallWhen(input.value));
			}

			return 'Freigeben und einplanen';
		}
		if (mode === MODE_NOW) {
			return 'Jetzt veröffentlichen';
		}
		if (mode === MODE_FIXED) {
			return 'Termin speichern';
		}

		return keepsSlot ? 'Keine Änderung' : 'In die Warteschlange';
	};
	const relabel = () => {
		const mode = chosen();
		dateField.hidden = mode !== MODE_FIXED;
		button.replaceChildren(icon(mode === MODE_NOW ? 'check' : 'uhr'), buttonText(mode));
		button.disabled = modus === 'aendern' && mode === MODE_SLOT && keepsSlot;
	};
	for (const radio of radios) {
		radio.addEventListener('change', () => {
			relabel();
			if (chosen() === MODE_FIXED) {
				input.focus();
			}
		});
	}
	input.addEventListener('input', () => {
		zeigeFehler(errorLine, '');
		input.removeAttribute('aria-invalid');
		relabel();
	});
	relabel();

	const fehler = (text) => {
		zeigeFehler(errorLine, text);
		input.setAttribute('aria-invalid', 'true');
		input.focus();
	};
	const lesen = () => {
		const mode = chosen();
		if (mode !== MODE_FIXED) {
			return { when: mode };
		}
		if (input.value === '') {
			fehler('Bitte Datum und Uhrzeit wählen.');

			return null;
		}
		if (input.value <= toInputValue(new Date())) {
			fehler('Der Termin liegt in der Vergangenheit. Wähle eine Zeit nach ' + when(new Date()) + '.');

			return null;
		}

		return { when: mode, at: input.value };
	};

	return { nodes: [fieldset], lesen, fehler };
}

function visitHint() {
	return el('p', { class: 'besuch-hinweis' }, [icon('uhr'), 'Ein geplanter Artikel erscheint mit dem ersten Seitenaufruf ab seinem Termin – auch deinem eigenen. Kommt niemand vorbei, wartet er.']);
}

function loadPlan() {
	return api('GET', 'schedule');
}

// ---------- Freigeben ----------

/** Dialog „Freigeben“ für einen nie veröffentlichten Artikel. */
export async function freigeben(post) {
	let plan;
	try {
		plan = await loadPlan();
	} catch (error) {
		toast(error.message);

		return;
	}
	const button = el('button', { type: 'button', class: 'knopf primaer' });
	const choice = zeitpunktWahl(null, 'freigeben', button, plan);
	const box = dialog('dlg-freigeben', rahmen('Freigeben', [el('p', { class: 'worum', text: '„' + post.title + '“' }), choice.nodes, visitHint()], [
		el('button', { type: 'button', class: 'knopf leise', 'data-close': true, text: 'Abbrechen' }),
		button,
	]));
	button.addEventListener('click', async () => {
		const wish = choice.lesen();
		if (wish === null) {
			return;
		}
		button.disabled = true;
		try {
			const result = await api('POST', 'posts/' + post.id + '/approve', wish);
			box.close();
			await afterApprove(result, post);
		} catch (error) {
			button.disabled = false;
			reportError(error, choice);
		}
	});
}

function reportError(error, choice) {
	if (error.error === 'invalid_value' && error.details && error.details.field === 'at') {
		choice.fehler(error.message);

		return;
	}
	toast(error.message);
}

async function afterApprove(result, post) {
	aktualisieren().catch(() => null);
	if (result.post.status !== 'scheduled') {
		if (meldeFreigabe(result)) {
			setTimeout(() => location.assign('/artikel/' + post.slug + '/'), REDIRECT_MS);
		}

		return;
	}
	let text = 'Freigegeben.';
	try {
		const entry = (await loadPlan()).items.find((item) => item.id === post.id);
		if (entry !== undefined) {
			text = 'Freigegeben. Erscheint voraussichtlich ' + when(parseStamp(entry.expected_at)) + '.';
		}
	} catch {}
	toast(text);
	setTimeout(() => location.assign('/'), REDIRECT_MS);
}

// ---------- Zeilen ----------

function kindMark(item) {
	if (item.due) {
		return el('span', { class: 'art', text: 'erscheint mit dem nächsten Aufruf' });
	}
	if (item.mode === MODE_FIXED) {
		return el('span', { class: 'art fest' }, [icon('uhr'), 'Fester Termin']);
	}

	return el('span', { class: 'art', text: 'Warteschlange · Platz ' + item.position });
}

function previewLink(item) {
	return el('a', { href: '/vorschau/?id=' + item.id, text: item.title });
}

function compactRow(item) {
	return el('li', {}, [
		el('span', { class: 'plan-zeit', text: when(parseStamp(item.expected_at)) }),
		el('div', { class: 'plan-titel' }, [previewLink(item), el('div', { class: 'unter' }, kindMark(item))]),
	]);
}

// ---------- „Geplant“ auf der Startseite ----------

/** Abschnitt „Geplant“ nach „Wartet auf dich“; ohne eingeplante Artikel bleibt die Seite wie sie ist. */
export async function geplant() {
	const wrap = document.querySelector('#inhalt .wrap');
	if (wrap === null) {
		return;
	}
	const plan = await loadPlan();
	document.getElementById('geplant')?.remove();
	if (plan.items.length === 0) {
		return;
	}
	const rest = plan.items.length - COMPACT_ROWS;
	const open = () => planOeffnen();
	const section = el('section', { id: 'geplant', class: 'geplant red-fade', 'aria-labelledby': 'geplant-titel', 'data-red': true }, [
		el('div', { class: 'geplant-kopf' }, [
			el('h2', { id: 'geplant-titel', text: 'Geplant' }),
			el('span', { text: count(plan.items.length, 'Artikel', 'Artikel') + ' · Warteschlange: ' + plan.per_day + ' am Tag' }),
			el('button', { type: 'button', class: 'knopf klein-k', onclick: open }, [icon('uhr'), 'Plan öffnen']),
		]),
		el('ul', { class: 'plan-liste kompakt' }, plan.items.slice(0, COMPACT_ROWS).map(compactRow)),
		rest > 0 ? el('p', { class: 'plan-summe' }, el('button', { type: 'button', class: 'knopf leise klein-k', text: 'und ' + count(rest, 'weiterer', 'weitere') + ' im Plan', onclick: open })) : false,
	]);
	const waiting = document.getElementById('wartet');
	const lead = wrap.querySelector('.aufmacher');
	if (waiting !== null) {
		waiting.after(section);
	} else if (lead !== null) {
		lead.before(section);
	} else if (wrap.querySelector('.seitenkopf h1') === null) {
		wrap.prepend(section);
	}
}

// ---------- Dialog „Veröffentlichungsplan“ ----------

/** Aktion, die einen Artikel wieder so einplant, wie er vor dem Herausnehmen war. */
function replanBody(item) {
	if (item.mode === MODE_FIXED && toInputValue(parseStamp(item.expected_at)) > toInputValue(new Date())) {
		return { when: MODE_FIXED, at: toInputValue(parseStamp(item.expected_at)) };
	}

	return { when: MODE_SLOT };
}

export async function planOeffnen() {
	const body = el('div');
	const box = dialog('dlg-plan', rahmen('Veröffentlichungsplan', body, [el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' })]), 'breit');
	box.addEventListener('close', () => {
		if (document.body.dataset.seite === 'start') {
			geplant().catch(() => null);
		}
	}, { once: true });
	const state = { plan: null, openId: null, focusSelector: null, redraw: null };

	const draw = () => {
		body.replaceChildren(...planParts(state, update, change, remove));
		if (state.focusSelector !== null) {
			body.querySelector(state.focusSelector)?.focus();
			state.focusSelector = null;
		}
	};
	state.redraw = draw;
	const showError = () => {
		body.replaceChildren(
			el('p', { class: 'plan-fehler', role: 'alert' }, [icon('warn'), 'Der Plan ließ sich nicht laden. Geplante Artikel erscheinen trotzdem zu ihrem Termin.']),
			el('button', { type: 'button', class: 'knopf klein-k', text: 'Erneut versuchen', onclick: () => reload() }),
		);
	};
	async function reload() {
		body.replaceChildren(el('div', { 'aria-busy': 'true', 'aria-label': 'Plan wird geladen' }, [el('div', { class: 'lader kurz' }), el('div', { class: 'lader' }), el('div', { class: 'lader' }), el('div', { class: 'lader kurz' })]));
		try {
			state.plan = await loadPlan();
		} catch {
			showError();

			return;
		}
		draw();
	}
	async function update(perDay) {
		const previous = state.plan.per_day;
		try {
			state.plan = await api('PATCH', 'schedule', { per_day: perDay });
		} catch (error) {
			toast(error.message);

			return;
		}
		state.focusSelector = '.gewicht [aria-pressed="true"]';
		draw();
		toast('Gespeichert: ' + perDay + ' am Tag, ' + timesText(state.plan.times) + ' Uhr. Die Termine der Warteschlange sind neu berechnet.', () => update(previous));
	}
	async function change(item, wish, choice) {
		try {
			const result = await api('POST', 'posts/' + item.id + '/schedule', wish);
			state.openId = null;
			if (result.post.status === 'scheduled') {
				toast(wish.when === MODE_FIXED ? 'Neuer Termin: ' + wallWhen(wish.at) + '.' : 'Zurück in der Warteschlange.');
			} else {
				meldeFreigabe(result);
			}
		} catch (error) {
			reportError(error, choice);

			return;
		}
		aktualisieren().catch(() => null);
		await reload();
	}
	async function remove(item) {
		const replan = replanBody(item);
		try {
			await api('POST', 'posts/' + item.id + '/unschedule');
		} catch (error) {
			toast(error.message);

			return;
		}
		state.openId = null;
		toast('Aus dem Plan genommen. Der Artikel wartet wieder auf Freigabe.', async () => {
			try {
				await api('POST', 'posts/' + item.id + '/approve', replan);
			} catch (error) {
				toast(error.message);
			}
			aktualisieren().catch(() => null);
			await reload();
		});
		aktualisieren().catch(() => null);
		await reload();
	}

	await reload();
}

function planParts(state, update, change, remove) {
	const plan = state.plan;
	const parts = [settingBlock(plan, update)];
	if (plan.items.length === 0) {
		parts.push(el('div', { class: 'leer' }, [icon('uhr'), el('p', { text: 'Nichts geplant.' }), el('p', { text: 'Was du freigibst, landet hier und erscheint ' + timesText(plan.times) + ' Uhr – ein Artikel je Termin.' })]));
	} else {
		parts.push(el('p', { class: 'plan-summe' }, count(plan.items.length, 'Artikel', 'Artikel') + ' geplant · nächster freier Platz: ' + when(parseStamp(plan.next_free))));
		parts.push(...dayGroups(state, plan, change, remove));
	}
	parts.push(visitHint());

	return parts;
}

function settingBlock(plan, update) {
	const group = el('div', { class: 'gewicht hell', role: 'group', 'aria-label': 'Artikel pro Tag aus der Warteschlange' });
	for (let amount = 1; amount <= MAX_PER_DAY; amount += 1) {
		group.append(el('button', {
			type: 'button',
			'aria-pressed': String(amount === plan.per_day),
			'aria-label': count(amount, 'Artikel', 'Artikel') + ' pro Tag',
			text: String(amount),
			onclick: () => {
				if (amount !== plan.per_day) {
					update(amount);
				}
			},
		}));
	}

	return el('div', { class: 'plan-einstellung' }, [
		el('b', { text: 'Warteschlange: Artikel pro Tag' }),
		group,
		el('span', { class: 'plan-zeiten', text: timesText(plan.times) + ' Uhr' }),
		info('Freigegebene Artikel erscheinen nacheinander zu diesen Zeiten, gleichmäßig verteilt ab 8 Uhr. Artikel mit festem Termin kommen zusätzlich. Änderst du die Zahl, verschieben sich die Termine der Warteschlange sofort.'),
	]);
}

/** Zeilen je Berliner Tag: heute Erschienene, Geplante und die kursive Zeile „Nächster freier Platz“. */
function dayGroups(state, plan, change, remove) {
	const groups = new Map();
	const add = (row) => {
		const key = berlinDay(row.time);
		if (!groups.has(key)) {
			groups.set(key, []);
		}
		groups.get(key).push(row);
	};
	for (const done of plan.released_today) {
		add({ kind: 'done', time: parseStamp(done.published_at), item: done });
	}
	for (const item of plan.items) {
		add({ kind: 'item', time: parseStamp(item.expected_at), item });
	}
	add({ kind: 'free', time: parseStamp(plan.next_free), item: null });

	const parts = [];
	for (const key of [...groups.keys()].sort()) {
		const rows = groups.get(key).sort((first, second) => first.time - second.time);
		parts.push(el('h3', { class: 'gruppe-titel', text: dayTitle(rows[0].time) }), el('ul', { class: 'plan-liste' }, rows.map((row) => planRow(state, plan, row, change, remove))));
	}

	return parts;
}

function planRow(state, plan, row, change, remove) {
	if (row.kind === 'free') {
		return el('li', { class: 'frei' }, [el('span', { class: 'plan-zeit', text: clock(row.time) }), el('div', { class: 'plan-titel', text: 'Nächster freier Platz' })]);
	}
	const item = row.item;
	if (row.kind === 'done') {
		return el('li', { class: 'erschienen' }, [
			el('span', { class: 'plan-zeit', text: clock(row.time) }),
			el('div', { class: 'plan-titel' }, [
				el('a', { href: '/artikel/' + item.slug + '/', text: item.title }),
				el('div', { class: 'unter' }, el('span', { class: 'punkt', text: item.mode === null ? 'sofort erschienen' : 'erschienen' })),
			]),
		]);
	}
	const isOpen = state.openId === item.id;
	const openButton = el('button', {
		type: 'button',
		class: 'knopf leise klein-k',
		'aria-expanded': String(isOpen),
		onclick: () => {
			state.openId = isOpen ? null : item.id;
			state.focusSelector = '[aria-expanded="true"]';
			state.redraw();
		},
	}, [icon('stift'), 'Ändern']);
	const li = el('li', {}, [
		el('span', { class: 'plan-zeit', text: clock(row.time) }),
		el('div', { class: 'plan-titel' }, [previewLink(item), el('div', { class: 'unter' }, kindMark(item))]),
		el('div', { class: 'plan-aktionen' }, [
			openButton,
			el('button', { type: 'button', class: 'weg', 'aria-label': 'Aus dem Plan nehmen: ' + item.title, title: 'Aus dem Plan nehmen – der Artikel wartet wieder auf Freigabe', text: '✕', onclick: () => remove(item) }),
		]),
	]);
	if (isOpen) {
		li.append(editBlock(state, plan, item, change));
	}

	return li;
}

function editBlock(state, plan, item, change) {
	const button = el('button', { type: 'button', class: 'knopf primaer klein-k' });
	const choice = zeitpunktWahl(item, 'aendern', button, plan);
	button.addEventListener('click', () => {
		const wish = choice.lesen();
		if (wish !== null) {
			button.disabled = true;
			change(item, wish, choice).finally(() => {
				button.disabled = false;
			});
		}
	});

	return el('div', { class: 'bearbeiten' }, el('div', {}, [
		choice.nodes,
		el('div', { class: 'knopfzeile' }, [
			el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Abbrechen', onclick: () => { state.openId = null; state.redraw(); } }),
			button,
		]),
	]));
}

// ---------- Leiste eines geplanten Artikels ----------

/** Die Freigabe-Leiste für einen eingeplanten Artikel; `post.js` hängt sie wie die Freigabe-Leiste ein. */
export function leiste(data) {
	const post = data.post;
	const status = el('span', { class: 'geplant-status' }, [icon('uhr'), 'Geplant']);
	const refresh = async () => {
		let plan;
		try {
			plan = await loadPlan();
		} catch {
			return null;
		}
		const item = plan.items.find((entry) => entry.id === post.id);
		if (item !== undefined) {
			status.replaceChildren(icon('uhr'), 'Geplant für ' + when(parseStamp(item.expected_at)), kindMark(item));
		}

		return { plan, item };
	};
	refresh();

	const changeButton = el('button', { type: 'button', class: 'knopf klein-k', onclick: async () => {
		const current = await refresh();
		if (current !== null && current.item !== undefined) {
			openTermDialog(post, current.item, current.plan, refresh);
		}
	} }, [icon('stift'), 'Termin ändern']);
	const removeButton = el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Aus dem Plan nehmen', onclick: async () => {
		removeButton.disabled = true;
		try {
			await api('POST', 'posts/' + post.id + '/unschedule');
		} catch (error) {
			removeButton.disabled = false;
			toast(error.message);

			return;
		}
		aktualisieren().catch(() => null);
		toast('Aus dem Plan genommen. Der Artikel wartet wieder auf Freigabe.');
		setTimeout(() => location.assign('/'), REDIRECT_MS);
	} });
	const nowButton = el('button', { type: 'button', class: 'knopf primaer', onclick: async () => {
		nowButton.disabled = true;
		try {
			const result = await api('POST', 'posts/' + post.id + '/schedule', { when: MODE_NOW });
			aktualisieren().catch(() => null);
			if (meldeFreigabe(result)) {
				setTimeout(() => location.assign('/artikel/' + post.slug + '/'), REDIRECT_MS);
			}
		} catch (error) {
			nowButton.disabled = false;
			toast(error.message);
		}
	} }, [icon('check'), 'Jetzt veröffentlichen']);

	return el('div', { class: 'freigabe' }, el('div', { class: 'wrap' }, [
		status,
		el('div', { class: 'freigabe-knoepfe' }, [changeButton, removeButton, nowButton]),
	]));
}

function openTermDialog(post, item, plan, refresh) {
	const button = el('button', { type: 'button', class: 'knopf primaer' });
	const choice = zeitpunktWahl(item, 'aendern', button, plan);
	const box = dialog('dlg-termin', rahmen('Termin ändern', [el('p', { class: 'worum', text: '„' + item.title + '“' }), choice.nodes], [
		el('button', { type: 'button', class: 'knopf leise', 'data-close': true, text: 'Abbrechen' }),
		button,
	]));
	button.addEventListener('click', async () => {
		const wish = choice.lesen();
		if (wish === null) {
			return;
		}
		button.disabled = true;
		try {
			const result = await api('POST', 'posts/' + post.id + '/schedule', wish);
			box.close();
			if (result.post.status === 'scheduled') {
				toast('Termin geändert.');
				await refresh();
			} else if (meldeFreigabe(result)) {
				setTimeout(() => location.assign('/artikel/' + post.slug + '/'), REDIRECT_MS);
			}
			aktualisieren().catch(() => null);
		} catch (error) {
			button.disabled = false;
			reportError(error, choice);
		}
	});
}
