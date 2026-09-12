<?php
/**
 * ファクトチェック用 Gemini モデルの選択（Free Tier Max）
 *
 * 設計方針（2026-08-18 の Google 公式ドキュメントを確認して実装）:
 * - モデルIDをコードへ固定しない。Models API（ListModels）の結果から都度選ぶ。
 * - ただし「API に載っている＝無料枠で使える」とは判定しない。
 *   Gemini API の料金表で無料枠（Free of charge）が明示されているのは Flash / Flash-Lite 系のみで、
 *   Pro / Preview / 画像・音声などの特殊エンドポイントは無料枠の対象外か不明。
 *   そのため「素の gemini-<版>-flash / gemini-<版>-flash-lite」だけを自動利用の対象にする。
 * - `-latest` エイリアスは指す先が Preview / Experimental へ入れ替わりうるため自動利用しない。
 * - 有料モデルは管理者が明示的に許可（node_ai_fc_allow_paid）しない限り使わない。
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Node_AI_Fact_Check_Models {

	/** モデル一覧キャッシュ（Transients API）。 */
	public const CACHE_KEY = 'node_ai_fc_models';

	/** 最後に正常動作したモデル（Options API）。 */
	public const LAST_GOOD_OPTION = 'node_ai_fc_last_good_model';

	/** generateContent が 404 を返したモデル（提供終了）。 */
	public const RETIRED_OPTION = 'node_ai_fc_retired_models';

	/** モデル選択モード: auto | manual。 */
	public const MODE_OPTION = 'node_ai_fc_model_mode';

	/** manual モードで使うモデルID。 */
	public const MANUAL_OPTION = 'node_ai_fc_manual_model';

	/** 有料モデルの利用を管理者が明示的に許可したか。 */
	public const ALLOW_PAID_OPTION = 'node_ai_fc_allow_paid';

	/**
	 * 無料枠で使えると判断できるモデルIDの形。
	 *
	 * 枝番なしの安定版 Flash / Flash-Lite のみ。
	 * preview / experimental / -latest / 日付入りスナップショット / Pro / 画像・音声系は
	 * この形に一致しないため、自動選択の対象外になる。
	 */
	public const FREE_TIER_PATTERN = '/^gemini-(\d+(?:\.\d+)?)-flash(-lite)?$/';

	/**
	 * Models API に到達できないときの候補（最後の手段）。
	 *
	 * 2026-08-18 に Gemini API の Models / Pricing ドキュメントで
	 * 「Free of charge」を確認したIDのみ。ここに固定するのではなく、
	 * API から一覧が取れたときは常に API 側を正とする。
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fallback_catalog(): array {
		$catalog = array(
			'gemini-3.7-flash'      => array( 'label' => 'Gemini 3.7 Flash' ),
			'gemini-3.6-flash'      => array( 'label' => 'Gemini 3.6 Flash' ),
			'gemini-3.5-flash'      => array( 'label' => 'Gemini 3.5 Flash' ),
			'gemini-2.5-flash'      => array( 'label' => 'Gemini 2.5 Flash' ),
			'gemini-3.5-flash-lite' => array( 'label' => 'Gemini 3.5 Flash-Lite' ),
			'gemini-3.1-flash-lite' => array( 'label' => 'Gemini 3.1 Flash-Lite' ),
			'gemini-2.5-flash-lite' => array( 'label' => 'Gemini 2.5 Flash-Lite' ),
		);

		foreach ( $catalog as $id => $meta ) {
			$catalog[ $id ] = array_merge(
				array(
					'label'    => $id,
					'version'  => '',
					'thinking' => true,
					'methods'  => array( 'generateContent' ),
					'source'   => 'fallback',
				),
				$meta
			);
		}

		return $catalog;
	}

	/**
	 * 無料枠で自動利用してよいモデルIDか。
	 *
	 * 「無料枠かどうか不明なモデルを試して課金される」ことを防ぐため、
	 * 判定できないものはすべて false にする（安全側）。
	 */
	public static function is_free_tier_candidate( string $id ): bool {
		$id = trim( $id );

		if ( ! preg_match( self::FREE_TIER_PATTERN, $id ) ) {
			return false;
		}

		if ( in_array( $id, self::get_retired(), true ) ) {
			return false;
		}

		$denylist = (array) apply_filters( 'node_ai_fc_model_denylist', array() );
		if ( in_array( $id, $denylist, true ) ) {
			return false;
		}

		return (bool) apply_filters( 'node_ai_fc_is_free_tier_model', true, $id );
	}

	/**
	 * 安定版 / Preview / Experimental / エイリアスの区別。
	 */
	public static function classify( string $id ): string {
		$id = strtolower( trim( $id ) );

		if ( str_ends_with( $id, '-latest' ) ) {
			return 'alias';
		}
		if ( false !== strpos( $id, '-exp' ) ) {
			return 'experimental';
		}
		if ( false !== strpos( $id, 'preview' ) ) {
			return 'preview';
		}

		return 'stable';
	}

	/**
	 * モデルIDから世代（版数）と Lite かどうかを取り出す。
	 *
	 * @return array{version: float, lite: bool}
	 */
	public static function parse_id( string $id ): array {
		if ( ! preg_match( self::FREE_TIER_PATTERN, trim( $id ), $m ) ) {
			return array(
				'version' => 0.0,
				'lite'    => false,
			);
		}

		return array(
			'version' => (float) $m[1],
			'lite'    => ! empty( $m[2] ),
		);
	}

	// ------------------------------------------------------------------
	// Models API
	// ------------------------------------------------------------------

	/**
	 * ファクトチェック用のAPIキー（Node_Gemini_API と同じ解決順）。
	 */
	public static function resolve_api_key( int $user_id = 0 ): string {
		// 0 のときは実行中のユーザーの個人キーを見る（ライターは個人キー運用のため）。
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( function_exists( 'node_get_user_gemini_api_key' ) && $user_id > 0 ) {
			$key = node_get_user_gemini_api_key( $user_id );
			if ( '' !== $key ) {
				return $key;
			}
		} elseif ( $user_id > 0 ) {
			$key = trim( (string) get_user_meta( $user_id, 'node_gemini_api_key', true ) );
			if ( '' !== $key ) {
				return $key;
			}
		}

		$site_key = trim( (string) get_option( 'node_ai_gemini_api_key', '' ) );
		if ( '' !== $site_key ) {
			return $site_key;
		}

		if ( defined( 'GEMINI_API_KEY' ) && GEMINI_API_KEY ) {
			return (string) GEMINI_API_KEY;
		}

		return '';
	}

	/**
	 * Models API からモデル一覧を取得する（Transient キャッシュつき）。
	 *
	 * 取得に失敗した場合は、直近に取得できた一覧（期限切れでも）→ 静的候補の順に退避する。
	 *
	 * @param bool $force_refresh キャッシュを無視する（管理者の手動更新）。
	 * @param int  $user_id       APIキー解決に使うユーザー。
	 * @return array{models: array<string, array<string, mixed>>, from_api: bool, fetched_at: int}
	 */
	public static function fetch_catalog( bool $force_refresh = false, int $user_id = 0 ): array {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) && ! empty( $cached['models'] ) ) {
				return $cached;
			}
		}

		$api_key = (string) apply_filters( 'node_ai_fc_models_api_key', self::resolve_api_key( $user_id ), $user_id );
		$models  = '' === $api_key ? array() : self::request_models( $api_key );

		if ( empty( $models ) ) {
			// 取得に失敗したら、直近に正常取得できた一覧を使う（無ければ静的候補）。
			$stale = get_option( self::CACHE_KEY . '_last', array() );
			if ( is_array( $stale ) && ! empty( $stale['models'] ) ) {
				return $stale;
			}

			return array(
				'models'     => self::fallback_catalog(),
				'from_api'   => false,
				'fetched_at' => 0,
			);
		}

		$payload = array(
			'models'     => $models,
			'from_api'   => true,
			'fetched_at' => time(),
		);

		$hours = (int) apply_filters( 'node_ai_fc_models_cache_hours', 12 );
		set_transient( self::CACHE_KEY, $payload, max( 1, $hours ) * HOUR_IN_SECONDS );
		update_option( self::CACHE_KEY . '_last', $payload, false );

		return $payload;
	}

	/**
	 * ListModels を叩いて generateContent 対応モデルのメタ情報を集める。
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function request_models( string $api_key ): array {
		$models = array();
		$url    = add_query_arg(
			array(
				'key'      => $api_key,
				'pageSize' => 100,
			),
			'https://generativelanguage.googleapis.com/v1beta/models'
		);

		for ( $page = 0; $page < 10; $page++ ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
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

				$name = (string) ( $model['name'] ?? '' );
				if ( ! str_starts_with( $name, 'models/' ) ) {
					continue;
				}

				$id      = substr( $name, 7 );
				$methods = (array) ( $model['supportedGenerationMethods'] ?? array() );

				if ( ! in_array( 'generateContent', $methods, true ) ) {
					continue;
				}

				$models[ $id ] = array(
					'label'        => (string) ( $model['displayName'] ?? $id ),
					'version'      => (string) ( $model['version'] ?? '' ),
					'thinking'     => ! empty( $model['thinking'] ),
					'methods'      => array_map( 'strval', $methods ),
					'input_limit'  => (int) ( $model['inputTokenLimit'] ?? 0 ),
					'output_limit' => (int) ( $model['outputTokenLimit'] ?? 0 ),
					'source'       => 'api',
				);
			}

			$token = (string) ( $data['nextPageToken'] ?? '' );
			if ( '' === $token ) {
				break;
			}

			$url = add_query_arg(
				array(
					'key'       => $api_key,
					'pageSize'  => 100,
					'pageToken' => $token,
				),
				'https://generativelanguage.googleapis.com/v1beta/models'
			);
		}

		return $models;
	}

	/**
	 * モデル一覧キャッシュを捨てる（管理者の手動更新 / 404 検出時）。
	 */
	public static function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	// ------------------------------------------------------------------
	// 候補と選択
	// ------------------------------------------------------------------

	/**
	 * 無料枠で使える候補を優先順に並べて返す。
	 *
	 * 1. 無料枠の最新安定版 Flash
	 * 2. 1世代前の安定版 Flash
	 * 3. 無料枠の Flash-Lite
	 * 4. 最後に正常動作したモデル
	 *
	 * @param bool $force_refresh モデル一覧を強制再取得する。
	 * @return array<int, string>
	 */
	public static function candidates( bool $force_refresh = false ): array {
		$catalog = self::fetch_catalog( $force_refresh );
		$ids     = array();

		foreach ( array_keys( (array) $catalog['models'] ) as $id ) {
			if ( self::is_free_tier_candidate( (string) $id ) ) {
				$ids[] = (string) $id;
			}
		}

		// API 側が空（キー未設定など）でも、静的候補から選べるようにする。
		if ( empty( $ids ) ) {
			foreach ( array_keys( self::fallback_catalog() ) as $id ) {
				if ( self::is_free_tier_candidate( (string) $id ) ) {
					$ids[] = (string) $id;
				}
			}
		}

		usort(
			$ids,
			static function ( string $a, string $b ): int {
				$pa = self::parse_id( $a );
				$pb = self::parse_id( $b );

				// Flash を Flash-Lite より優先し、同種なら新しい世代を優先する。
				if ( $pa['lite'] !== $pb['lite'] ) {
					return $pa['lite'] ? 1 : -1;
				}

				return $pb['version'] <=> $pa['version'];
			}
		);

		// 最後に正常動作したモデルを最終候補として末尾に足す。
		$last_good = (string) get_option( self::LAST_GOOD_OPTION, '' );
		if ( '' !== $last_good && self::is_free_tier_candidate( $last_good ) && ! in_array( $last_good, $ids, true ) ) {
			$ids[] = $last_good;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * 現在の設定モード（auto / manual）。
	 */
	public static function get_mode(): string {
		$mode = (string) get_option( self::MODE_OPTION, 'auto' );

		return 'manual' === $mode ? 'manual' : 'auto';
	}

	/**
	 * ファクトチェックに使うモデルを決める。
	 *
	 * @param array<int, string> $exclude 今回すでに失敗したモデル。
	 * @return string|WP_Error モデルID。候補が尽きたら WP_Error（有料へは切り替えない）。
	 */
	public static function select( array $exclude = array() ) {
		if ( 'manual' === self::get_mode() ) {
			$manual = trim( (string) get_option( self::MANUAL_OPTION, '' ) );

			if ( '' !== $manual && ! in_array( $manual, $exclude, true ) && ! self::is_unavailable( $manual ) ) {
				// 有料・無料不明のモデルは、管理者が明示的に許可した場合のみ使う。
				if ( self::is_free_tier_candidate( $manual ) || '1' === (string) get_option( self::ALLOW_PAID_OPTION, '0' ) ) {
					return $manual;
				}
			}
		}

		foreach ( self::candidates() as $id ) {
			if ( in_array( $id, $exclude, true ) || self::is_unavailable( $id ) ) {
				continue;
			}

			return $id;
		}

		return new WP_Error(
			'node_ai_fc_no_model',
			'無料枠で利用できる Gemini Flash モデルが見つからなかったため、ファクトチェックを安全に中止しました。有料モデルへの自動切り替えは行いません。時間をおいて再実行してください。',
			array( 'status' => 503 )
		);
	}

	// ------------------------------------------------------------------
	// 状態の記録
	// ------------------------------------------------------------------

	/**
	 * @return array<int, string>
	 */
	public static function get_retired(): array {
		$stored = get_option( self::RETIRED_OPTION, array() );

		return is_array( $stored ) ? array_values( array_unique( array_map( 'strval', $stored ) ) ) : array();
	}

	/**
	 * generateContent が 404 を返したモデルを記録し、以後の候補から外す。
	 */
	public static function record_retired( string $id ): void {
		$id = trim( $id );
		if ( '' === $id ) {
			return;
		}

		$retired = self::get_retired();
		if ( in_array( $id, $retired, true ) ) {
			return;
		}

		$retired[] = $id;
		update_option( self::RETIRED_OPTION, $retired, false );

		// 404 は一覧が古い可能性が高いので取り直す。
		self::clear_cache();
	}

	/**
	 * 429 などで一時的に使えないモデルを記録する（有限時間）。
	 */
	public static function record_unavailable( string $id, int $seconds ): void {
		$id = trim( $id );
		if ( '' === $id ) {
			return;
		}

		$max     = (int) apply_filters( 'node_ai_fc_unavailable_max_seconds', 10 * MINUTE_IN_SECONDS );
		$seconds = max( 60, min( $seconds > 0 ? $seconds : 60, $max ) );

		set_transient( 'node_ai_fc_unavail_' . md5( $id ), time() + $seconds, $seconds );
	}

	public static function is_unavailable( string $id ): bool {
		$until = get_transient( 'node_ai_fc_unavail_' . md5( trim( $id ) ) );

		return is_numeric( $until ) && (int) $until > time();
	}

	/**
	 * 正常動作したモデルを「最後に成功したモデル」として保存する。
	 */
	public static function record_success( string $id ): void {
		$id = trim( $id );
		if ( '' === $id || $id === (string) get_option( self::LAST_GOOD_OPTION, '' ) ) {
			return;
		}

		update_option( self::LAST_GOOD_OPTION, $id, false );
	}

	/**
	 * 管理画面表示用の情報。
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$catalog    = self::fetch_catalog();
		$candidates = self::candidates();
		$selected   = self::select();

		return array(
			'mode'        => self::get_mode(),
			'selected'    => is_wp_error( $selected ) ? '' : $selected,
			'error'       => is_wp_error( $selected ) ? $selected->get_error_message() : '',
			'candidates'  => $candidates,
			'catalog'     => (array) $catalog['models'],
			'from_api'    => ! empty( $catalog['from_api'] ),
			'fetched_at'  => (int) ( $catalog['fetched_at'] ?? 0 ),
			'last_good'   => (string) get_option( self::LAST_GOOD_OPTION, '' ),
			'retired'     => self::get_retired(),
			'allow_paid'  => '1' === (string) get_option( self::ALLOW_PAID_OPTION, '0' ),
		);
	}

	/**
	 * 実際に選んで使えるモデルだけの一覧（ラベル付き）。
	 *
	 * Pro は無料枠の対象外で選んでも 429 になるため出さない。
	 * Preview / Experimental / -latest / 提供終了 / 一時的に利用不可のものも除く。
	 *
	 * @param int $user_id API キー解決に使うユーザー。
	 * @return array<string, string> id => ラベル
	 */
	public static function usable_options( int $user_id = 0 ): array {
		$catalog = self::fetch_catalog( false, $user_id );
		$options = array();

		foreach ( self::candidates() as $id ) {
			if ( self::is_unavailable( $id ) ) {
				continue;
			}

			$options[ $id ] = (string) ( $catalog['models'][ $id ]['label'] ?? $id );
		}

		return $options;
	}

	/**
	 * そのモデルが「確実に使えない」と分かっているか。
	 *
	 * 提供終了・直近の 429・（明示許可のない）Pro が対象。
	 * 単に無料枠候補に入っていないだけのモデルは、ここでは弾かない
	 * （管理者が意図して指定した Preview 等を勝手に無効化しないため）。
	 */
	public static function is_known_unusable( string $id ): bool {
		$id = trim( $id );
		if ( '' === $id ) {
			return true;
		}

		if ( in_array( $id, self::get_retired(), true ) || self::is_unavailable( $id ) ) {
			return true;
		}

		return (bool) preg_match( '/-pro(?:-|$)/i', $id ) && '1' !== (string) get_option( self::ALLOW_PAID_OPTION, '0' );
	}

	/**
	 * モデル一覧の自動更新（日次 cron）。
	 *
	 * cron にはログインユーザーがいないため、サイト共通キー → 定数 →
	 * 個人キーを登録済みのユーザーの順にキーを探して取得する。
	 */
	public static function refresh_catalog(): void {
		$key_user_id = 0;
		$key         = self::resolve_api_key( 0 );

		if ( '' === $key ) {
			$users = get_users(
				array(
					'meta_key'     => 'node_gemini_api_key',
					'meta_compare' => 'EXISTS',
					'number'       => 5,
					'fields'       => 'ID',
				)
			);

			foreach ( $users as $user_id ) {
				if ( '' !== trim( (string) get_user_meta( (int) $user_id, 'node_gemini_api_key', true ) ) ) {
					$key_user_id = (int) $user_id;
					$key         = self::resolve_api_key( $key_user_id );
					break;
				}
			}
		}

		if ( '' === $key ) {
			return;
		}

		self::clear_cache();
		self::fetch_catalog( true, $key_user_id );
	}

	/**
	 * モデルが thinking（思考量指定）に対応しているか。
	 * Models API の thinking フラグを正とし、不明な場合は指定しない（安全側）。
	 */
	public static function supports_thinking( string $id ): bool {
		$catalog = self::fetch_catalog();
		$meta    = (array) ( $catalog['models'][ $id ] ?? array() );

		return ! empty( $meta['thinking'] );
	}
}
