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
        updateHitCount(); // 初期表示時にカウントを更新
    };

    // --- Multi-page Logic ---
    const switchPage = (pageNum) => {
        const pagesContainer = modal.querySelector('.m3-modal__pages-container');
        const pages = modal.querySelectorAll('.m3-modal__page');
        const tabs = modal.querySelectorAll('.m3-modal__tab');
        const totalPages = pages.length;

        // Page Transform (100 / totalPages * (pageNum - 1))
        const movePercent = (100 / totalPages) * (pageNum - 1);
        pagesContainer.style.transform = `translateX(-${movePercent}%)`;

        // Active State
        pages.forEach(p => p.classList.toggle('is-active', p.dataset.page == pageNum));
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.page == pageNum));
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

        const updateUI = () => {
            const minPercent = (minVal / totalMax) * 100;
            const maxPercent = (maxVal / totalMax) * 100;
            
            const padding = 28; // Matches CSS padding
            const containerWidth = slider.offsetWidth;
            const trackWidth = containerWidth - (padding * 2);
            
            const minPos = padding + (minPercent / 100) * trackWidth;
            const maxPos = padding + (maxPercent / 100) * trackWidth;
            
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
            const rect = slider.getBoundingClientRect();
            const padding = 28;
            const trackWidth = rect.width - (padding * 2);
            const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left - padding;
            
            const percent = Math.min(100, Math.max(0, (x / trackWidth) * 100));
            let val = Math.round((percent / 100) * totalMax);
            
            // スナップ機能 (500単位)
            val = Math.round(val / 500) * 500;

            if (type === 'min') {
                minVal = Math.min(val, maxVal - 500);
            } else {
                maxVal = Math.max(val, minVal + 500);
            }
            updateUI();
            updateHitCount(); // ヒット件数をリアルタイム更新
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

        // --- Manual Input Sync ---
        minInput.addEventListener('change', () => {
            minVal = Math.min(Math.max(0, parseInt(minInput.value) || 0), maxVal - 500);
            updateUI();
            updateHitCount();
        });
        maxInput.addEventListener('change', () => {
            maxVal = Math.max(Math.min(totalMax, parseInt(maxInput.value) || 0), minVal + 500);
            updateUI();
            updateHitCount();
        });

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

    // --- Platform Selection Limit (Games Only) ---
    const MAX_GAME_PLATFORMS = 5;
    const applyButton = document.getElementById('m3-advanced-search-apply');
    const platformCheckboxes = modal.querySelectorAll('input[name="m3_platform[]"]');

    platformCheckboxes.forEach(cb => {
        cb.addEventListener('change', (e) => {
            const isGame = cb.dataset.isGame === 'true';
            if (isGame && cb.checked) {
                const checkedGames = modal.querySelectorAll('input[name="m3_platform[]"][data-is-game="true"]:checked');
                if (checkedGames.length > MAX_GAME_PLATFORMS) {
                    // 5個を超えても一応選択可能にするが、警告（シェイク）は出す
                    if (applyButton && typeof gsap !== 'undefined') {
                        gsap.to(applyButton, { 
                            x: 8, duration: 0.08, repeat: 5, yoyo: true, 
                            ease: "power2.inOut", 
                            onComplete: () => gsap.set(applyButton, { x: 0 })
                        });
                        applyButton.classList.add('is-error-shake');
                        setTimeout(() => applyButton.classList.remove('is-error-shake'), 600);
                    }
                }
            }
            updateHitCount();
        });
    });

    // --- Modal Logic with Vanishing Animation ---
    modalReset.addEventListener('click', () => {
        const sections = modal.querySelectorAll('.m3-search-section, .m3-platform-group');
        
        // M3E Vanishing Animation
        if (typeof gsap !== 'undefined') {
            gsap.to(sections, {
                y: 10, opacity: 0, duration: 0.25, stagger: 0.03, ease: "power2.in",
                onComplete: () => {
                    performReset();
                    // Reappear Animation
                    gsap.to(sections, {
                        y: 0, opacity: 1, duration: 0.4, stagger: 0.03, ease: "back.out(1.5)"
                    });
                }
            });
        } else {
            performReset();
        }

        function performReset() {
            modal.querySelectorAll('input, select').forEach(input => {
                if (input.tagName === 'SELECT') input.selectedIndex = 0;
                else if (input.type === 'checkbox') input.checked = false;
                else if (input.type === 'radio') {
                    if (['m3_ai', 'm3_media', 'm3_reading_time'].includes(input.name)) {
                        input.checked = input.value === 'all';
                    } else {
                        input.checked = false;
                    }
                }
                else if (input.id === 'm3-min-chars') input.value = 0;
                else if (input.id === 'm3-max-chars') input.value = 10000;
                else input.value = '';
            });
            initRangeSlider();
            updateHitCount();
        }
    });

    // --- Real-time Search Hits (Debounced for stability) ---
    let hitCountTimeout;
    function updateHitCount() {
        const hitCountText = document.querySelector('.m3-search-hits-text strong');
        if (!hitCountText) return;
        
        clearTimeout(hitCountTimeout);
        hitCountTimeout = setTimeout(() => {
            hitCountText.style.transition = 'opacity 0.2s';
            hitCountText.style.opacity = '0.3'; // Loading state
            
            const params = new URLSearchParams();
            const sValue = searchInput.value.trim();
            if (sValue) params.append('s', sValue);
            
            modal.querySelectorAll('input, select').forEach(input => {
                if (input.type === 'checkbox' && input.checked) params.append(input.name, input.value);
                if (input.type === 'radio' && input.checked) params.append(input.name, input.value);
                if (input.tagName === 'SELECT' && input.value) params.append(input.name, input.value);
                if ((input.type === 'text' || input.type === 'date' || input.type === 'hidden' || input.type === 'number') && input.value) {
                    params.append(input.name, input.value);
                }
            });
            
            // WordPress AJAX endpoint
            const ajaxUrl = `${m3_ajax.ajax_url}?action=node_get_search_count&${params.toString()}`;
            
            const resultsContainer = document.getElementById('m3-search-results-list');
        if (resultsContainer) showSkeletonLoader(resultsContainer, 4);

        fetch(ajaxUrl)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        hitCountText.textContent = data.data.count;
                    }
                    hitCountText.style.opacity = '1';
                })
                .catch(() => { hitCountText.style.opacity = '1'; });
        }, 300); // 300ms debounce
    };

    const updateTabStatus = () => {
        const tabs = modal.querySelectorAll('.m3-modal__tab');
        const pages = modal.querySelectorAll('.m3-modal__page');

        pages.forEach((page, index) => {
            const tab = tabs[index];
            let hasValue = false;

            if (index === 0) {
                // Page 1: Cat, Tag, Dates
                const cat = page.querySelector('select[name="m3_cat"]')?.value;
                const tag = page.querySelector('input[name="m3_tag"]')?.value;
                const start = page.querySelector('input[name="m3_start_date"]')?.value;
                const end = page.querySelector('input[name="m3_end_date"]')?.value;
                if (cat || tag || start || end) hasValue = true;
            } else if (index === 1) {
                // Page 2: Reading Time, Chars, Media, AI
                const readingTime = page.querySelector('input[name="m3_reading_time"]:checked')?.value;
                const minChars = page.querySelector('#m3-min-chars')?.value;
                const maxChars = page.querySelector('#m3-max-chars')?.value;
                const mediaChecked = page.querySelectorAll('input[name="m3_media_type[]"]:checked').length > 0;
                const ai = page.querySelector('input[name="m3_ai"]:checked')?.value;
                
                if ((readingTime && readingTime !== 'all') || 
                    (minChars && minChars !== '0') || 
                    (maxChars && maxChars !== '10000') || 
                    mediaChecked || 
                    (ai && ai !== 'all')) {
                    hasValue = true;
                }
            } else if (index === 2) {
                // Page 3: Platforms
                const platformsChecked = page.querySelectorAll('input[name="m3_platform[]"]:checked').length > 0;
                if (platformsChecked) hasValue = true;
            }

            tab.classList.toggle('has-settings', hasValue);
        });
    };

    // Add listeners to ALL inputs in the modal
    modal.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', () => {
            updateHitCount();
            updateTabStatus();
        });
        if (input.type === 'text' || input.type === 'number') {
            input.addEventListener('input', () => {
                updateHitCount();
                updateTabStatus();
            });
        }
    });
    searchInput.addEventListener('input', updateHitCount);
    
    // Initial call to set status if filters are pre-filled (e.g. from URL)
    updateTabStatus();

    modalApply.addEventListener('click', () => {
        const loading = document.getElementById('m3-search-loading');
        if (loading) loading.classList.add('is-active');

        const params = new URLSearchParams();
        const sValue = searchInput.value.trim();
        params.append('s', sValue); // Always append s to trigger search.php even if empty

        // すべての入力要素を収集
        modal.querySelectorAll('input, select').forEach(input => {
            if (input.type === 'checkbox' && input.checked) params.append(input.name, input.value);
            if (input.type === 'radio' && input.checked) params.append(input.name, input.value);
            if (input.tagName === 'SELECT' && input.value) params.append(input.name, input.value);
            if ((input.type === 'text' || input.type === 'date' || input.type === 'hidden' || input.type === 'number') && input.value) {
                params.append(input.name, input.value);
            }
        });

        // アニメーションを見せるための微小な遅延
        setTimeout(() => {
            const homeUrl = typeof m3_ajax !== 'undefined' ? m3_ajax.home_url : `${window.location.origin}/`;
            window.location.href = `${homeUrl}?${params.toString()}`;
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
}
import './styles/_blogcard-ai.css';

