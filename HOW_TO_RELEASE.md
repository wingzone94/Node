# Node Theme リリース手順

このドキュメントでは、テーマ編集後に本番用のZIPファイル (`node.zip`) を生成し、GitHubにプッシュするまでの手順を説明します。

## 0. 命名・ブランド
- ブログ / サイトのブランド名は **Luminous Core** です。
- WordPress テーマ名は **Node** です。
- 配布ZIPのファイル名は **node.zip**、ZIP内のテーマルートディレクトリは **Node/** に統一します。
- ConoHa WING 本番環境は Linux のため、既存テーマURL `/wp-content/themes/Node/` と同じ大文字小文字を維持します。
- `style.css` の `Theme Name` は **Node** のまま維持します。

## 1. アセットのビルド
テーマ内のCSSやJavaScriptを変更した場合は、必ずビルドを実行して最新のアセットを生成します。

```bash
bun x vite build
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
  --exclude='.tmp*/' \
  --exclude='.cursor/' \
  --exclude='.gemini/' \
  --exclude='.codex/' \
  --exclude='.agents/' \
  --exclude='scratch/' \
  --exclude='production_plugins/' \
  --exclude='src/' \
  --exclude='AGENTS.md' \
  --exclude='GEMINI.md' \
  --exclude='AI.md' \
  --exclude='TECHNOLOGIES.md' \
  --exclude='skills-lock.json' \
  --exclude='gemini_targets.txt' \
  --exclude='package.json' \
  --exclude='bun.lock' \
  --exclude='vite.config.js' \
  --exclude='HOW_TO_RELEASE.md' \
  --exclude='CHANGELOG.md' \
  --exclude='.gitignore' \
  --exclude='.gitattributes' \
  --exclude='assets/css/main.css' \
  --exclude='assets/css/material3.css' \
  ./ "$tmpdir/Node/"
(cd "$tmpdir" && zip -qr "$repo_dir/node.zip" Node)
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

## 5. バージョンと自動タグ運用（GitHub）
- `style.css` の `Version` をリリース版に更新してからコミットします（例: `1.0.1`）。
- `master` への push 時に GitHub Actions が `style.css` を読み取り、`v<Version>` タグを自動作成します。
- 既に同名タグが存在する場合は自動スキップされます。
- 自動タグの対象は `1.0.0` 以上です（`v1.0.0` から運用）。

---
※ この手順は、作業完了時に自動エージェントによって実行されるように設計されています。
