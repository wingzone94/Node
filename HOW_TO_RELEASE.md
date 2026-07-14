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

### 古いハッシュ付きバンドルの掃除
`vite.config.js` の `clean-hashed-bundles` プラグインが、ビルド開始時に `assets/js/` 内のハッシュ付きバンドル（`main.<hash>.js` など2ドット形式のファイル）を自動削除します。手動配置の `blocks.js` や `assets/images/` 等は対象外です。

- `assets/js/` に実体として使われるのは `assets/.vite/manifest.json` が参照する最新ハッシュのファイルのみです。古いハッシュのファイルが残っていると `node.zip` が肥大化します。
- 何らかの理由でプラグインを経由せず古いバンドルが残った場合は、ZIP生成前に `git status assets/js` と `manifest.json` を突き合わせ、参照されていないハッシュ付き `.js` を削除してください（git追跡済みの古いバンドルは削除をコミットに含めます）。
- `assets/` には `images/`・`fonts/`・`pwa/`・`js/blocks.js` など手動配置物があるため、`build.emptyOutDir: true` は**使用禁止**です（`outDir` が `assets` 直下のため全消しされます）。

## 2. ローカル表示検査
`cybernode.local` にテーマを同期したあと、Playwrightで主要画面のスクリーンショット取得と文字潰れ・はみ出し候補の自動検査を行います。

```bash
bun run verify:visual
```

必要に応じて対象パスをカンマ区切りで指定します。

```bash
NODE_VISUAL_PATHS="/,/sample-post/,/category/news/,/?s=node" bun run verify:visual
```

スクリーンショットは `scratch/visual-check/` 以下に保存されます。検査が失敗した場合、HTTPエラー、ブラウザエラー、文字の横はみ出し、縦方向のクリップ、極端に詰まった行間の候補を確認し、問題が残る場合はZIP生成に進みません。

### 2-b. Node Library 回帰スイート（Node Libraryコード変更時は必須）

Node Library（`plugins-embedded/node-library/`）のコードを変更したリリースでは、ZIP生成前に回帰スイートを必ず実行します（NODE_LIBRARY_REGRESSION_PLAN.md 準拠）。

```bash
# 1. フィクスチャ記事の決定的再作成（LocalWPのphpバイナリで実行）
"/Users/saitoutatsuya/Library/Application Support/Local/lightning-services/php-8.2.30+1/bin/darwin/bin/php" \
  -d mysqli.default_socket="/Users/saitoutatsuya/Library/Application Support/Local/run/Q39UjXsTt/mysql/mysqld.sock" \
  scripts/library-fixtures.php

# 2. Playwright回帰チェック（タブ・ボタン・注記・Steamトグル・機種警告・モバイル幅）
bun scripts/library-regression.mjs
```

全項目passし、代表スクリーンショット（`scratch/library-regression/`）を確認できるまでZIP生成に進みません。フィクスチャは `node-library-regression-*` スラッグのみ操作するため、実運用データには影響しません。

## 3. 不要なファイルのクリーンアップ（任意）
開発時のキャッシュファイルや不要な`.DS_Store`ファイルなどが混入しないよう整理します。

## 4. 本番用ZIPファイルの生成
プロジェクトルートディレクトリから、必要なファイルのみを含めたZIPファイルを作成します。以下のコマンドで `node.zip` を出力します。

配布ZIPに開発用の成果物（`vendor/`・`tests/`・`.claude/` 等）を混入させないこと。1.2 のリリース準備時、除外リストがこれらの追加に追いついておらず、ZIPが 8.3MB → 46MB に膨張していました。生成後は必ず**サイズ**（目安 10MB 未満）と `zipinfo -1 node.zip | awk -F/ 'NF>2{print $2}' | sort -u` の**トップレベル構成**を確認してください。

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
  --exclude='.claude/' \
  --exclude='.agents/' \
  --exclude='scratch/' \
  --exclude='production_plugins/' \
  --exclude='src/' \
  --exclude='vendor/' \
  --exclude='tests/' \
  --exclude='test-results/' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpunit.xml.dist' \
  --exclude='.phpunit.result.cache' \
  --exclude='STATUS.md' \
  --exclude='NODE-2.0.md' \
  --exclude='STRUCTURAL-REVIEW-1.2.md' \
  --exclude='REFACTORING_PLAN.md' \
  --exclude='NODE_LIBRARY_REGRESSION_PLAN.md' \
  --exclude='1.2*.md' \
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

## 5. Git へのコミットとプッシュ
変更したソースコードと、生成した本番用ZIPファイルをGitHubにプッシュします。

```bash
# 変更されたファイルをステージング
git add .

# コミットを作成
git commit -m "chore: release Node theme"

# GitHubへプッシュ
git push origin master
```

## 6. Luminous Settings 更新確認との整合
テーマ管理画面の Luminous Settings は、GitHub Release やPRではなく、テーマ内の `inc/ajax.php` で指定している以下の `master` 固定URLを参照します。

- バージョン確認: `https://raw.githubusercontent.com/wingzone94/Node/master/style.css`
- ZIP取得: `https://github.com/wingzone94/Node/raw/master/node.zip`

そのため、PRブランチや `v1.1.x` タグを作成しただけでは、Luminous Settings には最新バージョンとして表示されません。リリース時は必ず `master` 上の `style.css` と `node.zip` を更新してください。

確認コマンド:

```bash
curl -L -s https://raw.githubusercontent.com/wingzone94/Node/master/style.css | sed -n '1,12p'
curl -L -s -o /tmp/node-remote.zip https://github.com/wingzone94/Node/raw/master/node.zip
unzip -p /tmp/node-remote.zip Node/style.css | sed -n '1,12p'
zipinfo -1 /tmp/node-remote.zip | head
```

確認ポイント:

- raw の `style.css` がリリース版の `Version` を返すこと。
- `node.zip` がHTTP 200で取得できること。
- ZIP内のルートディレクトリが `Node/` であること。
- ZIP内の `Node/style.css` も同じ `Version` を返すこと。
- `Author` は `Luminous Core Teams` のまま維持すること。

注意:

- GitHub の raw / raw ZIP URL はCDNキャッシュにより、push直後に古い `style.css` や `node.zip` を返す場合があります。
- `git clone` や GitHub Contents API で新しいコミットが確認できても、Luminous Settings が使う raw URL は数分遅れることがあります。
- raw URLが古い場合は、`cache-control` / `x-cache` / `source-age` ヘッダーを確認し、TTL切れ後に再確認してください。
- raw URLで新しい `Version` と新しいZIPを確認できるまで、「Luminous Settingsから更新可能」と報告しないでください。

## 7. バージョンと自動タグ運用（GitHub）
- `style.css` の `Version` をリリース版に更新してからコミットします（例: `1.0.1`）。
- `master` への push 時に GitHub Actions が `style.css` を読み取り、`v<Version>` タグを自動作成します。
- 既に同名タグが存在する場合は自動スキップされます。
- 自動タグの対象は `1.0.0` 以上です（`v1.0.0` から運用）。

---
※ この手順は、作業完了時に自動エージェントによって実行されるように設計されています。
