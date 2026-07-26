<?php
/**
 * Gemini API 通信クラス
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Node_Gemini_API
 */
class Node_Gemini_API {

    /**
     * API Key
     */
    private string $api_key;

    /**
     * API URL Base
     */
    private string $api_url_base = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct( ?int $user_id = null ) {
        $user_id = $user_id ?? get_current_user_id();

        if ( function_exists( 'node_get_user_gemini_api_key' ) && $user_id > 0 ) {
            $this->api_key = node_get_user_gemini_api_key( $user_id );
        } else {
            $this->api_key = $user_id > 0
                ? (string) get_user_meta( $user_id, 'node_gemini_api_key', true )
                : '';
        }

        // サイト共通キー（AI設定）をフォールバック。個人キー未登録のライターに適用
        if ( empty( $this->api_key ) ) {
            $this->api_key = trim( (string) get_option( 'node_ai_gemini_api_key', '' ) );
        }

        // 開発環境のみ: wp-config.php の GEMINI_API_KEY 定数をフォールバック
        if ( empty( $this->api_key ) && defined( 'GEMINI_API_KEY' ) && GEMINI_API_KEY ) {
            $this->api_key = (string) GEMINI_API_KEY;
        }
    }

    /**
     * 高度なコンテンツ生成 (システム指示対応)
     */
    public function generate_content(string $prompt, array $options = []): string|array|WP_Error {
        if (empty($this->api_key)) {
            return new WP_Error(
                'missing_api_key',
                'Gemini API キーが設定されていません。ユーザー → プロフィール の「Gemini API（個人設定）」から、あなた専用のキーを登録してください。'
            );
        }

        $options = wp_parse_args($options, [
            'system_instruction' => 'あなたはプロのテクニカルライターであり、デザイナーです。ユーザーに驚きと知覚的な喜びを与える文章を作成してください。',
            'max_tokens'  => 400,
            'temperature' => 0.7,
            'response_mime_type' => 'text/plain',
            'google_search_grounding' => false,
            'return_metadata' => false,
            'timeout' => 45,
        ]);

        $user_id = get_current_user_id();
        if ( function_exists( 'node_get_user_gemini_model' ) && $user_id > 0 ) {
            $model_name = node_get_user_gemini_model( $user_id );
        } else {
            $model_name = function_exists( 'node_get_default_gemini_model' )
                ? node_get_default_gemini_model()
                : 'gemini-3.5-flash';
        }
        
        // `<モデルID>@high` / `@low` は思考量（thinkingLevel）つき仮想ID。実IDと思考量に分解する
        $thinking_level = '';
        if ( preg_match( '/^(.+)@(high|low)$/i', $model_name, $m ) ) {
            $model_name     = $m[1];
            $thinking_level = strtolower( $m[2] );
        }

        // Google 検索グラウンディングは通常生成とは別枠で、Gemini 3系では
        // 通常生成が通るのにグラウンディングだけ 429 になることがある。
        // 枠を一緒に扱うと、検索なしなら使えるモデルまで止めてしまうため分けて記録する
        $quota_key = ! empty( $options['google_search_grounding'] )
            ? $model_name . '#grounding'
            : $model_name;

        // 直近の 429 でこのモデルがまだ利用不可なら、API を叩かずに即座に失敗させる。
        // 叩いても失敗するだけでレート枠と待ち時間を消費するため
        if ( function_exists( 'node_gemini_get_quota_block' ) ) {
            $block = node_gemini_get_quota_block( $user_id, $quota_key );

            if ( ! empty( $block['blocked'] ) ) {
                return new WP_Error(
                    'gemini_quota_exceeded',
                    sprintf(
                        '%s は利用上限に達しているため実行を中止しました（%s頃に再開見込み）。別のモデルを選ぶか、時間をおいてお試しください。',
                        $model_name,
                        wp_date( 'n月j日 H:i', (int) $block['until'] )
                    ),
                    array( 'status' => 429 )
                );
            }
        }

        $url = $this->api_url_base . $model_name . ':generateContent?key=' . $this->api_key;

        // 画像入力（マルチモーダル）。指定時は画像パートを先に置く
        $parts = [];
        if ( ! empty( $options['image'] ) && is_array( $options['image'] ) ) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => (string) $options['image']['mime'],
                    'data'      => (string) $options['image']['data'],
                ],
            ];
        }
        $parts[] = ['text' => $prompt];

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => $parts]
            ],
            'system_instruction' => [
                'parts' => [['text' => $options['system_instruction']]]
            ],
            'generationConfig' => [
                'maxOutputTokens'  => $options['max_tokens'],
                'temperature'      => $options['temperature'],
                'responseMimeType' => $options['response_mime_type'],
            ]
        ];

        if ( '' !== $thinking_level ) {
            $payload['generationConfig']['thinkingConfig'] = array( 'thinkingLevel' => $thinking_level );
        }

        if ( ! empty( $options['google_search_grounding'] ) ) {
            $payload['tools'] = array(
                array( 'google_search' => new \stdClass() ),
            );
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($payload),
            'timeout' => (int) $options['timeout'],
        ]);

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            if (strpos($err_msg, 'timed out') !== false) {
                return new WP_Error('gemini_timeout', 'Gemini APIからの応答がタイムアウトしました。しばらく待ってから再度お試しください。', ['status' => 504]);
            }
            return new WP_Error('gemini_request_failed', 'Gemini APIへの接続に失敗しました。詳細: ' . $err_msg, ['status' => 502]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        // HTTP エラー / API エラー応答（429 の利用上限超過を含む）を分かりやすく返す。
        if (200 !== $status || (is_array($data) && isset($data['error']))) {
            if ( function_exists( 'node_gemini_record_quota_error' ) ) {
                node_gemini_record_quota_error( $user_id, $quota_key, $status, $data );
            }

            // 提供終了モデル（404）は以後プルダウンから外す
            if ( function_exists( 'node_gemini_maybe_record_retired_model' ) ) {
                node_gemini_maybe_record_retired_model( $model_name, $status, $data );
            }

            $message = function_exists('node_gemini_format_api_error')
                ? node_gemini_format_api_error($status, $data, $body)
                : ('Gemini API エラー (HTTP ' . $status . ')');
            $code = (429 === $status) ? 'gemini_quota_exceeded' : ((503 === $status) ? 'gemini_model_unavailable' : 'gemini_api_error');
            return new WP_Error($code, $message, ['status' => $status]);
        }

        $parts         = $data['candidates'][0]['content']['parts'] ?? null;
        $finish_reason = (string) ( $data['candidates'][0]['finishReason'] ?? '' );

        // 出力上限で打ち切られた応答は、途中まで本文が返っていても使えない
        // （JSON が閉じずに壊れるため）。部分応答を成功として返さず、原因が分かる形で失敗させる。
        // 思考モデルは maxOutputTokens を思考にも消費するため起きやすい
        if ( 'MAX_TOKENS' === $finish_reason ) {
            return new WP_Error(
                'gemini_max_tokens',
                'Gemini の出力が上限に達し、応答が途中で切れました。思考量を Low にするか、記事を短くしてお試しください。',
                ['status' => 500]
            );
        }

        // 思考モデル（Gemini 3系）は先頭に thought パートを返すことがあり、
        // Google Search グラウンディング併用時は本文が複数パートに分割されることもある。
        // parts[0] 決め打ちだと思考文や断片だけを拾い JSON 解析に失敗するため、
        // thought 以外の text パートをすべて連結する
        if ( is_array( $parts ) ) {
            $chunks = array();

            foreach ( $parts as $part ) {
                if ( ! is_array( $part ) || ! isset( $part['text'] ) || ! empty( $part['thought'] ) ) {
                    continue;
                }
                $chunks[] = (string) $part['text'];
            }

            $text = trim( implode( '', $chunks ) );

            if ( '' !== $text ) {
                if ( function_exists( 'node_gemini_record_usage' ) ) {
                    $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);
                    node_gemini_record_usage( $user_id, $model_name, $tokens, 1 );
                }

                if ( ! empty( $options['return_metadata'] ) ) {
                    return array(
                        'text'      => $text,
                        'grounding' => $data['candidates'][0]['groundingMetadata'] ?? array(),
                    );
                }

                return $text;
            }
        }

        return new WP_Error(
            'api_error',
            function_exists('node_gemini_format_api_error')
                ? node_gemini_format_api_error($status, $data, $body)
                : 'Gemini API から有効なレスポンスが得られませんでした。'
        );
    }

    /**
     * デザイナー品質の要約を生成する
     */
    public function generate_summary(string $content, string $custom_prompt = '', array $options = []): string|WP_Error {
        $options = wp_parse_args($options, [
            'max_lines' => 3,
            'max_chars' => 120
        ]);

        $system_prompt = "あなたは先進的な技術ブログ 'Luminous Core' の編集長です。
提供された記事を解析し、以下の JSON フォーマットでレスポンスしてください。
・必ず、要約は {$options['max_lines']} 行以内、かつ {$options['max_chars']} 文字以内厳守で作成してください。
・Markdownのコードブロック（```json ... ```）は絶対に使わず、生の中括弧 { } から始まる純粋なJSON文字列のみを出力してください。
・要約内に改行を含めないでください。
{
  \"summary\": \"読者の好奇心を刺激する、情緒的で洗練された要約。\",
  \"tone_color\": \"記事のトーンを表す色（hexコード）。\",
  \"vibe_keywords\": [\"キーワード1\", \"キーワード2\"]
}";

        $prompt = "以下の記事を解析し、最高の要約を生成してください：\n\n" . mb_substr($content, 0, 5000);

        if ( ! empty( $custom_prompt ) ) {
            $prompt .= "\n\n【追加の指示（プロンプト）】\n" . $custom_prompt;
        }

        $result = $this->generate_content($prompt, [
            'system_instruction' => $system_prompt,
            'response_mime_type' => 'application/json',
            'temperature' => 0.4,
            'max_tokens' => 2048
        ]);
        
        return $result;
    }

    /**
     * 記事本文のファクトチェック（編集者向け・要手動確認）
     */
    public function fact_check( string $content, string $title = '' ): array|WP_Error {
        $guidelines_block = '';
        $guidelines_used  = false;

        if ( function_exists( 'node_ai_fetch_guidelines' ) ) {
            $guidelines = node_ai_fetch_guidelines();
            if ( is_string( $guidelines ) && '' !== $guidelines ) {
                $guidelines_used  = true;
                $guidelines_block = "\n\n【Luminous Core 運営ガイドライン（Google ドキュメント）】\n" . $guidelines;
            }
        }

        $system_prompt = 'あなたはテクニカルブログ「Luminous Core」のファクトチェック補助アシスタントです。
Google Search の検索結果を参照し、記事内の事実関係に関わる主張を抽出して検証してください。
これは確認箇所の抽出支援であり、真偽の最終判定はしないことに留意してください。最終判断は必ず人間の編集者が行います。

【創作・二次創作の扱い】
記事に「フィクション」「二次創作」「架空」等の断り書きがある場合、登場人物の発言・作中設定は
現実の主張ではなく虚構表現です。これを現実の事実誤りとして断定しないでください。
ただし、現実の事実として読まれると誤解・炎上・法的リスクを招く記述（差別的主張、
科学的に否定された言説、領土・歴史に関する断定など）は、作中表現であっても見逃さず、
status を uncertain とし、note の冒頭に「作中の虚構表現。ただし事実として読まれると問題」と明記してください。
あわせて、提供される Luminous Core 運営ガイドラインに照らし、コンプライアンス違反の可能性がある記述も指摘してください。
ガイドライン違反は status を uncertain または likely_incorrect とし、note に該当ルールを簡潔に記載してください。
以下の JSON 形式のみで回答してください。
・Markdown のコードブロック（```json ... ```）は絶対に使わず、生の中括弧 { } から始まる純粋な JSON のみを出力してください。
・推測で断定せず、不確実な場合は status を uncertain または unverifiable にしてください。
・最大 8 件の主張に絞ってください。
・note には検索結果・ガイドラインに基づく根拠・確認方法・注意点を簡潔に書いてください。

{
  "summary": "全体所見（2〜3文、日本語）",
  "overall_risk": "low または medium または high",
  "claims": [
    {
      "claim": "記事中の主張（原文に近い形）",
      "status": "correct / likely_correct / uncertain / likely_incorrect / unverifiable のいずれか。correct=信頼できる情報源で裏付けが取れた、likely_correct=概ね正しいが出典未確認や細部に幅がある、uncertain=要確認、likely_incorrect=誤りの可能性が高い、unverifiable=検証できない",
      "confidence": "high / medium / low のいずれか",
      "note": "根拠・補足（日本語）"
    }
  ]
}' . $guidelines_block;

        $prompt = '以下の記事をファクトチェックしてください。可能な限り Google Search の情報を参照してください。';
        if ( ! empty( $title ) ) {
            $prompt .= "\n\n【タイトル】\n" . $title;
        }
        $prompt .= "\n\n【本文】\n" . mb_substr( $content, 0, 8000 );

        $options = array(
            'system_instruction'        => $system_prompt,
            'response_mime_type'      => 'text/plain',
            'temperature'             => 0.2,
            // 思考モデルは maxOutputTokens を思考にも消費するため、
            // JSON が途中で切れないよう長めに確保する
            'max_tokens'              => 8192,
            'google_search_grounding' => true,
            'return_metadata'         => true,
            'timeout'                 => 60,
        );

        // 実行に使われるモデル（エラー文言と検索可否の判定に使う）
        $model_used = function_exists( 'node_get_user_gemini_model' ) && get_current_user_id() > 0
            ? node_get_user_gemini_model( get_current_user_id() )
            : ( function_exists( 'node_get_default_gemini_model' ) ? node_get_default_gemini_model() : '' );
        $model_used = function_exists( 'node_split_gemini_model' )
            ? node_split_gemini_model( $model_used )['model']
            : $model_used;

        $result = $this->generate_content( $prompt, $options );

        // 検索併用の枠が切れているとき、検索なしで実行すると
        // 最新情報を裏取りできないまま「成功」した結果が出てしまう。
        // 黙って精度を落とさないため、フォールバックはせず中止して理由を伝える
        if ( is_wp_error( $result ) && 'gemini_quota_exceeded' === $result->get_error_code() ) {
            return new WP_Error(
                'gemini_grounding_unavailable',
                sprintf(
                    '%s は Google 検索併用の利用枠に達したため、ファクトチェックを中止しました。検索なしで実行すると最新情報を裏取りできないため、あえて実行していません。時間をおくか、別のモデルでお試しください。',
                    $model_used
                ),
                array( 'status' => 429 )
            );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'text'            => (string) ( $result['text'] ?? '' ),
            'grounding'       => is_array( $result['grounding'] ?? null ) ? $result['grounding'] : array(),
            'guidelines_used' => $guidelines_used,
        );
    }

    /**
     * 画像から代替テキスト（alt）の案を生成する。
     *
     * 画像の「出典」は推測させない。Gemini のグラウンディングは逆画像検索ではなく、
     * 画像内の文字から話題を検索するだけなので、自作画像に他社の記事URLを
     * 出典として提示してしまう（実測で確認済み）。
     *
     * @param string $path 画像のローカルパス。
     * @return array{alt_suggestion: string}|WP_Error
     */
    public function analyze_image( string $path ) {
        if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
            return new WP_Error( 'image_unreadable', '画像ファイルを読み込めませんでした。' );
        }

        $mime = (string) ( wp_check_filetype( $path )['type'] ?? '' );
        if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
            return new WP_Error( 'image_unsupported', '対応していない画像形式です。' );
        }

        $system_prompt = 'あなたは日本語メディアの編集者です。渡された画像の代替テキスト（alt）を作成します。
画像の「出典」「引用元」「配信元」は絶対に推測しないでください（画像の見た目からは特定できません）。
以下の JSON 形式のみで回答してください（コードブロック禁止）。

{ "alt_suggestion": "画像の内容を説明する日本語の代替テキスト（80文字以内、句点で終える）" }

読み上げ環境の利用者に画像の情報が伝わるよう、写っているものと画像内の文字を簡潔にまとめてください。
「画像」「写真」といった語での説明の始まりは避け、内容そのものから書き始めてください。';

        $result = $this->generate_content(
            'この画像の代替テキストを作成してください。',
            array(
                'system_instruction' => $system_prompt,
                'response_mime_type' => 'application/json',
                'temperature'        => 0.1,
                'max_tokens'         => 2048,
                'timeout'            => 60,
                'image'              => array(
                    'mime' => $mime,
                    'data' => base64_encode( (string) file_get_contents( $path ) ),
                ),
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data = function_exists( 'node_ai_parse_json_response' )
            ? node_ai_parse_json_response( is_array( $result ) ? (string) ( $result['text'] ?? '' ) : (string) $result )
            : null;

        if ( null === $data || ! isset( $data['alt_suggestion'] ) ) {
            return new WP_Error( 'image_parse_failed', '代替テキストの生成結果を解析できませんでした。' );
        }

        return array( 'alt_suggestion' => (string) $data['alt_suggestion'] );
    }

    /**
     * AIによる読了目安時間の推定
     */
    public function generate_reading_time_estimate(string $content): string|WP_Error {
        $prompt = "以下の記事の読了目安時間を推定し、「〇分〇秒」という形式で回答してください。日本語として自然な読書速度（分速400〜600文字程度）を基準にしつつ、内容の難易度や構成（コード、リスト、画像等）も加味して人間が読み終えるのにかかる時間を算出してください。解説は不要です。結果の数値のみを返してください。\n\n" . mb_substr($content, 0, 4000);
        return $this->generate_content($prompt, [
            'max_tokens'  => 10,
            'temperature' => 0.1
        ]);
    }
}
