<?php
/**
 * plugins-embedded/node-connect/includes/class-x-poster.php の自動テスト。
 *
 * テンプレート置換・イベントゲート・二重投稿防止・除外メタ・再送分類
 * （4xx打ち切り / 5xx・通信エラー再送）・認証情報サニタイズをカバーする。
 *
 * @package Node_Connect
 */

require_once dirname( __DIR__ ) . '/plugins-embedded/node-connect/node-connect.php';
require_once dirname( __DIR__ ) . '/plugins-embedded/node-connect/admin/settings-page.php';

class Node_Connect_X_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		// $wp_filter は各テスト後に巻き戻るため毎回登録し直す。
		$plugin = Node_Connect::instance();
		Node_Connect_Event_Bus::instance()->register();
		Node_Connect_X_Poster::instance()->register();
		add_action( 'transition_post_status', [ $plugin, 'on_transition_post_status' ], 10, 3 );
		add_action( 'post_updated', [ $plugin, 'on_post_updated' ], 10, 3 );

		update_option( Node_Connect_X_Poster::OPTION_ENABLED, '1' );
		update_option( Node_Connect_X_Poster::OPTION_API_KEY, 'test-api-key' );
		update_option( Node_Connect_X_Poster::OPTION_API_SECRET, 'test-api-secret' );
		update_option( Node_Connect_X_Poster::OPTION_ACCESS_TOKEN, 'test-access-token' );
		update_option( Node_Connect_X_Poster::OPTION_ACCESS_TOKEN_SECRET, 'test-token-secret' );
		// Webhook側は無効化してXの挙動だけを見る。
		update_option( Node_Connect_Event_Bus::OPTION_ENABLED, '' );

		_set_cron_array( [] );
	}

	/**
	 * @return array<int, array> node_connect_deliver_x キューの引数リスト。
	 */
	private function get_queued_x_deliveries(): array {
		$queued = [];
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( (array) $events as $hook => $entries ) {
				if ( Node_Connect_X_Poster::CRON_HOOK !== $hook ) {
					continue;
				}
				foreach ( $entries as $entry ) {
					$queued[] = $entry['args'];
				}
			}
		}
		return $queued;
	}

	private function mock_response( int $code ): void {
		add_filter(
			'pre_http_request',
			static fn() => [
				'response' => [ 'code' => $code, 'message' => '' ],
				'headers'  => [],
				'body'     => '{}',
			]
		);
	}

	// ---- テンプレート置換 ----

	public function test_render_template_substitutes_placeholders(): void {
		$cat_id  = self::factory()->category->create( [ 'name' => 'ガジェット' ] );
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_title'    => 'テスト記事',
				'post_excerpt'  => '抜粋テキスト',
				'post_category' => [ $cat_id ],
			]
		);

		$text = Node_Connect_X_Poster::render_template( '{{title}} / {{summary}} / {{category}} / {{url}}', get_post( $post_id ) );

		$this->assertStringContainsString( 'テスト記事', $text );
		$this->assertStringContainsString( '抜粋テキスト', $text );
		$this->assertStringContainsString( 'ガジェット', $text );
		$this->assertStringContainsString( get_permalink( $post_id ), $text );
	}

	public function test_render_template_prefers_ai_summary(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_excerpt' => '抜粋テキスト',
			]
		);
		update_post_meta( $post_id, '_node_ai_summary', 'AI要約テキスト' );

		$text = Node_Connect_X_Poster::render_template( '{{summary}}', get_post( $post_id ) );

		$this->assertStringContainsString( 'AI要約テキスト', $text );
		$this->assertStringNotContainsString( '抜粋テキスト', $text );
	}

	public function test_render_template_appends_hashtags_from_post_tags(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish', 'post_title' => 'T' ] );
		wp_set_post_tags( $post_id, [ '遊戯王', 'ガジェット レビュー' ] );

		$text = Node_Connect_X_Poster::render_template( "{{title}}\n{{tags}}", get_post( $post_id ) );

		$this->assertStringContainsString( '#遊戯王', $text );
		$this->assertStringContainsString( '#ガジェットレビュー', $text, 'タグ名の空白は除去してハッシュタグ化' );
	}

	public function test_render_template_drops_tags_over_budget(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_post_tags( $post_id, [ str_repeat( 'あ', 30 ), '短いタグ' ] );

		// 残り予算をほぼ使い切るテンプレート（日本語135文字 = 重み270）。
		$template = str_repeat( 'あ', 135 ) . '{{tags}}';
		$text     = Node_Connect_X_Poster::render_template( $template, get_post( $post_id ) );

		$this->assertStringNotContainsString( '#' . str_repeat( 'あ', 30 ), $text, '収まらないタグは落とす' );
		$this->assertLessThanOrEqual( Node_Connect_X_Poster::X_WEIGHTED_LIMIT, Node_Connect_X_Poster::weighted_length( $text ) );
	}

	public function test_default_template_shape_and_fits_x_limit(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'テンプレ確認記事',
			]
		);
		wp_set_post_tags( $post_id, [ 'タグA', 'タグB' ] );

		$text = Node_Connect_X_Poster::render_template( Node_Connect_X_Poster::DEFAULT_TEMPLATE, get_post( $post_id ) );

		$this->assertSame( "ブログ記事を投稿しました\n「テンプレ確認記事」\n" . get_permalink( $post_id ) . "\n#タグA #タグB", $text, '固定テンプレの出力形' );
		$this->assertLessThanOrEqual( Node_Connect_X_Poster::X_WEIGHTED_LIMIT, Node_Connect_X_Poster::weighted_length( $text ) );
	}

	public function test_summary_is_trimmed_to_fit_x_limit(): void {
		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_title'   => '長文テスト記事',
				'post_excerpt' => str_repeat( '要約が長い。', 60 ),
			]
		);
		wp_set_post_tags( $post_id, [ 'タグA', 'タグB' ] );

		$text = Node_Connect_X_Poster::render_template( "{{title}}\n{{summary}}\n{{url}}\n{{tags}}", get_post( $post_id ) );

		$this->assertLessThanOrEqual( Node_Connect_X_Poster::X_WEIGHTED_LIMIT, Node_Connect_X_Poster::weighted_length( $text ), '全体がXの上限に収まる' );
		$this->assertStringContainsString( '…', $text, '長い要約は切り詰められる' );
		$this->assertStringContainsString( '#タグA', $text, '要約切り詰め後もタグは入る' );
	}

	public function test_weighted_length_counts_url_as_23(): void {
		$this->assertSame( 23, Node_Connect_X_Poster::weighted_length( 'https://example.com/very/long/path/that/is/definitely/longer' ) );
		$this->assertSame( 4, Node_Connect_X_Poster::weighted_length( 'abcd' ) );
		$this->assertSame( 6, Node_Connect_X_Poster::weighted_length( 'あいう' ) );
	}

	// ---- イベントゲート ----

	public function test_new_publish_queues_x_delivery(): void {
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$queued = $this->get_queued_x_deliveries();
		$this->assertCount( 1, $queued );
		$this->assertSame( 1, $queued[0][1], '初回試行としてキューされる' );
	}

	public function test_update_does_not_queue_x_delivery(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		_set_cron_array( [] );

		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => '更新タイトル',
			]
		);

		$this->assertCount( 0, $this->get_queued_x_deliveries(), '公開済み記事の更新では自動投稿しない' );
	}

	public function test_disabled_or_missing_credentials_do_not_queue(): void {
		update_option( Node_Connect_X_Poster::OPTION_ENABLED, '' );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->assertCount( 0, $this->get_queued_x_deliveries(), '無効時はキューされない' );

		update_option( Node_Connect_X_Poster::OPTION_ENABLED, '1' );
		update_option( Node_Connect_X_Poster::OPTION_API_KEY, '' );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->assertCount( 0, $this->get_queued_x_deliveries(), '認証情報不足時はキューされない' );
	}

	public function test_already_posted_meta_prevents_queue(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		update_post_meta( $post_id, Node_Connect_X_Poster::POSTED_META, time() );
		_set_cron_array( [] );

		wp_publish_post( $post_id );

		$this->assertCount( 0, $this->get_queued_x_deliveries(), '投稿済み記事は再公開してもキューされない' );
	}

	// ---- 送信・再送・メタ ----

	public function test_deliver_success_marks_posted_and_logs(): void {
		$this->mock_response( 201 );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		_set_cron_array( [] );

		Node_Connect_X_Poster::deliver( $post_id, 1 );

		$this->assertNotSame( '', (string) get_post_meta( $post_id, Node_Connect_X_Poster::POSTED_META, true ) );
		$log = Node_Connect_Delivery_Log::get();
		$this->assertSame( 'x_post', $log[0]['event'] );
		$this->assertTrue( $log[0]['ok'] );
		$this->assertCount( 0, $this->get_queued_x_deliveries(), '成功時は再送しない' );
	}

	public function test_deliver_4xx_does_not_retry(): void {
		$this->mock_response( 403 );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		_set_cron_array( [] );

		Node_Connect_X_Poster::deliver( $post_id, 1 );

		$this->assertSame( '', (string) get_post_meta( $post_id, Node_Connect_X_Poster::POSTED_META, true ) );
		$this->assertCount( 0, $this->get_queued_x_deliveries(), '4xxは再送しない' );
		$this->assertFalse( Node_Connect_Delivery_Log::get()[0]['ok'] );
	}

	public function test_deliver_5xx_retries_up_to_three_attempts(): void {
		$this->mock_response( 503 );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		_set_cron_array( [] );

		Node_Connect_X_Poster::deliver( $post_id, 1 );
		$queued = $this->get_queued_x_deliveries();
		$this->assertCount( 1, $queued );
		$this->assertSame( 2, $queued[0][1] );

		_set_cron_array( [] );
		Node_Connect_X_Poster::deliver( $post_id, 3 );
		$this->assertCount( 0, $this->get_queued_x_deliveries(), '3回目失敗で打ち切り' );
	}

	public function test_deliver_respects_skip_meta(): void {
		$this->mock_response( 201 );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, Node_Connect_X_Poster::SKIP_META, '1' );

		Node_Connect_X_Poster::deliver( $post_id, 1 );

		$this->assertSame( '', (string) get_post_meta( $post_id, Node_Connect_X_Poster::POSTED_META, true ), '除外チェック済み記事は投稿しない' );
		$this->assertCount( 0, Node_Connect_Delivery_Log::get() );
	}

	public function test_deliver_skips_unpublished_post(): void {
		$this->mock_response( 201 );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);
		_set_cron_array( [] );

		Node_Connect_X_Poster::deliver( $post_id, 1 );

		$this->assertSame( '', (string) get_post_meta( $post_id, Node_Connect_X_Poster::POSTED_META, true ), '送信時点で非公開なら投稿しない' );
	}

	// ---- OAuthヘッダー ----

	public function test_oauth_header_shape(): void {
		$header = Node_Connect_X_Poster::build_oauth_header(
			[
				'api_key'             => 'ck',
				'api_secret'          => 'cs',
				'access_token'        => 'at',
				'access_token_secret' => 'ats',
			],
			'POST',
			'https://api.twitter.com/2/tweets'
		);

		$this->assertStringStartsWith( 'OAuth ', $header );
		$this->assertStringContainsString( 'oauth_consumer_key="ck"', $header );
		$this->assertStringContainsString( 'oauth_token="at"', $header );
		$this->assertStringContainsString( 'oauth_signature_method="HMAC-SHA1"', $header );
		$this->assertStringContainsString( 'oauth_signature="', $header );
	}

	// ---- 分類 ----

	public function test_classify_response(): void {
		$ok = Node_Connect_X_Poster::classify_response(
			[
				'response' => [ 'code' => 201, 'message' => '' ],
				'headers'  => [],
				'body'     => '{}',
			]
		);
		$this->assertTrue( $ok['ok'] );

		$client_error = Node_Connect_X_Poster::classify_response(
			[
				'response' => [ 'code' => 429, 'message' => '' ],
				'headers'  => [],
				'body'     => '{}',
			]
		);
		$this->assertFalse( $client_error['ok'] );
		$this->assertFalse( $client_error['retryable'] );

		$server_error = Node_Connect_X_Poster::classify_response(
			[
				'response' => [ 'code' => 500, 'message' => '' ],
				'headers'  => [],
				'body'     => '{}',
			]
		);
		$this->assertTrue( $server_error['retryable'] );

		$wp_error = Node_Connect_X_Poster::classify_response( new WP_Error( 'timeout', 'timeout' ) );
		$this->assertFalse( $wp_error['ok'] );
		$this->assertTrue( $wp_error['retryable'] );
	}

	// ---- 認証情報サニタイズ（設定画面） ----

	public function test_credential_sanitizer_keeps_existing_when_blank(): void {
		$sanitized = node_connect_sanitize_x_credential( '', Node_Connect_X_Poster::OPTION_API_KEY );
		$this->assertSame( 'test-api-key', $sanitized, '空欄なら保存済みの値を維持' );

		$sanitized = node_connect_sanitize_x_credential( 'new-key', Node_Connect_X_Poster::OPTION_API_KEY );
		$this->assertSame( 'new-key', $sanitized );
	}

	public function test_credential_sanitizer_clears_all_when_flagged(): void {
		$_POST['node_connect_x_clear'] = '1';
		$sanitized                     = node_connect_sanitize_x_credential( '', Node_Connect_X_Poster::OPTION_API_KEY );
		unset( $_POST['node_connect_x_clear'] );

		$this->assertSame( '', $sanitized, 'クリア指定時は空にする' );
	}
}
