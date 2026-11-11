// Calls to the Laravel JSON API (SPECIFICATION.md §10.3).

import { config } from './config';

type Query = Record<string, string | number | boolean | null | undefined> | URLSearchParams;

export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public data: { message?: string; code?: string; errors?: Record<string, string[]> } | null,
    ) {
        super(message);
    }

    /** The first validation message of each field. */
    get fieldErrors(): Record<string, string> {
        return Object.fromEntries(Object.entries(this.data?.errors ?? {}).map(([field, messages]) => [field, messages[0]]));
    }
}

export function apiUrl(path: string, query?: Query): string {
    const params = query instanceof URLSearchParams ? query : new URLSearchParams();

    if (query && !(query instanceof URLSearchParams)) {
        for (const [key, value] of Object.entries(query)) {
            if (value !== null && value !== undefined && value !== '') {
                params.set(key, String(value));
            }
        }
    }

    const search = params.toString();

    return config.apiPath + path + (search ? `?${search}` : '');
}

/**
 * Laravel sets the XSRF-TOKEN cookie on every response; requests that change data send it back.
 * It is read each time, because it changes on login and logout. The app's cookie has the longest
 * path, so the browser lists it before any cookie of the same name from a website around the app.
 */
function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function request<T>(method: string, path: string, body?: unknown, query?: Query): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    const token = xsrfToken();
    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    let payload: BodyInit | undefined;
    if (body instanceof FormData) {
        payload = body;
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const response = await fetch(apiUrl(path, query), { method, headers, body: payload, credentials: 'same-origin' });
    const data = await response.json().catch(() => null);

    // The login session ended (401) or the page is older than the session (419): log in again,
    // then come back to the same screen. The page reloads, so this call never settles.
    if (response.status === 401 || response.status === 419) {
        const back = location.pathname.slice(config.appPath.length) + location.search;
        location.assign(`${config.appPath}/login?next=${encodeURIComponent(back || '/')}`);

        return new Promise<T>(() => {});
    }

    if (!response.ok) {
        throw new ApiError(response.status, data?.message || response.statusText, data);
    }

    return data as T;
}

export const api = {
    get: <T>(path: string, query?: Query) => request<T>('GET', path, undefined, query),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
    patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
    delete: <T>(path: string, body?: unknown) => request<T>('DELETE', path, body),
};
