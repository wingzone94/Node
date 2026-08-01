function isLatestHomePage() {
    const body = document.body;
    return (body.classList.contains('home')
        || body.classList.contains('blog')
        || body.classList.contains('front-page'))
        && !body.classList.contains('paged');
}

export function initSectionArchiveLinks() {
    if (!isLatestHomePage()) return;

    const spotlightMoreLink = document.querySelector('.special-features .m3-headlines__more');
    if (!spotlightMoreLink) return;

    const href = spotlightMoreLink.getAttribute('href');
    if (!href || href === '#') {
        spotlightMoreLink.href = `${window.location.origin}/spotlight/`;
    }
}

export function initHeroInfoBubble() {
    const trigger = document.getElementById('m3-hero-reading-badge');
    if (!trigger) return;

    trigger.classList.remove('is-info-active');
    trigger.removeAttribute('role');
    trigger.removeAttribute('tabindex');
    trigger.removeAttribute('aria-expanded');
    trigger.removeAttribute('aria-controls');
    document.getElementById('m3-hero-info-panel')?.classList.remove('is-active');
}
