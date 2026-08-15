#!/usr/bin/env bun
/**
 * ルート到達性スモークテスト（T-11）。
 *
 * cybernode.local（LocalWP起動中が前提）に対して主要ルートのHTTPステータスを実測し、
 * 期待表との不一致・5xx・多段リダイレクトがあれば exit 1 で失敗する。
 * リリースゲートで verify:visual と併走させる。
 *
 * 使い方:
 *   bun scripts/route-check.mjs
 *   bun scripts/route-check.mjs --base=http://cybernode.local --series=yugioh-series
 *
 * シリーズslugはハードコードせず、REST（/wp-json/wp/v2/node_series）から
 * 記事を持つtermを動的に取得する。--series= で明示指定も可能。
 */

const argv = process.argv.slice(2);

function cliOption(name) {
	const prefix = `--${name}=`;
	const hit = argv.find((a) => a.startsWith(prefix));
	return hit ? hit.slice(prefix.length) : null;
}

const BASE = (cliOption('base') ?? 'http://cybernode.local').replace(/\/$/, '');

/** リダイレクトを追わずに1リクエストだけ発行する。 */
async function probe(url) {
	const res = await fetch(url, {
		redirect: 'manual',
		headers: { 'User-Agent': 'node-route-check/1.0 (T-11 smoke)' },
	});
	return { status: res.status, location: res.headers.get('location') };
}

/** 記事を持つ node_series term の slug をRESTから動的取得する。 */
async function resolveSeriesSlug() {
	const explicit = cliOption('series');
	if (explicit) return explicit;

	try {
		const res = await fetch(`${BASE}/wp-json/wp/v2/node_series?per_page=100`);
		if (!res.ok) return null;
		const terms = await res.json();
		const withPosts = terms.find((t) => t.count > 0);
		return withPosts ? withPosts.slug : null;
	} catch {
		return null;
	}
}

/** 相対/絶対どちらのLocationも絶対URLへ正規化して比較する。 */
function normalizeUrl(value) {
	return new URL(value, BASE).href;
}

async function main() {
	const seriesSlug = await resolveSeriesSlug();

	/** @type {{path: string, expect: number, redirectTo?: string, redirectOneHop?: boolean, note?: string}[]} */
	const routes = [
		{ path: '/', expect: 200 },
		{ path: '/all-articles/', expect: 200 },
		{ path: '/headlines/', expect: 200 },
		{ path: '/spotlight/', expect: 200 },
		{ path: '/category/spotlight/', expect: 301, redirectTo: '/spotlight/' },
		seriesSlug
			? { path: `/series/${seriesSlug}/`, expect: 200 }
			: { path: '/series/{unresolved}/', expect: 200, note: 'series slug未解決（--series= で指定可）' },
		{ path: '/?s=node', expect: 200 },
		{ path: '/feed/', expect: 200 },
		{ path: '/page/999999/?utm_source=route-check', expect: 301, redirectOneHop: true },
		{ path: '/no-such-xyz/', expect: 404 },
		{ path: '/wp-sitemap.xml', expect: 200 },
	];

	const rows = [];
	let failed = false;

	for (const route of routes) {
		const row = {
			path: route.path,
			expect: route.redirectTo
				? `${route.expect} -> ${route.redirectTo}`
				: route.redirectOneHop
					? `${route.expect} -> 200 (1 hop)`
					: String(route.expect),
			actual: '-',
			result: 'OK',
			detail: '',
		};

		if (route.note) {
			row.result = 'FAIL';
			row.detail = route.note;
			failed = true;
			rows.push(row);
			continue;
		}

		try {
			const first = await probe(`${BASE}${route.path}`);
			row.actual = String(first.status);

			if (first.status >= 500) {
				row.result = 'FAIL';
				row.detail = '5xx応答';
				failed = true;
			} else if (route.redirectTo || route.redirectOneHop) {
				// 301行: ステータス・Location・1ホップで200到達の3点を検査する。
				if (first.status !== route.expect) {
					row.result = 'FAIL';
					row.detail = `expected ${route.expect}, got ${first.status}`;
					failed = true;
				} else if (!first.location) {
					row.result = 'FAIL';
					row.detail = 'Locationなし';
					failed = true;
				} else if (route.redirectTo && normalizeUrl(first.location) !== normalizeUrl(route.redirectTo)) {
					row.result = 'FAIL';
					row.detail = `Location不一致: ${first.location}`;
					failed = true;
				} else {
					const second = await probe(normalizeUrl(first.location));
					row.actual = `${first.status} -> ${second.status}`;
					if (second.status !== 200) {
						row.result = 'FAIL';
						row.detail = '1ホップで200に到達しない';
						failed = true;
					}
				}
			} else if (first.status !== route.expect) {
				row.result = 'FAIL';
				row.detail = `expected ${route.expect}, got ${first.status}${first.location ? ` (Location: ${first.location})` : ''}`;
				failed = true;
			}
		} catch (error) {
			row.result = 'FAIL';
			row.detail = `fetch失敗: ${error.message}（LocalWP起動確認）`;
			failed = true;
		}

		rows.push(row);
	}

	// --- 結果表 ---
	const headers = { path: 'PATH', expect: 'EXPECT', actual: 'ACTUAL', result: 'RESULT', detail: 'DETAIL' };
	const widths = {};
	for (const key of Object.keys(headers)) {
		widths[key] = Math.max(headers[key].length, ...rows.map((r) => String(r[key]).length));
	}
	const line = (r) => Object.keys(headers).map((k) => String(r[k]).padEnd(widths[k])).join('  ');

	console.log(`route-check: ${BASE}\n`);
	console.log(line(headers));
	console.log(Object.keys(headers).map((k) => '-'.repeat(widths[k])).join('  '));
	for (const row of rows) console.log(line(row));

	const failCount = rows.filter((r) => r.result === 'FAIL').length;
	console.log(`\n${rows.length - failCount}/${rows.length} passed`);

	process.exit(failed ? 1 : 0);
}

main();
