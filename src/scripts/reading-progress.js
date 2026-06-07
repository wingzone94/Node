/**
 * Header reading progress gauge (v0.6.2 spec)
 * Bar at bottom of header.php with shimmer + shatter animation at 100%.
 */

import gsap from 'gsap';
import { isSinglePostView } from './page-state';

const SHATTER_THRESHOLD = 99.8;
const RESTORE_THRESHOLD = 99.5;

function getHeaderOffset() {
    const header = document.querySelector('.m3-header');
    return header ? header.offsetHeight : 64;
}

export function initReadingProgress() {
    const progressBar = document.querySelector('.m3-header__progress-bar');
    const container = document.querySelector('.m3-header__progress-container');
    const article = document.querySelector('.m3-article__body') || document.querySelector('.site-main');

    if (!progressBar || !article) return;

    let shattered = false;

    const updateProgress = () => {
        const scrollY = window.scrollY || window.pageYOffset;
        const headerOffset = getHeaderOffset();
        const rect = article.getBoundingClientRect();
        const articleTop = rect.top + scrollY;
        const articleHeight = rect.height;
        const windowHeight = window.innerHeight;
        const scrollStart = Math.max(0, articleTop - headerOffset);

        let progress = 0;
        if (scrollY > scrollStart) {
            const scrollDistance = scrollY - scrollStart;
            const scrollableHeight = articleHeight - windowHeight + headerOffset;
            progress = (scrollDistance / Math.max(1, scrollableHeight)) * 100;
        }

        progress = Math.min(100, Math.max(0, progress));

        if (progress < RESTORE_THRESHOLD && shattered) {
            shattered = false;
            gsap.to(progressBar, { opacity: 1, scaleY: 1, duration: 0.3, ease: 'power2.out' });
        }

        if (!shattered) {
            progressBar.style.width = `${progress}%`;
        }

        if (progress >= SHATTER_THRESHOLD && !shattered) {
            shattered = true;
            playBarShatterAnimation(container, progressBar);
        }

        if (container) {
            container.classList.toggle('is-visible', scrollY > scrollStart && progress > 0);
        }
    };

    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });
    updateProgress();
}

export function initReadingProgressSingleOnly() {
    const progressContainer = document.getElementById('m3-reading-progress');
    if (!isSinglePostView()) {
        if (progressContainer) progressContainer.style.display = 'none';
        return;
    }

    initReadingProgress();
}

function playBarShatterAnimation(parent, bar) {
    if (!parent || !bar) return;

    gsap.to(bar, {
        opacity: 0,
        scaleY: 3,
        duration: 0.2,
        ease: 'expo.out',
        transformOrigin: 'center bottom',
    });

    const rect = bar.getBoundingClientRect();
    const primaryColor =
        getComputedStyle(document.documentElement).getPropertyValue('--md-sys-color-primary').trim() ||
        '#FF9900';

    for (let i = 0; i < 20; i++) {
        const shard = document.createElement('div');
        shard.className = 'm3-gauge-shard';
        shard.style.backgroundColor = primaryColor;
        shard.style.left = `${Math.random() * Math.max(rect.width, 1)}px`;
        shard.style.top = '0px';
        parent.appendChild(shard);

        const angle = Math.random() * Math.PI + Math.PI;
        const dist = 40 + Math.random() * 120;

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
    }
}
