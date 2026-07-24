<?php
/**
 * ファクトチェックの自動実行（下書き保存時）と公開ゲート
 *
 * 仕様（2026-07-19 ユーザー指示）:
 * - APIキー登録済みライターの記事は、下書き保存時にファクトチェックを自動実行（cronで非同期）
 * - ファクトチェック未実行の記事は公開できない（キー登録者のみ。未登録者はゲート対象外）
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_ai_author_has_api_key' ) ) {
	/**
	 * 指定ユーザーが Gemini API キーを持つか（開発用定数フォールバック含む）
	 *
	 * @param int $user_id ユーザーID。
	 */
	function node_ai_author_has_api_key( int $user_id ): bool {
		$key = '';
		if ( function_exists( 'node_get_user_gemini_api_key' ) && $user_id > 0 ) {
			$key = node_get_user_gemini_api_key( $user_id );
		} elseif ( $user_id > 0 ) {
			$key = trim( (string) get_user_meta( $user_id, 'node_gemini_api_key', true ) );
		}

		if ( '' === $key ) {
			$key = trim( (string) get_option( 'node_ai_gemini_api_key', '' ) );
		}

		if ( '' === $key && defined( 'GEMINI_API_KEY' ) && GEMINI_API_KEY ) {
			$key = (string) GEMINI_API_KEY;
		}

		return '' !== $key;
	}
}

if ( ! function_exists( 'node_ai_fact_check_content_hash' ) ) {
	/**
	 * ファクトチェック対象コンテンツのハッシュ（再チェック要否の判定用）
	 *
	 * @param WP_Post $post 投稿。
	 */
	function node_ai_fact_check_content_hash( WP_Post $post ): string {
		return md5( $post->post_title . "\n" . strip_shortcodes( strip_tags( $post->post_content ) ) );
	}
}

if ( ! function_exists( 'node_ai_store_fact_check_result' ) ) {
	/**
	 * fact_check() の結果を整形して post meta に保存（AJAX・cron 共通処理）
	 *
	 * @param int                  $post_id 投稿ID。
	 * @param array<string, mixed> $result  Node_Gemini_API::fact_check() の戻り値。
	 * @return array<string, mixed>|WP_Error 保存済みペイロード。
	 */
	function node_ai_store_fact_check_result( int $post_id, array $result ) {
		$data = node_ai_parse_json_response( (string) ( $result['text'] ?? '' ) );
		if ( null === $data || empty( $data['claims'] ) || ! is_array( $data['claims'] ) ) {
			return new WP_Error( 'fact_check_parse_failed', 'ファクトチェック結果の解析に失敗しました。' );
		}

		$grounding = is_array( $result['grounding'] ?? null ) ? $result['grounding'] : array();
		$sources   = function_exists( 'node_ai_extract_grounding_sources' )
			? node_ai_extract_grounding_sources( $grounding )
			: array();

		$payload = array(
			'summary'         => sanitize_text_field( (string) ( $data['summary'] ?? '' ) ),
			'overall_risk'    => sanitize_key( (string) ( $data['overall_risk'] ?? 'medium' ) ),
			'claims'          => array(),
			'sources'         => $sources,
			'search_queries'  => array_map( 'sanitize_text_field', (array) ( $grounding['webSearchQueries'] ?? array() ) ),
			'grounded'        => ! empty( $sources ) || ! empty( $grounding['webSearchQueries'] ),
			'guidelines_used' => ! empty( $result['guidelines_used'] ),
			'checked_at'      => current_time( 'mysql' ),
		);

		foreach ( $data['claims'] as $claim ) {
			if ( ! is_array( $claim ) ) {
				continue;
			}
			$payload['claims'][] = array(
				'claim'      => sanitize_text_field( (string) ( $claim['claim'] ?? '' ) ),
				'status'     => sanitize_key( (string) ( $claim['status'] ?? 'uncertain' ) ),
				'confidence' => sanitize_key( (string) ( $claim['confidence'] ?? 'low' ) ),
				'note'       => sanitize_textarea_field( (string) ( $claim['note'] ?? '' ) ),
			);
		}

		if ( empty( $payload['claims'] ) ) {
			return new WP_Error( 'fact_check_no_claims', '検証対象の主張が見つかりませんでした。' );
		}

		update_post_meta( $post_id, '_node_ai_fact_check', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_node_ai_fact_check_approved', '' );
		delete_post_meta( $post_id, '_node_ai_fact_check_error' );

		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			update_post_meta( $post_id, '_node_ai_fact_check_hash', node_ai_fact_check_content_hash( $post ) );
		}

		return $payload;
	}
}

if ( ! function_exists( 'node_ai_maybe_schedule_fact_check' ) ) {
	/**
	 * 投稿保存時: 条件を満たせばファクトチェックの自動実行を予約する
	 *
	 * @param int          $post_id 投稿ID。
	 * @param WP_Post|null $post    投稿。
	 */
	function node_ai_maybe_schedule_fact_check( int $post_id, ?WP_Post $post = null ): void {
		$post = $post ?? get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_status, array( 'draft', 'pending', 'future', 'publish' ), true ) ) {
			return;
		}
		if ( ! node_ai_author_has_api_key( (int) $post->post_author ) ) {
			return;
		}

		$content = strip_shortcodes( strip_tags( $post->post_content ) );
		if ( '' === trim( $content ) ) {
			return;
		}

		// 既存結果があり、内容が前回チェック時から変わっていなければ再実行しない
		$hash = node_ai_fact_check_content_hash( $post );
		if (
			'' !== (string) get_post_meta( $post_id, '_node_ai_fact_check', true )
			&& $hash === (string) get_post_meta( $post_id, '_node_ai_fact_check_hash', true )
		) {
			return;
		}

		// デバウンス: 同一記事の予約済みイベントは置き換える
		$timestamp = wp_next_scheduled( 'node_ai_auto_fact_check', array( $post_id ) );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'node_ai_auto_fact_check', array( $post_id ) );
		}

		wp_schedule_single_event( time() + 30, 'node_ai_auto_fact_check', array( $post_id ) );
	}
}

if ( ! function_exists( 'node_ai_run_auto_fact_check' ) ) {
	/**
	 * cron: ファクトチェックを実行して結果を保存する（承認はしない）
	 *
	 * @param int $post_id 投稿ID。
	 */
	function node_ai_run_auto_fact_check( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		$author_id = (int) $post->post_author;
		if ( ! node_ai_author_has_api_key( $author_id ) ) {
			return;
		}

		$content = strip_shortcodes( strip_tags( $post->post_content ) );
		if ( '' === trim( $content ) ) {
			return;
		}

		// ライターのモデル設定・利用記録を使うため、cron 内でも投稿者として実行する
		$prev_user = get_current_user_id();
		wp_set_current_user( $author_id );

		$api    = new Node_Gemini_API( $author_id );
		$result = $api->fact_check( $content, $post->post_title );

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_node_ai_fact_check_error', sanitize_text_field( $result->get_error_message() ) );
			wp_set_current_user( $prev_user );
			return;
		}

		$stored = node_ai_store_fact_check_result( $post_id, $result );
		if ( is_wp_error( $stored ) ) {
			update_post_meta( $post_id, '_node_ai_fact_check_error', sanitize_text_field( $stored->get_error_message() ) );
		}

		wp_set_current_user( $prev_user );
	}
}

if ( ! function_exists( 'node_ai_gate_publish_without_fact_check' ) ) {
	/**
	 * 公開ゲート: ファクトチェック未実行の記事は公開・予約できない（Gutenberg/REST 経由）
	 *
	 * APIキー未登録のライターはゲート対象外（実行手段が無いため）。
	 * 既に公開済みの記事の更新はブロックしない。
	 *
	 * @param stdClass|WP_Error $prepared_post REST 挿入前の投稿データ。
	 * @param WP_REST_Request   $request       リクエスト。
	 * @return stdClass|WP_Error
	 */
	function node_ai_gate_publish_without_fact_check( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		$new_status = (string) ( $prepared_post->post_status ?? '' );
		if ( ! in_array( $new_status, array( 'publish', 'future' ), true ) ) {
			return $prepared_post;
		}

		$post_id = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;

		// 既に公開・予約済みの記事の更新はゲートしない
		if ( $post_id && in_array( (string) get_post_status( $post_id ), array( 'publish', 'future' ), true ) ) {
			return $prepared_post;
		}

		$author_id = isset( $prepared_post->post_author ) ? (int) $prepared_post->post_author : get_current_user_id();
		if ( $author_id <= 0 ) {
			$author_id = get_current_user_id();
		}
		if ( ! node_ai_author_has_api_key( $author_id ) ) {
			return $prepared_post;
		}

		if ( $post_id && '' !== (string) get_post_meta( $post_id, '_node_ai_fact_check', true ) ) {
			return $prepared_post;
		}

		return new WP_Error(
			'node_ai_fact_check_required',
			'公開前にファクトチェックの実行が必要です。下書き保存すると自動実行されます（約1分後に結果が保存されます）。手動の場合はサイドバーの「ファクトチェックを実行」を押してください。',
			array( 'status' => 403 )
		);
	}
}
