/**
 * Node Theme Manager
 * Handles light/dark mode switching and persistence.
 */

const THEME_KEY = 'node_theme';

export function initThemeManager() {
    const toggle = document.getElementById('theme-toggle');
    const handyToggle = document.getElementById('m3-theme-toggle-handy');
    
    const getStoredTheme = () => localStorage.getItem(THEME_KEY);
    const setStoredTheme = (theme) => localStorage.setItem(THEME_KEY, theme);

    const applyTheme = (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.setAttribute('data-theme', theme);
        
        // Update icons if they exist
        const icons = document.querySelectorAll('#theme-toggle .material-symbols-outlined, #m3-theme-toggle-handy .material-symbols-outlined');
        icons.forEach(icon => {
            // Keep it as brightness_6 as per design choice, or switch if desired.
            // The user previously wanted it fixed to brightness_6.
            icon.textContent = 'brightness_6';
        });

        setStoredTheme(theme);
    };

    const toggleTheme = () => {
        const current = document.documentElement.getAttribute('data-theme') || getStoredTheme() || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
    };

    if (toggle) {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            toggleTheme();
        });
    }

    if (handyToggle) {
        handyToggle.addEventListener('click', (e) => {
            e.preventDefault();
            toggleTheme();
        });
    }

    // Initial Sync (in case the head script missed something or for state consistency)
    const savedTheme = getStoredTheme();
    if (savedTheme) {
        applyTheme(savedTheme);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        // Fallback to system preference if no saved theme
        // applyTheme('dark'); // Uncomment if you want auto-dark
    }
}
