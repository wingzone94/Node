import { extractColorFromImage } from '../colorExtractor';

const HEX_COLOR = /^#[0-9a-f]{6}$/i;

function applyBadgeColor(badge, color) {
    // カテゴリラベルの文字色は常に白で固定する（ライト/ダーク共通）。
    badge.style.setProperty('--category-color', color);
    badge.style.setProperty('--category-on-color', '#ffffff');
    badge.style.backgroundColor = color;
    badge.style.color = 'var(--category-on-color)';
    badge.style.textShadow = '0 1px 2px rgba(0,0,0,0.3)';
}

export function initColorExtraction() {
    const badges = document.querySelectorAll('.m3-article__category-group a, .m3-reading-badge-label');
    badges.forEach(badge => {
        const configuredColor = (badge.dataset.color || '').trim();
        if (HEX_COLOR.test(configuredColor)) {
            applyBadgeColor(badge, configuredColor);
            return;
        }

        const thumbUrl = badge.dataset.thumb;
        if (thumbUrl) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.src = thumbUrl;

            extractColorFromImage(img)
                .then(color => {
                    if (color) {
                        applyBadgeColor(badge, color);
                    }
                })
                .catch(() => {
                    // Keep the default category style when the image cannot be read.
                });
        }
    });
}
