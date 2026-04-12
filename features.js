document.addEventListener('DOMContentLoaded', () => {
    // 1. ダークモード切り替え
    const themeToggleBtn = document.getElementById('theme-toggle');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');
    const lightIcon = document.getElementById('theme-toggle-light-icon');

    const setIcon = (theme) => {
        if (theme === 'dark') {
            if (darkIcon) darkIcon.classList.add('hidden');
            if (lightIcon) lightIcon.classList.remove('hidden');
        } else {
            if (lightIcon) lightIcon.classList.add('hidden');
            if (darkIcon) darkIcon.classList.remove('hidden');
        }
    };

    const currentTheme = localStorage.getItem('theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', currentTheme);
    setIcon(currentTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            setIcon(theme);
        });
    }

    // 2. スポイラー機能
    const spoilers = document.querySelectorAll('.node-spoiler');
    spoilers.forEach(spoiler => {
        spoiler.addEventListener('click', () => {
            spoiler.classList.add('is-revealed');
            spoiler.setAttribute('aria-expanded', 'true');
        });
        spoiler.setAttribute('tabindex', '0');
        spoiler.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') spoiler.click();
        });
    });

    // 3. CERO Z 警告ダイアログ
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

    // 6. シェア機能 (URLコピー)
    const copyBtn = document.querySelector('.m3-share-btn--copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
            await navigator.clipboard.writeText(window.location.href);
            const originalText = copyBtn.innerText;
            copyBtn.innerText = 'Copied!';
            setTimeout(() => { copyBtn.innerText = originalText; }, 2000);
        });
    }
});