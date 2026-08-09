<?php

declare( strict_types=1 );
/**
 * Luna Frontier 2.0 / SkyAlow — ヘッダー
 *
 * 親テーマ Node 1.3 の header.php を「コピーせずに」拡張する。
 * header.php には <head>・検索ダイアログ・テーマ初期化スクリプトが同居しており、
 * 丸ごと子へ複製すると親の更新から取り残される（§77: Node Theme 全体コピー禁止）。
 *
 * そこで親をそのまま実行し、出力に対して 2 点だけ差分を当てる。
 *   1. ヘッダーの Discord を取り除く（公式 Discord への導線はフッターに残す）
 *   2. ヘッダーの真下にトピックナビを追加する
 *
 * @package LunaFrontier
 */

ob_start();
require get_template_directory() . '/header.php';
$lf_header = (string) ob_get_clean();

/*
 * Discord のアイコンボタンだけを外す。
 * 置換に失敗しても壊れないよう、CSS 側（_topic-nav.css）でも
 * .m3-discord-button を非表示にしてある。どちらか一方でも結果は同じになる。
 */
$lf_stripped = preg_replace(
	'#\s*(?:<!--\s*Discord\s*-->\s*)?<a\b[^>]*m3-discord-button[^>]*>.*?</a>#s',
	'',
	$lf_header,
	1
);

echo null !== $lf_stripped ? $lf_stripped : $lf_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 親テンプレートの出力をそのまま通す。

get_template_part( 'template-parts/luna/topic-nav' );
