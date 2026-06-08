<?php
/**
 * Gemini API モデル一覧の動的取得
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transient キー
 */
function node_gemini_models_cache_key(): string {
	return 'node_gemini_models_cache';
}

/**
 * フォールバック用の静的モデル一覧
 *
 * @return array<string, string>
 */
function node_get_gemini_model_fallback_options(): array {
	return array(
		'gemini-2.5-flash'       => 'Gemini 2.5 Flash',
		'gemini-2.5-pro'         => 'Gemini 2.5 Pro',
		'gemini-2.0-flash'       => 'Gemini 2.0 Flash',
		'gemini-2.0-flash-lite'  => 'Gemini 2.0 Flash-Lite',
	);
}

/**
 * モデル一覧キャッシュを削除
 */
function node_clear_gemini_models_cache(): void {
	delete_transient( node_gemini_models_cache_key() );
}

/**
 * モデル一覧取得に使う API キーを解決
 *
 * @param int $user_id 優先するユーザーID。0 の場合は現在のユーザー。
 */
function node_resolve_gemini_api_key_for_models( int $user_id = 0 ): string {
	if ( function_exists( 'node_get_user_gemini_api_key' ) ) {
		$key = node_get_user_gemini_api_key( $user_id > 0 ? $user_id : 0 );
		if ( '' !== $key ) {
			return $key;
		}
	}

	if ( defined( 'GEMINI_API_KEY' ) && GEMINI_API_KEY ) {
		return (string) GEMINI_API_KEY;
	}

	return '';
}

/**
 * API レスポンスから generateContent 対応の Gemini モデルを抽出
 *
 * @param array<string, mixed> $model API モデルオブジェクト。
 * @return array{0: string, 1: string}|null [ id, label ]
 */
function node_parse_gemini_model_entry( array $model ): ?array {
	$name = (string) ( $model['name'] ?? '' );
	if ( ! str_starts_with( $name, 'models/' ) ) {
		return null;
	}

	$id = substr( $name, 7 );
	if ( ! preg_match( '/^gemini-[a-z0-9][a-z0-9.-]*$/i', $id ) ) {
		return null;
	}

	if ( preg_match( '/(embedding|embed|aqa|imagen|veo|tts|live|computer-use)/i', $id ) ) {
		return null;
	}

	$methods = (array) ( $model['supportedGenerationMethods'] ?? array() );
	if ( ! in_array( 'generateContent', $methods, true ) ) {
		return null;
	}

	$label = trim( (string) ( $model['displayName'] ?? '' ) );
	if ( '' === $label ) {
		$label = $id;
	}

	return array( $id, $label );
}

/**
 * Gemini API からモデル一覧を取得
 *
 * @param string $api_key       API キー。
 * @param bool   $force_refresh キャッシュを無視する。
 * @return array{models: array<string, string>, from_api: bool, fetched_at: int}|WP_Error
 */
function node_fetch_gemini_models_from_api( string $api_key, bool $force_refresh = false ) {
	$cache_key   = node_gemini_models_cache_key();
	$cache_hours = (int) apply_filters( 'node_gemini_models_cache_hours', 6 );

	if ( ! $force_refresh ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && ! empty( $cached['models'] ) && is_array( $cached['models'] ) ) {
			return $cached;
		}
	}

	if ( '' === trim( $api_key ) ) {
		return new WP_Error( 'missing_api_key', 'API キーが未設定です。' );
	}

	$models   = array();
	$page_url = add_query_arg(
		array(
			'key'      => $api_key,
			'pageSize' => 100,
		),
		'https://generativelanguage.googleapis.com/v1beta/models'
	);

	for ( $page = 0; $page < 20; $page++ ) {
		$response = wp_remote_get(
			$page_url,
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			break;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			break;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			break;
		}

		foreach ( (array) ( $data['models'] ?? array() ) as $model ) {
			if ( ! is_array( $model ) ) {
				continue;
			}

			$parsed = node_parse_gemini_model_entry( $model );
			if ( null === $parsed ) {
				continue;
			}

			$models[ $parsed[0] ] = $parsed[1];
		}

		$next_token = (string) ( $data['nextPageToken'] ?? '' );
		if ( '' === $next_token ) {
			break;
		}

		$page_url = add_query_arg(
			array(
				'key'       => $api_key,
				'pageSize'  => 100,
				'pageToken' => $next_token,
			),
			'https://generativelanguage.googleapis.com/v1beta/models'
		);
	}

	if ( empty( $models ) ) {
		$stale = get_transient( $cache_key );
		if ( is_array( $stale ) && ! empty( $stale['models'] ) ) {
			return $stale;
		}

		return new WP_Error( 'models_fetch_failed', 'Gemini API からモデル一覧を取得できませんでした。' );
	}

	uasort(
		$models,
		static function ( string $label_a, string $label_b ): int {
			return strnatcasecmp( $label_a, $label_b );
		}
	);

	$payload = array(
		'models'    => $models,
		'from_api'  => true,
		'fetched_at'=> time(),
	);

	set_transient( $cache_key, $payload, $cache_hours * HOUR_IN_SECONDS );

	return $payload;
}

/**
 * 表示用モデル一覧（API 優先、失敗時フォールバック）
 *
 * @param int $user_id モデル取得に使う API キーのユーザー。
 * @return array<string, string>
 */
function node_get_gemini_model_options( int $user_id = 0 ): array {
	$api_key = node_resolve_gemini_api_key_for_models( $user_id );
	$result  = node_fetch_gemini_models_from_api( $api_key );

	if ( is_array( $result ) && ! empty( $result['models'] ) ) {
		return $result['models'];
	}

	return node_get_gemini_model_fallback_options();
}

/**
 * モデル一覧の取得メタ情報
 *
 * @param int $user_id ユーザーID。
 * @return array{from_api: bool, fetched_at: int}
 */
function node_get_gemini_models_meta( int $user_id = 0 ): array {
	$api_key = node_resolve_gemini_api_key_for_models( $user_id );
	$cached  = get_transient( node_gemini_models_cache_key() );

	if ( is_array( $cached ) && ! empty( $cached['models'] ) && '' !== $api_key ) {
		return array(
			'from_api'   => ! empty( $cached['from_api'] ),
			'fetched_at' => (int) ( $cached['fetched_at'] ?? 0 ),
		);
	}

	return array(
		'from_api'   => false,
		'fetched_at' => 0,
	);
}

/**
 * ユーザー向けモデル選択肢（保存済みモデルも含む）
 *
 * @param int $user_id ユーザーID。
 * @return array<string, string>
 */
function node_get_gemini_model_options_for_user( int $user_id ): array {
	$options = node_get_gemini_model_options( $user_id );
	$saved   = get_user_meta( $user_id, 'node_gemini_model', true );
	$saved   = is_string( $saved ) ? trim( $saved ) : '';

	if ( '' !== $saved && ! isset( $options[ $saved ] ) ) {
		$options = array_merge(
			array( $saved => sprintf( '%s (%s)', $saved, __( '保存済み', 'node' ) ) ),
			$options
		);
	}

	return $options;
}

/**
 * デフォルトモデル（Flash 系を優先）
 */
function node_get_default_gemini_model(): string {
	$options = node_get_gemini_model_options();

	foreach ( array_keys( $options ) as $id ) {
		if ( false !== stripos( $id, 'flash' ) ) {
			return $id;
		}
	}

	$keys = array_keys( $options );
	return $keys[0] ?? 'gemini-2.0-flash';
}

/**
 * 保存可能なモデル ID か
 *
 * @param string $model モデル ID。
 */
function node_is_valid_gemini_model_id( string $model ): bool {
	return (bool) preg_match( '/^gemini-[a-z0-9][a-z0-9.-]*$/i', $model );
}
