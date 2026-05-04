import { extractColorFromImage } from './colorExtractor';
import { generateM3Colors } from './theme';
import { storage } from './storage';
import './scripts/card-animation';
import './scripts/share-actions';

document.addEventListener('DOMContentLoaded', async () => {
    if (typeof gsap !== 'undefined') gsap.config({ force3D: true });
    
    const initializers = [
        initColorExtraction,
        initDarkMode,
        initSearchBar,
        initDrawer,
        initViewSwitcher,
        initHandyMode,
        initShareFeatures,
        initFloatingActions,
        initTableOfContents,
        initOverdriveScroll,
        initKeyboardShortcuts,
        initTooltips,
        initRippleEffect,
        initReadingProgress,
        initHeroInfoBubble,
        initScrollAnimations,
        initHeaderClock
    ];

    initializers.forEach(init => {
        try {
            init();
        } catch (e) {
            console.error(`Initializer failed: ${init.name}`, e);
        }
    });
});

function initHeroInfoBubble() {
    const trigger = document.getElementById('m3-hero-reading-badge');
    if (!trigger) return;

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

    const update = () => {
        const now = new Date();
        const greetingEl = document.getElementById('m3-header-greeting');
        const dateEl = document.getElementById('m3-header-date');
        const timeEl = document.getElementById('m3-header-time');

        if (greetingEl) {
            const hour = now.getHours();
            let g = "Hello";
            if (hour < 5) g = "Good night";
            else if (hour < 12) g = "Good morning";
            else if (hour < 18) g = "Good afternoon";
            else g = "Good evening";
            greetingEl.textContent = g;
        }

        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        }

        if (timeEl) {
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
        }

        // Auto hide after 5 seconds if not home/front
        const isHomePage = document.body.classList.contains('home') || document.body.classList.contains('front-page');
        if (!isHomePage) {
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
            if (container) {
                if (progress > 0) container.classList.add('is-visible');
                else container.classList.remove('is-visible');
            }
            
            // 100%到達時の粉砕アニメーション
            if (progress >= 99.9 && !shattered) {
                shattered = true;
                shatterProgressBar(progressBar);
            }
        }
    };

    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
}

function shatterProgressBar(el) {
    if (typeof gsap === 'undefined') return;
    
    const rect = el.getBoundingClientRect();
    const shards = 12;
    
    for (let i = 0; i < shards; i++) {
        const shard = document.createElement('div');
        shard.className = 'm3-gauge-shard';
        shard.style.backgroundColor = '#FF9900';
        shard.style.left = `${rect.left + (Math.random() * rect.width)}px`;
        shard.style.top = `${rect.top}px`;
        document.body.appendChild(shard);
        
        gsap.to(shard, {
            x: (Math.random() - 0.5) * 300,
            y: Math.random() * 500 + 100,
            rotation: Math.random() * 720,
            opacity: 0,
            duration: 1.5,
            ease: "power2.out",
            onComplete: () => shard.remove()
        });
    }
    
    gsap.to(el, { opacity: 0, scaleY: 0, duration: 0.2 });
}

function initColorExtraction() {
    const badges = document.querySelectorAll('.m3-article__category-group a, .m3-reading-badge-label');
    badges.forEach(badge => {
        const thumbUrl = badge.dataset.thumb;
        if (thumbUrl) {
            extractColorFromImage(thumbUrl).then(color => {
                if (color) {
                    badge.style.backgroundColor = color;
                    badge.style.color = '#ffffff';
                    badge.style.textShadow = '0 1px 2px rgba(0,0,0,0.3)';
                }
            });
        }
    });
}

function initDarkMode() {
    const toggle = document.getElementById('theme-toggle');
    if (!toggle) return;

    const updateTheme = () => {
        const isDark = document.body.getAttribute('data-theme') === 'dark';
        const icon = toggle.querySelector('.material-symbols-outlined');
        
        // 常に「明るさ（brightness_6）」アイコンを使用
        if (icon) {
            icon.textContent = 'brightness_6';
        }
        
        storage.set('theme', isDark ? 'dark' : 'light');
    };

    toggle.addEventListener('click', () => {
        const current = document.body.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', next);
        updateTheme();
    });

    const bottomToggleBtn = document.getElementById('m3-theme-toggle-handy');
    const toggleFunc = () => {
        const current = document.body.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', next);
        updateTheme();
    };
    bottomToggleBtn?.addEventListener('click', toggleFunc);
    updateTheme();
}

function initSearchBar() {
    console.log('Search Bar: Initializing Full Version...');
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.getElementById('m3-search-input');
    const searchClear = document.getElementById('m3-search-clear');
    const modal = document.getElementById('m3-advanced-search-modal');
    const modalClose = document.getElementById('m3-advanced-search-close');
    const modalReset = document.getElementById('m3-advanced-search-reset');
    const modalApply = document.getElementById('m3-advanced-search-apply');
    const header = document.querySelector('.m3-header');

    if (!searchToggle || !searchBar || !searchInput) return;

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

    // --- Clear Button ---
    const updateClearBtn = () => {
        if (searchClear) searchClear.style.display = searchInput.value ? 'flex' : 'none';
    };
    searchInput.addEventListener('input', updateClearBtn);
    searchClear?.addEventListener('click', () => {
        searchInput.value = '';
        updateClearBtn();
        searchInput.focus();
    });

    // --- Mobile Close ---
    document.getElementById('m3-search-mobile-close')?.addEventListener('click', () => {
        searchBar.classList.remove('is-active');
        header?.classList.remove('search-is-active');
    });

    if (!modal) return;

    const openModal = () => {
        console.log('Search Bar: Opening Modal...');
        modal.classList.add('is-active');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
        document.body.style.overflow = 'hidden';

        // --- Restore saved search settings ---
        const saved = storage.get('m3-saved-search');
        const saveToggle = document.getElementById('m3-save-search-settings');
        if (saved) {
            console.log('Search Bar: Restoring saved settings...');
            modal.querySelectorAll('input, select').forEach(input => {
                if (saved[input.name] !== undefined) {
                    if (input.type === 'checkbox') {
                        input.checked = Array.isArray(saved[input.name]) ? saved[input.name].includes(input.value) : saved[input.name] === input.value;
                    } else if (input.type === 'radio') {
                        input.checked = saved[input.name] === input.value;
                    } else {
                        input.value = saved[input.name];
                    }
                }
            });
            if (saveToggle) saveToggle.checked = true;
        }
        
        if (window.innerWidth > 600) {
            switchPage(1);
        }
        
        setTimeout(() => {
            initRangeSlider();
            updateHitCount();
            updateTabStatus();
        }, 150);
    };

    const closeModal = () => {
        modal.classList.remove('is-active');
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        setTimeout(() => { modal.style.display = 'none'; }, 400);
        document.body.style.overflow = '';
    };

    // --- Global Event Delegation for Advanced Search Trigger ---
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.m3-search-advanced-trigger, #m3-advanced-search-trigger');
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        }
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

    const updateTabStatus = () => {
        const tabs = modal.querySelectorAll('.m3-modal__tab');
        tabs.forEach(tab => {
            const pageNum = tab.dataset.page;
            const page = modal.querySelector(`.m3-modal__page[data-page="${pageNum}"]`);
            const hasValue = Array.from(page.querySelectorAll('input, select')).some(input => {
                if (input.type === 'checkbox' || input.type === 'radio') return input.checked && input.value !== 'all';
                if (input.type === 'date' || input.type === 'number') return !!input.value;
                if (input.tagName === 'SELECT') return !!input.value;
                return false;
            });
            tab.classList.toggle('has-value', hasValue);
        });
    };

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
            const onMove = (me) => handleDrag(me, type);
            const onEnd = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onEnd);
                document.removeEventListener('touchmove', onMove);
                document.removeEventListener('touchend', onEnd);
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('touchend', onEnd);
        };

        minHandle.addEventListener('mousedown', (e) => onStart(e, 'min'));
        minHandle.addEventListener('touchstart', (e) => onStart(e, 'min'), { passive: false });
        maxHandle.addEventListener('mousedown', (e) => onStart(e, 'max'));
        maxHandle.addEventListener('touchstart', (e) => onStart(e, 'max'), { passive: false });

        minInput.addEventListener('change', () => {
            minVal = Math.min(parseInt(minInput.value) || 0, maxVal - 500);
            updateUI();
            updateHitCount();
        });
        maxInput.addEventListener('change', () => {
            maxVal = Math.max(parseInt(maxInput.value) || 0, minVal + 500);
            updateUI();
            updateHitCount();
        });

        window.addEventListener('resize', updateUI);
        updateUI();
    }

    // --- Search Hit Counter ---
    let debounceTimer;
    function updateHitCount() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const data = new FormData();
            modal.querySelectorAll('input, select').forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) data.append(input.name, input.value);
                } else {
                    data.append(input.name, input.value);
                }
            });

            const params = new URLSearchParams(data);
            params.append('action', 'm3_get_search_hit_count');

            const counter = document.getElementById('m3-search-hit-count');
            const applyBtn = document.getElementById('m3-advanced-search-apply');
            
            if (counter) counter.style.opacity = '0.5';

            fetch(m3_ajax.ajax_url, {
                method: 'POST',
                body: params
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && counter) {
                    counter.textContent = res.data.count;
                    counter.style.opacity = '1';
                    
                    if (applyBtn) {
                        applyBtn.disabled = (res.data.count === 0);
                        applyBtn.style.opacity = res.data.count === 0 ? '0.5' : '1';
                    }
                }
            });
        }, 300);
    }

    modal.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', () => { updateHitCount(); updateTabStatus(); });
        if (input.type === 'text' || input.type === 'number') input.addEventListener('input', () => { updateHitCount(); updateTabStatus(); });
    });

    // --- Apply Search ---
    modalApply?.addEventListener('click', () => {
        document.getElementById('m3-search-loading')?.classList.add('is-active');
        const params = new URLSearchParams();
        params.append('s', searchInput.value.trim());

        const searchData = {};
        const saveToggle = document.getElementById('m3-save-search-settings');

        modal.querySelectorAll('input, select').forEach(input => {
            if ((input.type === 'checkbox' || input.type === 'radio')) {
                if (input.checked) {
                    params.append(input.name, input.value);
                    if (input.type === 'checkbox') {
                        if (!searchData[input.name]) searchData[input.name] = [];
                        searchData[input.name].push(input.value);
                    } else {
                        searchData[input.name] = input.value;
                    }
                }
            } else if (input.tagName === 'SELECT' && input.value) {
                params.append(input.name, input.value);
                searchData[input.name] = input.value;
            } else if ((input.type === 'text' || input.type === 'date' || input.type === 'number') && input.value) {
                params.append(input.name, input.value);
                searchData[input.name] = input.value;
            }
        });

        // Save to LocalStorage if enabled
        if (saveToggle && saveToggle.checked) {
            storage.set('m3-saved-search', searchData);
        } else {
            storage.remove('m3-saved-search');
        }

        setTimeout(() => { window.location.href = `${m3_ajax.home_url}?${params.toString()}`; }, 600);
    });

    // --- Reset ---
    modalReset?.addEventListener('click', () => {
        modal.querySelectorAll('input, select').forEach(input => {
            if (input.type === 'checkbox') input.checked = false;
            else if (input.type === 'radio') input.checked = (input.value === 'all' || input.value === 'date');
            else if (input.tagName === 'SELECT') input.selectedIndex = 0;
            else if (input.id === 'm3-min-chars') input.value = 0;
            else if (input.id === 'm3-max-chars') input.value = 10000;
            else input.value = '';
        });
        
        // Also clear saved search
        storage.remove('m3-saved-search');
        const saveToggle = document.getElementById('m3-save-search-settings');
        if (saveToggle) saveToggle.checked = false;

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
            drawer.classList.toggle('is-active', open);
            scrim.classList.toggle('is-active', open);
            document.body.style.overflow = open ? 'hidden' : '';
        };
        menuBtn.addEventListener('click', () => toggle(true));
        scrim.addEventListener('click', () => toggle(false));
    }
}

function initViewSwitcher() {
    const btn = document.getElementById('m3-view-toggle');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const current = storage.get('view-mode') || 'pc';
        const next = current === 'pc' ? 'mobile' : 'pc';
        storage.set('view-mode', next);
        location.reload();
    });
}

function initHandyMode() {
    const tocBtn = document.getElementById('m3-handy-toc-trigger');
    if (tocBtn) {
        tocBtn.addEventListener('click', () => {
            const toc = document.getElementById('m3-toc-trigger');
            toc?.click();
        });
    }
}

function initShareFeatures() {
    // Share actions are handled in scripts/share-actions.js
}

function initTableOfContents() {
    const trigger = document.getElementById('m3-toc-trigger');
    const toc = document.getElementById('m3-toc-modal');
    if (trigger && toc) {
        const fabIds = ['m3-back-to-top', 'm3-scroll-to-comments', 'm3-jump-to-ai', 'm3-toc-trigger'];

        trigger.addEventListener('click', () => {
            toc.classList.add('is-active');
            document.body.style.overflow = 'hidden';
            // Hide ALL FABs
            fabIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.setProperty('display', 'none', 'important');
            });
        });
        
        const closeTOC = () => {
            toc.classList.remove('is-active');
            document.body.style.overflow = '';
            // Restore ALL FABs
            fabIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.setProperty('display', 'flex', 'important');
            });
        };

        toc.querySelector('.m3-modal__close')?.addEventListener('click', closeTOC);
        
        // Escape to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && toc.classList.contains('is-active')) closeTOC();
        });
    }
}

function initFloatingActions() {
    const actionStack = document.querySelector('.m3-action-stack');
    if (!actionStack) return;

    const backToTop = document.getElementById('m3-back-to-top');
    const scrollToComments = document.getElementById('m3-scroll-to-comments');
    const jumpToAI = document.getElementById('m3-jump-to-ai');

    // --- Back to Top ---
    backToTop?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // --- Scroll to Comments ---
    scrollToComments?.addEventListener('click', () => {
        const comments = document.getElementById('comments') || document.getElementById('respond');
        if (comments) {
            const headerOffset = 100;
            const elementPosition = comments.getBoundingClientRect().top + window.pageYOffset;
            window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });
        }
    });

    // --- Jump to AI Summary ---
    jumpToAI?.addEventListener('click', () => {
        const aiSummary = document.getElementById('m3-ai-summary');
        if (aiSummary) {
            const headerOffset = 100;
            const elementPosition = aiSummary.getBoundingClientRect().top + window.pageYOffset;
            window.scrollTo({ top: elementPosition - headerOffset, behavior: 'smooth' });
            
            // Highlight effect
            aiSummary.style.transition = 'box-shadow 0.5s ease';
            aiSummary.style.boxShadow = '0 0 60px rgba(255, 153, 0, 0.6)';
            setTimeout(() => { aiSummary.style.boxShadow = ''; }, 2000);
        }
    });

    // --- Scroll Visibility Logic ---
    const updateVisibility = () => {
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;
        const threshold = 300; // 400pxから300pxに短縮

        if (scrollY > threshold) {
            actionStack.classList.add('is-visible');
        } else {
            actionStack.classList.remove('is-visible');
        }
    };

    window.addEventListener('scroll', updateVisibility, { passive: true });
    updateVisibility();
}

function initOverdriveScroll() {
    // Scroll behavior handled via GSAP in scripts/card-animation.js
}

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            document.getElementById('search-toggle')?.click();
        }
    });
}

function initTooltips() {
    // Tooltips handled via CSS and small JS in main.js
}

function initRippleEffect() {
    document.querySelectorAll('.m3-button, .m3-fab, .m3-icon-button').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const x = e.clientX - e.target.offsetLeft;
            const y = e.clientY - e.target.offsetTop;
            const ripple = document.createElement('span');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });
}
