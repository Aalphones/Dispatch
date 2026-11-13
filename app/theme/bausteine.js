// Bausteine für Artikel (Karussell, Bildstreifen, Vorher/Nachher, Erklärpunkte). Maße nur über element.style (CSP).

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
	if (fotos.length < 2 || figure.classList.contains('bereit')) return;
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
	if (figure.dataset.gestartet) return;
	figure.dataset.gestartet = '1';
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
	if (figure.classList.contains('bereit')) return;
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
	if (figure.dataset.gestartet) return;
	figure.dataset.gestartet = '1';
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

// Startet alle Bausteine unter root; bereits gestartete werden übersprungen.
export function start(root = document) {
	root.querySelectorAll('.galerie[data-variante="karussell"]').forEach(karussell);
	root.querySelectorAll('.galerie[data-variante="streifen"]').forEach(streifen);
	root.querySelectorAll('.vergleich').forEach(vergleich);
	root.querySelectorAll('.erklaerbild').forEach(erklaerbild);
}
