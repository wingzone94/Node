/**
 * グリッドを調整して、中途半端な最後の行を隠す
 */
const balanceGrid = (container) => {
    const cards = Array.from(container.querySelectorAll('.m3-card, .m3-elevated-nav-card, .special-features__item'));
    if (!cards.length) return;

    // コンテナの幅とカードの幅から現在の列数を計算
    const containerWidth = container.offsetWidth;
    const cardWidth = cards[0].offsetWidth;
    const gapWidth = parseFloat(getComputedStyle(container).gap) || 0;
    
    // 実効列数を算出
    const columns = Math.floor((containerWidth + gapWidth) / (cardWidth + gapWidth));
    
    if (columns <= 1) {
        cards.forEach(card => card.style.display = '');
        return;
    }

    // 表示すべき総数（列数の倍数）を算出
    const totalToDisplay = Math.floor(cards.length / columns) * columns;

    cards.forEach((card, index) => {
        if (index < totalToDisplay) {
            card.style.display = '';
        } else {
            card.style.display = 'none'; // 端数を隠す
        }
    });
};

export const initCardAnimations = () => {
    if (typeof gsap === 'undefined') return;

    const gridContainer = document.querySelector('.m3-post-grid__container');
    const selectors = [
        '.m3-card',
        '.m3-elevated-nav-card',
        '.special-features__item',
        '.m3-nexus-card',
        '.m3-blog-card',
        '.m3-product-card'
    ];

    const cards = document.querySelectorAll(selectors.join(', '));
    
    if (cards.length > 0) {
        // グリッドのバランス調整を実行
        if (gridContainer) {
            balanceGrid(gridContainer);
            window.addEventListener('resize', () => balanceGrid(gridContainer), { passive: true });
        }

        // 初期状態（高速化のため y移動を控えめに）
        gsap.set(cards, { 
            opacity: 0, 
            y: 40,
            scale: 0.95
        });

        const observerOptions = {
            threshold: 0.05, // より早く反応するように
            rootMargin: "0px 0px 50px 0px"
        };

        const observer = new IntersectionObserver((entries) => {
            // 表示されている（display: none でない）要素のみをアニメーション対象にする
            const visibleEntries = entries.filter(entry => entry.isIntersecting && entry.target.style.display !== 'none');
            
            if (visibleEntries.length > 0) {
                const elementsToAnimate = visibleEntries.map(entry => entry.target);
                
                // アニメーション高速化 (duration: 0.4s)
                gsap.to(elementsToAnimate, {
                    opacity: 1,
                    y: 0,
                    scale: 1,
                    duration: 0.4,
                    ease: "power2.out",
                    stagger: 0.05, // スタッガーも高速化
                    overwrite: true
                });

                elementsToAnimate.forEach(el => observer.unobserve(el));
            }
        }, observerOptions);

        cards.forEach(card => observer.observe(card));
    }
};

document.addEventListener('DOMContentLoaded', initCardAnimations);
