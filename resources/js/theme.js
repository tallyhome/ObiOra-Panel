const STORAGE_KEY = 'obiora-theme';

export function applyObioraTheme(theme) {
    const next = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-obiora-theme', next);
    localStorage.setItem(STORAGE_KEY, next);
}

export function initObioraTheme() {
    const saved = localStorage.getItem(STORAGE_KEY) || 'dark';
    applyObioraTheme(saved);
}

export function toggleObioraTheme() {
    const current = document.documentElement.getAttribute('data-obiora-theme') || 'dark';
    applyObioraTheme(current === 'dark' ? 'light' : 'dark');
}

initObioraTheme();
window.obioraToggleTheme = toggleObioraTheme;
