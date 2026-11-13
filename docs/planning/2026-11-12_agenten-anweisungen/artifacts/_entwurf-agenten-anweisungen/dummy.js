// Klickdummy „Anweisungen für Agenten“. Vorlage für app/theme/redaktion/anweisungen.js.
// Im Produkt kommen Daten und gerendertes HTML vom Server (GET /dispatch/api/instructions …); hier liegen sie in daten.js,
// und Markdown, Vergleich und Speichern werden nur nachgebaut.
'use strict';

(() => {
	const DATEN = window.ENTWURF_DATEN;
	const SPEICHER_SCHLUESSEL = 'dispatch.anweisungen.werkzeuge';
	const NOTIZ_MAX = 500;
	const DOKUMENTE = { richtlinien: 'editorial_guidelines', praeferenzen: 'preferences' };
	const DOKUMENT_NAMEN = { editorial_guidelines: 'Richtlinien', preferences: 'Präferenzen' };
	const RECHTE = { 'blog:read': 'Lesen', 'blog:write': 'Schreiben', 'blog:admin': 'Einstellungen' };
	const BEREICHE = [
		['Briefing und Dokumente', ['get_editorial_brief', 'get_style_guide', 'list_documents', 'get_document', 'update_document']],
		['Kategorien und Einstellungen', ['list_categories', 'save_category', 'get_site_info', 'update_settings', 'list_changes']],
		['Queue', ['list_queue', 'add_to_queue', 'claim_queue_item', 'release_queue_item']],
		['Artikel', ['list_posts', 'search_posts', 'get_post', 'create_post', 'update_post', 'validate_post', 'submit_post', 'withdraw_post', 'delete_post', 'list_post_revisions', 'restore_post_revision']],
		['Medien', ['import_media_from_url', 'upload_media', 'update_media', 'list_media', 'get_media']],
		['Statistik und Website', ['get_stats', 'get_post_stats', 'rebuild_site']],
	];
	const WOFUER = {
		menue: 'Den neuen Eintrag finden: Er steht bei den Agenten-Themen direkt unter „Zugänge für Agenten“.',
		uebersicht: 'Auf einen Blick sehen, was ein Agent in welcher Reihenfolge an Anweisungen bekommt und was davon hier änderbar ist.',
		richtlinien: 'Die gültigen Redaktionsrichtlinien lesen, zu einem Abschnitt springen, Bearbeiten oder Verlauf öffnen.',
		'richtlinien/bearbeiten': 'Die Richtlinien ändern und speichern; die Änderung gilt ab dem nächsten Agenten-Lauf.',
		'richtlinien/verlauf': 'Nachsehen, wer wann was geändert hat, Fassungen vergleichen und eine alte Fassung zurückholen.',
		praeferenzen: 'Die gültigen Präferenzen lesen, zu einem Abschnitt springen, Bearbeiten oder Verlauf öffnen.',
		'praeferenzen/bearbeiten': 'Die Präferenzen ändern und speichern; die Änderung gilt ab dem nächsten Agenten-Lauf.',
		'praeferenzen/verlauf': 'Nachsehen, wer wann die Präferenzen geändert hat, Fassungen vergleichen und eine alte zurückholen.',
		werkzeuge: 'Nachschlagen, welche Werkzeuge ein Agent hat, was jedes tut und welche Angaben es erwartet.',
		ablaeufe: 'Nachlesen, welche festen Texte ein Agent beim Verbinden, im Briefing und vor dem Schreiben bekommt.',
	};
	const ICONS = {
		person: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		check: '<path d="M20 6 9 17l-5-5"/>',
		stift: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		herz: '<path d="M19.5 12.6 12 20l-7.5-7.4A5 5 0 1 1 12 6a5 5 0 1 1 7.5 6.6z"/>',
		chart: '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
		schluessel: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/>',
		ki: '<path d="M12 3l1.8 4.7L18.5 9.5 13.8 11.3 12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
		uhr: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		warn: '<path d="M12 3 2 21h20zM12 10v5M12 18h.01"/>',
		tuer: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
		lupe: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		zurueck: '<path d="M15 18l-6-6 6-6"/>',
	};

	const zustand = {
		ansicht: 'menue',
		modus: { editorial_guidelines: 'lesen', preferences: 'lesen' },
		entwurf: { editorial_guidelines: null, preferences: null },
		offeneWerkzeuge: new Set(),
		offeneVergleiche: new Set(),
		offeneBloecke: new Set(['server']),
		suche: '',
		filter: { recht: new Set(), wirkung: new Set() },
		panel: null,
		anzeige: ladeAnzeige(),
		konfliktEingespielt: false,
	};
	const dummy = { zustand: 'normal' };
	let box = null;
	let menue = null;

	// ---------- Bausteine (wie app/theme/redaktion/ui.js) ----------
	function el(tag, attributes = {}, children = []) {
		const node = document.createElement(tag);
		for (const [name, value] of Object.entries(attributes)) {
			if (value === false || value === null || value === undefined) continue;
			if (name.startsWith('on')) node.addEventListener(name.slice(2), value);
			else if (name === 'text') node.textContent = value;
			else if (name === 'html') node.innerHTML = value;
			else node.setAttribute(name, value === true ? '' : value);
		}
		for (const child of [children].flat(Infinity)) {
			if (child === false || child === null || child === undefined) continue;
			node.append(child instanceof Node ? child : String(child));
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
	function werkzeugHinweis(text) {
		return el('span', { class: 'anw-werkzeug' }, ['Agent: ', el('code', { text })]);
	}
	function beispiel() {
		return el('span', { class: 'dummy-beispiel', text: 'Beispiel' });
	}
	let toastTimer = 0;
	function toast(text, rueckgaengig) {
		let t = document.getElementById('toast');
		if (!t) {
			t = el('div', { class: 'toast', id: 'toast', role: 'status' }, [el('span', { id: 'toast-text' }), el('button', { type: 'button', id: 'toast-zurueck', text: 'Rückgängig' })]);
			document.body.append(t);
		}
		t.querySelector('#toast-text').textContent = text;
		const knopf = t.querySelector('#toast-zurueck');
		knopf.hidden = typeof rueckgaengig !== 'function';
		knopf.onclick = () => { t.classList.remove('zeigen'); rueckgaengig(); };
		t.classList.add('zeigen');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(() => t.classList.remove('zeigen'), knopf.hidden ? 3800 : 7000);
	}

	// ---------- Markdown: Nachbau von Util\Markdown nach der Erweiterung um Code und Tabellen ----------
	function maskiere(text) {
		return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
	function inline(text) {
		const codes = [];
		let html = text.replace(/`([^`]+)`/g, (_, code) => { codes.push(code); return '\u0001' + (codes.length - 1) + '\u0002'; });
		html = maskiere(html)
			.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
			.replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
			.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_, label, ziel) => (/^(https?:|\/)/.test(ziel) ? `<a href="${ziel}">${label}</a>` : label));
		return html.replace(/\u0001(\d+)\u0002/g, (_, index) => '<code>' + maskiere(codes[Number(index)]) + '</code>');
	}
	function anker(text) {
		return 'abschnitt-' + text.toLowerCase().replace(/[^a-z0-9äöüß]+/g, '-').replace(/^-|-$/g, '');
	}
	function istBlockStart(zeile) {
		return /^(#{1,4}\s|>|[-*]\s|\d+\.\s|\||```|-{3,}\s*$)/.test(zeile);
	}
	function markdown(text) {
		const zeilen = text.replace(/\r/g, '').split('\n');
		const html = [];
		let i = 0;
		while (i < zeilen.length) {
			const zeile = zeilen[i];
			let treffer;
			if (zeile.trim() === '') { i++; continue; }
			if (zeile.startsWith('```')) {
				const code = [];
				for (i++; i < zeilen.length && !zeilen[i].startsWith('```'); i++) code.push(zeilen[i]);
				i++;
				html.push('<pre><code>' + maskiere(code.join('\n')) + '</code></pre>');
			} else if ((treffer = zeile.match(/^(#{1,4})\s+(.*)$/))) {
				const ebene = treffer[1].length <= 2 ? 3 : 4;
				html.push(`<h${ebene}${ebene === 3 ? ` id="${anker(treffer[2])}"` : ''}>${inline(treffer[2])}</h${ebene}>`);
				i++;
			} else if (/^-{3,}\s*$/.test(zeile)) {
				html.push('<hr>');
				i++;
			} else if (zeile.startsWith('|')) {
				const reihen = [];
				for (; i < zeilen.length && zeilen[i].startsWith('|'); i++) reihen.push(zeilen[i].replace(/^\||\|\s*$/g, '').split('|').map((zelle) => zelle.trim()));
				const [kopf, , ...rumpf] = reihen;
				html.push('<table><thead><tr>' + kopf.map((zelle) => `<th>${inline(zelle)}</th>`).join('') + '</tr></thead><tbody>'
					+ rumpf.map((reihe) => '<tr>' + reihe.map((zelle) => `<td>${inline(zelle)}</td>`).join('') + '</tr>').join('') + '</tbody></table>');
			} else if (zeile.startsWith('>')) {
				const absaetze = [];
				for (; i < zeilen.length && zeilen[i].startsWith('>'); i++) absaetze.push('<p>' + inline(zeilen[i].replace(/^>\s?/, '')) + '</p>');
				html.push('<blockquote>' + absaetze.join('') + '</blockquote>');
			} else if (/^([-*]|\d+\.)\s/.test(zeile)) {
				const geordnet = /^\d+\./.test(zeile);
				const punkte = [];
				for (; i < zeilen.length && /^([-*]|\d+\.)\s/.test(zeilen[i]); i++) punkte.push('<li>' + inline(zeilen[i].replace(/^([-*]|\d+\.)\s+/, '')) + '</li>');
				html.push(geordnet ? `<ol>${punkte.join('')}</ol>` : `<ul>${punkte.join('')}</ul>`);
			} else {
				const absatz = [];
				for (; i < zeilen.length && zeilen[i].trim() !== '' && (absatz.length === 0 || !istBlockStart(zeilen[i])); i++) absatz.push(zeilen[i].trim());
				html.push('<p>' + inline(absatz.join(' ')) + '</p>');
			}
		}
		return html.join('');
	}
	function textBlock(text) {
		return el('div', { class: 'anw-text', html: markdown(text) });
	}

	// ---------- Vergleich zweier Fassungen: Absätze per längster gemeinsamer Teilfolge ----------
	function absaetze(text) {
		return text.replace(/\r/g, '').split(/\n\s*\n/).map((absatz) => absatz.trim()).filter(Boolean);
	}
	function vergleiche(alt, neu) {
		const a = absaetze(alt);
		const b = absaetze(neu);
		const tabelle = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0));
		for (let x = a.length - 1; x >= 0; x--) {
			for (let y = b.length - 1; y >= 0; y--) {
				tabelle[x][y] = a[x] === b[y] ? tabelle[x + 1][y + 1] + 1 : Math.max(tabelle[x + 1][y], tabelle[x][y + 1]);
			}
		}
		const schritte = [];
		let x = 0;
		let y = 0;
		while (x < a.length || y < b.length) {
			if (x < a.length && y < b.length && a[x] === b[y]) { schritte.push(['gleich', a[x]]); x++; y++; }
			else if (y < b.length && (x === a.length || tabelle[x][y + 1] >= tabelle[x + 1][y])) { schritte.push(['neu', b[y]]); y++; }
			else { schritte.push(['weg', a[x]]); x++; }
		}
		return schritte;
	}
	function vergleichsAnsicht(alt, neu) {
		const teile = [];
		let gleich = 0;
		const unveraendert = () => {
			if (gleich > 0) teile.push(el('p', { class: 'anw-unveraendert', text: gleich === 1 ? '1 Absatz unverändert' : gleich + ' Absätze unverändert' }));
			gleich = 0;
		};
		for (const [art, absatz] of vergleiche(alt, neu)) {
			if (art === 'gleich') { gleich++; continue; }
			unveraendert();
			teile.push(el('div', { class: art === 'neu' ? 'diff-neu' : 'diff-weg' }, [
				el('small', { class: 'anw-diffwort', text: art === 'neu' ? 'Neu' : 'Entfernt' }),
				textBlock(absatz),
			]));
		}
		unveraendert();
		return teile;
	}

	// ---------- Hilfen für Fassungen und Akteure ----------
	function zeit(utc) {
		return new Date(utc.replace(' ', 'T') + 'Z').toLocaleString('de-DE', { timeZone: 'Europe/Berlin', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
	}
	function jetztUtc() {
		return new Date().toISOString().slice(0, 19).replace('T', ' ');
	}
	function wer(akteur) {
		if (akteur === 'owner') return { name: 'Du', art: 'Herausgeber' };
		if (akteur === 'cli') return { name: 'Einrichtung', art: 'Kommandozeile' };
		const oauth = akteur.match(/^oauth:(.+)$/);
		if (oauth) return { name: oauth[1], art: 'Agent, angemeldet als App' };
		const token = akteur.match(/^mcp:(.+)$/);
		if (token) return { name: token[1], art: 'Agent mit Zugangs-Token' };
		return { name: akteur, art: '' };
	}
	function aktuell(key) {
		return DATEN.documents[key].revisions[0];
	}
	function neueFassung(key, inhalt, notiz) {
		const fassung = { revision: aktuell(key).revision + 1, created_at: jetztUtc(), actor: 'owner', note: notiz || null, content: inhalt };
		DATEN.documents[key].revisions.unshift(fassung);
		return fassung;
	}

	// ---------- Navigation über die Adresse ----------
	function ziel() {
		const teile = location.hash.slice(1).split('/');
		return { reiter: teile[0] || 'menue', unter: teile[1] || '' };
	}
	function geheZu(adresse) {
		if (location.hash !== '#' + adresse) location.hash = adresse;
		else zeichne();
	}
	/** Schlüssel des Dokuments mit ungespeichertem Entwurf, sonst null. */
	function ungespeichert() {
		for (const key of Object.values(DOKUMENTE)) {
			const entwurf = zustand.entwurf[key];
			if (entwurf && entwurf.text !== aktuell(key).content) return key;
		}
		return null;
	}
	function slugVon(key) {
		return key === 'editorial_guidelines' ? 'richtlinien' : 'praeferenzen';
	}

	// Wer den Bearbeiten-Modus mit Änderungen verlässt, wird gefragt; in den Verlauf desselben Dokuments darf er.
	window.addEventListener('hashchange', (event) => {
		const offen = ungespeichert();
		const altHash = new URL(event.oldURL).hash.slice(1).split('/');
		const neu = ziel();
		const kamAusBearbeiten = offen && DOKUMENTE[altHash[0]] === offen && altHash[1] === 'bearbeiten';
		const bleibtBeimDokument = DOKUMENTE[neu.reiter] === offen && (neu.unter === 'verlauf' || neu.unter === 'bearbeiten');
		if (kamAusBearbeiten && !bleibtBeimDokument) {
			history.replaceState(null, '', new URL(event.oldURL).hash);
			zustand.entwurf[offen].meldung = { art: 'verwerfen', weiter: '#' + neu.reiter + (neu.unter ? '/' + neu.unter : '') };
		}
		zeichne();
	});

	// ---------- Menü (wie konto.js, mit neuem Eintrag) ----------
	function baueKopf() {
		const eintraege = [
			{ icon: 'check', label: 'Freigaben' },
			{ icon: 'stift', label: 'Vorschläge' },
			{ icon: 'plus', label: 'Thema einreichen', trenner: true },
			{ icon: 'herz', label: 'Interessen' },
			{ icon: 'chart', label: 'Statistik' },
			{ icon: 'schluessel', label: 'Zugänge für Agenten' },
			{ icon: 'ki', label: 'Anweisungen für Agenten', ziel: 'uebersicht', trenner: true },
			{ icon: 'tuer', label: 'Abmelden' },
		];
		menue = el('div', { id: 'menue', popover: 'auto', class: 'menue' }, [
			el('div', { class: 'kopfzeile', text: 'Angemeldet als Herausgeber' }),
			eintraege.map((eintrag) => [
				el('button', {
					type: 'button',
					onclick: () => {
						menue.hidePopover();
						if (eintrag.ziel) geheZu(eintrag.ziel);
						else toast('Im Entwurf nicht belegt.');
					},
				}, [icon(eintrag.icon), eintrag.label]),
				eintrag.trenner ? el('hr') : null,
			]),
		]);
		const konto = el('button', { type: 'button', class: 'konto', popovertarget: 'menue', 'aria-label': 'Redaktionsmenü' }, [el('span', { class: 'avatar' }, icon('person')), el('span', { class: 'text', text: 'Redaktion' })]);
		menue.addEventListener('beforetoggle', (event) => {
			if (event.newState === 'open') {
				const rahmen = konto.getBoundingClientRect();
				menue.style.top = rahmen.bottom + 8 + 'px';
				menue.style.right = Math.max(8, window.innerWidth - rahmen.right) + 'px';
			}
		});
		document.body.append(menue);
		document.getElementById('kopf-rechts').append(konto);
	}

	// ---------- Dialog ----------
	const REITER = [['uebersicht', 'Übersicht'], ['richtlinien', 'Richtlinien'], ['praeferenzen', 'Präferenzen'], ['werkzeuge', 'Werkzeuge'], ['ablaeufe', 'Abläufe']];

	function baueDialog() {
		box = el('dialog', { id: 'anweisungen', class: 'breit anweisungen', 'aria-labelledby': 'anweisungen-titel' });
		box.addEventListener('cancel', (event) => {
			if (zustand.panel) { event.preventDefault(); return; }
			if (ungespeichert()) { event.preventDefault(); verwerfenFragen(); }
		});
		box.addEventListener('close', () => { if (ziel().reiter !== 'menue') history.replaceState(null, '', '#menue'); zeichneDummyLeiste(); });
		document.body.append(box);
	}
	function verwerfenFragen() {
		const key = ungespeichert();
		zustand.entwurf[key].meldung = { art: 'verwerfen', weiter: 'schliessen' };
		history.replaceState(null, '', '#' + slugVon(key) + '/bearbeiten');
		zeichne();
	}
	function reiterLeiste(aktiv) {
		const leiste = el('div', { class: 'reiter', role: 'tablist', 'aria-label': 'Bereiche' });
		for (const [id, name] of REITER) {
			const anzahl = id === 'werkzeuge' ? el('span', { class: 'anzahl', text: String(DATEN.tools.length) }) : null;
			leiste.append(el('button', {
				type: 'button', role: 'tab', id: 'reiter-' + id, 'aria-selected': String(id === aktiv), 'aria-controls': 'anweisungen-rumpf', tabindex: id === aktiv ? '0' : '-1',
				onclick: () => geheZu(id),
			}, [name, anzahl]));
		}
		leiste.addEventListener('keydown', (event) => {
			const index = REITER.findIndex(([id]) => id === aktiv);
			const schritt = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
			if (schritt === 0) return;
			const naechster = REITER[(index + schritt + REITER.length) % REITER.length][0];
			geheZu(naechster);
			requestAnimationFrame(() => document.getElementById('reiter-' + naechster).focus());
		});
		return leiste;
	}
	function zeichneDialog(reiter, unter) {
		const rumpf = el('div', { class: 'dlg-rumpf', id: 'anweisungen-rumpf', role: 'tabpanel', 'aria-labelledby': 'reiter-' + reiter });
		const fuss = el('div', { class: 'dlg-fuss', hidden: true });
		if (dummy.zustand === 'laedt') {
			rumpf.append(el('div', { 'aria-busy': 'true', 'aria-label': 'Anweisungen werden geladen' }, [el('div', { class: 'lader titel' }), el('div', { class: 'lader' }), el('div', { class: 'lader' }), el('div', { class: 'lader kurz' }), el('div', { class: 'lader' }), el('div', { class: 'lader kurz' })]));
		} else if (dummy.zustand === 'fehler') {
			rumpf.append(el('div', { class: 'leer', role: 'alert' }, [icon('warn'), el('p', { text: 'Die Anweisungen ließen sich nicht laden. Der Server hat nicht geantwortet.' }), el('button', { type: 'button', class: 'knopf', text: 'Erneut versuchen', onclick: () => toast('Im Entwurf: „Zustand“ oben auf „Normal“ stellen.') })]));
		} else if (reiter === 'uebersicht') {
			rumpf.append(...uebersicht());
		} else if (DOKUMENTE[reiter]) {
			dokument(DOKUMENTE[reiter], unter, rumpf, fuss);
		} else if (reiter === 'werkzeuge') {
			rumpf.append(...werkzeuge(unter));
		} else if (reiter === 'ablaeufe') {
			rumpf.append(...ablaeufe(unter));
		}
		box.replaceChildren(
			el('div', { class: 'dlg-kopf' }, [
				el('h2', { id: 'anweisungen-titel', tabindex: '-1', text: 'Anweisungen für Agenten' }),
				el('button', { type: 'button', class: 'zu', 'aria-label': 'Schließen', text: '✕', onclick: () => (ungespeichert() ? verwerfenFragen() : box.close()) }),
			]),
			reiterLeiste(reiter),
			rumpf,
			fuss,
		);
		if (!box.open) box.showModal();
	}

	// ---------- Reiter „Übersicht“ ----------
	function teil(text, unter, adresse, herkunft, werkzeug) {
		const inhalt = [
			el('span', { class: 't' }, [el('b', { text }), unter ? el('small', {}, unter) : null, werkzeug ? werkzeugHinweis(werkzeug) : null]),
			el('span', { class: 'anw-herkunft' + (herkunft === 'hier' ? ' hier' : ''), text: herkunft === 'hier' ? 'hier änderbar' : herkunft }),
		];
		return el('li', {}, adresse ? el('a', { href: '#' + adresse }, inhalt) : el('div', { class: 'ohne-link' }, inhalt));
	}
	function standText(key) {
		const fassung = aktuell(key);
		return 'Fassung ' + fassung.revision + ' · ' + zeit(fassung.created_at) + ' · ' + wer(fassung.actor).name;
	}
	function uebersicht() {
		const regeln = DATEN.form_rules;
		const schritt = (wann, werkzeug, teile) => el('li', {}, [el('div', {}, [el('p', { class: 'anw-wann' }, [wann, werkzeug ? el('code', { text: werkzeug }) : null]), el('ul', { class: 'anw-teile' }, teile)])]);
		return [
			el('p', { class: 'anw-lead', text: 'Ein Agent bekommt seine Anweisungen in fünf Schritten. Was hier als „hier änderbar“ markiert ist, kannst du in diesem Dialog bearbeiten; es gilt ab seinem nächsten Lauf.' }),
			el('ol', { class: 'anw-kette' }, [
				schritt('Beim Verbinden', null, [teil('Server-Anweisung', 'Wie ein Redaktionslauf beginnt und was verbindlich ist', 'ablaeufe/server', 'fest im Code')]),
				schritt('Zu Beginn jedes Laufs', 'get_editorial_brief', [
					teil('Redaktionsrichtlinien', [standText('editorial_guidelines'), ' ', beispiel()], 'richtlinien', 'hier', 'get_document · update_document'),
					teil('Präferenzen', [standText('preferences'), ' ', beispiel()], 'praeferenzen', 'hier', 'get_document · update_document'),
					teil('Formvorgaben', `Mindestens ${regeln.min_words} Wörter und ${regeln.min_sources} Quellen, bei Tiefe „tief“ ${regeln.min_words_deep.toLocaleString('de-DE')} und ${regeln.min_sources_deep}; Titelbild und Bildlizenz Pflicht`, null, 'fest in der Konfiguration'),
					teil('Workflow des gewählten Wegs', 'Geplanter Artikel, Auftrag aus der Queue oder zurückgegebener Entwurf', 'ablaeufe/geplant', 'fest im Code'),
				]),
				schritt('Vor dem Schreiben', 'get_style_guide', [teil('Style-Guide', 'Erlaubtes HTML, Gestaltungsmittel, Bilder, Belege, Formprüfung', 'ablaeufe/style', 'fest im Code')]),
				schritt('Bei jedem Werkzeug', null, [teil(DATEN.tools.length + ' Werkzeuge', 'Je Werkzeug Beschreibung, Angaben und Hinweise', 'werkzeuge', 'fest im Code')]),
				schritt('Beim Prüfen und Einreichen', 'validate_post · submit_post', [teil('Formprüfung', 'Fehler blockieren das Einreichen, Warnungen nicht — darunter fehlende „Kurz gesagt“-Kästen', 'ablaeufe/style', 'fest im Code')]),
			]),
			el('section', { class: 'anw-zuletzt', 'aria-labelledby': 'zuletzt-titel' }, [
				el('h3', { id: 'zuletzt-titel', text: 'Zuletzt geändert' }),
				el('ul', {}, Object.keys(DOKUMENT_NAMEN).map((key) => el('li', {}, [DOKUMENT_NAMEN[key] + ': ' + standText(key) + (aktuell(key).note ? ' — „' + aktuell(key).note + '“' : '') + ' ', beispiel()]))),
			]),
		];
	}

	// ---------- Reiter „Richtlinien“ und „Präferenzen“ ----------
	function dokument(key, unter, rumpf, fuss) {
		const gewuenscht = unter === 'bearbeiten' ? 'bearbeiten' : unter === 'verlauf' ? 'verlauf' : 'lesen';
		if (gewuenscht === 'bearbeiten' && zustand.entwurf[key] === null) {
			zustand.entwurf[key] = { text: aktuell(key).content, notiz: '', vorschau: false, meldung: null, fehler: '', basis: aktuell(key).revision };
		}
		const offen = zustand.entwurf[key];
		if (gewuenscht !== 'bearbeiten' && offen && offen.text === aktuell(key).content) zustand.entwurf[key] = null;
		zustand.modus[key] = gewuenscht;
		if (gewuenscht === 'bearbeiten') bearbeiten(key, rumpf, fuss);
		else if (gewuenscht === 'verlauf') verlauf(key, rumpf);
		else lesen(key, rumpf);
	}
	function lesen(key, rumpf) {
		const fassung = aktuell(key);
		const slug = key === 'editorial_guidelines' ? 'richtlinien' : 'praeferenzen';
		const urheber = wer(fassung.actor);
		const abschnitte = [...fassung.content.matchAll(/^##\s+(.+)$/gm)].map((treffer) => treffer[1]);
		rumpf.append(
			el('div', { class: 'anw-kopf' }, [
				el('p', { class: 'anw-stand' }, [el('b', { text: 'Fassung ' + fassung.revision }), ' · ' + zeit(fassung.created_at) + ' · ' + urheber.name + (urheber.art ? ' (' + urheber.art + ')' : ''), beispiel(), info('Gilt ab dem nächsten Agenten-Lauf. Agenten lesen das Dokument im Briefing und ändern es mit dem Werkzeug update_document; jede Änderung landet im Verlauf.')]),
				el('span', { class: 'anw-aktionen' }, [
					el('span', { class: 'anw-aktion' }, [el('a', { class: 'knopf leise', href: '#' + slug + '/verlauf' }, [icon('uhr'), 'Verlauf']), werkzeugHinweis('kein Werkzeug, nur hier')]),
					el('span', { class: 'anw-aktion' }, [el('a', { class: 'knopf primaer', href: '#' + slug + '/bearbeiten' }, [icon('stift'), zustand.entwurf[key] ? 'Weiter bearbeiten' : DOKUMENT_NAMEN[key] + ' bearbeiten']), werkzeugHinweis('update_document')]),
				]),
			]),
			abschnitte.length > 2 ? el('nav', { 'aria-label': 'Abschnitte' }, el('ul', { class: 'anw-sprung' }, abschnitte.map((titel) => el('li', {}, el('a', {
				href: '#' + slug, onclick: (event) => { event.preventDefault(); document.getElementById(anker(titel))?.scrollIntoView({ block: 'start' }); },
			}, titel))))) : null,
			textBlock(fassung.content),
		);
	}
	function meldung(key) {
		const entwurf = zustand.entwurf[key];
		if (!entwurf.meldung) return null;
		const slug = key === 'editorial_guidelines' ? 'richtlinien' : 'praeferenzen';
		if (entwurf.meldung.art === 'verwerfen') {
			return el('div', { class: 'anw-meldung ruhig', role: 'alert' }, [
				el('p', {}, [el('b', { text: 'Deine Änderungen sind noch nicht gespeichert. ' }), 'Verwerfen oder weiter bearbeiten?']),
				el('div', { class: 'knopfzeile' }, [
					el('button', { type: 'button', class: 'knopf', text: 'Weiter bearbeiten', onclick: () => { entwurf.meldung = null; zeichne(); } }),
					el('button', { type: 'button', class: 'knopf gefahr', text: 'Änderungen verwerfen', onclick: () => {
						const weiter = entwurf.meldung.weiter;
						zustand.entwurf[key] = null;
						zustand.modus[key] = 'lesen';
						if (weiter === 'schliessen') box.close();
						else { history.replaceState(null, '', weiter); zeichne(); }
					} }),
				]),
			]);
		}
		if (entwurf.meldung.art === 'konflikt') {
			const fremd = aktuell(key);
			return el('div', { class: 'anw-meldung', role: 'alert' }, [
				el('p', {}, [el('b', { text: 'Noch nicht gespeichert: ' }), `${wer(fremd.actor).name} hat die ${DOKUMENT_NAMEN[key]} inzwischen geändert (Fassung ${fremd.revision}, ${zeit(fremd.created_at)}). Du hast auf Fassung ${entwurf.basis} aufgebaut.`]),
				el('p', { text: 'Speicherst du trotzdem, wird deine Fassung die neueste; die des Agenten bleibt im Verlauf und lässt sich zurückholen.' }),
				el('div', { class: 'knopfzeile' }, [
					el('button', { type: 'button', class: 'knopf primaer', text: 'Meine Fassung trotzdem speichern', onclick: () => speichern(key, true) }),
					el('a', { class: 'knopf', href: '#' + slug + '/verlauf', text: 'Änderung des Agenten ansehen', onclick: () => { entwurf.meldung = null; } }),
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
		const geaendert = entwurf.text !== aktuell(key).content;
		const zuLang = entwurf.notiz.length > NOTIZ_MAX;
		const fehler = el('p', { class: 'feldfehler', id: 'inhalt-fehler', role: 'alert', hidden: entwurf.fehler === '', text: entwurf.fehler });
		const editor = el('textarea', {
			class: 'anw-editor', id: 'anw-editor', 'aria-label': DOKUMENT_NAMEN[key] + ' als Markdown', spellcheck: 'true', 'aria-invalid': entwurf.fehler ? 'true' : null, 'aria-describedby': 'inhalt-fehler',
			oninput: (event) => { entwurf.text = event.target.value; entwurf.fehler = ''; aktualisiereFuss(); },
		});
		editor.value = entwurf.text;
		const zaehlung = el('span', { class: 'zaehlung' + (zuLang ? ' zu-lang' : ''), text: entwurf.notiz.length + ' / ' + NOTIZ_MAX });
		const notiz = el('input', {
			type: 'text', id: 'anw-notiz', value: entwurf.notiz, 'aria-describedby': 'notiz-hilfe', 'aria-invalid': zuLang ? 'true' : null,
			oninput: (event) => { entwurf.notiz = event.target.value; zaehlung.textContent = entwurf.notiz.length + ' / ' + NOTIZ_MAX; zaehlung.classList.toggle('zu-lang', entwurf.notiz.length > NOTIZ_MAX); aktualisiereFuss(); },
		});
		const umschalter = el('div', { class: 'anw-umschalter', role: 'group', 'aria-label': 'Darstellung' }, [
			el('button', { type: 'button', 'aria-pressed': String(!entwurf.vorschau), text: 'Text', onclick: () => { entwurf.vorschau = false; zeichne(); } }),
			el('button', { type: 'button', 'aria-pressed': String(entwurf.vorschau), text: 'Vorschau', onclick: () => { entwurf.vorschau = true; zeichne(); } }),
		]);
		rumpf.append(
			meldung(key) || '',
			el('div', { class: 'anw-bearbeiten' }, [
				el('div', { class: 'anw-kopf' }, [el('p', { class: 'anw-stand' }, [el('b', { text: DOKUMENT_NAMEN[key] + ' bearbeiten' }), ' · Grundlage: Fassung ' + entwurf.basis, info('Markdown: ## Überschrift, - Liste, **fett**, `Code`, | Tabelle |. Speichern legt eine neue Fassung an; die bisherige bleibt im Verlauf.')]), umschalter]),
				entwurf.vorschau ? el('div', { class: 'anw-vorschau' }, textBlock(entwurf.text)) : el('div', {}, [editor, fehler]),
				el('label', { class: 'anw-notiz', for: 'anw-notiz' }, ['Was hast du geändert?', notiz, el('span', { class: 'feldhilfe', id: 'notiz-hilfe' }, [el('span', { text: 'Optional. Erscheint im Verlauf, damit du und die Agenten den Grund kennen.' }), zaehlung])]),
			]),
		);
		const abbrechen = el('button', { type: 'button', class: 'knopf leise', text: 'Abbrechen', onclick: () => {
			if (ungespeichert() === key) { entwurf.meldung = { art: 'verwerfen', weiter: '#' + slugVon(key) }; zeichne(); }
			else { zustand.entwurf[key] = null; geheZu(slugVon(key)); }
		} });
		const speichernKnopf = el('button', { type: 'button', class: 'knopf primaer', title: 'Strg+S', onclick: () => speichern(key, false) }, 'Fassung speichern');
		function aktualisiereFuss() {
			speichernKnopf.disabled = entwurf.text === aktuell(key).content || entwurf.notiz.length > NOTIZ_MAX;
		}
		aktualisiereFuss();
		fuss.hidden = false;
		fuss.append(el('span', { class: 'anw-fusstext', text: geaendert ? 'Ungespeicherte Änderungen' : 'Noch nichts geändert' }), abbrechen, el('span', { class: 'anw-aktion' }, [speichernKnopf, werkzeugHinweis('update_document')]));
		rumpf.addEventListener('keydown', (event) => {
			if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); if (!speichernKnopf.disabled) speichern(key, false); }
		});
		if (!entwurf.vorschau && !entwurf.meldung) requestAnimationFrame(() => editor.focus({ preventScroll: true }));
	}
	function speichern(key, trotzdem) {
		const entwurf = zustand.entwurf[key];
		if (entwurf.text.trim() === '') {
			entwurf.fehler = 'Der Inhalt darf nicht leer sein — ohne Text arbeiten die Agenten ohne diese Regeln.';
			zeichne();
			return;
		}
		if (dummy.zustand === 'offline') {
			entwurf.meldung = { art: 'offline' };
			zeichne();
			return;
		}
		if (dummy.zustand === 'konflikt' && !trotzdem) {
			if (!zustand.konfliktEingespielt) {
				const basis = aktuell(key);
				DATEN.documents[key].revisions.unshift({ revision: basis.revision + 1, created_at: jetztUtc(), actor: 'oauth:ChatGPT', note: 'Korrekturhinweis ergänzt.', content: basis.content + '\n\nNenne in der Korrekturbox, was falsch war und seit wann es richtig ist.' });
				zustand.konfliktEingespielt = true;
			}
			entwurf.meldung = { art: 'konflikt' };
			zeichne();
			return;
		}
		const vorher = aktuell(key);
		const fassung = neueFassung(key, entwurf.text, entwurf.notiz.trim());
		zustand.entwurf[key] = null;
		zustand.modus[key] = 'lesen';
		geheZu(key === 'editorial_guidelines' ? 'richtlinien' : 'praeferenzen');
		toast(`Gespeichert als Fassung ${fassung.revision}. Gilt ab dem nächsten Agenten-Lauf.`, () => {
			neueFassung(key, vorher.content, `Fassung ${fassung.revision} rückgängig gemacht`);
			zeichne();
			toast(`Fassung ${vorher.revision} ist wieder gültig.`);
		});
	}
	function verlauf(key, rumpf) {
		const fassungen = DATEN.documents[key].revisions;
		const slug = key === 'editorial_guidelines' ? 'richtlinien' : 'praeferenzen';
		const entwurf = zustand.entwurf[key];
		rumpf.append(
			entwurf ? el('div', { class: 'anw-meldung ruhig' }, [el('p', {}, [el('b', { text: 'Dein Entwurf ist noch offen. ' }), 'Er wartet unverändert, bis du ihn speicherst oder verwirfst.']), el('div', { class: 'knopfzeile' }, el('a', { class: 'knopf', href: '#' + slug + '/bearbeiten', text: 'Weiter bearbeiten' }))]) : '',
			el('div', { class: 'anw-kopf' }, [
				el('a', { class: 'knopf leise', href: '#' + slug }, [icon('zurueck'), 'Gültige Fassung']),
				el('p', { class: 'anw-stand' }, [el('b', { text: 'Verlauf der ' + DOKUMENT_NAMEN[key] }), ' · ' + fassungen.length + ' Fassungen', beispiel()]),
			]),
			el('ol', { class: 'anw-verlauf' }, fassungen.map((fassung, index) => {
				const vorgaenger = fassungen[index + 1];
				const urheber = wer(fassung.actor);
				const kennung = key + ':' + fassung.revision;
				const offen = zustand.offeneVergleiche.has(kennung);
				const vergleichbar = vorgaenger && fassung.content !== null && vorgaenger.content !== null;
				return el('li', {}, [
					el('span', { class: 'anw-rev' }, [String(fassung.revision), index === 0 ? el('small', { text: 'gültig' }) : null]),
					el('span', { class: 'anw-zeit', text: zeit(fassung.created_at) }),
					el('span', { class: 'anw-wer' }, [el('b', { text: urheber.name }), urheber.art ? ' · ' + urheber.art : '', el('small', { text: fassung.note ? '„' + fassung.note + '“' : 'ohne Anmerkung' })]),
					el('span', { class: 'aktionen' }, [
						vorgaenger ? el('button', { type: 'button', class: 'knopf klein-k', 'aria-expanded': String(offen), onclick: () => { offen ? zustand.offeneVergleiche.delete(kennung) : zustand.offeneVergleiche.add(kennung); zeichne(); } }, offen ? 'Vergleich schließen' : 'Mit Fassung ' + vorgaenger.revision + ' vergleichen') : null,
						index > 0 && fassung.content !== null ? el('button', { type: 'button', class: 'knopf klein-k', onclick: () => wiederherstellen(key, fassung) }, 'Wiederherstellen') : null,
					]),
					offen ? el('div', { class: 'anw-vergleich' }, vergleichbar ? vergleichsAnsicht(vorgaenger.content, fassung.content) : el('p', { class: 'anw-unveraendert', text: 'Für diese Fassung liegt im Entwurf kein Text vor; im Produkt hat jede Fassung ihren Text.' })) : null,
				]);
			})),
		);
	}
	function wiederherstellen(key, fassung) {
		const vorher = aktuell(key);
		const neu = neueFassung(key, fassung.content, `Fassung ${fassung.revision} wiederhergestellt`);
		zeichne();
		toast(`Fassung ${fassung.revision} ist als Fassung ${neu.revision} wieder gültig.`, () => {
			neueFassung(key, vorher.content, `Wiederherstellung rückgängig gemacht`);
			zeichne();
		});
	}

	// ---------- Reiter „Werkzeuge“ ----------
	function ladeAnzeige() {
		try {
			const gespeichert = JSON.parse(localStorage.getItem(SPEICHER_SCHLUESSEL) || '{}');
			return { ansicht: gespeichert.ansicht === 'tabelle' ? 'tabelle' : 'liste', gruppe: ['bereich', 'recht', 'keine'].includes(gespeichert.gruppe) ? gespeichert.gruppe : 'bereich' };
		} catch {
			return { ansicht: 'liste', gruppe: 'bereich' };
		}
	}
	function speichereAnzeige() {
		localStorage.setItem(SPEICHER_SCHLUESSEL, JSON.stringify(zustand.anzeige));
	}
	function wirkung(werkzeug) {
		if (werkzeug.annotations.readOnlyHint) return 'liest nur';
		return werkzeug.annotations.destructiveHint ? 'löscht' : 'ändert';
	}
	function bereich(werkzeug) {
		return (BEREICHE.find(([, namen]) => namen.includes(werkzeug.name)) || ['Sonstige'])[0];
	}
	function passt(werkzeug) {
		const { recht, wirkung: wirkungen } = zustand.filter;
		if (recht.size && !recht.has(RECHTE[werkzeug.scope])) return false;
		if (wirkungen.size && !wirkungen.has(wirkung(werkzeug))) return false;
		const suche = zustand.suche.trim().toLowerCase();
		if (!suche) return true;
		const text = [werkzeug.name, werkzeug.title, werkzeug.description, ...Object.keys(werkzeug.inputSchema.properties || {})].join(' ').toLowerCase();
		return suche.split(/\s+/).every((wort) => text.includes(wort));
	}
	function typ(schema) {
		const namen = { string: 'Text', integer: 'Ganzzahl', number: 'Zahl', boolean: 'ja/nein', object: 'Objekt' };
		const art = Array.isArray(schema.type) ? schema.type.filter((t) => t !== 'null')[0] : schema.type;
		if (art === 'array') return 'Liste' + (schema.items ? ' (' + typ(schema.items) + ')' : '');
		return namen[art] || art || 'beliebig';
	}
	function werte(schema) {
		const teile = [];
		if (schema.enum) teile.push('Werte: ' + schema.enum.join(', '));
		if (schema.minimum !== undefined || schema.maximum !== undefined) teile.push('Bereich: ' + (schema.minimum ?? '…') + ' bis ' + (schema.maximum ?? '…'));
		if (schema.maxLength !== undefined) teile.push('höchstens ' + schema.maxLength + ' Zeichen');
		if (schema.default !== undefined) teile.push('Standard: ' + JSON.stringify(schema.default));
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
			if (offen) zustand.offeneWerkzeuge.delete(werkzeug.name);
			else zustand.offeneWerkzeuge.add(werkzeug.name);
			zeichne();
		};
		if (imTabelle) return el('button', { type: 'button', class: 'anw-tool-kopf', 'aria-expanded': String(offen), onclick: umschalten }, [el('span', { class: 'anw-pfeil', text: '▸', 'aria-hidden': 'true' }), el('span', { class: 'anw-name', text: werkzeug.name })]);
		return el('button', { type: 'button', class: 'anw-tool-kopf', 'aria-expanded': String(offen), onclick: umschalten }, [
			el('span', { class: 'anw-pfeil', text: '▸', 'aria-hidden': 'true' }),
			el('span', { class: 'anw-name', text: werkzeug.name }),
			el('span', { class: 'anw-titel', text: werkzeug.title }),
			el('span', { class: 'anw-merkmale' }, [el('span', { class: 'anw-wort', text: RECHTE[werkzeug.scope] }), el('span', { class: 'anw-wort' + (wirkung(werkzeug) === 'löscht' ? ' loescht' : ''), text: wirkung(werkzeug) })]),
		]);
	}
	function gruppiert(liste) {
		if (zustand.anzeige.gruppe === 'keine') return [[null, liste]];
		const schluessel = zustand.anzeige.gruppe === 'recht' ? (werkzeug) => RECHTE[werkzeug.scope] : bereich;
		const reihenfolge = zustand.anzeige.gruppe === 'recht' ? Object.values(RECHTE) : BEREICHE.map(([name]) => name).concat('Sonstige');
		return reihenfolge.map((name) => [name, liste.filter((werkzeug) => schluessel(werkzeug) === name)]).filter(([, gruppe]) => gruppe.length);
	}
	function filterPanel() {
		const kaestchen = (art, wert) => el('label', {}, [el('input', { type: 'checkbox', checked: zustand.filter[art].has(wert), onchange: (event) => { event.target.checked ? zustand.filter[art].add(wert) : zustand.filter[art].delete(wert); zeichne(); } }), wert]);
		return el('div', { class: 'anw-panel', id: 'panel-filter', hidden: zustand.panel !== 'filter' }, [
			el('fieldset', {}, [el('legend', { text: 'Recht' }), Object.values(RECHTE).map((wert) => kaestchen('recht', wert))]),
			el('fieldset', {}, [el('legend', { text: 'Wirkung' }), ['liest nur', 'ändert', 'löscht'].map((wert) => kaestchen('wirkung', wert))]),
		]);
	}
	function anzeigePanel() {
		const optionen = [['bereich', 'Bereich'], ['recht', 'Recht'], ['keine', 'Ohne Gruppen']];
		return el('div', { class: 'anw-panel', id: 'panel-anzeige', hidden: zustand.panel !== 'anzeige' }, [
			el('fieldset', {}, [el('legend', { text: 'Gruppieren nach' }), optionen.map(([wert, name]) => el('label', {}, [el('input', { type: 'radio', name: 'gruppe', checked: zustand.anzeige.gruppe === wert, onchange: () => { zustand.anzeige.gruppe = wert; speichereAnzeige(); zeichne(); } }), name]))]),
		]);
	}
	function werkzeuge(unter) {
		if (unter) zustand.offeneWerkzeuge.add(unter);
		const zaehle = (recht) => DATEN.tools.filter((werkzeug) => werkzeug.scope === recht).length;
		const treffer = DATEN.tools.filter(passt);
		const aktivFilter = [...zustand.filter.recht].map((wert) => ['recht', wert]).concat([...zustand.filter.wirkung].map((wert) => ['wirkung', wert]));
		const panelKnopf = (id, name) => el('div', { class: 'anw-aufklapp' }, [
			el('button', { type: 'button', class: 'knopf', 'aria-expanded': String(zustand.panel === id), 'aria-controls': 'panel-' + id, onclick: () => { zustand.panel = zustand.panel === id ? null : id; zeichne(); } }, [name, ' ▾']),
			id === 'filter' ? filterPanel() : anzeigePanel(),
		]);
		const suchfeld = el('input', { type: 'search', id: 'anw-suche', placeholder: 'Werkzeug, Angabe oder Stichwort', 'aria-label': 'Werkzeuge durchsuchen', value: zustand.suche });
		suchfeld.addEventListener('input', () => {
			zustand.suche = suchfeld.value;
			zeichne();
			requestAnimationFrame(() => { const feld = document.getElementById('anw-suche'); feld.focus(); feld.setSelectionRange(feld.value.length, feld.value.length); });
		});
		const ansicht = el('div', { class: 'anw-umschalter anw-ansicht', role: 'group', 'aria-label': 'Ansicht' }, [['liste', 'Liste'], ['tabelle', 'Tabelle']].map(([wert, name]) => el('button', { type: 'button', 'aria-pressed': String(zustand.anzeige.ansicht === wert), text: name, onclick: () => { zustand.anzeige.ansicht = wert; speichereAnzeige(); zeichne(); } })));
		const ergebnisse = [];
		if (treffer.length === 0) {
			ergebnisse.push(el('div', { class: 'leer' }, [icon('lupe'), el('p', { text: `Kein Werkzeug passt zu „${zustand.suche}“${aktivFilter.length ? ' und den gewählten Filtern' : ''}.` }), el('button', { type: 'button', class: 'knopf', text: 'Suche und Filter zurücksetzen', onclick: () => { zustand.suche = ''; zustand.filter.recht.clear(); zustand.filter.wirkung.clear(); zeichne(); } })]));
		} else if (zustand.anzeige.ansicht === 'liste') {
			for (const [name, gruppe] of gruppiert(treffer)) {
				if (name) ergebnisse.push(el('h3', { class: 'gruppe-titel', text: name + ' · ' + gruppe.length }));
				ergebnisse.push(el('ul', { class: 'anw-tools' }, gruppe.map((werkzeug) => {
					const offen = zustand.offeneWerkzeuge.has(werkzeug.name);
					return el('li', { id: 'werkzeug-' + werkzeug.name }, [werkzeugKopf(werkzeug, offen, false), offen ? werkzeugDetail(werkzeug) : null]);
				})));
			}
		} else {
			const zeilen = [];
			for (const [name, gruppe] of gruppiert(treffer)) {
				if (name) zeilen.push(el('tr', {}, el('th', { colspan: '5', scope: 'colgroup', class: 'gruppe-titel', text: name + ' · ' + gruppe.length })));
				for (const werkzeug of gruppe) {
					const offen = zustand.offeneWerkzeuge.has(werkzeug.name);
					zeilen.push(el('tr', { class: offen ? 'offen' : null, id: 'werkzeug-' + werkzeug.name }, [
						el('td', { 'data-spalte': 'Werkzeug' }, werkzeugKopf(werkzeug, offen, true)),
						el('td', { 'data-spalte': 'Titel', text: werkzeug.title }),
						el('td', { 'data-spalte': 'Recht', text: RECHTE[werkzeug.scope] }),
						el('td', { 'data-spalte': 'Wirkung', text: wirkung(werkzeug) }),
						el('td', { class: 'zahl', 'data-spalte': 'Angaben', text: String(Object.keys(werkzeug.inputSchema.properties || {}).length) }),
					]));
					if (offen) zeilen.push(el('tr', {}, el('td', { colspan: '5', class: 'anw-detailzelle' }, werkzeugDetail(werkzeug))));
				}
			}
			ergebnisse.push(el('table', { class: 'anw-tabelle' }, [el('thead', {}, el('tr', {}, ['Werkzeug', 'Titel', 'Recht', 'Wirkung', 'Angaben'].map((titel) => el('th', { scope: 'col', text: titel })))), el('tbody', {}, zeilen)]));
		}
		if (unter) requestAnimationFrame(() => document.getElementById('werkzeug-' + unter)?.scrollIntoView({ block: 'start' }));
		return [
			el('p', { class: 'anw-summe' }, [`${DATEN.tools.length} Werkzeuge: ${zaehle('blog:read')} zum Lesen, ${zaehle('blog:write')} zum Schreiben, ${zaehle('blog:admin')} für Einstellungen. `, 'Ein Agent sieht nur die, für die sein Zugang das Recht hat.', ' ', info('Die Rechte vergibst du unter „Zugänge für Agenten“. Veröffentlichen kann kein Werkzeug — das bleibt dir vorbehalten.')]),
			el('div', { class: 'anw-leiste' }, [el('label', { class: 'anw-suche' }, [icon('lupe'), suchfeld]), panelKnopf('filter', 'Filter'), panelKnopf('anzeige', 'Anzeige'), ansicht]),
			el('div', { class: 'anw-chips' }, [
				aktivFilter.map(([art, wert]) => el('span', { class: 'anw-chip' }, [wert, el('button', { type: 'button', 'aria-label': 'Filter ' + wert + ' entfernen', text: '✕', onclick: () => { zustand.filter[art].delete(wert); zeichne(); } })])),
				el('span', { class: 'rechts', text: (zustand.suche || aktivFilter.length ? treffer.length + ' von ' + DATEN.tools.length + ' · ' : '') + ({ bereich: 'Gruppiert nach Bereich', recht: 'Gruppiert nach Recht', keine: 'Ohne Gruppen' })[zustand.anzeige.gruppe] }),
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
		details.addEventListener('toggle', () => (details.open ? zustand.offeneBloecke.add(id) : zustand.offeneBloecke.delete(id)));
		return details;
	}
	function ablaeufe(unter) {
		if (unter) zustand.offeneBloecke.add(unter);
		if (unter) requestAnimationFrame(() => document.getElementById('block-' + unter)?.scrollIntoView({ block: 'start' }));
		const texte = DATEN.texts;
		return [
			el('p', { class: 'anw-lead', text: 'Diese Texte stehen im Code und ändern sich nur mit einem Deploy. Hier liest du nach, was ein Agent wann bekommt.' }),
			block('server', 'Server-Anweisung', 'beim Verbinden, an jeden Agenten', textBlock(texte.instructions), 'app/mcp/instructions.md'),
			block('prompts', 'Prompts (' + DATEN.prompts.length + ')', 'Kurzbefehle, die einen Lauf starten, z. B. als Slash-Befehl in Claude Code', el('div', {}, DATEN.prompts.map((prompt) => el('div', { class: 'anw-prompt' }, [el('h4', {}, [prompt.title, el('code', { text: prompt.name })]), el('p', { class: 'anw-hinweise', text: prompt.description }), textBlock(prompt.text)]))), 'app/src/Mcp/Prompts.php'),
			block('geplant', 'Workflow: geplanter Artikel', 'im Briefing, wenn weder Auftrag noch zurückgegebener Entwurf warten', textBlock(texte['workflow-scheduled']), 'app/mcp/workflow-scheduled.md'),
			block('queue', 'Workflow: Auftrag aus der Queue', 'im Briefing, wenn ein Thema in der Queue liegt', textBlock(texte['workflow-queue']), 'app/mcp/workflow-queue.md'),
			block('zurueck', 'Workflow: zurückgegebener Entwurf', 'im Briefing, wenn du einen Entwurf zurückgegeben hast', textBlock(texte['workflow-returned']), 'app/mcp/workflow-returned.md'),
			block('style', 'Style-Guide', 'über get_style_guide, vor dem ersten Schreiben eines Laufs', textBlock(texte['style-guide']), 'app/mcp/style-guide.md'),
		];
	}

	// ---------- Zeichnen und Entwurfsleiste ----------
	function zeichne() {
		const { reiter, unter } = ziel();
		zustand.ansicht = reiter + (unter && unter !== '' && (unter === 'bearbeiten' || unter === 'verlauf') ? '/' + unter : '');
		if (reiter === 'menue') {
			if (box.open) box.close();
			requestAnimationFrame(() => { if (!menue.matches(':popover-open')) menue.showPopover(); });
		} else {
			if (menue.matches(':popover-open')) menue.hidePopover();
			const scroll = box.querySelector('.dlg-rumpf')?.scrollTop || 0;
			const gleich = box.dataset.ansicht === zustand.ansicht;
			zeichneDialog(reiter, unter);
			box.dataset.ansicht = zustand.ansicht;
			if (gleich) box.querySelector('.dlg-rumpf').scrollTop = scroll;
		}
		zeichneDummyLeiste();
	}
	function zeichneDummyLeiste() {
		const auswahl = document.getElementById('d-ansicht');
		const wert = box && box.open ? zustand.ansicht : 'menue';
		if ([...auswahl.options].some((option) => option.value === wert)) auswahl.value = wert;
		document.getElementById('d-wofuer').textContent = WOFUER[wert] || WOFUER[wert.split('/')[0]] || '';
	}
	function zeichneStufe() {
		const breite = window.innerWidth;
		const stufe = breite <= 736 ? 'Handy' : breite <= 992 ? 'Tablet' : 'Desktop';
		document.getElementById('d-stufe').textContent = 'Stufe: ' + stufe + ' (' + breite + ' px)';
	}

	document.addEventListener('DOMContentLoaded', () => {
		baueKopf();
		baueDialog();
		document.getElementById('d-ansicht').addEventListener('change', (event) => geheZu(event.target.value));
		document.getElementById('d-zustand').addEventListener('change', (event) => { dummy.zustand = event.target.value; if (box.open) zeichne(); });
		document.getElementById('d-thema').addEventListener('change', (event) => {
			document.documentElement.classList.toggle('hell', event.target.value === 'hell');
			document.documentElement.classList.toggle('dunkel', event.target.value === 'dunkel');
		});
		document.getElementById('d-werkzeuge').addEventListener('change', (event) => document.body.classList.toggle('mit-werkzeug', event.target.checked));
		document.addEventListener('click', (event) => {
			if (zustand.panel && !event.target.closest('.anw-aufklapp')) { zustand.panel = null; zeichne(); }
		});
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && zustand.panel) { event.preventDefault(); zustand.panel = null; zeichne(); }
		});
		window.addEventListener('resize', zeichneStufe);
		zeichneStufe();
		const parameter = new URLSearchParams(location.search);
		if (parameter.get('zustand')) { dummy.zustand = parameter.get('zustand'); document.getElementById('d-zustand').value = dummy.zustand; }
		if (parameter.get('thema')) { document.getElementById('d-thema').value = parameter.get('thema'); document.getElementById('d-thema').dispatchEvent(new Event('change')); }
		if (parameter.get('werkzeuge')) { document.getElementById('d-werkzeuge').checked = true; document.body.classList.add('mit-werkzeug'); }
		if (parameter.get('darstellung') === 'tabelle') zustand.anzeige.ansicht = 'tabelle';
		if (!location.hash) history.replaceState(null, '', '#menue');
		zeichne();
	});
})();
