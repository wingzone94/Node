/**
 * Luna Frontier 2.0 / SkyAlow — entry point
 *
 * 方針（NODE-2.0.md / Luna Frontier 指示書 §16）:
 * - JavaScript は Progressive Enhancement 専用。
 * - 本文・固定ページ・ナビゲーション・パンくず・基本検索は JS なしで成立させる。
 * - 親テーマ（Node 1.3）の main.js を置き換えず、2.0 固有の差分だけを足す。
 *
 * CSS は独立した Vite entry（src/styles/luna.css）として出力し、ここからは import
 * しない。JS が失敗してもスタイルは適用される状態を保つため。
 *
 * Phase 1（Scaffold）では意図的に何もしない。
 */

export {};
