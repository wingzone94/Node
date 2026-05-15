/**
 * Floating action stack (back-to-top, comments, AI, TOC trigger visibility).
 */

export function initFloatingActions() {
    const actionStack = document.querySelector('.m3-action-stack');
    if (!actionStack) return;

    const backToTop = document.getElementById('m3-back-to-top');
    const scrollToComments = document.getElementById('m3-scroll-to-comments');
    const jumpToAI = document.getElementById('m3-jump-to-ai');

    backToTop?.addEventListener('click', (e) => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    scrollToComments?.addEventListener('click', (e) => {
        e.preventDefault();
        const comments = document.getElementById('comments') || document.getElementById('respond');
        if (!comments) return;
        const headerOffset = 120;
        const elementPosition = comments.getBoundingClientRect().top + window.pageYOffset;
        window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });
    });

    jumpToAI?.addEventListener('click', (e) => {
        e.preventDefault();
        const aiSummary = document.getElementById('m3-ai-summary');
        if (!aiSummary) return;
        const headerOffset = 120;
        const elementPosition = aiSummary.getBoundingClientRect().top + window.pageYOffset;
        window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });

        aiSummary.style.transition = 'box-shadow 0.5s ease';
        aiSummary.style.boxShadow = '0 0 60px rgba(255, 153, 0, 0.6)';
        setTimeout(() => {
            aiSummary.style.boxShadow = '';
        }, 2000);
    });

    const updateVisibility = () => {
        const scrollY = window.scrollY || window.pageYOffset;
        const tocReady = document.querySelector('#m3-toc-trigger.toc-ready:not([hidden])');
        const mobileTocActive = window.matchMedia('(max-width: 1100px)').matches && !!tocReady;
        actionStack.classList.toggle('is-visible', scrollY > 200 || mobileTocActive);
    };

    window.addEventListener('scroll', updateVisibility, { passive: true });
    setTimeout(updateVisibility, 150);
}
