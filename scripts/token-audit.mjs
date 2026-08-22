#!/usr/bin/env bun
/**
 * デザイントークン欠落の検査（Node 2.0 Preview 2）。
 *
 * 未定義のカスタムプロパティを参照した宣言は「invalid at computed-value time」となり、
 * `background` は透明に、`color` は親から継承し、`box-shadow` は none になる。
 * 「書いたのに効いていない」類は目視では見落とすため、機械的に判定する。
 * 判定内容と背景は `NODE-2.0-PREVIEW2.md` を参照。
 *
 * 使い方:
 *   bun scripts/token-audit.mjs --static          # LocalWP 不要。出荷CSSの棚卸しだけ
 *   bun run verify:tokens                          # 既定は http://cybernode.local
 *   bun scripts/token-audit.mjs --base=http://...  # URL 指定
 *
 * ライブモードは事前に `bun x playwright install chromium` が必要（verify:visual と同じ）。
 * ブラウザの実体を明示したい場合は NODE_PW_EXECUTABLE で上書きできる。
 *
 * 注意:
 * - R1〜R4 を実施する前は FAIL するのが正常。本スクリプトは受け入れ条件そのもの。
 * - 静的モードは「どこかに定義があるか」しか見ない。ダークにだけ定義があってライトで
 *   欠けているもの（例 --md-sys-color-surface-container-highest）は素通りする。
 *   スキーム別の欠落はライブモードが light/dark 両方を実測して捕まえる。
 * - 静的モードはビルド済みの assets/css/style.css を数えるので、src/styles/ を直接
 *   grep した数とは1〜2件ずれることがある。
 */

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const argv = process.argv.slice(2);

function cliOption(name) {
	const prefix = `--${name}=`;
	const hit = argv.find((a) => a.startsWith(prefix));
	return hit ? hit.slice(prefix.length) : null;
}

const STATIC_ONLY = argv.includes('--static');
const BASE = (cliOption('base') ?? process.env.NODE_VISUAL_BASE_URL ?? 'http://cybernode.local').replace(/\/$/, '');
const EXECUTABLE = process.env.NODE_PW_EXECUTABLE || undefined;

/** iOS のヒューマンインターフェイスガイドラインが推奨するタップ領域。mobile-check.mjs と同じ。 */
const MIN_TAP_TARGET = 44;
/**
 * backdrop-filter が意味を持つ背景の不透明度の上限。
 * これを超えるとぼかす対象がほぼ見えず、GPUコストだけ払うことになる（＝「うそのガラス」）。
 */
const MAX_GLASS_ALPHA = 0.85;

/** R1/R2 で定義されるべきトークン。ここが空だと参照側の宣言がまとめて無効になる。 */
const REQUIRED_TOKENS = [
	'--m3-elevation-1',
	'--m3-elevation-2',
	'--m3-elevation-3',
	'--m3-elevation-4',
	'--md-sys-elevation-level1',
	'--md-sys-color-on-surface-variant',
	'--md-sys-color-surface-container-high',
	'--md-sys-color-surface-container-highest',
	'--md-sys-color-outline',
];

const failures = [];
const notes = [];

function check(ok, label, detail) {
	console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? ` — ${detail}` : ''}`);
	if (!ok) failures.push(`${label}${detail ? ` (${detail})` : ''}`);
}

function skip(label, detail) {
	console.log(`  SKIP  ${label}${detail ? ` — ${detail}` : ''}`);
	notes.push(`${label}${detail ? ` (${detail})` : ''}`);
}

function info(label) {
	console.log(`  INFO  ${label}`);
}

// ---------------------------------------------------------------------------
// 静的モード: 出荷CSSの棚卸し
// ---------------------------------------------------------------------------

/** `--foo:` の形だけを定義とみなす。`var(--foo)` や `var(--foo, x)` は句読点が違うので拾わない。 */
function collectDefinitions(source) {
	const found = new Set();
	for (const m of source.matchAll(/--([\w-]+)\s*:/g)) found.add(`--${m[1]}`);
	return found;
}

/** 参照を集め、フォールバック（第2引数）の有無で分ける。 */
function collectReferences(source) {
	const refs = new Map();
	for (const m of source.matchAll(/var\(\s*--([\w-]+)\s*([,)])/g)) {
		const name = `--${m[1]}`;
		const entry = refs.get(name) ?? { withFallback: 0, noFallback: 0 };
		if (m[2] === ',') entry.withFallback += 1;
		else entry.noFallback += 1;
		refs.set(name, entry);
	}
	return refs;
}

/** CSS の外でトークンを差しうるソース（テーマ本体の PHP と src の JS）を列挙する。 */
function phpAndJsSources() {
	const roots = ['.', 'inc', 'template-parts', 'src'];
	const files = [];
	const walk = (dir, depth) => {
		let entries;
		try {
			entries = readdirSync(dir);
		} catch {
			return;
		}
		for (const entry of entries) {
			if (entry.startsWith('.') || entry === 'node_modules') continue;
			const full = join(dir, entry);
			if (statSync(full).isDirectory()) {
				if (depth > 0) walk(full, depth - 1);
			} else if (/\.(php|js|mjs)$/.test(entry)) {
				files.push(full);
			}
		}
	};
	for (const root of roots) walk(join(process.cwd(), root), 3);
	return files;
}

function runStaticAudit() {
	const cssPath = join(process.cwd(), 'assets', 'css', 'style.css');
	const css = readFileSync(cssPath, 'utf8');

	// CSS の外で値が入るトークンがある。
	//   - `<style id="m3-dynamic-colors">`（inc/utilities.php）が :root へ流し込むもの
	//   - テンプレートが style 属性で要素ごとに差すもの（--spotlight-color 等）
	//   - JS が setProperty で差すもの（--category-on-color 等）
	// CSS だけを見るとこれらを「未定義」と誤診するため、外側の定義も集める。
	const runtimeDefinitions = new Set();
	for (const file of phpAndJsSources()) {
		const source = readFileSync(file, 'utf8');
		for (const name of collectDefinitions(source)) runtimeDefinitions.add(name);
		for (const m of source.matchAll(/setProperty\(\s*['"]--([\w-]+)['"]/g)) {
			runtimeDefinitions.add(`--${m[1]}`);
		}
	}

	const cssDefinitions = collectDefinitions(css);
	const references = collectReferences(css);

	console.log('\n[static] 出荷CSSの棚卸し');
	console.log(`  対象: ${cssPath}`);
	console.log(`  定義: CSS ${cssDefinitions.size} 件 / CSS外（PHP/JS） ${runtimeDefinitions.size} 件`);
	console.log(`  参照: ${references.size} 種類\n`);

	const undefinedTokens = [...references.entries()]
		.filter(([name]) => !cssDefinitions.has(name) && !runtimeDefinitions.has(name))
		.sort((a, b) => b[1].noFallback - a[1].noFallback);

	const hardFailures = undefinedTokens.filter(([, c]) => c.noFallback > 0);

	if (hardFailures.length) {
		console.log('  未定義のまま参照されているトークン（フォールバック無し＝宣言が無効）:');
		for (const [name, count] of hardFailures) {
			const extra = count.withFallback ? ` / フォールバック付き ${count.withFallback}` : '';
			console.log(`    ${String(count.noFallback).padStart(4)} 箇所  ${name}${extra}`);
		}
	}

	const phpOnly = [...references.keys()].filter((n) => !cssDefinitions.has(n) && runtimeDefinitions.has(n));
	if (phpOnly.length) {
		info(`CSS に無く PHP/JS 側でのみ定義（要素ごとに差すもの・正本が二重のもの）: ${phpOnly.join(', ')}`);
	}

	const total = hardFailures.reduce((sum, [, c]) => sum + c.noFallback, 0);
	check(
		hardFailures.length === 0,
		'未定義トークンを参照している宣言が無い',
		hardFailures.length ? `${hardFailures.length} 種類 / 計 ${total} 宣言` : ''
	);

	return hardFailures.length === 0;
}

// ---------------------------------------------------------------------------
// ライブモード: cybernode.local の実サイトを計測
// ---------------------------------------------------------------------------

/** rgba() と color(srgb … / a) の両方から alpha を取り出す。color-mix は後者で返る。 */
function alphaOf(cssColor) {
	if (!cssColor || cssColor === 'transparent') return 0;
	const slash = cssColor.match(/\/\s*([\d.]+)\s*\)/);
	if (slash) return Number.parseFloat(slash[1]);
	const rgba = cssColor.match(/rgba?\(([^)]+)\)/);
	if (rgba) {
		const parts = rgba[1].split(',').map((p) => p.trim());
		return parts.length >= 4 ? Number.parseFloat(parts[3]) : 1;
	}
	return 1;
}

/** ページ内で共通して使う計測。ブラウザ側で評価される。 */
function collect(requiredTokens) {
	const visible = (el) => {
		const cs = getComputedStyle(el);
		return cs.display !== 'none' && cs.visibility !== 'hidden' && el.getBoundingClientRect().height > 0;
	};
	const style = (sel) => {
		const el = document.querySelector(sel);
		if (!el) return null;
		const cs = getComputedStyle(el);
		const rect = el.getBoundingClientRect();
		return {
			boxShadow: cs.boxShadow,
			color: cs.color,
			backgroundColor: cs.backgroundColor,
			fontSize: Number.parseFloat(cs.fontSize),
			height: +rect.height.toFixed(1),
		};
	};

	const root = getComputedStyle(document.documentElement);
	const tokens = Object.fromEntries(requiredTokens.map((n) => [n, root.getPropertyValue(n).trim()]));

	// backdrop-filter が乗っているのに背景がほぼ不透明な要素を拾う。
	const glass = [...document.querySelectorAll('body *')]
		.filter((el) => {
			const cs = getComputedStyle(el);
			const bf = cs.backdropFilter || cs.webkitBackdropFilter;
			return bf && bf !== 'none' && visible(el);
		})
		.map((el) => {
			const cs = getComputedStyle(el);
			const cls = (el.getAttribute('class') || '').split(' ').filter(Boolean).slice(0, 2).join('.');
			return {
				label: `${el.tagName.toLowerCase()}${cls ? '.' + cls : ''}`,
				background: cs.backgroundColor,
				filter: cs.backdropFilter || cs.webkitBackdropFilter,
			};
		});

	return {
		tokens,
		card: style('.m3-card'),
		cardTitle: style('.m3-card__title'),
		cardDate: style('.m3-card__date'),
		header: style('.m3-header'),
		badge: style('.m3-spotlight-badge'),
		sectionTitle: style('.m3-headlines__title'),
		glass,
	};
}

async function measureCardHover(page) {
	const card = await page.$('.m3-card');
	if (!card) return null;
	const before = await card.evaluate((el) => getComputedStyle(el).boxShadow);
	try {
		// スクロール外・非表示のカードで 30 秒待ってから落ちないよう短く切る。
		await card.scrollIntoViewIfNeeded({ timeout: 2000 });
		await card.hover({ timeout: 2000 });
	} catch (error) {
		return { unavailable: error.message.split('\n')[0] };
	}
	// transition: box-shadow 0.4s の着地を待つ。
	await page.waitForTimeout(600);
	const after = await card.evaluate((el) => getComputedStyle(el).boxShadow);
	return { before, after };
}

async function runLiveAudit() {
	const { chromium } = await import('playwright');

	console.log('\n[live] 実サイトの計測');
	console.log(`  Base URL: ${BASE}`);

	const browser = await chromium.launch({ executablePath: EXECUTABLE });
	try {
		const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
		const page = await context.newPage();

		let response;
		try {
			response = await page.goto(BASE + '/', { waitUntil: 'networkidle', timeout: 20000 });
		} catch (error) {
			console.log(`\n  ${BASE} に到達できませんでした。LocalWP が起動しているか確認してください。`);
			console.log(`  （静的な棚卸しだけなら LocalWP 不要: bun scripts/token-audit.mjs --static）`);
			console.log(`  詳細: ${error.message.split('\n')[0]}`);
			await context.close();
			return false;
		}

		check(response?.status() === 200, 'HTTP 200', `got ${response?.status()}`);

		for (const theme of ['light', 'dark']) {
			await page.evaluate((t) => {
				document.documentElement.dataset.theme = t;
			}, theme);
			const d = await page.evaluate(collect, REQUIRED_TOKENS);

			console.log(`\n  --- ${theme} ---`);

			const missing = REQUIRED_TOKENS.filter((n) => !d.tokens[n]);
			check(missing.length === 0, `必須トークンが解決する（${theme}）`, missing.join(', ') || '');

			if (d.card) {
				check(d.card.boxShadow !== 'none', `カードに影がある（${theme}）`, d.card.boxShadow);
			} else {
				skip(`カードの影（${theme}）`, '.m3-card が見つからない');
			}

			if (d.header) {
				check(d.header.boxShadow !== 'none', `ヘッダーに影がある（${theme}）`, d.header.boxShadow);
			}

			// on-surface-variant が未定義だと副文が本文色を継承し、カード内の強弱が消える。
			if (d.cardTitle && d.cardDate) {
				check(
					d.cardDate.color !== d.cardTitle.color,
					`カードの副文がタイトルと別の色（${theme}）`,
					`日付 ${d.cardDate.color} / タイトル ${d.cardTitle.color}`
				);
			} else {
				skip(`カードの副文の色（${theme}）`, 'タイトルか日付が見つからない');
			}

			const fakeGlass = d.glass.filter((g) => alphaOf(g.background) >= MAX_GLASS_ALPHA);
			check(
				fakeGlass.length === 0,
				`backdrop-filter がすべて実効ある透過の上にある（${theme}）`,
				fakeGlass.map((g) => `${g.label} α=${alphaOf(g.background).toFixed(2)}`).join(' | ')
			);

			if (theme === 'light') {
				if (d.badge && d.sectionTitle) {
					check(
						d.badge.fontSize < d.sectionTitle.fontSize,
						'SPOTLIGHT ピルがセクション見出しより小さい',
						`ピル ${d.badge.fontSize}px / 見出し ${d.sectionTitle.fontSize}px`
					);
					check(
						d.badge.height >= MIN_TAP_TARGET,
						`ピルのタップ領域が ${MIN_TAP_TARGET}px 以上`,
						`${d.badge.height}px`
					);
					info(`ピル配色（R6 の参考値・FAIL にはしない）: ${d.badge.color} on ${d.badge.backgroundColor}`);
				} else {
					skip('SPOTLIGHT ピルの寸法', 'ピルかセクション見出しが見つからない（SPOTLIGHT 未設定の環境）');
				}
			}
		}

		const hover = await measureCardHover(page);
		if (hover) {
			console.log('\n  --- hover ---');
			if (hover.unavailable) {
				skip('カードの hover で影が変化する', hover.unavailable);
			} else {
				check(
					hover.before !== hover.after,
					'カードの hover で影が変化する（transition: box-shadow が生きている）',
					`${hover.before} → ${hover.after}`
				);
			}
		}

		await context.close();
	} finally {
		await browser.close();
	}

	return true;
}

// ---------------------------------------------------------------------------

async function run() {
	console.log('Token audit (Node 2.0 Preview 2)');
	console.log('判定の根拠は NODE-2.0-PREVIEW2.md を参照。R1〜R4 実施前は FAIL が正常。');

	runStaticAudit();
	if (!STATIC_ONLY) await runLiveAudit();

	console.log('\n--- 結果 ---');
	if (notes.length) {
		console.log(`SKIP ${notes.length} 件:`);
		for (const n of notes) console.log(`  - ${n}`);
	}
	if (failures.length) {
		console.log(`FAIL ${failures.length} 件:`);
		for (const f of failures) console.log(`  - ${f}`);
		process.exitCode = 1;
		return;
	}
	console.log('すべて PASS');
}

run().catch((error) => {
	console.error(error);
	process.exitCode = 1;
});
