/**
 * System Share & Action Logic
 */
(function() {
    // コピー処理の共通関数
    const executeCopy = async (btn, url) => {
        if (btn.classList.contains('is-success')) return;

        const targetUrl = url || window.location.href;
        let success = false;

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(targetUrl);
                success = true;
            } else {
                throw new Error("Clipboard API unsupported");
            }
        } catch (err) {
            // フォールバック: textareaを使用したコピー
            const textarea = document.createElement('textarea');
            textarea.value = targetUrl;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                success = document.execCommand('copy');
            } catch (e) {
                success = false;
            }
            document.body.removeChild(textarea);
        }

        if (success) {
            btn.classList.add('is-success');

            const copyIcon = btn.querySelector('.m3-copy-icon') || btn.querySelector('.material-symbols-outlined');
            const copyLabel = btn.querySelector('.m3-copy-label') || btn.querySelector('.m3-share-btn__label');
            
            const originalIcon = copyIcon ? copyIcon.textContent : null;
            const originalLabel = copyLabel ? copyLabel.textContent : null;

            if (copyIcon) copyIcon.textContent = 'check';
            if (copyLabel) copyLabel.textContent = 'コピーしました！';

            if (typeof gsap !== 'undefined') {
                gsap.fromTo(btn, { scale: 0.92 }, { scale: 1, duration: 0.5, ease: "elastic.out(1, 0.3)" });
            }

            setTimeout(() => {
                btn.classList.remove('is-success');
                if (copyIcon && originalIcon) copyIcon.textContent = originalIcon;
                if (copyLabel && originalLabel) copyLabel.textContent = originalLabel;
            }, 2500);
        } else {
            console.error('Copy failed');
        }
    };

    // DOMContentLoadedを待つ
    document.addEventListener('DOMContentLoaded', () => {
        // 最上部へ戻る & コメントへ移動のスクロール処理
        const backToTopBtn = document.getElementById('m3-back-to-top');
        const scrollToCommentsBtn = document.getElementById('m3-scroll-to-comments');
        const commentsArea = document.getElementById('comments');

        if (backToTopBtn) {
            backToTopBtn.addEventListener('click', () => {
                if (typeof gsap !== 'undefined') {
                    gsap.to(window, { duration: 0.8, scrollTo: 0, ease: "power3.inOut" });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        }

        if (scrollToCommentsBtn && commentsArea) {
            scrollToCommentsBtn.addEventListener('click', () => {
                if (typeof gsap !== 'undefined') {
                    gsap.to(window, { duration: 0.8, scrollTo: { y: commentsArea, offsetY: 20 }, ease: "power3.inOut" });
                } else {
                    commentsArea.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }

        // スクロールに応じたFABの表示・非表示
        const handleActionStackVisibility = () => {
            const scrollY = window.scrollY;
            if (backToTopBtn) {
                if (scrollY > 400) backToTopBtn.classList.add('is-visible');
                else backToTopBtn.classList.remove('is-visible');
            }

            if (scrollToCommentsBtn && commentsArea) {
                const rect = commentsArea.getBoundingClientRect();
                // コメントエリアが画面外にある場合のみ移動ボタンを表示
                if (scrollY > 400 && rect.top > window.innerHeight) {
                    scrollToCommentsBtn.classList.add('is-visible');
                } else {
                    scrollToCommentsBtn.classList.remove('is-visible');
                }
            }
        };

        window.addEventListener('scroll', handleActionStackVisibility, { passive: true });
        handleActionStackVisibility(); // 初期実行

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.m3-share-btn');
            if (!btn) return;

            const isSystemShare = btn.id === 'm3-system-share-trigger' || btn.classList.contains('m3-share-btn--system');
            const isCopyBtn = btn.id === 'm3-copy-trigger' || btn.classList.contains('m3-share-btn--copy');

            // システムシェア または コピーボタンの場合
            if (isSystemShare || isCopyBtn) {
                e.stopImmediatePropagation();
                e.preventDefault();

                const urlToShare = btn.dataset.url || window.location.href;

                if (isSystemShare && navigator.share) {
                    try {
                        await navigator.share({
                            title: document.title,
                            url: urlToShare
                        });
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            executeCopy(btn, urlToShare);
                        }
                    }
                } else {
                    // コピーボタン、またはシステムシェア非対応時
                    executeCopy(btn, urlToShare);
                }
            }
        }, { capture: true });
    });
})();
