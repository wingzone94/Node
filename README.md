# Luminous Core
### Mobile & 404 Not Found Update

![Version](https://img.shields.io/badge/version-0.6.3-orange?style=for-the-badge)
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

## [0.6.3] - 2026.05.02
### 追加・変更点 (Mobile & 404 Not Found Update)
- **404 ページのゲーミフィケーションとアートギャラリー**: 
    - マインクラフト、フォートナイト、Windows BSOD、Material 3 等をテーマにした 6 種類のランダム・エフェクトを実装。
    - 隠し機能：タイトルエリアを 3 回連続クリックすることで、全テーマを鑑賞できる「404 Art Gallery」が発動。
- **Material 3 "Paper" UI**:
    - Material 3 Expressive 404 テーマを、背景画像から「デジタル・ペーパー」をコンセプトにしたレイヤー構造の UI デザインへと刷新。
- **モバイル UX の最適化**:
    - モバイル環境でのフォートナイト版出現頻度を制御（1/10）し、デバイスに馴染むテーマを優先。
    - 全 404 テーマの完全レスポンシブ化。BSOD の文言を「ページリクエストの問題」へ安全化し、誤解を防止。
- **セキュリティと著作権**:
    - アートワークの右クリック・ドラッグ禁止によるコピーガードを実装。
    - 実在の会社名（Mojang AB, Epic Games 等）に基づいた静的な権利表記を画面右下に配置。

## [0.6.2] - 2026.05.02
### 追加・変更点
- **読了プログレスバーの高度化**: 
    - サークル型ゲージをバー型に一本化。スクロールに応じた伸縮と「粉砕アニメーション」を実装。
- **SNSシェアアイコンの刷新**: 
    - Bluesky アイコンを初期デザインに回帰、Misskey アイコンを公式準拠スタイルに更新。
- **Material 3 Expressive ローディング**: 
    - 検索実行時の待機画面に、流動的なモーフィングを用いた M3E 仕様のアニメーションを導入。

## [0.6.1] - 2026.05.01
### 追加・変更点
- **フローティング・アクション・スタック:** 「目次」「コメント移動」「トップ戻り」を統合した FAB を実装。
- **ブランドカラーの厳格な適用:** 各プラットフォームチップに公式ブランドカラーを適用。

## [0.5.0] - 2026.04.26
### 変更点
- **記事詳細ヘッダーの刷新:** 「オーバーラップ・カード」形式に再構築。カテゴリーラベルの配置を最適化。

## [0.4.0] - 2026.04.26
- **PWA (Progressive Web App) 完全対応:** `manifest.json` および `sw.js` を新規実装。

## [0.3.2] - 2026.04.25
- **AI 要約アコーディオンの実装:** `<details>` ベースの折りたたみ式 AI 要約を追加。
