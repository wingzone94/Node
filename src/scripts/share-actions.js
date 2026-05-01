/**
 * System Share & Action Logic
 * 
 * Note: FAB visibility and scroll logic has been moved to main.js to avoid conflicts.
 * This script now only handles sharing functionalities.
 */
(function() {
    const executeCopy = async (btn, url) => {
        if (btn.classList.contains('is-success')) return;
        const targetUrl = url || window.location.href;
        let success = false;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(targetUrl);
                success = true;
            } else { throw new Error(); }
        } catch (err) {
            const textarea = document.createElement('textarea');
            textarea.value = targetUrl; textarea.style.position = 'fixed'; textarea.style.opacity = '0';
            document.body.appendChild(textarea); textarea.select();
            try { success = document.execCommand('copy'); } catch (e) { success = false; }
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
            if (typeof gsap !== 'undefined') gsap.fromTo(btn, { scale: 0.92 }, { scale: 1, duration: 0.5, ease: "elastic.out(1, 0.3)" });
            setTimeout(() => {
                btn.classList.remove('is-success');
                if (copyIcon && originalIcon) copyIcon.textContent = originalIcon;
                if (copyLabel && originalLabel) copyLabel.textContent = originalLabel;
            }, 2500);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.m3-share-btn');
            if (!btn) return;
            const isSystemShare = btn.id === 'm3-system-share-trigger' || btn.classList.contains('m3-share-btn--system');
            const isCopyBtn = btn.id === 'm3-copy-trigger' || btn.classList.contains('m3-share-btn--copy');
            if (isSystemShare || isCopyBtn) {
                e.stopImmediatePropagation(); e.preventDefault();
                const urlToShare = btn.dataset.url || window.location.href;
                if (isSystemShare && navigator.share) {
                    try { await navigator.share({ title: document.title, url: urlToShare }); }
                    catch (err) { if (err.name !== 'AbortError') executeCopy(btn, urlToShare); }
                } else { executeCopy(btn, urlToShare); }
            }
        }, { capture: true });
    });
})();
