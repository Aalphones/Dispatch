// Index-Daten kommen nur per createElement/append/setAttribute ins DOM, nie per innerHTML.
const ENDINGS = ['ungen', 'ung', 'ern', 'en', 'er', 'es', 'e', 'n', 's'];
const FIELDS = [['t', 10], ['h', 5], ['z', 3], ['c', 1]];
const SORTS = [['relevanz', 'Relevanz'], ['neu', 'Neueste'], ['alt', 'Älteste']];
const WORD = /[\p{L}\p{N}]+/gu;
const norm = (s) => s.toLowerCase().replace(/ß/g, 'ss')
	.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
const pad = (s) => ' ' + norm(s) + ' ';
const stems = (q) => norm(q).split(' ').filter(Boolean).map((w) => {
	const e = w.length < 5 ? '' : ENDINGS.find((x) => w.endsWith(x) && w.length > x.length);
	return e ? w.slice(0, -e.length) : w;
});
const $ = (id) => document.getElementById(id);
const hasPrefix = (w, st) => st.some((p) => w.startsWith(p));
function h(tag, attrs, ...kids) {
	const n = document.createElement(tag);
	for (const k in attrs) n.setAttribute(k, attrs[k]);
	n.append(...kids);
	return n;
}
const link = (cls, href, ...kids) => h('a', { class: cls, href }, ...kids);
const img = (src) => h('img', { src, alt: '', loading: 'lazy' });
const btn = (attrs, text, fn, ...kids) => {
	const n = h('button', { type: 'button', ...attrs }, text, ...kids);
	n.onclick = fn;
	return n;
};
// Ungerade Teile = Wörter.
const mark = (text, st) => text.split(/([\p{L}\p{N}]+)/u).map((part, i) => (i % 2 && hasPrefix(norm(part), st) ? h('mark', 0, part) : part));

let loaded;
const load = () => loaded || (loaded = fetch(document.documentElement.dataset.suchindex).then((r) => r.json()).then((ix) => {
	ix.artikel.forEach((a) => { a.f = { t: pad(a.t), h: pad(a.h.join(' ')), z: pad(a.z + ' ' + a.x), c: pad(a.c) }; });
	ix.cat = new Map(ix.kategorien.map((i) => [i.s, i]));
	ix.tag = new Map(ix.tags.map((i) => [i.s, i]));
	return ix;
}));

function find(ix, st, tags, cat) {
	const hits = [];
	ix.artikel.forEach((a) => {
		if (!tags.every((t) => a.g.includes(t)) || (cat && !a.k.includes(cat))) return;
		let p = 0;
		for (const w of st) {
			const f = FIELDS.find(([k]) => a.f[k].includes(' ' + w));
			if (!f) return;
			p += f[1];
		}
		hits.push({ a, p });
	});
	return hits;
}
const age = (x, y) => (x.a.d < y.a.d ? -1 : +(x.a.d > y.a.d));
const rank = (x, y) => y.p - x.p || age(y, x);

// ~140 Zeichen um den ersten Treffer, an Wortgrenzen; sonst der Teaser.
function snippet(a, st) {
	const hit = [...a.c.matchAll(WORD)].find((m) => hasPrefix(norm(m[0]), st));
	if (!hit) return a.x;
	const s = Math.max(0, hit.index - 50);
	let part = a.c.slice(s, s + 140);
	if (s) part = '… ' + part.replace(/^\S*\s/, '');
	if (s + 140 < a.c.length) part = part.replace(/\s\S*$/, '') + ' …';
	return part;
}

function suggest(ix, q) {
	const st = stems(q);
	const named = (l) => (st.length ? l.filter((i) => st.every((w) => pad(i.n).includes(' ' + w))) : l);
	const group = (title, cls, l) => (l.length ? [h('h3', 0, title), h('div', { class: 'reihe' }, ...l.map((i) => link(cls, i.u, ...mark(i.n, st))))] : []);
	const posts = (st.length ? find(ix, st, [], '').sort(rank).map((x) => x.a) : ix.artikel).slice(0, 4);
	const out = [...group('Kategorien', 'chip kat', st.length ? named(ix.kategorien).slice(0, 4) : []), ...group('Schlagwörter', 'chip tag', named(ix.tags).slice(0, 6))];
	if (posts.length) {
		out.push(h('h3', 0, st.length ? 'Artikel' : 'Zuletzt erschienen'), h('ul', { class: 'treffer' }, ...posts.map((a) => h('li', 0, link('', a.u,
			a.b ? img(a.b) : h('span'), h('span', 0, h('span', { class: 't' }, ...mark(a.t, st)), h('span', { class: 's' }, a.z || a.x)))))));
	}
	return out.length ? out : [h('p', { class: 'leer' }, 'Nichts gefunden zu „' + q.trim() + '“.')];
}

export function start(field) {
	const panel = $('panel');
	const cancel = $('suche-zu');
	let sel = -1;
	const render = () => load().then((ix) => {
		panel.replaceChildren(...suggest(ix, field.value));
		sel = -1;
	}).catch(() => {});
	field.addEventListener('input', render);
	render();
	field.addEventListener('keydown', (ev) => {
		const items = [...panel.querySelectorAll('.treffer a')];
		const step = { ArrowDown: 1, ArrowUp: -1 }[ev.key];
		if (step && items.length) {
			ev.preventDefault();
			if (items[sel]) items[sel].classList.remove('sel');
			sel = items[sel] ? (sel + step + items.length) % items.length : (step > 0 ? 0 : items.length - 1);
			items[sel].classList.add('sel');
		} else if (ev.key === 'Enter' && items[sel]) {
			ev.preventDefault();
			location.href = items[sel].href;
		} else if (ev.key === 'Escape') field.blur();
	});
	// Fokus halten, sonst schließt das Panel vor dem Klick.
	panel.onmousedown = (ev) => ev.preventDefault();
	if (cancel) cancel.onmousedown = (ev) => { ev.preventDefault(); field.blur(); };
}

const row = (label, chips) => h('div', { class: 'filterzeile' }, h('b', 0, label), h('ul', { class: 'chips' }, ...chips.map((c) => h('li', 0, c))));

function card(ix, a, st) {
	const c = ix.cat.get(a.k[0]);
	const date = new Date(a.d).toLocaleDateString('de-DE', { day: 'numeric', month: 'short', timeZone: 'UTC' });
	const photo = h('a', { class: 'foto', href: a.u, tabindex: '-1', 'aria-hidden': 'true' }, a.b ? img(a.b) : '');
	return h('article', { class: 'karte', 'data-post-id': a.i }, a.b ? photo : '', h('span', { class: 'klein dach' }, c ? c.n : ''),
		h('h3', 0, link('', a.u, ...mark(a.t, st))), h('p', { class: 'teaser' }, ...mark(snippet(a, st), st)),
		h('p', { class: 'meta' }, date));
}

function pager(page, total, pick) {
	const go = (n, text, attrs, on = true) => {
		const node = h(on ? 'a' : 'span', on ? { href: location.href, ...attrs } : { 'aria-disabled': 'true', ...attrs }, text);
		node.onclick = (ev) => { ev.preventDefault(); pick(n); };
		return node;
	};
	const arrow = (text, n, on, cls) => go(n, text, { class: cls }, on);
	const nums = Array.from({ length: total }, (_, i) => i + 1).filter((n) => total < 8 || n === 1 || n === total || Math.abs(n - page) < 2);
	return [arrow('← Neuere', page - 1, page > 1, 'weiter-knopf zurueck'),
		h('ol', 0, ...nums.map((n) => h('li', 0, go(n, String(n), n === page ? { 'aria-current': 'page' } : {})))),
		h('span', { class: 'stand' }, 'Seite ' + page + ' von ' + total), arrow('Ältere →', page + 1, page < total, 'weiter-knopf')];
}

export function seite() {
	const bar = document.querySelector('#filterleiste .wrap');
	const grid = $('suche-raster');
	const pages = $('suche-seiten');
	const field = $('suchfeld');
	const orders = { neu: (x, y) => age(y, x), alt: age };
	let p = new URLSearchParams(location.search);
	let page = 1;
	const url = () => location.pathname + (p.toString() ? '?' + p : '');
	const change = (fn) => { fn(p); page = 1; history.pushState(null, '', url()); draw(); };
	const name = (map, slug) => (map.has(slug) ? map.get(slug).n : slug);

	function filters(ix, hits) {
		const on = (cls, text, fn) => btn({ class: cls + ' aktiv' }, text, () => change(fn), ' ', h('span', { class: 'x' }, '×'));
		const tags = p.getAll('tag');
		const active = tags.map((slug) => on('chip tag', name(ix.tag, slug), (n) => { n.delete('tag'); tags.filter((t) => t !== slug).forEach((t) => n.append('tag', t)); }));
		if (p.get('kategorie')) active.push(on('chip kat', name(ix.cat, p.get('kategorie')), (n) => n.delete('kategorie')));
		const counts = new Map();
		hits.forEach((x) => x.a.g.forEach((slug) => counts.set(slug, (counts.get(slug) || 0) + 1)));
		const more = [...counts].filter(([slug]) => ix.tag.has(slug) && !tags.includes(slug)).sort((x, y) => y[1] - x[1]).slice(0, 8)
			.map(([slug]) => btn({ class: 'chip tag' }, ix.tag.get(slug).n, () => change((next) => next.append('tag', slug))));
		const current = orders[p.get('sort')] ? p.get('sort') : 'relevanz';
		const sorts = SORTS.map(([value, text]) => btn({ class: 'chip', 'aria-pressed': String(current === value) }, text, () => change((n) => (value === 'relevanz' ? n.delete('sort') : n.set('sort', value)))));
		const rows = active.length ? [row('Gefiltert nach', active)] : [];
		if (more.length) rows.push(row('Hinzufügen', more));
		bar.replaceChildren(...rows, row('Sortieren', sorts));
	}

	function draw() {
		if (field) field.value = p.get('q') || '';
		load().then((ix) => {
			const st = stems(p.get('q') || '');
			const hits = find(ix, st, p.getAll('tag'), p.get('kategorie')).sort(orders[p.get('sort')] || rank);
			filters(ix, hits);
			const total = Math.ceil(hits.length / 12) || 1;
			page = Math.min(page, total);
			grid.replaceChildren(...hits.slice((page - 1) * 12, page * 12).map((x) => card(ix, x.a, st)));
			if (!hits.length) grid.append(h('p', { class: 'hinweis' }, 'Keine Treffer'));
			pages.replaceChildren(...(total > 1 ? pager(page, total, (n) => { page = n; draw(); grid.scrollIntoView(); }) : []));
		}).catch(() => grid.replaceChildren(h('p', { class: 'hinweis' }, 'Suche nicht verfügbar')));
	}

	addEventListener('popstate', () => { p = new URLSearchParams(location.search); draw(); });
	history.replaceState(null, '', url());
	draw();
}
