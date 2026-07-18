# NODE 1.3 — 開発計画書（制作・連携アップデート / Node 1.3 Connect）

> 本ファイルは Node 1.3 の**開発判断の正本**。構想書（2026-07-19 ユーザー提示）を、実際のコードベース構成に接地させた実装計画としてまとめる。
> AGENTS.md / STATUS.md の制約（1タスク1目的・既存記事を壊さない・cybernode.local検証・テスト後ZIP・masterへリリース）を厳守すること。

---

## 0. テーゼ

**「作る、確かめる、届ける、残す。」**

| 概念 | 機能 | 実装先 |
| --- | --- | --- |
| 作る | AIによる記事制作支援（要約・校正・タイトル案・SNS投稿文） | `node-ai-tools` v2.0（AI共通基盤へ改修） |
| 確かめる | ファクトチェック・校正 | `node-ai-tools` v2.0 |
| 届ける | Webhook・Discord通知・X投稿支援 | **新規プラグイン `node-connect`** |
| 残す | 印刷・PDF保存 | テーマ本体（印刷CSS + 印刷ボタン） |

Node 1.2 が読者向け閲覧体験の強化だったのに対し、1.3 は運営者・ライターの「制作 → 確認 → 公開 → 共有 → 保存」フローを支える。Node をブログテーマから「Luminous Core の運営基盤」へ発展させる。

---

## 1. アーキテクチャ方針（テーマ内 / プラグインの役割分担）

### 1.1 配置の決定

| 機能 | 配置 | 理由 |
| --- | --- | --- |
| Webhook基盤・Discord通知・X投稿支援 | 新規 `plugins-embedded/node-connect/` | 投稿イベントと外部送信はテーマ切替後も動くべき機能。既存の embedded プラグイン群（node-series 等）と同じ配布形態（`production_plugins/node-connect.zip`）を踏襲 |
| AI共通基盤（Provider Adapter） | `plugins-embedded/node-ai-tools/` の内部改修 | 既存の `class-gemini-api.php` / meta-box群 / ajax-handlers をそのまま器として使い、内部だけ差し替える。プラグインを増やさない |
| 印刷CSS・印刷ボタン | テーマ本体（`src/styles/_print.css`、`template-parts/`） | 見た目の責務はテーマ。Node 2.0 のレイアウト刷新でもCSSファイル単位で差し替え可能 |

### 1.2 全機能共通の原則（構想書 §2 の実装解釈）

* 設定は既存パターンに従う: サイト全体設定は `get_option`（`node_connect_*` / `node_ai_*` プレフィックス）、ライター個別のAPIキーは既存の user meta 方式（`node_gemini_api_key` の前例）を踏襲
* Webhook URL・APIキーは**フロントHTMLへ一切出力しない**。設定画面は `manage_options` 権限のみ。保存時に `sanitize_url` / trim、表示時は末尾数文字以外をマスク
* 各機能は個別に有効化・無効化できる（機能ごとの enable オプション + 無効時はフック登録自体をスキップ）
* **外部送信の失敗は記事公開を妨げない**: 送信は publish フックから `wp_schedule_single_event()` で非同期化し、本体処理と分離する（既存 `inc/scheduler.php` に cron 運用の前例あり）
* 内部構造は Node 2.0 でそのまま再利用する前提で、表示（テンプレート/CSS）とロジック（イベント・送信・AI呼び出し）を分離する

---

## 2. 第1段階 — Webhook基盤 + Discord通知（`node-connect`）

### 2.1 プラグイン構成

```
plugins-embedded/node-connect/
├── node-connect.php              # ヘッダー・定数・ローダー（node-series.php の形式踏襲）
├── includes/
│   ├── class-event-bus.php       # Nodeイベントの定義とdispatch（do_action 'node_connect_event'）
│   ├── class-webhook-sender.php  # 送信キュー・wp_remote_post・タイムアウト・再送・ログ
│   ├── class-discord-formatter.php # イベント → Discord Embed ペイロード変換
│   └── class-delivery-log.php    # 送信履歴（optionに件数上限付きで保存）
└── admin/
    └── settings-page.php         # 外部連携設定画面・接続テスト
```

### 2.2 イベントとWPフックの対応

| Nodeイベント | 発火元WPフック | 備考 |
| --- | --- | --- |
| 記事の新規公開 | `transition_post_status`（→publish、旧≠publish） | 予約公開もここを通る。旧statusが `future` なら「予約記事の公開」として区別 |
| 公開済み記事の更新 | `post_updated`（publish→publish） | リビジョン・自動保存は除外 |
| 記事の非公開化 | `transition_post_status`（publish→draft/private） | |
| 記事の削除 | `wp_trash_post` / `before_delete_post` | 公開済みだったもののみ |
| AI要約の生成完了 / ファクトチェック完了 / AI処理失敗 | `node_ai_tools` 側から `do_action( 'node_connect_event', ... )` | 第4段階でAI側に発火を追加。node-connect側は最初からイベント名を予約 |
| Nodeアップデート通知 | 既存アップデータ（build.json検知）成功後 | |
| メンテナンス開始・終了 | 1.3初期では対象外（イベント名のみ予約） | |

イベントごとに通知の有無を設定できる（イベントID × Webhook URL のマトリクスではなく、**URL登録ごとに「対象イベントのチェックボックス群」**を持たせる単純な形にする）。

### 2.3 Discord通知仕様

* Incoming Webhook + Embed形式。含める情報: 記事タイトル・URL・投稿者名・公開日時・概要（抜粋）・アイキャッチ（`image`）・カテゴリ・シリーズ名（`node_series` term があれば）・更新/新規の区別・AI要約の有無
* Embedカラーはシリーズのプライマリカラー（term meta）→ なければブランドオレンジ `#FF9900`
* **Webhook URL登録は最大3件**（新着/更新/開発通知などの使い分け想定）。設定画面の複雑化を防ぐ
* アイコンに人物・人のシルエットは使わない（プロジェクト方針）

### 2.4 安全設計（必須実装）

* 接続テスト（設定画面からテストEmbed送信）
* `wp_remote_post` タイムアウト 5秒、失敗時は cron で最大2回再送（計3試行）
* 重複送信防止: `post_id + イベント種別 + URL` のハッシュを transient（10分）に記録し同一通知を抑止
* 送信履歴: 直近50件を option に保存（日時・イベント・宛先ラベル・HTTPステータス・成否）。設定画面に一覧表示
* 通知の一時停止トグル（全体 / URL単位）
* 送信失敗は記事公開処理へ波及させない（§1.2）

### 2.5 完了条件

* PHPUnitでイベント判定（新規/予約/更新/非公開/削除の status 遷移分岐）・重複抑止・イベントフィルタをカバー
* cybernode.local で実Discordサーバー（テスト用チャンネル）への通知を確認し、記事URL・編集URLを添えて報告
* `php -l` / `bun x vite build` 通過、ZIP生成時に `build.json` 再生成

---

## 3. 第2段階 — X投稿支援（`node-connect` 内）

**自動投稿はしない。** X APIの料金・規約変動リスクを避け、1.3では「投稿文を作って人が投稿する」支援に徹する。

* 投稿テンプレート設定（`{title}` `{url}` `{excerpt}` `{hashtags}` プレースホルダ置換）
* 記事編集画面のメタボックス（またはpublish後の管理バー/一覧行アクション）から: 生成文プレビュー → コピー → Web Intent（`https://twitter.com/intent/tweet?text=...`）で投稿画面を開く
* ハッシュタグ候補・投稿文のAI生成は第4段階のAI基盤連携で追加（それまではテンプレート置換のみ）
* 承認方式: 生成 → 人間が確認・編集 → 手動投稿。完全無確認の自動投稿は実装しない（§8）

---

## 4. 第3段階 — AI共通基盤（`node-ai-tools` v2.0）

### 4.1 現状と改修方針

現在の `includes/class-gemini-api.php` は Gemini 専用クラスで、`generate_content` / `generate_summary` / `fact_check` / `generate_reading_time_estimate` を持ち、APIキーは user meta `node_gemini_api_key` から取得している。これを次の3層に分離する。

```
Node AI Core（class-ai-core.php）
  ├─ 機能API: summarize() / fact_check() / proofread() / suggest_titles() / social_post()
  ├─ プロバイダー解決・利用履歴記録・エラー正規化
  ↓
Provider Adapter（interface Node_AI_Provider）
  ├─ class-provider-gemini.php   ← 既存 class-gemini-api.php を薄く包む（ロジック移植）
  ├─ class-provider-qwen.php     ← OpenAI Compatible API（chat/completions）
  └─ class-provider-ollama.php   ← ローカル /api/chat（キー不要・URL可変）
```

* `Node_AI_Provider` インターフェース: `generate( string $prompt, array $options ): string|WP_Error` + `test_connection(): true|WP_Error` + `get_label()` の最小3メソッド
* 既存の meta-box（ai-summary / fact-check / featured-image-ai）と ajax-handlers は Core の機能APIを呼ぶ形へ書き換え、**表示・保存仕様は変えない**（回帰ゼロが第4段階のゲート）

### 4.2 プロバイダー仕様

| | Gemini（標準・推奨） | Qwen | Ollama |
| --- | --- | --- | --- |
| 認証 | APIキー（既存user meta継続 + サイト共通キーのoption追加） | APIキー + エンドポイントURL | 不要 |
| モデル | 既定 Gemini 3.1 Pro Preview 相当。**コードに固定せず option で差し替え可能**（既存 `inc/gemini-models.php` の一覧管理を流用） | 推奨1種のみ表示（内部IDはoption） | Gemma / Qwen の2択。任意モデルIDは「詳細設定」に分離 |
| 接続先 | Google API固定 | OpenAI互換エンドポイント（可変） | 既定 `http://localhost:11434`（可変） |

### 4.3 AI設定画面

プロバイダー選択（Gemini推奨 / Qwen / Ollama / 使用しない）→ 選択したプロバイダーの項目だけ表示。各プロバイダーに接続テストボタン。詳細なモデルIDは常時表示しない。

### 4.4 利用履歴

実行のたびに記録: 機能・プロバイダー・モデル・日時・成否・対象記事ID・（取得できれば）トークン数。optionまたはカスタムテーブルに直近200件上限で保存し、設定画面に月間実行回数と一覧を表示。**料金の正確な計算はしない**（構想書 §4.9）。

### 4.5 完了条件

* 3アダプターの接続テストが実環境で通る（Gemini=実キー、Qwen=互換API、Ollama=ローカル起動して確認。Ollama未導入環境ではモック単体テストまで）
* 既存のAI要約・ファクトチェックUIに回帰がない（PHPUnit + cybernode.local実保存確認）

---

## 5. 第4段階 — 既存AI機能の移行と拡張

1. Intelligence Summary（既存AI要約）を Core 経由に移行。生成結果は自動公開しない・記事更新時も自動上書きしない現行仕様を維持し、「再生成確認」「古い要約の警告」（生成日時 vs 記事更新日時の比較表示）「使用モデル・生成日時の内部記録」を追加
2. ファクトチェックを Core 経由に移行。**確認箇所の抽出支援**という位置づけを出力プロンプトに明文化（真偽の最終判定はしない）
3. 校正・タイトル案・メタディスクリプション案・SNS投稿文生成を機能APIとして追加し、X投稿支援（第2段階）とメタボックスから利用可能にする
4. AI完了/失敗イベントを `node_connect_event` へ発火（第1段階で予約済みのイベントを接続）

---

## 6. 第5段階 — 印刷機能（テーマ本体）

### 6.1 実装

* `src/styles/_print.css` を新設し `@media print` でスタイル定義（vite ビルドに載せる）。既存の追従UI（`_fab.css`）・ナビ・シェア・コメント・広告・アニメーション・装飾背景を `display: none`
* 表示する: ブログ名・タイトル・概要・公開/更新日・投稿者・本文・見出し・画像・キャプション・表・引用・脚注・コード・参考リンク・記事URL・著作権表記。記事URLと著作権は印刷時のみ表示する要素として `single.php` 側に追加
* 1カラム固定・白背景黒文字・ダークモード変数を強制ライト値へ上書き・`break-inside: avoid`（見出し直後・表・コード・図版）・画像 `max-width: 100%`
* 印刷ボタンは記事下部（シェアボタン群の並び）に配置し `window.print()` を呼ぶだけ。追従ボタンは増やさない
* PDFはブラウザの「PDFとして保存」に委ねる。サーバー側PDF生成はしない

### 6.2 完了条件

* Chrome/Safari の印刷プレビューで、長文記事・コード記事・表/脚注入り記事の3パターンを確認（ライト/ダーク両モードから印刷してダーク色が漏れないこと）
* PC・スマートフォン・タブレット幅で印刷プレビュー確認、cybernode.local の実記事URLを添えて報告

---

## 7. 管理画面の構成

設定は3領域に分ける（構想書 §6）。

* **外部連携**（node-connect 設定画面）: Webhook有効化・Discord URL（最大3件）・イベント選択・テスト通知・X投稿テンプレート・送信履歴・一時停止
* **AI**（node-ai-tools 設定画面）: AI有効化・プロバイダー選択・キー/接続先・接続テスト・要約/ファクトチェック設定・利用履歴
* **印刷**（既存の Luminous Settings ページ = `inc/admin-settings.php` に1セクション追加）: 印刷機能の有効化・ボタン表示位置・記事URL/著作権表記の表示

各設定に1行説明を付け、専門用語を避ける。

---

## 8. Node 1.3 で実装しないもの

構想書 §8 のとおり。特に以下は**実装禁止**として明記する。

* Xへの自動投稿（無確認・承認式とも1.3では作らない。Web Intent 方式まで）
* Gemini / Qwen / Ollama 以外のプロバイダー、多数モデルの並列サポート
* AIによる事実の最終判定・記事の完全自動投稿
* 独自PDF生成エンジン、サーバー側での大規模ローカルAI実行
* Slack / Teams など Discord 以外の通知先（イベント基盤は汎用に作るが、フォーマッタは Discord のみ）
* 複雑なワークフロー自動化、AI画像生成、学習用データセット管理

---

## 9. リリース・検証規約（プロジェクト既定ルールの再掲）

* バージョンは **1.3.0**。`style.css` を正としてREADMEバッジ含む4箇所を同期。プラグイン側（node-connect / node-ai-tools）は各自のバージョンを更新
* リリースは必ず **master** に載せる（自己更新が master 参照）。ZIP生成のたびに `build.json` を再生成する
* ビルドフロー: `bun x vite build` → cybernode.local で検証 → ZIP。検証前に該当URLの Super Cache をパージ
* 検証報告には記事URL・編集URLを必ず添える
* 各段階の完了ごとに STATUS.md の Work Log へ記録する
* 段階間の依存: 第1段階（イベント基盤）→ 第2段階と第3段階は並行可 → 第4段階は第1・3段階に依存 → 第5段階は独立（いつでも並行可）

---

## 10. Node 2.0 との役割分担

1.3 = 機能と運営基盤の強化、2.0 = UI/レイアウト/閲覧体験の刷新（正本は NODE-2.0.md）。1.3 で作る Webhook・AI・印刷の**内部処理（イベントバス・Provider Adapter・印刷用マークアップ）は 2.0 でそのまま再利用**できるよう、表示層と分離して実装する。2.0 の2カラム化後も印刷は1カラムへ変換する前提で `_print.css` を本文中心の独立レイアウトとして書く。

---

## 11. 名称

正式名称は **「Node 1.3 Connect（制作・連携アップデート）」** とする。告知の3本柱は「外部サービス連携」「選べるAI連携」「印刷対応」。
