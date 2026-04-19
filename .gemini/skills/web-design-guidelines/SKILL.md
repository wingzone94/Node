---
name: web-design-guidelines
description: 'Review UI code for Web Interface Guidelines compliance. Use when asked to "review my UI", "check accessibility", "audit design", "review UX", or "check my site against best practices".'
---

# Web Interface Guidelines Review Skill

このスキルは、UIコード（HTML, CSS, JS, React等）を読み込み、最新の Web Interface Guidelines に照らしてアクセシビリティ、UX、デザインの一貫性をレビューします。

## Instructions

このスキルが有効化された場合、以下の手順でタスクを実行してください。

1. **最新ガイドラインの取得**:
   - `web_fetch` を使用して、以下のリソースURLから最新の `command.md` を取得してください。
   - リソースURL: `https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md`

2. **対象ファイルの特定**:
   - ユーザーから特定のファイルが指定されていない場合は、プロジェクト内の主要なUI関連ファイル（`src/components`, `styles`, `templates` 等）を特定するか、ユーザーに確認してください。

3. **コンプライアンス分析**:
   - 取得したガイドラインの各ルール（Contrast, Interactive States, Spacing, Typography等）を基準に、対象コードを詳細に分析してください。
   - 特に、アクセシビリティ（ARIA属性、キーボード操作性）とMaterial Design 3（Expressive）との整合性に注意を払ってください。

4. **フィードバックの報告**:
   - 発見された問題点は、以下の簡潔なフォーマットで出力してください：
     `file:line: [Category] 修正の提案内容`
   - 全体的なUX向上のためのアドバイスがあれば、最後にまとめて記載してください。

## Guidelines and Rules

- **常に最新の状態を維持**: レビューを開始する前に、必ず `web_fetch` でフレッシュなガイドラインを取得してください。
- **具体的かつ建設的**: 単に「違反」と指摘するのではなく、ガイドラインに基づいた具体的な修正コードの例を提示してください。
- **プロジェクトの文脈を尊重**: `GEMINI.md` に記載されているプロジェクト固有のデザインルール（Material 3 Expressive等）がある場合は、それを優先しつつガイドラインとのバランスを評価してください。

## Available Resources

- **Core Guidelines**: `https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md`
- **Reference URL**: `https://interface-guidelines.vercel.app/`
