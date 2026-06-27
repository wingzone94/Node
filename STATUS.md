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
