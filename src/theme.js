import { argbFromRgb, themeFromSourceColor, hexFromArgb } from "@material/material-color-utilities";

/**
 * Generates Material 3 Primary and OnPrimary colors from an RGB array.
 * @param {number[]} rgb - [r, g, b]
 * @returns {Object} { primary, onPrimary }
 */
export function generateM3Color(rgb) {
  const argb = argbFromRgb(rgb[0], rgb[1], rgb[2]);
  const theme = themeFromSourceColor(argb);
  
  // Using light scheme for category labels by default
  return {
    primary: hexFromArgb(theme.schemes.light.primary),
    onPrimary: hexFromArgb(theme.schemes.light.onPrimary)
  };
}
