<?php
/**
 * Luminous Core — Hook & Filter Bridge
 *
 * テーマとプラグイン間の疎結合インターフェースを定義する。
 * 各 apply_filters / do_action はプラグインが無効でも安全にフォールバックする。
 *
 * @package Luminous_Core
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. AI Core Hooks
   ========================================================================== */

/**
 * AI 要約テキストを取得する。
 *
 * luminous-ai-core プラグインが有効な場合はプラグイン側のフィルタが応答する。
 * 無効な場合は post_meta から直接読み込むフォールバックを提供する。
 *
 * @param int $post_id 投稿ID。
 * @return string AI 要約テキスト（空文字列 = 要約なし）。
 */
function luminous_get_ai_summary( int $post_id ): string {
	$summary = apply_filters( 'luminous_get_ai_summary', '', $post_id );

	// フォールバック: プラグインが未登録の場合、直接メタを参照
	if ( empty( $summary ) ) {
		$summary = get_post_meta( $post_id, '_node_ai_summary', true );
	}

	return is_string( $summary ) ? trim( $summary ) : '';
}

/**
 * 読了時間を取得する。
 *
 * @param int $post_id 投稿ID。
 * @return string 読了時間文字列（例: "3分20秒"）。空文字列 = 未計算。
 */
function luminous_get_reading_time( int $post_id ): string {
	$time = apply_filters( 'luminous_get_reading_time', '', $post_id );

	if ( empty( $time ) ) {
		$time = get_post_meta( $post_id, '_node_reading_time', true );
	}

	return is_string( $time ) ? $time : '';
}

/* ==========================================================================
   2. Nexus Hooks
   ========================================================================== */

/**
 * 記事ヘッダーカードの直後にコンテンツを挿入するアクション。
 *
 * luminous-nexus プラグインが有効な場合、ゲーム情報カードなどを出力する。
 *
 * @param int $post_id 投稿ID。
 */
function luminous_after_article_header( int $post_id ): void {
	do_action( 'luminous_after_article_header', $post_id );
}

/**
 * 記事本文の前にコンテンツを挿入するアクション。
 *
 * @param int $post_id 投稿ID。
 */
function luminous_before_content( int $post_id ): void {
	do_action( 'luminous_before_content', $post_id );
}

/**
 * 記事本文の後にコンテンツを挿入するアクション。
 *
 * @param int $post_id 投稿ID。
 */
function luminous_after_content( int $post_id ): void {
	do_action( 'luminous_after_content', $post_id );
}

/* ==========================================================================
   3. Interactivity Hooks
   ========================================================================== */

/**
 * 投稿が年齢確認ゲートを必要とするかを判定する。
 *
 * @param int $post_id 投稿ID。
 * @return bool true = CERO Z 等の年齢制限コンテンツ。
 */
function luminous_requires_age_gate( int $post_id ): bool {
	$requires = apply_filters( 'luminous_content_requires_age_gate', false, $post_id );

	// フォールバック: プラグインが未登録の場合、直接メタを参照
	if ( ! $requires && ! has_filter( 'luminous_content_requires_age_gate' ) ) {
		$requires = get_post_meta( $post_id, '_node_is_cero_z', true ) === '1';
	}

	return (bool) $requires;
}

/**
 * 年齢確認ダイアログを出力するアクション。
 *
 * luminous-interactivity プラグインが有効な場合にダイアログ HTML を出力する。
 *
 * @param int $post_id 投稿ID。
 */
function luminous_render_age_gate( int $post_id ): void {
	do_action( 'luminous_render_age_gate', $post_id );
}

/* ==========================================================================
   4. Badge / UI Extension Hooks
   ========================================================================== */

/**
 * 投稿バッジ配列をフィルタする。
 *
 * プラグインが独自のバッジ（例: 新着, 人気）を追加できる拡張ポイント。
 *
 * @param array $badges 現在のバッジ配列。
 * @param int   $post_id 投稿ID。
 * @return array フィルタ後のバッジ配列。
 */
function luminous_filter_post_badges( array $badges, int $post_id ): array {
	return apply_filters( 'luminous_post_badges', $badges, $post_id );
}

/**
 * 管理画面のメタボックスフィールドを拡張するフィルタ。
 *
 * @param array $fields 現在のフィールド定義配列。
 * @param int   $post_id 投稿ID。
 * @return array フィルタ後のフィールド配列。
 */
function luminous_filter_card_meta_fields( array $fields, int $post_id ): array {
	return apply_filters( 'luminous_card_meta_fields', $fields, $post_id );
}

/* ==========================================================================
   5. Asset Registration Hook
   ========================================================================== */

/**
 * プラグインがテーマのアセット登録タイミングでスクリプト/スタイルを追加するためのアクション。
 *
 * テーマの `wp_enqueue_scripts` 内から呼び出される。
 */
function luminous_enqueue_plugin_scripts(): void {
	do_action( 'luminous_enqueue_scripts' );
}
