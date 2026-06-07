export function initDrawer() {
    const menuBtn = document.querySelector('.m3-header__menu');
    const drawer = document.getElementById('m3-drawer');
    const scrim = document.getElementById('m3-drawer-scrim');
    const closeBtn = document.getElementById('m3-drawer-close');
    if (menuBtn && drawer && scrim) {
        const toggle = (open) => {
            drawer.classList.toggle('is-open', open);
            scrim.classList.toggle('is-visible', open);
            document.body.style.overflow = open ? 'hidden' : '';
        };
        menuBtn.addEventListener('click', () => toggle(true));
        scrim.addEventListener('click', () => toggle(false));
        closeBtn?.addEventListener('click', () => toggle(false));
    }
}
