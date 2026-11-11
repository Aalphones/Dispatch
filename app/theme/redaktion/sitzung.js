// Die Sitzung des Inhabers: Das Access-Token lebt nur in dieser Variable und wird zwischen Tabs geteilt (ADR-023).
import { toast } from './ui.js';

const MARK = 'dispatch.angemeldet';
const API = '/dispatch/api/auth/';
const RENEW_BEFORE_MS = 60_000;
const ASK_TIMEOUT_MS = 150;

const channel = new BroadcastChannel('dispatch-sitzung');
const endListeners = [];
const waiters = [];
let current = null;
let pending = null;

function expiryOf(jwt) {
	try {
		return JSON.parse(atob(jwt.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))).exp * 1000;
	} catch {
		return 0;
	}
}

function isFresh(session) {
	return session !== null && session.exp - Date.now() > RENEW_BEFORE_MS;
}

function adopt(jwt) {
	current = { token: jwt, exp: expiryOf(jwt) };
}

channel.onmessage = ({ data }) => {
	if (data.typ === 'frage' && isFresh(current)) {
		channel.postMessage({ typ: 'token', token: current.token });
	} else if (data.typ === 'token' && !isFresh(current)) {
		adopt(data.token);
		waiters.splice(0).forEach((done) => done());
	} else if (data.typ === 'ende') {
		endSession(data.text, false);
	}
};

/** Übernimmt ein frisch angemeldetes Token. */
export function setToken(jwt) {
	adopt(jwt);
}

/** `listener` läuft, wenn die Sitzung endet; dort räumen die Module ihre Elemente weg. */
export function onEnde(listener) {
	endListeners.push(listener);
}

function endSession(text, shouldBroadcast = true) {
	current = null;
	try {
		localStorage.removeItem(MARK);
	} catch {}
	document.body.classList.remove('angemeldet');
	document.querySelectorAll('.red').forEach((node) => node.remove());
	endListeners.forEach((listener) => listener());
	if (text) {
		toast(text);
	}
	if (shouldBroadcast) {
		channel.postMessage({ typ: 'ende', text });
	}
}

/** Fragt die anderen Tabs nach einem gültigen Token; wartet höchstens 150 ms. */
function askOtherTabs() {
	return new Promise((resolve) => {
		const timer = setTimeout(done, ASK_TIMEOUT_MS);
		function done() {
			clearTimeout(timer);
			resolve();
		}
		waiters.push(done);
		channel.postMessage({ typ: 'frage' });
	});
}

async function refresh() {
	const response = await fetch(API + 'refresh', { method: 'POST', credentials: 'same-origin', headers: { 'X-Dispatch': '1' } });
	if (response.status === 401) {
		const message = 'Sitzung abgelaufen – bitte neu anmelden.';
		endSession(message);
		throw { error: 'session_expired', message, status: 401 };
	}
	if (!response.ok) {
		throw { error: 'refresh_failed', message: 'Die Sitzung ließ sich nicht erneuern.', status: response.status };
	}
	adopt((await response.json()).access_token);

	return current.token;
}

async function obtain(shouldRenew) {
	if (!shouldRenew) {
		await askOtherTabs();
		if (isFresh(current)) {
			return current.token;
		}
	}

	return refresh();
}

/**
 * Ein gültiges Token: aus dem Speicher, sonst von einem anderen Tab, sonst per Refresh. Mehrere gleichzeitige
 * Aufrufe teilen sich eine Anfrage. `shouldRenew` überspringt Speicher und Tabs (nach einem 401).
 */
export function token(shouldRenew = false) {
	if (!shouldRenew && isFresh(current)) {
		return Promise.resolve(current.token);
	}
	if (pending === null) {
		pending = obtain(shouldRenew).finally(() => {
			pending = null;
		});
	}

	return pending;
}

export async function abmelden() {
	try {
		await fetch(API + 'logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-Dispatch': '1' } });
	} catch {}
	endSession('Abgemeldet.');
}

/** Widerruft alle Sitzungen des Inhabers; andere Browser melden sich beim nächsten Refresh ab. */
export async function ueberallAbmelden() {
	const response = await fetch(API + 'logout-all', { method: 'POST', credentials: 'same-origin', headers: { Authorization: 'Bearer ' + await token() } });
	if (!response.ok) {
		throw { error: 'logout_failed', message: 'Überall abmelden ist fehlgeschlagen.', status: response.status };
	}
	endSession('Überall abgemeldet.');
}
