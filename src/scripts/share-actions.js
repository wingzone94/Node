/**
 * System Share & Action Logic
 * 
 * Note: FAB visibility and scroll logic has been moved to main.js to avoid conflicts.
 * This script now only handles sharing functionalities.
 */
(function() {
    const openSharePopup = (link) => {
        if (window.matchMedia('(max-width: 599px)').matches) return false;

        const width = 640;
        const height = 720;
        const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
        const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || screen.width;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || screen.height;
        const left = Math.max(0, dualScreenLeft + (viewportWidth - width) / 2);
        const top = Math.max(0, dualScreenTop + (viewportHeight - height) / 2);
        const features = `scrollbars=yes,resizable=yes,width=${width},height=${height},left=${left},top=${top}`;
        const popup = window.open(link.href, 'node-share-window', features);

        if (!popup) return false;
        popup.opener = null;
        popup.focus();
        return true;
    };

    const getSharePayload = (btn) => {
        const url = btn.dataset.url || window.location.href;
        const rawTitle = btn.dataset.shareTitle || document.querySelector('meta[property="og:title"]')?.content || document.title || '';
        const title = rawTitle.replace(/\s+/g, ' ').trim();

        return {
            title,
            url,
            text: title ? `${title} ${url}` : url,
        };
    };

    const executeCopy = async (btn, text) => {
        if (btn.classList.contains('is-success')) return;
        const targetText = text || getSharePayload(btn).text;
        let success = false;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(targetText);
                success = true;
            } else { throw new Error(); }
        } catch (err) {
            const textarea = document.createElement('textarea');
            textarea.value = targetText; textarea.style.position = 'fixed'; textarea.style.opacity = '0';
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

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.m3-share-btn');
        if (!btn) return;
        const isSystemShare = btn.id === 'm3-system-share-trigger' || btn.classList.contains('m3-share-btn--system');
        const isCopyBtn = btn.id === 'm3-copy-trigger' || btn.classList.contains('m3-share-btn--copy');
        const isShareLink = btn.matches('a[data-share-popup="true"]');

        if (isShareLink && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey && e.button === 0) {
            if (openSharePopup(btn)) {
                e.preventDefault();
                e.stopPropagation();
            }
            return;
        }

        if (isSystemShare || isCopyBtn) {
            e.stopImmediatePropagation(); e.preventDefault();
            const sharePayload = getSharePayload(btn);
            if (isSystemShare && navigator.share) {
                try { await navigator.share({ title: sharePayload.title || document.title, url: sharePayload.url }); }
                catch (err) { if (err.name !== 'AbortError') executeCopy(btn, sharePayload.text); }
            } else { executeCopy(btn, sharePayload.text); }
        }
    }, { capture: true });
})();
