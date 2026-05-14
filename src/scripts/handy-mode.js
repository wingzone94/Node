export function initHandyMode() {
    const tocBtn = document.getElementById('m3-handy-toc-trigger');
    if (tocBtn) {
        tocBtn.addEventListener('click', () => {
            document.dispatchEvent(new CustomEvent("m3:toc:toggle"));
        });
    }

    const commentsBtn = document.getElementById('m3-bottom-comments-trigger');
    if (commentsBtn) {
        commentsBtn.addEventListener('click', () => {
            const comments = document.getElementById('comments') || document.getElementById('respond');
            if (comments) {
                const headerOffset = 100;
                const elementPosition = comments.getBoundingClientRect().top + window.pageYOffset;
                window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });
            }
        });
    }

    const topBtn = document.getElementById('m3-back-to-top-handy');
    if (topBtn) {
        topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }
}