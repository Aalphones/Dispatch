// Vorschau-Seite: ohne Anmeldung zeigt ein signierter Link den Arbeitsstand lesend; mit Anmeldung baut der Redaktionsmodus sie.
const box = document.getElementById('vorschau-inhalt');
const params = new URLSearchParams(location.search);

function login(text) {
	const button = document.createElement('button');
	button.type = 'button';
	button.className = 'knopf';
	button.textContent = 'Anmelden';
	button.onclick = () => document.getElementById('anmelden').click();
	box.replaceChildren(Object.assign(document.createElement('p'), { textContent: text }), button);
}

async function show() {
	if (!params.has('sig')) {
		login('Zum Ansehen bitte anmelden.');
		document.getElementById('anmelden').click();

		return;
	}
	try {
		const response = await fetch('/dispatch/api/preview?' + params);
		if (response.ok) {
			box.innerHTML = (await response.json()).html;

			return;
		}
	} catch {}
	login('Dieser Vorschau-Link ist ungültig oder abgelaufen.');
}

// Mit Merkzeichen übernimmt der Redaktionsmodus (post.js).
if (box && !localStorage.getItem('dispatch.angemeldet')) {
	show();
}
