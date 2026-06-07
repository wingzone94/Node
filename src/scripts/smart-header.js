export function initSmartHeader() {
    const header = document.querySelector('.m3-header');
    if (!header) return;

    let lastScrollY = window.scrollY || window.pageYOffset;
    let ticking = false;
    const isSearchActive = () => document.querySelector('.m3-search-bar.is-active') !== null;
    const isModalActive = () => document.querySelector('.m3-modal.is-active') !== null;

    const updateHeader = () => {
        const currentScrollY = window.scrollY || window.pageYOffset;

        if (currentScrollY <= 80) {
            header.classList.remove('is-hidden');
        } else if (currentScrollY > lastScrollY && !isSearchActive() && !isModalActive()) {
            if (currentScrollY - lastScrollY > 10) {
                header.classList.add('is-hidden');
            }
        } else if (lastScrollY - currentScrollY > 10) {
            header.classList.remove('is-hidden');
        }

        lastScrollY = currentScrollY;
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }, { passive: true });
}
