# AGENTS.md — Node テーマ / Luminous Core ブランド TOC/FAB バグ修正
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
- **変更するファイルは下記2ファイルのみ**。それ以外は一切触らないこと
- スコープ外の改善（TypeScript化・ESLint追加・リファクタ等）は行わないこと
- ビルドには必ず `bun` を使うこと（npm・yarn・pnpm は禁止）
- `vite.config.js` が存在しない場合は新規作成しないこと

## プロジェクト情報

- ブログブランド: Luminous Core
- テーマ: Node v1.0.0
- ビルド: `bun x vite build`（Bun v1.3.13 + Vite v5）
- ソース: `src/main.js` と `src/styles/style.css`
- 出力先: `assets/js/main.js` と `assets/css/style.css`
- `src/` がない場合は `assets/` を直接編集して上書き保存すること

## 修正対象ファイル

1. `src/main.js`（なければ `assets/js/main.js`）
2. `src/styles/style.css`（なければ `assets/css/style.css`）

---

## Bug 1 — 初期化順序が逆【最重要】

`src/main.js`（または `assets/js/main.js`）を編集。

検索:
```
initShareFeatures,ce,ae,
```
置換:
```
initShareFeatures,ae,ce,
```

**理由:** `ce()`（FAB表示）が `ae()`（TOC初期化）より先に走り、
TOCトリガーのdisplayが未設定のまま非表示判定される。

---

## Bug 2 — FAB初期判定タイミングが早すぎる

`src/main.js`（または `assets/js/main.js`）を編集。

**修正A — `t()` 関数の中身を置換**

検索:
```
(window.pageYOffset||document.documentElement.scrollTop)>200?o.classList.add("is-visible"):o.classList.remove("is-visible")
```
置換:
```
{const scrollY=window.pageYOffset||document.documentElement.scrollTop;const tocReady=document.querySelector("#m3-toc-trigger.toc-ready");(scrollY>200||tocReady)?o.classList.add("is-visible"):o.classList.remove("is-visible")}
```

**修正B — 即時実行を150ms遅延に変更**

検索:
```
window.addEventListener("scroll",t,{passive:!0}),t()}
```
置換:
```
window.addEventListener("scroll",t,{passive:!0}),setTimeout(t,150)}
```

---

## Bug 3 — 見出しありでもTOCトリガーが非表示のまま

`src/main.js`（または `assets/js/main.js`）を編集。

**修正A — `toc-ready` クラス付与**

検索:
```
s&&(s.style.display="flex"),t&&(t.style.display="flex")
```
置換:
```
s&&(s.style.display="flex",s.classList.add("toc-ready")),t&&(t.style.display="flex")
```

**修正B — `is-has-toc` クラスを action-stack に付与**

検索:
```
console.log("TOC: Initialization complete")
```
置換:
```
document.querySelector(".m3-action-stack")?.classList.add("is-has-toc");console.log("TOC: Initialization complete")
```

---

## Bug 4 — スマホ用トリガーが二重発火してTOCが即閉じる

`src/main.js`（または `assets/js/main.js`）を編集。

**修正A — `se()` 内をカスタムイベント発火に変更**

検索:
```
const o=document.getElementById("m3-handy-toc-trigger");o&&o.addEventListener("click",()=>{const s=document.getElementById("m3-toc-trigger");s==null||s.click()})
```
置換:
```
const o=document.getElementById("m3-handy-toc-trigger");o&&o.addEventListener("click",()=>{document.dispatchEvent(new CustomEvent("m3:toc:toggle"))})
```

**修正B — `ae()` 内の直接バインドをカスタムイベントリスナーに変更**

検索:
```
s&&s.addEventListener("click",L),t&&t.addEventListener("click",L)
```
置換:
```
s&&s.addEventListener("click",L),document.addEventListener("m3:toc:toggle",L)
```

---

## Bug 5 — PC版TOCパネルの位置がFABとズレる

`src/styles/style.css`（または `assets/css/style.css`）の**末尾に追記**すること
（既存ルールは変更しない）。

```css
/* ===== TOC/FAB Bug Fix ===== */
#m3-toc-trigger.toc-ready{opacity:1!important;visibility:visible!important;transform:scale(1)!important}
@media(min-width:1001px){.m3-sticky-toc{bottom:104px!important;right:24px!important;width:320px!important}}
body.is-active-toc .m3-action-stack .m3-fab:not(#m3-toc-trigger){opacity:0!important;visibility:hidden!important;pointer-events:none!important;transform:scale(.8)!important;transition:opacity .2s ease,transform .2s ease!important}
/* ===== End Bug Fix ===== */
```

---

## ビルド・検証・ZIP出力

### Step 1: 依存関係インストール
```bash
bun install
```

### Step 2: ビルド
```bash
bun x vite build
```
`src/` がない場合はスキップ。

### Step 3: 検証（必須・ZIPの前に実行）
```bash
echo "=== 検証 ==="
grep -c "ae,ce"           assets/js/main.js && echo "✅ Bug1" || echo "❌ Bug1"
grep -c "toc-ready"       assets/js/main.js && echo "✅ Bug3" || echo "❌ Bug3"
grep -c "m3:toc:toggle"   assets/js/main.js && echo "✅ Bug4" || echo "❌ Bug4"
grep -c "is-has-toc"      assets/js/main.js && echo "✅ Bug3b" || echo "❌ Bug3b"
grep -c "setTimeout.*150" assets/js/main.js && echo "✅ Bug2" || echo "❌ Bug2"
grep -c "toc-ready"       assets/css/style.css && echo "✅ Bug5" || echo "❌ Bug5"
echo "=== 完了 ==="
```

❌ が1件でもある場合は該当Bugを再修正してから進むこと。

### Step 4: ZIP生成
```bash
zip -r "node-theme-toc-fix-$(date +%Y%m%d).zip" \
  assets/js/main.js \
  assets/js/vendor.js \
  assets/css/style.css \
  assets/.vite/manifest.json \
  src/main.js \
  src/styles/style.css
```
`src/` がない場合は `src/main.js` と `src/styles/style.css` の行を除いて実行。
