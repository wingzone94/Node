#!/usr/bin/env bun
/**
 * モバイル表示の回帰チェック（Node 1.3.1）。
 *
 * cybernode.local（LocalWP起動中が前提）のトップページに対して、
 * 1.3.1 で入れたモバイル向けの修正が実際に効いているかを実測する。
 * CSS の詳細度負けのように「書いたのに効いていない」類は目視では見落とすため、
 * 計算済みスタイルと実寸で機械的に判定する。
 *
 * 使い方:
 *   bun scripts/mobile-check.mjs
 *   bun scripts/mobile-check.mjs --base=http://cybernode.local
 *
 * 事前に `bun x playwright install chromium` が必要（verify:visual と同じ）。
 * ブラウザの実体を明示したい場合は NODE_PW_EXECUTABLE で上書きできる。
 */

import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';

const argv = process.argv.slice(2);

function cliOption(name) {
	const prefix = `--${name}=`;
	const hit = argv.find((a) => a.startsWith(prefix));
	return hit ? hit.slice(prefix.length) : null;
}

const BASE = (cliOption('base') ?? process.env.NODE_VISUAL_BASE_URL ?? 'http://cybernode.local').replace(/\/$/, '');
const EXECUTABLE = process.env.NODE_PW_EXECUTABLE || undefined;

/** index.php の $latest_mobile_limit / $latest_limit と対応させる。 */
const LATEST_MOBILE_LIMIT = 3;
const LATEST_LIMIT = 6;
/** _cards.css / _category-nav.css のブレークポイント。 */
const MOBILE_BREAKPOINT = 700;
/** iOS のヒューマンインターフェイスガイドラインが推奨するタップ領域。 */
const MIN_TAP_TARGET = 44;

const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
const outputDir = join(process.cwd(), 'scratch', 'mobile-check', timestamp);

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

/** ページ内で共通して使う計測。ブラウザ側で評価される。 */
function collect() {
	const visible = (el) => {
		const cs = getComputedStyle(el);
		return cs.display !== 'none' && cs.visibility !== 'hidden' && el.getBoundingClientRect().height > 0;
	};

	const cards = [...document.querySelectorAll('.m3-post-grid__container--featured > .m3-card')];
	const overflowCards = [...document.querySelectorAll('.m3-latest-card--mobile-overflow')];
	const pills = [...document.querySelectorAll('.m3-category-nav__pill')];

	const bleeding = [...document.querySelectorAll('body *')]
		.filter((el) => {
			const r = el.getBoundingClientRect();
			return r.width > 0 && (r.right > window.innerWidth + 1 || r.left < -1);
		})
		.slice(0, 5)
		.map((el) => {
			const cls = (el.getAttribute('class') || '').split(' ').filter(Boolean).slice(0, 2).join('.');
			return `${el.tagName.toLowerCase()}${cls ? '.' + cls : ''}`;
		});

	return {
		cardsTotal: cards.length,
		cardsVisible: cards.filter(visible).length,
		overflowTotal: overflowCards.length,
		overflowVisible: overflowCards.filter(visible).length,
		hasCategorySection: !!document.querySelector('#categories.m3-category-nav'),
		categorySectionVisible: (() => {
			const el = document.querySelector('#categories.m3-category-nav');
			return el ? visible(el) : false;
		})(),
		pillCount: pills.length,
		pillMinHeight: pills.length ? Math.min(...pills.map((p) => Math.round(p.getBoundingClientRect().height))) : null,
		pillTruncated: pills
			.filter((p) => {
				const n = p.querySelector('.m3-category-nav__name');
				return n && n.scrollWidth > n.clientWidth + 1;
			})
			.map((p) => p.querySelector('.m3-category-nav__name').textContent.trim()),
		archivePillVisible: (() => {
			const el = document.querySelector('.m3-archive-pill-button');
			return el ? visible(el) : false;
		})(),
		footerCategoryLinks: document.querySelectorAll('.m3-footer__col--categories a').length,
		footerColumns: (() => {
			const grid = document.querySelector('.m3-footer__grid');
			return grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : null;
		})(),
		// 無限スクロールが復活していないかの直接確認。
		scrollerScripts: [...document.querySelectorAll('script[src]')]
			.map((s) => s.getAttribute('src'))
			.filter((src) => /node-flow|scroller/i.test(src)),
		horizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
		bleeding,
		pageHeight: Math.round(document.documentElement.scrollHeight),
		screens: +(document.documentElement.scrollHeight / window.innerHeight).toFixed(2),
	};
}

async function openHome(browser, width, height) {
	const context = await browser.newContext({
		viewport: { width, height },
		deviceScaleFactor: 2,
		isMobile: width <= MOBILE_BREAKPOINT,
		hasTouch: width <= MOBILE_BREAKPOINT,
	});
	const page = await context.newPage();

	// 未捕捉の例外だけを失敗扱いにする。画像や広告の 404 / 500 まで拾うと、
	// テーマと無関係な1件でチェック全体が落ちて使い物にならない。
	const jsErrors = [];
	const resourceErrors = [];
	page.on('pageerror', (e) => jsErrors.push(String(e)));
	page.on('console', (m) => {
		if (m.type() !== 'error') return;
		const text = m.text();
		if (/Failed to load resource|net::ERR_|favicon/i.test(text)) resourceErrors.push(text);
		else jsErrors.push(text);
	});

	const response = await page.goto(BASE + '/', { waitUntil: 'networkidle' });
	return { context, page, response, jsErrors, resourceErrors };
}

async function run() {
	await mkdir(outputDir, { recursive: true });

	console.log('Mobile check (Node 1.3.1)');
	console.log(`- Base URL: ${BASE}`);
	console.log(`- Screenshots: ${outputDir}`);

	const browser = await chromium.launch({ executablePath: EXECUTABLE });

	try {
		// --- モバイル幅: 1.3.1 の本命 ---------------------------------------
		{
			const { context, page, response, jsErrors, resourceErrors } = await openHome(browser, 390, 844);
			console.log('\n[390x844] トップページ');
			check(response?.status() === 200, 'HTTP 200', `got ${response?.status()}`);

			const d = await page.evaluate(collect);

			check(
				d.cardsVisible === LATEST_MOBILE_LIMIT,
				`LATEST の表示は ${LATEST_MOBILE_LIMIT} 件`,
				`表示${d.cardsVisible} / 全${d.cardsTotal}`
			);
			check(
				d.overflowTotal > 0 && d.overflowVisible === 0,
				'4件目以降は畳まれている',
				`overflow指定${d.overflowTotal} / 見えている${d.overflowVisible}`
			);
			check(d.hasCategorySection && d.categorySectionVisible, 'CATEGORY セクションが出ている', `pill=${d.pillCount}`);
			check(
				d.pillMinHeight === null || d.pillMinHeight >= MIN_TAP_TARGET,
				`カテゴリピルのタップ領域が ${MIN_TAP_TARGET}px 以上`,
				`最小 ${d.pillMinHeight}px`
			);
			check(d.pillTruncated.length === 0, 'カテゴリ名が省略されていない', d.pillTruncated.join(' / ') || 'なし');
			check(d.archivePillVisible, '「すべての記事を見る」が出ている');
			check(d.scrollerScripts.length === 0, '無限スクロールのスクリプトが読み込まれていない', d.scrollerScripts.join(', ') || 'なし');
			check(!d.horizontalOverflow, '横スクロールが発生していない', d.bleeding.join(' | ') || '');
			check(jsErrors.length === 0, 'JSエラーなし', jsErrors.slice(0, 3).join(' | ') || 'なし');
			if (resourceErrors.length) {
				console.log(`  INFO  読み込みに失敗したリソース ${resourceErrors.length} 件（テーマ外の可能性あり）: ${resourceErrors.slice(0, 2).join(' | ')}`);
			}
			console.log(`  INFO  ページ全体 ${d.pageHeight}px = ${d.screens} 画面ぶん / フッター ${d.footerColumns} 列 / フッターのカテゴリ ${d.footerCategoryLinks} 件`);

			await page.screenshot({ path: join(outputDir, 'home-390-full.png'), fullPage: true });
			const cat = page.locator('#categories');
			if (await cat.count()) await cat.screenshot({ path: join(outputDir, 'home-390-category.png') });

			// --- 検索サジェスト（実データ依存なので best-effort）--------------
			console.log('\n[390x844] 検索キーワードのサジェスト');
			const toggle = page.locator('#search-toggle');
			if (await toggle.count()) {
				await toggle.click();
				await page.fill('#m3-search-input', 'ニュ');
				await page.waitForTimeout(900); // 250ms デバウンス + REST 往復
				const s = await page.evaluate(() => {
					const box = document.getElementById('m3-search-suggestions');
					if (!box) return { exists: false };
					const opts = [...box.querySelectorAll('[role="option"]')];
					const input = document.getElementById('m3-search-input');
					const br = box.getBoundingClientRect();
					const ir = input.getBoundingClientRect();
					return {
						exists: true,
						active: box.classList.contains('is-active'),
						count: opts.length,
						minHeight: opts.length ? Math.min(...opts.map((o) => Math.round(o.getBoundingClientRect().height))) : null,
						truncated: opts
							.filter((o) => {
								const l = o.querySelector('.m3-search-suggestion__label');
								return l && l.scrollHeight > l.clientHeight + 1;
							})
							.map((o) => o.querySelector('.m3-search-suggestion__label').textContent.trim()),
						offscreen: br.right > window.innerWidth + 1 || br.left < -1,
						alignedWithInput: Math.abs(br.left - ir.left) <= 2 && Math.abs(br.right - ir.right) <= 24,
						expanded: input.getAttribute('aria-expanded'),
					};
				});

				if (!s.exists) {
					check(false, 'サジェストの受け皿が存在する', '#m3-search-suggestions が無い');
				} else if (s.count === 0) {
					skip('候補の表示', 'この環境では候補が0件（Node Library / カテゴリのデータ次第）');
				} else {
					check(s.active, 'ドロップダウンが開く', `候補 ${s.count} 件`);
					check(s.expanded === 'true', 'aria-expanded が true');
					check(!s.offscreen, 'ドロップダウンが画面外へ出ない');
					check(s.alignedWithInput, '入力欄と左右が揃っている');
					check(s.minHeight >= MIN_TAP_TARGET, `候補のタップ領域が ${MIN_TAP_TARGET}px 以上`, `最小 ${s.minHeight}px`);
					check(s.truncated.length === 0, '候補名が省略されていない', s.truncated.join(' / ') || 'なし');
					await page.screenshot({ path: join(outputDir, 'search-suggest-390.png') });
				}
			} else {
				skip('検索サジェスト', '#search-toggle が見つからない');
			}

			await context.close();
		}

		// --- ブレークポイントの境界 ------------------------------------------
		for (const [width, expected] of [
			[MOBILE_BREAKPOINT, LATEST_MOBILE_LIMIT],
			[MOBILE_BREAKPOINT + 1, LATEST_LIMIT],
		]) {
			const { context, page, jsErrors } = await openHome(browser, width, 900);
			console.log(`\n[${width}x900] ブレークポイント境界`);
			const d = await page.evaluate(collect);
			check(d.cardsVisible === expected, `LATEST の表示は ${expected} 件`, `表示${d.cardsVisible} / 全${d.cardsTotal}`);
			check(!d.horizontalOverflow, '横スクロールが発生していない', d.bleeding.join(' | ') || '');
			check(jsErrors.length === 0, 'JSエラーなし', jsErrors.slice(0, 3).join(' | ') || 'なし');
			await context.close();
		}

		// --- デスクトップ ----------------------------------------------------
		{
			const { context, page, jsErrors } = await openHome(browser, 1440, 1000);
			console.log('\n[1440x1000] デスクトップ');
			const d = await page.evaluate(collect);
			check(d.cardsVisible === LATEST_LIMIT, `LATEST の表示は ${LATEST_LIMIT} 件`, `表示${d.cardsVisible} / 全${d.cardsTotal}`);
			check(d.footerColumns === 3, 'フッターが3列', `${d.footerColumns} 列`);
			check(d.footerCategoryLinks > 0, 'フッターにカテゴリがある', `${d.footerCategoryLinks} 件`);
			check(d.archivePillVisible, '「すべての記事を見る」が出ている');
			check(!d.horizontalOverflow, '横スクロールが発生していない', d.bleeding.join(' | ') || '');
			check(jsErrors.length === 0, 'JSエラーなし', jsErrors.slice(0, 3).join(' | ') || 'なし');
			await page.screenshot({ path: join(outputDir, 'home-1440-full.png'), fullPage: true });
			await context.close();
		}
	} finally {
		await browser.close();
	}

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
