/**
 * Floating action stack (back-to-top, comments, AI, TOC trigger visibility).
 */

import { isIndexOrArchiveView, isSinglePostView } from './page-state';

export function initFloatingActions() {
    const actionStack = document.querySelector('.m3-action-stack');
    if (!actionStack) return;

    const isSingle = isSinglePostView();
    const showBackToTopContext = isSingle || isIndexOrArchiveView();
    const backToTop = document.getElementById('m3-back-to-top');
    const tocTrigger = document.getElementById('m3-toc-trigger');
    const handyTocTrigger = document.getElementById('m3-handy-toc-trigger');
    const tocPanel = document.getElementById('m3-sticky-toc');

    backToTop?.addEventListener('click', (event) => {
        event.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    if (!isSingle) {
        tocTrigger?.remove();
        handyTocTrigger?.remove();
        tocPanel?.classList.remove('is-active');
        tocPanel?.remove();
        document.querySelector('.m3-toc-scrim')?.remove();
        actionStack.classList.remove('is-has-toc');
        document.body.classList.remove('is-active-toc');
    }

    const updateVisibility = () => {
        if (!showBackToTopContext) {
            actionStack.classList.remove('is-visible');
            return;
        }

        const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        const tocReady = isSingle && Boolean(document.querySelector('#m3-toc-trigger.toc-ready'));
        const isDesktop = window.matchMedia('(min-width: 1001px)').matches;
        const shouldShow = isDesktop || scrollY > 200 || tocReady;

        actionStack.classList.toggle('is-visible', shouldShow);
    };

    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('resize', updateVisibility, { passive: true });
    document.addEventListener('m3:toc:ready', updateVisibility);
    setTimeout(updateVisibility, 150);
}
