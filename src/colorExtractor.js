import ColorThief from 'colorthief';

/**
 * Extracts the dominant color from an image element.
 * @param {HTMLImageElement} imgElement 
 * @returns {Promise<number[]>} [r, g, b]
 */
export async function extractColorFromImage(imgElement) {
  const colorThief = new ColorThief();
  
  return new Promise((resolve, reject) => {
    const handleCapture = () => {
      try {
        const color = colorThief.getColor(imgElement);
        resolve(color);
      } catch (err) {
        reject(err);
      }
    };

    if (imgElement.complete) {
      handleCapture();
    } else {
      imgElement.addEventListener('load', handleCapture);
      imgElement.addEventListener('error', (err) => reject(err));
    }
  });
}
