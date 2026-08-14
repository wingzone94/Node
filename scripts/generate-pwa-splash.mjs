// PWA / iOS スプラッシュ画像とapple-touch-iconを生成する
// オレンジ背景 (#FF9900) にブランドロゴ(node-logo.svg)を透過状態で中央配置する。
// 実行: node scripts/generate-pwa-splash.mjs
import sharp from 'sharp';
import { readFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';

const ORANGE = { r: 255, g: 153, b: 0, alpha: 1 }; // #FF9900
const svg = readFileSync('node-logo.svg');
const outDir = 'assets/pwa';
mkdirSync(outDir, { recursive: true });

async function logoBuffer(size) {
	return await sharp(svg, { density: 600 })
		.resize(size, size, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
		.png()
		.toBuffer();
}

// iPhone 主要解像度 (portrait, device px)
const splashes = [
	[750, 1334],
	[828, 1792],
	[1125, 2436],
	[1170, 2532],
	[1179, 2556],
	[1206, 2622],
	[1242, 2688],
	[1284, 2778],
	[1290, 2796],
	[1320, 2868],
];

for (const [w, h] of splashes) {
	const logoSize = Math.round(Math.min(w, h) * 0.42);
	const logo = await logoBuffer(logoSize);
	await sharp({ create: { width: w, height: h, channels: 4, background: ORANGE } })
		.composite([{ input: logo, gravity: 'center' }])
		.png({ compressionLevel: 9 })
		.toFile(path.join(outDir, `splash-${w}x${h}.png`));
	console.log(`splash-${w}x${h}.png`);
}

// apple-touch-icon (180x180) もオレンジ背景に統一（iOSは透過部を黒で塗るため）
const iconLogo = await logoBuffer(Math.round(180 * 0.68));
await sharp({ create: { width: 180, height: 180, channels: 4, background: ORANGE } })
	.composite([{ input: iconLogo, gravity: 'center' }])
	.png({ compressionLevel: 9 })
	.toFile(path.join(outDir, 'apple-touch-icon-180.png'));
console.log('apple-touch-icon-180.png');

console.log('done');
