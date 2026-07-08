# Node テーマ リファクタリング計画書（2026-07-03 版）

対象リポジトリ: `Node`（WordPress テーマ「Node」/ サイト名「Luminous Core」）
対象ブランチ: `codex/release-1.1.3`（作業開始時点でワーキングツリーに未コミット変更あり。項目 R-0 で必ずスナップショットを取ること）

> **この計画書の読み方**
> - 実行順は「R-0 → R-1 → … → R-11」。**順番を入れ替えないこと。**
> - 本書中の行番号は「R-0 のスナップショットコミット直後のワーキングツリー」を基準とする。行番号がずれていた場合は、**関数名を正とする**（各項目に対象関数名を明記してある）。
> - 1 項目 = 1 コミット。コミット前に必ずその項目の「完了条件」をすべて満たすこと。満たせない場合は**中断して報告**（コミットしない）。
> - 本書自体（REFACTORING_PLAN.md）はリポジトリにコミットしてよいが、リリース ZIP（node.zip）には含めないこと。

---

## 1. 現状理解（実行者への文脈共有）

### 1.1 これは何か

WordPress のクラシックテーマ。Vite（bun）で `src/` の JS/CSS をビルドして `assets/` に出力し、PHP 側は Vite の manifest（`assets/.vite/manifest.json`）を読んで enqueue する構成。テーマに 9 個の「埋め込みプラグイン」（`plugins-embedded/`）が同梱され、`functions.php` が直接 require して初期化する。

### 1.2 主要ファイルの役割と依存関係（構造マップ）

```
functions.php (1079行)  ← 本来「ローダー + 最低限の初期化」を自称しているが、実際は約1000行のロジック持ち【最大の問題】
 ├─ require inc/theme-setup.php   … テーマ基本設定・バージョン取得 node_get_theme_version()
 ├─ require inc/hooks.php         … 記事表示フック（AI要約/読了時間/年齢ゲート/画像lightbox 等）
 ├─ require inc/meta-boxes.php, category-meta.php, ajax.php, spotlight.php,
 │          archive-helpers.php, media.php, search.php
 ├─ require inc/utilities.php (712行) … 色計算・カテゴリラベル・バッジ・読了ランク・広告枠 等【責務混在】
 ├─ require inc/gemini-helper.php, gemini-models.php, gemini-user-settings.php,
 │          admin-settings.php, seo.php, scheduler.php
 ├─ require inc/ogp-generator.php  … 【中身が空のレガシースタブ（12行）＝デッドコード】
 ├─ require inc/toc-engine.php, blogcard.php (749行)
 ├─ plugins-embedded/ 9プラグインを require + init 関数直接実行
 └─ 以降 約1000行: Viteアセット読み込み / 独自リライトルール(all-articles・headlines・spotlight)
    / Service Worker / ブランド名正規化 / スラッグ自動変換 / FOUC対策 / About固定ページの文面補正
    / 脚注エンジン(約270行) / posts_per_page 制御 …
```

- **テンプレート**: `header.php`（インラインでテーマ切替 JS・PWA スプラッシュ定義を持つ・約860行）、`single.php`、`index.php`、`template-parts/`（カード各種）。テンプレートは `inc/utilities.php` と `inc/hooks.php` の関数を直接呼ぶ。
- **フロント JS**: `src/main.js` がエントリ。`src/scripts/` の各モジュールを静的/動的 import。ビルド出力は `assets/js/<name>.<hash>.js`（`vite.config.js` は `emptyOutDir: false` のため**古いハッシュのファイルが assets/js に堆積し続ける**）。
- **CSS**: `src/styles/style.css` が `@import` で 40 個超のパーシャルを束ねる。
- **テスト**: `tests/` に PHPUnit（wp-phpunit）テストが 2 本（`node-blogcard-test.php`, `node-series-test.php`）。`tests/bootstrap.php` は LocalWP 環境（cybernode.local）の WP コアを使い、DB は `wordpress_test` に接続する。実行は `composer test`。
- **自己更新機構（重要）**: `inc/ajax.php:54` が `https://github.com/wingzone94/Node/raw/master/node.zip` を直接ダウンロードして自動更新する。**リポジトリ直下の `node.zip`（9MB）は Git 管理から外してはならない。**
- **リリースフロー**: `HOW_TO_RELEASE.md` 参照。`bun x vite build` → `bun run verify:visual`（cybernode.local に対して Playwright 検査） → rsync で ZIP 生成。

### 1.3 洗い出した問題（優先度順の根拠）

| # | 問題 | 効果 | リスク | 対応項目 |
|---|------|------|--------|----------|
| 1 | `functions.php` が「ローダー」を自称しつつ約1000行のロジック持ち（アセット/ルーティング/脚注/コンテンツ補正が混在） | 大 | 低（純粋な移動で対応可能） | R-4〜R-7 |
| 2 | Git に不要物が残存（`scratch/` 3件・`.DS_Store` 5件は .gitignore 済みなのに追跡中） | 中 | 極低 | R-1 |
| 3 | `assets/js/` に古いハッシュのビルド成果物が堆積（20件中、manifest 参照は一部のみ）。リリース ZIP を肥大化させる | 中 | 低 | R-2 |
| 4 | デッドコード: `src/styles/.ai-summary-card.css`（どこからも参照なし）、`inc/ogp-generator.php`（空スタブ） | 小 | 極低 | R-3 |
| 5 | `node_enqueue_assets()` のエラー処理の穴（`json_decode` 失敗を未検査、`?? time()` の到達不能フォールバック） | 中 | 低 | R-8 |
| 6 | `inc/utilities.php` の責務混在（色計算 8 関数 + 表示ヘルパー + 広告 + 読了ランク） | 中 | 低 | R-9 |
| 7 | ブランドカラー `#FF9900` の直値が PHP 7 ファイル・21 箇所に散在 | 中 | 中 | R-10 |
| 8 | FOUC 対策の二重化: `node_critical_inline_styles()` が body を常時表示強制（!important）にしたため、`node_body_visibility_fallback()` の 2 秒タイマー・noscript が機能的に死んでいる | 小 | 中 | R-11（見送り可） |
| 9 | 命名不統一（`node_` / `luminous_` / `m3_` プレフィックス混在） | 小 | **高**（テンプレート全域に波及） | **やらない**（§5） |

---## 2. 項目 R-0: 安全網の構築（最初に必ず実行）

### R-0-a. 作業前スナップショットコミット

ワーキングツリーには v1.2 に向けた未コミット変更（blogcard 関連ほか 16 ファイル + 未追跡多数）がある。これをリファクタリングと混ぜないため、まず現状をそのままコミットする。

```bash
cd /Users/saitoutatsuya/Documents/GitHub/Node
git add -A
git commit -m "wip: pre-refactor snapshot (v1.2 blogcard WIP + tests)"
git switch -c refactor/structure-cleanup
```

以降の作業はすべて `refactor/structure-cleanup` ブランチ上で行う。**master / main には触れない。**

### R-0-b. ベースライン確認

以下を順に実行し、結果をメモに残す（後続項目の「期待結果」はこのベースラインとの比較）。

1. **PHP 構文チェック（全ファイル）**
   ```bash
   find . -name "*.php" -not -path "./node_modules/*" -not -path "./vendor/*" \
     -not -path "./.tmp_node_prod_zip/*" -not -path "./scratch/*" \
     -exec php -l {} \; 2>&1 | grep -v "No syntax errors" || echo "ALL OK"
   ```
   期待: `ALL OK`（エラー行が 1 行も出ない）。

2. **既存 PHPUnit テスト**
   ```bash
   composer test
   ```
   期待: `OK` （blogcard / series の全テスト green）。
   ※ LocalWP（cybernode.local）が起動しており、MySQL に `wordpress_test` DB が存在することが前提（`tests/wp-tests-config.php` 参照）。**この環境が用意できない場合は中断して報告**（本計画のかなりの検証がこれに依存する）。

3. **フロントビルド**
   ```bash
   bun install && bun x vite build
   ```
   期待: exit 0。`git status` で `assets/` 配下の差分有無を記録。

4. **ビジュアル回帰ベースライン**
   ```bash
   bun run verify:visual
   ```
   期待: 検査 pass。スクリーンショットが `scratch/visual-check/` に保存される。

5. **HTML 特性スナップショット（最重要の特性テスト）**
   PHP の「純粋な移動」項目（R-4〜R-7, R-9）では、フロント出力 HTML は 1 バイトも変わらないはず。それを固定する。
   ```bash
   mkdir -p ~/node-refactor-baseline
   for p in "/" "/all-articles/" "/headlines/" "/spotlight/" "/?s=node"; do
     curl -s "http://cybernode.local${p}" > ~/node-refactor-baseline/$(echo "$p" | tr '/?=' '___').html
   done
   # 公開済み記事を1本選び（脚注付き・複数ページの記事があればそれを優先）、URL を固定して取得
   curl -s "http://cybernode.local/post-XX/" > ~/node-refactor-baseline/single.html
   ```
   各項目の検証時は同じコマンドで再取得し `diff` する。期待: **差分ゼロ**（wp_nonce やコメントフォームの hidden 値など動的トークン行のみの差は許容。それ以外の差分が出たら失敗）。

### R-0-c. 特性テストの追加仕様

既存テストは blogcard / series のみ。R-6・R-9 で移動する純粋関数について、**移動と同じコミット内で**下記の特性テストを追加する（期待値は現行実装から静的に導出済み。そのままテストコードにしてよい）。

**tests/node-footnotes-test.php**（R-6 で作成。`inc/footnotes.php` を bootstrap の `muplugins_loaded` フィルタ内で require に追加する）

| テスト名 | 入力 | 期待値 |
|---|---|---|
| `test_extract_refs_basic` | `node_extract_footnote_references_from_html('<p>a<sup data-fn="fn-1" id="fn-1-link"><a href="#fn-1">1</a></sup>b</p>')` | `['ids' => ['fn-1'], 'numbers' => ['fn-1' => 1]]` |
| `test_extract_refs_dedup` | 同一 `data-fn="fn-1"` の sup を 2 個含む HTML | `ids` は `['fn-1']` の 1 件のみ |
| `test_extract_refs_empty` | `''` および `'<p>no footnotes</p>'` | `['ids' => [], 'numbers' => []]` |
| `test_extract_refs_non_string` | `null` | `['ids' => [], 'numbers' => []]` |

**tests/node-colors-test.php**（R-9 で作成。`inc/colors.php` を bootstrap に追加）

| テスト名 | 入力 | 期待値 |
|---|---|---|
| `test_mix_half_white` | `node_mix_hex_color('#ff0000', '#ffffff', 0.5)` | `'#ff8080'` |
| `test_mix_ratio_zero` | `node_mix_hex_color('#123456', '#ffffff', 0)` | `'#123456'` |
| `test_mix_invalid_source` | `node_mix_hex_color('red', '#ffffff', 0.5)` | `'#FF9900'`（不正入力時のフォールバック） |
| `test_readable_on_white` | `node_get_readable_text_color('#ffffff')` | `'#2b1700'` |
| `test_readable_on_black` | `node_get_readable_text_color('#000000')` | `'#ffffff'` |
| `test_readable_on_brand` | `node_get_readable_text_color('#FF9900')` | `'#2b1700'`（YIQ=166.056 ≥ 150） |
| `test_readable_invalid` | `node_get_readable_text_color('nonsense')` | `'#ffffff'` |
| `test_default_color_case_insensitive` | `node_is_default_category_color('#ff9900')` | `true` |

---

## 3. 作業項目リスト（実行順）

### R-1: Git 追跡から gitignore 済みファイルを外す

- **対象**: `scratch/check_posts.php`, `scratch/fix_src.cjs`, `scratch/list-categories.php`, 追跡中の `.DS_Store` 5 件
- **問題**: `.gitignore` に `scratch/`・`.DS_Store` が記載済みなのに、ignore 追加前にコミットされたため追跡され続けている。
- **どう変えるか**:
  ```bash
  git rm -r --cached scratch/
  git ls-files | grep "\.DS_Store$" | xargs git rm --cached
  git commit -m "chore: untrack gitignored files (scratch/, .DS_Store)"
  ```
  **注意: `node.zip` は絶対に untrack しないこと**（`inc/ajax.php:54` の自己更新が GitHub 上の `node.zip` を直接参照している。§5 参照）。
- **完了条件**: `git ls-files | grep -E "^scratch/|\.DS_Store"` の出力が空。ファイル自体はディスク上に残っている（`ls scratch/` で確認）。
- **リスク/戻し方**: 極低。`git revert <コミット>` で復帰。
- **依存**: R-0

### R-2: assets/js の古いビルド成果物を削除

- **対象**: `assets/js/` 配下のハッシュ付き JS（追跡 20 件 + 未追跡 4 件）のうち、現行 manifest から参照されていないもの（例: `main.BNl4TydD.js` と `main.DMU0uVyd.js` が併存 = 少なくとも一方は古い）。
- **問題**: `vite.config.js` の `emptyOutDir: false` により古いハッシュ版が削除されず堆積。リリース ZIP と Git 履歴を肥大化させる。
- **どう変えるか**: 機械的に判定して削除する。
  ```bash
  bun x vite build   # まず manifest を最新化
  cd assets/js
  for f in *.js; do
    # manifest に載っているか、PHP からファイル名で直接参照されているものは残す
    if ! grep -q "$f" ../.vite/manifest.json && \
       ! grep -rq "$f" ../../inc ../../plugins-embedded ../../*.php ../../template-parts; then
      echo "DELETE: $f"; rm "$f"; git rm --cached --ignore-unmatch "$f" > /dev/null
    fi
  done
  ```
  `blocks.js`（ハッシュなし・Vite 管理外）は上記 grep で PHP 参照が見つかるはずなので残る。万一 DELETE 対象に出たら**中断して報告**。
- **完了条件**: ① `bun x vite build` が exit 0。② `bun run verify:visual` pass。③ ブラウザで `http://cybernode.local/` を開き、開発者ツールの Console/Network に 404・エラーがない。
- **リスク/戻し方**: manifest 参照分を残す限り安全。問題が出たら `git checkout HEAD~1 -- assets/js/` で全復帰。
- **依存**: R-1（同じ「リポジトリ掃除」系のため。技術的依存はない）

### R-3: デッドファイルの削除

- **対象**:
  - `src/styles/.ai-summary-card.css` — どこからも `@import` されていない（`src/styles/style.css` の import 一覧に無し、grep で自己参照のみ）
  - `inc/ogp-generator.php` — 中身がコメントのみのレガシースタブ、および `functions.php:49` の `require_once ... '/inc/ogp-generator.php';` 行
- **問題**: 参照されない/何もしないファイルが「読むべきコード」を水増ししている。
- **どう変えるか**: 上記 2 ファイルを `git rm`。`functions.php` から require 1 行を削除。
- **完了条件**: ① `grep -rn "ogp-generator\|ai-summary-card" . --include="*.php" --include="*.css" --include="*.js" -l`（node_modules/vendor/scratch/.tmp を除く）が空。② `php -l functions.php` OK。③ `composer test` green。④ `bun x vite build` exit 0。
- **リスク/戻し方**: 極低。`git revert` 1 発。
- **依存**: R-0

### R-4: functions.php からアセット読み込み系を inc/assets.php へ抽出

- **対象**: `functions.php` の以下の関数と、対応する `add_action`/`add_filter` 行（現状 86〜196, 379〜396, 443〜503, 526〜576 行付近）:
  `node_register_vite_chain`, `node_enqueue_assets`, `node_register_service_worker`, `node_preload_webfonts`, `node_body_visibility_fallback`, `node_script_loader_tag`, `node_critical_inline_styles`, `node_fix_admin_visibility`
- **問題**: 「ローダーに徹する」と宣言している functions.php にアセット配信ロジックが直書きされている。
- **どう変えるか**: **純粋なカット&ペースト**（1 文字も書き換えない）。新規 `inc/assets.php` を作成し、先頭に以下を置いてから移動:
  ```php
  <?php
  /**
   * Vite アセット読み込み・インラインスタイル・Service Worker 登録
   *
   * @package Luminous_Core
   */

  if ( ! defined( 'ABSPATH' ) ) {
      exit;
  }
  ```
  `functions.php` の inc 読み込みブロック（`require_once` 群）の**末尾**（`inc/blogcard.php` の次）に `require_once NODE_THEME_DIR . '/inc/assets.php';` を追加。
  ※ `node_enqueue_assets` は `node_get_all_articles_url()`（R-5 まで functions.php 側に残る）と `luminous_enqueue_plugin_scripts()`（inc/hooks.php）を呼ぶが、フック発火時には全ファイル読み込み済みのため問題ない。
- **完了条件**: ① `php -l functions.php inc/assets.php` OK。② `composer test` green。③ R-0-b-5 の HTML スナップショット再取得で**差分ゼロ**。④ `bun run verify:visual` pass。
- **リスク/戻し方**: 移動漏れ（関数だけ移して `add_action` 行を置き忘れる/その逆）が典型。移動前に `grep -c "^function\|^add_action\|^add_filter" functions.php` を控え、移動後に functions.php と inc/assets.php の合計が一致することを確認。失敗時は `git revert`。
- **依存**: R-3（functions.php の行番号ずれを最小化するため直前の functions.php 編集を先に済ませる）

### R-5: functions.php から独自ルーティング系を inc/routing.php へ抽出

- **対象関数**（+ 対応する add_action/add_filter 行。現状 198〜377, 1024〜1079 行付近）:
  `node_get_all_articles_url`, `node_get_headlines_url`, `node_get_spotlight_url`, `node_is_spotlight_archive_request`, `node_spotlight_request_fallback`, `node_spotlight_404_fallback`, `node_register_all_articles_rewrite_rule`, `node_add_all_articles_query_var`, `node_use_all_articles_template`, `node_maybe_flush_rewrite_rules_for_all_articles`, `node_custom_posts_per_page`, `node_headlines_pre_get_posts`, `node_spotlight_pre_get_posts`, `node_redirect_spotlight_category_archive`
- **問題**: `/all-articles/`・`/headlines/`・`/spotlight/` の独自 URL 実装（リライト・クエリ・テンプレート差し替え・404 フォールバック）が functions.php に散在し、関連コードが 3 か所に分断されている。
- **どう変えるか**: R-4 と同じ様式で新規 `inc/routing.php` に純粋移動。require は `inc/assets.php` の直前に追加（`node_enqueue_assets` が `node_get_all_articles_url` を参照するため、定義順を素直に保つ）。定数 `NODE_ALL_ARTICLES_SLUG` 等は functions.php に残す（他所からも見えるグローバル定数のため）。
- **完了条件**: ① `php -l` OK。② `composer test` green。③ HTML スナップショット差分ゼロ（特に `/all-articles/`・`/headlines/`・`/spotlight/` の 3 ページが 200 で内容不変であること）。④ `curl -s -o /dev/null -w "%{http_code}" http://cybernode.local/category/spotlight/` が `301`。
- **リスク/戻し方**: リライトルールは DB にキャッシュされるため、移動自体でルールが消えることはない。万一 `/all-articles/` が 404 になったら WP 管理画面 > 設定 > パーマリンクを一度保存（flush）してから再確認。コード起因なら `git revert`。
- **依存**: R-4

### R-6: 脚注エンジンを inc/footnotes.php へ抽出 + 特性テスト追加

- **対象関数**（現状 739〜1016 行付近）: `node_get_footnote_multipage_url`, `node_extract_footnote_references_from_html`, `node_render_footnote_section`, `node_reposition_current_page_footnotes` と `add_filter( 'the_content', 'node_reposition_current_page_footnotes', 999 );`
- **問題**: 約 280 行の独立した機能（ページ分割記事の脚注をタブ UI で再配置）が functions.php に埋没。ロジックが複雑で、今後の変更時に最も退行しやすい箇所。
- **どう変えるか**:
  1. R-4 と同じ様式で `inc/footnotes.php` へ純粋移動（require 追加）。
  2. `tests/bootstrap.php` の `muplugins_loaded` フィルタ内に `require dirname( __DIR__ ) . '/inc/footnotes.php';` を追加。
  3. §R-0-c の表どおり `tests/node-footnotes-test.php` を作成（クラス名 `Node_Footnotes_Test extends WP_UnitTestCase`。既存 `tests/node-blogcard-test.php` の体裁に合わせる）。
- **完了条件**: ① `php -l` OK。② `composer test` green（新テスト `Node_Footnotes_Test` の 4 メソッドを含む）。③ 脚注付き記事の HTML スナップショット差分ゼロ。
- **リスク/戻し方**: `node_reposition_current_page_footnotes` は `the_content` priority 999 で動く。priority や関数名を 1 文字でも変えないこと。失敗時 `git revert`。
- **依存**: R-5

### R-7: コンテンツ補正フィルタ群を inc/content-fixes.php へ抽出

- **対象関数**（現状 398〜441, 517〜524, 578〜737 行付近）:
  `node_enforce_branding_update`, `luminous_brand_normalize`, `luminous_core_auto_post_slug`, `node_user_contact_methods`, `node_is_auto_draft_placeholder_title`, `node_force_default_post_status_on_save`, `node_normalize_feed_guid`, `node_fix_footer_menu_placeholder_urls`, `node_normalize_about_page_content`
- **問題**: 「サイト運用上の応急処置」（ブランド名正規化・About 固定ページの文面補正・フッターメニューの仮 URL 差し替え等）がテーマの基盤コードに混ざっている。分離して「これは応急処置である」と可視化する。
- **どう変えるか**: 純粋移動で `inc/content-fixes.php` を作成（様式は R-4 と同じ）。ファイル先頭 docblock に「本ファイルはコンテンツ運用上の暫定補正。DB 側の修正が完了したものから削除してよい」と明記する。**フィルタの中身・文言は 1 文字も変更しない**（文言変更は仕様変更）。
  この移動が終わると functions.php は「定数定義 + require 群 + 埋め込みプラグインローダー + `add_theme_support('admin-bar', ...)` 1 行」だけの約 120 行になるはず。
- **完了条件**: ① `php -l` OK。② `composer test` green。③ HTML スナップショット差分ゼロ（特にトップページの `<title>`・About ページ）。④ `wc -l functions.php` が 150 行未満。
- **リスク/戻し方**: `git revert`。
- **依存**: R-6

### R-8: node_enqueue_assets のエラー処理の穴を塞ぐ

- **対象**: `inc/assets.php`（R-4 で移動済み）内 `node_enqueue_assets()` と `node_register_vite_chain()`
- **問題**: ① manifest の `json_decode` 失敗（破損 JSON）で `null` が返ると、以降の `isset($manifest[...])` は静かに全 false になり原因が追いにくい。② `node_register_vite_chain()` の `$asset_version = $manifest[$key]['file'] ?? (string) time();` は直後 2 行前で `$file = $manifest[$key]['file'];` と無条件参照しており、`??` フォールバックは到達不能のデッドコード。むしろ `file` キー欠落時は warning で落ちる。
- **どう変えるか**:
  ```php
  // 変更前（node_enqueue_assets 冒頭）
  $manifest = json_decode( file_get_contents( $manifest_path ), true );
  // 変更後
  $manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
  if ( ! is_array( $manifest ) ) {
      return;
  }
  ```
  ```php
  // 変更前（node_register_vite_chain 内）
  $file   = $manifest[ $key ]['file'];
  $path   = NODE_THEME_DIR . '/assets/' . $file;
  $asset_version = $manifest[ $key ]['file'] ?? (string) time();
  // 変更後
  if ( empty( $manifest[ $key ]['file'] ) ) {
      return $handles;
  }
  $file          = $manifest[ $key ]['file'];
  $asset_version = $file;
  ```
  （`$path` は元コードでも未使用のため、この機会に削除してよい。それ以外の変更は禁止。）
- **完了条件**: ① `php -l inc/assets.php` OK。② HTML スナップショット差分ゼロ。③ 一時的に `assets/.vite/manifest.json` を `mv` で退避してトップページを開き、白画面や PHP エラーにならず「スタイルなし HTML」が表示されることを確認 → `mv` で戻す。④ `composer test` green。
- **リスク/戻し方**: 挙動変更は「壊れた manifest のとき静かに何もしない」のみ。`git revert`。
- **依存**: R-4

### R-9: inc/utilities.php から色計算系を inc/colors.php へ抽出 + 特性テスト追加

- **対象関数**（`inc/utilities.php` 現状 40〜335 行付近）: `node_get_image_seed_color`, `node_get_readable_text_color`, `node_mix_hex_color`, `node_get_category_color`, `node_is_default_category_color`, `node_generate_m3_colors` と `add_action('wp_head', 'node_generate_m3_colors');`
- **問題**: utilities.php（712 行）に「色の数値計算」「カテゴリラベル HTML 生成」「バッジ」「読了ランク」「広告枠」が同居。色計算はテーマの核（動的カラー）なので独立させ、テスト可能にする。
- **どう変えるか**:
  1. 純粋移動で `inc/colors.php` を作成。functions.php の require 群で `inc/utilities.php` の**直前**に追加（`node_generate_m3_colors` が utilities 側の `node_get_post_categories_for_display` を呼ぶが、フック発火時には両方読み込み済みなので順序はどちらでも動く。読みやすさのため色→utilities の順とする）。
  2. `tests/bootstrap.php` に `require dirname( __DIR__ ) . '/inc/colors.php';` を追加。
  3. §R-0-c の表どおり `tests/node-colors-test.php` を作成（クラス名 `Node_Colors_Test extends WP_UnitTestCase`）。
  ※ `node_get_category_label_props` 以降のラベル描画系は utilities.php に**残す**（HTML 生成は色計算とは別責務）。
- **完了条件**: ① `php -l inc/colors.php inc/utilities.php` OK。② `composer test` green（`Node_Colors_Test` 8 メソッド含む）。③ HTML スナップショット差分ゼロ（特に `<style id="m3-dynamic-colors">` ブロックが不変であること: `diff <(grep -A40 'm3-dynamic-colors' baseline) <(grep -A40 'm3-dynamic-colors' 再取得)`）。
- **リスク/戻し方**: `git revert`。
- **依存**: R-6（テスト bootstrap を触る項目同士の競合を避けるため直列化）

### R-10: デフォルトブランドカラー #FF9900 の直値を定数に集約

- **対象**: PHP 内で「デフォルト/フォールバック色」として使われる `#FF9900`（大文字小文字混在）。対象ファイル: `inc/colors.php`（R-9 後）, `inc/utilities.php`, `inc/archive-helpers.php`, `inc/admin-settings.php`, `inc/category-meta.php`, `inc/theme-setup.php`, `inc/spotlight.php`
- **問題**: ブランドカラー変更時に 21 箇所の直値を漏れなく修正する必要があり、既に大文字小文字の表記ゆれがある。
- **どう変えるか**:
  1. `functions.php` の定数ブロックに追加: `define( 'NODE_DEFAULT_BRAND_COLOR', '#FF9900' );`
  2. `grep -rn "#FF9900\|#ff9900" inc/*.php` で全箇所を列挙し、**PHP 文字列リテラルとして使われている箇所のみ** `NODE_DEFAULT_BRAND_COLOR` に置換（例: `$default_primary = '#FF9900';` → `$default_primary = NODE_DEFAULT_BRAND_COLOR;`）。
  3. **置換しないもの**: `header.php` の `<meta name="theme-color" content="#FF9900">` や `mask-icon` の color 属性などテンプレート内 HTML 直書き、PHP 内にヒアドキュメント/echo で埋め込まれた CSS 文字列の中の色。これらを PHP 式に書き換えるのは可読性を下げるため今回はしない。判断に迷う箇所は置換せずスキップし、報告に列挙する。
  4. `node_is_default_category_color()` は `strcasecmp` 比較のため既に大小無関係。比較対象リテラルのみ定数化する。
- **完了条件**: ① `php -l` 全 OK。② `composer test` green（`test_mix_invalid_source` のフォールバック値が不変であることを含む）。③ HTML スナップショット差分ゼロ。④ `grep -rn "'#[Ff][Ff]9900'" inc/*.php` が空（シングルクォートの直値リテラルが残っていない）。
- **リスク/戻し方**: 置換ミスで色が変わる可能性 → HTML スナップショット diff が検出する。`git revert`。
- **依存**: R-9（対象ファイル inc/colors.php が存在している必要がある）

### R-11: 【任意・見送り可】機能的に死んでいる FOUC フォールバックの削除

- **対象**: `inc/assets.php`（R-4 後）内 `node_body_visibility_fallback()` と `add_action( 'wp_head', 'node_body_visibility_fallback', 2 );`
- **問題**: `node_critical_inline_styles()`（wp_head priority 1）が `body { opacity: 1 !important; visibility: visible !important; }` を常時出力するよう変更された時点で、「JS 失敗時に 2 秒後 body を表示する」タイマーと noscript の**表示保証としての役割**は消滅している。ただし `is-loaded` クラス付与のフォールバックとしての副作用は残っており、`is-loaded` はアニメーション類が参照する。
- **どう変えるか**: 削除する前に、`grep -rn "is-loaded" src/ assets/css/ inc/ header.php footer.php` で参照箇所を列挙し、「main.js が正常動作すれば `is-loaded` は必ず付与される」ことを確認した上で、関数と add_action 行を削除する。**確認で main.js 以外に付与元がなく、かつ main.js 読み込み失敗時に表示崩れ以上の実害（コンテンツ不可視）が出る構造だと判明した場合は、削除せずスキップして報告する。**
- **完了条件**: ① `php -l` OK。② `bun run verify:visual` pass。③ ブラウザ開発者ツールで JS を無効化してトップページを開き、コンテンツが可視のままであること。④ JS 有効時にカードのスクロールアニメーションが従来どおり動くこと。
- **リスク/戻し方**: 中。判断が絡むため最後に置いた。少しでも疑義があればスキップ可（スキップは失敗ではない）。`git revert` 1 発で戻る。
- **依存**: R-4, R-8

---

## 4. 実行順トレース（検証済み）

- R-1/R-2/R-3 は PHP ロジックに触れない（R-3 のみ functions.php の require 1 行削除）→ 以降の行番号ずれは R-3 分のみで、R-4 以降は関数名基準なので影響なし。
- R-4 で `node_enqueue_assets` が移動した後も、それが呼ぶ `node_get_all_articles_url` は functions.php に残っている（R-5 で移動）→ どの時点でも「フック発火前に全定義が読み込み済み」の不変条件が保たれる。
- R-6/R-9 は両方 `tests/bootstrap.php` を編集する → 直列化済み（R-9 は R-6 に依存）。
- R-8 は R-4 が作る `inc/assets.php` を、R-10 は R-9 が作る `inc/colors.php` を前提とする → 依存明記済み。
- R-11 は R-8 で整えた `inc/assets.php` を編集する → 最後に配置。

## 5. やらないことリスト（実行者への禁止事項）

1. **`node.zip` を Git 追跡から外さない・削除しない・再生成しない。** 本番サイトの自己更新機構（`inc/ajax.php:54`）が GitHub master 上の `node.zip` を直接ダウンロードしている。リリース作業自体も本計画の対象外。
2. **関数プレフィックス（`node_` / `luminous_` / `m3_`）の統一・関数リネームをしない。** テンプレート・埋め込みプラグイン全域に波及し、効果に対してリスクが大きすぎる。
3. **`header.php` のインライン JS（テーマ切替・viewport 制御）を外部ファイル化しない。** FOUC 防止のため意図的にインライン化されている。
4. **機能追加・仕様変更・文言変更をしない。** 特に `node_normalize_about_page_content` などのコンテンツ補正フィルタは「不要に見えても」削除・修正しない（移動のみ）。
5. **依存ライブラリを更新しない。** `bun.lock` / `composer.lock` / Vite のバージョン等に触れない。`bun install` で lock が書き換わった場合は `git checkout -- bun.lock` で戻す。
6. **CSS のリファクタリング（`_article.css` 2579 行の分割等）・`src/scripts/` の JS 整理をしない。** 今回のスコープは PHP 構造とリポジトリ衛生のみ。
7. **`plugins-embedded/` 配下の内部実装に触れない**（テスト bootstrap 経由の require 追加を除く）。
8. **`.gitignore`・`vite.config.js`・`HOW_TO_RELEASE.md` を変更しない**（`emptyOutDir: false` は意図がある可能性があるため、変更提案は報告に留める）。
9. **master / main ブランチへコミット・push しない。** 作業は `refactor/structure-cleanup` のみ。push の要否は依頼者の指示を待つ。
10. **DB・WordPress 管理画面の設定を変更しない**（R-5 のパーマリンク再保存によるフラッシュを除く）。

## 6. 実行者への指示文（コピペ用）

> あなたは WordPress テーマ「Node」のリファクタリング実行者です。リポジトリ直下の `REFACTORING_PLAN.md` に従って作業してください。ルール:
>
> 1. 計画書の項目 R-0 から R-11 を**この順番どおり**に実施する。順番の入れ替え・スキップは禁止（R-11 のみ、計画書記載の条件でスキップ可）。
> 2. **1 項目 = 1 コミット。** コミットメッセージは `refactor(R-N): <要約>` 形式（R-1〜R-3 は `chore(R-N): ...`）。
> 3. 各項目の「完了条件」をすべて満たしてからコミットする。**1 つでも満たせない場合は、その項目をコミットせずに作業を中断し、何をどこまでやり、何が失敗したかを報告する。**
> 4. 「純粋移動」と書かれた項目では、移動対象コードを 1 文字も変更しない（インデント・コメント含む）。
> 5. 計画書の「やらないことリスト」(§5) に該当する変更は、たとえ改善に見えても行わない。気づいた改善点は報告に書くだけにする。
> 6. 行番号が計画書とずれている場合は関数名で対象を特定する。関数名でも特定できない場合は中断して報告する。
> 7. 前提環境: LocalWP の `cybernode.local` が起動済みで、MySQL に `wordpress_test` DB が存在すること（`composer test` が動くこと）。R-0 の時点でこれが確認できなければ、以降に進まず報告する。
> 8. 全項目完了後、`git log --oneline refactor/structure-cleanup` と各完了条件の実行結果一覧を最終報告としてまとめる。push は指示があるまで行わない。
