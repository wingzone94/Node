# GEMINI.md — Node テーマ / Luminous Core ブランド TOC/FAB バグ修正タスク
# Gemini Code Assist Agent Mode 用コンテキストファイル
# このファイルをプロジェクトルートに配置して Agent Mode で使用する
# IDE: VS Code / IntelliJ | モデル: Gemini 3.1 Pro (Preview) をIDE設定で選択

---

## 命名・ブランドの正本

- ブログ / サイトのブランド名は **Luminous Core**
- WordPress テーマ名は **Node**
- `style.css` の `Theme Name`、テーマディレクトリ名、配布ZIP内のルートディレクトリ名は **Node** に統一すること
- ヘッダー、フッター、OGP、メタ情報、サイト表示、`get_bloginfo( 'name' )` 由来の表示は **Luminous Core** を使用すること
- 「Luminous Core テーマ」「Luminous Core（Node）テーマ」のように、ブログ名とテーマ名を混同しないこと
- 併記が必要な場合は「ブログブランド: Luminous Core / テーマ: Node」と明記すること

---

## あなたの役割

あなたはWordPressテーマ開発・Vite/Bunビルドパイプライン・バニラJavaScript UI設計の
エキスパートエンジニアです。
このプロジェクト（ブログブランド: Luminous Core / テーマ: Node v1.0.0）のTOC/FABバグを修正し、
Bunでビルドして成果物ZIPを生成することがあなたのタスクです。

指示されたファイル以外は変更しないこと。
スコープ外の改善（TypeScript化・ESLint追加等）は行わないこと。

---

## プロジェクト情報

- ブログブランド: Luminous Core
- テーマ名: Node v1.0.0
- ビルドツール: Bun v1.3.13 + Vite v5
- ビルドコマンド: `bun x vite build`
- パッケージマネージャー: bun（npm / yarn / pnpm は使わないこと）
- ソース: `src/main.js`、`src/styles/style.css`
- 出力先: `assets/js/main.js`、`assets/css/style.css`
- `src/` がない場合は `assets/` 側を直接編集して上書き保存する

---

## 修正対象ファイル（2ファイルのみ）

1. `src/main.js`（なければ `assets/js/main.js`）
2. `src/styles/style.css`（なければ `assets/css/style.css`）

---

## 修正内容（Bug 5件）

### Bug 1 — 初期化関数の実行順序が逆【最重要】

**ファイル:** `src/main.js` または `assets/js/main.js`

**問題:**
DOMContentLoaded後の初期化リストで `ce`（FAB表示制御）が
`ae`（TOC初期化）より前に並んでいる。
`ce()` 内でFAB表示の初期判定が即走るが、このとき `ae()` 未実行のため
`#m3-toc-trigger` の display がまだ設定されておらず、常に非表示判定になる。
→「スーパーリロード後にボタンが表示されない」の根本原因。

**検索文字列:**
```
initShareFeatures,ce,ae,
```

**置換後:**
```
initShareFeatures,ae,ce,
```

---

### Bug 2 — `ce()` の初期スクロール判定タイミングが早すぎる

**ファイル:** `src/main.js` または `assets/js/main.js`

**問題:**
`ce()` 末尾でスクロール判定関数 `t()` を即時実行しているが、
`ae()` 未完了のためスクロール量0と評価されて `is-visible` クラスが付かない。
また `t()` がスクロール量のみを判定基準にしており、TOC有無を考慮していない。

**修正A — `t()` 関数の中身を置き換え**

検索文字列:
```
(window.pageYOffset||document.documentElement.scrollTop)>200?o.classList.add("is-visible"):o.classList.remove("is-visible")
```

置換後:
```
{const scrollY=window.pageYOffset||document.documentElement.scrollTop;const tocReady=document.querySelector("#m3-toc-trigger.toc-ready");(scrollY>200||tocReady)?o.classList.add("is-visible"):o.classList.remove("is-visible")}
```

**修正B — 即時実行を150ms遅延に変更**

検索文字列:
```
window.addEventListener("scroll",t,{passive:!0}),t()}
```

置換後:
```
window.addEventListener("scroll",t,{passive:!0}),setTimeout(t,150)}
```

---

### Bug 3 — 見出しがあっても `#m3-toc-trigger` が非表示のまま

**ファイル:** `src/main.js` または `assets/js/main.js`

**問題:**
`ae()` で見出しが見つかった際に `style.display="flex"` をセットするだけで、
CSSの `opacity:0; visibility:hidden` が残ったままになる。
`is-visible` はスクロール200px超えでのみ付くため、ページ最上部では実質表示されない。

**修正A — `toc-ready` クラス付与**

検索文字列:
```
s&&(s.style.display="flex"),t&&(t.style.display="flex")
```

置換後:
```
s&&(s.style.display="flex",s.classList.add("toc-ready")),t&&(t.style.display="flex")
```

**修正B — `is-has-toc` クラスを action-stack に付与**

検索文字列:
```
console.log("TOC: Initialization complete")
```

置換後:
```
document.querySelector(".m3-action-stack")?.classList.add("is-has-toc");console.log("TOC: Initialization complete")
```

---

### Bug 4 — スマホ用トリガーのクリックが二重発火してTOCが即閉じる

**ファイル:** `src/main.js` または `assets/js/main.js`

**問題:**
`#m3-handy-toc-trigger` のクリックが `se()` と `ae()` の両方に登録されている。
1回のクリックでトグル関数L が2回実行され `classList.toggle` が打ち消し合う。

**修正A — `se()` 内: カスタムイベント発火に変更**

検索文字列:
```
const o=document.getElementById("m3-handy-toc-trigger");o&&o.addEventListener("click",()=>{const s=document.getElementById("m3-toc-trigger");s==null||s.click()})
```

置換後:
```
const o=document.getElementById("m3-handy-toc-trigger");o&&o.addEventListener("click",()=>{document.dispatchEvent(new CustomEvent("m3:toc:toggle"))})
```

**修正B — `ae()` 内: 直接バインドをカスタムイベントリスナーに変更**

検索文字列:
```
s&&s.addEventListener("click",L),t&&t.addEventListener("click",L)
```

置換後:
```
s&&s.addEventListener("click",L),document.addEventListener("m3:toc:toggle",L)
```

---

### Bug 5 — PC版でTOCパネルの位置がFABボタンとズレる

**ファイル:** `src/styles/style.css` または `assets/css/style.css`

**修正: ファイル末尾に以下を追記（既存ルールは変更しない）**

```css
/* ===== TOC/FAB Bug Fix ===== */

/* [Fix 1] toc-readyで強制表示 */
#m3-toc-trigger.toc-ready{opacity:1!important;visibility:visible!important;transform:scale(1)!important}

/* [Fix 2] PC版TOCパネル位置補正 */
@media(min-width:1001px){.m3-sticky-toc{bottom:104px!important;right:24px!important;width:320px!important}}

/* [Fix 3] is-active-toc時に他FABを非表示 */
body.is-active-toc .m3-action-stack .m3-fab:not(#m3-toc-trigger){opacity:0!important;visibility:hidden!important;pointer-events:none!important;transform:scale(.8)!important;transition:opacity .2s ease,transform .2s ease!important}

/* ===== End Bug Fix ===== */
```

---

## ビルドと成果物の出力

### Step 1: 依存関係インストール
ターミナルツールで実行:
```bash
bun install
```

### Step 2: プロダクションビルド
```bash
bun x vite build
```

`src/` がない場合はこのステップをスキップし、直接編集したファイルをそのまま使う。

### Step 3: 修正確認（ZIP生成前に必ず実施）

```bash
echo "=== Bug Fix 検証 ==="
grep -c "ae,ce"              assets/js/main.js && echo "✅ Bug1 OK" || echo "❌ Bug1 要再修正"
grep -c "toc-ready"          assets/js/main.js && echo "✅ Bug3 OK" || echo "❌ Bug3 要再修正"
grep -c "m3:toc:toggle"      assets/js/main.js && echo "✅ Bug4 OK" || echo "❌ Bug4 要再修正"
grep -c "is-has-toc"         assets/js/main.js && echo "✅ Bug3b OK" || echo "❌ Bug3b 要再修正"
grep -c "setTimeout.*150"    assets/js/main.js && echo "✅ Bug2 OK" || echo "❌ Bug2 要再修正"
grep -c "toc-ready"          assets/css/style.css && echo "✅ Bug5 OK" || echo "❌ Bug5 要再修正"
echo "=== 検証完了 ==="
```

❌ が1件でもある場合はZIPを生成せず、該当Bugの修正を再適用してから再検証すること。

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

`src/` が存在しない場合は `src/main.js` と `src/styles/style.css` の行を除いて実行すること。

---

## 制約事項（厳守）

- 修正対象は上記2ファイルのみ。他のファイルは変更しないこと
- ビルドには bun を使うこと（npm / yarn / pnpm は不可）
- 既存の関数名・変数名・CSSクラス名は変更しないこと
  （`toc-ready`・`is-has-toc`・`m3:toc:toggle` は新規追加のみ）
- `vite.config.js` が存在しない場合は新規作成しないこと
- TypeScript化・ESLint追加など、スコープ外の変更は行わないこと
- プランを提示した後、ファイル変更の承認を求めること（自動実行しないこと）
