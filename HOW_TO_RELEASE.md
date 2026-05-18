# Node Theme リリース手順

このドキュメントでは、テーマ編集後に本番用のZIPファイル (`node.zip`) を生成し、GitHubにプッシュするまでの手順を説明します。

## 0. 命名・ブランド
- ブログ / サイトのブランド名は **Luminous Core** です。
- WordPress テーマ名は **Node** です。
- 配布ZIPのファイル名は **node.zip**、ZIP内のテーマルートディレクトリは **node/** に統一します。
- `style.css` の `Theme Name` は **Node** のまま維持します。

## 1. アセットのビルド
テーマ内のCSSやJavaScriptを変更した場合は、必ずビルドを実行して最新のアセットを生成します。

```bash
bun run build
```

## 2. 不要なファイルのクリーンアップ（任意）
開発時のキャッシュファイルや不要な`.DS_Store`ファイルなどが混入しないよう整理します。

## 3. 本番用ZIPファイルの生成
プロジェクトルートディレクトリから、必要なファイルのみを含めたZIPファイルを作成します。以下のコマンドで `node.zip` を出力します。

```bash
rm -f node.zip
repo_dir=$(pwd)
tmpdir=$(mktemp -d)
rsync -a \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='*.zip' \
  --exclude='.DS_Store' \
  --exclude='.!*!.DS_Store' \
  --exclude='.cursor/' \
  --exclude='.gemini/' \
  --exclude='scratch/' \
  ./ "$tmpdir/node/"
(cd "$tmpdir" && zip -qr "$repo_dir/node.zip" node)
rm -rf "$tmpdir"
```

## 4. Git へのコミットとプッシュ
変更したソースコードと、生成した本番用ZIPファイルをGitHubにプッシュします。

```bash
# 変更されたファイルをステージング
git add .

# コミットを作成
git commit -m "chore: release Node theme"

# GitHubへプッシュ
git push origin master
```

---
※ この手順は、作業完了時に自動エージェントによって実行されるように設計されています。
