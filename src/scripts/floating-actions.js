/**
 * Floating action stack (back-to-top, comments, AI, TOC trigger visibility).
 */

export function initFloatingActions() {
    const actionStack = document.querySelector('.m3-action-stack');
    if (!actionStack) return;

    const backToTop = document.getElementById('m3-back-to-top');

    backToTop?.addEventListener('click', (e) => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    const updateVisibility = () => {
        const scrollY = window.scrollY || window.pageYOffset;
        const isDesktop = window.matchMedia('(min-width: 1001px)').matches;
        const shouldShow = isDesktop ? true : scrollY > 200;
        actionStack.classList.toggle('is-visible', shouldShow);
    };

    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility, { passive: true });
    document.addEventListener('m3:toc:ready', updateVisibility);
    setTimeout(updateVisibility, 150);
}
