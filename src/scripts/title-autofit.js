/**
 * 記事タイトルの自動フィット
 *
 * 一覧に並ぶカードのタイトルが枠に収まらないとき、
 * 「少し詰めれば収まるものは詰める」「それでも溢れるものだけ省略する」を自動で行う。
 *
 * 背景:
 * カードタイトルには -webkit-line-clamp を指定しているが、Chromium は
 * display: -webkit-box を flow-root へ blockify するため clamp が効かず、
 * overflow: hidden だけが残って文字が行の途中で横に切られていた
 * （display: -webkit-box !important を当てても computed は flow-root のまま）。
 *
 * JS が動かない場合は _title-autofit.css の max-height + 下端フェードが受けるので、
 * 文字が中途半端に切れた見た目にはならない。
 */

/*
 * 対象は「一覧に並ぶ記事タイトル」。
 * 実際に溢れている要素にしか手を入れないため、対象を広めに取っても副作用はない。
 * 見出し・目次・アーカイブヘッダーなど、一覧のカードでないものは含めない。
 */
const TITLE_SELECTOR = [
    '.m3-card__title',
    '.c-card__title',
    '.c-headline-card__title',
    '.m3-headline-card__title',
    '.m3-related-card__title',
    '.m3-game-card__title',
    '.m3-elevated-nav-card__title',
].join(', ');

// [font-size 倍率, letter-spacing]
const FIT_STEPS = [
    [1, null],
    [0.96, '-0.015em'],
    [0.92, '-0.025em'],
    [0.88, '-0.035em'],
];

const overflows = (el) => el.scrollHeight - el.clientHeight > 1;

/*
 * タイトルの文字を持つ要素。
 * カードによっては <h3><a>タイトル</a></h3> の形なので、h3 の textContent を
 * 書き換えるとリンクごと壊れる。必ず内側の要素を触る。
 * 溢れ判定は高さを持つ外側（h3）で行う。
 */
const textHostOf = (el) => el.querySelector('a') || el;

/*
 * タイトルの箱が 2 行ぶんも無いときは、レイアウト側の都合で潰れている
 * （flex の縮小など）。ここで切り詰めても読めるものにはならず、
 * 元の文字列を壊すだけなので何もしない。
 */
const isCollapsed = (el) => {
    const lh = parseFloat(getComputedStyle(el).lineHeight);
    if (!Number.isFinite(lh) || lh <= 0) return false;
    return el.clientHeight < lh * 1.5;
};

const applyStep = (el, step) => {
    const [scale, tracking] = FIT_STEPS[step];

    if (step === 0) {
        el.style.removeProperty('--node-title-fit');
        el.style.removeProperty('--node-title-tracking');
        el.classList.remove('is-title-fit');
        return;
    }

    el.classList.add('is-title-fit');
    el.style.setProperty('--node-title-fit', String(scale));
    if (tracking) el.style.setProperty('--node-title-tracking', tracking);
};

const truncate = (el) => {
    const host = textHostOf(el);
    const full = el.dataset.nodeTitle;
    let lo = 0;
    let hi = full.length;

    // 収まる最大の文字数を二分探索する
    while (lo < hi) {
        const mid = Math.ceil((lo + hi) / 2);
        host.textContent = `${full.slice(0, mid)}…`;
        if (overflows(el)) hi = mid - 1;
        else lo = mid;
    }

    host.textContent = lo > 0 ? `${full.slice(0, lo)}…` : full;
    el.classList.add('is-title-truncated');
    el.title = full;
};

const fitTitle = (el) => {
    const host = textHostOf(el);
    if (!el.dataset.nodeTitle) el.dataset.nodeTitle = host.textContent.trim();

    // 毎回まっさらな状態から測り直す（リサイズで枠が広がった場合に戻せるように）
    host.textContent = el.dataset.nodeTitle;
    el.classList.remove('is-title-truncated');
    el.removeAttribute('title');
    applyStep(el, 0);

    if (!overflows(el) || isCollapsed(el)) return;

    for (let step = 1; step < FIT_STEPS.length; step += 1) {
        applyStep(el, step);
        if (!overflows(el)) return;
    }

    /*
     * 詰めても収まらなかった場合。
     * どのみち省略するなら、縮めたままだとカード間で文字サイズが不揃いになるだけなので
     * 通常サイズへ戻してから切る。詰めるのは「詰めれば切らずに済む」ときだけにする。
     */
    applyStep(el, 0);
    truncate(el);
};

export const initTitleAutoFit = () => {
    const titles = document.querySelectorAll(TITLE_SELECTOR);
    if (!titles.length) return;

    const run = () => titles.forEach(fitTitle);

    // Web フォント適用前に測ると行数がずれるので、読み込み完了後にもう一度測る。
    run();
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(run).catch(() => {});
    }

    let timer = 0;
    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = window.setTimeout(run, 150);
    });
};
