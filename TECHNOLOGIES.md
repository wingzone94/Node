# Technical Stack: Luminous Core v0.3

Luminous Core は、最高峰のパフォーマンスと視覚体験を両立するため、以下の技術スタックで構成されています。

## 🎨 UI / Design System
- **Material Design 3 (Expressive):** デザイントークンに基づいた一貫性のある色体系とスペーシング。
- **Fluid Design System:** `clamp()` 関数を駆使し、320px から 8K までピクセルパーフェクトにスケール。
- **Modern CSS:** CSS Variables, Grid Layout, Container Queries, Backdrop-filters.

## 🏎️ Frontend Performance & Animation
- **GSAP 3 (GreenSock):** ハイエンド・アニメーション・エンジン。`force3D: true` による GPU 加速。
- **Canvas API:** 投票ブロック等のパーティクル演出に使用する軽量な描画ロジック。
- **Vanilla JS:** フレームワーク（React/Vue）に依存しない、極限まで軽量化された実行基盤。
- **requestAnimationFrame:** 高リフレッシュレート（120Hz/240Hz）環境下での描画最適化。

## 🧠 Backend & Intelligence
- **PHP 8.1+:** WordPress の最新環境に最適化されたバックエンド。
- **Gemini API (Google AI):** 記事の自動要約（Intelligence Summary）およびメタデータ生成。
- **REST API & oEmbed:** 外部メディア（Apple Music, Steam 等）のシームレスな統合。

## 🛠️ Development & Build
- **Bun / Vite:** フロントエンド・アセットの超高速ビルドおよびパッケージ管理。
- **WordPress Hooks:** `register_block_type`, `embed_oembed_html` 等による標準機能の拡張。

---
*Evolution through Light and Logic.*
