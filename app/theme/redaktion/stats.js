// Kennzahlen: eine Zeile am Aufmacher, Zahlen und Funken im Redaktionskasten, dazu der Statistik-Dialog.
import { api } from './api.js';
import { dialog, el, fehlerZeile, icon, info, rahmen, zeigeFehler } from './ui.js';

const DAYS = 30;
const SPARK_DAYS = 7;
const SPARK_MIN_PCT = 6;

export function laden(id) {
	return api('GET', 'stats/posts/' + id + '?days=' + DAYS);
}

function hasViews(stats) {
	return stats && stats.views > 0;
}

export function minutes(seconds) {
	return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
}

/** „1.240 Aufrufe · 58 % zu Ende gelesen“ — am Aufmacher. */
export function kennzahl(stats) {
	const text = hasViews(stats) ? stats.views.toLocaleString('de-DE') + ' Aufrufe · ' + stats.read_full_pct + ' % zu Ende gelesen' : 'Noch keine Aufrufe';

	return el('span', { class: 'kennzahl' }, [icon('auge'), text]);
}

function sparkline(daily) {
	const days = daily.slice(-SPARK_DAYS);
	const max = Math.max(...days.map((day) => day.views), 1);
	const bars = days.map((day) => {
		const bar = el('i', { title: day.day + ': ' + day.views });
		bar.style.height = Math.max(Math.round(day.views / max * 100), SPARK_MIN_PCT) + '%';

		return bar;
	});

	return el('span', { class: 'funken', 'aria-hidden': 'true' }, bars);
}

/** Zahlen und Funken des Redaktionskastens. */
export function zahlen(stats) {
	if (!hasViews(stats)) {
		return el('div', { class: 'zahlen' }, el('span', { text: 'Noch keine Aufrufe' }));
	}

	return el('div', { class: 'zahlen' }, [
		el('span', {}, [el('b', { text: stats.views.toLocaleString('de-DE') }), 'Aufrufe']),
		el('span', {}, [el('b', { text: stats.read_full_pct + ' %' }), 'zu Ende gelesen']),
		el('span', {}, [el('b', { text: minutes(stats.avg_seconds) }), 'Lesezeit Ø']),
		sparkline(stats.daily),
	]);
}

const PERIODS = [7, 14, 30, 90];
const DEFAULT_PERIOD = 14;
const LIST_LIMIT = 5;
const BAR_MIN_PCT = 3;

function shortDay(day) {
	const [, month, date] = day.split('-');

	return date + '.' + month + '.';
}

function kpi(value, label, help) {
	return el('div', { class: 'kpi' }, [el('b', { text: value }), el('span', { text: label }), help ? ' ' : false, help ? info(help) : false]);
}

function bars(daily) {
	const max = Math.max(...daily.map((day) => day.views), 1);
	const summary = daily.map((day) => shortDay(day.day) + ' ' + day.views).join(', ');
	const box = el('div', { class: 'balken', role: 'img', 'aria-label': 'Aufrufe pro Tag: ' + summary });
	for (const day of daily) {
		const bar = el('i', { title: shortDay(day.day) + ': ' + day.views });
		bar.style.height = Math.max(Math.round(day.views / max * 100), BAR_MIN_PCT) + '%';
		box.append(bar);
	}

	return box;
}

function topPosts(posts) {
	return el('ul', { class: 'liste' }, posts.slice(0, LIST_LIMIT).map((post) => el('li', {}, el('span', { class: 't' }, [
		el('a', { href: '/artikel/' + post.slug + '/', text: post.title }),
		el('small', { text: post.views.toLocaleString('de-DE') + ' · ' + post.read_full_pct + ' % gelesen' }),
	]))));
}

function referrers(entries) {
	if (entries.length === 0) {
		return el('p', { class: 'meta', text: 'Noch keine Herkunft erfasst.' });
	}

	return el('ul', { class: 'liste' }, entries.slice(0, LIST_LIMIT).map((entry) => el('li', {}, el('span', { class: 't' }, [
		entry.value,
		el('small', { text: entry.count.toLocaleString('de-DE') + ' Aufrufe' }),
	]))));
}

function searchChips(searches, onPick) {
	if (searches.length === 0) {
		return [el('p', { class: 'meta', text: 'Keine Suche ohne Treffer.' })];
	}
	const chips = searches.map((search) => el('li', {}, el('button', {
		type: 'button',
		class: 'chip',
		title: 'Daraus ein Thema einreichen',
		text: search.value,
		onclick: () => onPick(search.value),
	})));

	return [el('ul', { class: 'chips' }, chips), el('p', { class: 'meta', text: 'Tipp: Daraus ein Thema einreichen.' })];
}

async function pickSearch(term) {
	document.getElementById('statistik').close();
	(await import('./queue.js')).oeffnen(term);
}

function report(data) {
	if (data.views === 0) {
		return el('p', { class: 'hinweis', text: 'Noch keine Aufrufe im Zeitraum.' });
	}

	return [
		el('div', { class: 'kpis' }, [
			kpi(data.views.toLocaleString('de-DE'), 'Aufrufe'),
			kpi(data.visitors.toLocaleString('de-DE'), 'Besucher (je Tag)', 'Eindeutig je Tag, über Tage nicht addierbar.'),
			kpi(data.read_full_pct + ' %', 'zu Ende gelesen'),
			kpi(minutes(data.avg_seconds), 'Lesezeit Ø'),
		]),
		bars(data.daily),
		el('div', { class: 'zwei zwischen' }, [
			el('div', {}, [el('h3', { class: 'klein', text: 'Meistgelesen' }), topPosts(data.top_posts)]),
			el('div', {}, [el('h3', { class: 'klein', text: 'Herkunft' }), referrers(data.referrers)]),
		]),
		el('h3', { class: 'klein zwischen', text: 'Suchen ohne Treffer' }),
		...searchChips(data.searches_without_hits, pickSearch),
	];
}

function periodSwitch(current, onPick) {
	const group = el('span', { class: 'gewicht hell zeitraum', role: 'group', 'aria-label': 'Zeitraum' });
	for (const days of PERIODS) {
		group.append(el('button', { type: 'button', 'aria-pressed': String(days === current), text: days + ' Tage', onclick: () => onPick(days) }));
	}

	return group;
}

/** Der Dialog „Statistik · letzte n Tage“ aus dem Menü. */
export async function dialogOeffnen(days = DEFAULT_PERIOD) {
	const errorLine = fehlerZeile('statistik-fehler');
	const content = el('div', { class: 'stand' });
	dialog('statistik', rahmen('Statistik · letzte ' + days + ' Tage', [periodSwitch(days, dialogOeffnen), errorLine, content], [
		el('button', { type: 'button', class: 'knopf', 'data-close': true, text: 'Schließen' }),
	]), 'breit');
	document.querySelector('#statistik .zeitraum [aria-pressed="true"]').focus({ preventScroll: true });

	try {
		content.replaceChildren(...[report(await api('GET', 'stats?days=' + days))].flat());
	} catch (error) {
		zeigeFehler(errorLine, error.message);
	}
}
