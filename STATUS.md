## Runtime / App Execution Policy

このプロジェクトでは、複数のLLMおよびAIエージェントを併用しますが、それぞれの実行環境を以下のように分けます。

### 基本方針

* Codexは主にIntel Mac上のCodex App / Codex CLIで使用する
* Claude系モデルはClaudeアプリおよびVisual Studio Code上のClaude Codeで使用する
* Gemini系モデルはGoogle Antigravityで使用する
* 各エージェントは同じリポジトリを扱う場合でも、同時に同じファイルを編集しない
* 作業開始前に必ずこの `STATUS.md` を確認する
* 作業終了時または中断時に、必ず作業状況をこのファイルへ記録する

---

## Local Development Environment

### Primary Machine

Intel Mac

### Notes

この環境では、AIの推論処理そのものはクラウド側で行われるため、モデルの知能・推論性能はMac本体のCPU性能に直接依存しません。

ただし、以下を同時に起動するとローカル環境が重くなる可能性があります。

* Codex App
* Visual Studio Code
* Claude Code
* Google Antigravity
* ブラウザ
* WordPressローカル環境
* Node / npm / PHP関連プロセス

そのため、複数エージェントを同時並行で走らせるのではなく、原則として役割ごとに切り替えて使います。

---

## Agent Runtime Mapping

| Agent / LLM   | Runtime                             | Main Role                  | Notes                |
| ------------- | ----------------------------------- | -------------------------- | -------------------- |
| Codex         | Intel Mac Codex App / Codex CLI     | 実装、バグ修正、差分作成、テスト           | 小〜中規模のコード修正を担当       |
| Claude Sonnet | Claude App / Claude Code in VS Code | 実装補助、レビュー、日常的な修正           | Codexの補助またはレビューに使用   |
| Claude Opus   | Claude App / Claude Code in VS Code | 複雑な設計、大規模レビュー、難所対応         | 大きな仕様判断や長文コード理解に使用   |
| Claude Fable  | Claude App / Claude Code in VS Code | 長期タスク、重いコード理解、難解な整理        | 必要時のみ使用              |
| GPT-5.5       | ChatGPT / Codex連携                   | 仕様整理、方針判断、文章化、レビュー         | STATUS.mdや設計文書の整理に使用 |
| Gemini系       | Google Antigravity                  | Gemini API関連検証、Google系仕様確認 | API枠・モデル利用可否に注意      |

---

## Recommended Agent Usage

### Codex

Codexは、主に以下の作業に使います。

* PHP / JavaScript / CSS のバグ修正
* WordPress管理画面のUI分岐修正
* Gemini APIエラー時の表示修正
* 保存ボタンの有効 / 無効制御
* 小〜中規模のリファクタ
* テスト実行
* 差分作成

今回のような、

```text
Gemini APIエラー時に成功メッセージが表示される
```

という問題は、Codexで修正する対象とします。

---

### Claude Sonnet / Opus / Fable

Claude系は、主に以下の作業に使います。

* Codexが作成した差分のレビュー
* 複雑な設計判断
* 大規模リファクタ前の方針整理
* WordPress権限、nonce、保存処理まわりの安全確認
* 長文コードの理解
* 仕様の矛盾チェック

Claude Appは相談・整理向け、Claude Code in VS Codeは実コードレビューや修正向けとして使います。

---

### Gemini / Antigravity

Gemini系は、主に以下の作業に使います。

* Gemini API関連の挙動確認
* Google AI Studio / Gemini APIの仕様確認
* Geminiモデルごとの制限確認
* Google系APIとの連携検証
* Antigravity上でのGemini系エージェント作業

ただし、Gemini 3.1 ProなどのPro系モデルは、利用中のGoogle AI Studio / Gemini APIプロジェクトで無料枠やクォータが0の場合があります。

そのため、Gemini系を使う場合は、作業前に必ず利用可能モデルとレート制限を確認します。

---

## Gemini Runtime Caution

Gemini系モデルでは、モデル名を指定していても、現在のプロジェクトで無料枠またはクォータが0の場合、以下のようなエラーが発生する可能性があります。

```text
You exceeded your current quota, please check your plan and billing details.
```

これは、必ずしも使いすぎを意味するとは限りません。

以下の可能性があります。

* そのモデルの無料枠が0
* Pro系モデルが現在のプランで使えない
* 1日あたりのリクエスト上限に達した
* 1分あたりのリクエスト上限に達した
* 課金設定が必要
* 指定モデルが現在利用不可

### Gemini系の運用ルール

* Gemini 3.1 Proを常用前提にしない
* Pro系モデルは使える時だけ使う
* 通常運用ではFlash Lite系を優先する
* 無料枠0のモデルを標準指定しない
* APIエラー時に成功メッセージを表示しない
* Gemini APIのエラーはNode Dashboard側で明確に分類して表示する

---

## Parallel Work Restriction

複数のAIエージェントを使う場合でも、同時に同じファイルを編集してはいけません。

### 禁止例

* CodexとClaude Codeが同時に同じPHPファイルを編集する
* CodexがJSを修正中にAntigravityが同じJSを変更する
* Claudeがレビュー中の差分をGeminiが別方針で書き換える
* STATUS.mdを複数エージェントが同時に更新する

### 推奨

1. Codexで実装する
2. STATUS.mdに変更内容を書く
3. Claudeでレビューする
4. STATUS.mdにレビュー結果を書く
5. 必要ならCodexで追加修正する
6. 最後に人間が確認する

---

## Recommended Workflow

### 通常のバグ修正

1. `STATUS.md` を読む
2. Codexで対象バグを修正する
3. 変更ファイルを記録する
4. テスト結果を記録する
5. Claudeでレビューする
6. 問題があればCodexで再修正する
7. 人間が最終確認する

### 設計が絡む修正

1. GPT-5.5またはClaude Opusで方針を整理する
2. 方針を `STATUS.md` に書く
3. Codexで実装する
4. Claudeでレビューする
5. Gemini API関連の場合はAntigravity / Geminiでも確認する
6. 人間が最終判断する

### Gemini API関連の修正

1. 現在のGeminiモデルとクォータを確認する
2. `Model Registry` を更新する
3. CodexでNode Dashboard側のエラー処理を修正する
4. Gemini / AntigravityでGoogle API側の仕様を確認する
5. ClaudeまたはGPT-5.5でエラー文言とUXを確認する
6. 人間が最終確認する

---

## Performance Policy on Intel Mac

Intel Macでは、以下の運用を推奨します。

### 推奨

* Codex作業中はAntigravityを閉じる
* Claude Code作業中はCodexの重い処理を止める
* Antigravity作業中はVS Codeの不要な拡張を止める
* ブラウザタブを増やしすぎない
* ローカルサーバー、npm watch、PHPサーバーを必要時のみ起動する

### 非推奨

* Codex App、Claude Code、Antigravityを同時にフル稼働させる
* 複数エージェントに同時編集させる
* レビュー前の差分を別エージェントに上書きさせる
* モデル確認なしにPro系Geminiを常用する

---

## Runtime Decision Log

### Decision: CodexはIntel Mac上で実装担当として使う

#### Reason

Codexはバグ修正、差分作成、テスト実行に向いているため。

#### Status

採用

---

### Decision: Claude系はClaudeアプリおよびVS Codeで使う

#### Reason

Claude Sonnet / Opus / Fableは、設計レビュー、長文コード理解、複雑な方針整理に向いているため。

#### Status

採用

---

### Decision: Gemini系はAntigravityで使う

#### Reason

Gemini APIやGoogle系仕様の検証にはGemini系エージェントが向いているため。

#### Status

採用

---

### Decision: Intel Macでは複数AIエージェントを同時フル稼働させない

#### Reason

ローカル環境の負荷増大、ファイル競合、作業状況の混乱を防ぐため。

#### Status

採用

---

## Work Log: シリーズ（連載）機能 — Claude Code (Sonnet) / 2026-06-26

### 変更ファイル

* `plugins-embedded/node-series/node-series.php`（プラグイン側・データ/ロジック層）
  * シリーズ（`node_series`タクソノミー）編集画面にプライマリカラー設定欄（カラーピッカー）を追加（term meta: `node_series_color`）
  * 記事ごとの色上書き欄を既存の表示順メタボックスに追加（post meta: `_node_series_color_override`）
  * `node_series_get_color($post_id)` を追加：記事上書き色 → シリーズ共通色 → 既定`#FF9900`の優先順でカラーを解決
* `inc/utilities.php` — `node_the_series_banner()` がCSS変数 `--node-series-color` をインラインで出力するように変更
* `template-parts/single/series-nav.php` — シリーズ目次カードのレイアウトを「ハイブリッド型」へ変更（見出し全体のオレンジ塗りつぶしピルを廃止し、ニュートラルな行＋現在地バッジ「X/Y」のみ強調。バッジと現在回ノードの色は `--node-series-color` を使用）
* `src/styles/_series-nav.css` — 上記レイアウト変更に伴うCSS全面更新
* `src/styles/_cards.css` — `.m3-card__series-banner` を `--node-series-color` 対応に変更、未使用だった `.m3-label--series` セレクタを削除

### 検証

* `bun x vite build` 成功（複数回）
* `php -l` で対象PHPファイルの構文エラー無し
* CDP（headless Chrome）スクリーンショットで cybernode.local 上の `yugioh-series-3` 記事を確認：ライトモード／ダークモード／モバイル幅(390px)／折りたたみ時／展開時の全パターンで意図通りの表示を確認済み

### 次にやるべきこと（未着手）

* シリーズタクソノミーのカラーピッカーUIは未テスト（管理画面で実際に色を設定→保存→反映を確認していない）。次回、cybernode.local管理画面で「シリーズ」編集画面を開き、色を設定して記事カード・目次バナーに反映されるか確認すること
* 本セッションの変更は未コミット。コミット前に人間の最終確認が必要

#### Status

進行中（コミット待ち）

---

## Work Log: シリーズ（連載）機能 — 完成 — Claude Code (Sonnet) / 2026-06-28

上記2026-06-26時点からの続き。管理画面UXの大幅改善、term削除時の後片付け、自動テスト基盤の新規導入まで完了し、**機能としては完成**。詳細な機能一覧・引き継ぎ事項は [1.2featurelist.md](1.2featurelist.md) を参照。

### このセッションでの主な変更

* 表示・吹き出しUI周りの試行錯誤（フローティング吹き出し→スナックバー→アンカー付きポップオーバー→**ピル自体が横に伸びる方式**に最終収束。位置計算JS・タイマーは全廃）
* シリーズ登録上限（10件）・1記事のみのシリーズは非表示にする仕様を追加
* 管理画面の「シリーズ内の表示順序」メタボックスを再設計:
  * 登録先シリーズ・表示順を両方プルダウン化し、1つのボックスに統合（標準タクソノミーUI／ブロックエディタのネイティブパネルは非表示化し、二重UIの食い違いを排除）
  * 上限に達したシリーズ、既に使用済み/既刊（公開済み）より前の表示順は、保存前にUI側で選択不可にする方式を採用（クラシックメタボックス保存にはGutenbergへエラーを返す経路が無いと判明したため）
  * `node_series_term_status` Ajaxエンドポイントでシリーズ切り替え時に表示順候補・件数表示をリアルタイム再計算
* シリーズterm削除時、投稿側に残る表示順メタ（`_node_series_order`）を後片付けするフック（`delete_node_series`）を追加。記事別カラー上書きは意図的に保持
* PHPUnit + `wp-phpunit/wp-phpunit`（Composer経由、svnチェックアウト不要）でテスト基盤を新規構築。`composer install && composer test`で実行可能。`tests/node-series-test.php`に14テスト（term削除の後片付け・表示順制約・上限バックストップ・目次の単独記事非表示・カラー優先順位・前後ナビ）。**このテストで実際に1件の不具合を発見・修正**（公開済み記事を無変更で再保存するだけで表示順が消えるバグ。`save_order_meta_box()`がterm再割当て後の状態を基準に「自分の既存値」を判定していたため誤検出していた）

### 検証方法

* CDP（headless Chrome）で実際にcybernode.localへログインし、Gutenberg実保存（`wp.data.dispatch('core/editor').savePost()`）・Ajax連動・上限到達時の選択不可化を確認
* `php -l`・`composer test`（14 tests, 27 assertions, all green）

### 引き継ぎ・残課題

* **未コミット・未push**（コミット分割は完了済み: `90ea093` Gemini側 / `8b50d86` シリーズ側。ローカル履歴を書き換えたため、pushにはforce-pushが必要）
* `bun x vite build` は今回の管理画面変更では再実行していない（CSS/JS側は今回触っていないため影響は薄いが、最終リリース前に通すこと）
* クラシックエディタ（ブロックエディタ未使用時）での表示は未確認
* `composer.json` / `composer.lock` / `phpunit.xml.dist` / `tests/` を新規追加（`vendor/`は`.gitignore`済み、本番ZIPに含まれない）。`brew install composer`でローカルマシンにComposerを導入済み（副作用としてシステムの`php`コマンドがHomebrew版8.5.7に切り替わっている。LocalWP側のPHP実行には影響なし）
* ユーザーの意向: **ブログカード機能の完成を待って、両方まとめて1.2としてpush**する方針

#### Status

完成（pushは1.2featurelist.md記載のブログカード機能完了後）

---

## Work Log: Node 1.3 開発計画策定 — Claude Code (Fable 5) / 2026-07-19

### 概要

Node 1.3（制作・連携アップデート / Node 1.3 Connect）の構想書（ユーザー提示）を、実コードベースに接地した開発計画書 [NODE-1.3.md](NODE-1.3.md) として策定。1.3の開発判断の正本はNODE-1.3.mdとする。

### 主要な設計決定

* Webhook基盤・Discord通知・X投稿支援 → **新規プラグイン `plugins-embedded/node-connect/`**（イベントバス + 送信クラス + Discordフォーマッタ + 送信履歴）
* AI共通基盤 → **`node-ai-tools` v2.0 として内部改修**（既存`class-gemini-api.php`をProvider Adapter層に分離。Gemini標準 / Qwen(OpenAI互換) / Ollama(ローカル)の3系統）
* 印刷 → **テーマ本体**（`src/styles/_print.css` + 記事下部の印刷ボタン。PDFはブラウザ保存に委ねる）
* X自動投稿はしない（Web Intent + テンプレート置換 + AI生成文の人間承認方式まで）
* 実装順序: ①Webhook基盤+Discord → ②X投稿支援 → ③AI共通基盤 → ④既存AI機能の移行 → ⑤印刷（⑤は独立・並行可）

### Status

計画策定完了。次アクションは第1段階（node-connect プラグインの骨格 + Discord通知）の実装。

---

## Work Log: Node 1.3 第1段階 — node-connect（Webhook基盤 + Discord通知） — Claude Code (Fable 5) / 2026-07-19

### 変更ファイル

* **新規** `plugins-embedded/node-connect/`（v1.3.0）
  * `node-connect.php` — ローダー。`transition_post_status` / `post_updated` / `wp_trash_post` / `before_delete_post` を監視（post タイプのみ、リビジョン・自動保存除外、ゴミ箱経由の二重通知防止）
  * `includes/class-event-bus.php` — イベント定義（post_published / post_updated / post_unpublished / post_deleted + AI系・node_updated を予約、maintenance系はID予約のみ）。`do_action('node_connect_event')` 経由で発火・受信。重複抑止（post_id+イベント+URL のハッシュを transient 10分）。送信は `wp_schedule_single_event` で非同期化し公開処理へ波及させない
  * `includes/class-webhook-sender.php` — `wp_remote_post` タイムアウト5秒、失敗時 cron で再送（60秒→300秒、計3試行）。cron引数にURLは載せず設定indexで解決
  * `includes/class-discord-formatter.php` — Embed変換。シリーズカラー（`node_series_get_color`）→ 無ければ `#FF9900`。削除/非公開イベントにはリンクを付けない。予約公開は見出しを区別
  * `includes/class-delivery-log.php` — 送信履歴を option に直近50件（URLは記録せずラベルのみ）
  * `admin/settings-page.php` — 設定 → 外部連携。全体有効化/一時停止、Webhook最大3件（ラベル・URL・イベント選択・個別有効化・削除）、接続テスト（admin-post）、送信履歴一覧。URLは末尾6文字以外マスク表示・input への再出力なし・空欄保存で既存URL維持・https必須
* `inc/ajax.php` — テーマ更新インストール成功時に `node_connect_event`（node_updated）を発火
* **新規** `tests/node-connect-test.php` — 26テスト（遷移分類・予約公開フラグ・更新/非公開/削除/リビジョン除外・無効/停止時の抑止・購読フィルタ・重複抑止・成功/失敗/HTTP 4xx の送信と再送上限・履歴上限50・フォーマッタのカラー/見出し/リンク省略・設定サニタイズ・ペイロード組み立て）

### 検証

* `php -l` 全ファイル通過、`composer test` 全157テスト green（新規26含む）、`bun x vite build` 通過
* cybernode.local 実環境: symlink 配置 + `activate_plugin` で有効化。設定画面レンダリング確認。実際に記事を下書き→公開し、イベントがキューに載り `wp_remote_post` 実行・送信履歴に記録されるまでの一連を確認（ダミーURLのため HTTP 404 失敗ログとして記録＝期待どおり）。テスト記事・キュー・履歴・transient は削除済み、Super Cache パージ済み
* **実Discord受信確認済み（2026-07-19）**: ユーザー許可のもとChromeでDiscordを操作し、Luminous Coreサーバー #更新情報 の既存Webhook「Update Bot」のURLを設定画面（設定 → 外部連携）へ登録。以下をすべて実機確認:
  * 接続テスト → HTTP 204、Discordに「🔧 接続テスト」Embed受信
  * 実記事公開（post 1173）→ wp-cron経由で「📰 新しい記事が公開されました」Embed受信（タイトルリンク・抜粋・投稿者・公開日時・カテゴリ・AI要約有無すべて表示）
  * ゴミ箱移動 →「🗑️ 記事が削除されました」Embed受信（仕様どおりリンクなし）
  * 送信履歴に post_published / test が ok=true HTTP 204 で記録されることを確認
  * テスト記事は完全削除済み・Super Cacheパージ済み

### 引き継ぎ

* 未コミット。人間の最終確認後にコミット→次は第2段階（X投稿支援）または第5段階（印刷、並行可）
* cybernode.local には Webhook 設定（#更新情報宛・5イベント購読）が残してある（実運用でそのまま使える）
* 注意: 機能有効化チェックは `plugins_loaded` 時に行うため、設定変更は次のリクエストから反映される（実運用では問題なし）

#### Status

第1段階完了（実装・自動テスト・実Discord検証すべて完了）

---

## Work Log: 1.2.1 リリース（node-connect ベータ同梱） — Claude Code (Fable 5) / 2026-07-19

### 概要

本番環境（luminous-core.net）での node-connect 検証のため、ユーザー指示により **1.2.1** としてリリース。ベータ版プラグイン node-connect（v1.3.0-beta.1）をテーマZIPに同梱し、インストール用 `production_plugins/node-connect.zip`（トップレベル `node-connect/` 構造・WP管理画面からアップロード可）も追加。

### 変更内容

* バージョン同期4箇所: `style.css` / `package.json` / READMEバッジ / `build.json` → **1.2.1**
* README・CHANGELOG に 1.2.1 エントリ追加
* node-connect のバージョンを `1.3.0-beta.1`（Description にベータ版明記）
* HOW_TO_RELEASE.md の ZIP 除外リストに `NODE-1.3.md` を追加
* `build.json` 再生成（build_id: `20260718T163038Z-bcb3be3`）
* `node.zip` 8.5MB・トップレベル構成正常・ZIP内 style.css / build.json が 1.2.1 を返すことを確認
* ゲート: `php -l` / `composer test`（157 tests green）/ `bun x vite build` 通過。フロントCSS/JSは1.2.0から無変更のため視覚回帰は省略
* 注意: 撤回済み旧 v1.2.1 タグ（24bdb45）がリモートに残っていたため削除し、Actions の自動タグで新リリースコミットに再作成させる

### Status

リリース準備完了 → master へ push
