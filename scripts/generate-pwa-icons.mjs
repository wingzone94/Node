// PWA / iOS のホーム画面アイコンを生成する。
//
// 背景はサイトのダーク面 (#1B1812)。以前はブランドオレンジ (#FF9900) のベタ塗り
// だったが、ホーム画面で悪目立ちするため廃止した（2026-08-08 ユーザー指示）。
// 透過のままにできないのは、iOS が透過部を黒で塗りつぶすため（角丸マスクの外側に
// 黒が回り込んでロゴが沈む）。無地で塗るなら黒よりサイトの面の色が合う。
//
// iOS の起動スプラッシュ（apple-touch-startup-image）は廃止したのでここでは作らない。
// 指定がなければ iOS は manifest の background_color で塗る。
//
// 実行: node scripts/generate-pwa-icons.mjs
import sharp from 'sharp';
import { readFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';

const ICON_BG = { r: 27, g: 24, b: 18, alpha: 1 }; // #1B1812 = サイトのダーク面
const svg = readFileSync('node-logo.svg');
const outDir = 'assets/pwa';
mkdirSync(outDir, { recursive: true });

async function logoBuffer(size) {
	return await sharp(svg, { density: 600 })
		.resize(size, size, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
		.png()
		.toBuffer();
}

const iconLogo = await logoBuffer(Math.round(180 * 0.68));
await sharp({ create: { width: 180, height: 180, channels: 4, background: ICON_BG } })
	.composite([{ input: iconLogo, gravity: 'center' }])
	.png({ compressionLevel: 9 })
	.toFile(path.join(outDir, 'apple-touch-icon-180.png'));
console.log('apple-touch-icon-180.png');

console.log('done');
