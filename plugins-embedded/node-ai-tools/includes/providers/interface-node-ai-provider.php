<?php
/**
 * AIプロバイダーの共通インターフェース（NODE-1.3.md §4）
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Node_AI_Provider {

	/**
	 * テキスト生成
	 *
	 * @param string               $prompt  ユーザープロンプト。
	 * @param array<string, mixed> $options 生成オプション:
	 *   - system_instruction (string) システム指示
	 *   - max_tokens (int)
	 *   - temperature (float)
	 *   - json (bool) JSON構造での応答を要求する
	 *   - timeout (int) 秒
	 *   - google_search_grounding (bool) Gemini のみ有効
	 *   - return_metadata (bool) 対応プロバイダーは ['text' => ..., 'grounding' => ...] を返す
	 * @return string|array<string, mixed>|WP_Error
	 */
	public function generate( string $prompt, array $options = array() );

	/**
	 * 接続テスト（コンテンツ生成を伴わない軽い疎通確認）
	 *
	 * @return true|WP_Error
	 */
	public function test_connection();

	/**
	 * 表示名（設定画面・利用履歴用）
	 */
	public function get_label(): string;

	/**
	 * 使用モデルID（利用履歴の記録用）
	 */
	public function get_model(): string;
}
