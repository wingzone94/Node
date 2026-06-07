import { storage } from '../storage';

export function initViewSwitcher() {
    const btn = document.getElementById('m3-view-toggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const current = storage.get('view-mode') || 'pc';
        const next = current === 'pc' ? 'mobile' : 'pc';
        storage.set('view-mode', next);
        location.reload();
    });
}
