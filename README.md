# Node
### WordPress Theme for Luminous Core

![Version](https://img.shields.io/badge/version-1.1.4-orange?style=for-the-badge)
![License](https://img.shields.io/badge/license-MIT-blue?style=for-the-badge)
![WordPress](https://img.shields.io/badge/WordPress-6.0+-21759b?style=for-the-badge&logo=wordpress)

Material Design 3 (Expressive) の哲学を WordPress テーマに昇華させた、次世代のクリエイティブ・プラットフォーム。  
README は導入と運用ガイドに集中し、リリース履歴は `CHANGELOG.md` に集約します。

## 主な機能
- **Material You 動的カラー:** アイキャッチ画像やカテゴリ設定からテーマカラーを自動生成。
- **インテリジェント詳細検索:** 読了時間、文字数、プラットフォーム、AI生成の有無などで高度な絞り込みが可能。
- **フローティング・ナビゲーション:** 記事ページでの目次アクセス、コメント移動、トップ戻りをスムーズに。
- **プラットフォーム・ブランド連携:** デバイスごとの公式ブランドカラーをUIに反映（Windows, iOS, Android, Nintendo, PlayStation, Xbox）。
- **AI 連携:** Gemini API を活用した記事要約（保存済みデータの高速表示に対応）。
- **PWA 対応:** オフライン閲覧やホーム画面へのインストールをサポート。

## インストール
1. `wp-content/themes/node` に配置。
2. `bun install && bun run build` を実行してアセットを生成。
3. WordPress 管理画面より「Node」を有効化。
4. 必要に応じて `functions.php` または環境変数に Gemini API キーを設定。

---
**Node Teams**
*Evolution through Light and Logic.*

## 更新履歴
更新履歴は [CHANGELOG.md](./CHANGELOG.md) を参照してください。
