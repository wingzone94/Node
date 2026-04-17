/**
 * Saves the auto-generated category color to the database via WordPress AJAX.
 * @param {string} categoryId 
 * @param {string} colorHex 
 * @returns {Promise<Object>}
 */
export async function saveCategoryColor(categoryId, colorHex) {
  if (!window.wpApiSettings) return;

  const formData = new FormData();
  formData.append('action', 'save_category_color');
  formData.append('category_id', categoryId);
  formData.append('color', colorHex);
  formData.append('nonce', window.wpApiSettings.nonce);

  try {
    const response = await fetch(window.wpApiSettings.ajaxUrl, {
      method: 'POST',
      body: formData
    });
    return await response.json();
  } catch (error) {
    console.error("Failed to save category color", error);
  }
}
