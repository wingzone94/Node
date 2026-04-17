import { extractColorFromImage } from './colorExtractor';
import { generateM3Color } from './theme';
import { saveCategoryColor } from './api';
import { storage } from './storage';

document.addEventListener('DOMContentLoaded', async () => {
  const labels = document.querySelectorAll('.category-label');
  const heroImage = document.querySelector('img.wp-post-image'); // Standard WP class for featured images

  if (!labels.length) return;

  for (const label of labels) {
    const catId = label.dataset.categoryId;
    const manualColor = label.dataset.manualColor;
    const autoColor = label.dataset.autoColor;

    // 1. If manual or auto color exists in DB, PHP already handled it.
    if (manualColor || autoColor) continue;

    // 2. Check LocalStorage cache
    const cached = storage.get(catId);
    if (cached) {
      applyColors(label, cached);
      continue;
    }

    // 3. Extract from image if available
    if (heroImage) {
      label.classList.add('is-extracting');
      try {
        const rgb = await extractColorFromImage(heroImage);
        const colors = generateM3Color(rgb);
        
        applyColors(label, colors);
        storage.set(catId, colors);
        
        // Save to DB via AJAX (fire and forget)
        saveCategoryColor(catId, colors.primary);
      } catch (err) {
        console.warn(`Could not extract color for category ${catId}:`, err);
      } finally {
        label.classList.remove('is-extracting');
      }
    }
  }
});

/**
 * Applies colors to the label element.
 * @param {HTMLElement} el 
 * @param {Object} colors 
 */
function applyColors(el, colors) {
  el.style.backgroundColor = colors.primary;
  el.style.color = colors.onPrimary;
}
