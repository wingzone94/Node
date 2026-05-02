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
    initViewSwitcher();
    initHandyMode();
    initShareFeatures();
    initTableOfContents(); // Handles TOC and FAB visibility
    initOverdriveScroll();
    initKeyboardShortcuts();
    initTooltips();
    initRippleEffect();
    initReadingProgress();
    initHeroInfoBubble();
    initScrollAnimations();
    initHeaderClock();
});

function initHeroInfoBubble() {
    const trigger = document.getElementById('m3-hero-reading-badge');
    const bubble = document.getElementById('m3-hero-info-bubble');
    
    if (!trigger || !bubble) return;

    let hideTimeout;

    const showInfo = () => {
        trigger.classList.add('is-info-active');
        clearTimeout(hideTimeout);
        hideTimeout = setTimeout(() => {
            trigger.classList.remove('is-info-active');
        }, 5000);
    };

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (trigger.classList.contains('is-info-active')) {
            trigger.classList.remove('is-info-active');
            clearTimeout(hideTimeout);
        } else {
            showInfo();
        }
    });

    document.addEventListener('click', (e) => {
        if (!trigger.contains(e.target)) {
            trigger.classList.remove('is-info-active');
        }
    });
}

function initHeaderClock() {
    const clock = document.getElementById('m3-header-clock');
    if (!clock) return;

    const dateEl = document.getElementById('m3-header-date');
    const timeEl = document.getElementById('m3-header-time');
    const greetingEl = document.getElementById('m3-header-greeting');

    const update = () => {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');

        if (dateEl) dateEl.textContent = `${y}/${m}/${d}`;
        if (timeEl) timeEl.textContent = `${hh}:${mm}:${ss}`;

        const hours = now.getHours();
        let greeting = '';
        let emoji = '';
        if (hours >= 6 && hours < 10) { greeting = 'Good Morning!'; emoji = '🌅'; }
        else if (hours >= 10 && hours < 18) { greeting = 'Hello!'; emoji = '☀️'; }
        else if (hours >= 18 && hours < 22) { greeting = 'Good Evening!'; emoji = '🌇'; }
        else { greeting = 'Good night...'; emoji = '😴'; }

        if (greetingEl && !greetingEl.dataset.initialized) {
            greetingEl.textContent = `${emoji} ${greeting}`;
            greetingEl.dataset.initialized = "true";
            
            // Fade-in animation
            if (typeof gsap !== 'undefined') {
                gsap.fromTo(clock, 
                    { opacity: 0, y: -10 }, 
                    { opacity: 0.6, y: 0, duration: 1.2, ease: "power3.out" }
                );
            }

            // Fade out the ENTIRE clock container after 5 seconds
            setTimeout(() => {
                if (typeof gsap !== 'undefined') {
                    gsap.to(clock, { 
                        opacity: 0, 
                        y: 10,
                        duration: 1.5, 
                        ease: "power3.inOut", 
                        onComplete: () => {
                            clock.style.display = 'none';
                        }
                    });
                } else {
                    clock.style.display = 'none';
                }
            }, 5000);
        }
    };

    setInterval(update, 1000);
    update();
}

function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.m3-reading-badge').forEach(el => observer.observe(el));
}

async function initReadingProgress() {
    const progressBar = document.querySelector('.m3-header__progress-bar');
    const container = document.querySelector('.m3-header__progress-container');
    const article = document.querySelector('.m3-article__body');
    
    if (!progressBar || !article) return;
    
    let shattered = false;
    
    const updateProgress = () => {
        const rect = article.getBoundingClientRect();
        const articleTop = rect.top + window.pageYOffset;
        const articleHeight = rect.height;
        const windowHeight = window.innerHeight;
        const currentScroll = window.pageYOffset;
        const scrollStart = articleTop - 64; 
        
        let progress = currentScroll > scrollStart ? ((currentScroll - scrollStart) / (articleHeight - windowHeight)) * 100 : 0;
        progress = Math.min(100, Math.max(0, progress));
        
        // 可逆性の実装: 上にスクロールして100%未満になったら復活させる
        if (progress < 99.5 && shattered) {
            shattered = false;
            gsap.to(progressBar, { opacity: 1, scaleY: 1, duration: 0.3, ease: "power2.out" });
        }

        if (!shattered) {
            progressBar.style.width = `${progress}%`;
        }
        
        // 読了時の粉砕アニメーション (100%到達時)
        if (progress >= 99.8 && !shattered) {
            shattered = true;
            playBarShatterAnimation(container, progressBar);
        }

        if (container) {
            // スクロール範囲内なら表示
            if (currentScroll > scrollStart && currentScroll < (articleTop + articleHeight - 100)) {
                container.classList.add('is-visible');
            } else {
                container.classList.remove('is-visible');
            }
        }
    };

    function playBarShatterAnimation(parent, bar) {
        if (!parent || !bar || typeof gsap === 'undefined') return;
        
        // バー自体を弾けさせる
        gsap.to(bar, { 
            opacity: 0, 
            scaleY: 3, 
            duration: 0.2, 
            ease: "expo.out" 
        });

        const rect = bar.getBoundingClientRect();
        const shardCount = 20;

        for (let i = 0; i < shardCount; i++) {
            const shard = document.createElement('div');
            shard.className = 'm3-gauge-shard';
            shard.style.backgroundColor = '#FF9900';
            shard.style.left = `${Math.random() * rect.width}px`;
            shard.style.top = `0px`;
            parent.appendChild(shard);
            
            // 上方へランダムに飛び散る
            const angle = (Math.random() * Math.PI) + Math.PI; 
            const dist = 40 + Math.random() * 120;
            
            gsap.to(shard, {
                x: Math.cos(angle) * dist,
                y: Math.sin(angle) * dist,
                rotation: Math.random() * 720,
                scale: 0,
                opacity: 0,
                duration: 0.8 + Math.random() * 0.4,
                ease: "power4.out",
                onComplete: () => shard.remove()
            });
        }
    }

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
    const toggleBtn = document.getElementById('theme-toggle');
    const bottomToggleBtn = document.getElementById('m3-bottom-theme-toggle');
    const toggleFunc = (e) => {
        if (e) e.preventDefault();
        localStorage.setItem('theme', document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        updateTheme();
    };
    toggleBtn?.addEventListener('click', toggleFunc);
    bottomToggleBtn?.addEventListener('click', toggleFunc);
    updateTheme();
}

function initSearchBar() {
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.getElementById('m3-search-input');
    const searchClear = document.getElementById('m3-search-clear');
    const modal = document.getElementById('m3-advanced-search-modal');
    const modalClose = document.getElementById('m3-advanced-search-close');
    const modalReset = document.getElementById('m3-advanced-search-reset');
    const modalApply = document.getElementById('m3-advanced-search-apply');

    if (!searchToggle || !searchBar || !searchInput || !modal) return;

    const header = document.querySelector('.m3-header');

    // --- Search Bar Toggle ---
    searchToggle.addEventListener('click', (e) => {
        if (!searchBar.classList.contains('is-active')) {
            searchBar.classList.add('is-active');
            header?.classList.add('search-is-active');
            setTimeout(() => searchInput.focus(), 300);
        } else if (!searchInput.value.trim()) {
            searchBar.classList.remove('is-active');
            header?.classList.remove('search-is-active');
        } else {
            searchBar.submit();
        }
    });

    // --- Clear Button Logic ---
    const updateClearBtn = () => {
        if (searchClear) searchClear.style.display = searchInput.value ? 'flex' : 'none';
    };
    searchInput.addEventListener('input', updateClearBtn);
    searchClear?.addEventListener('click', () => {
        searchInput.value = '';
        updateClearBtn();
        searchInput.focus();
        updateHitCount();
    });

    // --- Mobile Close Button ---
    document.getElementById('m3-search-mobile-close')?.addEventListener('click', (e) => {
        searchBar.classList.remove('is-active');
        header?.classList.remove('search-is-active');
    });

    // --- Modal Control ---
    const openModal = () => {
        modal.classList.add('is-active');
        document.body.style.overflow = 'hidden';
        
        if (window.innerWidth > 600) {
            switchPage(1);
        } else {
            // Mobile: Single scroll view
            modal.querySelectorAll('.m3-modal__page').forEach(p => p.classList.add('is-active'));
        }
        
        setTimeout(() => {
            initRangeSlider();
            updateHitCount();
            updateTabStatus();
        }, 150);
    };

    const closeModal = () => {
        modal.classList.remove('is-active');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('.m3-search-advanced-trigger').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            openModal();
        });
    });

    modalClose?.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // --- PC Tab Switching ---
    const switchPage = (pageNum) => {
        if (window.innerWidth <= 600) return;

        const pagesContainer = modal.querySelector('.m3-modal__pages-container');
        const allPages = Array.from(modal.querySelectorAll('.m3-modal__page'));
        const allTabs = Array.from(modal.querySelectorAll('.m3-modal__tab'));
        
        if (!pagesContainer || allPages.length === 0) return;

        const movePercent = (100 / allPages.length) * (pageNum - 1);
        pagesContainer.style.transform = `translateX(-${movePercent}%)`;

        allPages.forEach(p => p.classList.toggle('is-active', p.dataset.page == pageNum));
        allTabs.forEach(t => t.classList.toggle('is-active', t.dataset.page == pageNum));

        // Update tab indicator
        const tabsContainer = document.getElementById('m3-search-tabs');
        if (tabsContainer) {
            const indicator = tabsContainer.querySelector('.m3-modal__tab-indicator');
            const activeTab = allTabs.find(t => t.dataset.page == pageNum);
            if (indicator && activeTab) {
                indicator.style.width = `${activeTab.offsetWidth}px`;
                indicator.style.left = `${activeTab.offsetLeft}px`;
            }
        }
    };

    modal.querySelectorAll('.m3-modal__tab').forEach(el => {
        el.addEventListener('click', () => switchPage(parseInt(el.dataset.page)));
    });

    // --- Range Slider ---
    function initRangeSlider() {
        const slider = document.getElementById('m3-word-count-slider');
        const minHandle = document.getElementById('m3-slider-handle-min');
        const maxHandle = document.getElementById('m3-slider-handle-max');
        const range = document.getElementById('m3-slider-range');
        const minInput = document.getElementById('m3-min-chars');
        const maxInput = document.getElementById('m3-max-chars');
        
        if (!slider || !minHandle || !maxHandle || !range || !minInput || !maxInput) return;

        let minVal = parseInt(minInput.value) || 0;
        let maxVal = parseInt(maxInput.value) || 10000;
        const totalMax = 10000;

        const updateUI = () => {
            const rect = slider.getBoundingClientRect();
            if (rect.width === 0) return;

            const minPercent = (minVal / totalMax) * 100;
            const maxPercent = (maxVal / totalMax) * 100;
            
            const offset = 14;
            const trackWidth = rect.width - (offset * 2);
            
            const minPos = offset + (minPercent / 100) * trackWidth;
            const maxPos = offset + (maxPercent / 100) * trackWidth;
            
            minHandle.style.left = `${minPos}px`;
            maxHandle.style.left = `${maxPos}px`;
            range.style.left = `${minPos}px`;
            range.style.width = `${maxPos - minPos}px`;
            
            minHandle.querySelector('.m3-range-slider__value').textContent = minVal;
            maxHandle.querySelector('.m3-range-slider__value').textContent = maxVal >= totalMax ? '10000+' : maxVal;
            
            minInput.value = minVal;
            maxInput.value = maxVal;
        };

        const handleDrag = (e, type) => {
            if (e.cancelable) e.preventDefault();
            const rect = slider.getBoundingClientRect();
            const offset = 14;
            const trackWidth = rect.width - (offset * 2);
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const x = clientX - rect.left - offset;
            
            const percent = Math.min(100, Math.max(0, (x / trackWidth) * 100));
            let val = Math.round((percent / 100) * totalMax);
            val = Math.round(val / 500) * 500;

            if (type === 'min') {
                minVal = Math.min(val, maxVal - 500);
            } else {
                maxVal = Math.max(val, minVal + 500);
            }
            updateUI();
            updateHitCount();
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

        minHandle.onmousedown = (e) => onStart(e, 'min');
        maxHandle.onmousedown = (e) => onStart(e, 'max');
        minHandle.ontouchstart = (e) => onStart(e, 'min');
        maxHandle.ontouchstart = (e) => onStart(e, 'max');

        minInput.onchange = () => {
            minVal = Math.min(Math.max(0, parseInt(minInput.value) || 0), maxVal - 500);
            updateUI();
            updateHitCount();
        };
        maxInput.onchange = () => {
            maxVal = Math.max(Math.min(totalMax, parseInt(maxInput.value) || 0), minVal + 500);
            updateUI();
            updateHitCount();
        };

        updateUI();
    }

    // --- Tag Suggestions ---
    const initTagSuggestions = () => {
        const tagInput = document.getElementById('m3-tag-input');
        const suggestionList = document.getElementById('m3-tag-suggestions');
        if (!tagInput || !suggestionList) return;

        let debounceTimer;
        tagInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const query = tagInput.value.trim();
            if (query.length < 1) { suggestionList.classList.remove('is-active'); return; }

            debounceTimer = setTimeout(() => {
                fetch(`/wp-json/wp/v2/tags?search=${encodeURIComponent(query)}&per_page=10`)
                    .then(res => res.json())
                    .then(tags => {
                        if (tags?.length > 0) {
                            suggestionList.innerHTML = tags.map(tag => `
                                <div class="m3-suggestion-item" data-tag="${tag.name}">
                                    <span class="material-symbols-outlined">sell</span>
                                    <span>${tag.name}</span>
                                </div>
                            `).join('');
                            suggestionList.classList.add('is-active');
                            suggestionList.querySelectorAll('.m3-suggestion-item').forEach(item => {
                                item.onclick = () => {
                                    tagInput.value = item.dataset.tag;
                                    suggestionList.classList.remove('is-active');
                                    updateHitCount();
                                    updateTabStatus();
                                };
                            });
                        } else { suggestionList.classList.remove('is-active'); }
                    });
            }, 300);
        });
    };
    initTagSuggestions();

    // --- Reading Time Chips ---
    modal.querySelectorAll('input[name="m3_reading_time"]').forEach(input => {
        input.addEventListener('change', () => {
            const val = input.value;
            const minInput = document.getElementById('m3-min-chars');
            const maxInput = document.getElementById('m3-max-chars');
            if (val === 'short') { minInput.value = 0; maxInput.value = 2500; }
            else if (val === 'medium') { minInput.value = 2500; maxInput.value = 5000; }
            else if (val === 'long') { minInput.value = 5000; maxInput.value = 10000; }
            else { minInput.value = 0; maxInput.value = 10000; }
            initRangeSlider();
        });
    });

    // --- Hit Count & Tab Status ---
    let hitCountTimeout;
    const updateHitCount = () => {
        const hitCountText = document.querySelector('.m3-search-hits-text strong');
        if (!hitCountText) return;
        clearTimeout(hitCountTimeout);
        hitCountTimeout = setTimeout(() => {
            hitCountText.style.opacity = '0.3';
            const params = new URLSearchParams();
            const sVal = searchInput.value.trim();
            if (sVal) params.append('s', sVal);
            modal.querySelectorAll('input, select').forEach(input => {
                if ((input.type === 'checkbox' || input.type === 'radio') && input.checked) params.append(input.name, input.value);
                else if (input.tagName === 'SELECT' && input.value) params.append(input.name, input.value);
                else if ((input.type === 'text' || input.type === 'date' || input.type === 'number') && input.value) params.append(input.name, input.value);
            });
            fetch(`${m3_ajax.ajax_url}?action=node_get_search_count&${params.toString()}`)
                .then(res => res.json())
                .then(data => { if (data.success) hitCountText.textContent = data.data.count; hitCountText.style.opacity = '1'; });
        }, 400);
    };

    const updateTabStatus = () => {
        const pages = modal.querySelectorAll('.m3-modal__page');
        const tabs = modal.querySelectorAll('.m3-modal__tab');
        pages.forEach((page, i) => {
            let hasValue = false;
            page.querySelectorAll('input, select').forEach(input => {
                if (input.type === 'checkbox' && input.checked) hasValue = true;
                if (input.type === 'radio' && input.checked && input.value !== 'all') hasValue = true;
                if (input.tagName === 'SELECT' && input.value !== '') hasValue = true;
                if (input.type === 'text' && input.value !== '') hasValue = true;
                if ((input.id === 'm3-min-chars' && input.value !== '0') || (input.id === 'm3-max-chars' && input.value !== '10000')) hasValue = true;
            });
            tabs[i]?.classList.toggle('has-settings', hasValue);
        });
    };

    modal.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', () => { updateHitCount(); updateTabStatus(); });
        if (input.type === 'text' || input.type === 'number') input.addEventListener('input', () => { updateHitCount(); updateTabStatus(); });
    });

    // --- Apply Search ---
    modalApply?.addEventListener('click', () => {
        document.getElementById('m3-search-loading')?.classList.add('is-active');
        const params = new URLSearchParams();
        params.append('s', searchInput.value.trim());
        modal.querySelectorAll('input, select').forEach(input => {
            if ((input.type === 'checkbox' || input.type === 'radio') && input.checked) params.append(input.name, input.value);
            else if (input.tagName === 'SELECT' && input.value) params.append(input.name, input.value);
            else if ((input.type === 'text' || input.type === 'date' || input.type === 'number') && input.value) params.append(input.name, input.value);
        });
        setTimeout(() => { window.location.href = `${m3_ajax.home_url}?${params.toString()}`; }, 600);
    });

    // --- Reset ---
    modalReset?.addEventListener('click', () => {
        modal.querySelectorAll('input, select').forEach(input => {
            if (input.type === 'checkbox') input.checked = false;
            else if (input.type === 'radio') input.checked = input.value === 'all';
            else if (input.tagName === 'SELECT') input.selectedIndex = 0;
            else if (input.id === 'm3-min-chars') input.value = 0;
            else if (input.id === 'm3-max-chars') input.value = 10000;
            else input.value = '';
        });
        initRangeSlider();
        updateHitCount();
        updateTabStatus();
    });
}

function initDrawer() {
    const menuBtn = document.querySelector('.m3-header__menu');
    const drawer = document.getElementById('m3-drawer');
    const scrim = document.getElementById('m3-drawer-scrim');
    if (menuBtn && drawer && scrim) {
        const toggle = (open) => { 
            drawer.classList.toggle('is-open', open); 
            scrim.classList.toggle('is-visible', open); 
            document.body.style.overflow = open ? 'hidden' : ''; 
        };
        menuBtn.addEventListener('click', () => toggle(true));
        scrim.addEventListener('click', () => toggle(false));
        
        const closeBtn = document.getElementById('m3-drawer-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => toggle(false));
        }
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
        a.className = 'm3-toc-link';
        a.addEventListener('click', (e) => { e.preventDefault(); toggleToc(false); gsap.to(window, { duration: 0.8, scrollTo: { y: document.getElementById(id), offsetY: 80 }, ease: "power3.inOut" }); });
        li.appendChild(a); tocList.appendChild(li);
    });
    tocContainer.innerHTML = ''; tocContainer.appendChild(tocList);

    // Scroll Spy for TOC
    const observerOptions = { rootMargin: '-100px 0px -70% 0px', threshold: 0 };
    const tocObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id;
                document.querySelectorAll('.m3-toc-link').forEach(a => {
                    a.classList.toggle('is-active', a.getAttribute('href') === `#${id}`);
                });
            }
        });
    }, observerOptions);
    headings.forEach(h => tocObserver.observe(h));

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

/**
 * Minecraft Skeleton Loader Utility
 */
function showSkeletonLoader(container, count = 6) {
    const skeletonHtml = Array(count).fill(`
        <div class="m3-skeleton m3-skeleton-card">
            <div class="m3-skeleton__image"></div>
            <div class="m3-skeleton__title"></div>
            <div class="m3-skeleton__text"></div>
            <div class="m3-skeleton__meta"></div>
        </div>
    `).join('');
    
    container.innerHTML = `<div class="m3-post-grid__skeleton">${skeletonHtml}</div>`;
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
        
        let x, y;
        const isBottomTooltip = target.id === 'm3-rss-trigger' || target.id === 'theme-toggle';

        if (isBottomTooltip) {
            // Position at the bottom of the target
            x = rect.left + rect.width/2 - tipW/2; 
            y = rect.bottom + 8;
        } else {
            // Position at the left of the target (Default)
            x = rect.left - tipW - 16; 
            y = rect.top + rect.height/2 - tipH/2;
        }
        
        // Viewport bounds check
        x = Math.max(12, Math.min(x, window.innerWidth - tipW - 12)); 
        y = Math.max(12, Math.min(y, window.innerHeight - tipH - 12));
        
        const startX = isBottomTooltip ? (rect.left + rect.width/2 - tipW/2) : (rect.left - 20);
        const startY = isBottomTooltip ? (rect.bottom - 10) : (rect.top + rect.height/2);

        gsap.set(tooltip, { x: startX, y: startY });
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

    // --- Back to Top (Handy) ---
    document.getElementById('m3-back-to-top-handy')?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // --- Comments Scroll (Handy) ---
    document.getElementById('m3-bottom-comments-trigger')?.addEventListener('click', () => {
        const comments = document.getElementById('comments');
        if (comments) {
            window.scrollTo({ top: comments.offsetTop - 100, behavior: 'smooth' });
        }
    });
}

function initViewSwitcher() {
    const btn = document.getElementById('m3-view-toggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const isPC = document.body.classList.contains('pc-view');
        document.body.classList.toggle('pc-view', !isPC);
        localStorage.setItem('view-mode', !isPC ? 'pc' : 'mobile');
        alert(!isPC ? 'PCビューに切り替えました。' : 'モバイルビューに切り替えました。');
    });
}

function initHandyMode() {
    // Implement handy mode if needed
}
