# Luminous Core

![Version](https://img.shields.io/badge/version-0.6.1-orange?style=for-the-badge)
![License](https://img.shields.io/badge/license-MIT-blue?style=for-the-badge)
![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759b?style=for-the-badge&logo=wordpress)

Material Design 3 (Expressive) の哲学を WordPress テーマに昇華させた、次世代のクリエイティブ・プラットフォーム。

## 主な機能
- **Material You 動的カラー:** アイキャッチ画像やカテゴリ設定からテーマカラーを自動生成。
- **インテリジェント詳細検索:** 読了時間、文字数、プラットフォーム、AI生成の有無などで高度な絞り込みが可能。
- **フローティング・ナビゲーション:** 記事ページでの目次アクセス、コメント移動、トップ戻りをスムーズに。
- **プラットフォーム・ブランド連携:** デバイスごとの公式ブランドカラーをUIに反映（Windows, iOS, Android, Nintendo, PlayStation, Xbox）。
- **AI 連携:** Gemini API を活用した記事要約（保存済みデータの高速表示に対応）。
- **PWA 対応:** オフライン閲覧やホーム画面へのインストールをサポート。

## インストール
1. `wp-content/themes/node` に配置。
2. `npm install && npm run build` を実行してアセットを生成。
3. WordPress 管理画面より「Luminous Core」を有効化。
4. 必要に応じて `functions.php` または環境変数に Gemini API キーを設定。

---
**Luminous Core Teams**
*Evolution through Light and Logic.*


# リリースノート - Node テーマ

## [0.6.1] - 2026.05.01
### 追加・変更点
- **フローティング・アクション・スタック:** 記事ページ (`single.php`) に「目次」「コメントへのスクロール」「トップへ戻る」を統合した追従式ボタン（FAB）を実装。
- **高度な詳細検索 UI:** 
    - 読了時間・文字数スライダーの操作に応じて、モーダル全体のアクセントカラーが動的に変化するビジュアル・フィードバックを導入。
    - 冗長だった下部のページネーションドットを削除し、タブナビゲーションへ集約。
- **プラットフォーム分類の刷新:** 
    - デバイスを「スマートフォン・タブレット」と「PC（Chromebookを含む）」の論理的なカテゴリへ再編。
    - 検索ロジックを修正し、カテゴリ選択時に含まれる全デバイスを対象とした OR 検索に対応。
- **ブランドカラーの厳格な適用:**
    - 各プラットフォームチップに公式ブランドカラーを適用（Windows: 水色, iOS: 灰色, Android: 黄緑, 任天堂: 赤, PlayStation: 青, Xbox: 緑）。
- **ビルドシステムの更新:** Vite による本番ビルドを実行し、アセットを最適化。

## [0.5.0] - 2026.04.26
### 変更点
- **記事詳細ヘッダーの刷新:** `single.php` のヘッダーを「オーバーラップ・カード」形式に再構築。カテゴリーラベルをタイトル下の中央に配置し、情報の対称性と安定感を向上。
- **インタラクティブ・スポンサー吹き出し:** `single.php` において、タイトルをホバーした際のみ現れる「フローティング・バブル（吹き出し）」形式のスポンサー表示を実装。
- **ツールチップ・インテリジェンス:** 画面端や他のFABとの衝突を検知し、自動的に左側へシフトする回避ロジックを実装。

## [0.4.0] - 2026.04.26
### 変更点
- **PWA (Progressive Web App) 完全対応:** `manifest.json` および `sw.js` (Service Worker) を新規実装。
- **Safari 15+ ブラウザバー色の最適化:** `theme-color` メタタグとアドレスバー色の同期。
- **トップページ・ループの構造刷新:** 最新4記事を「⚡ LATEST」、それ以降を「🕒 ARTICLES」としてセクション分け。

## [0.3.2] - 2026.04.25
### 変更点
- **AI 要約アコーディオンの実装:** 記事カード一覧に `<details>` ベースの折りたたみ式 AI 要約を追加。
- **M3 カラーシステムの動的化:** 優先順位に基づいた Material 3 カラー生成ロジックの実装。
- **商品カードの一本化:** Amazon・楽天市場の同時表示に対応した `[product_card]` ショートコードの実装。
