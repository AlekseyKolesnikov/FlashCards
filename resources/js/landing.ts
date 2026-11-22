// Theme button on the landing page (resources/views/landing.blade.php), which has no React.
// The page renders both icons and CSS shows the one for the current theme.

import { getTheme, setTheme } from './theme';

const button = document.querySelector<HTMLButtonElement>('.theme-toggle');

button?.addEventListener('click', () => {
    setTheme(getTheme() === 'dark' ? 'light' : 'dark', button.dataset.cookiePath ?? '/');

    const label = `Switch to ${getTheme() === 'dark' ? 'light' : 'dark'} theme`;
    button.setAttribute('aria-label', label);
    button.title = label;
});
