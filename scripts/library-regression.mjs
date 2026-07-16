/**
 * Node Library カード回帰チェック（NODE_LIBRARY_REGRESSION_PLAN.md 準拠）。
 *
 * scripts/library-fixtures.php で再作成した固定フィクスチャ記事に対して、
 * タブ切替・可視プラットフォームボタン・ハード別注記・Steam埋め込みトグル・
 * 機種警告のクリック表示とタイムアウト・空ピルなし・モバイル幅の横はみ出し・
 * console error ゼロを Playwright で検証する。不一致は exit 1。
 *
 * 実行方法:
 *   1. （テーマ側を変更した場合のみ）bun x vite build → cybernode.local へ rsync 同期
 *      → 同期先の plugins-embedded/ を削除 → Super Cache クリア:
 *      rm -rf "/Users/saitoutatsuya/Local Sites/cybernode/app/public/wp-content/cache/supercache/"*
 *   2. LocalWPのphpバイナリで scripts/library-fixtures.php を実行（ヘッダ参照）
 *   3. bun scripts/library-regression.mjs
 *
 * スクリーンショットは scratch/library-regression/ に保存（リリース証跡用）。
 */

import { mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { chromium } from 'playwright';

const BASE = process.env.NODE_LIBRARY_BASE_URL ?? 'http://cybernode.local';
const OUT_DIR = join(process.cwd(), 'scratch', 'library-regression');
const WARNING_TIMEOUT_MS = 3500; // main.js initNodeLibraryNintendoWarnings の自動クローズ時間

const failures = [];
let checkCount = 0;

function check(fixture, label, ok, detail = '') {
  checkCount++;
  if (!ok) failures.push({ fixture, label, detail });
  console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${label}${!ok && detail ? ` — ${detail}` : ''}`);
}

/** 現在可視のプラットフォームボタン（ピル＋常時表示バッジ）を収集する。 */
async function visibleButtons(page) {
  return page.$$eval(
    // App Store / Google Play はピルではなくバッジ画像リンクから遷移するため、
    // 常時表示（mac/windows）に限らず全ストアバッジリンクを可視ボタンとして数える。
    '.node-library-card .m3-platform-button, .node-library-card .m3-platform-store-badge-link',
    (els) =>
      els
        .filter((el) => !el.hidden && el.offsetParent !== null)
        .map((el) => ({
          text: (el.innerText || '').replace(/\s+/g, ' ').trim(),
          aria: el.getAttribute('aria-label') || '',
          href: el.getAttribute('href') || '',
          note: el.querySelector('.m3-platform-button__note')?.textContent.trim() ?? '',
        }))
  );
}

/** 同一ラベルのピルが並ぶケース（Nintendo×2等）があるため、href基準で特定する。 */
function findButton(buttons, exp) {
  return buttons.find((b) => b.href === exp.href);
}

/**
 * 指定タブを有効化する。「全てを表示」ビュー中はタブ列が隠れるため、
 * 戻るボタンが見えていれば先にデバイスタイプ別ビューへ戻す。
 */
async function activateTab(page, tabKey) {
  const panel = page.locator(`.node-library-card [data-node-library-panel="${tabKey}"]`);
  if (await panel.isVisible()) return;

  const back = page.locator('.node-library-card [data-node-library-tab-back]');
  if (await back.isVisible()) {
    await back.click();
    await page.waitForTimeout(150);
  }
  await page.locator(`.node-library-card [data-node-library-tab="${tabKey}"]`).click();
  await page.waitForTimeout(150);
}

/**
 * 警告リンク（クリックで機種警告を出すピル）をhref基準で取得する。
 * 同一リンクがタブパネルと「全てを表示」パネルの両方に描画されるため、可視のものに絞る。
 */
function warningLink(page, href) {
  return page
    .locator(`.node-library-card a[data-node-library-platform-warning][href="${href}"]`)
    .filter({ visible: true })
    .first();
}

async function runFixture(browser, fixture) {
  console.log(`\n=== ${fixture.slug} ===`);
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return;
    const url = msg.location()?.url ?? '';
    // 外部オリジン（Steam iframe・バッジ画像CDN等）のエラーはサイト起因ではないため除外。
    if (url && !url.startsWith(BASE)) return;
    consoleErrors.push(`${msg.text()} (${url})`);
  });
  page.on('pageerror', (err) => consoleErrors.push(`pageerror: ${err.message}`));

  await page.goto(`${BASE}/${fixture.slug}/`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('.node-library-card', { timeout: 15000 });
  await page.evaluate(() => document.fonts.ready); // アイコンフォント読込前だと寸法計測がぶれる
  await page.waitForTimeout(400); // main.js 初期化待ち

  // --- タブ ---
  const tabLocator = page.locator('.node-library-card [role="tab"][data-node-library-tab]');
  const tabCount = await tabLocator.count();
  if (fixture.tabs) {
    check(fixture.slug, `タブ数 ${fixture.tabs.length}`, tabCount === fixture.tabs.length, `actual=${tabCount}`);
    for (const tabKey of fixture.tabs) {
      const tab = page.locator(`.node-library-card [data-node-library-tab="${tabKey}"]`);
      await tab.click();
      await page.waitForTimeout(150);
      const selected = await tab.getAttribute('aria-selected');
      const panelVisible = await page
        .locator(`.node-library-card [data-node-library-panel="${tabKey}"]`)
        .isVisible();
      check(fixture.slug, `タブ切替 ${tabKey}`, selected === 'true' && panelVisible);
    }
  } else {
    check(fixture.slug, 'タブなし（単一グループ）', tabCount === 0, `actual=${tabCount}`);
  }

  // --- 可視ボタン（ラベル・href・注記） ---
  for (const [tabKey, expected] of Object.entries(fixture.buttons)) {
    if (fixture.tabs) {
      await activateTab(page, tabKey);
    }
    const buttons = await visibleButtons(page);
    check(
      fixture.slug,
      `[${tabKey}] 可視ボタン数 ${expected.length}`,
      buttons.length === expected.length,
      `actual=${buttons.length}: ${buttons.map((b) => b.text || b.aria).join(' | ')}`
    );
    for (const exp of expected) {
      const btn = findButton(buttons, exp);
      check(fixture.slug, `[${tabKey}] href ${exp.href}`, Boolean(btn));
      if (btn) {
        check(
          fixture.slug,
          `[${tabKey}] ラベル「${exp.match}」`,
          btn.text.includes(exp.match) || btn.aria.includes(exp.match),
          `actual=${btn.text || btn.aria}`
        );
        if (exp.note !== undefined) {
          check(fixture.slug, `[${tabKey}] 注記 ${exp.note}`, btn.note === exp.note, `actual=${btn.note || '(なし)'}`);
        }
      }
    }
    // 空ピル検査: 可視ボタンは必ずラベルかaria-labelを持つ。
    const empty = buttons.filter((b) => !b.text && !b.aria);
    check(fixture.slug, `[${tabKey}] 空ピルなし`, empty.length === 0, `${empty.length}件`);
  }

  // --- Steam 埋め込みトグル ---
  if (fixture.steam) {
    // 「全てを表示」ビューのままだと最初のパネル内ピルが不可視のため、先頭タブへ戻す。
    if (fixture.tabs) {
      await activateTab(page, fixture.tabs[0]);
    }
    const control = page.locator('.node-library-card [data-node-library-steam-control]');
    const toggle = page.locator('.node-library-card [data-node-library-steam-toggle]');
    const panel = page.locator('.node-library-card [data-node-library-steam-panel]');
    // Steamピルはタブパネル＋「全てを表示」パネルに重複描画されるため、可視数で判定する。
    const steamPillVisible = async () =>
      (await page.locator('.node-library-card .m3-platform-button--steam:visible').count()) > 0;
    // トグルは <details> 内にあるため、まずドット（summary）を開いてから
    // 視覚スイッチ（label）をクリックして操作する（実UIと同じ経路）。
    const openControl = async () => {
      const isOpen = await control.evaluate((el) => el.open);
      if (!isOpen) {
        await control.locator('summary').click();
        await page.waitForTimeout(200);
      }
    };
    const flipToggle = async () => {
      await openControl();
      await control.locator('.node-library-steam-control__switch').click();
      await page.waitForTimeout(300);
    };

    check(fixture.slug, 'Steamトグルoff: ピル表示', await steamPillVisible());
    check(fixture.slug, 'Steamトグルoff: 埋め込み非表示', !(await panel.isVisible()));
    if (fixture.screenshots?.includes('embed-off')) {
      await page.screenshot({ path: join(OUT_DIR, `${fixture.slug}-embed-off.png`), fullPage: false });
    }

    await flipToggle();
    check(fixture.slug, 'Steamトグルon: checked状態', await toggle.isChecked());
    check(fixture.slug, 'Steamトグルon: ピル残骸なし', !(await steamPillVisible()));
    check(fixture.slug, 'Steamトグルon: 埋め込み表示', await panel.isVisible());
    const iframeSrc = await panel.locator('iframe').first().getAttribute('src');
    check(
      fixture.slug,
      'Steam埋め込みsrc',
      Boolean(iframeSrc && iframeSrc.startsWith('https://store.steampowered.com/widget/')),
      `actual=${iframeSrc}`
    );
    if (fixture.screenshots?.includes('embed-on')) {
      await page.waitForTimeout(1200); // iframe描画待ち（証跡用）
      await page.screenshot({ path: join(OUT_DIR, `${fixture.slug}-embed-on.png`), fullPage: false });
    }

    await flipToggle();
    check(fixture.slug, 'Steamトグルoff復帰: ピル再表示', await steamPillVisible());
  }

  // --- 機種警告（クリックで表示 → タイムアウトで自動クローズ＋カード寸法復元） ---
  if (fixture.warning) {
    const link = warningLink(page, fixture.warning.href);
    if (fixture.tabs && fixture.warning.tab) {
      await activateTab(page, fixture.warning.tab);
    }
    const warningSpan = link.locator('xpath=ancestor::span[contains(@class,"node-library-platform-link")]//span[contains(@class,"node-library-platform-warning")]');
    check(fixture.slug, '警告: クリック前は非表示', !(await warningSpan.isVisible()));

    const cardBoxBefore = await page.locator('.node-library-card').first().boundingBox();
    await link.click();
    await page.waitForTimeout(200);
    const shown = await warningSpan.isVisible();
    const warningText = shown ? (await warningSpan.textContent()).trim() : '';
    check(fixture.slug, '警告: クリックで表示', shown);
    check(fixture.slug, `警告文「${fixture.warning.message}」`, warningText === fixture.warning.message, `actual=${warningText}`);
    check(fixture.slug, '警告表示中はページ遷移しない', page.url().startsWith(`${BASE}/${fixture.slug}/`));

    if (fixture.screenshots?.includes('warning-mobile')) {
      await page.setViewportSize({ width: 390, height: 844 });
      await page.waitForTimeout(200);
      await page.screenshot({ path: join(OUT_DIR, `${fixture.slug}-warning-mobile.png`), fullPage: false });
      await page.setViewportSize({ width: 1440, height: 1000 });
    }

    await page.waitForTimeout(WARNING_TIMEOUT_MS + 700);
    check(fixture.slug, '警告: タイムアウトで自動クローズ', !(await warningSpan.isVisible()));
    const cardBoxAfter = await page.locator('.node-library-card').first().boundingBox();
    check(
      fixture.slug,
      '警告クローズ後にカード寸法が復元',
      Math.abs((cardBoxBefore?.height ?? 0) - (cardBoxAfter?.height ?? 0)) < 2,
      `before=${cardBoxBefore?.height} after=${cardBoxAfter?.height}`
    );
  }

  // --- モバイル幅の横はみ出し ---
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(300);
  const overflow = await page.evaluate(() => {
    const el = document.scrollingElement;
    return { scrollWidth: el.scrollWidth, clientWidth: el.clientWidth };
  });
  check(
    fixture.slug,
    'モバイル幅(390px)で横はみ出しなし',
    overflow.scrollWidth <= overflow.clientWidth + 1,
    `scrollWidth=${overflow.scrollWidth} clientWidth=${overflow.clientWidth}`
  );
  if (fixture.screenshots?.includes('mobile')) {
    await page.locator('.node-library-card').scrollIntoViewIfNeeded();
    await page.screenshot({ path: join(OUT_DIR, `${fixture.slug}-mobile.png`), fullPage: false });
  }
  if (fixture.screenshots?.includes('desktop')) {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('.node-library-card').scrollIntoViewIfNeeded();
    await page.waitForTimeout(200);
    await page.screenshot({ path: join(OUT_DIR, `${fixture.slug}-desktop.png`), fullPage: false });
  }

  // --- console error ---
  check(fixture.slug, 'console errorゼロ（同一オリジン）', consoleErrors.length === 0, consoleErrors.join(' / '));

  await page.close();
}

// フィクスチャ定義は scripts/library-fixtures.php と対で管理する（変更時は両方更新）。
const FIXTURES = [
  {
    slug: 'node-library-regression-steam-only',
    tabs: null,
    buttons: {
      single: [
        { match: 'Steam', href: 'https://store.steampowered.com/app/1091500/Cyberpunk_2077/' },
      ],
    },
    steam: true,
    screenshots: ['embed-off', 'embed-on', 'mobile'],
  },
  {
    slug: 'node-library-regression-steam-mixed',
    tabs: ['pc', 'mobile', 'console', 'all'],
    buttons: {
      pc: [
        { match: 'Steam', href: 'https://store.steampowered.com/app/1091500/Cyberpunk_2077/' },
        { match: 'Microsoft Store (Windows)', href: 'https://apps.microsoft.com/detail/9ncbcszsjrsb' },
      ],
      mobile: [
        { match: 'App Store', href: 'https://apps.apple.com/jp/app/id1604212236' },
      ],
      console: [
        { match: 'PS Store', href: 'https://store.playstation.com/ja-jp/product/EP4497-PPSA10666_00-0000000000000CP7', note: '(PS5)' },
      ],
      all: [
        { match: 'Steam', href: 'https://store.steampowered.com/app/1091500/Cyberpunk_2077/' },
        { match: 'Microsoft Store (Windows)', href: 'https://apps.microsoft.com/detail/9ncbcszsjrsb' },
        { match: 'App Store', href: 'https://apps.apple.com/jp/app/id1604212236' },
        { match: 'PS Store', href: 'https://store.playstation.com/ja-jp/product/EP4497-PPSA10666_00-0000000000000CP7', note: '(PS5)' },
      ],
    },
    steam: true,
    warning: {
      tab: 'console',
      href: 'https://store.playstation.com/ja-jp/product/EP4497-PPSA10666_00-0000000000000CP7',
      message: 'このタイトルはPS5専用です。',
    },
    screenshots: ['desktop'],
  },
  {
    slug: 'node-library-regression-console-mixed',
    tabs: null, // 全リンクconsoleカテゴリのため単一グループ
    buttons: {
      single: [
        { match: 'Nintendo Store', href: 'https://store-jp.nintendo.com/item/software/D70010000010193' },
        { match: 'Nintendo Store', href: 'https://store-jp.nintendo.com/item/software/D70010000096732' },
        { match: 'PS Store', href: 'https://store.playstation.com/ja-jp/product/JP0082-CUSA05088_00-KINGDOMHEARTS300', note: '(PS4)' },
        { match: 'PS Store', href: 'https://store.playstation.com/ja-jp/product/JP0082-PPSA02684_00-KINGDOMHEARTS3PS', note: '(PS5)' },
        { match: 'Microsoft Store（Xbox）', href: 'https://www.xbox.com/ja-jp/games/store/x/9nblggh43dpt', note: '(Xbox One)' },
        { match: 'Microsoft Store（Xbox）', href: 'https://www.xbox.com/ja-jp/games/store/x/9n2s04lgxxh4', note: '(Xbox Series X|S)' },
      ],
    },
    warning: {
      href: 'https://store-jp.nintendo.com/item/software/D70010000010193',
      message: 'このソフトはSwitch専用ソフトです。',
    },
    screenshots: ['desktop', 'warning-mobile'],
    /** PS4+PS5 / XboxOne+Series の両対応グループは警告が抑止されること（正本: 現行仕様）。 */
    noWarningHrefs: [
      'https://store.playstation.com/ja-jp/product/JP0082-CUSA05088_00-KINGDOMHEARTS300',
      'https://store.playstation.com/ja-jp/product/JP0082-PPSA02684_00-KINGDOMHEARTS3PS',
      'https://www.xbox.com/ja-jp/games/store/x/9nblggh43dpt',
      'https://www.xbox.com/ja-jp/games/store/x/9n2s04lgxxh4',
    ],
  },
  {
    slug: 'node-library-regression-mobile-apps',
    tabs: null,
    buttons: {
      single: [
        { match: 'App Store', href: 'https://apps.apple.com/jp/app/id310633997' },
        { match: 'Google Play', href: 'https://play.google.com/store/apps/details?id=com.whatsapp' },
        { match: 'Amazon App Store', href: 'https://www.amazon.co.jp/dp/B00DTHYPKW' },
      ],
    },
    screenshots: ['mobile'],
  },
  {
    slug: 'node-library-regression-invalid-links',
    tabs: null,
    buttons: {
      // 重複Steam URLは1ピルへ重複排除。空URL・名無しリンクは除外され空ピルが出ない。
      single: [{ match: 'Steam', href: 'https://store.steampowered.com/app/730/CS2/' }],
    },
    steam: true,
    screenshots: [],
  },
];

async function main() {
  await mkdir(OUT_DIR, { recursive: true });
  const browser = await chromium.launch();

  for (const fixture of FIXTURES) {
    try {
      await runFixture(browser, fixture);

      // 両対応グループの警告抑止（属性が付かないこと）を静的に確認。
      if (fixture.noWarningHrefs) {
        const page = await browser.newPage();
        await page.goto(`${BASE}/${fixture.slug}/`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.node-library-card', { timeout: 15000 });
        for (const href of fixture.noWarningHrefs) {
          const hasAttr = await page
            .locator(`.node-library-card a[href="${href}"]`)
            .first()
            .evaluate((el) => el.hasAttribute('data-node-library-platform-warning'));
          check(fixture.slug, `両対応につき警告抑止 ${href.slice(-20)}`, !hasAttr);
        }
        await page.close();
      }
    } catch (error) {
      failures.push({ fixture: fixture.slug, label: '実行エラー', detail: error.message });
      console.log(`  FAIL 実行エラー — ${error.message}`);
    }
  }

  await browser.close();

  console.log(`\n合計 ${checkCount} チェック / 失敗 ${failures.length} 件`);
  if (failures.length > 0) {
    console.log('\n失敗項目:');
    for (const f of failures) console.log(`  - [${f.fixture}] ${f.label}${f.detail ? ` — ${f.detail}` : ''}`);
    process.exit(1);
  }
  console.log(`スクリーンショット: ${OUT_DIR}`);
}

main();
