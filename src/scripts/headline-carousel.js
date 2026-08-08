/**
 * HEADLINE カルーセル
 *
 * CSS Scroll Snap による横スクロールを前提に、ナビボタン・端点判定・
 * キーボード操作だけを担当する。外部ライブラリは使用しない。
 */

import { storage } from '../storage';

const EDGE_TOLERANCE = 2;

/** 表示モード（carousel / list）の保存キー。 */
const VIEW_STORAGE_KEY = 'headline-view';

/** カテゴリ名を詰められる下限（長体率）。これ以上は潰さず ellipsis に任せる。 */
const MIN_SQUEEZE = 0.72;

/** リスト表示は3行まで折り返せるぶん、より強く詰めてよい。 */
const LIST_MIN_SQUEEZE = 0.6;
const LIST_MAX_LINES = 3;
const LIST_SQUEEZE_STEP = 0.04;

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * リスト表示のカテゴリ名を「3行以内 かつ 省略なし」に近づける。
 *
 * CSS 側で内側スパンの論理幅を `100% / --category-squeeze` にしてあるので、
 * 長体率を下げるほど1行に入る文字数が増える。3行に収まるまで段階的に詰める。
 * バッジ幅はリスト表示ではPC・スマホとも固定なので、どちらでも同じ計算が使える。
 */
function fitListCategoryLabel(label, text) {
    const lineHeight = parseFloat(getComputedStyle(text).lineHeight);
    if (!lineHeight) return;

    const style = getComputedStyle(label);
    const available = label.clientWidth
        - parseFloat(style.paddingInlineStart || 0)
        - parseFloat(style.paddingInlineEnd || 0);
    if (available <= 0) return;

    // 折り返しの基準幅を確定させる（未指定だと1行のまま横へはみ出す）
    text.style.width = `${available}px`;

    const overflows = () => text.scrollHeight > lineHeight * LIST_MAX_LINES + 1;
    if (!overflows()) return;

    label.style.setProperty('--category-tracking', '0');

    let ratio = 1;
    while (ratio > LIST_MIN_SQUEEZE && overflows()) {
        ratio = Math.max(LIST_MIN_SQUEEZE, ratio - LIST_SQUEEZE_STEP);
        label.style.setProperty('--category-squeeze', String(ratio));
        // 論理幅を広げてから scaleX で戻すので、1行に入る文字数だけが増える
        text.style.width = `${available / ratio}px`;
    }
}

/**
 * 長いカテゴリ名をバッジ幅に合わせて水平方向に詰める（長体）。
 * 和文フォントは font-stretch が効かないため scaleX で行う。
 */
function fitCategoryLabels(section) {
    section.querySelectorAll('.c-headline-card__category').forEach(label => {
        let text = label.querySelector('.c-headline-card__category-text');
        if (!text) {
            text = document.createElement('span');
            text.className = 'c-headline-card__category-text';
            while (label.firstChild) text.appendChild(label.firstChild);
            label.appendChild(text);
        }

        // 素の幅を測るため一旦リセットする
        text.style.width = '';
        label.style.removeProperty('--category-squeeze');
        label.style.removeProperty('--category-tracking');

        // 表示されていないバッジ（他モード用）は測っても 0 になるので触らない
        if (label.offsetParent === null) return;

        if (section.dataset.view === 'list') {
            fitListCategoryLabel(label, text);
            return;
        }

        // カード表示は長体で潰さず2段で折り返す（2026-08-08 ユーザー指示）。
        // 収まらない分はバッジの件数側で調整するので、ここでは何もしない。
        if (label.classList.contains('c-headline-card__category--tag')) return;

        const style = getComputedStyle(label);
        const available = label.clientWidth
            - parseFloat(style.paddingInlineStart || 0)
            - parseFloat(style.paddingInlineEnd || 0);
        const natural = text.scrollWidth;
        if (available <= 0 || natural <= available + 1) return;

        const ratio = Math.max(MIN_SQUEEZE, available / natural);
        label.style.setProperty('--category-squeeze', String(ratio));
        label.style.setProperty('--category-tracking', '0');
        // 縮小後にちょうど収まる論理幅を与える。詰めきれない分だけ ellipsis になる。
        text.style.width = `${available / ratio}px`;
    });
}

/**
 * カード表示のカテゴリバッジを、固定高のカテゴリ枠に収まる件数へ自動制限する。
 *
 * 枠の高さは CSS 側で2段ぶんに固定してある（カードごとに段数が変わると
 * タイトル・日付のY座標がずれるため）。名前が2行に折り返したバッジは
 * 高さこそ2行ぶんだが offsetTop は1段なので、段数ではなく**実高さ**で判定する。
 * はみ出すバッジは後ろから隠す（枠は overflow: hidden なので、隠し漏れても
 * 半分だけ見えることはない）。
 */
function limitCategoryBadges(section) {
    if (section.dataset.view === 'list') return;

    section.querySelectorAll('[data-headline-categories]').forEach(list => {
        const badges = Array.from(list.querySelectorAll('.c-headline-card__category'));
        badges.forEach(badge => badge.removeAttribute('hidden'));
        if (badges.length < 2 || list.offsetParent === null) return;

        // 隠すと残りが再配置されるので、収まるまで後ろから1つずつ落とす
        for (let guard = badges.length; guard > 0; guard -= 1) {
            const visible = badges.filter(badge => !badge.hasAttribute('hidden'));
            if (visible.length < 2) return;
            if (list.scrollHeight <= list.clientHeight + 1) return;

            visible[visible.length - 1].setAttribute('hidden', '');
        }
    });
}

function setupCarousel(section) {
    if (section.dataset.headlineCarouselReady === 'true') return;

    const viewport = section.querySelector('[data-headline-viewport]');
    const nav = section.querySelector('[data-headline-nav]');
    const prev = section.querySelector('[data-headline-prev]');
    const next = section.querySelector('[data-headline-next]');
    if (!viewport) return;

    section.dataset.headlineCarouselReady = 'true';

    const getStep = () => {
        const track = viewport.querySelector('.c-headline-carousel__track');
        const item = track?.firstElementChild;
        if (!item) return Math.round(viewport.clientWidth * 0.8);

        const gap = track ? parseFloat(getComputedStyle(track).columnGap) || 0 : 0;
        const step = item.getBoundingClientRect().width + gap;
        // 1画面に収まるカード枚数ぶんだけ動かす（最低1枚）
        const perView = Math.max(1, Math.floor(viewport.clientWidth / step));
        return Math.round(step * perView);
    };

    const setDisabled = (button, disabled) => {
        if (!button) return;
        button.disabled = disabled;
        button.setAttribute('aria-disabled', String(disabled));
    };

    const update = () => {
        if (section.dataset.view === 'list') {
            if (nav) nav.hidden = true;
            return;
        }

        const maxScroll = viewport.scrollWidth - viewport.clientWidth;
        const overflows = maxScroll > EDGE_TOLERANCE;

        if (nav) nav.hidden = !overflows;
        if (!overflows) {
            setDisabled(prev, true);
            setDisabled(next, true);
            return;
        }

        setDisabled(prev, viewport.scrollLeft <= EDGE_TOLERANCE);
        setDisabled(next, viewport.scrollLeft >= maxScroll - EDGE_TOLERANCE);
    };

    const scrollByStep = direction => {
        viewport.scrollBy({
            left: direction * getStep(),
            behavior: prefersReducedMotion() ? 'auto' : 'smooth'
        });
    };

    prev?.addEventListener('click', () => scrollByStep(-1));
    next?.addEventListener('click', () => scrollByStep(1));

    viewport.addEventListener('keydown', event => {
        const direction = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
        // カード内リンクにフォーカスがある時はブラウザ既定のスクロールに任せる
        if (!direction || event.target !== viewport || section.dataset.view === 'list') return;

        event.preventDefault();
        scrollByStep(direction);
    });

    let ticking = false;
    viewport.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(() => {
            ticking = false;
            update();
        });
    }, { passive: true });

    const refresh = () => {
        fitCategoryLabels(section);
        limitCategoryBadges(section);
        update();
    };

    // --- 表示切替（カード ⇄ リスト） ---
    const viewButtons = section.querySelectorAll('[data-headline-view]');
    const applyView = mode => {
        const view = mode === 'list' ? 'list' : 'carousel';
        section.dataset.view = view;

        viewButtons.forEach(button => {
            const active = button.dataset.headlineView === view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });

        if (view === 'carousel') {
            viewport.scrollLeft = 0;
        }

        refresh();
    };

    viewButtons.forEach(button => {
        button.addEventListener('click', () => {
            const view = button.dataset.headlineView;
            storage.set(VIEW_STORAGE_KEY, view);
            applyView(view);
        });
    });

    applyView(storage.get(VIEW_STORAGE_KEY));

    if ('ResizeObserver' in window) {
        new ResizeObserver(refresh).observe(viewport);
    } else {
        window.addEventListener('resize', refresh);
    }

    refresh();

    // Web フォント適用後は文字幅が変わるので測り直す
    document.fonts?.ready.then(() => {
        fitCategoryLabels(section);
        limitCategoryBadges(section);
    });
}

export function initHeadlineCarousel() {
    document.querySelectorAll('[data-headline-carousel]').forEach(setupCarousel);
}
