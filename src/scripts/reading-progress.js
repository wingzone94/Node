/**
 * Header reading progress gauge (v0.6.2 spec)
 * Bar at bottom of header.php with shimmer + shatter animation at 100%.
 */

const HEADER_OFFSET = 64;
const SHATTER_THRESHOLD = 99.8;
const RESTORE_THRESHOLD = 99.5;

export function initReadingProgress() {
    const progressBar = document.querySelector('.m3-header__progress-bar');
    const container = document.querySelector('.m3-header__progress-container');
    const article = document.querySelector('.m3-article__body') || document.querySelector('.site-main');

    if (!progressBar || !article) return;

    let shattered = false;

    const updateProgress = () => {
        const scrollY = window.scrollY || window.pageYOffset;
        const rect = article.getBoundingClientRect();
        const articleTop = rect.top + scrollY;
        const articleHeight = rect.height;
        const windowHeight = window.innerHeight;
        const scrollStart = Math.max(0, articleTop - HEADER_OFFSET);
        const scrollEnd = articleTop + articleHeight - 100;

        let progress = 0;
        if (scrollY > scrollStart) {
            const scrollDistance = scrollY - scrollStart;
            const scrollableHeight = articleHeight - windowHeight + HEADER_OFFSET;
            progress = (scrollDistance / Math.max(1, scrollableHeight)) * 100;
        }

        progress = Math.min(100, Math.max(0, progress));

        if (progress < RESTORE_THRESHOLD && shattered) {
            shattered = false;
            if (typeof gsap !== 'undefined') {
                gsap.to(progressBar, { opacity: 1, scaleY: 1, duration: 0.3, ease: 'power2.out' });
            } else {
                progressBar.style.opacity = '1';
                progressBar.style.transform = '';
            }
        }

        if (!shattered) {
            progressBar.style.width = `${progress}%`;
        }

        if (progress >= SHATTER_THRESHOLD && !shattered) {
            shattered = true;
            playBarShatterAnimation(container, progressBar);
        }

        if (container) {
            const inRange = scrollY > scrollStart && scrollY < scrollEnd;
            container.classList.toggle('is-visible', inRange);
        }
    };

    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });
    updateProgress();
}

function playBarShatterAnimation(parent, bar) {
    if (!parent || !bar) return;

    if (typeof gsap !== 'undefined') {
        gsap.to(bar, {
            opacity: 0,
            scaleY: 3,
            duration: 0.2,
            ease: 'expo.out',
            transformOrigin: 'center bottom',
        });
    } else {
        bar.style.opacity = '0';
    }

    const rect = bar.getBoundingClientRect();
    const shardCount = 20;

    for (let i = 0; i < shardCount; i++) {
        const shard = document.createElement('div');
        shard.className = 'm3-gauge-shard';
        shard.style.backgroundColor = '#FF9900';
        shard.style.left = `${Math.random() * Math.max(rect.width, 1)}px`;
        shard.style.top = '0px';
        parent.appendChild(shard);

        const angle = Math.random() * Math.PI + Math.PI;
        const dist = 40 + Math.random() * 120;

        if (typeof gsap !== 'undefined') {
            gsap.to(shard, {
                x: Math.cos(angle) * dist,
                y: Math.sin(angle) * dist,
                rotation: Math.random() * 720,
                scale: 0,
                opacity: 0,
                duration: 0.8 + Math.random() * 0.4,
                ease: 'power4.out',
                onComplete: () => shard.remove(),
            });
        } else {
            shard.remove();
        }
    }
}
