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

function init() {
  setupFootnoteRelocation();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  init();
}

export {};
