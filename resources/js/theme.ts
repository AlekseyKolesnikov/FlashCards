// Light or dark theme, per device (SPECIFICATION.md §9.3). Laravel reads the "theme" cookie and
// puts data-theme="dark" on <html>, so the landing page and the login screen follow it as well.

import { config } from './config';

export type Theme = 'light' | 'dark';

const oneYear = 60 * 60 * 24 * 365;

export function getTheme(): Theme {
    return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
}

export function setTheme(theme: Theme): void {
    if (theme === 'dark') {
        document.documentElement.dataset.theme = 'dark';
    } else {
        delete document.documentElement.dataset.theme;
    }

    // "light" is saved too rather than deleting the cookie, so a "theme" cookie from a website
    // around the app cannot switch it.
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `theme=${theme}; path=${config.cookiePath}; max-age=${oneYear}; SameSite=Lax${secure}`;
}
