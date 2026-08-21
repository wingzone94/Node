/**
 * Material Symbols のサブセット漏れを検出する。
 *
 * inc/icon-font.php は `icon_names=` で使うアイコンだけを要求している
 * （全アイコンだと 3,874KB、絞ると 83.9KB）。一覧に無いアイコンは
 * グリフが無いのでリガチャの文字がそのまま出る（"settings" と表示される）。
 *
 * ここでは2方向から突き合わせる。
 *
 *   1. 静的抽出 — ソースから読めるアイコン名。WordPress 不要。
 *   2. 実ページ — 実際に描画された .material-symbols-outlined の中身。
 *      JS が動的に入れる名前（color-mode.js の dark_mode など）は
 *      静的抽出では拾えないので、こちらが要る。
 *
 * サイトに繋がらない環境では 2 を飛ばして 1 だけ行う。
 */

import { readdir, readFile } from 'node:fs/promises';
import { join } from 'node:path';

const ROOT = process.cwd();
const BASE = (process.env.NODE_VISUAL_BASE_URL ?? 'http://cybernode.local').replace(/\/$/, '');

const SOURCE_EXTENSIONS = new Set(['.php', '.js', '.css', '.html']);
const SKIP_DIRS = new Set(['.git', 'node_modules', 'vendor', 'assets', 'scratch', 'test-results', '.cursor', '.claude']);

/** 静的抽出で拾えないと分かっているもの。理由を添えて明示的に許す。 */
const KNOWN_DYNAMIC = new Map([
	['dark_mode', 'src/scripts/color-mode.js の三項演算子で切り替わる'],
	['light_mode', 'src/scripts/color-mode.js の三項演算子で切り替わる'],
]);

const CRAWL_PATHS = [
	'/',
	'/all-articles/',
	'/?s=Claude',
	'/?s=zzzz-no-such-post',
	'/this-path-should-404/',
];

async function collectSourceFiles(dir) {
	const out = [];
	for (const entry of await readdir(dir, { withFileTypes: true })) {
		if (SKIP_DIRS.has(entry.name)) continue;
		const full = join(dir, entry.name);
		if (entry.isDirectory()) {
			out.push(...(await collectSourceFiles(full)));
			continue;
		}
		const dot = entry.name.lastIndexOf('.');
		if (dot !== -1 && SOURCE_EXTENSIONS.has(entry.name.slice(dot))) out.push(full);
	}
	return out;
}

/** inc/icon-font.php の配列を読む。PHP を動かさずに済ませる。 */
async function readDeclaredIcons() {
	const text = await readFile(join(ROOT, 'inc/icon-font.php'), 'utf8');
	const body = text.slice(text.indexOf('$names = array('), text.indexOf(');', text.indexOf('$names = array(')));
	const names = [...body.matchAll(/'([a-z0-9_]{2,40})'/g)].map((m) => m[1]);
	return new Set(names);
}

const ICON_PATTERNS = [
	/class="[^"]*material-symbols-outlined[^"]*"[^>]*>\s*([a-z0-9_]+)\s*</g,
	/\.textContent\s*=\s*['"]([a-z0-9_]+)['"]/g,
	/data-node-section-icon="([a-z0-9_]+)"/g,
	/data-icon="([a-z0-9_]+)"/g,
	/['"]icon['"]\s*=>\s*['"]([a-z0-9_]+)['"]/g,
];

async function extractFromSource() {
	const found = new Map();
	for (const file of await collectSourceFiles(ROOT)) {
		const text = await readFile(file, 'utf8');
		if (!/material-symbols|node-section-icon|textContent/i.test(text)) continue;
		const rel = file.replace(`${ROOT}/`, '');
		for (const pattern of ICON_PATTERNS) {
			pattern.lastIndex = 0;
			let match;
			while ((match = pattern.exec(text))) {
				const name = match[1];
				if (!found.has(name)) found.set(name, rel);
			}
		}
	}
	return found;
}

async function extractFromPages() {
	let chromium;
	try {
		({ chromium } = await import('playwright'));
	} catch {
		return null;
	}

	const browser = await chromium.launch();
	const found = new Map();
	try {
		for (const width of [390, 1440]) {
			for (const path of CRAWL_PATHS) {
				const page = await browser.newPage({ viewport: { width, height: 900 }, isMobile: width < 700, hasTouch: width < 700 });
				try {
					await page.goto(BASE + path, { waitUntil: 'networkidle', timeout: 20000 });
					// 折りたたまれた UI の中のアイコンも拾う。
					await page.evaluate(async () => {
						document.querySelectorAll('details').forEach((d) => { d.open = true; });
						document.querySelectorAll('[hidden]').forEach((e) => { e.hidden = false; });
						document.getElementById('search-toggle')?.click();
						await new Promise((resolve) => setTimeout(resolve, 600));
					});
					const names = await page.evaluate(() =>
						[...document.querySelectorAll('.material-symbols-outlined')]
							.map((el) => el.textContent.trim())
							.filter(Boolean)
					);
					names.forEach((n) => { if (!found.has(n)) found.set(n, `${width}px ${path}`); });
				} catch {
					// 個別ページの失敗は握りつぶす。到達できないだけなら静的側で担保する。
				}
				await page.close();
			}
		}
	} finally {
		await browser.close();
	}
	return found;
}

const declared = await readDeclaredIcons();
console.log(`inc/icon-font.php の登録数: ${declared.size}`);

const problems = [];

const fromSource = await extractFromSource();
const missingSource = [...fromSource].filter(([name]) => !declared.has(name));
console.log(`静的抽出: ${fromSource.size}種 / 未登録 ${missingSource.length}種`);
missingSource.forEach(([name, where]) => problems.push(`${name}  (${where})`));

const fromPages = await extractFromPages();
if (fromPages === null) {
	console.log('実ページ: playwright が無いので省略');
} else if (fromPages.size === 0) {
	console.log(`実ページ: ${BASE} に到達できず省略`);
} else {
	const missingPages = [...fromPages].filter(([name]) => !declared.has(name));
	console.log(`実ページ: ${fromPages.size}種 / 未登録 ${missingPages.length}種`);
	missingPages.forEach(([name, where]) => problems.push(`${name}  (描画: ${where})`));
}

// 使っていない登録は害が無い（数KBの差）が、増え続けると棚卸しできなくなるので知らせる。
const seen = new Set([...fromSource.keys(), ...(fromPages ? fromPages.keys() : [])]);
const unused = [...declared].filter((n) => !seen.has(n) && !KNOWN_DYNAMIC.has(n));
if (unused.length) {
	console.log(`\nINFO  どこにも見当たらない登録 ${unused.length}種（消してよいか要確認）: ${unused.join(', ')}`);
}

if (problems.length) {
	console.error(`\n未登録のアイコン ${problems.length} 種。inc/icon-font.php に追加すること。`);
	console.error('登録しないとグリフが無く、リガチャの文字がそのまま表示される。');
	problems.forEach((p) => console.error(`  - ${p}`));
	process.exit(1);
}

console.log('\n未登録のアイコンなし');
