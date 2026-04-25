document.addEventListener('DOMContentLoaded', () => {
    // 1. ダークモード切り替え
    const themeToggleBtn = document.getElementById('theme-toggle');
    const setIcon = (theme) => {
        const darkIcon = document.getElementById('theme-toggle-dark-icon');
        const lightIcon = document.getElementById('theme-toggle-light-icon');
        if (theme === 'dark') {
            if (darkIcon) darkIcon.classList.remove('hidden');
            if (lightIcon) lightIcon.classList.add('hidden');
        } else {
            if (lightIcon) lightIcon.classList.remove('hidden');
            if (darkIcon) darkIcon.classList.add('hidden');
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

    // --- M3 Constants ---
    const M3_EASE = "expo.out"; 

    // 2. 検索バーの伸縮制御 (GSAP)
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.querySelector('.m3-search-bar__input');

    if (searchToggle && searchBar && searchInput && typeof gsap !== 'undefined') {
        let isSearchOpen = false;
        gsap.set(searchInput, { opacity: 0, scaleX: 0.8, x: 10 });

        searchToggle.addEventListener('click', (e) => {
            if (!isSearchOpen) {
                e.preventDefault();
                isSearchOpen = true;
                searchBar.classList.add('is-active');
                gsap.to(searchInput, {
                    duration: 0.5,
                    opacity: 1,
                    scaleX: 1,
                    x: 0,
                    ease: M3_EASE,
                    onComplete: () => searchInput.focus()
                });
            } else if (searchInput.value === '') {
                isSearchOpen = false;
                searchBar.classList.remove('is-active');
                gsap.to(searchInput, {
                    duration: 0.4,
                    opacity: 0,
                    scaleX: 0.8,
                    x: 10,
                    ease: "power2.in"
                });
            } else {
                searchBar.submit();
            }
        });

        document.addEventListener('click', (e) => {
            if (!searchBar.contains(e.target) && isSearchOpen) {
                isSearchOpen = false;
                searchBar.classList.remove('is-active');
                gsap.to(searchInput, { duration: 0.4, opacity: 0, scaleX: 0.8, x: 10, ease: "power2.in" });
            }
        });
    }

    // 2.2 M3 ダイナミック・リップル・エフェクト (GSAP / Touch Support)
    if (typeof gsap !== 'undefined') {
        const createRipple = (e, el) => {
            const rect = el.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            
            // Handle touch vs mouse
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            
            const x = clientX - rect.left;
            const y = clientY - rect.top;

            const ripple = document.createElement('span');
            ripple.className = 'm3-ripple';
            ripple.style.width = ripple.style.height = `${size * 2}px`;
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            
            el.appendChild(ripple);

            gsap.fromTo(ripple, 
                { scale: 0, opacity: 0.15 },
                {
                    scale: 1,
                    duration: 0.5,
                    ease: "power2.out"
                }
            );

            const removeRipple = () => {
                gsap.to(ripple, {
                    opacity: 0,
                    duration: 0.3,
                    onComplete: () => ripple.remove()
                });
                el.removeEventListener('mouseup', removeRipple);
                el.removeEventListener('touchend', removeRipple);
                el.removeEventListener('mouseleave', removeRipple);
            };
            
            el.addEventListener('mouseup', removeRipple);
            el.addEventListener('touchend', removeRipple);
            el.addEventListener('mouseleave', removeRipple);
        };

        const rippleSelectors = '.m3-card, .m3-button, .m3-btn, .m3-icon-button, .toolbar-button, .m3-label--category, .page-numbers';
        document.querySelectorAll(rippleSelectors).forEach(el => {
            el.classList.add('m3-ripple-host');
            el.addEventListener('mousedown', (e) => createRipple(e, el));
            el.addEventListener('touchstart', (e) => createRipple(e, el), { passive: true });
        });
    }

    // 2.5 ナビゲーションドロワー制御
    const menuBtn = document.querySelector('.m3-header__menu');
    const drawer = document.getElementById('m3-drawer');
    const scrim = document.getElementById('m3-drawer-scrim');

    if (menuBtn && drawer && scrim) {
        const toggleDrawer = (open) => {
            drawer.classList.toggle('is-open', open);
            scrim.classList.toggle('is-visible', open);
            document.body.style.overflow = open ? 'hidden' : '';
        };

        menuBtn.addEventListener('click', () => toggleDrawer(true));
        scrim.addEventListener('click', () => toggleDrawer(false));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') toggleDrawer(false);
        });
    }

    // 3. URLコピー
    const copyBtn = document.getElementById('m3-copy-trigger');
    if (copyBtn) {
        const copyIcon = copyBtn.querySelector('.m3-copy-icon');
        const copyLabel = copyBtn.querySelector('.m3-copy-label');

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(window.location.href);
                copyBtn.classList.add('is-success');
                if (copyIcon) copyIcon.textContent = 'check';
                setTimeout(() => {
                    copyBtn.classList.remove('is-success');
                    if (copyIcon) copyIcon.textContent = 'content_copy';
                }, 2000);
            } catch (err) {}
        });
    }

    // 6. Adaptive Header
    const header = document.querySelector('.m3-header');
    if (header) {
        let isScrolled = false;
        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY > 20;
            if (scrolled && !isScrolled) {
                isScrolled = true;
                header.style.backgroundColor = "var(--md-sys-color-surface-container)";
                header.style.borderBottomColor = "var(--md-sys-color-outline-variant)";
            } else if (!scrolled && isScrolled) {
                isScrolled = false;
                header.style.backgroundColor = "transparent";
                header.style.borderBottomColor = "transparent";
            }
        }, { passive: true });
    }
});
