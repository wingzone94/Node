export function initFloatingActions() {
    const actionStack = document.querySelector('.m3-action-stack');
    if (!actionStack) return;

    const backToTop = document.getElementById('m3-back-to-top');
    const scrollToComments = document.getElementById('m3-scroll-to-comments');
    const jumpToAI = document.getElementById('m3-jump-to-ai');

    backToTop?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    scrollToComments?.addEventListener('click', () => {
        const comments = document.getElementById('comments') || document.getElementById('respond');
        if (comments) {
            const headerOffset = 100;
            const elementPosition = comments.getBoundingClientRect().top + window.pageYOffset;
            window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });
        }
    });

    jumpToAI?.addEventListener('click', () => {
        const aiSummary = document.getElementById('m3-ai-summary');
        if (aiSummary) {
            const headerOffset = 100;
            const elementPosition = aiSummary.getBoundingClientRect().top + window.pageYOffset;
            window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });

            aiSummary.style.transition = 'box-shadow 0.5s ease';
            aiSummary.style.boxShadow = '0 0 60px rgba(255, 153, 0, 0.6)';
            setTimeout(() => { aiSummary.style.boxShadow = ''; }, 2000);
        }
    });

    const updateVisibility = () => {
        const scrollPos = window.scrollY || window.pageYOffset;
        actionStack.classList.toggle('is-visible', scrollPos > 60);
    };
    window.addEventListener('scroll', updateVisibility, { passive: true });
    setTimeout(updateVisibility, 100);
}