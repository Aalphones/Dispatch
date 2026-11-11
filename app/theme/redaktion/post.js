// Vorschau-Seite des Redaktionsmodus: Freigabe-Leiste, Freigeben, Zurückgeben, Verwerfen, Änderungsansicht.
import { api } from './api.js';
import { start as startKommentar } from './kommentar.js';
import { dialog, el, icon, toast } from './ui.js';

const REDIRECT_MS = 1400;
const NOTE_MAX = 20000;

function plural(count, one, many) {
	return count + ' ' + (count === 1 ? one : many);
}

function checks(form, post) {
	const errors = form.errors.map((finding) => el('span', { class: 'pruef fehler', title: finding.message }, [icon('warn'), finding.message]));
	const warnings = form.warnings.map((finding) => el('span', { class: 'pruef warn', title: 'Warnung, blockiert nicht' }, [icon('warn'), finding.message]));
	if (errors.length > 0) {
		return [...errors, ...warnings];
	}
	const words = post.word_count.toLocaleString('de-DE') + ' Wörter';

	return [
		el('span', { class: 'pruef ok' }, [icon('check'), plural(post.sources, 'Quelle', 'Quellen')]),
		el('span', { class: 'pruef ok' }, [icon('check'), words]),
		...warnings,
	];
}

function diffHeadline(diff) {
	const { summary, media } = diff;
	const parts = [];
	if (summary.blocks_changed > 0) {
		parts.push(plural(summary.blocks_changed, 'Absatz geändert', 'Absätze geändert'));
	}
	if (summary.sources_added > 0) {
		parts.push(plural(summary.sources_added, 'Quelle neu', 'Quellen neu'));
	}
	if (summary.sources_removed > 0) {
		parts.push(plural(summary.sources_removed, 'Quelle entfernt', 'Quellen entfernt'));
	}
	if (media.added.length > 0) {
		parts.push(plural(media.added.length, 'Bild neu', 'Bilder neu'));
	}
	if (media.removed.length > 0) {
		parts.push(plural(media.removed.length, 'Bild entfernt', 'Bilder entfernt'));
	}

	return parts.length > 0 ? parts.join(' · ') : 'Keine Änderungen gegenüber der Live-Fassung.';
}

function diffList(title, items, className, prefix) {
	if (items.length === 0) {
		return false;
	}

	return el('section', {}, [
		el('h3', { text: title }),
		el('ul', {}, items.map((item) => el('li', { class: className, text: prefix + item }))),
	]);
}

function sourceLabel(source) {
	return source.url ? source.title + ' (' + source.url + ')' : source.title;
}

function mediaLabel(medium) {
	return medium.alt || medium.uid;
}

function diffView(diff) {
	const view = el('div', { class: 'v-ansicht v-diff', hidden: true }, [el('p', { class: 'diff-kopf', text: diffHeadline(diff) })]);
	view.insertAdjacentHTML('beforeend', diff.html);
	view.append(el('div', { class: 'diff-listen wrap' }, [
		diffList('Neue Quellen', diff.sources.added, 'diff-neu', '+ '),
		diffList('Entfernte Quellen', diff.sources.removed, 'diff-weg', '− '),
		diffList('Neue Bilder', diff.media.added.map(mediaLabel), 'diff-neu', '+ '),
		diffList('Entfernte Bilder', diff.media.removed.map(mediaLabel), 'diff-weg', '− '),
	]));

	return view;
}

function confirmDiscard(id) {
	const confirmButton = el('button', {
		type: 'button',
		class: 'knopf gefahr',
		text: 'Endgültig löschen',
		onclick: async () => {
			confirmButton.disabled = true;
			try {
				await api('DELETE', 'posts/' + id);
				toast('Entwurf gelöscht.');
				setTimeout(() => location.assign('/'), REDIRECT_MS);
			} catch (error) {
				confirmButton.disabled = false;
				toast(error.message);
			}
		},
	});
	dialog('verwerfen', [
		el('div', { class: 'dlg-kopf' }, el('h2', { text: 'Entwurf verwerfen' })),
		el('div', { class: 'dlg-rumpf' }, el('p', { text: 'Entwurf endgültig löschen? Das lässt sich nicht rückgängig machen.' })),
		el('div', { class: 'dlg-fuss' }, [
			el('button', { type: 'button', class: 'knopf leise', 'data-close': true, text: 'Abbrechen' }),
			confirmButton,
		]),
	]);
}

async function approve(data, button) {
	button.disabled = true;
	try {
		const result = await api('POST', 'posts/' + data.post.id + '/approve');
		if (!result.rebuilt) {
			toast('Freigegeben, aber die Website wurde nicht neu gebaut: ' + result.rebuild_error + '. Der nächste Neubau holt es nach.');

			return;
		}
		toast('Freigegeben. Die Website wird neu gebaut.');
		setTimeout(() => location.assign('/artikel/' + data.post.slug + '/'), REDIRECT_MS);
	} catch (error) {
		button.disabled = false;
		toast(error.message);
	}
}

/** Die Leiste mit Prüfung und Knöpfen; die Verdrahtung der Knöpfe folgt in `vorschau()`. */
function buildBar(data) {
	const hasErrors = data.form.errors.length > 0;
	const buttons = {
		comment: el('button', { type: 'button', class: 'knopf klein-k', 'aria-pressed': 'false', title: 'Stelle anklicken oder Text markieren, dann Anmerkung schreiben' }, [icon('stift'), 'Kommentieren ', el('span', { class: 'zaehler', hidden: true, text: '0' })]),
		diff: data.has_live ? el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Änderungen zeigen' }) : false,
		back: el('button', { type: 'button', class: 'knopf klein-k', text: 'Zurück an Agent' }),
		discard: data.post.published_revision === null ? el('button', { type: 'button', class: 'knopf leise gefahr klein-k', text: 'Verwerfen' }) : false,
		approve: el('button', { type: 'button', class: 'knopf primaer', disabled: hasErrors, title: hasErrors ? data.form.errors[0].message : false }, [icon('check'), 'Freigeben']),
	};
	const freeText = el('textarea', { placeholder: 'Was soll der Agent ändern? (wird ihm beim nächsten Lauf gezeigt)' });
	const send = el('button', { type: 'button', class: 'knopf primaer klein-k', text: 'Zurückgeben', disabled: true });
	const noteBox = el('div', { class: 'anmerkung' }, el('div', {}, [freeText, el('div', { class: 'anmerkung-fuss' }, send)]));
	const bar = el('div', { class: 'freigabe' }, el('div', { class: 'wrap' }, [
		el('div', { class: 'pruefung', 'aria-label': 'Formprüfung' }, checks(data.form, data.post)),
		el('div', { class: 'freigabe-knoepfe' }, [buttons.comment, buttons.diff, buttons.back, buttons.discard, buttons.approve]),
		noteBox,
	]));
	const hint = el('p', { class: 'k-hinweis', text: 'Kommentarmodus: Absatz, Bild oder Kasten anklicken – oder Text markieren · Esc beendet' });

	return { bar, hint, buttons, freeText, send, noteBox };
}

function keepBarHeight(bar) {
	if ('ResizeObserver' in window) {
		new ResizeObserver(() => document.documentElement.style.setProperty('--freigabe-h', bar.offsetHeight + 'px')).observe(bar);
	}
	document.body.classList.add('mit-freigabe');
}

export async function vorschau() {
	const content = document.getElementById('vorschau-inhalt');
	const id = Number(new URLSearchParams(location.search).get('id'));
	if (content === null) {
		return;
	}
	if (!Number.isInteger(id) || id <= 0) {
		content.textContent = 'Keine Vorschau ausgewählt.';

		return;
	}
	let data;
	try {
		data = await api('GET', 'posts/' + id + '/preview');
	} catch (error) {
		content.textContent = error.message;

		return;
	}

	const view = el('div', { class: 'v-ansicht' });
	view.innerHTML = data.html;
	content.replaceChildren(view);

	const { bar, hint, buttons, freeText, send, noteBox } = buildBar(data);
	document.querySelector('.freigabe')?.remove();
	document.querySelector('.vorschau-band').before(bar, hint);
	keepBarHeight(bar);

	let comments = null;
	const refresh = () => {
		const count = comments === null ? 0 : comments.count();
		const counter = buttons.comment.querySelector('.zaehler');
		counter.hidden = count === 0;
		counter.textContent = count;
		buttons.comment.setAttribute('aria-pressed', String(comments !== null && comments.isOn()));
		send.disabled = count === 0 && freeText.value.trim() === '';
	};
	comments = startKommentar({ root: view, id, revision: data.post.revision, onChange: refresh });
	freeText.addEventListener('input', refresh);
	refresh();

	buttons.comment.addEventListener('click', () => comments.toggle());
	buttons.back.addEventListener('click', () => noteBox.classList.toggle('offen'));
	buttons.approve.addEventListener('click', () => approve(data, buttons.approve));
	if (buttons.discard) {
		buttons.discard.addEventListener('click', () => confirmDiscard(id));
	}
	if (buttons.diff) {
		wireDiff(buttons, view, id, comments);
	}
	send.addEventListener('click', () => giveBack(id, freeText, comments, send));
}

function wireDiff(buttons, view, id, comments) {
	let diff = null;
	let isShown = false;

	buttons.diff.addEventListener('click', async () => {
		if (diff === null) {
			try {
				diff = diffView(await api('GET', 'posts/' + id + '/diff'));
			} catch (error) {
				toast(error.message);

				return;
			}
			view.after(diff);
		}
		isShown = !isShown;
		if (isShown && comments.isOn()) {
			comments.toggle();
		}
		view.hidden = isShown;
		diff.hidden = !isShown;
		buttons.comment.disabled = isShown;
		buttons.diff.textContent = isShown ? 'Änderungen ausblenden' : 'Änderungen zeigen';
	});
}

async function giveBack(id, freeText, comments, button) {
	const parts = [freeText.value.trim(), comments.note()].filter((part) => part !== '');
	const note = parts.join('\n\n');
	if (note.length > NOTE_MAX) {
		toast('Der Text ist zu lang (höchstens ' + NOTE_MAX + ' Zeichen).');

		return;
	}
	const count = comments.count();
	button.disabled = true;
	try {
		await api('POST', 'posts/' + id + '/return', { note });
	} catch (error) {
		button.disabled = false;
		toast(error.message);

		return;
	}
	comments.clear();
	toast(count > 0 ? 'Zurück an den Agenten, mit ' + plural(count, 'Anmerkung', 'Anmerkungen') + '.' : 'Zurück an den Agenten.');
	setTimeout(() => location.assign('/'), REDIRECT_MS);
}
