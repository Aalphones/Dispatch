// Dialog „Thema einreichen“ (Entwurf: #einreichen): Felder, Dateiablage, Upload mit Fortschritt.
import { upload } from './api.js';
import { dialog, el, fehlerZeile, groesse, info, rahmen, toast, zeigeFehler } from './ui.js';

const MAX_BYTES = 50 * 1024 * 1024;
const ACCEPT = 'image/*,video/*,audio/*';
const SUBMITTED = 'Thema eingereicht – der nächste Agentenlauf greift es auf.';
const PRIORITIES = [['normal', 'Normal'], ['soon', 'Bald'], ['now', 'Sofort']];
const DEPTHS = [
	['standard', 'Standard (ab 600 Wörter, 3 Quellen)'],
	['deep', 'Tiefenrecherche (ab 1.200 Wörter, 5 Quellen)'],
];

function select(name, options, label, help) {
	const field = el('select', { name }, options.map(([value, text]) => el('option', { value, text })));

	return el('label', { class: 'feld' }, [el('span', {}, [label + ' ', info(help)]), field]);
}

function textField(name, label, attributes) {
	const control = attributes.rows === undefined
		? el('input', { name, autocomplete: 'off', ...attributes })
		: el('textarea', { name, ...attributes });

	return el('label', { class: 'feld' }, [label, control]);
}

/** Die ausgewählten Dateien samt Ablage-Fläche; `files` bleibt die einzige Wahrheit. */
function dropZone(files, errorLine) {
	const input = el('input', { type: 'file', multiple: true, accept: ACCEPT, hidden: true });
	const list = el('ul', { class: 'liste dateien', hidden: true });
	const zone = el('button', { type: 'button', class: 'ablage' }, ['Fotos, Videos oder Ton hierher ziehen – oder ', el('b', { text: 'Dateien auswählen' })]);

	function renderList() {
		list.hidden = files.length === 0;
		list.replaceChildren(...files.map((file) => el('li', {}, [
			el('span', { class: 't' }, [file.name, el('small', { text: groesse(file.size) })]),
			el('button', {
				type: 'button',
				class: 'knopf leise gefahr klein-k',
				'aria-label': file.name + ' entfernen',
				text: 'Entfernen',
				onclick: () => {
					files.splice(files.indexOf(file), 1);
					renderList();
				},
			}),
		])));
	}

	function add(candidates) {
		const rejected = [];
		for (const file of candidates) {
			if (file.size > MAX_BYTES) {
				rejected.push(file.name + ' ist größer als 50 MB.');
			} else {
				files.push(file);
			}
		}
		zeigeFehler(errorLine, rejected.join(' '));
		renderList();
	}

	zone.addEventListener('click', () => input.click());
	input.addEventListener('change', () => {
		add([...input.files]);
		input.value = '';
	});
	zone.addEventListener('dragover', (event) => {
		event.preventDefault();
		zone.classList.add('ueber');
	});
	zone.addEventListener('dragleave', () => zone.classList.remove('ueber'));
	zone.addEventListener('drop', (event) => {
		event.preventDefault();
		zone.classList.remove('ueber');
		add([...event.dataTransfer.files]);
	});

	return [zone, input, list];
}

function formData(form, files) {
	const data = new FormData();
	for (const name of ['title', 'brief', 'links', 'priority', 'depth']) {
		data.append(name, form.elements[name].value.trim());
	}
	if (form.elements.own_material.checked) {
		data.append('own_material', '1');
	}
	for (const file of files) {
		data.append('files[]', file, file.name);
	}

	return data;
}

function submitMessage(result) {
	const lines = [SUBMITTED];
	for (const file of result.rejected || []) {
		lines.push(file.name + ': ' + file.message);
	}

	return lines.join('\n');
}

/** Öffnet den Dialog; `title` füllt das Feld „Thema“ vor (aus einer Suche ohne Treffer). */
export function oeffnen(title = '') {
	const files = [];
	const errorLine = fehlerZeile('einreichen-fehler');
	const titleError = fehlerZeile('einreichen-titel-fehler');
	const progress = el('progress', { max: '1', value: '0', hidden: true, 'aria-label': 'Fortschritt des Hochladens' });
	const submit = el('button', { type: 'submit', class: 'knopf primaer', form: 'einreichen-form', text: 'Einreichen' });
	const titleField = textField('title', 'Thema', { placeholder: 'z. B. Wie sicher sind Balkonkraftwerke?', 'aria-describedby': 'einreichen-titel-fehler' });
	const form = el('form', { id: 'einreichen-form', novalidate: true }, [
		titleField,
		titleError,
		textField('brief', 'Worum soll es gehen?', { rows: '3', placeholder: 'Fragen, Blickwinkel, was du wissen willst' }),
		textField('links', 'Links (einer pro Zeile)', { rows: '2', placeholder: 'https://…' }),
		dropZone(files, errorLine),
		el('label', { class: 'schalter' }, [
			el('input', { type: 'checkbox', name: 'own_material' }),
			' Das Material ist von mir ',
			info('Dann trägt der Artikel dich als Urheber ein, eine Lizenzangabe ist nicht nötig.'),
		]),
		el('div', { class: 'zwei' }, [
			select('priority', PRIORITIES, 'Wie dringend?', 'Sofort: Der nächste Agentenlauf nimmt das Thema zuerst auf.'),
			select('depth', DEPTHS, 'Wie tief?', 'Tiefenrecherche heißt: ab 1.200 Wörter und 5 Quellen.'),
		]),
		progress,
		errorLine,
	]);
	const titleInput = form.elements.title;
	titleInput.value = title;

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		zeigeFehler(errorLine, '');
		if (titleInput.value.trim() === '') {
			zeigeFehler(titleError, 'Gib dem Thema einen Titel.');
			titleInput.setAttribute('aria-invalid', 'true');
			titleInput.focus();

			return;
		}
		zeigeFehler(titleError, '');
		titleInput.removeAttribute('aria-invalid');

		submit.disabled = true;
		progress.hidden = false;
		try {
			const result = await upload('queue', formData(form, files), (share) => {
				progress.value = share;
			});
			document.getElementById('einreichen').close();
			toast(submitMessage(result));
		} catch (error) {
			zeigeFehler(errorLine, error.message);
			submit.disabled = false;
			progress.hidden = true;
		}
	});

	const box = dialog('einreichen', rahmen('Thema einreichen', form, [
		el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Abbrechen' }),
		submit,
	]), 'breit');
	box.querySelector('[name="title"]').focus();
}
