// Gemeinsame Bausteine der Redaktion: Elemente, Meldung, Dialoge, Symbole, Hilfetext.

const TOAST_MS = 3800;
const TOAST_UNDO_MS = 7000;

const ICONS = {
	person: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
	check: '<path d="M20 6 9 17l-5-5"/>',
	stift: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
	plus: '<path d="M12 5v14M5 12h14"/>',
	herz: '<path d="M19.5 12.6 12 20l-7.5-7.4A5 5 0 1 1 12 6a5 5 0 1 1 7.5 6.6z"/>',
	chart: '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
	schluessel: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/>',
	warn: '<path d="M12 3 2 21h20zM12 10v5M12 18h.01"/>',
	hoch: '<path d="M7 10v11M15 5.9 14 10h5.8a2 2 0 0 1 2 2.3l-1.4 7A2 2 0 0 1 18.4 21H7V10l4.2-7.4A1.9 1.9 0 0 1 15 5.9z"/>',
	runter: '<path d="M17 14V3M9 18.1 10 14H4.2a2 2 0 0 1-2-2.3l1.4-7A2 2 0 0 1 5.6 3H17v11l-4.2 7.4A1.9 1.9 0 0 1 9 18.1z"/>',
	auge: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
	ki: '<path d="M12 3l1.8 4.7L18.5 9.5 13.8 11.3 12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
	uhr: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
	sperre: '<circle cx="12" cy="12" r="9"/><path d="m5.7 5.7 12.6 12.6"/>',
	lupe: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
	zurueck: '<path d="M15 18l-6-6 6-6"/>',
	tuer:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
};

let toastTimer = 0;
let toastBox = null;

/** Macht ein Element. `style` wird nie gesetzt (CSP); `on…` hängt einen Listener an, `text` setzt den Text. */
export function el(tag, attributes = {}, children = []) {
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
	appendChildren(node, children);

	return node;
}

function appendChildren(node, children) {
	for (const child of [children].flat(Infinity)) {
		if (child === false || child === null || child === undefined) {
			continue;
		}
		node.append(child instanceof Node ? child : String(child));
	}
}

/** Symbol aus dem Entwurf als SVG-Element; die Pfade sind feste Texte dieser Datei. */
export function icon(name) {
	const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	svg.setAttribute('class', 'ico');
	svg.setAttribute('viewBox', '0 0 24 24');
	svg.setAttribute('aria-hidden', 'true');
	svg.innerHTML = ICONS[name] || '';

	return svg;
}

/** Kurze Meldung unten (auf dem Handy oben); mit `undo` erscheint „Rückgängig“. */
export function toast(text, undo) {
	if (toastBox === null) {
		const message = el('span', { id: 'toast-text' });
		const button = el('button', { type: 'button', id: 'toast-zurueck', hidden: true, text: 'Rückgängig' });
		toastBox = el('div', { class: 'toast', id: 'toast', role: 'status' }, [message, button]);
		document.body.append(toastBox);
	}
	const message = toastBox.querySelector('#toast-text');
	const button = toastBox.querySelector('#toast-zurueck');
	message.textContent = text;
	button.hidden = typeof undo !== 'function';
	button.onclick = () => {
		toastBox.classList.remove('zeigen');
		undo();
	};
	toastBox.classList.add('zeigen');
	clearTimeout(toastTimer);
	toastTimer = setTimeout(() => toastBox.classList.remove('zeigen'), button.hidden ? TOAST_MS : TOAST_UNDO_MS);
}

/**
 * Der Dialog mit dieser Kennung (wird einmal angelegt), neu gefüllt und geöffnet. `content` ist ein Element
 * oder eine Liste davon; die Überschrift `h2` benennt ihn für Screenreader, `[data-close]` schließt ihn.
 */
export function dialog(id, content, className = '') {
	let box = document.getElementById(id);
	if (box === null) {
		box = el('dialog', { id });
		box.addEventListener('click', (event) => {
			if (event.target.closest('[data-close]')) {
				box.close();
			}
		});
		document.body.append(box);
	}
	box.className = className;
	box.replaceChildren();
	appendChildren(box, content);
	const heading = box.querySelector('h2');
	if (heading) {
		heading.id = id + '-titel';
		box.setAttribute('aria-labelledby', heading.id);
	}
	if (!box.open) {
		const opener = document.activeElement;
		box.addEventListener('close', () => {
			if (opener instanceof HTMLElement && opener.isConnected) {
				opener.focus();
			}
		}, { once: true });
		box.showModal();
	}

	return box;
}

/** Das i-Zeichen mit Hilfetext; per Tastatur erreichbar. */
export function info(text) {
	return el('span', { class: 'info', tabindex: '0', role: 'note', 'aria-label': text, 'data-tipp': text }, 'i');
}

/** Kopf mit Überschrift und ✕, Rumpf und fester Fuß eines Dialogs (Entwurf: .dlg-kopf, .dlg-rumpf, .dlg-fuss). */
export function rahmen(title, body, footer) {
	return [
		el('div', { class: 'dlg-kopf' }, [
			el('h2', { tabindex: '-1', text: title }),
			el('button', { type: 'button', class: 'zu', 'data-close': true, 'aria-label': 'Schließen', text: '✕' }),
		]),
		el('div', { class: 'dlg-rumpf' }, body),
		el('div', { class: 'dlg-fuss' }, footer),
	];
}

/** Eine Fehlerzeile für Dialoge: bleibt unsichtbar, bis `zeigeFehler` Text setzt; `role=alert` liest sie vor. */
export function fehlerZeile(id) {
	return el('p', { class: 'feldfehler', id, role: 'alert', hidden: true });
}

export function zeigeFehler(line, text) {
	line.textContent = text;
	line.hidden = text === '';
}

/** 1 234 567 → „1,2 MB“. */
export function groesse(bytes) {
	const MEGA = 1024 * 1024;
	if (bytes >= MEGA) {
		return (bytes / MEGA).toLocaleString('de-DE', { maximumFractionDigits: 1 }) + ' MB';
	}

	return Math.max(1, Math.round(bytes / 1024)).toLocaleString('de-DE') + ' KB';
}
