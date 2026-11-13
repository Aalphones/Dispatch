// ==========================================================================
// Teil 1 — Vorlage für app/theme/bausteine.js; bildansicht() ist Vorlage für setUpLightbox in artikel.js.
// Teil 2 (unten) ist nur die Entwurfsleiste.
// Alle Funktionen arbeiten nur auf dem, was der Renderer ausliefert, und setzen Maße
// ausschließlich über element.style (die CSP verbietet style-Attribute im HTML).
// ==========================================================================

const schmal = () => matchMedia('(max-width: 46rem)').matches;

function neu(tag, className, text) {
	const node = document.createElement(tag);
	if (className) node.className = className;
	if (text !== undefined) node.textContent = text;
	return node;
}

function knopf(className, text, label) {
	const button = neu('button', className, text);
	button.type = 'button';
	if (label) button.setAttribute('aria-label', label);
	return button;
}

// ---- Karussell: ein Bild in voller Breite, Pfeile im Bild, Punkte darunter ----
function karussell(figure) {
	const spur = figure.querySelector('.spur');
	const fotos = [...spur.querySelectorAll('.foto')];
	if (fotos.length < 2) return;
	let jetzt = -1;
	const geheZu = (index) => {
		const ziel = Math.max(0, Math.min(fotos.length - 1, index));
		spur.scrollTo({ left: fotos[ziel].offsetLeft - fotos[0].offsetLeft });
	};
	const buehne = neu('div', 'karussell-buehne');
	spur.before(buehne);
	const zurueck = knopf('karussell-pfeil zurueck', '‹', 'Vorheriges Bild');
	const vor = knopf('karussell-pfeil vor', '›', 'Nächstes Bild');
	zurueck.addEventListener('click', () => geheZu(jetzt - 1));
	vor.addEventListener('click', () => geheZu(jetzt + 1));
	const zahl = neu('span', 'karussell-zahl');
	buehne.append(spur, zurueck, vor, zahl);
	const punkte = neu('div', 'karussell-punkte');
	const punktKnoepfe = fotos.map((foto, index) => {
		const punkt = knopf('', undefined, 'Bild ' + (index + 1) + ' zeigen');
		punkt.addEventListener('click', () => geheZu(index));
		punkte.append(punkt);
		return punkt;
	});
	const text = neu('p', 'karussell-text');
	text.setAttribute('aria-live', 'polite');
	buehne.after(punkte, text);
	spur.tabIndex = 0;
	spur.setAttribute('aria-label', 'Bildfolge, mit Pfeiltasten blättern');
	const zeige = (index) => {
		if (index === jetzt) return;
		jetzt = index;
		zahl.textContent = (index + 1) + ' / ' + fotos.length;
		text.textContent = (fotos[index].dataset.unterschrift || '').split(' · ')[0];
		zurueck.disabled = index === 0;
		vor.disabled = index === fotos.length - 1;
		punktKnoepfe.forEach((punkt, i) => punkt.setAttribute('aria-current', String(i === index)));
	};
	let rahmen = 0;
	spur.addEventListener('scroll', () => {
		cancelAnimationFrame(rahmen);
		rahmen = requestAnimationFrame(() => zeige(Math.min(fotos.length - 1, Math.round(spur.scrollLeft / spur.clientWidth))));
	});
	zeige(0);
	figure.classList.add('bereit');
}

// ---- Bildstreifen: Antippen zieht auf, zweites Antippen öffnet die Bildansicht ----
function streifen(figure) {
	figure.addEventListener('click', (event) => {
		const foto = event.target.closest('.foto');
		if (!foto || foto.classList.contains('offen') || !matchMedia('(hover: none)').matches) return;
		event.stopPropagation();
		figure.querySelectorAll('.foto.offen').forEach((other) => other.classList.remove('offen'));
		foto.classList.add('offen');
	}, true);
}

// ---- Vorher/Nachher: Schieberegler über die ganze Fläche (ziehen, antippen, Pfeiltasten) ----
function vergleich(figure) {
	const flaeche = figure.querySelector('.vergleich-flaeche');
	const marken = [...figure.querySelectorAll('.vergleich-marke')].map((marke) => marke.textContent);
	const regler = neu('input', 'vergleich-regler');
	regler.type = 'range';
	regler.min = '0';
	regler.max = '100';
	regler.value = '50';
	regler.setAttribute('aria-label', 'Vergleich verschieben: links ' + marken[0] + ', rechts ' + marken[1]);
	regler.addEventListener('input', () => flaeche.style.setProperty('--pos', regler.value + '%'));
	const griff = neu('div', 'vergleich-griff');
	griff.append(neu('span', 'vergleich-knauf'));
	flaeche.append(griff, regler);
	figure.classList.add('bereit');
}

// ---- Bild mit Erklärpunkten ----
function erklaerbild(figure) {
	const flaeche = figure.querySelector('.erklaerbild-flaeche');
	const punkte = [...figure.querySelectorAll('.erklaerbild-liste li')];
	let karte = null;
	const schliessen = () => {
		if (karte) karte.remove();
		karte = null;
		figure.querySelectorAll('.erklaerbild-punkt').forEach((punkt) => punkt.setAttribute('aria-expanded', 'false'));
		punkte.forEach((li) => li.classList.remove('aktiv'));
	};
	const oeffnen = (index, punkt) => {
		schliessen();
		figure.classList.add('benutzt');
		punkt.setAttribute('aria-expanded', 'true');
		punkte[index].classList.add('aktiv');
		if (schmal()) {
			punkte[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
			return;
		}
		const x = parseFloat(punkte[index].dataset.x);
		const y = parseFloat(punkte[index].dataset.y);
		karte = neu('div', 'erklaerbild-karte');
		karte.setAttribute('role', 'note');
		karte.append(...[...punkte[index].childNodes].map((node) => node.cloneNode(true)));
		const zu = knopf('zu', '×', 'Schließen');
		zu.addEventListener('click', () => { schliessen(); punkt.focus(); });
		karte.append(zu);
		karte.style.top = 'calc(' + y + '% + 1.3rem)';
		if (x < 60) karte.style.left = 'max(.5rem, calc(' + x + '% - 1rem))';
		else karte.style.right = 'max(.5rem, calc(' + (100 - x) + '% - 1rem))';
		if (y > 60) { karte.style.top = 'auto'; karte.style.bottom = 'calc(' + (100 - y) + '% + 1.3rem)'; }
		flaeche.append(karte);
	};
	punkte.forEach((li, index) => {
		const x = parseFloat(li.dataset.x);
		const y = parseFloat(li.dataset.y);
		if (!(x >= 0 && x <= 100 && y >= 0 && y <= 100)) return;
		const punkt = knopf('erklaerbild-punkt', String(index + 1), 'Punkt ' + (index + 1) + ': ' + (li.querySelector('b') || li).textContent);
		punkt.style.left = x + '%';
		punkt.style.top = y + '%';
		punkt.setAttribute('aria-expanded', 'false');
		punkt.addEventListener('click', (event) => {
			event.stopPropagation();
			if (punkt.getAttribute('aria-expanded') === 'true') schliessen();
			else oeffnen(index, punkt);
		});
		li.addEventListener('click', (event) => { event.stopPropagation(); oeffnen(index, punkt); });
		flaeche.append(punkt);
	});
	document.addEventListener('click', (event) => { if (karte && !karte.contains(event.target)) schliessen(); });
	document.addEventListener('keydown', (event) => { if (event.key === 'Escape') schliessen(); });
}

// ---- Bildansicht für alle Bilder und Galerien (Vorlage für setUpLightbox in artikel.js) ----
// Kopf mit Zähler und Schließen, Bild mit Pfeilen, Unterschrift, Vorschaubilder-Leiste.
// Tasten: ←/→ blättern, Pos1/Ende springen, Esc schließt (nativ), Tab läuft durch die Knöpfe.
function bildansicht() {
	let dialog = null;
	let liste = [];
	let stelle = 0;
	let ausloeser = null;
	const teile = {};
	const quelle = (foto) => { const img = foto.querySelector('img'); return img.currentSrc || img.src; };
	const zeige = () => {
		const foto = liste[stelle];
		const bild = document.createElement('img');
		bild.src = quelle(foto); // artikel.js: largestSource(picture)
		bild.alt = foto.querySelector('img').alt;
		teile.bild.replaceWith(bild);
		teile.bild = bild;
		const unterschrift = foto.dataset.unterschrift || (foto.closest('figure').querySelector('figcaption') || {}).textContent || '';
		teile.text.textContent = unterschrift.trim();
		teile.zahl.textContent = liste.length > 1 ? (stelle + 1) + ' / ' + liste.length : '';
		teile.zurueck.disabled = stelle === 0;
		teile.vor.disabled = stelle === liste.length - 1;
		[...teile.leiste.children].forEach((vorschau, i) => vorschau.setAttribute('aria-current', String(i === stelle)));
		if (teile.leiste.children[stelle]) teile.leiste.children[stelle].scrollIntoView({ block: 'nearest', inline: 'center' });
	};
	const geheZu = (ziel) => {
		if (ziel < 0 || ziel >= liste.length || ziel === stelle) return;
		stelle = ziel;
		zeige();
	};
	const bauen = () => {
		dialog = neu('dialog', 'lightbox');
		dialog.setAttribute('aria-label', 'Bildansicht');
		const kopf = neu('div', 'lightbox-kopf');
		teile.zahl = neu('span', 'lightbox-zahl');
		teile.zu = knopf('zu', '×', 'Schließen');
		kopf.append(teile.zahl, teile.zu);
		const buehne = neu('div', 'lightbox-buehne');
		teile.bild = document.createElement('img');
		teile.zurueck = knopf('blatt zurueck', '‹', 'Vorheriges Bild');
		teile.vor = knopf('blatt vor', '›', 'Nächstes Bild');
		buehne.append(teile.bild, teile.zurueck, teile.vor);
		teile.text = neu('p', 'lightbox-text');
		teile.leiste = neu('div', 'lightbox-leiste');
		teile.zu.addEventListener('click', () => dialog.close());
		teile.zurueck.addEventListener('click', () => geheZu(stelle - 1));
		teile.vor.addEventListener('click', () => geheZu(stelle + 1));
		dialog.addEventListener('click', (event) => { if (event.target === dialog || event.target === buehne) dialog.close(); });
		dialog.addEventListener('keydown', (event) => {
			const ziel = { ArrowLeft: stelle - 1, ArrowRight: stelle + 1, Home: 0, End: liste.length - 1 }[event.key];
			if (ziel === undefined) return;
			event.preventDefault();
			geheZu(ziel);
		});
		let startX = null;
		buehne.addEventListener('pointerdown', (event) => { startX = event.clientX; });
		buehne.addEventListener('pointerup', (event) => {
			if (startX === null) return;
			const weg = event.clientX - startX;
			startX = null;
			if (Math.abs(weg) > 50) geheZu(stelle + (weg < 0 ? 1 : -1));
		});
		dialog.addEventListener('close', () => { if (ausloeser) ausloeser.focus(); });
		dialog.append(kopf, buehne, teile.text, teile.leiste);
		document.body.append(dialog);
	};
	const oeffnen = (foto) => {
		const galerie = foto.closest('.galerie');
		liste = galerie ? [...galerie.querySelectorAll('.foto')] : [foto];
		stelle = liste.indexOf(foto);
		ausloeser = foto;
		if (!dialog) bauen();
		dialog.classList.toggle('einzeln', liste.length < 2);
		teile.leiste.replaceChildren(...(liste.length < 2 ? [] : liste.map((eintrag, i) => {
			const vorschau = knopf('', undefined, 'Bild ' + (i + 1) + ' von ' + liste.length);
			const klein = document.createElement('img');
			klein.src = quelle(eintrag);
			klein.alt = '';
			vorschau.append(klein);
			vorschau.addEventListener('click', () => geheZu(i));
			return vorschau;
		})));
		dialog.showModal();
		zeige();
	};
	// Tastatur: jedes vergrößerbare Bild ist ein Knopf.
	document.querySelectorAll('.abb .foto, .galerie .foto').forEach((foto) => {
		const galerie = foto.closest('.galerie');
		const fotos = galerie ? [...galerie.querySelectorAll('.foto')] : [foto];
		foto.tabIndex = 0;
		foto.setAttribute('role', 'button');
		foto.setAttribute('aria-label', fotos.length > 1 ? 'Bild ' + (fotos.indexOf(foto) + 1) + ' von ' + fotos.length + ' vergrößern' : 'Bild vergrößern');
	});
	document.addEventListener('click', (event) => {
		const foto = event.target.closest('.abb .foto, .galerie .foto');
		if (foto && foto.querySelector('img')) oeffnen(foto);
	});
	document.addEventListener('keydown', (event) => {
		const foto = event.target.closest && event.target.closest('.abb .foto, .galerie .foto');
		if (foto && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); oeffnen(foto); }
	});
}

function bausteineStarten() {
	document.querySelectorAll('.galerie[data-variante="karussell"]').forEach(karussell);
	document.querySelectorAll('.galerie[data-variante="streifen"]').forEach(streifen);
	document.querySelectorAll('.vergleich').forEach(vergleich);
	document.querySelectorAll('.erklaerbild').forEach(erklaerbild);
	bildansicht();
}

// ==========================================================================
// Teil 2 — NUR ENTWURF: Entwurfsleiste. Nicht übernehmen.
// Speicher abgesichert: in eingebetteten Ansichten (Sandbox) wirft sessionStorage.
// ==========================================================================
(function entwurf() {
	const html = document.documentElement;
	const thema = document.getElementById('d-thema');
	const menge = document.getElementById('d-menge');
	const ohneJs = document.getElementById('d-ohnejs');
	const syntax = document.getElementById('d-syntax');
	const stufe = document.getElementById('d-stufe');
	let gespeichert = {};
	try { gespeichert = JSON.parse(sessionStorage.getItem('d-bausteine') || '{}'); } catch (fehler) { /* kein Speicher */ }
	const speichern = () => {
		try { sessionStorage.setItem('d-bausteine', JSON.stringify({ menge: menge.value, ohneJs: ohneJs.checked, syntax: syntax.checked, thema: thema.value })); } catch (fehler) { /* kein Speicher */ }
	};
	const params = new URLSearchParams(location.search);
	thema.value = params.get('thema') || gespeichert.thema || '';
	menge.value = params.get('menge') || gespeichert.menge || '';
	ohneJs.checked = params.has('ohnejs') || !!gespeichert.ohneJs;
	syntax.checked = params.has('syntax') || !!gespeichert.syntax;

	const setzeThema = () => {
		html.classList.toggle('hell', thema.value === 'hell');
		html.classList.toggle('dunkel', thema.value === 'dunkel');
	};
	setzeThema();
	thema.addEventListener('change', () => { setzeThema(); speichern(); });
	document.body.classList.toggle('d-syntax-an', syntax.checked);
	syntax.addEventListener('change', () => { document.body.classList.toggle('d-syntax-an', syntax.checked); speichern(); });
	menge.addEventListener('change', () => { speichern(); location.reload(); });
	ohneJs.addEventListener('change', () => { speichern(); location.reload(); });

	if (menge.value === '2') {
		document.querySelectorAll('.galerie').forEach((galerie) => {
			const fotos = [...galerie.querySelectorAll('.foto')];
			fotos.slice(2).forEach((foto) => foto.remove());
			galerie.dataset.anzahl = '2';
		});
	}
	const zeigeStufe = () => {
		const breite = innerWidth;
		stufe.textContent = 'Stufe: ' + (breite <= 736 ? 'Handy (bis 46rem)' : breite <= 992 ? 'Tablet (bis 62rem)' : 'Desktop') + ' · ' + breite + ' px';
	};
	zeigeStufe();
	addEventListener('resize', zeigeStufe);
	if (!ohneJs.checked) bausteineStarten();
	// Prüfhilfe: ?klick=<CSS-Selektor> klickt nach dem Start ein Element an (z. B. ein Bild für die Bildansicht).
	const klick = params.get('klick') && document.querySelector(params.get('klick'));
	if (klick) klick.click();
})();
