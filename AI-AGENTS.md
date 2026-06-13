# AI-AGENTS.md — 本プロジェクトで利用するAIエージェント一覧

Luminous Core（ブログブランド） / Node（WordPressテーマ）の開発・運用・機能で利用しているAIエージェントおよびAIモデルをまとめる。
運用ルール・モデル使用方針の詳細は [`AGENTS.md`](./AGENTS.md) を参照すること。

---

## 1. 開発・運用に利用するAIエージェント / ツール

| エージェント / ツール | 役割 | 主な用途 |
| --- | --- | --- |
| **Cursor** | AIコーディングIDE | 実装・修正・レビュー・デバッグ |
| **Codex** | コーディングエージェント（CLI/IDE） | 単機能の実装・修正・レビュー |
| **AntiGravity** | AIエージェント（Worktree / Local モード） | 調査・差分提案・限定的な実装 |
| **ChatGPT** | 対話型AI | プロンプト整理・仕様整理・記事/文章の下書き |

---

## 2. 利用するAIモデル

`AGENTS.md` の「モデル使用方針・週次制限対策」に基づき、以下を高コストモデル／通常モデルとして区別して運用する。

### 高コストモデル（設計判断・重要修正・リリース前レビュー等に限定）

| モデル | 提供 | 備考 |
| --- | --- | --- |
| **Claude Opus 4.8**（thinking-high / thinking-high-fast を含む） | Anthropic | 難しい設計判断・破壊的変更前レビュー |
| **GPT-5.5**（Thinking を含む） | OpenAI | 重要修正・複雑なバグ調査 |
| **GPT-5.3 Codex**（High / Thinking 系を含む） | OpenAI | 重要修正・レビュー専用運用 |

### 通常 / 軽量モデル（小規模修正・diff確認・文章作業）

| モデル | 提供 | 備考 |
| --- | --- | --- |
| **Auto / 軽量モデル** | Cursor 等 | typo修正・小規模CSS修正・コミットメッセージ・diff要約 |
| **Gemini 系**（AntiGravity 等で利用） | Google | 調査・補助 |

---

## 3. テーマ機能として組み込まれているAI

`node-ai-tools` プラグインおよび `inc/gemini-models.php` を通じて、Google Gemini API を利用する。
モデル一覧は API から動的取得し、取得失敗時は以下の静的フォールバックを使用する。

| モデルID | 表示名 |
| --- | --- |
| `gemini-2.5-flash` | Gemini 2.5 Flash |
| `gemini-2.5-pro` | Gemini 2.5 Pro |
| `gemini-2.0-flash` | Gemini 2.0 Flash |
| `gemini-2.0-flash-lite` | Gemini 2.0 Flash-Lite |

- API エンドポイント: `https://generativelanguage.googleapis.com/v1beta/models`
- デフォルトは Flash 系を優先

---

## 4. 運用上の原則（要約）

- 高性能モデルは常用せず、設計判断・重要修正・リリース前レビューに限定する。
- 1タスク1目的・読み込みファイルは原則5ファイルまで。
- 検証は `cybernode.local` で行い、未検証を検証済みとしない。
- 詳細は [`AGENTS.md`](./AGENTS.md) を正本とする。

---

*この一覧は開発時点の利用状況に基づく。モデルやツールを追加・変更した場合は本ファイルを更新すること。*
