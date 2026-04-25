<footer class="m3-footer">
    <div class="m3-footer__content">


        <nav class="m3-footer__nav" aria-label="フッターナビゲーション">
            <ul class="m3-footer__links">
                <li><a href="<?php echo esc_url(home_url('/about/')); ?>">このブログについて</a></li>
                <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">プライバシーポリシー</a></li>
                <li><a href="<?php echo esc_url(home_url('/contact/')); ?>">お問い合わせ</a></li>
            </ul>
        </nav>

        <div class="m3-footer__meta">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?> - Luminous Core Teams</p>
        </div>
    </div>
</footer>

</div> <!-- .m3-page-container -->

<?php if (is_singular()) : ?>
    <!-- 追従ナビゲーションユニット -->
    <div class="m3-sticky-navigation">
        <!-- 追従目次パネル -->
        <div id="m3-sticky-toc" class="m3-sticky-toc">
            <div class="m3-sticky-toc__header">
                <span class="material-symbols-outlined m3-toc-icon">toc</span>
                <span class="m3-sticky-toc__title">目次</span>
                <button id="m3-toc-close" class="m3-toc-close-btn" aria-label="目次を閉じる">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="m3-toc-container" class="m3-toc-body"></div>
        </div>

        <!-- アクションボタンスタック -->
        <div class="m3-action-stack">
            <!-- 目次展開ボタン -->
            <button id="m3-toc-trigger" class="m3-fab m3-fab--tertiary" title="目次を表示" aria-label="目次を表示">
                <span class="material-symbols-outlined">list</span>
            </button>

            <!-- コメントへ移動ボタン -->
            <button id="m3-scroll-to-comments" class="m3-fab m3-fab--secondary" title="コメントへ移動" aria-label="コメントへ移動">
                <span class="material-symbols-outlined">comment</span>
                <?php 
                $comment_count = is_singular() ? get_comments_number(get_queried_object_id()) : 0;
                if ($comment_count > 0) : ?>
                    <span class="m3-fab__badge"><?php echo esc_html($comment_count); ?></span>
                <?php endif; ?>
            </button>

            <!-- 最上部へ戻るボタン -->
            <button id="m3-back-to-top" class="m3-fab m3-fab--primary" title="最上部へ戻る" aria-label="最上部へ戻る">
                <span class="material-symbols-outlined">arrow_upward</span>
            </button>
        </div>
    </div>
<?php endif; ?>

<?php if (is_home() || is_archive() || is_search()) : ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof gsap === 'undefined') return;
    const cards = document.querySelectorAll('.m3-post-grid .card-standard, .m3-post-grid .card-featured, .m3-spotlight-badge, .special-features__item');
    if (!cards.length) return;
    
    // 初期状態として非表示＆少し下に下げておく
    gsap.set(cards, { autoAlpha: 0, y: 40, scale: 0.95 });
    
    // Material Design らしい Stagger (ずらし) アニメーションを再生
    gsap.to(cards, {
        autoAlpha: 1, 
        y: 0, 
        scale: 1, 
        duration: 0.6, 
        stagger: 0.08, 
        ease: "back.out(1.2)"
    });
});
</script>
<?php endif; ?>

<script>
/**
 * System Share & Action Logic
 * ビルド環境に依存せず確実に動作させるためのホットパッチ
 */
/**
 * Share Logic Hotfix
 * 既存の built main.js の挙動をオーバーライドして正常化する
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
})();
</script>

<?php wp_footer(); ?>
</body>
</html>