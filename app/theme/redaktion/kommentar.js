// Kommentarmodus der Vorschau: Anmerkungen an Stellen (Absatz, Bild, Kasten oder markierter Text).
import { el, toast } from './ui.js';

const QUOTE_MAX = 200;
const SELECTION_MAX = 500;
const STORAGE_PREFIX = 'dispatch.anmerkungen.';
const BLOCK_SELECTOR = '.text > *';
const MARKER_SELECTOR = '.k-mark sup, .k-nr';

function plainText(node) {
	const copy = node.cloneNode(true);
	copy.querySelectorAll(MARKER_SELECTOR).forEach((marker) => marker.remove());

	return copy.textContent.replace(/\s+/g, ' ').trim();
}

function shorten(text) {
	return text.length > QUOTE_MAX ? text.slice(0, QUOTE_MAX) + ' …' : text;
}

function elementOf(node) {
	return node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
}

function wrapText(block, text) {
	const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
	for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
		const start = node.nodeValue.indexOf(text);
		if (start === -1) {
			continue;
		}
		const range = document.createRange();
		range.setStart(node, start);
		range.setEnd(node, start + text.length);
		const mark = el('mark', { class: 'k-mark' });
		range.surroundContents(mark);

		return mark;
	}

	return null;
}

/**
 * Macht die Vorschau in `root` kommentierbar. `onChange` läuft nach jeder Änderung (Anzahl, Modus).
 * Ungesendete Anmerkungen liegen je Artikel und Fassung in sessionStorage.
 */
export function start({ root, id, revision, onChange }) {
	const storageKey = STORAGE_PREFIX + id + '.' + revision;
	let entries = [];
	let target = null;
	let isOn = false;

	const quoteNode = el('blockquote');
	const field = el('textarea', { placeholder: 'Was soll der Agent hier ändern?' });
	const box = el('div', { class: 'k-box', hidden: true, role: 'dialog', 'aria-label': 'Anmerkung' }, [
		quoteNode,
		field,
		el('div', { class: 'k-knoepfe' }, [
			el('button', { type: 'button', class: 'knopf leise klein-k', text: 'Abbrechen', onclick: closeBox }),
			el('button', { type: 'button', class: 'knopf primaer klein-k', text: 'Anmerkung speichern', onclick: save }),
		]),
	]);
	const emptyNote = el('p', { class: 'k-leer', text: 'Noch keine. „Kommentieren“ einschalten, dann eine Stelle anklicken oder Text markieren.' });
	const listNodes = el('ol');
	const list = el('aside', { class: 'k-liste', 'aria-label': 'Deine Anmerkungen' }, [el('h3', { text: 'Deine Anmerkungen' }), listNodes, emptyNote]);

	(root.querySelector('.koerper') || root).append(list);
	document.body.append(box);
	field.addEventListener('keydown', (event) => {
		if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
			save();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') {
			return;
		}
		if (!box.hidden) {
			closeBox();
		} else if (isOn) {
			setMode(false);
		}
	});
	root.addEventListener('mouseup', pick);

	restore();
	render();

	function blocks() {
		return [...root.querySelectorAll(BLOCK_SELECTOR)];
	}

	function setMode(on) {
		isOn = on;
		document.body.classList.toggle('k-modus', on);
		if (!on) {
			closeBox();
		}
		onChange();
	}

	function openBox(entry, quote, rect) {
		target = entry;
		quoteNode.textContent = shorten(quote);
		field.value = '';
		box.hidden = false;
		const left = Math.min(rect.left + scrollX, scrollX + innerWidth - box.offsetWidth - 16);
		box.style.left = Math.max(16, left) + 'px';
		box.style.top = rect.bottom + scrollY + 10 + 'px';
		setTimeout(() => field.focus(), 30);
	}

	function closeBox() {
		box.hidden = true;
		if (target !== null && target.provisional) {
			target.el.replaceWith(...target.el.childNodes);
		}
		target = null;
	}

	function pick(event) {
		if (!isOn || event.target.closest('.k-nr, .k-mark')) {
			return;
		}
		const selection = getSelection();
		const selected = selection && !selection.isCollapsed && root.contains(selection.anchorNode) ? selection.toString().trim() : '';
		if (selected !== '') {
			if (pickSelection(selection, selected)) {
				return;
			}
		}
		const block = event.target.closest(BLOCK_SELECTOR);
		if (block === null || !root.contains(block)) {
			return;
		}
		openBox({ el: block, art: 'stelle', block: blocks().indexOf(block), auswahl: '' }, selected || plainText(block) || 'Bild', block.getBoundingClientRect());
	}

	/** Markiert den Text, wenn er in einem Block liegt; sonst `false` und die Stelle wird der ganze Block. */
	function pickSelection(selection, selected) {
		const range = selection.getRangeAt(0);
		const block = elementOf(range.commonAncestorContainer).closest(BLOCK_SELECTOR);
		if (block === null || !root.contains(block)) {
			return false;
		}
		const mark = el('mark', { class: 'k-mark' });
		try {
			range.surroundContents(mark);
		} catch {
			return false;
		} finally {
			selection.removeAllRanges();
		}
		openBox({ el: mark, art: 'text', provisional: true, block: blocks().indexOf(block), auswahl: selected.slice(0, SELECTION_MAX) }, selected, mark.getBoundingClientRect());

		return true;
	}

	function save() {
		const text = field.value.trim();
		if (text === '' || target === null) {
			return;
		}
		const entry = { ...target, text, zitat: shorten(quoteNode.textContent), provisional: false };
		target = null;
		box.hidden = true;
		entries.push(entry);
		render();
		toast('Anmerkung ' + entries.length + ' gespeichert.');
	}

	function placeMarker(entry, number) {
		if (entry.art === 'text') {
			let superscript = entry.el.querySelector('sup');
			if (superscript === null) {
				superscript = el('sup');
				entry.el.append(superscript);
			}
			superscript.textContent = number;

			return;
		}
		entry.el.classList.add('kommentiert');
		let badge = entry.el.querySelector(':scope > .k-nr');
		if (badge === null) {
			badge = el('span', { class: 'k-nr' });
			entry.el.append(badge);
		}
		badge.textContent = number;
	}

	function unmark(entry) {
		if (entry.art === 'text') {
			entry.el.querySelector('sup')?.remove();
			entry.el.replaceWith(...entry.el.childNodes);

			return;
		}
		entry.el.classList.remove('kommentiert');
		entry.el.querySelector(':scope > .k-nr')?.remove();
	}

	function remove(entry) {
		unmark(entry);
		entries = entries.filter((candidate) => candidate !== entry);
		render();
	}

	function render() {
		entries.forEach((entry, index) => placeMarker(entry, index + 1));
		listNodes.replaceChildren(...entries.map(listItem));
		emptyNote.hidden = entries.length > 0;
		persist();
		onChange();
	}

	function listItem(entry, index) {
		return el('li', { onclick: () => entry.el.scrollIntoView({ behavior: 'smooth', block: 'center' }) }, [
			el('b', { text: index + 1 }),
			el('span', { class: 'k-text', text: entry.text }),
			el('button', {
				type: 'button',
				class: 'weg',
				'aria-label': 'Anmerkung ' + (index + 1) + ' löschen',
				title: 'Löschen',
				text: '✕',
				onclick: (event) => {
					event.stopPropagation();
					remove(entry);
				},
			}),
			el('q', { text: entry.zitat }),
		]);
	}

	function persist() {
		try {
			if (entries.length === 0) {
				sessionStorage.removeItem(storageKey);
			} else {
				sessionStorage.setItem(storageKey, JSON.stringify(entries.map(({ text, zitat, art, block, auswahl }) => ({ text, zitat, art, block, auswahl }))));
			}
		} catch {}
	}

	function restore() {
		let saved = [];
		try {
			saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
		} catch {}
		const all = blocks();
		for (const item of saved) {
			const block = all[item.block];
			if (block === undefined) {
				continue;
			}
			const textMark = item.art === 'text' ? wrapText(block, item.auswahl) : null;
			entries.push(textMark === null ? { ...item, art: 'stelle', el: block } : { ...item, el: textMark });
		}
	}

	return {
		toggle: () => setMode(!isOn),
		isOn: () => isOn,
		count: () => entries.length,
		/** Je Anmerkung ein Block `> „Zitat“` und `→ Anmerkung`, getrennt durch eine Leerzeile. */
		note: () => entries.map((entry) => '> „' + entry.zitat + '“\n→ ' + entry.text).join('\n\n'),
		clear: () => {
			entries.forEach(unmark);
			entries = [];
			render();
			setMode(false);
		},
	};
}
