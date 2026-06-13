import { storage } from '../storage';

const NODE_DEBUG = false;

export function initSearchBar() {
    if (NODE_DEBUG) console.log('Search Bar: Initializing Full Version...');
    const searchToggle = document.getElementById('search-toggle');
    const searchBar = document.querySelector('.m3-search-bar');
    const searchInput = document.getElementById('m3-search-input');
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
            setTimeout(() => {
                searchInput.focus();
                if (typeof window.nodeUpdateSearchClear === 'function') {
                    window.nodeUpdateSearchClear();
                }
            }, 300);
        } else if (!searchInput.value.trim()) {
            searchBar.classList.remove('is-active');
            header?.classList.remove('search-is-active');
        } else {
            searchBar.submit();
        }
    });

    // クリアボタンは header.php インラインスクリプトが正本（二重バインド防止）

    const closeSearch = () => {
        searchBar.classList.remove('is-active');
        header?.classList.remove('search-is-active');
    };

    // --- Close (← / Escape) ---
    document.getElementById('m3-search-mobile-close')?.addEventListener('click', closeSearch);

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeSearch();
        }
    });

    if (!modal) return;

    const openModal = () => {
        if (NODE_DEBUG) console.log('Search Bar: Opening Modal...');
        modal.classList.add('is-active');
        modal.style.display = 'flex';
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
        document.body.style.overflow = 'hidden';

        // --- Restore saved search settings ---
        const saved = storage.get('m3-saved-search');
        const saveToggle = document.getElementById('m3-save-search-settings');
        if (saved) {
            if (NODE_DEBUG) console.log('Search Bar: Restoring saved settings...');
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
            params.append('action', 'node_get_search_count');

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
