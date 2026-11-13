// Interaktive Diagramme (Linie, Heatmap, Kalender, Sankey, Baumkarte, Blasen, Karte). Der Server hat alles vorgerechnet;
// hier werden nur Attribute und Klassen umgeschaltet. Maße nur über element.style (CSP).

const SVG_NS = 'http://www.w3.org/2000/svg';
const PFEILE = { ArrowLeft: ['h', -1], ArrowRight: ['h', 1], ArrowUp: ['v', -1], ArrowDown: ['v', 1] };
const HILFEN = {
	linie: 'Zeiger oder Pfeiltasten lesen Werte ab.',
	heatmap: 'Zelle antippen oder mit Pfeiltasten wählen.',
	kalender: 'Tag antippen oder mit Pfeiltasten wählen.',
	sankey: 'Knoten oder Band wählen; Esc hebt auf.',
	baumkarte: 'Gruppe wählen zum Hineinzoomen; Esc geht zurück.',
	blasen: 'Blase wählen für Werte; Chips filtern.',
	karte: 'Region wählen für Einzelheiten; Esc hebt auf.',
};

function neu(tag, className, text) {
	const node = document.createElement(tag);
	node.className = className;
	if (text !== undefined) node.textContent = text;
	return node;
}

const sichtbar = (knoten) => !knoten.closest('[hidden]');

// Wertefeld: schwebender Hinweis am Ziel (nur für Sehende) und Statuszeile darunter (Screenreader, Tippen).
function wertefeld(figure, typ) {
	const flaeche = figure.querySelector('.diagramm-flaeche');
	const hinweis = neu('div', 'diagramm-hinweis');
	const status = neu('p', 'diagramm-detail', HILFEN[typ]);
	hinweis.hidden = true;
	hinweis.setAttribute('aria-hidden', 'true');
	status.setAttribute('role', 'status');
	flaeche.append(hinweis);
	flaeche.after(status);
	return {
		zeige(text, punkt, imStatus = true) {
			const rahmen = flaeche.getBoundingClientRect();
			hinweis.textContent = text;
			hinweis.hidden = false;
			const halb = hinweis.offsetWidth / 2;
			hinweis.style.left = Math.max(halb, Math.min(flaeche.scrollWidth - halb, punkt.x - rahmen.left + flaeche.scrollLeft)) + 'px';
			hinweis.style.top = Math.max(0, punkt.y - rahmen.top - hinweis.offsetHeight - 6) + 'px';
			if (imStatus) status.textContent = text;
		},
		verberge: () => { hinweis.hidden = true; },
		setzeStatus: (text) => { status.textContent = text; },
	};
}

const mitte = (knoten) => {
	const rahmen = knoten.getBoundingClientRect();
	return { x: rahmen.left + rahmen.width / 2, y: rahmen.top };
};

// Natives Titel-Tooltip stört neben dem eigenen Hinweis: Text nach data-hinweis, nur die Figurenüberschrift bleibt.
function titelUmziehen(svg) {
	svg.querySelectorAll('title').forEach((titel) => {
		if (titel.parentNode === svg) return;
		titel.parentNode.dataset.hinweis = titel.textContent;
		titel.remove();
	});
}

function hinweise(figure, feld, selector, imStatus = true) {
	const zeige = (event) => {
		const marke = event.target.closest(selector);
		if (marke) feld.zeige(marke.dataset.hinweis, mitte(marke), imStatus);
	};
	figure.addEventListener('pointerover', zeige);
	figure.addEventListener('focusin', zeige);
	figure.addEventListener('pointerout', feld.verberge);
	figure.addEventListener('focusout', feld.verberge);
}

// Pfeiltasten wandern über die Marken; nur eine trägt tabindex 0. schritt: um wie viele Marken ein Pfeil springt.
function wandern(figure, selector, rolle, schritt = { h: 1, v: 1 }) {
	const marken = () => [...figure.querySelectorAll(selector)].filter(sichtbar);
	const ordne = (aktiv) => marken().forEach((marke) => marke.setAttribute('tabindex', marke === aktiv ? '0' : '-1'));
	figure.querySelectorAll(selector).forEach((marke) => {
		marke.setAttribute('role', rolle);
		if (marke.dataset.hinweis) marke.setAttribute('aria-label', marke.dataset.hinweis);
	});
	ordne(marken()[0]);
	figure.addEventListener('focusin', (event) => { if (event.target.matches(selector)) ordne(event.target); });
	figure.addEventListener('keydown', (event) => {
		const liste = marken();
		const von = liste.indexOf(event.target);
		if (von < 0) return;
		const pfeil = PFEILE[event.key];
		let ziel = { Home: 0, End: liste.length - 1 }[event.key];
		if (pfeil) ziel = von + pfeil[1] * schritt[pfeil[0]];
		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			event.target.dispatchEvent(new MouseEvent('click', { bubbles: true }));
		}
		if (ziel === undefined || !liste[ziel]) return;
		event.preventDefault();
		liste[ziel].focus();
	});
	return () => ordne(marken().find((marke) => marke.tabIndex === 0) || marken()[0]);
}

// Legendeneinträge werden zu Schaltern; mindestens einer bleibt an. beiAenderung(index, an).
function schalter(figure, beiAenderung) {
	const eintraege = [...figure.querySelectorAll('.diagramm-legende li')];
	eintraege.forEach((eintrag, index) => {
		const knopf = neu('button', '');
		knopf.type = 'button';
		knopf.setAttribute('aria-pressed', 'true');
		knopf.append(...eintrag.childNodes);
		eintrag.append(knopf);
		knopf.addEventListener('click', () => {
			const an = knopf.getAttribute('aria-pressed') !== 'true';
			if (!an && eintraege.filter((andere) => andere.firstChild.getAttribute('aria-pressed') === 'true').length < 2) return;
			knopf.setAttribute('aria-pressed', String(an));
			beiAenderung(index, an);
		});
	});
	return (index) => !eintraege[index] || eintraege[index].firstChild.getAttribute('aria-pressed') === 'true';
}

// ---- Linie: Reihen ein/aus, Fadenkreuz mit Wertefeld ----
function linie(figure, feld) {
	const svg = figure.querySelector('svg');
	const spalten = svg.querySelector('.spalten');
	const reihen = JSON.parse(spalten.dataset.reihen);
	const strich = document.createElementNS(SVG_NS, 'line');
	let jetzt = -1;
	strich.setAttribute('class', 'fadenkreuz');
	strich.setAttribute('y1', spalten.dataset.oben);
	strich.setAttribute('y2', spalten.dataset.unten);
	strich.setAttribute('hidden', '');
	svg.append(strich);
	const zeigeSpalte = (index, punkt) => {
		const spalte = spalten.children[index];
		const werte = JSON.parse(spalte.dataset.werte);
		const zeilen = reihen.map((name, nr) => (an(nr) ? (reihen.length > 1 ? name + ' ' : '') + werte[nr] : '')).filter(Boolean);
		jetzt = index;
		strich.setAttribute('x1', spalte.dataset.x);
		strich.setAttribute('x2', spalte.dataset.x);
		strich.removeAttribute('hidden');
		feld.zeige(spalte.dataset.label + ': ' + zeilen.join(' · '), punkt);
	};
	const an = schalter(figure, (index, ein) => {
		svg.querySelectorAll('g.r' + (index + 1)).forEach((gruppe) => gruppe.toggleAttribute('hidden', !ein));
		if (!strich.hasAttribute('hidden')) zeigeSpalte(jetzt, mitte(strich));
	});
	const amZeiger = (event) => {
		const rahmen = svg.getBoundingClientRect();
		const x = ((event.clientX - rahmen.left) * svg.viewBox.baseVal.width) / rahmen.width;
		const naechste = [...spalten.children].reduce((best, spalte, index) => (Math.abs(spalte.dataset.x - x) < Math.abs(spalten.children[best].dataset.x - x) ? index : best), 0);
		zeigeSpalte(naechste, { x: event.clientX, y: rahmen.top });
	};
	const verberge = () => { strich.setAttribute('hidden', ''); feld.verberge(); };
	svg.setAttribute('tabindex', '0');
	svg.addEventListener('pointermove', amZeiger);
	svg.addEventListener('pointerdown', amZeiger);
	svg.addEventListener('pointerleave', verberge);
	svg.addEventListener('blur', verberge);
	svg.addEventListener('keydown', (event) => {
		const ziel = { ArrowLeft: jetzt - 1, ArrowRight: jetzt + 1, Home: 0, End: spalten.children.length - 1 }[event.key];
		if (event.key === 'Escape') verberge();
		if (ziel === undefined || ziel < 0 || ziel >= spalten.children.length) return;
		event.preventDefault();
		const rahmen = svg.getBoundingClientRect();
		zeigeSpalte(ziel, { x: rahmen.left + (spalten.children[ziel].dataset.x * rahmen.width) / svg.viewBox.baseVal.width, y: rahmen.top });
	});
}

// ---- Sankey: Fluss hervorheben (Vorgänger und Nachfolger), übrige dimmen ----
function sankey(figure, feld) {
	const svg = figure.querySelector('svg');
	const baender = [...svg.querySelectorAll('.band')];
	const knoten = [...svg.querySelectorAll('[data-knoten]')];
	const taste = (teil) => (teil.matches('.band') ? teil : teil.querySelector('.m'));
	let aktiv = null;
	const folge = (kennung, vorwaerts, treffer) => {
		treffer.add(kennung);
		baender.filter((band) => band.dataset[vorwaerts ? 'von' : 'nach'] === kennung).forEach((band) => {
			treffer.add(band);
			folge(band.dataset[vorwaerts ? 'nach' : 'von'], vorwaerts, treffer);
		});
	};
	const waehle = (teil) => {
		if (aktiv) taste(aktiv).setAttribute('aria-pressed', 'false');
		aktiv = teil;
		const treffer = new Set();
		if (teil) {
			taste(teil).setAttribute('aria-pressed', 'true');
			const band = teil.matches('.band');
			folge(band ? teil.dataset.nach : teil.dataset.knoten, true, treffer);
			folge(band ? teil.dataset.von : teil.dataset.knoten, false, treffer);
			treffer.add(teil);
		}
		[...baender, ...knoten].forEach((teilStueck) => teilStueck.classList.toggle('gedimmt', Boolean(teil) && !treffer.has(teilStueck.dataset.knoten || teilStueck)));
	};
	[...baender, ...knoten.map(taste)].forEach((marke) => marke.setAttribute('aria-pressed', 'false'));
	wandern(figure, '.band, [data-knoten] > .m', 'button');
	hinweise(figure, feld, '[data-hinweis]');
	figure.addEventListener('click', (event) => {
		const teil = event.target.closest('[data-knoten], .band');
		waehle(teil === aktiv ? null : teil);
	});
	figure.addEventListener('keydown', (event) => { if (event.key === 'Escape') waehle(null); });
}

// ---- Baumkarte: vorgerechnete zweite Ebene einblenden ----
function baumkarte(figure, feld) {
	const svg = figure.querySelector('svg');
	const uebersicht = svg.querySelector('.ebene-1');
	const ebenen = [...svg.querySelectorAll('g.ebene')];
	const pfad = neu('div', 'diagramm-pfad');
	const zurueck = neu('button', '', '← Zurück');
	const krume = neu('span', '', 'Gesamt');
	zurueck.type = 'button';
	zurueck.hidden = true;
	pfad.append(zurueck, krume);
	figure.querySelector('.diagramm-flaeche').before(pfad);
	let herkunft = null;
	const aktualisiere = wandern(figure, '.gruppe > .m, g.ebene .m', 'img');
	svg.querySelectorAll('.gruppe > .m').forEach((kachel) => kachel.setAttribute('role', 'button'));
	const zeige = (kennung) => {
		const gruppe = kennung && svg.querySelector('.gruppe[data-gruppe="' + kennung + '"]');
		uebersicht.toggleAttribute('hidden', Boolean(kennung));
		ebenen.forEach((ebene) => ebene.toggleAttribute('hidden', ebene.dataset.gruppe !== kennung));
		zurueck.hidden = !kennung;
		if (gruppe) herkunft = gruppe.querySelector('.m');
		krume.textContent = gruppe ? 'Gesamt › ' + herkunft.dataset.hinweis.replace(/: [^:]*$/, '') : 'Gesamt';
		feld.verberge();
		aktualisiere();
		(gruppe ? zurueck : herkunft).focus();
	};
	hinweise(figure, feld, '[data-hinweis]');
	figure.addEventListener('click', (event) => {
		const gruppe = event.target.closest('.gruppe');
		if (gruppe) zeige(gruppe.dataset.gruppe);
	});
	zurueck.addEventListener('click', () => zeige(null));
	figure.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !zurueck.hidden) zeige(null); });
}

// ---- Blasen: Kategorie-Chips blenden Marken (samt Beschriftung) aus ----
function blasen(figure, feld) {
	const svg = figure.querySelector('svg');
	let aktualisiere = null;
	schalter(figure, (index, an) => {
		svg.querySelectorAll('g.r' + (index + 1)).forEach((gruppe) => gruppe.toggleAttribute('hidden', !an));
		aktualisiere();
	});
	aktualisiere = wandern(figure, 'circle.m', 'img');
	hinweise(figure, feld, '[data-hinweis]');
}

// ---- Karte: Region wählen, Einzelheiten in die Statuszeile ----
function karte(figure, feld) {
	let gewaehlt = null;
	const waehle = (region) => {
		if (gewaehlt) {
			gewaehlt.classList.remove('gewaehlt');
			gewaehlt.setAttribute('aria-pressed', 'false');
		}
		gewaehlt = region;
		if (!region) {
			feld.setzeStatus(HILFEN.karte);
			return;
		}
		region.classList.add('gewaehlt');
		region.setAttribute('aria-pressed', 'true');
		feld.setzeStatus(region.dataset.hinweis);
	};
	wandern(figure, 'path[data-region]', 'button');
	figure.querySelectorAll('path[data-region]').forEach((region) => region.setAttribute('aria-pressed', 'false'));
	hinweise(figure, feld, '[data-hinweis]', false);
	figure.addEventListener('click', (event) => {
		const region = event.target.closest('path[data-region]');
		waehle(region === gewaehlt ? null : region);
	});
	figure.addEventListener('keydown', (event) => { if (event.key === 'Escape') waehle(null); });
}

// ---- Heatmap und Kalender: Wert bei Hover, Fokus und Tippen; Zellen per Pfeiltasten ----
function zellen(figure, feld, schritt) {
	wandern(figure, 'g:is(.s0, .s1, .s2, .s3, .s4, .s5) > rect.m', 'img', schritt);
	hinweise(figure, feld, 'rect.m');
}

function start(root = document) {
	root.querySelectorAll('.diagramm[data-interaktiv]').forEach((figure) => {
		if (figure.dataset.gestartet) return;
		figure.dataset.gestartet = 'ja';
		const typ = figure.dataset.typ;
		const svg = figure.querySelector('svg');
		const feld = wertefeld(figure, typ);
		titelUmziehen(svg);
		if (typ === 'linie') linie(figure, feld);
		if (typ === 'sankey') sankey(figure, feld);
		if (typ === 'baumkarte') baumkarte(figure, feld);
		if (typ === 'blasen') blasen(figure, feld);
		if (typ === 'karte') karte(figure, feld);
		if (typ === 'heatmap') zellen(figure, feld, { h: 1, v: Number(figure.dataset.spalten) });
		if (typ === 'kalender') zellen(figure, feld, { h: 7, v: 1 });
	});
}

export { start };
