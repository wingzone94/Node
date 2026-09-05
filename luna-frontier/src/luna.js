/**
 * Luna Frontier 2.0 / SkyAlow — entry point
 *
 * 方針（指示書 §16）:
 * - JavaScript は Progressive Enhancement 専用。
 * - 本文・固定ページ・ナビゲーション・パンくず・基本検索は JS なしで成立させる。
 * - 親テーマ（Node 1.3）の main.js を置き換えず、2.0 固有の差分だけを足す。
 *
 * CSS は独立した Vite entry（src/styles/luna.css）として出力し、ここからは import
 * しない。JS が失敗してもスタイルは適用される状態を保つため。
 */

/**
 * 脚注を Reading Aside（右カラム）へ移す。
 *
 * 脚注は親テーマが the_content フィルタ（優先度 999）で本文末に追加するため、
 * Reading Aside を描画する時点ではまだ存在しない。サーバー側で右カラムへ
 * 差し込むことができないので、描画後に DOM を移動する。
 *
 * - 2 カラムが成立する幅（>=1024px）でだけ移動する。
 * - 移動できない／JS が動かない場合は本文末に残るだけで、脚注は問題なく読める。
 * - innerHTML で作り直さず要素そのものを移すので、親テーマが張った
 *   イベントリスナー（番号タブ・説明トグル）はそのまま生き残る。
 */
function setupFootnoteRelocation() {
  const aside = document.querySelector('.lf-reading-aside');
  const footnotes = document.querySelector('.node-footnotes');

  if (!aside || !footnotes) return;

  // 元の位置を覚えておき、狭い画面へ戻したときに復帰できるようにする。
  const anchor = document.createComment('luna-frontier:footnotes');
  footnotes.parentNode.insertBefore(anchor, footnotes);

  const block = document.createElement('section');
  block.className = 'lf-aside-block lf-aside-block--footnotes';
  block.setAttribute('aria-label', '脚注');

  const media = window.matchMedia('(min-width: 1024px)');

  const apply = () => {
    if (media.matches) {
      if (footnotes.parentNode === block) return;
      block.appendChild(footnotes);
      aside.appendChild(block);
    } else {
      if (footnotes.parentNode !== block) return;
      anchor.parentNode.insertBefore(footnotes, anchor.nextSibling);
      block.remove();
    }
  };

  apply();

  if (typeof media.addEventListener === 'function') {
    media.addEventListener('change', apply);
  }
}

/**
 * 記事タイトルが枠に収まらないカードを自動で詰める。
 *
 * 背景: HEADLINE カードのタイトルは親テーマが -webkit-line-clamp で 3 行に
 * 制限しているが、Chromium が display: -webkit-box を blockify するため
 * clamp が効かず、overflow: hidden だけが残って文字の途中で切れていた。
 *
 * ここでは「少し詰めれば収まるものは詰める」を段階的に試し、
 * それでも収まらないものだけ末尾を省略する。
 *   1. 字送りと文字サイズをわずかに詰める（3 段階）
 *   2. それでも溢れるなら二分探索で切り詰めて … を付ける
 *
 * JS が動かない場合は CSS 側の max-height + 下端のぼかしが受け止めるので、
 * 文字が中途半端に切れた見た目にはならない。
 */
/*
 * 対象は省略表示を維持する HEADLINE・関連記事などのタイトル。
 * 通常記事カード（.m3-card__title / .c-card__title）は CSS で全文を表示し、
 * フォント読み込みやリサイズの際にも縮小・省略しない。
 * 見出し・目次・アーカイブヘッダーなど「一覧のカードではないもの」は含めない。
 */
const TITLE_SELECTOR = [
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

function overflows(el) {
  return el.scrollHeight - el.clientHeight > 1;
}

function applyStep(el, step) {
  const [scale, tracking] = FIT_STEPS[step];
  if (step === 0) {
    el.style.removeProperty('--lf-title-fit');
    el.style.removeProperty('--lf-title-tracking');
    el.classList.remove('lf-title-fit');
    return;
  }
  el.classList.add('lf-title-fit');
  el.style.setProperty('--lf-title-fit', String(scale));
  if (tracking) el.style.setProperty('--lf-title-tracking', tracking);
}

/*
 * タイトルの文字を持つ要素。カードによっては <h3><a>タイトル</a></h3> の形なので、
 * h3 の textContent を書き換えるとリンクごと壊れる。必ず内側の要素を触る。
 * 溢れているかどうかの判定は、高さを持つ外側（h3）で行う。
 */
function textHostOf(el) {
  return el.querySelector('a') || el;
}

function truncate(el) {
  const host = textHostOf(el);
  const full = el.dataset.lfTitle;
  let lo = 0;
  let hi = full.length;

  // 収まる最大の文字数を二分探索する
  while (lo < hi) {
    const mid = Math.ceil((lo + hi) / 2);
    host.textContent = full.slice(0, mid) + '…';
    if (overflows(el)) hi = mid - 1;
    else lo = mid;
  }

  host.textContent = lo > 0 ? full.slice(0, lo) + '…' : full;
  el.classList.add('lf-title-truncated');
  el.title = full;
}

/*
 * タイトルの箱が 2 行ぶんも無いときは、レイアウト側の都合で潰れている
 * （flex の縮小など）。ここで切り詰めても読めるものにはならず、
 * 元の文字列を壊すだけなので何もしない。
 */
function isCollapsed(el) {
  const lh = parseFloat(getComputedStyle(el).lineHeight);
  if (!Number.isFinite(lh) || lh <= 0) return false;
  return el.clientHeight < lh * 1.5;
}

function fitTitle(el) {
  const host = textHostOf(el);
  if (!el.dataset.lfTitle) el.dataset.lfTitle = host.textContent.trim();

  // 毎回まっさらな状態から測り直す（リサイズで枠が広がった場合に戻せるように）
  host.textContent = el.dataset.lfTitle;
  el.classList.remove('lf-title-truncated');
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
}

function setupTitleAutoFit() {
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
}

function init() {
  setupFootnoteRelocation();
  setupTitleAutoFit();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}

export {};
