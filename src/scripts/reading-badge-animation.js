const BADGE_SELECTOR = '#m3-hero-reading-badge';
const ANIMATED_CLASS = 'is-reading-badge-animated';
const COMPLETE_CLASS = 'is-reading-badge-complete';
const START_DELAY_MS = 160;
const COMPLETE_FALLBACK_MS = 3200;
const DATE_READY_TIMEOUT_MS = 1200;

function isSinglePostView() {
    return document.body.classList.contains('single') || document.body.classList.contains('single-post');
}

function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return rect.bottom > 0 && rect.top < window.innerHeight;
}

function playReadingBadgeAnimation(badge) {
    if (badge.dataset.readingBadgeAnimated === 'true') return;
    badge.dataset.readingBadgeAnimated = 'true';

    window.setTimeout(() => {
        let completed = false;
        let fallbackTimer = 0;

        const completeAnimation = () => {
            if (completed) return;
            completed = true;
            window.clearTimeout(fallbackTimer);
            badge.classList.add(COMPLETE_CLASS);
            badge.classList.remove(ANIMATED_CLASS);
        };

        const icon = badge.querySelector('.m3-reading-badge__gauge .material-symbols-outlined');
        icon?.addEventListener('animationend', completeAnimation, { once: true });
        fallbackTimer = window.setTimeout(completeAnimation, COMPLETE_FALLBACK_MS);
        badge.classList.add(ANIMATED_CLASS);
    }, START_DELAY_MS);
}

function waitForHeroDateReady(callback) {
    const startedAt = Date.now();

    const check = () => {
        const dateEl = document.querySelector('.m3-article__date time');
        const isReady = dateEl
            && dateEl.textContent.trim()
            && dateEl.getBoundingClientRect().width > 0;

        if (isReady || Date.now() - startedAt >= DATE_READY_TIMEOUT_MS) {
            window.requestAnimationFrame(() => callback());
            return;
        }

        window.requestAnimationFrame(check);
    };

    check();
}

export function initReadingBadgeAnimation() {
    if (!isSinglePostView()) return;

    const badge = document.querySelector(BADGE_SELECTOR);
    if (!badge) return;

    badge.classList.remove(ANIMATED_CLASS);
    badge.classList.remove(COMPLETE_CLASS);
    delete badge.dataset.readingBadgeAnimated;

    const armAnimation = () => {
        if (isElementInViewport(badge)) {
            playReadingBadgeAnimation(badge);
            return;
        }

        if (!('IntersectionObserver' in window)) {
            playReadingBadgeAnimation(badge);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                playReadingBadgeAnimation(badge);
                observer.disconnect();
            });
        }, {
            rootMargin: '0px 0px -12% 0px',
            threshold: 0.08
        });

        observer.observe(badge);
    };

    waitForHeroDateReady(() => window.setTimeout(armAnimation, 120));
}
