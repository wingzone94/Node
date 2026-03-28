document.addEventListener('DOMContentLoaded', () => {
    // スポイラー機能
    const spoilers = document.querySelectorAll('.node-spoiler');
    spoilers.forEach(spoiler => {
        spoiler.addEventListener('click', () => {
            spoiler.classList.add('is-revealed');
            spoiler.setAttribute('aria-expanded', 'true');
        });
        // キーボード操作対応
        spoiler.setAttribute('tabindex', '0');
        spoiler.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') spoiler.click();
        });
    });

    // CERO Z 警告ダイアログ
    const ceroDialog = document.getElementById('cero-z-dialog');
    if (ceroDialog) {
        const hasAccepted = sessionStorage.getItem('cero_z_accepted');

        if (!hasAccepted) {
            ceroDialog.showModal();
        }

        document.getElementById('cero-z-accept').addEventListener('click', () => {
            sessionStorage.setItem('cero_z_accepted', 'true');
            ceroDialog.close();
        });

        document.getElementById('cero-z-decline').addEventListener('click', () => {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/';
            }
        });
    }
});