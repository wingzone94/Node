function isLatestHomePage() {
    const body = document.body;
    return (body.classList.contains('home')
        || body.classList.contains('blog')
        || body.classList.contains('front-page'))
        && !body.classList.contains('paged');
}

export function initLatestGridExpansion() {
    if (!isLatestHomePage()) return;

    const cardSelector = '.m3-card, .m3-elevated-nav-card, .special-features__item, .m3-nexus-card, .m3-blog-card, .m3-product-card';
    const applyLatestLayout = () => {
        const latestSurface = document.querySelector('.m3-surface--latest');
        const latestContainer = latestSurface?.querySelector('.m3-post-grid__container--featured');
        const articlesContainer = document.querySelector('.m3-surface--articles .m3-post-grid__container.is-articles-grid');
        if (!latestSurface || !latestContainer) return;

        const latestCards = Array.from(latestContainer.children).filter((node) => node.matches?.(cardSelector));
        const extraCards = Array.from(articlesContainer?.children || []).filter((node) => node.matches?.(cardSelector));
        const needed = Math.max(0, 9 - latestCards.length);

        for (let i = 0; i < needed && i < extraCards.length; i += 1) {
            latestContainer.appendChild(extraCards[i]);
        }

        latestContainer.querySelectorAll(cardSelector).forEach((card) => {
            card.style.display = '';
        });

        if (articlesContainer) {
            const hasArticleCards = Array.from(articlesContainer.children).some((node) => node.matches?.(cardSelector));
            if (!hasArticleCards) {
                articlesContainer.closest('.m3-surface--articles')?.classList.add('is-empty');
            }
        }

        if (!latestSurface.querySelector('.m3-latest-see-all')) {
            const normalizeArchiveUrl = (href) => {
                if (!href) return null;
                try {
                    const url = new URL(href, window.location.href);
                    url.hash = '';
                    url.pathname = url.pathname.replace(/\/page\/\d+\/?$/i, '/');
                    url.searchParams.delete('paged');
                    return url.toString();
                } catch {
                    return null;
                }
            };

            const sourceLink = normalizeArchiveUrl(window.m3_ajax?.all_articles_url)
                || normalizeArchiveUrl(window.m3_ajax?.home_url)
                || normalizeArchiveUrl(document.querySelector('.m3-archive-pill-button')?.getAttribute('href'))
                || normalizeArchiveUrl(window.location.origin + '/')
                || window.location.href;

            const seeAllLink = document.createElement('a');
            seeAllLink.className = 'm3-latest-see-all m3-button m3-button--text';
            seeAllLink.href = sourceLink;
            seeAllLink.innerHTML = [
                '<span class="m3-latest-see-all__text">すべて見る</span>',
                '<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>'
            ].join('');
            latestSurface.appendChild(seeAllLink);
        }

        window.dispatchEvent(new Event('resize'));
    };

    applyLatestLayout();
    setTimeout(applyLatestLayout, 180);
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
