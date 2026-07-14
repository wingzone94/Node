<?php
/**
 * node-series.php の自動テスト。
 *
 * @package Node_Series
 */

class Node_Series_Test extends WP_UnitTestCase {

	private function create_series_term( string $name ): int {
		$term = self::factory()->term->create( [ 'taxonomy' => 'node_series', 'name' => $name ] );
		return (int) $term;
	}

	private function create_post_in_series( int $term_id, ?int $order = null, string $status = 'publish' ): int {
		$post_id = self::factory()->post->create( [ 'post_status' => $status ] );
		wp_set_object_terms( $post_id, [ $term_id ], 'node_series' );

		if ( null !== $order ) {
			update_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, $order );
		}

		return $post_id;
	}

	private function get_order_constraints( int $term_id, int $exclude_post_id ): array {
		$method = new ReflectionMethod( Node_Series::class, 'get_series_order_constraints' );
		$method->setAccessible( true );
		return $method->invoke( Node_Series::instance(), $term_id, $exclude_post_id );
	}

	/**
	 * save_order_meta_box() は $_POST を直接読むため、毎テスト後にリセットする。
	 */
	public function tear_down() {
		$_POST = [];
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * 管理者として振る舞う（F-3の current_user_can ガードを通過するため）。
	 * factoryデータはテストごとにロールバックされるため毎回作成する。
	 */
	private function act_as_admin(): int {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		return $admin_id;
	}

	/**
	 * メタボックスPOSTを組み立てる（nonceは現在ユーザーに紐づくため、ユーザー設定後に呼ぶこと）。
	 */
	private function build_order_meta_box_post( ?int $term_id, $order ): void {
		$_POST = [
			'node_series_order_nonce'   => wp_create_nonce( 'node_series_save_order' ),
			'node_series_term_id'       => $term_id ?? '',
			'node_series_order'         => $order ?? '',
			'node_series_color_override' => '',
		];
	}

	private function submit_order_meta_box( int $post_id, ?int $term_id, $order ): void {
		$this->act_as_admin();
		$this->build_order_meta_box_post( $term_id, $order );

		Node_Series::instance()->save_order_meta_box( $post_id );
	}

	// --- シリーズterm削除時の後片付け -----------------------------------------

	public function test_term_deletion_removes_order_meta_but_keeps_color_override(): void {
		$term_id  = $this->create_series_term( '削除テスト' );
		$post_id  = $this->create_post_in_series( $term_id, 1 );
		update_post_meta( $post_id, NODE_SERIES_COLOR_OVERRIDE_META_KEY, '#123456' );

		wp_delete_term( $term_id, 'node_series' );

		$this->assertSame( '', get_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, true ) );
		$this->assertSame( '#123456', get_post_meta( $post_id, NODE_SERIES_COLOR_OVERRIDE_META_KEY, true ) );
		$this->assertSame( [], wp_get_post_terms( $post_id, 'node_series', [ 'fields' => 'ids' ] ) );
	}

	public function test_term_deletion_cleans_up_all_member_posts(): void {
		$term_id = $this->create_series_term( '複数記事の削除テスト' );
		$post_a  = $this->create_post_in_series( $term_id, 1 );
		$post_b  = $this->create_post_in_series( $term_id, 2 );

		wp_delete_term( $term_id, 'node_series' );

		$this->assertSame( '', get_post_meta( $post_a, NODE_SERIES_ORDER_META_KEY, true ) );
		$this->assertSame( '', get_post_meta( $post_b, NODE_SERIES_ORDER_META_KEY, true ) );
	}

	// --- 表示順の制約計算 (get_series_order_constraints) -----------------------

	public function test_order_constraints_next_min_follows_max_published_order(): void {
		$term_id = $this->create_series_term( '既刊しきい値テスト' );
		$this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );
		$this->create_post_in_series( $term_id, 5, 'draft' ); // 未公開は次回最小値の判定に影響しない。

		$new_post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$constraints = $this->get_order_constraints( $term_id, $new_post_id );

		$this->assertSame( 3, $constraints['next_min'] );
		$this->assertArrayHasKey( 1, $constraints['used'] );
		$this->assertArrayHasKey( 2, $constraints['used'] );
		$this->assertArrayHasKey( 5, $constraints['used'] );
	}

	public function test_order_constraints_exclude_self(): void {
		$term_id = $this->create_series_term( '自分自身は除外' );
		$post_id = $this->create_post_in_series( $term_id, 3, 'publish' );

		$constraints = $this->get_order_constraints( $term_id, $post_id );

		$this->assertArrayNotHasKey( 3, $constraints['used'] );
		$this->assertSame( 1, $constraints['next_min'] );
	}

	// --- 保存時のバックストップ検証 (save_order_meta_box) ----------------------

	public function test_save_rejects_order_used_by_another_post(): void {
		$term_id = $this->create_series_term( '重複拒否テスト' );
		$this->create_post_in_series( $term_id, 3, 'draft' );
		$new_post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->submit_order_meta_box( $new_post_id, $term_id, 3 );

		$this->assertSame( '', get_post_meta( $new_post_id, NODE_SERIES_ORDER_META_KEY, true ) );
	}

	public function test_save_rejects_order_earlier_than_published_episode(): void {
		$term_id = $this->create_series_term( '既刊より前は拒否' );
		$this->create_post_in_series( $term_id, 5, 'publish' );
		$new_post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->submit_order_meta_box( $new_post_id, $term_id, 2 );

		$this->assertSame( '', get_post_meta( $new_post_id, NODE_SERIES_ORDER_META_KEY, true ) );
	}

	public function test_save_accepts_valid_order_and_assigns_term(): void {
		$term_id = $this->create_series_term( '正常系' );
		$this->create_post_in_series( $term_id, 1, 'publish' );
		$new_post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->submit_order_meta_box( $new_post_id, $term_id, 2 );

		$this->assertSame( 2, (int) get_post_meta( $new_post_id, NODE_SERIES_ORDER_META_KEY, true ) );
		$this->assertContains( $term_id, wp_get_post_terms( $new_post_id, 'node_series', [ 'fields' => 'ids' ] ) );
	}

	public function test_save_keeps_own_existing_order_even_if_now_earlier_than_published(): void {
		$term_id = $this->create_series_term( '自分の既存値は保持' );
		$post_id = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 5, 'publish' );

		// 自分自身が既に1回として公開済みの状態で、同じ値(1)を保存し直しても拒否されない。
		$this->submit_order_meta_box( $post_id, $term_id, 1 );

		$this->assertSame( 1, (int) get_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, true ) );
	}

	// --- 上限件数のバックストップ (enforce_max_posts_per_series) ----------------

	public function test_enforce_max_posts_removes_term_when_over_limit(): void {
		$term_id = $this->create_series_term( '上限超過テスト' );

		for ( $i = 1; $i <= NODE_SERIES_MAX_POSTS; $i++ ) {
			$this->create_post_in_series( $term_id, $i, 'publish' );
		}

		$over_limit_post = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $over_limit_post, [ $term_id ], 'node_series' );

		Node_Series::instance()->enforce_max_posts_per_series( $over_limit_post );

		$this->assertSame( [], wp_get_post_terms( $over_limit_post, 'node_series', [ 'fields' => 'ids' ] ) );
	}

	// --- 目次データ (node_series_get_toc_data) ----------------------------------

	public function test_toc_data_is_null_for_single_post_series(): void {
		$term_id = $this->create_series_term( '単独記事は非表示' );
		$post_id = $this->create_post_in_series( $term_id, 1, 'publish' );

		$this->assertNull( node_series_get_toc_data( $post_id ) );
	}

	public function test_toc_data_present_for_multi_post_series(): void {
		$term_id = $this->create_series_term( '複数記事は表示' );
		$post_a  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$toc = node_series_get_toc_data( $post_a );

		$this->assertNotNull( $toc );
		$this->assertCount( 2, $toc['items'] );
	}

	// --- プライマリカラーの優先順位 (node_series_get_color) ---------------------

	public function test_color_priority_post_override_wins_over_term_color(): void {
		$term_id = $this->create_series_term( 'カラー優先度' );
		$post_a  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		update_term_meta( $term_id, NODE_SERIES_COLOR_TERM_META_KEY, '#00FF00' );
		update_post_meta( $post_a, NODE_SERIES_COLOR_OVERRIDE_META_KEY, '#FF00FF' );

		$this->assertSame( '#FF00FF', node_series_get_color( $post_a ) );
	}

	public function test_color_falls_back_to_term_color_then_default(): void {
		$term_id    = $this->create_series_term( 'デフォルトカラー' );
		$post_a     = $this->create_post_in_series( $term_id, 1, 'publish' );
		$post_b     = $this->create_post_in_series( $term_id, 2, 'publish' );

		update_term_meta( $term_id, NODE_SERIES_COLOR_TERM_META_KEY, '#00FF00' );

		$this->assertSame( '#00FF00', node_series_get_color( $post_b ) );

		delete_term_meta( $term_id, NODE_SERIES_COLOR_TERM_META_KEY );

		$this->assertSame( NODE_SERIES_DEFAULT_COLOR, node_series_get_color( $post_a ) );
	}

	// --- 前後記事ナビゲーション (node_series_get_adjacent) ----------------------

	public function test_get_adjacent_prev_and_next(): void {
		$term_id = $this->create_series_term( '前後ナビ' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$post_2  = $this->create_post_in_series( $term_id, 2, 'publish' );
		$post_3  = $this->create_post_in_series( $term_id, 3, 'publish' );

		$this->assertSame( $post_1, node_series_get_adjacent( $post_2, 'prev' )->ID );
		$this->assertSame( $post_3, node_series_get_adjacent( $post_2, 'next' )->ID );
		$this->assertNull( node_series_get_adjacent( $post_1, 'prev' ) );
		$this->assertNull( node_series_get_adjacent( $post_3, 'next' ) );
	}

	// --- T-5: 順序メタ無し記事の包含 (F-9) ------------------------------------

	public function test_meta_less_post_included_at_end_of_toc(): void {
		$term_id = $this->create_series_term( 'メタ無し包含' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$post_2  = $this->create_post_in_series( $term_id, 2, 'publish' );

		$post_no_meta = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_no_meta, [ $term_id ], 'node_series' );

		$toc = node_series_get_toc_data( $post_no_meta );

		$this->assertNotNull( $toc );
		$this->assertCount( 3, $toc['items'] );
		$this->assertSame( $post_1, $toc['items'][0]['id'] );
		$this->assertSame( $post_2, $toc['items'][1]['id'] );
		$this->assertSame( $post_no_meta, $toc['items'][2]['id'] );
		$this->assertTrue( $toc['items'][2]['is_current'] );
	}

	public function test_meta_less_post_in_adjacent_navigation(): void {
		$term_id = $this->create_series_term( 'メタ無し前後ナビ' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );

		$post_no_meta = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_no_meta, [ $term_id ], 'node_series' );

		$this->assertSame( $post_1, node_series_get_adjacent( $post_no_meta, 'prev' )->ID );
		$this->assertNull( node_series_get_adjacent( $post_no_meta, 'next' ) );
		$this->assertSame( $post_no_meta, node_series_get_adjacent( $post_1, 'next' )->ID );
	}

	public function test_meta_less_post_position_is_last(): void {
		$term_id = $this->create_series_term( 'メタ無しポジション' );
		$this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$post_no_meta = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_no_meta, [ $term_id ], 'node_series' );

		$pos = node_series_get_position( $post_no_meta );

		$this->assertNotNull( $pos );
		$this->assertSame( 3, $pos['index'] );
		$this->assertSame( 3, $pos['total'] );
	}

	// --- T-5: 下書き混在時のposition/total整合 --------------------------------

	public function test_draft_excluded_from_toc_and_position(): void {
		$term_id = $this->create_series_term( '下書き混在' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'draft' );
		$post_3  = $this->create_post_in_series( $term_id, 3, 'publish' );

		$toc = node_series_get_toc_data( $post_1 );

		$this->assertNotNull( $toc );
		$this->assertCount( 2, $toc['items'] );
		$this->assertSame( $post_1, $toc['items'][0]['id'] );
		$this->assertSame( $post_3, $toc['items'][1]['id'] );

		$pos = node_series_get_position( $post_3 );
		$this->assertSame( 2, $pos['index'] );
		$this->assertSame( 2, $pos['total'] );
	}

	// --- T-5: 記事削除時のtotal追随 -------------------------------------------

	public function test_deleted_post_reduces_total(): void {
		$term_id = $this->create_series_term( '削除時total' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$post_2  = $this->create_post_in_series( $term_id, 2, 'publish' );
		$post_3  = $this->create_post_in_series( $term_id, 3, 'publish' );

		wp_delete_post( $post_2, true );

		$toc = node_series_get_toc_data( $post_1 );

		$this->assertNotNull( $toc );
		$this->assertCount( 2, $toc['items'] );

		$pos = node_series_get_position( $post_3 );
		$this->assertSame( 2, $pos['index'] );
		$this->assertSame( 2, $pos['total'] );
	}

	// --- T-5: API凍結 — 戻り値スキーマのアサート --------------------------------

	public function test_toc_data_schema(): void {
		$term_id = $this->create_series_term( 'TOCスキーマ' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$toc = node_series_get_toc_data( $post_1 );

		$this->assertIsArray( $toc );
		$this->assertArrayHasKey( 'term', $toc );
		$this->assertArrayHasKey( 'items', $toc );
		$this->assertInstanceOf( WP_Term::class, $toc['term'] );
		$this->assertIsArray( $toc['items'] );

		$item = $toc['items'][0];
		$this->assertArrayHasKey( 'id', $item );
		$this->assertArrayHasKey( 'title', $item );
		$this->assertArrayHasKey( 'url', $item );
		$this->assertArrayHasKey( 'is_current', $item );
		$this->assertIsInt( $item['id'] );
		$this->assertIsString( $item['title'] );
		$this->assertIsString( $item['url'] );
		$this->assertIsBool( $item['is_current'] );
		$this->assertCount( 4, $item );
	}

	public function test_position_schema(): void {
		$term_id = $this->create_series_term( 'positionスキーマ' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$pos = node_series_get_position( $post_1 );

		$this->assertIsArray( $pos );
		$this->assertArrayHasKey( 'term', $pos );
		$this->assertArrayHasKey( 'index', $pos );
		$this->assertArrayHasKey( 'total', $pos );
		$this->assertInstanceOf( WP_Term::class, $pos['term'] );
		$this->assertIsInt( $pos['index'] );
		$this->assertIsInt( $pos['total'] );
		$this->assertCount( 3, $pos );
	}

	public function test_adjacent_schema(): void {
		$term_id = $this->create_series_term( 'adjacentスキーマ' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$next = node_series_get_adjacent( $post_1, 'next' );
		$this->assertInstanceOf( WP_Post::class, $next );

		$prev = node_series_get_adjacent( $post_1, 'prev' );
		$this->assertNull( $prev );
	}

	public function test_color_schema(): void {
		$term_id = $this->create_series_term( 'colorスキーマ' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$color = node_series_get_color( $post_1 );
		$this->assertIsString( $color );
		$this->assertMatchesRegularExpression( '/^#[0-9A-Fa-f]{6}$/', $color );

		$no_series_post = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->assertNull( node_series_get_color( $no_series_post ) );
	}

	// --- 既存並びの不変性（全記事メタあり） ------------------------------------

	public function test_all_posts_with_meta_ordering_unchanged(): void {
		$term_id = $this->create_series_term( '全メタあり並び' );
		$post_3  = $this->create_post_in_series( $term_id, 3, 'publish' );
		$post_1  = $this->create_post_in_series( $term_id, 1, 'publish' );
		$post_2  = $this->create_post_in_series( $term_id, 2, 'publish' );

		$posts = node_series_get_posts( $term_id );

		$this->assertCount( 3, $posts );
		$this->assertSame( $post_1, $posts[0]->ID );
		$this->assertSame( $post_2, $posts[1]->ID );
		$this->assertSame( $post_3, $posts[2]->ID );
	}

	// --- T-5: リビジョン/オートセーブ/権限ガード (F-3) --------------------------

	public function test_save_ignores_revision_and_autosave_ids(): void {
		$term_id = $this->create_series_term( 'リビジョンガード' );
		$post_id = $this->create_post_in_series( $term_id, 1, 'publish' );

		$revision_id = _wp_put_post_revision( get_post( $post_id ) );
		$autosave_id = _wp_put_post_revision( get_post( $post_id ), true );

		$this->act_as_admin();
		$this->build_order_meta_box_post( $term_id, 5 );

		Node_Series::instance()->save_order_meta_box( $revision_id );
		Node_Series::instance()->save_order_meta_box( $autosave_id );

		// リビジョン/オートセーブに term が割り当てられない（object_ids汚染の防止）。
		$this->assertSame( [], wp_get_object_terms( $revision_id, 'node_series', [ 'fields' => 'ids' ] ) );
		$this->assertSame( [], wp_get_object_terms( $autosave_id, 'node_series', [ 'fields' => 'ids' ] ) );

		// メタ関数は親へリダイレクトされるため、親の表示順も不変であること。
		$this->assertSame( 1, (int) get_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, true ) );
	}

	public function test_save_rejected_for_user_without_edit_permission(): void {
		$term_id = $this->create_series_term( '権限ガード' );
		$post_id = $this->create_post_in_series( $term_id, 1, 'publish' );

		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		$this->build_order_meta_box_post( $term_id, 5 );

		Node_Series::instance()->save_order_meta_box( $post_id );

		$this->assertSame( 1, (int) get_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, true ), '権限なしユーザーのPOSTで表示順が変更されないこと' );
	}

	/**
	 * クラシックエディタで発見した実害のキルチェーン回帰:
	 * メタボックス入力を伴う wp_update_post（本文変更あり）はリビジョンを生成し、
	 * F-3ガードが無いとリビジョン向け save_post 実行で
	 * (1) リビジョンに term が割り当てられ (2) delete_post_meta の親リダイレクトにより
	 * 親の表示順メタが削除される（constraints計算で親自身が「使用済みorder」に見えるため）。
	 */
	public function test_revision_generating_update_keeps_order_meta(): void {
		$term_id = $this->create_series_term( 'リビジョン往復' );
		$this->create_post_in_series( $term_id, 1, 'publish' );
		$this->create_post_in_series( $term_id, 2, 'publish' );

		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => '初版本文',
			]
		);

		$this->act_as_admin();
		$this->build_order_meta_box_post( $term_id, 3 );

		// クラシックエディタ相当: シリーズ+表示順の設定と本文変更を同一保存で行う。
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => '改訂本文（リビジョン生成）',
			]
		);

		$this->assertSame( 3, (int) get_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, true ), 'リビジョン生成を伴う保存でも表示順メタが親に残ること' );
		$this->assertContains( $term_id, wp_get_post_terms( $post_id, 'node_series', [ 'fields' => 'ids' ] ) );

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertNotEmpty( $revisions, 'この保存経路でリビジョンが生成されること（前提の担保）' );

		foreach ( $revisions as $revision ) {
			$this->assertSame( [], wp_get_object_terms( $revision->ID, 'node_series', [ 'fields' => 'ids' ] ), 'リビジョンに node_series term が割り当てられないこと' );
		}

		// さらにもう一度、本文変更を伴う更新（2回目のリビジョン）でも保持されること。
		$this->build_order_meta_box_post( $term_id, 3 );
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => '再改訂本文（2つ目のリビジョン）',
			]
		);

		$this->assertSame( 3, (int) get_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, true ), '2回目のリビジョン生成後も表示順メタが残ること' );
	}

	// --- T-1: node_series リライト/フラッシュ (F-1) -----------------------------

	/**
	 * 正本T-1の準備手順: permalink構造設定 → タクソノミー登録 → flush の順。
	 * set_permalink_structure() が $wp_rewrite->init() で書き換え規則を初期化するため、
	 * その後にタクソノミーを再登録してからflushしないと /series/ 規則が消える。
	 */
	private function prepare_series_rewrite(): void {
		$this->set_permalink_structure( '/%postname%/' );
		Node_Series::instance()->register_taxonomy();
		$GLOBALS['wp_rewrite']->flush_rules();
	}

	public function test_series_archive_route_resolves(): void {
		$this->prepare_series_rewrite();

		$term_id = self::factory()->term->create(
			[
				'taxonomy' => 'node_series',
				'name'     => 'ルーティング検証',
				'slug'     => 'routing-series',
			]
		);
		$this->create_post_in_series( (int) $term_id, 1 ); // 空のtermアーカイブは404になるため記事を1件入れる。

		$this->go_to( '/series/routing-series/' );

		$this->assertTrue( is_tax( 'node_series' ), '/series/{slug}/ が node_series アーカイブとして解決されること' );
		$this->assertFalse( is_404() );
		$this->assertSame( (int) $term_id, get_queried_object_id() );
	}

	public function test_series_archive_unknown_slug_is_404(): void {
		$this->prepare_series_rewrite();

		$this->go_to( '/series/no-such/' );

		$this->assertTrue( is_404(), '未知スラッグ /series/no-such/ は404であること' );
		$this->assertFalse( is_tax( 'node_series' ) );
	}

	public function test_flush_not_reexecuted_when_option_is_current(): void {
		update_option( 'node_series_flushed_version', NODE_SERIES_VERSION );

		// flush_rewrite_rules() は必ず update_option('rewrite_rules', '') を通るため、
		// update_option アクションの捕捉で再flushの有無を検出できる。
		$updated_options = [];
		$spy             = function ( $option ) use ( &$updated_options ) {
			$updated_options[] = $option;
		};
		add_action( 'update_option', $spy );

		Node_Series::instance()->register_taxonomy();

		remove_action( 'update_option', $spy );

		$this->assertNotContains( 'rewrite_rules', $updated_options, 'flush optionが現行値のとき flush_rewrite_rules が再実行されないこと' );
		$this->assertSame( NODE_SERIES_VERSION, get_option( 'node_series_flushed_version' ) );
	}

	public function test_flush_runs_once_when_version_is_outdated(): void {
		update_option( 'node_series_flushed_version', '0' );

		$updated_options = [];
		$spy             = function ( $option ) use ( &$updated_options ) {
			$updated_options[] = $option;
		};
		add_action( 'update_option', $spy );

		Node_Series::instance()->register_taxonomy();

		remove_action( 'update_option', $spy );

		$this->assertContains( 'rewrite_rules', $updated_options, 'バージョンが古いとき一回だけflushが走ること' );
		$this->assertSame( NODE_SERIES_VERSION, get_option( 'node_series_flushed_version' ), 'flush後にoptionが現行バージョンへ更新されること' );
	}
}
