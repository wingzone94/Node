# Node 構造診断レポート（v1.2前・全ソース横断 / 2026-07-04）

> 診断: Claude (Fable)。実装・テストコードは書かない。後段の実装者（GPT-5.5/Codex）が本書だけで着手できる粒度に留める。
> 対象: working tree（`codex/release-1.1.3` + v1.2未コミット差分）。テーマ本体 / inc 20本 / plugins-embedded 9本 / src JS・CSS / tests / 全ドキュメント / git全履歴。
> 前提データ: アクセス分析レポート本体はリポジトリに無いため「404が全体2位=107表示・多ページ分散」という要約値を所与とした。テーマ内にGAタグは無い（計測はプラグイン側）。404はタイトル1バケットに集約されURLが多数分散する、という報告形とコード側の実態は整合する。

---

## 1. 三層原則違反マップ（深刻度順）

三層定義: (1) テーマ=表示専用 (2) Node Utility（plugins-embedded 9本）=状態管理 (3) データはWP標準構造（post/term/meta/option）。

| # | 深刻度 | 場所 | 違反内容（データフロー） | 処置 |
|---|---|---|---|---|
| V-1 | 高 | `inc/blogcard.php` `node_resolve_redirect()`（**v1.2新規差分**） | `sslverify => false` を新規追加。対象はgoo.gl/maps.app.goo.gl（Google）で検証を切る理由が無い。**絶対原則「sslverify=true対策を後退させない」への後退** | **F-2（①）** |
| V-2 | 高 | `plugins-embedded/node-series/node-series.php` `register_taxonomy()` | `rewrite => ['slug'=>'series']` で公開URL構造を追加するのに**フラッシュ戦略が無い**。テーマ側の一括フラッシュは `node_all_articles_v5` 達成済みで再発火しない。/spotlight/ 本番404事故（CHANGELOG 1.0.3）の再演条件 | **F-1（①）** |
| V-3 | 高 | `inc/scheduler.php` `node_post_to_x()` / `node_check_missed_schedules()` / `node_on_post_published()` | テーマ（表示層）が X API書込・`$wpdb`直クエリ+`wp_publish_post`・公開時AI要約トリガという**純状態管理**を所有。API鍵（`node_x_*`）もテーマ設定画面がoption管理 | F-12（③移管） |
| V-4 | 高 | `functions.php` `luminous_core_auto_post_slug()` | テーマが `wp_unique_post_slug` でスラッグ＝**URLデータを書換**（post以外の投稿タイプにも適用）。URL資産の不安定化＝404供給源C4 | 触らない（挙動をテストで凍結。仕様変更はユーザー判断事項） |
| V-5 | 中 | `functions.php` `node_force_default_post_status_on_save()` | テーマが post_status を pending に強制 | 触らない（運用仕様として記録） |
| V-6 | 中 | `inc/search.php` `node_save_post_char_count` / `inc/meta-boxes.php` / `inc/media.php` / `inc/category-meta.php` | テーマが post/term メタの保存処理を所有 | F-15（③移管候補。v1.2では触らない） |
| V-7 | 中 | `functions.php` `node_enforce_branding_update()` | admin_init 毎に `update_option('blogname')` 判定 | 触らない（R-7で「暫定補正」として分離予定） |
| V-8 | 中 | `inc/theme-setup.php` `node_initialize_seed_color()` | 全リクエストで option 書込判定。option `_node_seed_color` の**参照者は他に存在しない＝デッド状態書込** | F-15（③削除候補。NODE-2.0.md Phase 0 と整合） |
| V-9 | 中 | `inc/utilities.php` `node_get_image_seed_color()` | wp_head 描画中に GD 処理 + `update_post_meta`（表示層からの書込） | 触らない（2.0 §2.3「保存時抽出」への移行が正本） |
| V-10 | 中 | `plugins-embedded/node-flow` `Scroller::get_posts_html()` | REST `node-flow/v1/posts` が**クライアント任意の `query` 配列を WP_Query に直マージ**（クエリ引数インジェクション面。meta_query/post_type等を外部指定可能） | **F-4（①）** |
| V-11 | 低 | `inc/utilities.php` `node_get_global_max_chars()` | テーマから `$wpdb` 直クエリ（transientあり） | ③（実害小） |
| V-12 | 低 | `inc/admin-settings.php` | テーマが nexus / library / X連携等**プラグインの設定を代理 register_setting**（層越境） | 触らない（設定UI集約の設計判断として記録） |
| V-13 | 例外 | `inc/ajax.php` 自己更新 / node-flow の `get_template_part` 呼出 | 前者はテーマ自身のライフサイクル管理、後者は「プレゼンテーション委譲」とコメント宣言済み | 承認済み例外として記録 |

補記: `functions.php` へのルーティング/脚注/補正ロジック集中は **REFACTORING_PLAN.md（R-0〜R-11）が正本**。本レポートは重複指定しない。F-5（301モジュール）は R-5 の `inc/routing.php` 集約後に載せるのが最小差分。

### sslverify 台帳（現状確定値）

| 箇所 | 値 | 判定 |
|---|---|---|
| `inc/ajax.php` テーマ更新チェック/`download_url` | 既定(true) | **維持必須（対策の本体）** |
| Gemini API（`inc/gemini-models.php` / node-ai-tools / node-library の `wp_remote_post`） | 既定(true) | **維持必須** |
| `inc/blogcard.php:93` OGP取得 / `luminous-nexus/shortcode-blogcard.php:54` / `node-library:381` | false | 既存（v0.3〜1.0.2由来）。v1.2では触らない・新規追加は禁止（T-8で機械的に凍結） |
| `inc/blogcard.php:546` `node_resolve_redirect` | **false（v1.2新規）** | **後退 → F-2で撤回** |

---

## 2. 404（107表示・多ページ分散）の構造的根本原因

**増幅器（404を「悪化」させている構造）**: `404.php` はコミット `d9c0c2f`（2026-05-22）で削除済み（v0.6.3の404アートギャラリーごと）。現在404は index.php フォールバック（「記事が見つかりませんでした」+トップ戻りのみ、検索・導線なし）。`src/styles/_404.css`（559行）は style.css から import されておらずデッド。さらに範囲外URLを301せず404で返す設計のため、クローラ・利用者が同じ死URLを叩き続ける。

**供給源（寄与度順。コード・git履歴から特定）**:

- **C1: 範囲外アーカイブページネーション（最有力）** — `64b8afe`（2026-05-26）で `node_custom_posts_per_page` 導入。それ以前はWP管理画面既定（通常10件）→ home 12 / archive・search 24 に変更。**アーカイブの最大ページ数が約40%に縮小**し、それ以前に索引・共有された `/category/*/page/N/`・`/page/N/` が一斉に範囲外化。WPコアは「ページ指定付きで結果0件」のアーカイブを404にする（term一致による200救済は `!is_paged()` のときだけ）。「404が多ページに分散」という報告形と最も整合。
- **C2: 独自エンドポイントのフラッシュ未実施窓** — `/spotlight/` は本番404の実績あり（1.0.3でrequest/template_redirectフォールバック+再フラッシュを追加して収束）。`/headlines/`・`/all-articles/` は同種フォールバックを持たず `node_all_articles_v5` 一括フラッシュ機構に依存。404を一度学習した外部リンク・クローラは再訪し続ける。
- **C3: マルチページ記事の再構成** — 脚注タブUI・TOC・ページ別meta description（`（全Nページ中Mページ目）`）が `<!--nextpage-->` 分割を深く前提化しており分割記事が常態。WP 5.5+ は範囲外の `/post-N/M/` を**リダイレクトせず404**にする。分割数を減らす編集をした瞬間、旧ページURLが死ぬ。
- **C4: スラッグ正規化の履歴** — `luminous_core_auto_post_slug` が日本語スラッグを `post-{ID}` へ書換。投稿更新経由の変更はコアの `_wp_old_slug` リダイレクトで救済されるが、**term（カテゴリ）スラッグ変更にはコアの救済が存在しない**。SNS側で切り詰められたエンコードURL等の派生も非救済。
- **C5: （計測期間依存）** v0.6.3〜5/22 の404アートギャラリーは404閲覧を意図的にゲーム化していた（3回クリックで全テーマ鑑賞）。期間が旧いほど分子を底上げ。
- **C6: （v1.2起因・未来）** node_series の rewrite 未フラッシュのままリリースすると、`/series/*` と wp-sitemap.xml（コアsitemapは有効、公開タクソノミーを自動収載）記載のURLが**リリース直後から404** — C2の再演。①ブロッカー。

**恒久対策の骨子**: (a) F-5 範囲外ページネーション→301正規化 (b) F-6 404.php新設（noindex・検索・主要導線） (c) F-1 リライト資産のバージョン規律（「新ルール追加=バージョン文字列更新」をHOW_TO_RELEASE.md手順に固定） (d) F-7 Series移行301 (e) 残余の特定は本番アクセスログ/GAのパス一覧をC1〜C4に突合（コード外作業・要データ提供）。

---

## 3. v1.2 ⇄ v1.3 統合ロードマップ

v1.2 = Series + BlogCard + node/embed + ブランド統一（実装済・未push）。v1.3 = モバイルナビ刷新。**相互依存**:

- **D1**: v1.3ボトムナビの「シリーズ前後移動」は `node_series_get_adjacent()/get_position()` を消費 → v1.2でAPIシグネチャをテスト凍結（既存14テスト+T-5）。
- **D2**: 現行ボトムナビ（`#m3-bottom-nav`）は `expressive-toc.js` の `ensureShell()` が**JSでトリガーを動的注入**している。これがv1.3刷新の最大障害。v1.3先行タスク=**ボトムバー項目のPHPレンダリング化（F-13）**。v1.2ではDOM契約（`#m3-bottom-nav` `#m3-handy-toc-trigger` `#m3-toc-trigger` `.m3-action-stack`）を変更しない。
- **D3**: F-5/F-6（404恒久対策）はv1.3が増やす導線（シリーズハブ等）の前提インフラ → v1.2内で先行。
- **D4**: 検索モーダルはv1.3モバイル動線の中心になるが、**UIとバックエンドが不一致**（並び順 views/comments・読了目安 `m3_reading_time`・メディア `ai` は `inc/search.php` に実装が無い）→ v1.2でUIから退避（F-11）、実装可否はv1.3で判断（F-16）。
- **D5**: タブレット view-switcher（viewport width=390/1280 書換）配下で新ナビが成立することをv1.3受入条件にする（`data-view-mode=pc` 時にボトムナビ非表示でもナビ到達性を維持）。

**実装順（Phase）**:

```
Phase A（①ブロッカー）  F-2 → F-3 → F-4 → F-1
Phase B（②v1.2内）      F-9 → F-5 → F-6 → F-7 → F-8 → F-10 → F-11
→ v1.2リリース（bun x vite build → cybernode検証 → master反映。シリーズ側コミットのforce-push要確認は1.2featurelist.md記載どおり）
Phase C（③v1.3先行）    F-13（ボトムバーPHP化）→ F-12（scheduler移管）→ F-14 → F-16 → F-15 → F-17 → F-18
Phase D                 v1.3モバイルナビ本体
```

依存: F-7はF-1の後（301先の /series/ が解決可能になってから）。F-6はF-5の後（301で減った残余だけが404面に到達）。F-13はPhase D全体の前提。

---

## 4. 既存機能との衝突予測

| 既存機能 | 新機能との衝突面 | 判定・対処 |
|---|---|---|
| SunCalc連動ダークモード3段階（auto/manual/os） | Series色・ブログカードはCSS変数疎結合で衝突なし。ただし `--node-series-color` は**ライト/ダーク単一値**でダーク時コントラスト未保証。v1.3ナビにテーマトグルを置く場合は既存ID `#m3-theme-toggle-handy` を使えば `color-mode.js` のdocument委譲が既に対応 | ②検証項目（T-11目視）+ v1.3で新ID発明禁止 |
| TOCスクロール同期 / FAB | `node_blogcard_hydrate`(20) が `node_add_heading_ids`(15) の後に走るため、カードはplaceholder段階で見出しゼロ→**目次採番に影響しない**（設計上の偶然の安全）。lightbox(20)→hydrate(20) の順序が「require順（hooks.php→blogcard.php）」でのみ保証される**脆い不変条件**：逆転するとカード内画像がlightboxリンクで包まれオーバーレイが壊れる | **F-8で優先度21へ明示** + T-6で凍結 |
| TOC × Seriesシリーズ目次 | `series-toc.js`（開閉）と expressive-toc（フローティング）はDOM独立。MutationObserverの再render負荷は軽微 | 衝突なし |
| 検索UI | v1.2とは干渉なし。v1.3はD4（UI/バックエンド不一致の解消が前提） | F-11→F-16 |
| レスポンシブ / タブレットview-switcher | v1.2要素（シリーズ目次・カード）は幅可変で問題なし。v1.3はD5 | v1.3受入条件化 |
| パンくず | Series記事もカテゴリ基準のまま（現仕様維持）。シリーズをパンくず位置2に出すのはv1.3以降の判断 | ③ |
| 埋め込み間の相互作用 | nexusの `auto_blogcard` フォールバックは `node_auto_blogcard` ダミー登録で封じ済み（既存テストで凍結済・維持必須）。Spotify/Apple Musicは `wp_embed_register_handler` が先取りするためカード化対象外（仕様どおり） | 維持 |
| 既知の潜在欠陥（新規発見） | `header.php:515` の `fallback_cb => 'node_primary_menu_fallback'` が**未定義**（メニュー未割当時にドロワーが空になる。is_callable判定によりFatalにはならない） | **F-10（②）** |

---

## 5. 修正方針（5点セット・優先度・適用順）

### ①今すぐ（v1.2ブロッカー）

**F-1: node_series リライトフラッシュ規律**
- 対象: `plugins-embedded/node-series/node-series.php`
- 意図: リリース直後の `/series/*`・sitemap収載URLの全404（C6）を防ぐ。
- 最小差分方針: プラグイン自身が option（例 `node_series_rewrite_version`）を持ち、init（taxonomy登録より後の優先度）で不一致時のみ `flush_rewrite_rules(false)`+option更新。テーマの `node_all_articles_v5` 機構は**触らない**（代替案=テーマ側バージョン文字列のbumpでも可だが、シリーズURLの所有者はプラグインであるべき）。HOW_TO_RELEASE.md に「rewrite追加時はバージョン文字列更新」を1行追記。
- 想定リスク: フラッシュはDB書込を伴うが一度きり・`false`（.htaccess非書換）で安全。
- 三層・sslverify根拠: URL構造＝プラグインが所有するデータ層のライフサイクル管理であり、Node Utility側に置くのが三層に適合。HTTP通信なし。

**F-2: sslverify後退の撤回**
- 対象: `inc/blogcard.php` `node_resolve_redirect()`
- 意図: v1.2差分で追加された `'sslverify' => false` の除去（絶対原則の回復）。
- 最小差分方針: 配列から1行削除（既定true）。対象はGoogleの短縮URL解決のみで、検証失敗の実運用リスクなし。既存3箇所のfalse（OGPスクレイパー系）は触らない。
- 想定リスク: ほぼゼロ。万一失敗してもマップ埋め込みが「変換できず空文字→カードフォールバック」に落ちるだけ。
- 三層・sslverify根拠: sslverify=true原則の回復そのもの。

**F-3: `save_order_meta_box()` のガード追加**
- 対象: `plugins-embedded/node-series/node-series.php`
- 意図: リビジョン/オートセーブ/権限のガードが無く、リビジョンIDに対する term割当・メタ書込（データ汚染）が起こり得る。
- 最小差分方針: 冒頭に `wp_is_post_revision` / `wp_is_post_autosave` / `current_user_can('edit_post')` の3ガードを追加（`enforce_max_posts_per_series` と同型）。
- 想定リスク: 既存の正常保存経路には無影響（既存14テストがそのまま回帰網になる）。
- 三層根拠: 状態管理の堅牢化はNode Utility内で完結。通信なし。

**F-4: node-flow REST クエリのホワイトリスト化**
- 対象: `plugins-embedded/node-flow/includes/Frontend/Scroller.php`
- 意図: クライアント任意の `query` を WP_Query に直マージしている入力面を閉じる（meta_query/post_type等の外部注入防止）。本番で稼働中のため①。
- 最小差分方針: `get_posts_html()` で許可キーのみ抽出（`cat, tag, s, author, year, monthnum, day, node_series` 等、`get_current_query_vars()` が実際に渡すもの）。`post_status=publish` 強制は維持。
- 想定リスク: 未知のアーカイブ種で無限スクロールの絞り込みが外れる可能性 → T-9で主要アーカイブを固定。
- 三層根拠: プラグイン内完結。通信なし。

### ②v1.2内

**F-9: シリーズ目次クエリの「順序メタ無し記事」包含**（F-1の前に実施可・独立）
- 対象: `node-series.php` `node_series_get_posts()`
- 意図: 現行は `meta_key` 指定によるINNER JOINで、**順序メタ未設定の記事（クイック編集でのシリーズ割当等）が目次・話数計算から静かに脱落**し、「自分が載っていない目次」が出る。
- 最小差分方針: `meta_query` を `relation=OR`（EXISTS / NOT EXISTS）にし、orderby を `メタ値昇順→日付昇順` に維持。メタ無しは末尾へ。
- 想定リスク: 既存の並び（全員メタ有り）には無影響（既存テストで担保）。
- 三層根拠: プラグイン内完結。

**F-5: 範囲外ページネーション301モジュール（404恒久対策・中核）**
- 対象: 新規 `inc/routing.php` 内（REFACTORING_PLAN R-5 の集約先。R-5未実施なら functions.php 末尾に独立関数群で追加し、R-5時に純移動）
- 意図: C1/C3の恒久対策。「一度生きていたページネーションURL」を404にせず正規側へ301。
- 最小差分方針: `template_redirect`（priority 1、spotlightフォールバック(0)の後）で is_404 のときのみ発火する **純関数 `node_resolve_404_redirect_target( $wp_query, $request_uri ): ?string`** + 薄いフックの2段構成（テスト可能性のため）。判定: (a) paged付きアーカイブ/ホーム/all-articles → 同アーカイブの最終有効ページ（1ページ目はページ番号なしURL）へ301 (b) 分割記事の範囲外ページ → 記事1ページ目へ301 (c) それ以外はnull（既存404維持）。`wp_old_slug_redirect`（コア）より後に評価される位置関係を維持。
- 想定リスク: リダイレクトループ／正当な404の誤救済 → T-2で「1ホップで200到達」「未知スラッグは404のまま」を固定。
- 三層根拠: 対象URLはテーマが定義した表示面（per_page・独自エンドポイント）の産物であり、テーマルーティング層に置くのが整合（node-flowへの移管は将来検討として記録）。通信なし。

**F-6: 404.php 新設**（F-5の後）
- 対象: 新規 `404.php` + 小さな `src/styles/_404-lite.css`（style.cssへ@import追加）。デッドの `_404.css`（559行）は削除せず放置（削除はR-3系の掃除タスクで）。
- 意図: 404の増幅器を解消。検索フォーム・ホーム/全記事/ヘッドライン導線・`noindex` を持つ軽量テンプレート。旧アートギャラリーは復活させない。
- 最小差分方針: index.phpの構造（get_header/footer・m3クラス）を踏襲した静的マークアップ。`wp_robots` フィルタで404時 noindex を追加。`template-parts/all-articles.php` の `get_404_template()` includeが自動的にこれを拾う（追加変更不要）。
- 想定リスク: 低。テンプレート追加のみで既存ページに波及なし。
- 三層根拠: 純表示層。通信なし。

**F-7: Series移行301（旧URL→/series/）**
- 対象: `plugins-embedded/node-series/node-series.php`（新規関数群）
- 意図: 連載を旧来表現していたカテゴリ/タグURLから新 `/series/{slug}/` への到達性保証（SEO資産の移送）。
- 最小差分方針: option `node_series_redirect_map`（`{taxonomy}:{old_slug}` → `series_slug` の連想配列。初期値は空、管理は当面コード/WP-CLIで投入）を参照し、`template_redirect` で該当termアーカイブ（paged・feed含む）を `/series/{slug}/` へ301。**純関数 `node_series_resolve_legacy_redirect( $queried_object, $request_uri ): ?string`** + 薄いフック。マップ未登録termは絶対に触らない。移行元の実データ（どのカテゴリ/タグを連載扱いしていたか）は**ユーザー提供が前提**＝コード外入力として明記。
- 想定リスク: 誤マッピングで正規アーカイブが飛ぶ → マップ登録制（デフォルト空）で構造的に抑止。ターゲット未存在時はリダイレクトせず404維持。
- 三層根拠: シリーズというデータ移行の付随処理はNode Utility所有。マップはwp_options（WP標準構造）。通信なし。

**F-8: hydrate優先度の明示化**
- 対象: `inc/blogcard.php` 最終行付近 `add_filter( 'the_content', 'node_blogcard_hydrate', 20 )`
- 意図: lightbox(20)→hydrate(20)が「require順」でのみ保たれる脆い不変条件を優先度で明示（21へ）。
- 最小差分方針: 数値1文字変更+理由コメント1行。
- 想定リスク: 21より後の`the_content`は footnotes(999) のみで、footnotesは `data-fn` 起点のためカードHTMLに反応しない（確認済）。
- 三層根拠: 表示層内の順序固定のみ。

**F-10: primary menu fallback の解消**
- 対象: `header.php:515`
- 意図: 未定義関数参照の解消（メニュー未割当時にドロワーが空になる潜在欠陥）。
- 最小差分方針: `inc/utilities.php` に `node_primary_menu_fallback()`（footer版と同型のホーム/全記事/ヘッドライン3リンク）を追加するか、`fallback_cb => false` に変更のどちらか。前者推奨（ドロワーが空にならない）。
- 想定リスク: 極小。
- 三層根拠: 表示層内。

**F-11: 検索モーダルの未実装項目をUIから退避**
- 対象: `header.php`（詳細検索モーダル）
- 意図: バックエンド（`inc/search.php`）に存在しない `m3_sort=views/comments`・`m3_reading_time`・`m3_media_type=ai` をユーザーに提示しない（誹表示の解消）。実装追加はv1.3判断（F-16）。
- 最小差分方針: 該当inputをコメントアウトまたは `hidden`。CSSレイアウトの崩れがないことのみ確認。
- 想定リスク: 低（見た目のみ）。
- 三層根拠: 表示層内。

### ③v1.3以降

| # | 内容 | 対象 | 要点 |
|---|---|---|---|
| F-13 | **ボトムバーPHPレンダリング化（v1.3先行・必須）** | `template-parts/components/bottom-bar.php` / `expressive-toc.js` | `ensureShell()` のDOM注入をやめ、トリガーは全てPHPで出力。JSはイベント結線のみに縮退。v1.3ナビ刷新の前提（D2） |
| F-12 | scheduler移管 | `inc/scheduler.php` → node-signal または新設Node Utility | X自動投稿・missed schedule・公開時AIトリガを純移動（R-4様式）。テーマ側requireを差し替え。設定画面の所有も同時に移す |
| F-14 | headlinesカテゴリ参照の堅牢化 | `functions.php` / `index.php` / `template-parts/headlines.php` | `get_term_by('name','ニュース')` 3箇所を slug or option 参照に統一（リネームで速報が全記事化する事故防止） |
| F-15 | テーマ内の状態書込縮減 | V-6/V-8/V-11群 | `_node_seed_color` option削除、char_count保存の移管等。HTMLスナップショット差分ゼロを完了条件に |
| F-16 | 検索バックエンドの実装 or 恒久削除 | `inc/search.php` | views/comments並び替え（人気=計測基盤が必要）採否をv1.3ナビ設計と同時に判断 |
| F-17 | robots整備 | `inc/seo.php` | 検索結果・範囲外残余への noindex 付与（`wp_robots`） |
| F-18 | OGPスクレイパー3重複の統合検討 | theme blogcard / nexus / node-library | transient名前空間3系統（`node_ogp_` `luminous_ogp_` `node_lib_ogp_`）。動作中のため**当面触らない**。統合時はsslverify方針（既存false）の見直しと同時に |

### 触らない判断（明示）

1. `functions.php` の構造分割 — REFACTORING_PLAN.mdの実行線が正本（本線と混ぜない）
2. テーマ自己更新機構・`node.zip` のGit追跡 — master参照の生命線（メモリ/計画書どおり）
3. `node_auto_blogcard` ダミー登録 — nexusフォールバック封じの意図的コード（テスト凍結済）
4. oEmbed proxy 404（`inc/cleanup.php`）— node/embedブロックが前提化した意図的仕様
5. `node_normalize_about_page_content` 等のコンテンツ補正・FOUC対策二重化 — R-7/R-11の管轄
6. 既存3箇所の OGP `sslverify=false` — 挙動維持。**新規追加のみ禁止**（T-8）
7. `/feed/x-post` — 公開情報のみのため放置可（F-12移管時に要否再判断）
8. spotlight系フォールバック群 — 本番404の実績対策。撤去しない

---

## 6. 自動テスト仕様一覧

**層の判断基準**: PHPの振る舞い（ルーティング・301・クエリ・フック）= PHPUnit/`WP_UnitTestCase`（既存 wp-phpunit 基盤、`composer test`）。実サーバのステータス到達性 = bunスクリプト（新設 `scripts/route-check.mjs`、`verify:visual` と同じ cybernode.local 前提）。表示・JS相互作用 = 既存 Playwright `verify:visual` の対象パス追加。**新しいJSユニットテスト基盤は導入しない**（AGENTS.mdの最小ツール方針）。

301系のテスト可能性のため、F-5/F-7は「URL解決の純関数 + 薄いtemplate_redirectフック」の2段構成を実装要件とする（純関数はリダイレクト先URL文字列/nullを返すだけ。exitしない）。

### T-1: node_series リライト/フラッシュ（F-1）

- 層: PHPUnit（`$wp_rewrite->set_permalink_structure('/%postname%/')` → init再実行 → flush）
- ケース:
  1. `go_to('/series/{slug}/')` → `is_tax('node_series')` true / `is_404` false / queried term一致
  2. `/series/{slug}/page/2/`（記事25件シード時）→ 200相当・paged=2
  3. 未知スラッグ `/series/no-such/` → is_404 true
  4. フラッシュoptionが現行値のとき `flush_rewrite_rules` が再実行されないこと（`pre_option`フックで検出 or option値アサート）
  5. wp-sitemap: taxonomiesプロバイダに node_series termが含まれる
- 合否: 各クエリフラグ・termIDの完全一致。回帰: `/all-articles/`・`/headlines/`・`/spotlight/` の3ルートが従前どおり解決（既存独自ルールとの共存確認）。

### T-2: 範囲外ページネーション301（F-5）— 404恒久対策の網羅

- 層: PHPUnit（純関数を直接 + `go_to`後のwp_query渡し）。到達性はT-11で実確認。
- 前提シード: 投稿30件（archive 24/頁→2頁）、カテゴリA 30件、分割記事（nextpage×3）、all-articles想定は総数30（24/頁→2頁）。
- 301になるべきケース（期待Location付き）:
  1. `/category/a/page/9/` → `/category/a/page/2/`（最終有効ページ）
  2. `/category/a/page/3/`（ちょうど+1）→ `/category/a/page/2/`
  3. `/page/99/`（home, 12/頁→3頁）→ `/page/3/`
  4. `/all-articles/page/11/`（240件上限=10頁）→ `/all-articles/page/{last}/`
  5. 分割記事 `/post-1/9/`（numpages=3）→ `/post-1/`（1ページ目へ正規化）
  6. `?utm_source=x` 付き → クエリ文字列維持で301
- 404のまま維持すべきケース（誤救済の禁止）:
  7. `/no-such-page/`・`/category/no-such/page/2/`（term自体が無い）
  8. `/series/no-such/`（F-1後）
  9. 範囲内 `/category/a/page/2/`・`/post-1/2/` → **リダイレクト発生ゼロ**（最重要回帰）
- ループ安全:
  10. 全301ケースの Location を再解決して 200（1ホップ・`.`チェーン禁止）
  11. コアの old-slug リダイレクトと同時成立時（旧スラッグ+範囲外頁）に無限ループしない（どちらか一方が先に解決）
- 合否: 純関数の戻り値URL文字列完全一致 / nullケースはnull。ステータスコードは T-11 で 301/404 実測。

### T-3: Series移行301（F-7）— 旧URL群の網羅

- 層: PHPUnit。マップ `{'category:yugioh-old' => 'yugioh', 'post_tag:renai-tag' => 'renai'}` をシード。
- 301すべきケース:
  1. `/category/yugioh-old/` → `/series/yugioh/`
  2. `/category/yugioh-old/page/2/` → `/series/yugioh/`（ページ位置は引き継がない仕様で固定）
  3. `/category/yugioh-old/feed/` → `/series/yugioh/feed/`
  4. `/tag/renai-tag/` → `/series/renai/`
  5. エンコード日本語旧スラッグ（`/category/%e9%81%8a%e6%88%af%e7%8e%8b.../`をマップ登録した場合）→ 対応series
  6. `?utm=`付き → クエリ維持
- 301してはならないケース:
  7. マップ未登録カテゴリ/タグ → 素通り（200）
  8. マップ先の series term が削除済み → リダイレクトせず従来挙動（regression: 404にも301にもしない=旧アーカイブ表示のまま）
  9. 管理画面・REST・feed以外のコンテキスト非干渉（`is_admin`で無効）
- 到達性: 全301のLocationが200で解決（T-11でも実測）。1ホップのみ。
- 合否: Location完全一致・ステータス301・未登録素通り。回帰: `/category/spotlight/`→`/spotlight/` の既存301が優先順位を保って共存。

### T-4: 404テンプレート（F-6）

- 層: PHPUnit（`go_to('/definitely-404/')` → `get_404_template()` が新404.phpを返す）+ Playwright（描画確認）
- 検証: ステータス404 / `wp_robots` に noindex / 検索フォーム・ホームリンクの存在 / `template-parts/all-articles.php` の範囲外分岐からも同テンプレートが include される / パンくずがFatalしない
- 合否: 上記全て。回帰: 通常ページのrobotsにnoindexが混入しない。

### T-5: シリーズ整合性のエッジ（F-3/F-9 + API凍結）

- 層: PHPUnit（既存 `tests/node-series-test.php` へ追補）
- ケース:
  1. **順序メタ無し**: `wp_set_object_terms` 直（クイック編集相当）で追加した記事が `node_series_get_toc_data` に**末尾で含まれ**、その記事側から見て `is_current` が立つ（F-9後）
  2. **下書き混在**: order=2が下書きのとき、公開側TOCは1,3のみ・連番表示は1,2・position/totalが一致
  3. **記事削除**: order=2を削除 → TOCが1,3の2件・totalが2に追随
  4. **リビジョン保存**: `save_order_meta_box` がリビジョンIDに term/メタを書かない（F-3後）
  5. **権限なしユーザー**のPOSTで order が変更されない（F-3後）
  6. **API凍結**: `node_series_get_adjacent/position/toc_data/color` の戻り値スキーマ（キー名・型）をアサート（v1.3が消費する契約）
- 合否: 各アサート一致。回帰: 既存14テスト green 維持。

### T-6: ブログカード×TOC×lightbox×脚注の順序不変条件（F-8）

- 層: PHPUnit（`the_content` フル適用の統合テスト）
- 検証: 見出し3つ+内部URL単独行+脚注+nextpageを含む本文で、
  1. カード追加の前後で**見出しIDが不変**（node_add_heading_ids の採番がカードに影響されない）
  2. カード内 `<img>` が `m3-lightbox-link` に**包まれていない**、通常本文画像は包まれている
  3. 脚注セクションが本文末尾（カードより後）に1つだけ
  4. hydrate後の出力に `node-blogcard-slot`（未復元placeholder）が残存しない
- 合否: 文字列アサート。回帰: 既存blogcardテスト22件 green。

### T-7: oEmbedキャッシュ挙動（現状凍結）

- 層: PHPUnit
- 検証: 内部URLの `WP_Embed::shortcode` を2回実行 → 2回目（`_oembed_*` キャッシュヒット）でも hydrate 後に同一カードHTML / `delete_oembed_caches` 後にOGP変更が反映 / X URLはキャッシュ `{{unknown}}` 経由でも毎回 `maybe_make_link` に到達し widgets.js フラグが立つ
- 合否: 1回目==2回目のHTML一致、キャッシュ削除後の更新反映。

### T-8: sslverify 静的ガード（F-2）

- 層: PHPUnit（ファイル走査の静的テスト）
- 検証: リポジトリPHP（node_modules/vendor/scratch/.tmp除外）内の `'sslverify' => false` 出現箇所が**許容リスト3件**（blogcard:OGP取得 / nexus / node-library）と完全一致すること。`node_resolve_redirect` は `pre_http_request` フィルタで引数捕捉し `sslverify` が true（既定）であること。
- 合否: 出現リスト一致。**このテストが「後退させない」原則の恒久的な機械化**。

### T-9: node-flow RESTホワイトリスト（F-4）

- 層: PHPUnit（REST Request直呼び）
- 検証: `query[meta_key]`・`query[post_type]=page`・`query[post_status]=draft` が**無視**される / `query[cat]`・`query[s]`・`query[tag]` は機能 / post_statusは常にpublish / page=2で hasMore が総数と整合 / 下書きタイトルがHTMLに漏れない
- 合否: 各ケースのHTML/hasMore期待値。回帰: カテゴリアーカイブでの無限スクロール継続（T-11目視）。

### T-10: 検索バックエンド契約（F-11/F-16）

- 層: PHPUnit
- 検証: `node_get_advanced_search_args` の m3_cat/m3_tag/m3_min/m3_max/日付/並び順(word_count/oldest/newest/alpha)の各argsスキーマ / 未実装パラメータ（views等）を渡しても例外・クエリ破壊がない / AJAX `node_get_search_count` が count を返す
- 合否: args配列の一致・JSONレスポンス形。UI退避（F-11）は verify:visual の対象パス（`/?s=`+モーダル開）で確認。

### T-11: ルート到達性スモーク（bun・実サーバ）

- 層: bunスクリプト新設 `scripts/route-check.mjs`（fetchでステータス・Location検査。`bun run verify:routes`）。cybernode.local 前提、リリースゲートで `verify:visual` と併走。
- URL表（期待ステータス）: `/`=200, `/all-articles/`=200, `/all-articles/page/2/`=200(記事数次第でskip可), `/headlines/`=200, `/spotlight/`=200, `/category/spotlight/`=301→`/spotlight/`, `/series/{seed}/`=200, `/?s=node`=200, `/feed/`=200, `/no-such-xyz/`=404, 範囲外`/page/99/`=301→200(1ホップ), `/wp-sitemap.xml`=200
- 合否: 全行一致・301は1ホップで200到達・5xxゼロ。
- 回帰対象: 本表自体が「旧ページネーションURLが404にならないこと」の受入基準。

### 回帰バンドル（全フェーズ共通の完了条件）

1. `composer test` 全green（既存41+追補）
2. `bun x vite build` exit 0
3. `bun run verify:visual` pass（対象パスに `/series/{seed}/`・404ページを追加）
4. REFACTORING_PLAN R-0-b-5 方式のHTMLスナップショット差分ゼロ（F-5/F-6/F-7は「対象URL以外」で差分ゼロ）
5. `curl -s http://cybernode.local/ | grep -i error` 空

---

## 付録: 検証済みエビデンス（要点）

- 404.php削除: `git log --diff-filter=D -- 404.php` → `d9c0c2f`(2026-05-22)。`_404.css` は style.css の36 importに含まれず。
- posts_per_page導入: `64b8afe`(2026-05-26)。それ以前は `node_custom_posts_per_page` 自体が存在しない（WP既定値運用）。
- /spotlight/ 404事故と対策: CHANGELOG 1.0.3・functions.php の request/template_redirect フォールバック・`node_all_articles_v5`。
- sslverify=false 現存4箇所: `inc/blogcard.php:93`（既存・1.0.2から）/ `:546`（**v1.2新規**）/ nexus:54 / node-library:381。Gemini・テーマ更新系は全て既定true。
- `node_primary_menu_fallback`: header.php:515 で参照、全リポジトリに定義なし。
- 検索UI/バックエンド不一致: header.php のモーダルが `m3_sort=views|comments`・`m3_reading_time`・`m3_media_type=ai` を提示、inc/search.php に対応実装なし。
- node_series: `rewrite ['slug'=>'series']`・public・show_in_rest。フラッシュ処理なし。`node_series_get_posts` は meta_key INNER JOIN。
- oEmbedパイプライン: cleanup.php が `wp_filter_oembed_result` 除去・oEmbed RESTルート無効化済み。内部URLはコア `wp_filter_pre_oembed_result` → `oembed_dataparse` 経由でカード化（実装の前提と一致）。
