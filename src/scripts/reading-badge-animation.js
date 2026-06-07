const BADGE_SELECTOR = '#m3-hero-reading-badge';
const ANIMATED_CLASS = 'is-reading-badge-animated';
const START_DELAY_MS = 680;

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
        badge.classList.add(ANIMATED_CLASS);
    }, START_DELAY_MS);
}

export function initReadingBadgeAnimation() {
    if (!isSinglePostView()) return;

    const badge = document.querySelector(BADGE_SELECTOR);
    if (!badge) return;

    badge.classList.remove(ANIMATED_CLASS);
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

    window.setTimeout(armAnimation, 260);
}
