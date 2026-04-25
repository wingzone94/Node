import { extractColorFromImage } from './colorExtractor';
import { generateM3Colors } from './theme';
import { storage } from './storage';

document.addEventListener('DOMContentLoaded', async () => {
    // GSAP global config
    if (typeof gsap !== 'undefined') {
        gsap.config({ force3D: true });
    }

    // 1. カラー抽出ロジック
    initColorExtraction();

    // 2. ダークモード切り替え
    initDarkMode();

    // 3. 伸縮検索バー (GSAP Enhanced)
    initSearchBar();

    // 4. ナビゲーションドロワー
    initDrawer();

    // 5. リンクコピー & Web Share API
    initShareFeatures();

    // 6. 目次 & FAB
    initTableOfContents();
    initCommentFAB();

    // 7. M3 Rubber Banding / Stretch Effect (GSAP)
    initOverdriveScroll();

    // 8. Keyboard Shortcuts
    initKeyboardShortcuts();

    // 9. M3 Dynamic Tooltips
    initTooltips();

    // 10. M3 Dynamic Ripple Effect
    initRippleEffect();

    // 11. Adaptive Header
    initAdaptiveHeader();

    // 12. Index Post Cards Floating Animation
    initIndexCardsAnimation();
});

async function initColorExtraction() {
    const labels = document.querySelectorAll('.m3-label--category');
    if (!labels.length) return;

    for (const label of labels) {
        const colorVal = label.dataset.color;
        const thumbUrl = label.dataset.thumb;
        const cacheId = `${label.textContent.trim()}_${thumbUrl || 'no-img'}`;

        try {
            if (colorVal && colorVal.startsWith('#')) {
                const colors = generateM3Colors(colorVal);
                applyM3Colors(label, colors);
                continue;
            }

            if (colorVal === 'auto' && thumbUrl) {
                const cached = storage.get(cacheId);
                if (cached) {
                    applyM3Colors(label, cached);
                    continue;
                }

                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.src = thumbUrl;
                label.style.opacity = '0.6';

                const rgb = await extractColorFromImage(img);
                const colors = generateM3Colors(rgb);
                applyM3Colors(label, colors);
                storage.set(cacheId, colors);
                label.style.opacity = '1';
            } else {
                const colors = generateM3Colors('#6750A4');
                applyM3Colors(label, colors);
            }
        } catch (err) {
            const colors = generateM3Colors('#6750A4');
            applyM3Colors(label, colors);
        }
    }
}

function applyM3Colors(el, colors) {
    el.style.setProperty('--md-sys-color-secondary-container', colors.secondaryContainer);
    el.style.setProperty('--md-sys-color-on-secondary-container', colors.onSecondaryContainer);
}

function initDarkMode() {
    const themeControls = document.getElementById('m3-theme-controls');
    const themeToggleBtn = document.getElementById('theme-toggle');
    const popover = document.getElementById('theme-popover');
    const syncToggle = document.getElementById('theme-sync-toggle');
    const mql = window.matchMedia('(prefers-color-scheme: dark)');

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

    const updateTheme = (source = 'auto') => {
        const isSyncOn = localStorage.getItem('theme-sync') !== 'false';
        
        if (syncToggle) {
            syncToggle.checked = isSyncOn;
        }

        // 同期中であってもボタンは常に有効にする（クリックで同期解除するため）
        if (themeToggleBtn) {
            themeToggleBtn.style.opacity = '1';
            themeToggleBtn.style.pointerEvents = 'auto';
        }

        let newTheme;
        if (isSyncOn) {
            newTheme = mql.matches ? 'dark' : 'light';
        } else {
            newTheme = localStorage.getItem('theme') || (mql.matches ? 'dark' : 'light');
        }

        document.documentElement.setAttribute('data-theme', newTheme);
        setIcon(newTheme);
    };

    // Listen to system changes
    mql.addEventListener('change', () => {
        if (localStorage.getItem('theme-sync') !== 'false') {
            updateTheme('system');
        }
    });

    // Handle Manual Toggle
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            localStorage.setItem('theme-sync', 'false');
            if (syncToggle) syncToggle.checked = false;
            
            localStorage.setItem('theme', targetTheme);
            updateTheme('manual');
        });
    }

    // Handle Sync Toggle
    if (syncToggle) {
        syncToggle.addEventListener('change', (e) => {
            const isSyncOn = e.target.checked;
            if (isSyncOn) {
                localStorage.removeItem('theme-sync'); // Default is on
            } else {
                localStorage.setItem('theme-sync', 'false');
                localStorage.setItem('theme', mql.matches ? 'dark' : 'light');
            }
            updateTheme('sync-toggle');
        });
    }

    // Popover Hover / Long Press Logic
    if (themeControls && popover && typeof gsap !== 'undefined') {
        let hoverTimeout;
        const isTouch = window.matchMedia('(pointer: coarse)').matches;

        const showPopover = () => {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                popover.classList.add('is-active');
                gsap.fromTo(popover, 
                    { autoAlpha: 0, y: 15 },
                    { autoAlpha: 1, y: 0, duration: 0.4, ease: "back.out(1.5)", overwrite: true }
                );
            }, 300); // 300ms Intent delay
        };

        const hidePopover = () => {
            clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                gsap.to(popover, {
                    autoAlpha: 0, y: 10, duration: 0.25, ease: "power2.in", overwrite: true,
                    onComplete: () => popover.classList.remove('is-active')
                });
            }, 150); // Small debounce
        };

        if (!isTouch) {
            themeControls.addEventListener('mouseenter', showPopover);
            themeControls.addEventListener('mouseleave', hidePopover);
            
            // Allow focus-in to trigger it too
            themeToggleBtn.addEventListener('focus', showPopover);
            themeToggleBtn.addEventListener('blur', hidePopover);
        } else {
            let pressTimer;
            themeControls.addEventListener('touchstart', () => {
                pressTimer = setTimeout(showPopover, 500); // Long press
            }, { passive: true });
            
            const cancelPress = () => clearTimeout(pressTimer);
            themeControls.addEventListener('touchend', cancelPress);
            themeControls.addEventListener('touchmove', cancelPress);
            
            document.addEventListener('touchstart', (e) => {
                if (!themeControls.contains(e.target)) {
                    hidePopover();
                }
            }, { passive: true });
        }
    }

    updateTheme('init');
}

function initSearchBar() {
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.querySelector('.m3-search-bar__input');

    if (searchToggle && searchBar && searchInput && typeof gsap !== 'undefined') {
        let isSearchOpen = false;
        const M3_EASE = "expo.out"; 
        
        // Init state
        gsap.set(searchInput, { opacity: 0, scaleX: 0.8, x: 10 });

        const toggleSearch = (open) => {
            if (open) {
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
            } else {
                isSearchOpen = false;
                searchBar.classList.remove('is-active');
                gsap.to(searchInput, {
                    duration: 0.4,
                    opacity: 0,
                    scaleX: 0.8,
                    x: 10,
                    ease: "power2.in",
                    onComplete: () => searchInput.blur()
                });
            }
        };

        searchToggle.addEventListener('click', (e) => {
            if (!isSearchOpen) {
                e.preventDefault();
                toggleSearch(true);
            } else if (searchInput.value === '') {
                toggleSearch(false);
            } else {
                searchBar.submit();
            }
        });

        document.addEventListener('click', (e) => {
            if (!searchBar.contains(e.target) && isSearchOpen) {
                toggleSearch(false);
            }
        });
    }
}

function initDrawer() {
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
    }
}

function initShareFeatures() {
    const shareBtns = document.querySelectorAll('.m3-share-btn');
    const pageUrl = window.location.href;
    const title = document.title;

    const executeCopyFallback = async (btn, urlToCopy) => {
        const copyBtn = btn;
        const targetUrl = urlToCopy || pageUrl;
        const copyIcon = copyBtn.querySelector('.m3-copy-icon');
        const copyLabel = copyBtn.querySelector('.m3-copy-label');
        let success = false;

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(targetUrl);
                success = true;
            } else {
                throw new Error("Clipboard API unsupported");
            }
        } catch (err) {
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
            copyBtn.classList.add('is-success');
            const originalText = copyLabel ? (copyLabel.dataset.original || copyLabel.textContent) : 'リンクをコピー';
            if (copyLabel && !copyLabel.dataset.original) copyLabel.dataset.original = originalText;
            
            if (copyIcon) copyIcon.textContent = 'check';
            if (copyLabel) copyLabel.textContent = 'コピーしました！'; 
            
            if (typeof gsap !== 'undefined') {
                gsap.fromTo(copyBtn, { scale: 0.95 }, { scale: 1, duration: 0.3, ease: "back.out(2, 0.5)" });
            }

            setTimeout(() => {
                copyBtn.classList.remove('is-success');
                if (copyIcon) copyIcon.textContent = 'content_copy';
                if (copyLabel) copyLabel.textContent = originalText;
            }, 2500); 
        } else {
            alert('コピーに失敗しました。');
        }
    };

    shareBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const urlToShare = btn.dataset.url || pageUrl;

            if (btn.id === 'm3-copy-trigger' || btn.classList.contains('m3-share-btn--copy')) {
                e.preventDefault();
                executeCopyFallback(btn, urlToShare);
                return;
            }

            if (navigator.share) {
                e.preventDefault();
                try {
                    await navigator.share({ title: title, url: urlToShare });
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        executeCopyFallback(btn, urlToShare);
                    }
                }
            }
        });
    });
}

function initTableOfContents() {
    const articleBody = document.querySelector('.m3-article__body');
    const tocContainer = document.getElementById('m3-toc-container');
    const stickyToc = document.getElementById('m3-sticky-toc');
    const tocTrigger = document.getElementById('m3-toc-trigger');
    const closeBtn = document.getElementById('m3-toc-close');

    if (!articleBody || !tocContainer || !stickyToc || !tocTrigger) return;

    const headings = articleBody.querySelectorAll('h2, h3');
    if (headings.length === 0) {
        tocTrigger.remove();
        stickyToc.remove();
        return;
    }

    const tocList = document.createElement('ul');
    headings.forEach((heading, index) => {
        const id = heading.id || `m3-heading-${index}`;
        heading.id = id;
        const li = document.createElement('li');
        li.className = `toc-level-${heading.tagName.toLowerCase()}`;
        const a = document.createElement('a');
        a.href = `#${id}`;
        a.textContent = heading.textContent.trim();
        
        a.addEventListener('click', (e) => {
            e.preventDefault();
            toggleToc(false);
            const target = document.getElementById(id);
            if (target && typeof gsap !== 'undefined') {
                gsap.to(window, { duration: 0.8, scrollTo: { y: target, offsetY: 80 }, ease: "power3.inOut" });
            }
        });

        li.appendChild(a);
        tocList.appendChild(li);
    });
    tocContainer.innerHTML = '';
    tocContainer.appendChild(tocList);

    const toggleToc = (show) => {
        if (show) {
            stickyToc.classList.add('is-active');
            gsap.fromTo(stickyToc, 
                { autoAlpha: 0, y: 20, scale: 0.95 },
                { autoAlpha: 1, y: 0, scale: 1, duration: 0.4, ease: "back.out(1.2)" }
            );
        } else {
            gsap.to(stickyToc, { 
                autoAlpha: 0, y: 15, scale: 0.95, duration: 0.3, ease: "power2.in",
                onComplete: () => stickyToc.classList.remove('is-active')
            });
        }
    };

    tocTrigger.addEventListener('click', () => {
        const isActive = stickyToc.classList.contains('is-active');
        toggleToc(!isActive);
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', () => toggleToc(false));
    }

    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            tocTrigger.classList.add('is-visible');
        } else {
            tocTrigger.classList.remove('is-visible');
            if (stickyToc.classList.contains('is-active')) toggleToc(false);
        }
    }, { passive: true });
}

function initCommentFAB() {
    const scrollToCommentsBtn = document.getElementById('m3-scroll-to-comments');
    const backToTopBtn = document.getElementById('m3-back-to-top');
    const commentSection = document.getElementById('comments');

    if (!scrollToCommentsBtn || !backToTopBtn) return;

    const updateFABs = () => {
        const scrollY = window.scrollY;
        
        if (scrollY > 400) {
            backToTopBtn.classList.add('is-visible');
        } else {
            backToTopBtn.classList.remove('is-visible');
        }

        if (commentSection) {
            const rect = commentSection.getBoundingClientRect();
            if (scrollY > 600 && rect.top > window.innerHeight) {
                scrollToCommentsBtn.classList.add('is-visible');
            } else {
                scrollToCommentsBtn.classList.remove('is-visible');
            }
        }
    };

    window.addEventListener('scroll', updateFABs, { passive: true });

    backToTopBtn.addEventListener('click', () => {
        if (typeof gsap !== 'undefined') {
            gsap.to(window, { duration: 0.8, scrollTo: 0, ease: "power3.inOut" });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    scrollToCommentsBtn.addEventListener('click', () => {
        if (commentSection) {
            if (typeof gsap !== 'undefined') {
                gsap.to(window, { duration: 0.8, scrollTo: { y: commentSection, offsetY: 20 }, ease: "power3.inOut" });
            } else {
                commentSection.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });
}

function initOverdriveScroll() {
    if (typeof gsap === 'undefined' || !document.querySelector('.m3-page-container')) return;

    const container = document.querySelector('.m3-page-container');
    
    const stretchEffect = () => {
        const scrollY = window.scrollY;
        const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        
        if (scrollY < 0) {
            const stretch = Math.min(Math.abs(scrollY) / 200, 0.1);
            gsap.set(container, { scaleY: 1 + stretch, transformOrigin: "top center" });
        } else if (scrollY > maxScroll) {
            const stretch = Math.min((scrollY - maxScroll) / 200, 0.1);
            gsap.set(container, { scaleY: 1 + stretch, transformOrigin: "bottom center" });
        } else {
            gsap.to(container, { scaleY: 1, duration: 0.3, ease: "power2.out" });
        }
        requestAnimationFrame(stretchEffect);
    };

    requestAnimationFrame(stretchEffect);
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
            if (e.key === 'Escape') e.target.blur();
            return;
        }

        switch (e.key) {
            case '/':
                e.preventDefault();
                const searchToggle = document.getElementById('search-toggle');
                if (searchToggle) searchToggle.click();
                break;
            case 'Escape':
                const drawer = document.getElementById('m3-drawer');
                if (drawer && drawer.classList.contains('is-open')) document.getElementById('m3-drawer-scrim').click();
                const searchBar = document.querySelector('.m3-search-bar');
                if (searchBar && searchBar.classList.contains('is-active')) document.getElementById('search-toggle').click();
                break;
        }
    });
}

function initTooltips() {
    if (typeof gsap === 'undefined') return;
    const tooltip = document.createElement('div');
    tooltip.className = 'm3-dynamic-tooltip';
    document.body.appendChild(tooltip);

    const isTouch = window.matchMedia('(pointer: coarse)').matches;

    const showTooltip = (target) => {
        if (isTouch) return;
        const text = target.getAttribute('data-tooltip') || target.getAttribute('title') || '';
        if (!text) return;

        tooltip.textContent = text;
        
        // 1. まず表示させてからサイズを取得する (不透明度0で)
        gsap.set(tooltip, { display: 'block', autoAlpha: 0, scale: 0.8 });
        
        const rect = target.getBoundingClientRect();
        const tipWidth = tooltip.offsetWidth;
        const tipHeight = tooltip.offsetHeight;
        
        let x = rect.left + (rect.width / 2) - (tipWidth / 2);
        let y = rect.top - tipHeight - 12; // 要素の上に表示

        // 画面外はみ出し防止
        x = Math.max(12, Math.min(x, window.innerWidth - tipWidth - 12));
        if (y < 12) y = rect.bottom + 12; // 上に入らなければ下へ

        gsap.set(tooltip, { x: x, y: y });
        gsap.to(tooltip, { autoAlpha: 1, scale: 1, duration: 0.25, ease: "power2.out" });
    };

    const hideTooltip = () => gsap.to(tooltip, { autoAlpha: 0, scale: 0.8, duration: 0.2, overwrite: true });

    // 動的な要素にも対応するためにイベント委譲を使用
    document.body.addEventListener('mouseenter', (e) => {
        const target = e.target.closest('.m3-tooltip-target, [data-tooltip]');
        if (target) showTooltip(target);
    }, true);

    document.body.addEventListener('mouseleave', (e) => {
        const target = e.target.closest('.m3-tooltip-target, [data-tooltip]');
        if (target) hideTooltip();
    }, true);
}

function initRippleEffect() {
    if (typeof gsap === 'undefined') return;

    const createRipple = (e, el) => {
        const rect = el.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
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

        gsap.fromTo(ripple, { scale: 0, opacity: 0.15 }, { scale: 1, duration: 0.5, ease: "power2.out" });

        const removeRipple = () => {
            gsap.to(ripple, { opacity: 0, duration: 0.3, onComplete: () => ripple.remove() });
        };
        el.addEventListener('mouseup', removeRipple, { once: true });
        el.addEventListener('touchend', removeRipple, { once: true });
        el.addEventListener('mouseleave', removeRipple, { once: true });
    };

    const rippleSelectors = '.m3-card, .m3-button, .m3-btn, .m3-icon-button, .m3-label--category, .page-numbers, .m3-share-btn, .m3-elevated-nav-card';
    document.querySelectorAll(rippleSelectors).forEach(el => {
        el.classList.add('m3-ripple-host');
        el.addEventListener('mousedown', (e) => createRipple(e, el));
        el.addEventListener('touchstart', (e) => createRipple(e, el), { passive: true });
    });
}

function initAdaptiveHeader() {
    const header = document.querySelector('.m3-header');
    if (!header) return;
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

function initIndexCardsAnimation() {
    if (typeof gsap === 'undefined') return;
    
    const cards = document.querySelectorAll('.m3-post-grid .m3-card, .special-features__item');
    if (!cards.length) return;

    // 初期状態として非表示＆少し下に下げておく
    gsap.set(cards, { autoAlpha: 0, y: 40, scale: 0.95 });

    // Stagger (ずらし) アニメーションを再生
    gsap.to(cards, {
        autoAlpha: 1,
        y: 0,
        scale: 1,
        duration: 0.6,
        stagger: 0.08,
        ease: "back.out(1.2)",
    });
}
