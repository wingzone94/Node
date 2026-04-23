import { argbFromHex, themeFromSourceColor, hexFromArgb } from "@material/material-color-utilities";

const theme = themeFromSourceColor(argbFromHex("#FF9900"));

const light = theme.schemes.light;
const dark = theme.schemes.dark;

console.log("Light Theme:");
console.log(`--md-sys-color-primary: ${hexFromArgb(light.primary)};`);
console.log(`--md-sys-color-on-primary: ${hexFromArgb(light.onPrimary)};`);
console.log(`--md-sys-color-primary-container: ${hexFromArgb(light.primaryContainer)};`);
console.log(`--md-sys-color-on-primary-container: ${hexFromArgb(light.onPrimaryContainer)};`);
console.log(`--md-sys-color-secondary-container: ${hexFromArgb(light.secondaryContainer)};`);
console.log(`--md-sys-color-on-secondary-container: ${hexFromArgb(light.onSecondaryContainer)};`);
console.log(`--md-sys-color-surface: #FFF5E6; /* Soft light orange */`);
console.log(`--md-sys-color-on-surface: ${hexFromArgb(light.onSurface)};`);
console.log(`--md-sys-color-surface-container-low: ${hexFromArgb(light.surfaceContainerLow)};`);
console.log(`--md-sys-color-surface-container: ${hexFromArgb(light.surfaceContainer)};`);
console.log(`--md-sys-color-surface-container-high: ${hexFromArgb(light.surfaceContainerHigh)};`);
console.log(`--md-sys-color-outline: ${hexFromArgb(light.outline)};`);
console.log(`--md-sys-color-outline-variant: ${hexFromArgb(light.outlineVariant)};`);

console.log("Dark Theme:");
console.log(`--md-sys-color-primary: ${hexFromArgb(dark.primary)};`);
console.log(`--md-sys-color-on-primary: ${hexFromArgb(dark.onPrimary)};`);
console.log(`--md-sys-color-primary-container: ${hexFromArgb(dark.primaryContainer)};`);
console.log(`--md-sys-color-on-primary-container: ${hexFromArgb(dark.onPrimaryContainer)};`);
console.log(`--md-sys-color-secondary-container: ${hexFromArgb(dark.secondaryContainer)};`);
console.log(`--md-sys-color-on-secondary-container: ${hexFromArgb(dark.onSecondaryContainer)};`);
console.log(`--md-sys-color-surface: #331F00; /* Darker soft orange */`);
console.log(`--md-sys-color-on-surface: ${hexFromArgb(dark.onSurface)};`);
console.log(`--md-sys-color-surface-container-low: ${hexFromArgb(dark.surfaceContainerLow)};`);
console.log(`--md-sys-color-surface-container: ${hexFromArgb(dark.surfaceContainer)};`);
console.log(`--md-sys-color-surface-container-high: ${hexFromArgb(dark.surfaceContainerHigh)};`);
console.log(`--md-sys-color-outline: ${hexFromArgb(dark.outline)};`);
console.log(`--md-sys-color-outline-variant: ${hexFromArgb(dark.outlineVariant)};`);
