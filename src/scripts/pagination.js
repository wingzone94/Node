/**
 * Pagination and Article Navigation Logic
 */

export function initPagination() {
    // 1. Page Selector Dropdown (for multi-page posts)
    const pageSelector = document.getElementById('m3-page-selector');
    const paginationNumbers = document.querySelectorAll('.m3-pagination__number');
    const hasArticleActions = document.getElementById('m3-article-toc-anchor') || document.getElementById('m3-article-top-anchor');

    if (!pageSelector && !paginationNumbers.length && !hasArticleActions) {
        return;
    }

    if (pageSelector) {
        pageSelector.addEventListener('change', (e) => {
            if (e.target.value) {
                // Add a small delay for better feel
                setTimeout(() => {
                    window.location.href = e.target.value;
                }, 100);
            }
        });
    }

    // 2. Smooth Scroll for Pagination Links (if they are on the same page, though usually they aren't in WP)
    // But we handle the TOP button here as well for consistency
    document.addEventListener('click', (e) => {
        const tocBtn = e.target.closest('#m3-article-toc-anchor');
        if (tocBtn) {
            e.preventDefault();
            document.dispatchEvent(new CustomEvent('m3:toc:toggle'));
            return;
        }

        const topBtn = e.target.closest('#m3-article-top-anchor');
        if (topBtn) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
    });

    // 3. Animation for pagination numbers on hover (Expressive style)
    paginationNumbers.forEach(num => {
        num.addEventListener('mouseenter', () => {
            if (typeof gsap !== 'undefined' && num.tagName === 'A') {
                gsap.to(num, {
                    scale: 1.1,
                    duration: 0.3,
                    ease: "power2.out"
                });
            }
        });
        num.addEventListener('mouseleave', () => {
            if (typeof gsap !== 'undefined' && num.tagName === 'A') {
                gsap.to(num, {
                    scale: 1,
                    duration: 0.3,
                    ease: "power2.out"
                });
            }
        });
    });
}
