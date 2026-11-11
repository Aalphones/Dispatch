// Kennzahlen zu einem Artikel: eine Zeile am Aufmacher, Zahlen und Funken im Redaktionskasten.
import { api } from './api.js';
import { el, icon } from './ui.js';

const DAYS = 30;
const SPARK_DAYS = 7;
const SPARK_MIN_PCT = 6;

export function laden(id) {
	return api('GET', 'stats/posts/' + id + '?days=' + DAYS);
}

function hasViews(stats) {
	return stats && stats.views > 0;
}

function minutes(seconds) {
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
