import { storage } from '../storage';

export function initKeyboardSnackbar() {
    const snackbar = document.getElementById('m3-snackbar');
    const actionBtn = document.getElementById('m3-snackbar-action');
    const closeBtn = document.getElementById('m3-snackbar-close');
    if (!snackbar) return;

    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    if (!isTouchDevice) return;

    const showSnackbar = () => {
        snackbar.classList.add('is-visible');
        setTimeout(() => snackbar.classList.remove('is-visible'), 8000);
    };

    const handleKeydown = (e) => {
        if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            const currentMode = storage.get('view-mode') || 'mobile';
            if (currentMode === 'mobile') {
                showSnackbar();
                window.removeEventListener('keydown', handleKeydown);
            }
        }
    };

    window.addEventListener('keydown', handleKeydown);

    actionBtn?.addEventListener('click', () => {
        storage.set('view-mode', 'pc');
        location.reload();
    });

    closeBtn?.addEventListener('click', () => {
        snackbar.classList.remove('is-visible');
    });
}
