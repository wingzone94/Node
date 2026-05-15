const CACHE_KEY_PREFIX = 'm3_cat_color_';
const STORE_KEY_PREFIX = 'm3_store_';
const CACHE_EXPIRY = 1000 * 60 * 60 * 24 * 7; // 7 days

function isM3ColorObject(value) {
    return (
        value &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        ('primary' in value || 'primaryContainer' in value || 'surface' in value)
    );
}

export const storage = {
    /**
     * @param {string} id
     * @returns {unknown|null}
     */
    get(id) {
        const storeRaw = localStorage.getItem(STORE_KEY_PREFIX + id);
        if (storeRaw !== null) {
            try {
                return JSON.parse(storeRaw);
            } catch {
                return null;
            }
        }

        const catRaw = localStorage.getItem(CACHE_KEY_PREFIX + id);
        if (!catRaw) return null;

        try {
            const parsed = JSON.parse(catRaw);
            if (Date.now() > parsed.expiry) {
                localStorage.removeItem(CACHE_KEY_PREFIX + id);
                return null;
            }
            return parsed.colors;
        } catch {
            return null;
        }
    },

    /**
     * @param {string} id
     * @param {unknown} value
     */
    set(id, value) {
        if (isM3ColorObject(value)) {
            localStorage.setItem(
                CACHE_KEY_PREFIX + id,
                JSON.stringify({ colors: value, expiry: Date.now() + CACHE_EXPIRY })
            );
            return;
        }
        localStorage.setItem(STORE_KEY_PREFIX + id, JSON.stringify(value));
    },

    /**
     * @param {string} id
     */
    remove(id) {
        localStorage.removeItem(STORE_KEY_PREFIX + id);
        localStorage.removeItem(CACHE_KEY_PREFIX + id);
    },
};
