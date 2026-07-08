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
		parent::tear_down();
	}

	private function submit_order_meta_box( int $post_id, ?int $term_id, $order ): void {
		$_POST = [
			'node_series_order_nonce'   => wp_create_nonce( 'node_series_save_order' ),
			'node_series_term_id'       => $term_id ?? '',
			'node_series_order'         => $order ?? '',
			'node_series_color_override' => '',
		];

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
}
