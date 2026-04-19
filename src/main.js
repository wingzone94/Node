import { extractColorFromImage } from './colorExtractor';
import { generateM3Colors } from './theme';
import { storage } from './storage';

document.addEventListener('DOMContentLoaded', async () => {
  const labels = document.querySelectorAll('.m3-label--category');

  if (!labels.length) return;

  for (const label of labels) {
    const colorVal = label.dataset.color; // "#xxxxxx" or "auto"
    const thumbUrl = label.dataset.thumb;
    const cacheId = `${label.textContent.trim()}_${thumbUrl || 'no-img'}`;

    try {
      // 1. 手動設定カラー (HEX)
      if (colorVal && colorVal.startsWith('#')) {
        const colors = generateM3Colors(colorVal);
        applyM3Colors(label, colors);
        continue;
      }

      // 2. 自動生成カラー (auto)
      if (colorVal === 'auto' && thumbUrl) {
        // キャッシュチェック
        const cached = storage.get(cacheId);
        if (cached) {
          applyM3Colors(label, cached);
          continue;
        }

        // 画像から抽出
        const img = new Image();
        img.crossOrigin = "Anonymous";
        img.src = thumbUrl;

        label.style.opacity = '0.6'; // 読み込み中のフィードバック

        const rgb = await extractColorFromImage(img);
        const colors = generateM3Colors(rgb);
        
        applyM3Colors(label, colors);
        storage.set(cacheId, colors);
        label.style.opacity = '1';
      } else {
        // 3. フォールバック (デフォルト)
        const colors = generateM3Colors('#6750A4');
        applyM3Colors(label, colors);
      }
    } catch (err) {
      console.warn(`[M3] Failed to apply colors for label:`, label.textContent, err);
      // フォールバック適用
      const colors = generateM3Colors('#6750A4');
      applyM3Colors(label, colors);
    }
  }
});

/**
 * 要素単位でMaterial 3の配色を適用
 * @param {HTMLElement} el 
 * @param {Object} colors 
 */
function applyM3Colors(el, colors) {
  el.style.setProperty('--md-sys-color-secondary-container', colors.secondaryContainer);
  el.style.setProperty('--md-sys-color-on-secondary-container', colors.onSecondaryContainer);
  // 他のM3変数が必要な場合はここに追加
}
