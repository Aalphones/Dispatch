// Zählt Aufruf, Lesezeit, Lesetiefe, Ausgänge und Suchen (ADR-022). Sendet nur Beacons, speichert nichts im Browser.
const send = (data) => navigator.sendBeacon('/dispatch/api/hit', JSON.stringify({ p: location.pathname, ...data }));

function referrer() {
	try {
		const host = new URL(document.referrer).hostname;
		return host === location.hostname ? '' : host;
	} catch {
		return '';
	}
}

let device = 'desktop';
if (innerWidth < 600) device = 'mobil';
else if (innerWidth < 1024) device = 'tablet';
send({ t: 'view', r: referrer(), u: new URLSearchParams(location.search).get('utm_source') || '', d: device });

// Panel und Suchseite melden denselben Begriff; der Merker zählt ihn einmal.
addEventListener('dispatch:suche', (event) => {
	const term = event.detail.q.trim().toLowerCase();
	if (sessionStorage.getItem('dispatch.suche') === term) return;
	sessionStorage.setItem('dispatch.suche', term);
	send({ t: 'search', q: term, n: event.detail.n });
});

document.addEventListener('click', (event) => {
	const link = event.target.closest && event.target.closest('a[href]');
	if (link && link.hostname && link.hostname !== location.hostname) send({ t: 'out', z: link.hostname });
});

const text = document.body.dataset.seite === 'artikel' && document.querySelector('.artikel .text');
if (text) {
	let seconds = 0;
	let depth = 0;
	let sentDepth = 0;
	// Zwei unsichtbare Marken: nach der Hälfte der Kindelemente und am Ende des Artikeltexts.
	const reach = new IntersectionObserver((entries) => entries.forEach((entry) => {
		if (entry.isIntersecting) depth = Math.max(depth, Number(entry.target.dataset.tiefe));
	}));
	[[Math.floor(text.children.length / 2), 50], [text.children.length, 100]].forEach(([index, value]) => {
		const mark = document.createElement('div');
		mark.dataset.tiefe = value;
		mark.setAttribute('aria-hidden', 'true');
		Object.assign(mark.style, { height: '1px', margin: '0' });
		text.insertBefore(mark, text.children[index] || null);
		reach.observe(mark);
	});

	setInterval(() => {
		if (document.visibilityState === 'visible' && document.hasFocus()) seconds += 1;
	}, 1000);

	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState !== 'hidden' || seconds === 0) return;
		// Die Tiefe geht nur einmal je Stufe hinaus, sonst zählte ein Wiederkehrer sie doppelt.
		send({ t: 'read', s: Math.min(seconds, 3600), h: depth > sentDepth ? depth : 0 });
		sentDepth = Math.max(sentDepth, depth);
		seconds = 0;
	});
}
