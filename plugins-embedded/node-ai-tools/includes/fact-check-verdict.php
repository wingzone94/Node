<?php
/**
 * ファクトチェック判定の後処理（誤判定の連鎖を止めるサーバー側ガード）
 *
 * モデルの出力をそのまま結果として採用しない。
 * 「根拠がない」ことを「誤り」として扱わせない、否定命題を内部知識だけで断定させない、
 * ひとつの前提から複数項目・記事全体のリスクへ波及させない、を PHP 側で担保する。
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_ai_fc_statuses' ) ) {
	/**
	 * 使用してよい status の一覧。
	 *
	 * @return array<int, string>
	 */
	function node_ai_fc_statuses(): array {
		return array( 'correct', 'likely_correct', 'uncertain', 'likely_incorrect', 'unverifiable' );
	}
}

if ( ! function_exists( 'node_ai_fc_is_negative_claim' ) ) {
	/**
	 * 「未発表 / 存在しない / 非対応 / サービス終了」等の否定命題か。
	 *
	 * この種の判定は内部知識だけでは成立しない（知らないことは存在しないことの根拠にならない）。
	 *
	 * @param string $text 主張および註記。
	 */
	function node_ai_fc_is_negative_claim( string $text ): bool {
		$patterns = (array) apply_filters(
			'node_ai_fc_negative_patterns',
			array(
				'未発表',
				'発表されていない',
				'発表されていません',
				'存在しません',
				'実在しません',
				'対応していません',
				'提供されていません',
				'搭載されていません',
				'発表されておらず',
				'公表されていない',
				'存在しない',
				'実在しない',
				'発売されていない',
				'未発売',
				'リリースされていない',
				'対応していない',
				'非対応',
				'搭載されていない',
				'公式情報がな',
				'公式発表がな',
				'情報が確認できない',
				'確認できません',
				'確認できない',
				'提供されていない',
				'提供終了',
				'サービス終了',
				'廃止',
				'存在が確認できない',
				'該当する製品はな',
				'見つかりません',
				'not announced',
				'does not exist',
				'no official',
			)
		);

		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== mb_strpos( $text, (string) $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'node_ai_fc_is_subjective' ) ) {
	/**
	 * 筆者の感想・主観表現か（客観的事実として検証すべきでないもの）。
	 *
	 * @param string $text 主張。
	 */
	function node_ai_fc_is_subjective( string $text ): bool {
		$patterns = (array) apply_filters(
			'node_ai_fc_subjective_patterns',
			array(
				'と感じ',
				'感じた',
				'感じました',
				'物足りな',
				'個人的に',
				'私見',
				'筆者としては',
				'気に入',
				'気になった',
				'好みが分かれ',
				'印象',
				'と思います',
				'と思った',
				'快適だっ',
				'使いやすかっ',
				'見やすくなった',
			)
		);

		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== mb_strpos( $text, (string) $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'node_ai_fc_claim_has_evidence' ) ) {
	/**
	 * その主張に外部根拠が付いているか。
	 *
	 * @param array<string, mixed> $claim 主張。
	 */
	function node_ai_fc_claim_has_evidence( array $claim ): bool {
		foreach ( (array) ( $claim['evidence'] ?? array() ) as $evidence ) {
			if ( is_array( $evidence ) && '' !== trim( (string) ( $evidence['url'] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'node_ai_fc_claim_has_official_evidence' ) ) {
	/**
	 * その主張に一次情報（公式）の根拠が付いているか。
	 *
	 * @param array<string, mixed> $claim 主張。
	 */
	function node_ai_fc_claim_has_official_evidence( array $claim ): bool {
		foreach ( (array) ( $claim['evidence'] ?? array() ) as $evidence ) {
			if ( is_array( $evidence ) && ! empty( $evidence['official'] ) && '' !== trim( (string) ( $evidence['url'] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'node_ai_fc_evidence_supports' ) ) {
	/**
	 * モデルが挙げた根拠が、実際に取得したページ本文で裏づけられているか。
	 *
	 * モデルは「公式ページにこう書いてある」と述べながら、実際にはそのページに無い内容
	 * （内部知識由来の別の名称・価格など）を根拠にすることがある。実測では、公式ブログを
	 * 引用しつつ「Gemini Spark は存在しない」「Antigravity は存在しない」と断定した。
	 * 引用元の本文を持っている場合は、註記に出てくる固有の語がその本文にあるかを確認し、
	 * ひとつも無ければ「裏づけなし」として扱う。
	 *
	 * @param array<string, mixed> $claim   主張。
	 * @param array<string, mixed> $context 実行コンテキスト（evidence_text を含む）。
	 */
	function node_ai_fc_evidence_supports( array $claim, array $context ): bool {
		$texts = (array) ( $context['evidence_text'] ?? array() );
		if ( empty( $texts ) ) {
			return true; // 引用元の本文を持っていない場合は判断しない
		}

		$body = '';
		foreach ( (array) ( $claim['evidence'] ?? array() ) as $evidence ) {
			$url = (string) ( $evidence['url'] ?? '' );
			if ( isset( $texts[ $url ] ) ) {
				$body .= ' ' . (string) $texts[ $url ];
			}
		}

		if ( '' === trim( $body ) ) {
			return true; // 引用先の本文が手元に無い（検索由来の URL など）
		}

		$tokens = node_ai_fc_extract_evidence_tokens( (string) ( $claim['note'] ?? '' ) );
		if ( empty( $tokens ) ) {
			return true; // 照合できる語がなければ判断しない
		}

		foreach ( $tokens as $token ) {
			if ( false !== stripos( $body, $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 註記から、引用元本文と突き合わせられる固有の語を取り出す。
	 *
	 * @return array<int, string>
	 */
	function node_ai_fc_extract_evidence_tokens( string $note ): array {
		$tokens = array();

		// 「」『』"" で囲まれた固有名
		if ( preg_match_all( '/[「『"]([^」』"]{2,40})[」』"]/u', $note, $quoted ) ) {
			foreach ( $quoted[1] as $value ) {
				$tokens[] = trim( $value );
			}
		}

		// 価格・スコア（$0.0375 / 34.0% など）
		if ( preg_match_all( '/\$[0-9]+(?:\.[0-9]+)?|[0-9]+(?:\.[0-9]+)?%/u', $note, $numbers ) ) {
			foreach ( $numbers[0] as $value ) {
				$tokens[] = $value;
			}
		}

		// 英数字の固有名（Gemini Advanced / Vertex AI など）
		if ( preg_match_all( '/\b[A-Z][A-Za-z0-9.]{3,}(?:\s[A-Z][A-Za-z0-9.]{2,})?/u', $note, $latin ) ) {
			foreach ( $latin[0] as $value ) {
				$tokens[] = trim( $value );
			}
		}

		$tokens = array_values(
			array_unique(
				array_filter(
					$tokens,
					static function ( string $token ): bool {
						return mb_strlen( $token ) >= 3;
					}
				)
			)
		);

		return array_slice( $tokens, 0, 8 );
	}
}

if ( ! function_exists( 'node_ai_fc_soften_unsupported_denial' ) ) {
	/**
	 * 裏づけの無い「存在しない」という断定を、註記の文面からも取り除く。
	 *
	 * status を下げても註記に「〜というサービスは存在しません」と残ると、編集者は
	 * それを事実として読んでしまう。実測では、実在する Gemini Spark / Google Antigravity を
	 * 「存在しない」と書いていた。確認できたのは「提示資料に記載が無い」ことだけなので、
	 * その通りの文面に置き換える。
	 *
	 * @param string $note 註記。
	 * @return string 置き換え後の註記。
	 */
	function node_ai_fc_soften_unsupported_denial( string $note ): string {
		if ( '' === trim( $note ) ) {
			return $note;
		}

		$denial_patterns = (array) apply_filters(
			'node_ai_fc_denial_sentence_patterns',
			array(
				'存在しません',
				'存在しない',
				'実在しません',
				'実在しない',
				'存在が確認されていません',
				'提供されていません',
				'該当するサービスはありません',
				'というサービスはありません',
				'というプラットフォームはありません',
				'は架空',
				'の誤り',
				'誤情報',
			)
		);

		// 文単位で見て、断定している文だけを差し替える（他の説明は残す）
		$sentences = preg_split( '/(?<=[。．!?！？])/u', $note, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $sentences ) ) {
			return $note;
		}

		$replaced = false;
		$result   = array();

		foreach ( $sentences as $sentence ) {
			$hit = false;

			foreach ( $denial_patterns as $pattern ) {
				if ( '' !== $pattern && false !== mb_strpos( $sentence, (string) $pattern ) ) {
					$hit = true;
					break;
				}
			}

			if ( $hit ) {
				$replaced = true;
				continue;
			}

			$result[] = $sentence;
		}

		if ( ! $replaced ) {
			return $note;
		}

		array_unshift( $result, '提示した資料の範囲では確認できませんでした（存在しないと確認できたわけではありません）。' );

		return trim( implode( '', $result ) );
	}
}

if ( ! function_exists( 'node_ai_fc_normalize_claim' ) ) {
	/**
	 * 1件の判定を検証可能な形へ整える。
	 *
	 * ルール:
	 * - 根拠なしの likely_incorrect は「要確認（uncertain）」へ落とす（根拠不足を誤りと同一視しない）。
	 * - 否定命題は外部根拠がなければ「検証困難（unverifiable）」にする。
	 * - 筆者の主観は事実判定の対象にしない。
	 * - 依存する基礎前提が未確認なら、その主張だけで誤りと断定しない。
	 *
	 * @param array<string, mixed> $claim    主張。
	 * @param array<string, mixed> $context  grounded / premises 等の実行コンテキスト。
	 * @return array<string, mixed>
	 */
	function node_ai_fc_normalize_claim( array $claim, array $context = array() ): array {
		$status = (string) ( $claim['status'] ?? 'uncertain' );
		if ( ! in_array( $status, node_ai_fc_statuses(), true ) ) {
			$status = 'uncertain';
		}

		$claim_type = (string) ( $claim['claim_type'] ?? 'fact' );
		if ( ! in_array( $claim_type, array( 'fact', 'opinion', 'premise' ), true ) ) {
			$claim_type = 'fact';
		}

		$text  = (string) ( $claim['claim'] ?? '' );
		$note  = (string) ( $claim['note'] ?? '' );
		$notes = array();

		// 筆者の感想は客観的事実として扱わない。
		if ( 'opinion' === $claim_type || node_ai_fc_is_subjective( $text ) ) {
			$claim_type = 'opinion';

			if ( in_array( $status, array( 'likely_incorrect', 'correct' ), true ) ) {
				$status  = 'uncertain';
				$notes[] = '筆者の主観表現のため、客観的事実としての真偽判定は行っていません。';
			}
		}

		$has_evidence  = node_ai_fc_claim_has_evidence( $claim );
		$has_official  = node_ai_fc_claim_has_official_evidence( $claim );
		$is_negative   = node_ai_fc_is_negative_claim( $text ) || node_ai_fc_is_negative_claim( $note );
		$premise_ok    = true;

		// 依存する基礎前提が確認できていない場合は、その前提を根拠にできない。
		$depends_on = isset( $claim['depends_on'] ) ? (int) $claim['depends_on'] : -1;
		if ( $depends_on >= 0 ) {
			$premise = (array) ( $context['premises'][ $depends_on ] ?? array() );
			if ( ! empty( $premise ) && 'confirmed' !== (string) ( $premise['status'] ?? '' ) ) {
				$premise_ok = false;
			}
		}

		// 根拠は挙がっているが、引用元の本文にその裏づけが見当たらない場合は断定させない。
		if ( 'likely_incorrect' === $status && $has_evidence && ! node_ai_fc_evidence_supports( $claim, $context ) ) {
			$status  = $is_negative ? 'unverifiable' : 'uncertain';
			$notes[] = '引用元として挙げられたページの本文に、この指摘を裏づける記述が見つからないため判定を下げました。';

			// 追跡できるよう引用自体は残し、「裏づけが取れなかった」ことを記録する
			$claim['evidence_unsupported'] = true;

			$has_evidence = false;
			$has_official = false;
		}

		if ( 'likely_incorrect' === $status && ! $has_evidence ) {
			// 根拠がないのに「誤り」とはしない。否定命題ならさらに慎重に「検証困難」へ。
			$status  = $is_negative ? 'unverifiable' : 'uncertain';
			$notes[] = $is_negative
				? '否定的な断定（未発表・非対応・存在しない等）に対する外部根拠が確認できないため「検証困難」に変更しました。モデルが知らないことは、存在しないことの根拠になりません。'
				: '判定を裏づける外部根拠が確認できないため「要確認」に変更しました。根拠不足のみを理由に不正確とは判定しません。';
		} elseif ( 'likely_incorrect' === $status && ! $premise_ok ) {
			$status  = 'uncertain';
			$notes[] = '前提となる事実が独立に確認できていないため「要確認」に変更しました（未確認の前提から誤りを断定しません）。';
		}

		if ( $is_negative && 'correct' === $status && ! $has_official ) {
			// 否定命題を「正確」と断定するのも同じ危うさがある。
			$status  = 'likely_correct';
			$notes[] = '否定的な主張のため、公式情報での裏づけが取れるまでは断定を避けています。';
		}

		$confidence = (string) ( $claim['confidence'] ?? 'low' );
		if ( ! in_array( $confidence, array( 'high', 'medium', 'low' ), true ) ) {
			$confidence = 'low';
		}

		// 検索（グラウンディング）も公式ページ本文も無い実行では確信度を上げない。
		if ( empty( $context['grounded'] ) && ! $has_evidence && 'high' === $confidence ) {
			$confidence = 'medium';
			$notes[]    = '外部情報を参照できない実行のため、確信度を下げています。';
		}

		// 「存在しない」という断定は、外部の裏づけが取れているときだけ残す。
		// 取れていなければ註記の文面からも取り除く（編集者が事実として読んでしまうため）。
		// 註記の言い回し（丁寧形・体言止め）に依存しないよう、裏づけの有無だけで判断する
		if ( ! ( $has_evidence && node_ai_fc_evidence_supports( $claim, $context ) ) ) {
			$note = node_ai_fc_soften_unsupported_denial( $note );
		}

		if ( ! empty( $notes ) ) {
			$note = trim( implode( ' ', $notes ) . ( '' !== $note ? ' / ' . $note : '' ) );
		}

		$claim['claim']      = $text;
		$claim['status']     = $status;
		$claim['confidence'] = $confidence;
		$claim['note']       = $note;
		$claim['claim_type'] = $claim_type;
		$claim['depends_on'] = $depends_on;
		$claim['adjusted']   = ! empty( $notes );

		return $claim;
	}
}

if ( ! function_exists( 'node_ai_fc_compute_overall_risk' ) ) {
	/**
	 * 記事全体のリスクをサーバー側で算出する（モデルの申告をそのまま採用しない）。
	 *
	 * 「高」は、根拠つきの重大な事実誤認が複数あるか、公式一次情報と明確に矛盾する場合に限る。
	 * 検証困難が多い・外部情報が取れなかった・前提がひとつ疑わしい、だけでは「高」にしない。
	 *
	 * @param array<int, array<string, mixed>> $claims  正規化済みの主張。
	 * @param array<string, mixed>             $context 実行コンテキスト。
	 */
	function node_ai_fc_compute_overall_risk( array $claims, array $context = array() ): string {
		$evidenced_incorrect = 0;
		$official_conflict   = 0;
		$plain_incorrect     = 0;
		$uncertain           = 0;
		$counted_premises    = array();

		foreach ( $claims as $claim ) {
			if ( ! is_array( $claim ) ) {
				continue;
			}
			if ( 'opinion' === (string) ( $claim['claim_type'] ?? 'fact' ) ) {
				continue; // 筆者の感想はリスクに数えない。
			}

			$status = (string) ( $claim['status'] ?? 'uncertain' );

			if ( 'uncertain' === $status ) {
				$uncertain++;
				continue;
			}

			if ( 'likely_incorrect' !== $status ) {
				continue;
			}

			// 同じ基礎前提に依存する指摘は、まとめて1件として数える（カスケードの防止）。
			$depends_on = isset( $claim['depends_on'] ) ? (int) $claim['depends_on'] : -1;
			if ( $depends_on >= 0 ) {
				if ( isset( $counted_premises[ $depends_on ] ) ) {
					continue;
				}
				$counted_premises[ $depends_on ] = true;
			}

			$plain_incorrect++;

			if ( node_ai_fc_claim_has_evidence( $claim ) ) {
				$evidenced_incorrect++;
			}
			if ( node_ai_fc_claim_has_official_evidence( $claim ) ) {
				$official_conflict++;
			}
		}

		$risk = 'low';

		if ( $official_conflict >= 1 || $evidenced_incorrect >= 2 ) {
			$risk = 'high';
		} elseif ( $plain_incorrect >= 1 || $uncertain >= 3 ) {
			$risk = 'medium';
		}

		// 外部根拠をひとつも参照できていない実行では「高」を付けない
		// （検証できなかったこと自体は、記事が危険であることの根拠ではない）。
		if ( 'high' === $risk && empty( $context['grounded'] ) && 0 === $official_conflict && 0 === $evidenced_incorrect ) {
			$risk = 'medium';
		}

		return $risk;
	}
}

if ( ! function_exists( 'node_ai_fc_normalize_payload' ) ) {
	/**
	 * 保存前のペイロード全体を正規化する（AJAX・cron 共通の最終ゲート）。
	 *
	 * @param array<string, mixed> $payload 保存候補のペイロード。
	 * @param array<string, mixed> $context 実行コンテキスト（grounded / premises など）。
	 * @return array<string, mixed>
	 */
	function node_ai_fc_normalize_payload( array $payload, array $context = array() ): array {
		$context = wp_parse_args(
			$context,
			array(
				'grounded' => ! empty( $payload['grounded'] ),
				'premises' => (array) ( $payload['premises'] ?? array() ),
			)
		);

		$claims     = array();
		$adjustments = 0;

		foreach ( (array) ( $payload['claims'] ?? array() ) as $claim ) {
			if ( ! is_array( $claim ) ) {
				continue;
			}

			$normalized = node_ai_fc_normalize_claim( $claim, $context );
			if ( ! empty( $normalized['adjusted'] ) ) {
				$adjustments++;
			}

			$claims[] = $normalized;
		}

		$payload['claims']       = $claims;
		$payload['overall_risk'] = node_ai_fc_compute_overall_risk( $claims, $context );
		$payload['adjustments']  = $adjustments;

		return $payload;
	}
}
