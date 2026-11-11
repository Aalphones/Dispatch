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
		box.showModal();
	}

	return box;
}

/** Das i-Zeichen mit Hilfetext; per Tastatur erreichbar. */
export function info(text) {
	return el('span', { class: 'info', tabindex: '0', role: 'note', 'aria-label': text, 'data-tipp': text }, 'i');
}
