<?php
/**
 * 記事にリンクが無くても公式（一次情報）サイトを自動で見つける
 *
 * 記事本文に公式URLが書かれていない場合、従来は「検索が使えなければ裏取りできない」状態だった。
 * ここでは記事の主題（製品名・企業名・サービス名）を取り出し、
 * Wikidata の「公式ウェブサイト」(P856) から公式URLを引き当てて、そのページ本文を根拠にする。
 *
 * 方針:
 * - 検索エンジンのスクレイピングは行わない（公開APIのみを使う）。
 * - Wikidata 自体は一次情報として扱わない。あくまで「公式サイトの所在」を引くための索引として使い、
 *   検証根拠になるのは取得した公式ページ本文のほう。
 * - 取得可否・公式かどうかは Node_AI_Fact_Check_Sources 側で必ず検証する
 *   （実在しないURLや別サイトへの誤誘導は、取得と判定の段階で落ちる）。
 * - 課金は発生しない（Wikidata API は無料・キー不要）。
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Node_AI_Fact_Check_Discovery {

	/** 自動発見を有効にするか（サイト設定）。 */
	public const ENABLED_OPTION = 'node_ai_fc_discover_sources';

	private const API_ENDPOINT = 'https://www.wikidata.org/w/api.php';

	public static function is_enabled(): bool {
		$enabled = '0' !== (string) get_option( self::ENABLED_OPTION, '1' );

		return (bool) apply_filters( 'node_ai_fc_discover_enabled', $enabled );
	}

	/**
	 * 記事の主題になっている固有名詞を取り出す。
	 *
	 * タイトルを優先し、足りなければ本文の冒頭から補う。
	 * 外部呼び出しを増やさないため、抽出はローカルのヒューリスティックだけで行う。
	 *
	 * @return array<int, string>
	 */
	public static function extract_entities( string $title, string $body ): array {
		$limit      = (int) apply_filters( 'node_ai_fc_entity_limit', 3 );
		$candidates = array();

		foreach ( array( $title, mb_substr( $body, 0, 600 ) ) as $text ) {
			foreach ( self::extract_from_text( (string) $text ) as $candidate ) {
				$candidates[ $candidate ] = true;
			}
		}

		$entities = array_keys( $candidates );

		// 長い（＝具体的な）名前を優先する。「Nintendo Switch 2」を「Nintendo」より先に引く
		usort(
			$entities,
			static function ( string $a, string $b ): int {
				return mb_strlen( $b ) <=> mb_strlen( $a );
			}
		);

		return array_slice( $entities, 0, max( 1, $limit ) );
	}

	/**
	 * 1つのテキストから固有名詞候補を取り出す。
	 *
	 * @return array<int, string>
	 */
	private static function extract_from_text( string $text ): array {
		$found = array();

		// 「」『』 で囲まれた名前
		if ( preg_match_all( '/[「『]([^」』]{2,40})[」』]/u', $text, $quoted ) ) {
			foreach ( $quoted[1] as $name ) {
				$found[] = trim( $name );
			}
		}

		// 英字の連なり（数字つき製品名を含む）: Nintendo Switch 2 / Pixel 10 / iOS 26
		if ( preg_match_all( '/\b[A-Za-z][A-Za-z0-9.\-]*(?:\s+[A-Za-z0-9.\-]+){0,3}/u', $text, $latin ) ) {
			foreach ( $latin[0] as $name ) {
				$found[] = trim( $name );
			}
		}

		// カタカナの連なり（プレイステーション、ドコモ 等）
		if ( preg_match_all( '/[ァ-ヴー]{3,20}(?:\s?\d{1,3})?/u', $text, $kana ) ) {
			foreach ( $kana[0] as $name ) {
				$found[] = trim( $name );
			}
		}

		return array_values( array_filter( array_map( array( self::class, 'clean_entity' ), $found ) ) );
	}

	/**
	 * 記事タイトルによく付く語を落として、名前だけにする。
	 */
	public static function clean_entity( string $name ): string {
		$name = trim( (string) preg_replace( '/\s+/u', ' ', $name ) );

		$stopwords = (array) apply_filters(
			'node_ai_fc_entity_stopwords',
			array(
				'レビュー', 'まとめ', '使い方', '比較', '解説', '感想', '検証', '対応', '設定',
				'アップデート', 'ニュース', 'ランキング', 'おすすめ', 'ポイント', 'メリット',
				'デメリット', 'カメラ', 'バッテリー', 'ディスプレイ',
			)
		);

		foreach ( $stopwords as $stopword ) {
			if ( $name === $stopword ) {
				return '';
			}
		}

		// 短すぎる・一般的すぎる語は問い合わせない
		if ( mb_strlen( $name ) < 3 || preg_match( '/^(?:the|and|for|with|this|that|http|https|www)$/i', $name ) ) {
			return '';
		}

		return $name;
	}

	/**
	 * 記事の主題から公式サイトのURLを引く。
	 *
	 * @return array<int, array{url: string, entity: string, via: string}>
	 */
	public static function discover( string $title, string $body ): array {
		if ( ! self::is_enabled() ) {
			return array();
		}

		$results = array();
		$seen    = array();

		foreach ( self::extract_entities( $title, $body ) as $entity ) {
			foreach ( self::official_urls_for( $entity ) as $url ) {
				if ( isset( $seen[ $url ] ) ) {
					continue;
				}

				$seen[ $url ] = true;
				$results[]    = array(
					'url'    => $url,
					'entity' => $entity,
					'via'    => 'wikidata',
				);
			}
		}

		return array_slice( $results, 0, (int) apply_filters( 'node_ai_fc_discovered_url_limit', 3 ) );
	}

	/**
	 * Wikidata から「公式ウェブサイト」(P856) を引く（結果はキャッシュする）。
	 *
	 * @return array<int, string>
	 */
	public static function official_urls_for( string $entity ): array {
		$entity = trim( $entity );
		if ( '' === $entity ) {
			return array();
		}

		$cache_key = 'node_ai_fc_official_' . md5( $entity );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$id   = self::search_entity_id( $entity );
		$urls = '' === $id ? array() : self::official_urls_for_id( $id );

		// 見つからなかった場合も短めにキャッシュして、毎回問い合わせないようにする
		set_transient(
			$cache_key,
			$urls,
			( empty( $urls ) ? (int) apply_filters( 'node_ai_fc_discovery_miss_hours', 24 ) : (int) apply_filters( 'node_ai_fc_discovery_cache_hours', 24 * 7 ) ) * HOUR_IN_SECONDS
		);

		return $urls;
	}

	/**
	 * 名前から Wikidata のエンティティIDを引く。
	 */
	private static function search_entity_id( string $entity ): string {
		$response = self::request(
			array(
				'action'   => 'wbsearchentities',
				'search'   => $entity,
				'language' => 'ja',
				'uselang'  => 'ja',
				'limit'    => 3,
				'format'   => 'json',
			)
		);

		$hits = (array) ( $response['search'] ?? array() );

		if ( empty( $hits ) ) {
			// 日本語で見つからない場合は英語でも引く
			$response = self::request(
				array(
					'action'   => 'wbsearchentities',
					'search'   => $entity,
					'language' => 'en',
					'uselang'  => 'en',
					'limit'    => 3,
					'format'   => 'json',
				)
			);
			$hits     = (array) ( $response['search'] ?? array() );
		}

		foreach ( $hits as $hit ) {
			$id = (string) ( $hit['id'] ?? '' );
			if ( preg_match( '/^Q\d+$/', $id ) ) {
				return $id;
			}
		}

		return '';
	}

	/**
	 * エンティティIDから公式サイトURLを取り出す。
	 *
	 * @return array<int, string>
	 */
	private static function official_urls_for_id( string $id ): array {
		$response = self::request(
			array(
				'action'   => 'wbgetclaims',
				'entity'   => $id,
				'property' => 'P856',
				'format'   => 'json',
			)
		);

		$urls      = array();
		$preferred = array();

		foreach ( (array) ( $response['claims']['P856'] ?? array() ) as $claim ) {
			if ( ! is_array( $claim ) ) {
				continue;
			}

			$value = (string) ( $claim['mainsnak']['datavalue']['value'] ?? '' );
			$url   = esc_url_raw( $value );

			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}

			if ( 'preferred' === (string) ( $claim['rank'] ?? '' ) ) {
				$preferred[] = $url;
			} else {
				$urls[] = $url;
			}
		}

		$merged = array_values( array_unique( array_merge( $preferred, $urls ) ) );

		return array_slice( $merged, 0, 2 );
	}

	/**
	 * Wikidata API への問い合わせ（公開API・キー不要・課金なし）。
	 *
	 * @param array<string, mixed> $args クエリ。
	 * @return array<string, mixed>
	 */
	private static function request( array $args ): array {
		$response = wp_remote_get(
			add_query_arg( array_map( 'strval', $args ), self::API_ENDPOINT ),
			array(
				'timeout'    => 12,
				'user-agent' => 'NodeFactCheck/1.0 (+' . home_url( '/' ) . ')',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : array();
	}
}
