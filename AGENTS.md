# AGENTS.md — Node テーマ / Luminous Core ブランド 軽量化・刷新方針
# Codex デスクトップアプリ（Mac）用コンテキストファイル
# プロジェクトルートに配置して使用する

## 命名・ブランドの正本

- ブログ / サイトのブランド名は **Luminous Core**
- WordPress テーマ名は **Node**
- `style.css` の `Theme Name`、テーマディレクトリ名、配布ZIP内のルートディレクトリ名は **Node** に統一すること
- ヘッダー、フッター、OGP、メタ情報、サイト表示、`get_bloginfo( 'name' )` 由来の表示は **Luminous Core** を使用すること
- 「Luminous Core テーマ」「Luminous Core（Node）テーマ」のように、ブログ名とテーマ名を混同しないこと
- 併記が必要な場合は「ブログブランド: Luminous Core / テーマ: Node」と明記すること
## 作業開始時のルール【最重要】

- **作業に取りかかる前に、必ずこの AGENTS.md を最初から最後まで一読すること**
- 読まずに作業を始めることは禁止。ユーザーから指摘された場合は即座に読み直すこと

---

## 開発ルーチン（テスト → ZIP）

すべてのテーマ変更は、以下の順序で行うこと。**ZIP出力はテスト確認後にのみ実行すること。**

### Step 1: コード変更・ビルド
```bash
bun x vite build
```

### Step 2: ローカルテスト環境（cybernode.local）へデプロイ
```bash
# テーマファイルを同期（ZIPではなく直接コピー）
rsync -a \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='*.zip' \
  --exclude='.DS_Store' \
  --exclude='.cursor/' \
  --exclude='.gemini/' \
  --exclude='scratch/' \
  --delete \
  ./ "/Users/saitoutatsuya/Local Sites/cybernode/app/public/wp-content/themes/node/"

# 【必須】plugins-embedded を削除（プラグインとの二重読み込みを回避）
rm -rf "/Users/saitoutatsuya/Local Sites/cybernode/app/public/wp-content/themes/node/plugins-embedded"

# OPcache 対策: 変更ファイルの mtime を更新
find "/Users/saitoutatsuya/Local Sites/cybernode/app/public/wp-content/themes/node/" \
  -name '*.php' -newer /tmp/.node_last_deploy -exec touch {} + 2>/dev/null || true
touch /tmp/.node_last_deploy
```

### Step 3: cybernode.local で動作確認
- ブラウザまたは Puppeteer で `http://cybernode.local` にアクセスし、変更箇所を目視確認
- エラー（Fatal error 等）がないことを `curl -s http://cybernode.local/ | grep -i 'error'` で確認
- **問題があれば Step 1 に戻って修正。ZIP出力に進まないこと**

### Step 4: テスト確認後に ZIP 出力
テスト環境で問題がないことを確認してから、`HOW_TO_RELEASE.md` に従い ZIP を生成する。
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

### テスト環境に関する注意事項
- **テスト環境パス**: `/Users/saitoutatsuya/Local Sites/cybernode/app/public/wp-content/themes/node/`
- **テスト環境URL**: `http://cybernode.local`
- **plugins-embedded の競合**: テスト環境では `node-signal`・`node-flow`・`node-ai-tools` がシンボリックリンクで `/wp-content/plugins/` にも存在する。テーマ側の `plugins-embedded/` を残すと `Cannot redeclare` Fatal Error が発生するため、**デプロイ後に必ず削除すること**
- **OPcache**: LocalWP の PHP が古い `.php` をキャッシュする場合がある。変更が反映されない場合は `touch` でタイムスタンプを更新するか、LocalWP の PHP を再起動すること

---

## 役割と制約

WordPressテーマ開発・Vite/Bunビルド・バニラJS UIのエキスパートとして動作すること。

このテーマでは、古い限定パッチの遵守よりも、**Luminous Core 向けテーマ Node を軽く、保守しやすく、安全に刷新することを優先する**。

- 未実装機能、コード残骸、古いビルド成果物、不要な配布ZIP、`.DS_Store` などは棚卸しし、不要と判断できるものから整理してよい
- `src/main.js` のモジュール化は推奨する。既存の `src/scripts/` 構成を優先し、機能単位で分割すること
- CSS は既存の `src/styles/*` 分割方針に従い、必要に応じて整理・統合・削除してよい
- PHP テンプレート、`inc/`、`template-parts/`、Vite設定、リリース関連ファイルも、軽量化・未使用整理・依存関係整理に必要であれば変更してよい
- ただし、無関係な全面リライト、TypeScript化、フレームワーク導入、大規模な設計変更は、明確な必要性がある場合のみ行うこと
- ビルドには必ず `bun` を使うこと（npm・yarn・pnpm は禁止）
- `vite.config.js` が存在しない場合は新規作成しないこと
- 動作削除を伴う場合は、削除理由と確認結果を簡潔に残すこと

## プロジェクト情報

- ブログブランド: Luminous Core
- テーマ: Node v1.0.0
- ビルド: `bun x vite build`（Bun v1.3.13 + Vite v5）
- 主要ソース: `src/main.js`、`src/scripts/*`、`src/styles/style.css`、`src/styles/*`
- 出力先: `assets/js/*`、`assets/css/*`、`assets/.vite/manifest.json`
- `src/` がない場合は `assets/` を直接編集して上書き保存すること

## 軽量化・刷新の基本方針

### 1. まず棚卸し

- `src/main.js` 内の機能を、ページ共通、単一記事、ホーム/アーカイブ、検索、TOC/FAB、管理・補助機能に分類する
- PHP 側の出力と JS/CSS 側のセレクタを照合し、実際に使われていない UI とコードを特定する
- `assets/js/main.*.js` などの古いハッシュ付き成果物、古いZIP、作業用ディレクトリ、`.DS_Store` は配布対象から除外・削除候補にする

### 2. `src/main.js` のモジュール化

- 既存の `src/scripts/` を活用し、機能単位で切り出す
- 推奨分割例:
  - `search-bar`
  - `smart-header`
  - `expressive-toc`
  - `article-navigation`
  - `home-layout`
  - `scroll-animations`
  - `comments`
  - `header-clock`
- `src/main.js` は import と初期化順序の管理に寄せる
- 既存挙動を残す必要がある場合も、重複する旧ロジックと新ロジックが同時に走らないようにする

### 3. 条件付き初期化・条件付きロード

- 単一記事だけで必要な処理は `body.single` / `body.single-post` などで限定する
- ホーム・アーカイブ専用処理は該当ページでのみ動かす
- 検索モーダル、TOC、読了進捗、記事ページ用UIなどは DOM の存在確認だけでなく、ページ種別も見て初期化する
- 可能であれば dynamic import を使い、初回ロードで不要な機能を読み込まない

### 4. CSS整理

- `src/styles/style.css` の import 構成を正本とする
- 未使用セレクタは PHP/JS 出力と照合してから削除する
- 追加パッチを末尾に積み続けず、責務に合う partial へ移す
- `!important` は既存仕様維持に必要な場合に限定し、刷新時には段階的に減らす

### 5. 残骸整理

- 古い配布ZIP、作業用ディレクトリ、不要なハッシュ付き成果物、`.DS_Store`、一時スクリーンショットは削除候補にする
- 削除前に、`functions.php`、`inc/`、`template-parts/`、Vite manifest、ライブHTMLとの参照関係を確認する
- 配布ZIPに含めるべきものと、リポジトリ作業用に留めるものを分ける

## 検証方針

軽量化・モジュール化後は、最低限以下を確認する。

1. `bun x vite build`
2. `assets/.vite/manifest.json`、`assets/js/*`、`assets/css/*` が更新内容と整合していること
3. cybernode.local へ同期して、トップページ・単一記事・検索・TOC/FAB・ホーム/アーカイブ表示を確認
4. `curl -s http://cybernode.local/ | grep -i 'error'` で Fatal error 等が出ていないこと
5. ZIP を作る場合は、Local 検証後に `HOW_TO_RELEASE.md` に従って生成すること

## 旧 TOC/FAB パッチについて

このファイルに以前記載されていた TOC/FAB の文字列置換手順は、過去の緊急パッチであり、今後の刷新作業の制約にはしない。

同種の不具合を扱う場合は、圧縮後の `assets/js/main.js` を直接文字列置換するのではなく、原則として `src/main.js` または切り出し後の `src/scripts/*` を修正し、`bun x vite build` で成果物を更新する。
