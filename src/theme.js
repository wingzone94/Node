import { argbFromRgb, themeFromSourceColor, hexFromArgb } from "@material/material-color-utilities";

/**
 * HEXカラー文字列をARGBに変換
 * @param {string} hex 
 * @returns {number}
 */
function hexToArgb(hex) {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return argbFromRgb(r, g, b);
}

/**
 * シードカラーからMaterial 3の配色を生成
 * @param {string|number[]} source - HEX文字列 or [r, g, b] 配列
 * @returns {Object}
 */
export function generateM3Colors(source) {
  let argb;
  if (typeof source === 'string') {
    argb = hexToArgb(source);
  } else {
    argb = argbFromRgb(source[0], source[1], source[2]);
  }

  const theme = themeFromSourceColor(argb);
  const light = theme.schemes.light;

  return {
    secondaryContainer: hexFromArgb(light.secondaryContainer),
    onSecondaryContainer: hexFromArgb(light.onSecondaryContainer)
  };
}
