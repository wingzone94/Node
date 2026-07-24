<?php
/**
 * Qwen プロバイダーアダプター（OpenAI Compatible API: /chat/completions）
 *
 * DashScope の互換モードを既定としつつ、エンドポイント URL は option で差し替え可能。
 * 他の OpenAI 互換サービスにもそのまま接続できる。
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Node_AI_Provider_Qwen implements Node_AI_Provider {

	public const DEFAULT_ENDPOINT = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1';
	public const DEFAULT_MODEL    = 'qwen-plus';

	public function generate( string $prompt, array $options = array() ) {
		$key = $this->api_key();
		if ( '' === $key ) {
			return new WP_Error( 'ai_missing_credentials', 'Qwen の API キーが設定されていません。AI設定から登録してください。' );
		}

		$options = wp_parse_args(
			$options,
			array(
				'system_instruction' => '',
				'max_tokens'         => 1024,
				'temperature'        => 0.7,
				'json'               => false,
				'timeout'            => 45,
			)
		);

		$messages = array();
		if ( '' !== (string) $options['system_instruction'] ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => (string) $options['system_instruction'],
			);
		}
		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		$body = array(
			'model'       => $this->get_model(),
			'messages'    => $messages,
			'max_tokens'  => (int) $options['max_tokens'],
			'temperature' => (float) $options['temperature'],
		);
		if ( ! empty( $options['json'] ) ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$response = wp_remote_post(
			$this->endpoint() . '/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => (int) $options['timeout'],
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== strpos( $message, 'timed out' ) ) {
				return new WP_Error( 'ai_timeout', 'Qwen API からの応答がタイムアウトしました。しばらく待ってから再度お試しください。' );
			}
			return new WP_Error( 'ai_unavailable', 'Qwen API への接続に失敗しました。詳細: ' . $message );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			$api_message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			$code        = 'ai_error';
			if ( 401 === $status || 403 === $status ) {
				$code = 'ai_missing_credentials';
			} elseif ( 429 === $status ) {
				$code = 'ai_quota';
			} elseif ( $status >= 500 ) {
				$code = 'ai_unavailable';
			}
			return new WP_Error( $code, 'Qwen API エラー (HTTP ' . $status . ')' . ( '' !== $api_message ? ': ' . $api_message : '' ) );
		}

		$text = is_array( $data ) ? (string) ( $data['choices'][0]['message']['content'] ?? '' ) : '';
		if ( '' === trim( $text ) ) {
			return new WP_Error( 'ai_bad_response', 'Qwen API から有効なレスポンスが得られませんでした。' );
		}

		$text = trim( $text );

		if ( ! empty( $options['return_metadata'] ) ) {
			return array(
				'text'      => $text,
				'grounding' => array(),
				'tokens'    => (int) ( $data['usage']['total_tokens'] ?? 0 ),
			);
		}

		return $text;
	}

	public function test_connection() {
		$key = $this->api_key();
		if ( '' === $key ) {
			return new WP_Error( 'ai_missing_credentials', 'Qwen の API キーが設定されていません。AI設定から登録してください。' );
		}

		$response = wp_remote_get(
			$this->endpoint() . '/models',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ai_unavailable', 'Qwen API への接続に失敗しました。詳細: ' . $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error( 'ai_error', 'Qwen API 接続テストに失敗しました (HTTP ' . $status . ')。エンドポイントと API キーを確認してください。' );
		}

		return true;
	}

	public function get_label(): string {
		return 'Qwen';
	}

	public function get_model(): string {
		$model = trim( (string) get_option( 'node_ai_qwen_model', '' ) );
		return '' !== $model ? $model : self::DEFAULT_MODEL;
	}

	private function endpoint(): string {
		$endpoint = trim( (string) get_option( 'node_ai_qwen_endpoint', '' ) );
		if ( '' === $endpoint ) {
			$endpoint = self::DEFAULT_ENDPOINT;
		}
		return rtrim( $endpoint, '/' );
	}

	private function api_key(): string {
		return trim( (string) get_option( 'node_ai_qwen_api_key', '' ) );
	}
}
