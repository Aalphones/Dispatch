// Klickdummy „Zeitversetztes Veröffentlichen“. Bausteine (el, icon, toast, dialog, rahmen, info) wie in app/theme/redaktion/ui.js.
(() => {
	'use strict';

	const JETZT = new Date(2026, 10, 12, 10, 30); // Beispielzeit Do, 12.11.2026, 10:30
	const START_MIN = 8 * 60;
	const FENSTER_MIN = 12 * 60;
	const RASTER_MIN = 15;
	const MAX_PRO_TAG = 6;
	const TAG_MS = 864e5;

	const T = {
		speicher: 'Wie Speicher das Stromnetz durch den Winter tragen',
		rad: 'Rad-Schnellwege: Warum Städte jetzt in lange Strecken investieren',
		waerme: 'Wärmepumpen im Altbau: Was Messungen nach zwei Wintern zeigen',
		satelliten: 'Tausende Satelliten, wenig Regeln: Wer räumt im Orbit auf?',
		homeoffice: 'Homeoffice verändert, wohin Menschen ziehen',
		bibliothek: 'Die Stadtbibliothek als dritter Ort: Was Besucher dort suchen',
		viertage: 'Vier-Tage-Woche: Die Zwischenbilanz nach einem Jahr',
		phishing: 'Phishing-Welle trifft kleine Betriebe: So erkennen Sie gefälschte Rechnungen',
	};
	const ALLE_TITEL = [
		'Wie eine Sonde unter das Eis des Jupitermondes Europa kommen soll',
		'Der Prüfstand für Sprachmodelle: Wie sich Antworten nachprüfen lassen',
		'Passkeys statt Passwörter: Was sich beim Anmelden ändert',
		'Alpengletscher verlieren schneller Eis, als Modelle vorhersagen',
		'KI-Assistenten im Klassenzimmer: Was Lehrkräfte berichten',
		'Die große Atlantikströmung schwächt sich ab, aber nicht überall gleich',
		T.phishing, T.viertage, T.waerme, T.satelliten, T.homeoffice, T.bibliothek,
	];

	const ICONS = {
		person: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		check: '<path d="M20 6 9 17l-5-5"/>',
		stift: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		herz: '<path d="M19.5 12.6 12 20l-7.5-7.4A5 5 0 1 1 12 6a5 5 0 1 1 7.5 6.6z"/>',
		chart: '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
		schluessel: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/>',
		warn: '<path d="M12 3 2 21h20zM12 10v5M12 18h.01"/>',
		auge: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		uhr: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		tuer: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
	};

	// ---------- Bausteine (wie ui.js) ----------

	function el(tag, attributes = {}, children = []) {
		const node = document.createElement(tag);
		for (const [name, value] of Object.entries(attributes)) {
			if (name === 'style' || value === false || value === null || value === undefined) {
				continue;
			}
			if (name.startsWith('on')) {
				node.addEventListener(name.slice(2), value);
			} else if (name === 'text') {
				node.textContent = value;
			} else {
				node.setAttribute(name, value === true ? '' : value);
			}
		}
		for (const child of [children].flat(Infinity)) {
			if (child !== false && child !== null && child !== undefined) {
				node.append(child instanceof Node ? child : String(child));
			}
		}

		return node;
	}

	function icon(name) {
		const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('class', 'ico');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('aria-hidden', 'true');
		svg.innerHTML = ICONS[name] || '';

		return svg;
	}

	function info(text) {
		return el('span', { class: 'info', tabindex: '0', role: 'note', 'aria-label': text, 'data-tipp': text }, 'i');
	}

	let toastBox = null;
	let toastTimer = 0;
	function toast(text, undo) {
		if (toastBox === null) {
			toastBox = el('div', { class: 'toast', id: 'toast', role: 'status' }, [el('span', { id: 'toast-text' }), el('button', { type: 'button', id: 'toast-zurueck', hidden: true, text: 'Rückgängig' })]);
			document.body.append(toastBox);
		}
		const button = toastBox.querySelector('#toast-zurueck');
		toastBox.querySelector('#toast-text').textContent = text;
		button.hidden = typeof undo !== 'function';
		button.onclick = () => {
			toastBox.classList.remove('zeigen');
			undo();
		};
		toastBox.classList.add('zeigen');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(() => toastBox.classList.remove('zeigen'), button.hidden ? 3800 : 7000);
	}

	let rendering = false;
	function dialog(id, content, className = '') {
		let box = document.getElementById(id);
		if (box === null) {
			box = el('dialog', { id });
			box.addEventListener('click', (event) => {
				if (event.target.closest('[data-close]')) {
					box.close();
				}
			});
			box.addEventListener('close', () => {
				if (!rendering) {
					nachDialog(id);
				}
			});
			document.body.append(box);
		}
		box.className = className;
		box.replaceChildren(...[content].flat());
		const heading = box.querySelector('h2');
		if (heading) {
			heading.id = id + '-titel';
			box.setAttribute('aria-labelledby', heading.id);
		}
		if (!box.open) {
			box.showModal();
		}

		return box;
	}

	function rahmen(title, body, footer) {
		return [
			el('div', { class: 'dlg-kopf' }, [
				el('h2', { tabindex: '-1', text: title }),
				el('button', { type: 'button', class: 'zu', 'data-close': true, 'aria-label': 'Schließen', text: '✕' }),
			]),
			el('div', { class: 'dlg-rumpf' }, body),
			el('div', { class: 'dlg-fuss' }, footer),
		];
	}

	// ---------- Zeit ----------

	function tagesbeginn(datum) {
		const x = new Date(datum);
		x.setHours(0, 0, 0, 0);

		return x;
	}

	function plusTage(datum, tage) {
		const x = new Date(datum);
		x.setDate(x.getDate() + tage);

		return x;
	}

	function umMinute(tag, minuten) {
		const x = new Date(tag);
		x.setHours(0, minuten, 0, 0);

		return x;
	}

	function tagUm(abstand, stunde, minute) {
		return umMinute(plusTage(tagesbeginn(JETZT), abstand), stunde * 60 + minute);
	}

	function tagAbstand(datum) {
		return Math.round((tagesbeginn(datum) - tagesbeginn(JETZT)) / TAG_MS);
	}

	function uhr(datum) {
		return datum.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
	}

	function tagKurz(datum) {
		const abstand = tagAbstand(datum);
		if (abstand === 0) {
			return 'heute';
		}
		if (abstand === 1) {
			return 'morgen';
		}

		return datum.toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });
	}

	function wann(datum) {
		return tagKurz(datum) + ', ' + uhr(datum);
	}

	function tagTitel(datum) {
		const lang = datum.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long' });
		const abstand = tagAbstand(datum);

		return abstand === 0 ? 'Heute · ' + lang : abstand === 1 ? 'Morgen · ' + lang : lang;
	}

	const zwei = (zahl) => String(zahl).padStart(2, '0');

	function alsEingabe(datum) {
		return datum.getFullYear() + '-' + zwei(datum.getMonth() + 1) + '-' + zwei(datum.getDate()) + 'T' + zwei(datum.getHours()) + ':' + zwei(datum.getMinutes());
	}

	function ausEingabe(text) {
		const treffer = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(text);

		return treffer === null ? null : new Date(+treffer[1], +treffer[2] - 1, +treffer[3], +treffer[4], +treffer[5]);
	}

	/** Erscheinungszeiten der Warteschlange: n Termine gleichmäßig ab 08:00 über zwölf Stunden, auf Viertelstunden gerundet. */
	function zeitenFuer(n) {
		return Array.from({ length: n }, (_, k) => START_MIN + Math.round((k * FENSTER_MIN) / n / RASTER_MIN) * RASTER_MIN);
	}

	function zeitenText(n) {
		const zeiten = zeitenFuer(n).map((m) => zwei(Math.floor(m / 60)) + ':' + zwei(m % 60));

		return zeiten.length === 1 ? 'um ' + zeiten[0] : 'um ' + zeiten.slice(0, -1).join(', ') + ' und ' + zeiten.at(-1);
	}

	const anzahl = (n, eins, viele) => n + ' ' + (n === 1 ? eins : viele);

	// ---------- Zustand ----------

	const state = { ansicht: 'start', daten: 'beispiel', fassung: 'neu', n: 2, queue: [], fest: [], erschienen: [], review: [], offeneZeile: null, ausgewaehlt: null, neu: null };

	function datenSetzen(name) {
		state.daten = name;
		state.offeneZeile = null;
		state.neu = null;
		state.queue = [];
		state.fest = [];
		state.erschienen = [];
		state.review = [{ id: 'speicher', titel: T.speicher, woerter: 1240, quellen: 6 }];
		if (name === 'leer') {
			return;
		}
		state.erschienen = [{ id: 'rad', titel: T.rad, zeit: tagUm(0, 8, 0), art: 'warteschlange' }];
		if (name === 'viele') {
			state.queue = Array.from({ length: 30 }, (_, i) => ({
				id: 'v' + i,
				titel: ALLE_TITEL[i % ALLE_TITEL.length] + (i >= ALLE_TITEL.length ? ' (Folge ' + (Math.floor(i / ALLE_TITEL.length) + 1) + ')' : ''),
			}));
			state.fest = [{ id: 'viertage-f', titel: T.viertage, termin: tagUm(1, 11, 30) }];

			return;
		}
		state.queue = [
			{ id: 'waerme', titel: T.waerme },
			{ id: 'satelliten', titel: T.satelliten },
			{ id: 'homeoffice', titel: T.homeoffice },
			{ id: 'bibliothek', titel: T.bibliothek },
		];
		state.fest = [
			{ id: 'viertage', titel: T.viertage, termin: tagUm(1, 11, 30) },
			{ id: 'phishing', titel: T.phishing, termin: tagUm(5, 9, 0) },
		];
	}

	function schnappschuss() {
		return structuredClone({ queue: state.queue, fest: state.fest, erschienen: state.erschienen, review: state.review });
	}

	function zurueck(stand) {
		return () => {
			Object.assign(state, structuredClone(stand));
			render();
		};
	}

	/**
	 * Rechnet den Plan wie der Server: Die Warteschlange belegt die heutigen Termine ab dem ersten noch nicht
	 * verbrauchten (heute schon aus der Warteschlange erschienen = verbraucht), danach Tag für Tag. Feste Termine
	 * kommen zusätzlich. `frei` ist der Termin, den die nächste Freigabe bekäme.
	 */
	function planBerechnen() {
		const zeiten = zeitenFuer(state.n);
		const heuteVerbraucht = state.erschienen.filter((e) => e.art === 'warteschlange' && tagAbstand(e.zeit) === 0).length;
		const zeilen = state.erschienen.filter((e) => tagAbstand(e.zeit) === 0).map((e) => ({ ...e, typ: 'erschienen' }));
		let tag = 0;
		let k = heuteVerbraucht;
		let i = 0;
		let frei = null;
		while (frei === null) {
			if (k >= zeiten.length) {
				tag += 1;
				k = 0;
				continue;
			}
			const zeit = umMinute(plusTage(tagesbeginn(JETZT), tag), zeiten[k]);
			k += 1;
			if (zeit <= JETZT) {
				continue;
			}
			if (i < state.queue.length) {
				zeilen.push({ ...state.queue[i], zeit, typ: 'warteschlange', platz: i + 1 });
				i += 1;
			} else {
				frei = zeit;
			}
		}
		for (const eintrag of state.fest) {
			zeilen.push({ ...eintrag, zeit: eintrag.termin, typ: 'fest' });
		}
		zeilen.sort((a, b) => a.zeit - b.zeit);

		return { zeilen, frei, geplant: state.queue.length + state.fest.length };
	}

	function herausnehmen(id) {
		const eintrag = state.queue.find((q) => q.id === id) || state.fest.find((f) => f.id === id);
		state.queue = state.queue.filter((q) => q.id !== id);
		state.fest = state.fest.filter((f) => f.id !== id);

		return eintrag;
	}

	// ---------- Aktionen ----------

	function freigeben(artikel, wahl) {
		const stand = schnappschuss();
		state.review = state.review.filter((r) => r.id !== artikel.id);
		state.neu = artikel.id;
		if (wahl.art === 'sofort') {
			state.erschienen.push({ id: artikel.id, titel: artikel.titel, zeit: new Date(JETZT), art: 'sofort' });
			toast('Freigegeben und veröffentlicht. Die Website wird neu gebaut.');
		} else if (wahl.art === 'fest') {
			state.fest.push({ id: artikel.id, titel: artikel.titel, termin: wahl.termin });
			toast('Freigegeben. Erscheint ' + wann(wahl.termin) + '.', zurueck(stand));
		} else {
			state.queue.push({ id: artikel.id, titel: artikel.titel });
			const zeile = planBerechnen().zeilen.find((z) => z.id === artikel.id);
			toast('Freigegeben. Erscheint voraussichtlich ' + wann(zeile.zeit) + '.', zurueck(stand));
		}
		zeige('start');
	}

	function aendern(zeile, wahl) {
		const stand = schnappschuss();
		const eintrag = herausnehmen(zeile.id);
		state.offeneZeile = null;
		state.neu = zeile.id;
		if (wahl.art === 'sofort') {
			state.erschienen.push({ id: eintrag.id, titel: eintrag.titel, zeit: new Date(JETZT), art: 'sofort' });
			toast('Veröffentlicht. Die Website wird neu gebaut.');
		} else if (wahl.art === 'fest') {
			state.fest.push({ id: eintrag.id, titel: eintrag.titel, termin: wahl.termin });
			toast('Neuer Termin: ' + wann(wahl.termin) + '.', zurueck(stand));
		} else {
			state.queue.push({ id: eintrag.id, titel: eintrag.titel });
			const neu = planBerechnen().zeilen.find((z) => z.id === eintrag.id);
			toast('Zurück in der Warteschlange. Erscheint voraussichtlich ' + wann(neu.zeit) + '.', zurueck(stand));
		}
		render();
	}

	function ausDemPlan(id) {
		const stand = schnappschuss();
		const eintrag = herausnehmen(id);
		state.review.push({ id: eintrag.id, titel: eintrag.titel, woerter: 1100, quellen: 5 });
		state.offeneZeile = null;
		toast('Aus dem Plan genommen. Der Artikel wartet wieder auf Freigabe.', zurueck(stand));
		if (state.ansicht === 'geplant') {
			zeige('start');
		} else {
			render();
		}
	}

	// ---------- Zeitpunkt wählen (Freigeben, Termin ändern) ----------

	/**
	 * Drei Wahlen: Warteschlange, fester Termin, sofort. `modus` ist 'freigeben' oder 'aendern'; `knopf` ist der
	 * Hauptknopf, dessen Beschriftung der Wahl folgt. Liefert die Knoten und `lesen()` (null bei ungültiger Eingabe).
	 */
	function zeitpunktWahl(zeile, modus, knopf) {
		const name = 'zp-' + Math.random().toString(36).slice(2, 8);
		const plan = planBerechnen();
		const bleibt = modus === 'aendern' && zeile.typ === 'warteschlange';
		const davor = state.queue.filter((q) => q.id !== zeile.id).length;
		const queueText = bleibt
			? ['Platz ' + zeile.platz + ' · ', el('b', { text: wann(zeile.zeit) })]
			: ['Voraussichtlich ', el('b', { text: wann(plan.frei) }), ' · ' + (davor === 0 ? 'nichts davor' : anzahl(davor, 'Artikel', 'Artikel') + ' davor')];
		const vorschlag = zeile.typ === 'fest' ? zeile.zeit : tagUm(1, 18, 0);
		const eingabe = el('input', { type: 'datetime-local', id: name + '-termin', value: alsEingabe(vorschlag), min: alsEingabe(JETZT), step: '300', 'aria-describedby': name + '-fehler' });
		const fehler = el('p', { class: 'feldfehler', id: name + '-fehler', role: 'alert', hidden: true });
		const terminFeld = el('div', { class: 'termin-feld', hidden: true }, [el('label', { for: name + '-termin', text: 'Datum und Uhrzeit' }), eingabe, fehler]);
		const optionen = [
			{
				wert: 'warteschlange',
				titel: bleibt ? 'In der Warteschlange lassen' : 'Nächster freier Platz',
				text: queueText,
				tipp: 'Die Warteschlange veröffentlicht höchstens ' + state.n + ' Artikel am Tag, ' + zeitenText(state.n) + ' Uhr, in der Reihenfolge der Freigabe. Einstellen im Veröffentlichungsplan.',
			},
			{ wert: 'fest', titel: 'Fester Termin', text: ['Erscheint genau dann, zusätzlich zum Tageslimit'], tipp: 'Ein fester Termin belegt keinen Platz der Warteschlange.' },
			{ wert: 'sofort', titel: 'Sofort', text: [modus === 'freigeben' ? 'Erscheint jetzt; die Website wird neu gebaut' : 'Erscheint jetzt statt zum Termin'] },
		];
		const start = modus === 'freigeben' ? 'warteschlange' : zeile.typ;
		const radios = [];
		const wahlen = optionen.map((option) => {
			const radio = el('input', { type: 'radio', name, value: option.wert, checked: option.wert === start });
			radios.push(radio);

			return el('label', { class: 'wahl' }, [radio, el('b', { text: option.titel }), option.tipp ? info(option.tipp) : false, el('small', {}, option.text)]);
		});
		const fieldset = el('fieldset', { class: 'rechtewahl zeitpunkt' }, [el('legend', { text: 'Wann erscheint der Artikel?' }), wahlen[0], wahlen[1], terminFeld, wahlen[2]]);

		const gewaehlt = () => radios.find((r) => r.checked).value;
		const beschriften = () => {
			const art = gewaehlt();
			terminFeld.hidden = art !== 'fest';
			const termin = ausEingabe(eingabe.value);
			let text;
			if (modus === 'freigeben') {
				text = art === 'sofort' ? 'Freigeben und veröffentlichen' : art === 'fest' ? 'Freigeben für ' + (termin ? wann(termin) : '…') : 'Freigeben und einplanen';
			} else {
				text = art === 'sofort' ? 'Jetzt veröffentlichen' : art === 'fest' ? 'Termin speichern' : bleibt ? 'Keine Änderung' : 'In die Warteschlange';
			}
			knopf.replaceChildren(icon(art === 'sofort' ? 'check' : 'uhr'), text);
			knopf.disabled = modus === 'aendern' && art === 'warteschlange' && bleibt;
		};
		for (const radio of radios) {
			radio.addEventListener('change', () => {
				beschriften();
				if (gewaehlt() === 'fest') {
					eingabe.focus();
				}
			});
		}
		eingabe.addEventListener('input', () => {
			fehler.hidden = true;
			eingabe.removeAttribute('aria-invalid');
			beschriften();
		});
		beschriften();

		const lesen = () => {
			const art = gewaehlt();
			if (art !== 'fest') {
				return { art };
			}
			const termin = ausEingabe(eingabe.value);
			const meldung = termin === null ? 'Bitte Datum und Uhrzeit wählen.' : termin <= JETZT ? 'Der Termin liegt in der Vergangenheit. Wähle eine Zeit nach ' + wann(JETZT) + '.' : '';
			if (meldung !== '') {
				fehler.textContent = meldung;
				fehler.hidden = false;
				eingabe.setAttribute('aria-invalid', 'true');
				eingabe.focus();

				return null;
			}

			return { art, termin };
		};

		return { knoten: [fieldset], lesen };
	}

	function besuchHinweis() {
		return el('p', { class: 'besuch-hinweis' }, [icon('uhr'), 'Ein geplanter Artikel erscheint mit dem ersten Seitenaufruf ab seinem Termin – auch deinem eigenen. Kommt niemand vorbei, wartet er.']);
	}

	// ---------- Dialoge ----------

	function freigebenDialog(artikel) {
		const knopf = el('button', { type: 'button', class: 'knopf primaer' });
		const wahl = zeitpunktWahl({ id: artikel.id, typ: 'neu' }, 'freigeben', knopf);
		const box = dialog('dlg-freigeben', rahmen('Freigeben', [el('p', { class: 'worum', text: '„' + artikel.titel + '“' }), wahl.knoten, besuchHinweis()], [
			el('button', { type: 'button', class: 'knopf leise', 'data-close': true, text: 'Abbrechen' }),
			knopf,
		]));
		knopf.addEventListener('click', () => {
			const ergebnis = wahl.lesen();
			if (ergebnis !== null) {
				box.close();
				freigeben(artikel, ergebnis);
			}
		});
	}

	function terminDialog(zeile) {
		const knopf = el('button', { type: 'button', class: 'knopf primaer' });
		const wahl = zeitpunktWahl(zeile, 'aendern', knopf);
		const box = dialog('dlg-termin', rahmen('Termin ändern', [el('p', { class: 'worum', text: '„' + zeile.titel + '“' }), wahl.knoten], [
			el('button', { type: 'button', class: 'knopf leise', 'data-close': true, text: 'Abbrechen' }),
			knopf,
		]));
		knopf.addEventListener('click', () => {
			const ergebnis = wahl.lesen();
			if (ergebnis !== null) {
				box.close();
				aendern(zeile, ergebnis);
			}
		});
	}

	function planDialog() {
		const rumpf = el('div');
		dialog('dlg-plan', rahmen('Veröffentlichungsplan', rumpf, [el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' })]), 'breit');
		planFuellen(rumpf);
	}

	function einstellung(rumpf) {
		const gruppe = el('div', { class: 'gewicht hell', role: 'group', 'aria-label': 'Artikel pro Tag aus der Warteschlange' });
		for (let n = 1; n <= MAX_PRO_TAG; n += 1) {
			gruppe.append(el('button', {
				type: 'button',
				'aria-pressed': String(n === state.n),
				'aria-label': anzahl(n, 'Artikel', 'Artikel') + ' pro Tag',
				text: String(n),
				onclick: () => {
					if (n === state.n) {
						return;
					}
					const alt = state.n;
					state.n = n;
					planFuellen(rumpf, true);
					toast('Gespeichert: ' + n + ' am Tag, ' + zeitenText(n) + ' Uhr. Die Termine der Warteschlange sind neu berechnet.', () => {
						state.n = alt;
						planFuellen(rumpf, true);
					});
				},
			}));
		}

		return el('div', { class: 'plan-einstellung' }, [
			el('b', { text: 'Warteschlange: Artikel pro Tag' }),
			gruppe,
			el('span', { class: 'plan-zeiten', text: zeitenText(state.n) + ' Uhr' }),
			info('Freigegebene Artikel erscheinen nacheinander zu diesen Zeiten, gleichmäßig verteilt ab 8 Uhr. Artikel mit festem Termin kommen zusätzlich. Änderst du die Zahl, verschieben sich die Termine der Warteschlange sofort.'),
		]);
	}

	function planFuellen(rumpf, fokusAufZahl = false) {
		const geladen = state.daten !== 'laedt' && state.daten !== 'fehler';
		const teile = geladen ? [einstellung(rumpf)] : [];
		if (state.daten === 'laedt') {
			teile.push(el('div', { 'aria-busy': 'true', 'aria-label': 'Plan wird geladen' }, [el('div', { class: 'lader kurz' }), el('div', { class: 'lader' }), el('div', { class: 'lader' }), el('div', { class: 'lader kurz' })]));
		} else if (state.daten === 'fehler') {
			teile.push(
				el('p', { class: 'plan-fehler', role: 'alert' }, [icon('warn'), 'Der Plan ließ sich nicht laden: keine Verbindung zum Server. Geplante Artikel erscheinen trotzdem zu ihrem Termin.']),
				el('button', { type: 'button', class: 'knopf klein-k', text: 'Erneut versuchen', onclick: () => { datenWaehlen('beispiel'); } }),
			);
		} else {
			const plan = planBerechnen();
			if (plan.geplant === 0) {
				teile.push(el('div', { class: 'leer' }, [icon('uhr'), el('p', { text: 'Nichts geplant.' }), el('p', { text: 'Was du freigibst, landet hier und erscheint ' + zeitenText(state.n) + ' Uhr – ein Artikel je Termin.' })]));
			} else {
				teile.push(el('p', { class: 'plan-summe' }, [anzahl(plan.geplant, 'Artikel', 'Artikel') + ' geplant · nächster freier Platz: ' + wann(plan.frei)]));
				teile.push(...tagesGruppen(plan));
			}
			teile.push(besuchHinweis());
		}
		rumpf.replaceChildren(...teile);
		if (fokusAufZahl) {
			rumpf.querySelector('.gewicht [aria-pressed="true"]')?.focus();
		}
	}

	function tagesGruppen(plan) {
		const gruppen = new Map();
		const dazu = (zeile) => {
			const schluessel = tagesbeginn(zeile.zeit).getTime();
			if (!gruppen.has(schluessel)) {
				gruppen.set(schluessel, []);
			}
			gruppen.get(schluessel).push(zeile);
		};
		plan.zeilen.forEach(dazu);
		dazu({ id: 'frei', typ: 'frei', zeit: plan.frei });
		const teile = [];
		for (const [schluessel, zeilen] of [...gruppen.entries()].sort((a, b) => a[0] - b[0])) {
			zeilen.sort((a, b) => a.zeit - b.zeit);
			teile.push(el('h3', { class: 'gruppe-titel', text: tagTitel(new Date(schluessel)) }), el('ul', { class: 'plan-liste' }, zeilen.map((z) => planZeile(z))));
		}

		return teile;
	}

	// ---------- Zeilen ----------

	function artMarke(zeile) {
		if (zeile.typ === 'fest') {
			return el('span', { class: 'art fest' }, [icon('uhr'), 'Fester Termin']);
		}
		if (zeile.typ === 'warteschlange') {
			return el('span', { class: 'art', text: 'Warteschlange · Platz ' + zeile.platz });
		}

		return el('span', { class: 'punkt', text: zeile.art === 'sofort' ? 'sofort erschienen' : 'erschienen' });
	}

	function planZeile(zeile, kompakt = false) {
		if (zeile.typ === 'frei') {
			return el('li', { class: 'frei' }, [el('span', { class: 'plan-zeit', text: uhr(zeile.zeit) }), el('div', { class: 'plan-titel', text: 'Nächster freier Platz' })]);
		}
		const istErschienen = zeile.typ === 'erschienen';
		const li = el('li', { class: [istErschienen ? 'erschienen' : '', zeile.id === state.neu ? 'neu' : ''].join(' ').trim() || false });
		const link = istErschienen
			? el('a', { href: '#start', text: zeile.titel })
			: el('a', { href: '#geplant', text: zeile.titel, onclick: (event) => { event.preventDefault(); state.ausgewaehlt = zeile.id; zeige('geplant'); } });
		li.append(
			el('span', { class: 'plan-zeit', text: kompakt ? wann(zeile.zeit) : uhr(zeile.zeit) }),
			el('div', { class: 'plan-titel' }, [link, el('div', { class: 'unter' }, artMarke(zeile))]),
		);
		if (kompakt || istErschienen) {
			return li;
		}
		const offen = state.offeneZeile === zeile.id;
		li.append(el('div', { class: 'plan-aktionen' }, [
			el('button', {
				type: 'button',
				class: 'knopf leise klein-k',
				'aria-expanded': String(offen),
				onclick: () => {
					state.offeneZeile = offen ? null : zeile.id;
					render();
				},
			}, [icon('stift'), 'Ändern']),
			el('button', { type: 'button', class: 'weg', 'aria-label': 'Aus dem Plan nehmen: ' + zeile.titel, title: 'Aus dem Plan nehmen – der Artikel wartet wieder auf Freigabe', text: '✕', onclick: () => ausDemPlan(zeile.id) }),
		]));
		if (offen) {
			const knopf = el('button', { type: 'button', class: 'knopf primaer klein-k' });
			const wahl = zeitpunktWahl(zeile, 'aendern', knopf);
			knopf.addEventListener('click', () => {
				const ergebnis = wahl.lesen();
				if (ergebnis !== null) {
					aendern(zeile, ergebnis);
				}
			});
			li.append(el('div', { class: 'bearbeiten' }, el('div', {}, [
				wahl.knoten,
				el('div', { class: 'knopfzeile' }, [
					el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Abbrechen', onclick: () => { state.offeneZeile = null; render(); } }),
					knopf,
				]),
			])));
		}

		return li;
	}

	// ---------- Seiten ----------

	function wartekarte(artikel) {
		return el('article', { class: 'wartekarte' }, [
			el('span', { class: 'status review', text: 'Zur Freigabe' }),
			el('h3', { text: artikel.titel }),
			el('span', { class: 'meta', text: 'Eingereicht heute 09:12 · ' + artikel.woerter.toLocaleString('de-DE') + ' Wörter · ' + artikel.quellen + ' Quellen · Formprüfung ok' }),
			el('div', { class: 'zeile' }, el('a', { class: 'knopf primaer klein-k', href: '#vorschau', onclick: (event) => { event.preventDefault(); zeige('vorschau'); } }, [icon('auge'), 'Ansehen und freigeben'])),
		]);
	}

	function startseite(main) {
		const wrap = el('div', { class: 'wrap' });
		if (state.review.length > 0) {
			wrap.append(el('section', { class: 'wartet', 'aria-labelledby': 'wartet-titel' }, [
				el('div', { class: 'wartet-kopf' }, [el('h2', { id: 'wartet-titel', text: 'Wartet auf dich' }), el('span', { text: anzahl(state.review.length, 'Entwurf', 'Entwürfe') })]),
				el('div', { class: 'wartet-reihe' }, state.review.map(wartekarte)),
			]));
		}
		const plan = planBerechnen();
		const kommend = plan.zeilen.filter((z) => z.typ !== 'erschienen');
		if (kommend.length > 0 && state.daten !== 'laedt' && state.daten !== 'fehler') {
			const zeigen = kommend.slice(0, 3);
			const rest = kommend.length - zeigen.length;
			wrap.append(el('section', { class: 'geplant', 'aria-labelledby': 'geplant-titel' }, [
				el('div', { class: 'geplant-kopf' }, [
					el('h2', { id: 'geplant-titel', text: 'Geplant' }),
					el('span', { text: anzahl(kommend.length, 'Artikel', 'Artikel') + ' · Warteschlange: ' + state.n + ' am Tag' }),
					el('button', { type: 'button', class: 'knopf klein-k', onclick: () => zeige('plan') }, [icon('uhr'), 'Plan öffnen']),
				]),
				el('ul', { class: 'plan-liste kompakt' }, zeigen.map((z) => planZeile(z, true))),
				rest > 0 ? el('p', { class: 'plan-summe' }, el('button', { type: 'button', class: 'knopf leise klein-k', text: 'und ' + anzahl(rest, 'weiterer', 'weitere') + ' im Plan', onclick: () => zeige('plan') })) : false,
			]));
		}
		wrap.append(el('div', { class: 'dummy-unveraendert', text: 'Aufmacher und Artikelraster – unverändert' }));
		main.append(wrap);
	}

	function artikelflaeche(titel, band) {
		return [
			el('p', { class: 'vorschau-band', text: band }),
			el('article', { class: 'wrap dummy-artikel' }, [
				el('p', { class: 'klein', text: 'Energie' }),
				el('h1', { text: titel }),
				el('p', { class: 'beispieltext', text: 'Beispieltext. Hier steht der Artikel so, wie ihn die Vorschau heute zeigt – dieser Bereich ändert sich nicht.' }),
			]),
		];
	}

	function leiste(inhalt) {
		const bar = el('div', { class: 'freigabe' }, el('div', { class: 'wrap' }, inhalt));
		if ('ResizeObserver' in window) {
			new ResizeObserver(() => document.documentElement.style.setProperty('--freigabe-h', bar.offsetHeight + 'px')).observe(bar);
		}
		document.body.classList.add('mit-freigabe');

		return bar;
	}

	const nichtBelegt = () => toast('Wie heute – im Entwurf nicht belegt.');

	function vorschauReview(main) {
		const artikel = state.review[0];
		if (artikel === undefined) {
			main.append(el('div', { class: 'wrap' }, el('div', { class: 'leer' }, [icon('check'), el('p', { text: 'Nichts wartet auf Freigabe.' }), el('button', { type: 'button', class: 'knopf klein-k', text: 'Beispiel zurücksetzen', onclick: () => datenWaehlen('beispiel', 'vorschau') })])));

			return;
		}
		const istFassung = state.fassung === 'fassung';
		const freigabeKnopf = el('button', { type: 'button', class: 'knopf primaer' }, [icon('check'), istFassung ? 'Neue Fassung freigeben' : 'Freigeben …']);
		freigabeKnopf.addEventListener('click', () => {
			if (istFassung) {
				toast('Freigegeben. Die neue Fassung ist online, die Website wird neu gebaut.');
			} else {
				zeige('freigeben');
			}
		});
		main.append(
			leiste([
				el('div', { class: 'pruefung', 'aria-label': 'Formprüfung' }, [
					el('span', { class: 'pruef ok' }, [icon('check'), artikel.quellen + ' Quellen']),
					el('span', { class: 'pruef ok' }, [icon('check'), artikel.woerter.toLocaleString('de-DE') + ' Wörter']),
				]),
				el('div', { class: 'freigabe-knoepfe' }, [
					el('button', { type: 'button', class: 'knopf klein-k', onclick: nichtBelegt }, [icon('stift'), 'Kommentieren']),
					istFassung ? el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Änderungen zeigen', onclick: nichtBelegt }) : false,
					el('button', { type: 'button', class: 'knopf klein-k', text: 'Zurück an Agent', onclick: nichtBelegt }),
					istFassung ? false : el('button', { type: 'button', class: 'knopf leise gefahr klein-k', text: 'Verwerfen', onclick: nichtBelegt }),
					freigabeKnopf,
				]),
			]),
			...artikelflaeche(artikel.titel, istFassung ? 'Vorschau · neue Fassung, noch nicht online' : 'Vorschau · nicht veröffentlicht'),
		);
	}

	function vorschauGeplant(main) {
		const kommend = planBerechnen().zeilen.filter((z) => z.typ === 'warteschlange' || z.typ === 'fest');
		const zeile = kommend.find((z) => z.id === state.ausgewaehlt) || kommend[0];
		if (zeile === undefined) {
			main.append(el('div', { class: 'wrap' }, el('div', { class: 'leer' }, [icon('uhr'), el('p', { text: 'Nichts geplant.' }), el('button', { type: 'button', class: 'knopf klein-k', text: 'Beispiel zurücksetzen', onclick: () => datenWaehlen('beispiel', 'geplant') })])));

			return;
		}
		state.ausgewaehlt = zeile.id;
		main.append(
			leiste([
				el('span', { class: 'geplant-status' }, [icon('uhr'), 'Geplant für ' + wann(zeile.zeit), artMarke(zeile)]),
				el('div', { class: 'freigabe-knoepfe' }, [
					el('button', { type: 'button', class: 'knopf klein-k', onclick: () => terminDialog(zeile) }, [icon('stift'), 'Termin ändern']),
					el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Aus dem Plan nehmen', onclick: () => ausDemPlan(zeile.id) }),
					el('button', { type: 'button', class: 'knopf primaer', onclick: () => aendern(zeile, { art: 'sofort' }) }, [icon('check'), 'Jetzt veröffentlichen']),
				]),
			]),
			...artikelflaeche(zeile.titel, 'Vorschau · geplant, noch nicht veröffentlicht'),
		);
	}

	// ---------- Kopf und Menü ----------

	let menue = null;
	let kontoKnopf = null;

	function kopfBauen() {
		menue = el('div', { id: 'menue', popover: 'auto', class: 'menue' });
		kontoKnopf = el('button', { type: 'button', class: 'konto', popovertarget: 'menue', 'aria-label': 'Redaktionsmenü' });
		document.getElementById('kopf-rechts').append(kontoKnopf);
		document.body.append(menue);
		menue.addEventListener('beforetoggle', (event) => {
			if (event.newState === 'open') {
				const box = kontoKnopf.getBoundingClientRect();
				menue.style.top = box.bottom + 8 + 'px';
				menue.style.right = Math.max(8, window.innerWidth - box.right) + 'px';
			}
		});
		menue.addEventListener('toggle', (event) => {
			if (event.newState === 'closed' && !rendering && state.ansicht === 'menue') {
				state.ansicht = 'start';
				syncSteuerung();
			}
		});
	}

	function kopfFuellen() {
		const offen = state.review.length;
		kontoKnopf.replaceChildren(...[el('span', { class: 'avatar' }, icon('person')), el('span', { class: 'text', text: 'Redaktion' }), offen > 0 ? el('span', { class: 'zaehler', text: String(offen) }) : false].filter(Boolean));
		kontoKnopf.setAttribute('aria-label', offen > 0 ? 'Redaktionsmenü, ' + offen + ' offen' : 'Redaktionsmenü');
		const eintrag = (symbol, text, aktion, zahl = 0) => el('button', { type: 'button', onclick: () => { menue.hidePopover(); aktion(); } }, [icon(symbol), text, zahl > 0 ? el('span', { class: 'zaehler', text: String(zahl) }) : false]);
		menue.replaceChildren(
			el('div', { class: 'kopfzeile', text: 'Angemeldet als Herausgeber' }),
			eintrag('check', 'Freigaben', () => zeige('start'), offen),
			eintrag('stift', 'Vorschläge', () => zeige('start')),
			eintrag('uhr', 'Veröffentlichungsplan', () => zeige('plan')),
			eintrag('plus', 'Thema einreichen', nichtBelegt),
			el('hr'),
			eintrag('herz', 'Interessen', nichtBelegt),
			eintrag('chart', 'Statistik', nichtBelegt),
			eintrag('schluessel', 'Zugänge für Agenten', nichtBelegt),
			el('hr'),
			eintrag('tuer', 'Abmelden', nichtBelegt),
		);
	}

	// ---------- Steuerung ----------

	const WOFUER = {
		start: 'Sehen, was auf Freigabe wartet und was als Nächstes erscheint. Neu: der Abschnitt „Geplant“ unter „Wartet auf dich“.',
		menue: 'Den Veröffentlichungsplan aus dem Redaktionsmenü öffnen. Neu: der Eintrag „Veröffentlichungsplan“.',
		vorschau: 'Artikel prüfen und freigeben. Neu: „Freigeben …“ fragt, wann er erscheint; die neue Fassung eines Live-Artikels geht weiter sofort online (Auswahl „Artikel“ oben).',
		freigeben: 'Den Zeitpunkt wählen – meist einfach den nächsten freien Platz der Warteschlange.',
		geplant: 'Einen eingeplanten Artikel ansehen, seinen Termin ändern, ihn zurücknehmen oder sofort veröffentlichen.',
		plan: 'Überblick, was wann erscheint; Tageslimit der Warteschlange einstellen; Termine ändern oder Artikel zurücknehmen.',
	};

	function syncSteuerung() {
		document.getElementById('d-ansicht').value = state.ansicht;
		document.getElementById('d-daten').value = state.daten;
		document.getElementById('d-fassung').value = state.fassung;
		document.getElementById('d-wofuer').textContent = WOFUER[state.ansicht];
		if (location.hash !== '#' + state.ansicht) {
			history.replaceState(null, '', '#' + state.ansicht);
		}
	}

	function nachDialog(id) {
		if (id === 'dlg-freigeben' && state.ansicht === 'freigeben') {
			state.ansicht = 'vorschau';
			syncSteuerung();
		}
		if (id === 'dlg-plan' && state.ansicht === 'plan') {
			state.ansicht = 'start';
			state.offeneZeile = null;
			render();
		}
	}

	function render() {
		rendering = true;
		for (const box of document.querySelectorAll('dialog[open]')) {
			if (!(state.ansicht === 'plan' && box.id === 'dlg-plan')) {
				box.close();
			}
		}
		if (menue.matches(':popover-open') && state.ansicht !== 'menue') {
			menue.hidePopover();
		}
		document.body.classList.remove('mit-freigabe');
		const main = document.getElementById('inhalt');
		main.replaceChildren();
		kopfFuellen();
		syncSteuerung();
		if (state.ansicht === 'vorschau' || state.ansicht === 'freigeben') {
			vorschauReview(main);
			if (state.ansicht === 'freigeben' && state.review[0]) {
				freigebenDialog(state.review[0]);
			}
		} else if (state.ansicht === 'geplant') {
			vorschauGeplant(main);
		} else {
			startseite(main);
			if (state.ansicht === 'plan') {
				planDialog();
			}
			if (state.ansicht === 'menue' && !menue.matches(':popover-open')) {
				menue.showPopover();
			}
		}
		rendering = false;
	}

	function zeige(ansicht) {
		state.ansicht = WOFUER[ansicht] ? ansicht : 'start';
		if (state.ansicht !== 'plan') {
			state.offeneZeile = null;
		}
		render();
		if (state.neu !== null) {
			setTimeout(() => { state.neu = null; }, 1700);
		}
	}

	function datenWaehlen(name, ansicht = state.ansicht) {
		datenSetzen(name);
		zeige(ansicht);
	}

	function thema(wert) {
		document.documentElement.classList.toggle('hell', wert === 'hell');
		document.documentElement.classList.toggle('dunkel', wert === 'dunkel');
		// Nur im Entwurf: erzwungenes Thema auch für Formular-Elemente (die Website folgt allein dem System).
		document.documentElement.style.colorScheme = wert === 'hell' ? 'light' : wert === 'dunkel' ? 'dark' : '';
		document.getElementById('d-thema').value = wert;
	}

	function stufe() {
		const breite = window.innerWidth;
		const text = breite < 736 ? 'schmal (Handy) < 736 px' : breite < 992 ? 'mittel (Tablet) 736–991 px' : 'breit ≥ 992 px';
		document.getElementById('d-stufe').textContent = 'Breite ' + breite + ' px · Stufe ' + text;
	}

	document.getElementById('d-ansicht').addEventListener('change', (event) => zeige(event.target.value));
	document.getElementById('d-daten').addEventListener('change', (event) => datenWaehlen(event.target.value));
	document.getElementById('d-fassung').addEventListener('change', (event) => { state.fassung = event.target.value; zeige(state.ansicht === 'freigeben' ? 'vorschau' : state.ansicht); });
	document.getElementById('d-thema').addEventListener('change', (event) => thema(event.target.value));
	window.addEventListener('resize', stufe);
	window.addEventListener('hashchange', () => {
		const ziel = location.hash.slice(1);
		if (ziel !== state.ansicht && WOFUER[ziel]) {
			zeige(ziel);
		}
	});

	window.dummy = { zeige, daten: datenWaehlen, thema, fassung: (wert) => { state.fassung = wert; zeige(state.ansicht); }, oeffneZeile: (id) => { state.offeneZeile = id; render(); } };

	kopfBauen();
	datenSetzen('beispiel');
	stufe();
	zeige(location.hash.slice(1) || 'start');
})();
