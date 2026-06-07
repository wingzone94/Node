import { extractColorFromImage } from '../colorExtractor';

const HEX_COLOR = /^#[0-9a-f]{6}$/i;

function getReadableTextColor(hexColor) {
    if (hexColor.toLowerCase() === '#ff9900') {
        return '#ffffff';
    }

    const hex = hexColor.replace('#', '');
    const red = parseInt(hex.slice(0, 2), 16);
    const green = parseInt(hex.slice(2, 4), 16);
    const blue = parseInt(hex.slice(4, 6), 16);
    const yiq = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

    return yiq >= 150 ? '#2b1700' : '#ffffff';
}

function applyBadgeColor(badge, color) {
    badge.style.setProperty('--category-color', color);
    badge.style.setProperty('--category-on-color', getReadableTextColor(color));
    badge.style.backgroundColor = color;
    badge.style.color = 'var(--category-on-color)';
    badge.style.textShadow = getReadableTextColor(color) === '#ffffff'
        ? '0 1px 2px rgba(0,0,0,0.3)'
        : 'none';
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
