import { extractColorFromImage } from './colorExtractor';
import { generateM3Colors } from './theme';
import { storage } from './storage';
import './scripts/card-animation';
import './scripts/share-actions';

document.addEventListener('DOMContentLoaded', async () => {
    if (typeof gsap !== 'undefined') gsap.config({ force3D: true });
    initColorExtraction();
    initDarkMode();
    initSearchBar();
    initDrawer();
    initShareFeatures();
    initTableOfContents(); // Handles TOC and FAB visibility
    initOverdriveScroll();
    initKeyboardShortcuts();
    initTooltips();
    initRippleEffect();
    initAdaptiveHeader();
    initReadingInfoBubble();
    initReadingProgress();
});

function initReadingInfoBubble() {
    const meta = document.getElementById('m3-reading-meta-toggle');
    if (!meta) return;

    meta.addEventListener('click', () => {
        meta.classList.toggle('is-info-active');
    });
}

async function initReadingProgress() {
    const progressBar = document.querySelector('.m3-header__progress-bar');
    const container = document.querySelector('.m3-header__progress-container');
    const article = document.querySelector('.m3-article__body');
    if (!progressBar || !container || !article) return;
    const updateProgress = () => {
        const rect = article.getBoundingClientRect();
        const articleTop = rect.top + window.pageYOffset;
        const articleHeight = rect.height;
        const windowHeight = window.innerHeight;
        const currentScroll = window.pageYOffset;
        const scrollStart = articleTop - 64; 
        let progress = currentScroll > scrollStart ? ((currentScroll - scrollStart) / (articleHeight - windowHeight)) * 100 : 0;
        progress = Math.min(100, Math.max(0, progress));
        progressBar.style.width = `${progress}%`;
        if (currentScroll > scrollStart && currentScroll < (articleTop + articleHeight - 100)) container.classList.add('is-visible');
        else container.classList.remove('is-visible');
    };
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
}

async function initColorExtraction() {
    const labels = document.querySelectorAll('.m3-label--category');
    for (const label of labels) {
        const colorVal = label.dataset.color; const thumbUrl = label.dataset.thumb;
        const cacheId = `${label.textContent.trim()}_${thumbUrl || 'no-img'}`;
        try {
            if (colorVal && colorVal.startsWith('#')) applyM3Colors(label, generateM3Colors(colorVal));
            else if (colorVal === 'auto' && thumbUrl) {
                const cached = storage.get(cacheId);
                if (cached) applyM3Colors(label, cached);
                else {
                    const img = new Image(); img.crossOrigin = "Anonymous"; img.src = thumbUrl;
                    const colors = await generateM3Colors(await extractColorFromImage(img));
                    applyM3Colors(label, colors); storage.set(cacheId, colors);
                }
            } else applyM3Colors(label, generateM3Colors('#FF9900'));
        } catch (err) { applyM3Colors(label, generateM3Colors('#FF9900')); }
    }
}
function applyM3Colors(el, colors) {
    el.style.setProperty('--md-sys-color-secondary-container', colors.secondaryContainer);
    el.style.setProperty('--md-sys-color-on-secondary-container', colors.onSecondaryContainer);
}

function initDarkMode() {
    const mql = window.matchMedia('(prefers-color-scheme: dark)');
    const updateTheme = () => document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || (mql.matches ? 'dark' : 'light'));
    mql.addEventListener('change', () => !localStorage.getItem('theme') && updateTheme());
    document.getElementById('theme-toggle')?.addEventListener('click', (e) => {
        e.preventDefault();
        localStorage.setItem('theme', document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        updateTheme();
    });
    updateTheme();
}

function initSearchBar() {
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.getElementById('m3-search-input');
    const searchClear = document.getElementById('m3-search-clear');
    const advancedTrigger = document.getElementById('m3-advanced-search-trigger');
    const modal = document.getElementById('m3-advanced-search-modal');
    const modalClose = document.getElementById('m3-advanced-search-close');
    const modalReset = document.getElementById('m3-advanced-search-reset');
    const modalApply = document.getElementById('m3-advanced-search-apply');
    const inputWrapper = document.querySelector('.m3-search-input-wrapper');

    if (!searchToggle || !searchBar || !searchInput) return;

    // --- Search Bar Toggle ---
    searchToggle.addEventListener('click', (e) => {
        if (!searchBar.classList.contains('is-active')) {
            searchBar.classList.add('is-active');
            setTimeout(() => searchInput.focus(), 300);
        } else if (!searchInput.value.trim()) {
            searchBar.classList.remove('is-active');
        } else {
            searchBar.submit();
        }
    });

    // --- Clear Button Visibility & Logic ---
    const updateClearBtn = () => {
        searchClear.style.display = searchInput.value ? 'flex' : 'none';
    };
    searchInput.addEventListener('input', updateClearBtn);
    searchClear.addEventListener('click', () => {
        searchInput.value = '';
        updateClearBtn();
        searchInput.focus();
    });
    updateClearBtn();

    // --- Advanced Search Modal ---
    const openModal = () => {
        modal.classList.add('is-active');
        document.body.style.overflow = 'hidden';
        switchPage(1); // 常に1ページ目から開始
        initRangeSlider();
    };

    // --- Multi-page Logic ---
    const switchPage = (pageNum) => {
        const pagesContainer = modal.querySelector('.m3-modal__pages-container');
        const pages = modal.querySelectorAll('.m3-modal__page');
        const tabs = modal.querySelectorAll('.m3-modal__tab');
        const indicator = modal.querySelector('.m3-modal__tab-indicator');
        const totalPages = pages.length;

        // Page Transform (100 / totalPages * (pageNum - 1))
        const movePercent = (100 / totalPages) * (pageNum - 1);
        pagesContainer.style.transform = `translateX(-${movePercent}%)`;

        // Active State
        pages.forEach(p => p.classList.toggle('is-active', p.dataset.page == pageNum));
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.page == pageNum));

        // Tab Indicator Position
        const activeTab = modal.querySelector(`.m3-modal__tab[data-page="${pageNum}"]`);
        if (activeTab && indicator) {
            indicator.style.width = `${activeTab.offsetWidth}px`;
            indicator.style.left = `${activeTab.offsetLeft}px`;
        }
    };

    modal.querySelectorAll('.m3-modal__tab').forEach(el => {
        el.addEventListener('click', () => switchPage(parseInt(el.dataset.page)));
    });


    const closeModal = () => {
        modal.classList.remove('is-active');
        document.body.style.overflow = '';
    };

    advancedTrigger.addEventListener('click', openModal);
    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // --- Range Slider Logic ---
    function initRangeSlider() {
        const slider = document.getElementById('m3-word-count-slider');
        const minHandle = slider.querySelector('.m3-range-slider__handle--min');
        const maxHandle = slider.querySelector('.m3-range-slider__handle--max');
        const range = slider.querySelector('.m3-range-slider__range');
        const minInput = document.getElementById('m3-min-chars');
        const maxInput = document.getElementById('m3-max-chars');
        
        let minVal = parseInt(minInput.value) || 0;
        let maxVal = parseInt(maxInput.value) || 10000;
        const totalMax = 10000;

        const updateAccentColor = () => {
            let color = 'var(--rank-long)';
            if (maxVal <= 2500) color = 'var(--rank-short)';
            else if (maxVal <= 5000) color = 'var(--rank-medium-short)';
            else if (maxVal <= 7500) color = 'var(--rank-standard)';
            else if (maxVal <= 9999) color = 'var(--rank-medium-long)';
            
            modal.style.setProperty('--md-sys-color-primary', color);
        };

        const updateUI = () => {
            const minPercent = (minVal / totalMax) * 100;
            const maxPercent = (maxVal / totalMax) * 100;
            minHandle.style.left = `${minPercent}%`;
            maxHandle.style.left = `${maxPercent}%`;
            range.style.left = `${minPercent}%`;
            range.style.width = `${maxPercent - minPercent}%`;
            minHandle.querySelector('.m3-range-slider__value').textContent = minVal;
            maxHandle.querySelector('.m3-range-slider__value').textContent = maxVal >= totalMax ? '10000+' : maxVal;
            minInput.value = minVal;
            maxInput.value = maxVal;
            updateAccentColor();
        };

        const handleDrag = (e, type) => {
            const rect = slider.getBoundingClientRect();
            const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
            let percent = Math.min(100, Math.max(0, (x / rect.width) * 100));
            let val = Math.round((percent / 100) * totalMax);

            if (type === 'min') {
                minVal = Math.min(val, maxVal - 500);
            } else {
                maxVal = Math.max(val, minVal + 500);
            }
            updateUI();
        };

        const onStart = (e, type) => {
            const move = (ev) => handleDrag(ev, type);
            const end = () => {
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', end);
                document.removeEventListener('touchmove', move);
                document.removeEventListener('touchend', end);
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', end);
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', end);
        };

        minHandle.addEventListener('mousedown', (e) => onStart(e, 'min'));
        maxHandle.addEventListener('mousedown', (e) => onStart(e, 'max'));
        minHandle.addEventListener('touchstart', (e) => onStart(e, 'min'), { passive: false });
        maxHandle.addEventListener('touchstart', (e) => onStart(e, 'max'), { passive: false });

        updateUI();
    }

    // --- Reading Time Chips Logic ---
    modal.querySelectorAll('input[name="m3_reading_time"]').forEach(input => {
        input.addEventListener('change', () => {
            const val = input.value;
            const minInput = document.getElementById('m3-min-chars');
            const maxInput = document.getElementById('m3-max-chars');
            
            if (val === 'short') { minInput.value = 0; maxInput.value = 2500; }
            else if (val === 'medium') { minInput.value = 2500; maxInput.value = 5000; }
            else if (val === 'long') { minInput.value = 5000; maxInput.value = 10000; }
            else { minInput.value = 0; maxInput.value = 10000; }

            // スライダーを再初期化して反映
            initRangeSlider();
        });
    });

    // --- Modal Logic ---
    modalReset.addEventListener('click', () => {
        modal.querySelectorAll('input, select').forEach(input => {
            if (input.tagName === 'SELECT') input.selectedIndex = 0;
            else if (input.type === 'checkbox') input.checked = false;
            else if (input.type === 'radio') {
                if (input.name === 'm3_ai' || input.name === 'm3_media' || input.name === 'm3_reading_time') {
                    input.checked = input.value === 'all';
                } else {
                    input.checked = false;
                }
            }
            else if (input.id === 'm3-min-chars') input.value = 0;
            else if (input.id === 'm3-max-chars') input.value = 10000;
            else input.value = '';
        });
        // スライダーをリセット
        initRangeSlider();
    });

    modalApply.addEventListener('click', () => {
        const loading = document.getElementById('m3-search-loading');
        if (loading) loading.classList.add('is-active');

        const params = new URLSearchParams();
        const sValue = searchInput.value.trim();
        if (sValue) params.append('s', sValue);

        // すべての入力要素を収集
        modal.querySelectorAll('input, select').forEach(input => {
            if (input.type === 'checkbox' && input.checked) params.append(input.name, input.value);
            if (input.type === 'radio' && input.checked) params.append(input.name, input.value);
            if (input.tagName === 'SELECT' && input.value) params.append(input.name, input.value);
            if ((input.type === 'text' || input.type === 'date' || input.type === 'hidden') && input.value) {
                params.append(input.name, input.value);
            }
        });

        // アニメーションを見せるための微小な遅延
        setTimeout(() => {
            window.location.href = `${window.location.origin}/?${params.toString()}`;
        }, 800);
    });
}



function initDrawer() {
    const menuBtn = document.querySelector('.m3-header__menu');
    const drawer = document.getElementById('m3-drawer');
    const scrim = document.getElementById('m3-drawer-scrim');
    if (menuBtn && drawer && scrim) {
        const toggle = (open) => { drawer.classList.toggle('is-open', open); scrim.classList.toggle('is-visible', open); document.body.style.overflow = open ? 'hidden' : ''; };
        menuBtn.addEventListener('click', () => toggle(true)); scrim.addEventListener('click', () => toggle(false));
    }
}

function initShareFeatures() {
    const shareBtns = document.querySelectorAll('.m3-share-btn');
    shareBtns.forEach(btn => btn.addEventListener('click', async (e) => {
        const url = btn.dataset.url || window.location.href;
        if (btn.id === 'm3-copy-trigger' || btn.classList.contains('m3-share-btn--copy')) {
            e.preventDefault(); try { await navigator.clipboard.writeText(url); alert('コピーしました！'); } catch(err){}
        } else if (navigator.share) {
            e.preventDefault(); try { await navigator.share({ title: document.title, url }); } catch(err){}
        }
    }));
}

function initTableOfContents() {
    const articleBody = document.querySelector('.m3-article__body');
    const tocContainer = document.getElementById('m3-toc-container');
    const stickyToc = document.getElementById('m3-sticky-toc');
    const tocTrigger = document.getElementById('m3-toc-trigger');
    const closeBtn = document.getElementById('m3-toc-close');
    const commentFab = document.getElementById('m3-scroll-to-comments');
    const backToTopFab = document.getElementById('m3-back-to-top');
    const commentSection = document.getElementById('comments');

    // Central Scroll Logic for FABs (Registered for ALL pages)
    const handleFabVisibility = () => {
        const scrollY = window.scrollY;
        if (backToTopFab) {
            if (scrollY > 100) backToTopFab.classList.add('is-visible');
            else backToTopFab.classList.remove('is-visible');
        }
        if (commentFab && commentSection) {
            const rect = commentSection.getBoundingClientRect();
            if (scrollY > 100 && rect.top > window.innerHeight) commentFab.classList.add('is-visible');
            else commentFab.classList.remove('is-visible');
        }
        if (tocTrigger) {
            if (scrollY > 100) tocTrigger.classList.add('is-visible');
            else { tocTrigger.classList.remove('is-visible'); if (stickyToc?.classList.contains('is-active')) toggleToc(false); }
        }
    };
    window.addEventListener('scroll', handleFabVisibility, { passive: true });
    handleFabVisibility();

    if (backToTopFab) backToTopFab.addEventListener('click', () => gsap.to(window, { duration: 0.8, scrollTo: 0, ease: "power3.inOut" }));
    if (commentFab && commentSection) commentFab.addEventListener('click', () => gsap.to(window, { duration: 0.8, scrollTo: { y: commentSection, offsetY: 20 }, ease: "power3.inOut" }));

    // TOC Specific Logic
    if (!articleBody || !tocContainer || !stickyToc || !tocTrigger) return;
    const headings = articleBody.querySelectorAll('h2, h3');
    if (headings.length === 0) { tocTrigger.style.display = 'none'; return; }

    const tocList = document.createElement('ul');
    headings.forEach((heading, index) => {
        const id = heading.id || `m3-heading-${index}`; heading.id = id;
        const li = document.createElement('li'); li.className = `toc-level-${heading.tagName.toLowerCase()}`;
        const a = document.createElement('a'); a.href = `#${id}`; a.textContent = heading.textContent.trim();
        a.addEventListener('click', (e) => { e.preventDefault(); toggleToc(false); gsap.to(window, { duration: 0.8, scrollTo: { y: document.getElementById(id), offsetY: 80 }, ease: "power3.inOut" }); });
        li.appendChild(a); tocList.appendChild(li);
    });
    tocContainer.innerHTML = ''; tocContainer.appendChild(tocList);

    const toggleToc = (show) => {
        if (show) {
            stickyToc.classList.add('is-active');
            if (commentFab) commentFab.style.opacity = '0'; if (backToTopFab) backToTopFab.style.opacity = '0';
            gsap.fromTo(stickyToc, { autoAlpha: 0, y: 20, scale: 0.95 }, { autoAlpha: 1, y: 0, scale: 1, duration: 0.4, ease: "back.out(1.2)" });
        } else {
            gsap.to(stickyToc, { autoAlpha: 0, y: 15, scale: 0.95, duration: 0.3, ease: "power2.in", onComplete: () => {
                stickyToc.classList.remove('is-active');
                if (commentFab) commentFab.style.opacity = ''; if (backToTopFab) backToTopFab.style.opacity = '';
            }});
        }
    };
    tocTrigger.addEventListener('click', () => toggleToc(!stickyToc.classList.contains('is-active')));
    if (closeBtn) closeBtn.addEventListener('click', () => toggleToc(false));
}

function initOverdriveScroll() {
    if (typeof gsap === 'undefined' || !document.querySelector('.m3-page-container') || document.body.classList.contains('single-post')) return;
    const container = document.querySelector('.m3-page-container');
    const stretch = () => {
        const scrollY = window.scrollY; const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
        if (scrollY < 0) gsap.set(container, { scaleY: 1 + Math.min(Math.abs(scrollY) / 200, 0.1), transformOrigin: "top center" });
        else if (scrollY > maxScroll) gsap.set(container, { scaleY: 1 + Math.min((scrollY - maxScroll) / 200, 0.1), transformOrigin: "bottom center" });
        else gsap.to(container, { scaleY: 1, duration: 0.3, ease: "power2.out" });
        requestAnimationFrame(stretch);
    };
    stretch();
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') { if (e.key === 'Escape') e.target.blur(); return; }
        if (e.key === '/') { e.preventDefault(); document.getElementById('search-toggle')?.click(); }
        else if (e.key === 'Escape') { document.getElementById('m3-drawer')?.classList.remove('is-open'); document.getElementById('m3-drawer-scrim')?.classList.remove('is-visible'); document.querySelector('.m3-search-bar')?.classList.remove('is-active'); }
    });
}

function initTooltips() {
    if (typeof gsap === 'undefined' || window.matchMedia('(pointer: coarse)').matches) return;
    const tooltip = document.createElement('div'); tooltip.className = 'm3-dynamic-tooltip'; document.body.appendChild(tooltip);
    const show = (target) => {
        const text = target.getAttribute('data-tooltip') || target.getAttribute('title'); if (!text) return;
        tooltip.textContent = text; gsap.set(tooltip, { display: 'block', autoAlpha: 0, scale: 0.8 });
        const rect = target.getBoundingClientRect(); const tipW = tooltip.offsetWidth; const tipH = tooltip.offsetHeight;
        let x = rect.left - tipW - 16; let y = rect.top + rect.height/2 - tipH/2;
        x = Math.max(12, Math.min(x, window.innerWidth - tipW - 12)); y = Math.max(12, Math.min(y, window.innerHeight - tipH - 12));
        gsap.set(tooltip, { x: rect.left - 20, y: rect.top + rect.height/2 });
        gsap.to(tooltip, { autoAlpha: 1, x, y, scale: 1, duration: 0.35, ease: "back.out(1.2)" });
    };
    const hide = () => gsap.to(tooltip, { autoAlpha: 0, scale: 0.8, duration: 0.2 });
    document.body.addEventListener('mouseenter', (e) => { const t = e.target.closest('.m3-tooltip-target, [data-tooltip]'); if (t) show(t); }, true);
    document.body.addEventListener('mouseleave', (e) => { const t = e.target.closest('.m3-tooltip-target, [data-tooltip]'); if (t) hide(); }, true);
}

function initRippleEffect() {
    if (typeof gsap === 'undefined') return;
    const create = (e, el) => {
        const rect = el.getBoundingClientRect(); const size = Math.max(rect.width, rect.height);
        const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
        const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
        const ripple = document.createElement('span'); ripple.className = 'm3-ripple';
        ripple.style.width = ripple.style.height = `${size * 2}px`; ripple.style.left = `${x}px`; ripple.style.top = `${y}px`;
        el.appendChild(ripple); gsap.fromTo(ripple, { scale: 0, opacity: 0.15, xPercent: -50, yPercent: -50 }, { scale: 1, duration: 0.5, ease: "power2.out" });
        const remove = () => gsap.to(ripple, { opacity: 0, duration: 0.3, onComplete: () => ripple.remove() });
        el.addEventListener('mouseup', remove, { once: true }); el.addEventListener('touchend', remove, { once: true }); el.addEventListener('mouseleave', remove, { once: true });
    };
    document.querySelectorAll('.m3-card, .m3-button, .m3-btn, .m3-icon-button, .m3-label--category, .page-numbers, .m3-share-btn, .m3-elevated-nav-card').forEach(el => {
        el.classList.add('m3-ripple-host'); el.addEventListener('mousedown', (e) => create(e, el)); el.addEventListener('touchstart', (e) => create(e, el), { passive: true });
    });
}

function initAdaptiveHeader() {
    const header = document.querySelector('.m3-header'); if (!header) return;
    let isScrolled = false;
    window.addEventListener('scroll', () => {
        const scrolled = window.scrollY > 20; if (scrolled && !isScrolled) { isScrolled = true; header.classList.add('is-scrolled'); }
        else if (!scrolled && isScrolled) { isScrolled = false; header.classList.remove('is-scrolled'); }
    }, { passive: true });
}
import './styles/_blogcard-ai.css';

