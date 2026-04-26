<?php
// Interactivity: スポイラー機能 — スタブ
if ( ! defined( 'ABSPATH' ) ) exit;
function luminous_spoiler_shortcode( $atts, $content = '' ) {
	return '<span class="node-spoiler">' . esc_html( $content ) . '</span>';
}
