import { argbFromHex, themeFromSourceColor, hexFromArgb } from "@material/material-color-utilities";
const theme = themeFromSourceColor(argbFromHex("#FF9900"));
console.log(Object.keys(theme.schemes.light));
