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

function fail(data, status) {
	return {
		error: data && data.error ? data.error : 'http_error',
		message: data && data.message ? data.message : 'Der Aufruf ist fehlgeschlagen.',
		details: data && data.details ? data.details : {},
		status,
	};
}

// `fetch` kennt keinen Upload-Fortschritt, deshalb XMLHttpRequest.
function sendWithProgress(path, body, jwt, onProgress) {
	return new Promise((resolve, reject) => {
		const request = new XMLHttpRequest();
		request.open('POST', API + path);
		request.setRequestHeader('Authorization', 'Bearer ' + jwt);
		request.upload.onprogress = (event) => {
			if (event.lengthComputable) {
				onProgress(event.loaded / event.total);
			}
		};
		request.onerror = () => reject(fail(null, 0));
		request.onload = () => {
			let data = null;
			try {
				data = JSON.parse(request.responseText);
			} catch {}
			resolve({ status: request.status, data });
		};
		request.send(body);
	});
}

/** Multipart-POST mit Fortschritt (0–1); wie `api` bei 401 einmal mit neuem Token wiederholt. */
export async function upload(path, body, onProgress) {
	let result = await sendWithProgress(path, body, await token(), onProgress);
	if (result.status === 401) {
		result = await sendWithProgress(path, body, await token(true), onProgress);
	}
	if (result.status < 200 || result.status >= 300) {
		throw fail(result.data, result.status);
	}

	return result.data;
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
		throw fail(data, response.status);
	}

	return data;
}
