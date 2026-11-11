// Aufrufe der Redaktions-API: JSON rein, JSON raus, Fehler als Objekt {error, message, status}.
import { token } from './sitzung.js';

const API = '/dispatch/api/';

async function send(method, path, body, jwt) {
	const headers = { Authorization: 'Bearer ' + jwt };
	let payload;
	if (body instanceof FormData) {
		payload = body;
	} else if (body !== undefined) {
		headers['Content-Type'] = 'application/json';
		payload = JSON.stringify(body);
	}

	return fetch(API + path, { method, headers, body: payload, credentials: 'same-origin' });
}

/** Bei 401 holt es einmal ein neues Token und wiederholt den Aufruf. */
export async function api(method, path, body) {
	let response = await send(method, path, body, await token());
	if (response.status === 401) {
		response = await send(method, path, body, await token(true));
	}
	if (response.status === 204) {
		return null;
	}

	let data = null;
	try {
		data = await response.json();
	} catch {}
	if (!response.ok) {
		throw {
			error: data && data.error ? data.error : 'http_error',
			message: data && data.message ? data.message : 'Der Aufruf ist fehlgeschlagen.',
			status: response.status,
		};
	}

	return data;
}
