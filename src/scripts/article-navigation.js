const NODE_DEBUG = false;

export function initArticleNavigation() {
    document.addEventListener('click', (e) => {
        const commentTrigger = e.target.closest('#m3-hero-comment-trigger');
        if (commentTrigger) {
            e.preventDefault();
            if (NODE_DEBUG) console.log('Main: Hero Comment Clicked');
            const target = document.getElementById('comments');
            if (target) {
                const headerOffset = 120;
                const elementPosition = target.getBoundingClientRect().top + window.scrollY;
                window.scrollTo({
                    top: elementPosition - headerOffset,
                    behavior: 'smooth'
                });
            }
        }
    });
}
