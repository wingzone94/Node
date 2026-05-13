# Node Theme リリース手順

このドキュメントでは、テーマ編集後に本番用のZIPファイル (`node-theme-production.zip`) を生成し、GitHubにプッシュするまでの手順を説明します。

## 1. アセットのビルド
テーマ内のCSSやJavaScriptを変更した場合は、必ずビルドを実行して最新のアセットを生成します。

```bash
npm run build
# または
bun run build
```

## 2. 不要なファイルのクリーンアップ（任意）
開発時のキャッシュファイルや不要な`.DS_Store`ファイルなどが混入しないよう整理します。

## 3. 本番用ZIPファイルの生成
プロジェクトルートディレクトリから、必要なファイルのみを含めたZIPファイルを作成します。以下のコマンドで `node-theme-production.zip` を出力します。

```bash
zip -r node-theme-production.zip . -x "node_modules/*" -x ".git/*" -x "src/*" -x ".gemini/*" -x "scratch/*" -x "*.zip"
```

## 4. Git へのコミットとプッシュ
変更したソースコードと、生成した本番用ZIPファイルをGitHubにプッシュします。

```bash
# 変更されたファイルをステージング
git add .

# コミットを作成
git commit -m "chore: release version 0.8.10 and update production zip"

# GitHubへプッシュ
git push origin main
```

---
※ この手順は、作業完了時に自動エージェントによって実行されるように設計されています。
