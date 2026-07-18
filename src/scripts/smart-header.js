export function initSmartHeader() {
    const header = document.querySelector('.m3-header');
    if (!header) return;

    let lastScrollY = window.scrollY || window.pageYOffset;
    let ticking = false;
    const isSearchActive = () => document.querySelector('.m3-search-bar.is-active') !== null;
    const isModalActive = () => document.querySelector('.m3-modal.is-active') !== null;

    const adminBar = document.body.classList.contains('admin-bar')
        ? document.getElementById('wpadminbar')
        : null;

    const syncAdminBarOffset = () => {
        if (!adminBar) return;

        // モバイル幅(600px以下)では管理バーが position:absolute になり
        // スクロールと共に画面外へ出るため、ヘッダーの top を
        // 管理バーの「見えている下端」に追従させる
        if (getComputedStyle(adminBar).position === 'absolute') {
            header.style.top = `${Math.max(0, Math.round(adminBar.getBoundingClientRect().bottom))}px`;
        } else if (header.style.top) {
            header.style.top = '';
        }
    };

    const updateHeader = () => {
        const currentScrollY = window.scrollY || window.pageYOffset;

        syncAdminBarOffset();

        if (currentScrollY <= 80) {
            header.classList.remove('is-hidden');
        } else if (currentScrollY > lastScrollY && !isSearchActive() && !isModalActive()) {
            if (currentScrollY - lastScrollY > 10) {
                header.classList.add('is-hidden');
            }
        } else if (lastScrollY - currentScrollY > 10) {
            header.classList.remove('is-hidden');
        }

        lastScrollY = currentScrollY;
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }, { passive: true });

    if (adminBar) {
        syncAdminBarOffset();
        window.addEventListener('resize', syncAdminBarOffset, { passive: true });
    }
}
