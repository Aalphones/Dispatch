// Dialog „Anweisungen für Agenten“ (Entwurf: docs/archive/2026-11/2026-11-12_agenten-anweisungen/artifacts/entwurf-agenten-anweisungen.html).
import { api } from './api.js';
import { dialog, el, icon, info } from './ui.js';

const DIALOG_ID = 'anweisungen';
const SPEICHER_SCHLUESSEL = 'dispatch.anweisungen.werkzeuge';
const NOTIZ_MAX = 500;
const DOKUMENTE = { richtlinien: 'editorial_guidelines', praeferenzen: 'preferences' };
const DOKUMENT_NAMEN = { editorial_guidelines: 'Richtlinien', preferences: 'Präferenzen' };
const REITER_VON_KEY = { editorial_guidelines: 'richtlinien', preferences: 'praeferenzen' };
const RECHTE = { 'blog:read': 'Lesen', 'blog:write': 'Schreiben', 'blog:admin': 'Einstellungen' };
const REITER = [['uebersicht', 'Übersicht'], ['richtlinien', 'Richtlinien'], ['praeferenzen', 'Präferenzen'], ['werkzeuge', 'Werkzeuge'], ['ablaeufe', 'Abläufe']];
const WIRKUNGEN = ['liest nur', 'ändert', 'löscht'];
const BLOCK_QUELLE_PROMPTS = 'app/src/Mcp/Prompts.php';
const PROMPTS_ID = 'prompts';

let daten = null;
let ladeStatus = 'laedt';
let box = null;
let speichertGerade = false;

const zustand = {
	reiter: 'uebersicht',
	modus: { editorial_guidelines: 'lesen', preferences: 'lesen' },
	entwurf: { editorial_guidelines: null, preferences: null },
	verlauf: { editorial_guidelines: null, preferences: null },
	verlaufMeldung: { editorial_guidelines: null, preferences: null },
	vergleiche: new Map(),
	offeneVergleiche: new Set(),
	hinweis: null,
	offeneWerkzeuge: new Set(),
	offeneBloecke: new Set(['instructions']),
	suche: '',
	filter: { recht: new Set(), wirkung: new Set() },
	panel: null,
	anzeige: ladeAnzeige(),
};

// ---------- Hilfen ----------

/** Setzt `html` (vom Server bereinigtes Markdown) in ein Element der Klasse `anw-text`. */
function textBlock(html) {
	const block = el('div', { class: 'anw-text' });
	block.innerHTML = html;

	return block;
}

function zeit(utc) {
	return new Date(utc.replace(' ', 'T') + 'Z').toLocaleString('de-DE', { timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function wer(akteur) {
	if (!akteur) {
		return { name: 'Unbekannt', art: '' };
	}
	if (akteur === 'owner') {
		return { name: 'Du', art: 'Herausgeber' };
	}
	if (akteur === 'cli') {
		return { name: 'Einrichtung', art: 'Kommandozeile' };
	}
	const oauth = akteur.match(/^oauth:(.+)$/);
	if (oauth) {
		return { name: oauth[1], art: 'Agent, angemeldet als App' };
	}
	const token = akteur.match(/^mcp:(.+)$/);
	if (token) {
		return { name: token[1], art: 'Agent mit Zugangs-Token' };
	}

	return { name: akteur, art: '' };
}

function dok(key) {
	return daten.documents.find((dokument) => dokument.key === key);
}

function stand(dokument) {
	return 'Fassung ' + dokument.revision + ' · ' + zeit(dokument.updated_at) + ' · ' + wer(dokument.actor).name;
}

/** Schlüssel des Dokuments mit ungespeichertem Entwurf, sonst null. */
function ungespeichert() {
	if (daten === null) {
		return null;
	}
	for (const key of Object.values(DOKUMENTE)) {
		const entwurf = zustand.entwurf[key];
		if (entwurf && entwurf.text !== dok(key).content) {
			return key;
		}
	}

	return null;
}

function ladeAnzeige() {
	try {
		const gespeichert = JSON.parse(localStorage.getItem(SPEICHER_SCHLUESSEL) || '{}');

		return { ansicht: gespeichert.ansicht === 'tabelle' ? 'tabelle' : 'liste', gruppe: ['bereich', 'recht', 'keine'].includes(gespeichert.gruppe) ? gespeichert.gruppe : 'bereich' };
	} catch {
		return { ansicht: 'liste', gruppe: 'bereich' };
	}
}

function speichereAnzeige() {
	try {
		localStorage.setItem(SPEICHER_SCHLUESSEL, JSON.stringify(zustand.anzeige));
	} catch {}
}

function istNetzfehler(fehler) {
	return !fehler || !fehler.error;
}

// ---------- Öffnen, Laden, Navigation ----------

export async function oeffnen() {
	if (daten === null) {
		ladeStatus = 'laedt';
	}
	zeichne();
	await laden();
}

/** Holt die Gesamtansicht; ein bereits geladener Stand bleibt bei einem Fehler stehen. */
async function laden() {
	if (daten === null) {
		ladeStatus = 'laedt';
		zeichne();
	}
	try {
		daten = await api('GET', 'instructions');
		ladeStatus = 'da';
	} catch {
		ladeStatus = daten === null ? 'fehler' : 'da';
	}
	zeichne();
}

/** Wechselt Reiter und Unteransicht; mit ungespeicherten Änderungen im Editor fragt es erst nach. */
function geheZu(reiter, unter = '') {
	const offen = ungespeichert();
	if (offen !== null && zustand.reiter === REITER_VON_KEY[offen] && zustand.modus[offen] === 'bearbeiten') {
		const bleibtBeimDokument = DOKUMENTE[reiter] === offen && (unter === 'verlauf' || unter === 'bearbeiten');
		if (!bleibtBeimDokument) {
			zustand.entwurf[offen].meldung = { art: 'verwerfen', weiter: { reiter, unter } };
			zeichne();

			return;
		}
	}
	zustand.reiter = reiter;
	zustand.hinweis = null;
	if (DOKUMENTE[reiter]) {
		dokumentAnsicht(DOKUMENTE[reiter], unter);
	} else if (reiter === 'werkzeuge' && unter) {
		zustand.offeneWerkzeuge.add(unter);
	} else if (reiter === 'ablaeufe' && unter) {
		zustand.offeneBloecke.add(unter);
	}
	zeichne(unter);
}

function dokumentAnsicht(key, unter) {
	const wunsch = unter === 'bearbeiten' ? 'bearbeiten' : unter === 'verlauf' ? 'verlauf' : 'lesen';
	if (wunsch === 'bearbeiten' && zustand.entwurf[key] === null) {
		zustand.entwurf[key] = { text: dok(key).content, notiz: '', vorschau: false, vorschauHtml: null, meldung: null, fehler: '', basis: dok(key).revision };
	}
	const offen = zustand.entwurf[key];
	if (wunsch !== 'bearbeiten' && offen && offen.text === dok(key).content) {
		zustand.entwurf[key] = null;
	}
	zustand.modus[key] = wunsch;
}

function schliessen() {
	const offen = ungespeichert();
	if (offen === null) {
		box.close();

		return;
	}
	zustand.reiter = REITER_VON_KEY[offen];
	zustand.modus[offen] = 'bearbeiten';
	zustand.entwurf[offen].meldung = { art: 'verwerfen', weiter: 'schliessen' };
	zeichne();
}

// ---------- Dialog ----------

function reiterLeiste() {
	const leiste = el('div', { class: 'reiter', role: 'tablist', 'aria-label': 'Bereiche' });
	for (const [id, name] of REITER) {
		const anzahl = id === 'werkzeuge' && daten ? el('span', { class: 'anzahl', text: String(daten.tools.length) }) : null;
		leiste.append(el('button', {
			type: 'button', role: 'tab', id: 'reiter-' + id, 'aria-selected': String(id === zustand.reiter), 'aria-controls': 'anweisungen-rumpf', tabindex: id === zustand.reiter ? '0' : '-1',
			onclick: () => geheZu(id),
		}, [name, anzahl]));
	}
	leiste.addEventListener('keydown', (event) => {
		const schritt = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
		if (schritt === 0) {
			return;
		}
		const index = REITER.findIndex(([id]) => id === zustand.reiter);
		const naechster = REITER[(index + schritt + REITER.length) % REITER.length][0];
		geheZu(naechster);
		requestAnimationFrame(() => document.getElementById('reiter-' + naechster)?.focus());
	});

	return leiste;
}

/** Hängt Kinder an; null und false entfallen (Node.append machte daraus den Text „null“). */
function fuelle(eltern, ...kinder) {
	eltern.append(...kinder.flat(Infinity).filter((kind) => kind !== null && kind !== false && kind !== undefined));
}

function ladeZeilen() {
	return el('div', { 'aria-busy': 'true', 'aria-label': 'Anweisungen werden geladen' }, [
		el('div', { class: 'lader titel' }), el('div', { class: 'lader' }), el('div', { class: 'lader' }), el('div', { class: 'lader kurz' }), el('div', { class: 'lader' }), el('div', { class: 'lader kurz' }),
	]);
}

function fehlerAnsicht(text, erneut) {
	return el('div', { class: 'leer', role: 'alert' }, [icon('warn'), el('p', { text }), el('button', { type: 'button', class: 'knopf', text: 'Erneut versuchen', onclick: erneut })]);
}

function zeichne(unter = '') {
	const rumpf = el('div', { class: 'dlg-rumpf', id: 'anweisungen-rumpf', role: 'tabpanel', 'aria-labelledby': 'reiter-' + zustand.reiter });
	const fuss = el('div', { class: 'dlg-fuss', hidden: true });
	let nachher = null;
	if (daten === null && ladeStatus === 'laedt') {
		fuelle(rumpf, ladeZeilen());
	} else if (daten === null) {
		fuelle(rumpf, fehlerAnsicht('Die Anweisungen ließen sich nicht laden. Der Server hat nicht geantwortet.', laden));
	} else if (zustand.reiter === 'uebersicht') {
		fuelle(rumpf, ...uebersicht());
	} else if (DOKUMENTE[zustand.reiter]) {
		dokument(DOKUMENTE[zustand.reiter], rumpf, fuss);
	} else if (zustand.reiter === 'werkzeuge') {
		fuelle(rumpf, ...werkzeuge());
		nachher = () => document.getElementById('werkzeug-' + unter)?.scrollIntoView({ block: 'start' });
	} else if (zustand.reiter === 'ablaeufe') {
		fuelle(rumpf, ...ablaeufe());
		nachher = () => document.getElementById('block-' + unter)?.scrollIntoView({ block: 'start' });
	}
	const vorher = box?.querySelector('.dlg-rumpf');
	const gleich = vorher && box.dataset.ansicht === ansichtKennung();
	const scroll = gleich ? vorher.scrollTop : 0;
	box = dialog(DIALOG_ID, [
		el('div', { class: 'dlg-kopf' }, [
			el('h2', { tabindex: '-1', text: 'Anweisungen für Agenten' }),
			el('button', { type: 'button', class: 'zu', 'aria-label': 'Schließen', text: '✕', onclick: schliessen }),
		]),
		reiterLeiste(),
		rumpf,
		fuss,
	], 'breit anweisungen');
	box.dataset.ansicht = ansichtKennung();
	verdrahte();
	if (gleich) {
		rumpf.scrollTop = scroll;
	}
	if (nachher && unter) {
		requestAnimationFrame(nachher);
	}
}

function ansichtKennung() {
	return zustand.reiter + (DOKUMENTE[zustand.reiter] ? '/' + zustand.modus[DOKUMENTE[zustand.reiter]] : '');
}

/** Hängt die Ereignisse des Dialogs einmal an: Esc, Klick neben ein offenes Panel. */
function verdrahte() {
	if (box.dataset.verdrahtet) {
		return;
	}
	box.dataset.verdrahtet = '1';
	box.addEventListener('cancel', (event) => {
		if (zustand.panel) {
			event.preventDefault();
			zustand.panel = null;
			zeichne();

			return;
		}
		if (ungespeichert()) {
			event.preventDefault();
			schliessen();
		}
	});
	box.addEventListener('click', (event) => {
		if (zustand.panel && !event.target.closest('.anw-aufklapp')) {
			zustand.panel = null;
			zeichne();
		}
	});
}

// ---------- Reiter „Übersicht“ ----------

function teil(text, unterzeile, ziel, herkunft) {
	const inhalt = [
		el('span', { class: 't' }, [el('b', { text }), unterzeile ? el('small', {}, unterzeile) : null]),
		el('span', { class: 'anw-herkunft' + (herkunft === 'hier' ? ' hier' : ''), text: herkunft === 'hier' ? 'hier änderbar' : herkunft }),
	];

	return el('li', {}, ziel ? el('button', { type: 'button', class: 'anw-sprungknopf', onclick: () => geheZu(ziel[0], ziel[1]) }, inhalt) : el('div', { class: 'ohne-link' }, inhalt));
}

function uebersicht() {
	const regeln = daten.form_rules;
	const schritt = (wann, werkzeug, teile) => el('li', {}, [el('div', {}, [el('p', { class: 'anw-wann' }, [wann, werkzeug ? el('code', { text: werkzeug }) : null]), el('ul', { class: 'anw-teile' }, teile)])]);

	return [
		el('p', { class: 'anw-lead', text: 'Ein Agent bekommt seine Anweisungen in fünf Schritten. Was hier als „hier änderbar“ markiert ist, kannst du in diesem Dialog bearbeiten; es gilt ab seinem nächsten Lauf.' }),
		el('ol', { class: 'anw-kette' }, [
			schritt('Beim Verbinden', null, [teil('Server-Anweisung', 'Wie ein Redaktionslauf beginnt und was verbindlich ist', ['ablaeufe', 'instructions'], 'fest im Code')]),
			schritt('Zu Beginn jedes Laufs', 'get_editorial_brief', [
				teil('Redaktionsrichtlinien', stand(dok('editorial_guidelines')), ['richtlinien', ''], 'hier'),
				teil('Präferenzen', stand(dok('preferences')), ['praeferenzen', ''], 'hier'),
				teil('Formvorgaben', `Mindestens ${regeln.min_words} Wörter und ${regeln.min_sources} Quellen, bei Tiefe „tief“ ${Number(regeln.min_words_deep).toLocaleString('de-DE')} und ${regeln.min_sources_deep}; Titelbild und Bildlizenz Pflicht`, null, 'fest in der Konfiguration'),
				teil('Workflow des gewählten Wegs', 'Geplanter Artikel, Auftrag aus der Queue oder zurückgegebener Entwurf', ['ablaeufe', 'workflow-scheduled'], 'fest im Code'),
			]),
			schritt('Vor dem Schreiben', 'get_style_guide', [teil('Style-Guide', 'Erlaubtes HTML, Gestaltungsmittel, Bilder, Belege, Formprüfung', ['ablaeufe', 'style-guide'], 'fest im Code')]),
			schritt('Bei jedem Werkzeug', null, [teil(daten.tools.length + ' Werkzeuge', 'Je Werkzeug Beschreibung, Angaben und Hinweise', ['werkzeuge', ''], 'fest im Code')]),
			schritt('Beim Prüfen und Einreichen', 'validate_post · submit_post', [teil('Formprüfung', 'Fehler blockieren das Einreichen, Warnungen nicht — darunter fehlende „Kurz gesagt“-Kästen', ['ablaeufe', 'style-guide'], 'fest im Code')]),
		]),
		el('section', { class: 'anw-zuletzt', 'aria-labelledby': 'zuletzt-titel' }, [
			el('h3', { id: 'zuletzt-titel', text: 'Zuletzt geändert' }),
			el('ul', {}, Object.keys(DOKUMENT_NAMEN).map((key) => el('li', {}, DOKUMENT_NAMEN[key] + ': ' + stand(dok(key)) + (dok(key).note ? ' — „' + dok(key).note + '“' : '')))),
		]),
	];
}

// ---------- Reiter „Richtlinien“ und „Präferenzen“ ----------

function dokument(key, rumpf, fuss) {
	const modus = zustand.modus[key];
	if (modus === 'bearbeiten') {
		bearbeiten(key, rumpf, fuss);
	} else if (modus === 'verlauf') {
		verlauf(key, rumpf);
	} else {
		lesen(key, rumpf);
	}
}

function lesen(key, rumpf) {
	const dokumentDaten = dok(key);
	const reiter = REITER_VON_KEY[key];
	const urheber = wer(dokumentDaten.actor);
	const text = textBlock(dokumentDaten.html);
	const ueberschriften = [...text.querySelectorAll('h3')];
	ueberschriften.forEach((ueberschrift, index) => { ueberschrift.id = 'abschnitt-' + index; });
	fuelle(rumpf, 
		el('div', { class: 'anw-kopf' }, [
			el('p', { class: 'anw-stand' }, [el('b', { text: 'Fassung ' + dokumentDaten.revision }), ' · ' + zeit(dokumentDaten.updated_at) + ' · ' + urheber.name + (urheber.art ? ' (' + urheber.art + ')' : ''), info('Gilt ab dem nächsten Agenten-Lauf. Agenten lesen das Dokument im Briefing und ändern es mit dem Werkzeug update_document; jede Änderung landet im Verlauf.')]),
			el('span', { class: 'anw-aktionen' }, [
				el('button', { type: 'button', class: 'knopf leise', onclick: () => geheZu(reiter, 'verlauf') }, [icon('uhr'), 'Verlauf']),
				el('button', { type: 'button', class: 'knopf primaer', onclick: () => geheZu(reiter, 'bearbeiten') }, [icon('stift'), zustand.entwurf[key] ? 'Weiter bearbeiten' : DOKUMENT_NAMEN[key] + ' bearbeiten']),
			]),
		]),
		hinweisZeile(),
		ueberschriften.length > 2 ? el('nav', { 'aria-label': 'Abschnitte' }, el('ul', { class: 'anw-sprung' }, ueberschriften.map((ueberschrift) => el('li', {}, el('button', {
			type: 'button', onclick: () => ueberschrift.scrollIntoView({ block: 'start' }),
		}, ueberschrift.textContent))))) : null,
		text,
	);
}

/** Meldung „Gespeichert als Fassung N …“ mit „Rückgängig“, solange sie im Zustand steht. */
function hinweisZeile() {
	if (!zustand.hinweis) {
		return null;
	}
	const hinweis = zustand.hinweis;

	return el('div', { class: 'anw-meldung ruhig', role: 'status' }, [
		el('p', { text: hinweis.text }),
		hinweis.rueckgaengig ? el('div', { class: 'knopfzeile' }, el('button', { type: 'button', class: 'knopf', text: 'Rückgängig', onclick: hinweis.rueckgaengig })) : null,
	]);
}

function konfliktText(key, details, basis, aktion) {
	const fremd = wer(details.actor).name;
	const wann = details.created_at ? ', ' + zeit(details.created_at) : '';

	return [el('b', { text: 'Noch nicht ' + aktion + ': ' }), `${fremd} hat die ${DOKUMENT_NAMEN[key]} inzwischen geändert (Fassung ${details.current_revision}${wann}). Du hast auf Fassung ${basis} aufgebaut.`];
}

function meldung(key) {
	const entwurf = zustand.entwurf[key];
	if (!entwurf.meldung) {
		return null;
	}
	if (entwurf.meldung.art === 'verwerfen') {
		return el('div', { class: 'anw-meldung ruhig', role: 'alert' }, [
			el('p', {}, [el('b', { text: 'Deine Änderungen sind noch nicht gespeichert. ' }), 'Verwerfen oder weiter bearbeiten?']),
			el('div', { class: 'knopfzeile' }, [
				el('button', { type: 'button', class: 'knopf', text: 'Weiter bearbeiten', onclick: () => { entwurf.meldung = null; zeichne(); } }),
				el('button', { type: 'button', class: 'knopf gefahr', text: 'Änderungen verwerfen', onclick: () => {
					const weiter = entwurf.meldung.weiter;
					zustand.entwurf[key] = null;
					zustand.modus[key] = 'lesen';
					if (weiter === 'schliessen') {
						zeichne();
						box.close();
					} else {
						geheZu(weiter.reiter, weiter.unter);
					}
				} }),
			]),
		]);
	}
	if (entwurf.meldung.art === 'konflikt') {
		return el('div', { class: 'anw-meldung', role: 'alert' }, [
			el('p', {}, konfliktText(key, entwurf.meldung.details, entwurf.basis, 'gespeichert')),
			el('p', { text: 'Speicherst du trotzdem, wird deine Fassung die neueste; die des Agenten bleibt im Verlauf und lässt sich zurückholen.' }),
			el('div', { class: 'knopfzeile' }, [
				el('button', { type: 'button', class: 'knopf primaer', text: 'Meine Fassung trotzdem speichern', onclick: () => speichern(key, true) }),
				el('button', { type: 'button', class: 'knopf', text: 'Änderung des Agenten ansehen', onclick: () => { entwurf.meldung = null; geheZu(REITER_VON_KEY[key], 'verlauf'); } }),
			]),
		]);
	}

	return el('div', { class: 'anw-meldung', role: 'alert' }, [
		el('p', {}, [el('b', { text: 'Speichern fehlgeschlagen: ' }), 'keine Verbindung zum Server. Dein Text bleibt hier stehen.']),
		el('div', { class: 'knopfzeile' }, [el('button', { type: 'button', class: 'knopf', text: 'Erneut speichern', onclick: () => speichern(key, false) })]),
	]);
}

function bearbeiten(key, rumpf, fuss) {
	const entwurf = zustand.entwurf[key];
	const geaendert = entwurf.text !== dok(key).content;
	const zuLang = entwurf.notiz.length > NOTIZ_MAX;
	const fehler = el('p', { class: 'feldfehler', id: 'inhalt-fehler', role: 'alert', hidden: entwurf.fehler === '', text: entwurf.fehler });
	const speichernKnopf = el('button', { type: 'button', class: 'knopf primaer', title: 'Strg+S', onclick: () => speichern(key, false) }, 'Fassung speichern');
	const fusstext = el('span', { class: 'anw-fusstext', text: geaendert ? 'Ungespeicherte Änderungen' : 'Noch nichts geändert' });
	const aktualisiereFuss = () => {
		speichernKnopf.disabled = entwurf.text === dok(key).content || entwurf.notiz.length > NOTIZ_MAX;
		fusstext.textContent = entwurf.text !== dok(key).content ? 'Ungespeicherte Änderungen' : 'Noch nichts geändert';
	};
	const editor = el('textarea', {
		class: 'anw-editor', id: 'anw-editor', 'aria-label': DOKUMENT_NAMEN[key] + ' als Markdown', spellcheck: 'true', 'aria-invalid': entwurf.fehler ? 'true' : null, 'aria-describedby': 'inhalt-fehler',
		oninput: (event) => {
			entwurf.text = event.target.value;
			entwurf.vorschauHtml = null;
			if (entwurf.fehler !== '') {
				entwurf.fehler = '';
				fehler.hidden = true;
				fehler.textContent = '';
				editor.removeAttribute('aria-invalid');
			}
			aktualisiereFuss();
		},
	});
	editor.value = entwurf.text;
	const zaehlung = el('span', { class: 'zaehlung' + (zuLang ? ' zu-lang' : ''), text: entwurf.notiz.length + ' / ' + NOTIZ_MAX });
	const notiz = el('input', {
		type: 'text', id: 'anw-notiz', value: entwurf.notiz, 'aria-describedby': 'notiz-hilfe', 'aria-invalid': zuLang ? 'true' : null,
		oninput: (event) => {
			entwurf.notiz = event.target.value;
			zaehlung.textContent = entwurf.notiz.length + ' / ' + NOTIZ_MAX;
			zaehlung.classList.toggle('zu-lang', entwurf.notiz.length > NOTIZ_MAX);
			aktualisiereFuss();
		},
	});
	const umschalter = el('div', { class: 'anw-umschalter', role: 'group', 'aria-label': 'Darstellung' }, [
		el('button', { type: 'button', 'aria-pressed': String(!entwurf.vorschau), text: 'Text', onclick: () => { entwurf.vorschau = false; zeichne(); } }),
		el('button', { type: 'button', 'aria-pressed': String(entwurf.vorschau), text: 'Vorschau', onclick: () => vorschauZeigen(entwurf) }),
	]);
	fuelle(rumpf, 
		meldung(key),
		el('div', { class: 'anw-bearbeiten' }, [
			el('div', { class: 'anw-kopf' }, [el('p', { class: 'anw-stand' }, [el('b', { text: DOKUMENT_NAMEN[key] + ' bearbeiten' }), ' · Grundlage: Fassung ' + entwurf.basis, info('Markdown: ## Überschrift, - Liste, **fett**, `Code`, | Tabelle |. Speichern legt eine neue Fassung an; die bisherige bleibt im Verlauf.')]), umschalter]),
			entwurf.vorschau ? vorschauAnsicht(entwurf) : el('div', {}, [editor, fehler]),
			el('label', { class: 'anw-notiz', for: 'anw-notiz' }, ['Was hast du geändert?', notiz, el('span', { class: 'feldhilfe', id: 'notiz-hilfe' }, [el('span', { text: 'Optional. Erscheint im Verlauf, damit du und die Agenten den Grund kennen.' }), zaehlung])]),
		]),
	);
	const abbrechen = el('button', { type: 'button', class: 'knopf leise', text: 'Abbrechen', onclick: () => {
		if (ungespeichert() === key) {
			entwurf.meldung = { art: 'verwerfen', weiter: { reiter: REITER_VON_KEY[key], unter: '' } };
			zeichne();
		} else {
			zustand.entwurf[key] = null;
			geheZu(REITER_VON_KEY[key]);
		}
	} });
	aktualisiereFuss();
	fuss.hidden = false;
	fuss.append(fusstext, abbrechen, speichernKnopf);
	rumpf.addEventListener('keydown', (event) => {
		if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
			event.preventDefault();
			if (!speichernKnopf.disabled) {
				speichern(key, false);
			}
		}
	});
	if (!entwurf.vorschau && !entwurf.meldung) {
		requestAnimationFrame(() => editor.focus({ preventScroll: true }));
	}
}

function vorschauAnsicht(entwurf) {
	if (entwurf.vorschauHtml === null) {
		return el('div', { class: 'anw-vorschau', 'aria-busy': 'true', 'aria-label': 'Vorschau wird geladen' }, [el('div', { class: 'lader' }), el('div', { class: 'lader kurz' }), el('div', { class: 'lader' })]);
	}

	return el('div', { class: 'anw-vorschau' }, textBlock(entwurf.vorschauHtml));
}

async function vorschauZeigen(entwurf) {
	entwurf.vorschau = true;
	entwurf.vorschauHtml = null;
	zeichne();
	const angefragt = entwurf.text;
	try {
		const antwort = await api('POST', 'instructions/preview', { content: angefragt });
		entwurf.vorschauHtml = antwort.html;
	} catch (fehler) {
		entwurf.vorschau = false;
		entwurf.fehler = istNetzfehler(fehler) ? 'Die Vorschau ließ sich nicht laden: keine Verbindung zum Server. Dein Text bleibt hier stehen.' : fehler.message;
	}
	zeichne();
}

function ersetzeDokument(neu) {
	daten.documents = daten.documents.map((dokumentDaten) => (dokumentDaten.key === neu.key ? neu : dokumentDaten));
	zustand.verlauf[neu.key] = null;
	for (const kennung of [...zustand.vergleiche.keys()]) {
		if (kennung.startsWith(neu.key + ':')) {
			zustand.vergleiche.delete(kennung);
		}
	}
	zustand.offeneVergleiche.clear();
}

async function speichern(key, trotzdem) {
	const entwurf = zustand.entwurf[key];
	if (speichertGerade) {
		return;
	}
	if (entwurf.text.trim() === '') {
		entwurf.fehler = 'Der Inhalt darf nicht leer sein — ohne Text arbeiten die Agenten ohne diese Regeln.';
		zeichne();
		requestAnimationFrame(() => document.getElementById('anw-editor')?.focus({ preventScroll: true }));

		return;
	}
	const vorher = dok(key);
	entwurf.meldung = null;
	speichertGerade = true;
	try {
		const antwort = await api('PATCH', 'instructions/' + vorher.slug, { content: entwurf.text, note: entwurf.notiz.trim() || null, base_revision: entwurf.basis, force: trotzdem });
		ersetzeDokument(antwort.document);
		zustand.entwurf[key] = null;
		zustand.modus[key] = 'lesen';
		const neu = antwort.document;
		zustand.hinweis = { text: `Gespeichert als Fassung ${neu.revision}. Gilt ab dem nächsten Agenten-Lauf.`, rueckgaengig: () => stelleHer(key, vorher.revision, neu.revision, false, false) };
		zeichne();
	} catch (fehler) {
		if (fehler && fehler.error === 'revision_conflict') {
			entwurf.meldung = { art: 'konflikt', details: fehler.details };
		} else if (istNetzfehler(fehler)) {
			entwurf.meldung = { art: 'offline' };
		} else {
			entwurf.fehler = fehler.message;
		}
		zeichne();
	} finally {
		speichertGerade = false;
	}
}

/** Legt die Fassung `revision` als neue Fassung wieder an; `basis` ist die zuletzt bekannte gültige. */
async function stelleHer(key, revision, basis, trotzdem, mitRueckgaengig = true) {
	const vorher = dok(key).revision;
	try {
		const antwort = await api('POST', 'instructions/' + dok(key).slug + '/restore', { revision, base_revision: basis, force: trotzdem });
		const neu = antwort.document;
		ersetzeDokument(neu);
		zustand.verlaufMeldung[key] = null;
		zustand.hinweis = {
			text: `Fassung ${revision} ist als Fassung ${neu.revision} wieder gültig.`,
			rueckgaengig: mitRueckgaengig ? () => stelleHer(key, vorher, neu.revision, false, false) : null,
		};
		zeichne();
	} catch (fehler) {
		if (fehler && fehler.error === 'revision_conflict') {
			zustand.verlaufMeldung[key] = { art: 'konflikt', details: fehler.details, revision, basis };
			zustand.reiter = REITER_VON_KEY[key];
			zustand.modus[key] = 'verlauf';
			zustand.hinweis = null;
		} else {
			zustand.hinweis = { text: istNetzfehler(fehler) ? 'Wiederherstellen fehlgeschlagen: keine Verbindung zum Server.' : fehler.message, rueckgaengig: null };
		}
		zeichne();
	}
}

// ---------- Verlauf ----------

async function verlaufLaden(key) {
	zustand.verlauf[key] = { status: 'laedt' };
	try {
		const antwort = await api('GET', 'instructions/' + dok(key).slug + '/revisions');
		zustand.verlauf[key] = { status: 'da', liste: antwort.revisions };
	} catch {
		zustand.verlauf[key] = { status: 'fehler' };
	}
	zeichne();
}

async function vergleichLaden(key, revision) {
	const kennung = key + ':' + revision;
	zustand.vergleiche.set(kennung, { status: 'laedt' });
	try {
		zustand.vergleiche.set(kennung, { status: 'da', antwort: await api('GET', 'instructions/' + dok(key).slug + '/revisions/' + revision) });
	} catch {
		zustand.vergleiche.set(kennung, { status: 'fehler' });
	}
	zeichne();
}

/** Der Vergleich aus dem Server-HTML: unveränderte Blöcke klappen zu einer Zeile zusammen. */
function vergleichsAnsicht(diffHtml) {
	const vorlage = document.createElement('template');
	vorlage.innerHTML = diffHtml;
	const teile = [];
	let gleich = [];
	const unveraendert = () => {
		if (gleich.length === 0) {
			return;
		}
		const knoten = gleich;
		const zeile = el('p', { class: 'anw-unveraendert' }, el('button', {
			type: 'button', class: 'knopf leise klein-k', 'aria-expanded': 'false',
			text: knoten.length === 1 ? '1 Absatz unverändert' : knoten.length + ' Absätze unverändert',
			onclick: () => zeile.replaceWith(el('div', { class: 'anw-text' }, knoten)),
		}));
		teile.push(zeile);
		gleich = [];
	};
	for (const kind of [...vorlage.content.children]) {
		const weg = kind.classList.contains('diff-weg');
		const neu = kind.classList.contains('diff-neu');
		if (!weg && !neu && kind.querySelector('ins, del') === null) {
			gleich.push(kind);
			continue;
		}
		unveraendert();
		kind.prepend(el('small', { class: 'anw-diffwort', text: weg ? 'Entfernt' : neu ? 'Neu' : 'Geändert' }));
		teile.push(el('div', { class: 'anw-text' }, kind));
	}
	unveraendert();
	if (teile.length === 0) {
		teile.push(el('p', { class: 'anw-unveraendert', text: 'Kein Unterschied im Text.' }));
	}

	return teile;
}

function vergleichInhalt(key, revision) {
	const eintrag = zustand.vergleiche.get(key + ':' + revision);
	if (!eintrag || eintrag.status === 'laedt') {
		return el('div', { 'aria-busy': 'true', 'aria-label': 'Vergleich wird geladen' }, [el('div', { class: 'lader' }), el('div', { class: 'lader kurz' })]);
	}
	if (eintrag.status === 'fehler') {
		return el('p', { class: 'anw-unveraendert' }, ['Der Vergleich ließ sich nicht laden. ', el('button', { type: 'button', class: 'knopf klein-k', text: 'Erneut versuchen', onclick: () => vergleichLaden(key, revision) })]);
	}
	if (!eintrag.antwort.diff) {
		return el('p', { class: 'anw-unveraendert', text: 'Das ist die erste Fassung, es gibt nichts zu vergleichen.' });
	}

	return vergleichsAnsicht(eintrag.antwort.diff.html);
}

function verlaufMeldungKonflikt(key) {
	const eintrag = zustand.verlaufMeldung[key];
	if (!eintrag) {
		return null;
	}

	return el('div', { class: 'anw-meldung', role: 'alert' }, [
		el('p', {}, konfliktText(key, eintrag.details, eintrag.basis, 'wiederhergestellt')),
		el('p', { text: 'Stellst du trotzdem wieder her, wird die gewählte Fassung die neueste; die des Agenten bleibt im Verlauf.' }),
		el('div', { class: 'knopfzeile' }, [
			el('button', { type: 'button', class: 'knopf primaer', text: 'Trotzdem wiederherstellen', onclick: () => stelleHer(key, eintrag.revision, eintrag.basis, true) }),
			el('button', { type: 'button', class: 'knopf', text: 'Verlauf neu laden', onclick: () => { zustand.verlaufMeldung[key] = null; laden(); } }),
		]),
	]);
}

function verlauf(key, rumpf) {
	const reiter = REITER_VON_KEY[key];
	const entwurf = zustand.entwurf[key];
	const eintrag = zustand.verlauf[key];
	if (eintrag === null) {
		verlaufLaden(key);
	}
	fuelle(rumpf, 
		hinweisZeile(),
		entwurf ? el('div', { class: 'anw-meldung ruhig' }, [el('p', {}, [el('b', { text: 'Dein Entwurf ist noch offen. ' }), 'Er wartet unverändert, bis du ihn speicherst oder verwirfst.']), el('div', { class: 'knopfzeile' }, el('button', { type: 'button', class: 'knopf', text: 'Weiter bearbeiten', onclick: () => geheZu(reiter, 'bearbeiten') }))]) : null,
		verlaufMeldungKonflikt(key),
	);
	if (eintrag === null || eintrag.status === 'laedt') {
		fuelle(rumpf, ladeZeilen());

		return;
	}
	if (eintrag.status === 'fehler') {
		fuelle(rumpf, fehlerAnsicht('Der Verlauf ließ sich nicht laden. Der Server hat nicht geantwortet.', () => verlaufLaden(key)));

		return;
	}
	const fassungen = eintrag.liste;
	fuelle(rumpf, 
		el('div', { class: 'anw-kopf' }, [
			el('button', { type: 'button', class: 'knopf leise', onclick: () => geheZu(reiter) }, [icon('zurueck'), 'Gültige Fassung']),
			el('p', { class: 'anw-stand' }, [el('b', { text: 'Verlauf der ' + DOKUMENT_NAMEN[key] }), ' · ' + fassungen.length + (fassungen.length === 1 ? ' Fassung' : ' Fassungen')]),
		]),
		el('ol', { class: 'anw-verlauf' }, fassungen.map((fassung, index) => {
			const vorgaenger = fassungen[index + 1];
			const urheber = wer(fassung.actor);
			const kennung = key + ':' + fassung.revision;
			const offen = zustand.offeneVergleiche.has(kennung);

			return el('li', {}, [
				el('span', { class: 'anw-rev' }, [String(fassung.revision), index === 0 ? el('small', { text: 'gültig' }) : null]),
				el('span', { class: 'anw-zeit', text: zeit(fassung.created_at) }),
				el('span', { class: 'anw-wer' }, [el('b', { text: urheber.name }), urheber.art ? ' · ' + urheber.art : '', el('small', { text: fassung.note ? '„' + fassung.note + '“' : 'ohne Anmerkung' })]),
				el('span', { class: 'aktionen' }, [
					vorgaenger ? el('button', { type: 'button', class: 'knopf klein-k', 'aria-expanded': String(offen), onclick: () => {
						if (offen) {
							zustand.offeneVergleiche.delete(kennung);
							zeichne();
						} else {
							zustand.offeneVergleiche.add(kennung);
							if (zustand.vergleiche.has(kennung)) {
								zeichne();
							} else {
								vergleichLaden(key, fassung.revision);
							}
						}
					} }, offen ? 'Vergleich schließen' : 'Mit Fassung ' + vorgaenger.revision + ' vergleichen') : null,
					index > 0 ? el('button', { type: 'button', class: 'knopf klein-k', text: 'Wiederherstellen', onclick: () => stelleHer(key, fassung.revision, dok(key).revision, false) }) : null,
				]),
				offen ? el('div', { class: 'anw-vergleich' }, vergleichInhalt(key, fassung.revision)) : null,
			]);
		})),
	);
}

// ---------- Reiter „Werkzeuge“ ----------

function wirkung(werkzeug) {
	if (werkzeug.annotations.readOnlyHint) {
		return 'liest nur';
	}

	return werkzeug.annotations.destructiveHint ? 'löscht' : 'ändert';
}

function bereich(werkzeug) {
	return werkzeug.group || 'Weitere';
}

function passt(werkzeug) {
	const { recht, wirkung: wirkungen } = zustand.filter;
	if (recht.size && !recht.has(RECHTE[werkzeug.scope])) {
		return false;
	}
	if (wirkungen.size && !wirkungen.has(wirkung(werkzeug))) {
		return false;
	}
	const suche = zustand.suche.trim().toLowerCase();
	if (!suche) {
		return true;
	}
	const text = [werkzeug.name, werkzeug.title, werkzeug.description, ...Object.keys(werkzeug.inputSchema.properties || {})].join(' ').toLowerCase();

	return suche.split(/\s+/).every((wort) => text.includes(wort));
}

function typ(schema) {
	const namen = { string: 'Text', integer: 'Ganzzahl', number: 'Zahl', boolean: 'ja/nein', object: 'Objekt' };
	const art = Array.isArray(schema.type) ? schema.type.filter((t) => t !== 'null')[0] : schema.type;
	if (art === 'array') {
		return 'Liste' + (schema.items ? ' (' + typ(schema.items) + ')' : '');
	}

	return namen[art] || art || 'beliebig';
}

function werte(schema) {
	const teile = [];
	if (schema.enum) {
		teile.push('Werte: ' + schema.enum.join(', '));
	}
	if (schema.minimum !== undefined || schema.maximum !== undefined) {
		teile.push('Bereich: ' + (schema.minimum ?? '…') + ' bis ' + (schema.maximum ?? '…'));
	}
	if (schema.maxLength !== undefined) {
		teile.push('höchstens ' + schema.maxLength + ' Zeichen');
	}
	if (schema.default !== undefined) {
		teile.push('Standard: ' + JSON.stringify(schema.default));
	}

	return teile.join(' · ');
}

function werkzeugDetail(werkzeug) {
	const eigenschaften = Object.entries(werkzeug.inputSchema.properties || {});
	const pflicht = werkzeug.inputSchema.required || [];
	const hinweise = [werkzeug.annotations.readOnlyHint ? 'liest nur' : 'schreibt', werkzeug.annotations.destructiveHint ? 'kann löschen' : null, werkzeug.annotations.idempotentHint ? 'wiederholbar ohne Nebenwirkung' : null, werkzeug.annotations.openWorldHint ? 'greift nach außen (Internet)' : 'bleibt in DISPATCH'].filter(Boolean);

	return el('div', { class: 'anw-detail' }, [
		el('p', { class: 'anw-beschreibung', text: werkzeug.description }),
		eigenschaften.length === 0 ? el('p', { class: 'anw-hinweise', text: 'Keine Angaben nötig.' }) : el('table', { class: 'anw-params' }, [
			el('thead', {}, el('tr', {}, ['Angabe', 'Art', 'Pflicht', 'Beschreibung'].map((titel) => el('th', { scope: 'col', text: titel })))),
			el('tbody', {}, eigenschaften.map(([name, schema]) => el('tr', {}, [
				el('td', { text: name }),
				el('td', { 'data-spalte': 'Art', text: typ(schema) }),
				el('td', { 'data-spalte': 'Pflicht', text: pflicht.includes(name) ? 'ja' : 'nein' }),
				el('td', {}, [schema.description || '—', werte(schema) ? el('span', { class: 'werte', text: werte(schema) }) : null]),
			]))),
		]),
		el('p', { class: 'anw-hinweise', text: 'Hinweise an den Agenten: ' + hinweise.join(' · ') + ' · Recht: ' + RECHTE[werkzeug.scope] }),
	]);
}

function werkzeugKopf(werkzeug, offen, imTabelle) {
	const umschalten = () => {
		if (offen) {
			zustand.offeneWerkzeuge.delete(werkzeug.name);
		} else {
			zustand.offeneWerkzeuge.add(werkzeug.name);
		}
		zeichne();
	};
	const pfeil = el('span', { class: 'anw-pfeil', text: '▸', 'aria-hidden': 'true' });
	if (imTabelle) {
		return el('button', { type: 'button', class: 'anw-tool-kopf', 'aria-expanded': String(offen), onclick: umschalten }, [pfeil, el('span', { class: 'anw-name', text: werkzeug.name })]);
	}

	return el('button', { type: 'button', class: 'anw-tool-kopf', 'aria-expanded': String(offen), onclick: umschalten }, [
		pfeil,
		el('span', { class: 'anw-name', text: werkzeug.name }),
		el('span', { class: 'anw-titel', text: werkzeug.title }),
		el('span', { class: 'anw-merkmale' }, [el('span', { class: 'anw-wort', text: RECHTE[werkzeug.scope] }), el('span', { class: 'anw-wort' + (wirkung(werkzeug) === 'löscht' ? ' loescht' : ''), text: wirkung(werkzeug) })]),
	]);
}

function gruppiert(liste) {
	if (zustand.anzeige.gruppe === 'keine') {
		return [[null, liste]];
	}
	const schluessel = zustand.anzeige.gruppe === 'recht' ? (werkzeug) => RECHTE[werkzeug.scope] : bereich;
	const reihenfolge = zustand.anzeige.gruppe === 'recht' ? Object.values(RECHTE) : [...new Set(daten.tools.map(bereich))];

	return reihenfolge.map((name) => [name, liste.filter((werkzeug) => schluessel(werkzeug) === name)]).filter(([, gruppe]) => gruppe.length);
}

function filterPanel() {
	const kaestchen = (art, wert) => el('label', {}, [el('input', { type: 'checkbox', checked: zustand.filter[art].has(wert), onchange: (event) => {
		if (event.target.checked) {
			zustand.filter[art].add(wert);
		} else {
			zustand.filter[art].delete(wert);
		}
		zeichne();
	} }), wert]);

	return el('div', { class: 'anw-panel', id: 'panel-filter', hidden: zustand.panel !== 'filter' }, [
		el('fieldset', {}, [el('legend', { text: 'Recht' }), Object.values(RECHTE).map((wert) => kaestchen('recht', wert))]),
		el('fieldset', {}, [el('legend', { text: 'Wirkung' }), WIRKUNGEN.map((wert) => kaestchen('wirkung', wert))]),
	]);
}

function anzeigePanel() {
	const optionen = [['bereich', 'Bereich'], ['recht', 'Recht'], ['keine', 'Ohne Gruppen']];

	return el('div', { class: 'anw-panel', id: 'panel-anzeige', hidden: zustand.panel !== 'anzeige' }, [
		el('fieldset', {}, [el('legend', { text: 'Gruppieren nach' }), optionen.map(([wert, name]) => el('label', {}, [el('input', { type: 'radio', name: 'gruppe', checked: zustand.anzeige.gruppe === wert, onchange: () => { zustand.anzeige.gruppe = wert; speichereAnzeige(); zeichne(); } }), name]))]),
	]);
}

function werkzeuge() {
	const zaehle = (recht) => daten.tools.filter((werkzeug) => werkzeug.scope === recht).length;
	const treffer = daten.tools.filter(passt);
	const aktivFilter = [...zustand.filter.recht].map((wert) => ['recht', wert]).concat([...zustand.filter.wirkung].map((wert) => ['wirkung', wert]));
	const panelKnopf = (id, name) => el('div', { class: 'anw-aufklapp' }, [
		el('button', { type: 'button', class: 'knopf', 'aria-expanded': String(zustand.panel === id), 'aria-controls': 'panel-' + id, onclick: () => { zustand.panel = zustand.panel === id ? null : id; zeichne(); } }, [name, ' ▾']),
		id === 'filter' ? filterPanel() : anzeigePanel(),
	]);
	const suchfeld = el('input', { type: 'search', id: 'anw-suche', placeholder: 'Werkzeug, Angabe oder Stichwort', 'aria-label': 'Werkzeuge durchsuchen', value: zustand.suche });
	suchfeld.addEventListener('input', () => {
		zustand.suche = suchfeld.value;
		zeichne();
		requestAnimationFrame(() => {
			const feld = document.getElementById('anw-suche');
			feld.focus();
			feld.setSelectionRange(feld.value.length, feld.value.length);
		});
	});
	const ansicht = el('div', { class: 'anw-umschalter anw-ansicht', role: 'group', 'aria-label': 'Ansicht' }, [['liste', 'Liste'], ['tabelle', 'Tabelle']].map(([wert, name]) => el('button', { type: 'button', 'aria-pressed': String(zustand.anzeige.ansicht === wert), text: name, onclick: () => { zustand.anzeige.ansicht = wert; speichereAnzeige(); zeichne(); } })));
	const ergebnisse = [];
	if (treffer.length === 0) {
		ergebnisse.push(el('div', { class: 'leer' }, [icon('lupe'), el('p', { text: `Kein Werkzeug passt zu „${zustand.suche}“${aktivFilter.length ? ' und den gewählten Filtern' : ''}.` }), el('button', { type: 'button', class: 'knopf', text: 'Filter zurücksetzen', onclick: () => { zustand.suche = ''; zustand.filter.recht.clear(); zustand.filter.wirkung.clear(); zeichne(); } })]));
	} else if (zustand.anzeige.ansicht === 'liste') {
		for (const [name, gruppe] of gruppiert(treffer)) {
			if (name) {
				ergebnisse.push(el('h3', { class: 'gruppe-titel', text: name + ' · ' + gruppe.length }));
			}
			ergebnisse.push(el('ul', { class: 'anw-tools' }, gruppe.map((werkzeug) => {
				const offen = zustand.offeneWerkzeuge.has(werkzeug.name);

				return el('li', { id: 'werkzeug-' + werkzeug.name }, [werkzeugKopf(werkzeug, offen, false), offen ? werkzeugDetail(werkzeug) : null]);
			})));
		}
	} else {
		const zeilen = [];
		for (const [name, gruppe] of gruppiert(treffer)) {
			if (name) {
				zeilen.push(el('tr', {}, el('th', { colspan: '5', scope: 'colgroup', class: 'gruppe-titel', text: name + ' · ' + gruppe.length })));
			}
			for (const werkzeug of gruppe) {
				const offen = zustand.offeneWerkzeuge.has(werkzeug.name);
				zeilen.push(el('tr', { class: offen ? 'offen' : null, id: 'werkzeug-' + werkzeug.name }, [
					el('td', { 'data-spalte': 'Werkzeug' }, werkzeugKopf(werkzeug, offen, true)),
					el('td', { 'data-spalte': 'Titel', text: werkzeug.title }),
					el('td', { 'data-spalte': 'Recht', text: RECHTE[werkzeug.scope] }),
					el('td', { 'data-spalte': 'Wirkung', text: wirkung(werkzeug) }),
					el('td', { class: 'zahl', 'data-spalte': 'Angaben', text: String(Object.keys(werkzeug.inputSchema.properties || {}).length) }),
				]));
				if (offen) {
					zeilen.push(el('tr', {}, el('td', { colspan: '5', class: 'anw-detailzelle' }, werkzeugDetail(werkzeug))));
				}
			}
		}
		ergebnisse.push(el('table', { class: 'anw-tabelle' }, [el('thead', {}, el('tr', {}, ['Werkzeug', 'Titel', 'Recht', 'Wirkung', 'Angaben'].map((titel) => el('th', { scope: 'col', text: titel })))), el('tbody', {}, zeilen)]));
	}

	return [
		el('p', { class: 'anw-summe' }, [`${daten.tools.length} Werkzeuge: ${zaehle('blog:read')} zum Lesen, ${zaehle('blog:write')} zum Schreiben, ${zaehle('blog:admin')} für Einstellungen. `, 'Ein Agent sieht nur die, für die sein Zugang das Recht hat.', ' ', info('Die Rechte vergibst du unter „Zugänge für Agenten“. Veröffentlichen kann kein Werkzeug — das bleibt dir vorbehalten.')]),
		el('div', { class: 'anw-leiste' }, [el('label', { class: 'anw-suche' }, [icon('lupe'), suchfeld]), panelKnopf('filter', 'Filter'), panelKnopf('anzeige', 'Anzeige'), ansicht]),
		el('div', { class: 'anw-chips' }, [
			aktivFilter.map(([art, wert]) => el('span', { class: 'anw-chip' }, [wert, el('button', { type: 'button', 'aria-label': 'Filter ' + wert + ' entfernen', text: '✕', onclick: () => { zustand.filter[art].delete(wert); zeichne(); } })])),
			el('span', { class: 'rechts', text: (zustand.suche || aktivFilter.length ? treffer.length + ' von ' + daten.tools.length + ' · ' : '') + ({ bereich: 'Gruppiert nach Bereich', recht: 'Gruppiert nach Recht', keine: 'Ohne Gruppen' })[zustand.anzeige.gruppe] }),
		]),
		...ergebnisse,
	];
}

// ---------- Reiter „Abläufe“ ----------

function block(id, titel, wann, inhalt, quelle) {
	const details = el('details', { class: 'anw-block', id: 'block-' + id, open: zustand.offeneBloecke.has(id) }, [
		el('summary', {}, [el('span', { class: 'anw-pfeil', text: '▸', 'aria-hidden': 'true' }), el('span', {}, [el('b', { text: titel }), el('small', { text: wann })]), el('span', { class: 'anw-herkunft', text: 'fest im Code' })]),
		el('div', {}, [inhalt, el('p', { class: 'anw-quelle' }, ['Quelle: ', el('code', { text: quelle }), ' — ändern nur über Code und Deploy'])]),
	]);
	details.addEventListener('toggle', () => {
		if (details.open) {
			zustand.offeneBloecke.add(id);
		} else {
			zustand.offeneBloecke.delete(id);
		}
	});

	return details;
}

function ablaeufe() {
	const textBloecke = daten.texts.map((text) => block(text.key, text.title, text.when, textBlock(text.html), text.source));
	const prompts = block(PROMPTS_ID, 'Prompts (' + daten.prompts.length + ')', 'Kurzbefehle, die einen Lauf starten, z. B. als Slash-Befehl in Claude Code', el('div', {}, daten.prompts.map((prompt) => el('div', { class: 'anw-prompt' }, [el('h4', {}, [prompt.title, el('code', { text: prompt.name })]), el('p', { class: 'anw-hinweise', text: prompt.description }), textBlock(prompt.html)]))), BLOCK_QUELLE_PROMPTS);

	return [
		el('p', { class: 'anw-lead', text: 'Diese Texte stehen im Code und ändern sich nur mit einem Deploy. Hier liest du nach, was ein Agent wann bekommt.' }),
		...textBloecke.slice(0, 1),
		prompts,
		...textBloecke.slice(1),
	];
}
