export function isSinglePostView() {
    return document.body.classList.contains('single') || document.body.classList.contains('single-post');
}

export function isIndexOrArchiveView() {
    const body = document.body;
    return body.classList.contains('home')
        || body.classList.contains('blog')
        || body.classList.contains('archive')
        || body.classList.contains('category')
        || body.classList.contains('tag')
        || body.classList.contains('date')
        || body.classList.contains('author');
}

export function parseCssPixels(value) {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
}

export function getAdminBarOffsetPx() {
    const offset = getComputedStyle(document.body)
        .getPropertyValue('--wp-admin-bar-offset')
        .trim();
    return parseCssPixels(offset);
}
