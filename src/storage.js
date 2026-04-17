const CACHE_KEY_PREFIX = 'm3_cat_color_';
const CACHE_EXPIRY = 1000 * 60 * 60 * 24 * 7; // 7 days

export const storage = {
  /**
   * Gets cached colors for a category.
   * @param {string} id 
   * @returns {Object|null}
   */
  get: (id) => {
    const data = localStorage.getItem(CACHE_KEY_PREFIX + id);
    if (!data) return null;

    try {
      const parsed = JSON.parse(data);
      if (Date.now() > parsed.expiry) {
        localStorage.removeItem(CACHE_KEY_PREFIX + id);
        return null;
      }
      return parsed.colors;
    } catch (e) {
      return null;
    }
  },

  /**
   * Caches colors for a category.
   * @param {string} id 
   * @param {Object} colors 
   */
  set: (id, colors) => {
    const data = {
      colors,
      expiry: Date.now() + CACHE_EXPIRY
    };
    localStorage.setItem(CACHE_KEY_PREFIX + id, JSON.stringify(data));
  }
};
