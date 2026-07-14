<?php
/**
 * node-flow REST エンドポイント（node-flow/v1/posts）のホワイトリスト検証（T-9 / F-4）。
 *
 * Scroller::get_posts_html() がクライアント任意の query 配列を WP_Query に直マージして
 * いた入力面を、既知キーのみのホワイトリスト方式に閉じたことを回帰網でロックする。
 *
 * @package Node_Flow
 */

use Node\Flow\Frontend\Scroller;

class Node_Flow_Rest_Test extends WP_UnitTestCase {

	/** @var int[] 直近の WP_Query が返した投稿ID群（the_posts フィルタで捕捉）。 */
	private $captured_ids = [];

	/** @var string[] 直近の WP_Query が返した投稿タイトル群。 */
	private $captured_titles = [];

	public static function set_up_before_class() {
		parent::set_up_before_class();
		// テスト対象クラスをオートローダー無しで直接読み込む（node-flow.php の定数に依存しない）。
		require_once dirname( __DIR__ ) . '/plugins-embedded/node-flow/includes/Frontend/Scroller.php';
	}

	public function tear_down() {
		remove_filter( 'the_posts', [ $this, 'capture_posts' ], 10 );
		$this->captured_ids    = [];
		$this->captured_titles = [];
		parent::tear_down();
	}

	/**
	 * get_posts_html() 内の WP_Query が実際に返した投稿を捕捉する。
	 * テーマテンプレート（get_template_part）の有無に依存せず絞り込み結果を検証できる。
	 */
	public function capture_posts( $posts ) {
		foreach ( (array) $posts as $post ) {
			$this->captured_ids[]    = (int) $post->ID;
			$this->captured_titles[] = $post->post_title;
		}
		return $posts;
	}

	/**
	 * query 配列を持つ REST リクエストを組み立てて get_posts_html() を実行し、
	 * WP_Query が返した投稿IDと WP_REST_Response を返す。
	 *
	 * @return array{ids:int[], titles:string[], response:\WP_REST_Response}
	 */
	private function run_scroll( array $query, int $page = 1 ) {
		$this->captured_ids    = [];
		$this->captured_titles = [];
		add_filter( 'the_posts', [ $this, 'capture_posts' ], 10, 1 );

		$request = new WP_REST_Request( 'GET', '/node-flow/v1/posts' );
		$request->set_param( 'page', $page );
		$request->set_param( 'query', $query );

		$response = Scroller::get_instance()->get_posts_html( $request );

		remove_filter( 'the_posts', [ $this, 'capture_posts' ], 10 );

		return [
			'ids'      => $this->captured_ids,
			'titles'   => $this->captured_titles,
			'response' => $response,
		];
	}

	// --- 注入系: 非許可キーが無視されること ---

	public function test_post_status_draft_is_ignored() {
		$published = self::factory()->post->create( [ 'post_status' => 'publish', 'post_title' => 'Published Alpha' ] );
		$draft     = self::factory()->post->create( [ 'post_status' => 'draft', 'post_title' => 'Secret Draft Beta' ] );

		$result = $this->run_scroll( [ 'post_status' => 'draft' ] );

		$this->assertContains( $published, $result['ids'], '公開記事は含まれる' );
		$this->assertNotContains( $draft, $result['ids'], 'query[post_status]=draft は無視され下書きは漏れない' );
	}

	public function test_draft_title_does_not_leak_in_html() {
		self::factory()->post->create( [ 'post_status' => 'publish', 'post_title' => 'Public Visible Title' ] );
		self::factory()->post->create( [ 'post_status' => 'draft', 'post_title' => 'ZZ_Secret_Draft_Marker' ] );

		$result = $this->run_scroll( [ 'post_status' => 'draft', 'post_type' => 'any' ] );

		$data = $result['response']->get_data();
		$this->assertStringNotContainsString( 'ZZ_Secret_Draft_Marker', $data['html'], '下書きタイトルはレスポンスHTMLに漏れない' );
	}

	public function test_post_type_page_is_ignored() {
		$post = self::factory()->post->create( [ 'post_status' => 'publish', 'post_title' => 'Real Post' ] );
		$page = self::factory()->post->create( [ 'post_status' => 'publish', 'post_type' => 'page', 'post_title' => 'A Page' ] );

		$result = $this->run_scroll( [ 'post_type' => 'page' ] );

		$this->assertContains( $post, $result['ids'], 'post_type は post に固定される' );
		$this->assertNotContains( $page, $result['ids'], 'query[post_type]=page は無視される' );
	}

	public function test_meta_key_injection_is_ignored() {
		$with_meta = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $with_meta, '_secret_flag', '1' );
		$without_meta = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		// meta_key を絞り込みに使わせようとしても無視され、両方返る。
		$result = $this->run_scroll( [ 'meta_key' => '_secret_flag', 'meta_value' => '1' ] );

		$this->assertContains( $with_meta, $result['ids'] );
		$this->assertContains( $without_meta, $result['ids'], 'query[meta_key] は無視され meta による絞り込みが効かない' );
	}

	// --- 正常系: 許可キーが機能すること ---

	public function test_cat_filter_works() {
		$term_id = self::factory()->category->create( [ 'slug' => 'nodeflow-cat' ] );
		$in_cat  = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $in_cat, [ $term_id ], 'category' );
		$out_cat = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$result = $this->run_scroll( [ 'cat' => $term_id ] );

		$this->assertContains( $in_cat, $result['ids'], 'query[cat] のカテゴリー記事は含まれる' );
		$this->assertNotContains( $out_cat, $result['ids'], 'カテゴリー外の記事は除外される' );
	}

	public function test_tag_filter_works() {
		$term_id  = self::factory()->tag->create( [ 'slug' => 'nodeflow-tag' ] );
		$tagged   = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $tagged, [ $term_id ], 'post_tag' );
		$untagged = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$result = $this->run_scroll( [ 'tag' => 'nodeflow-tag' ] );

		$this->assertContains( $tagged, $result['ids'], 'query[tag] のタグ記事は含まれる' );
		$this->assertNotContains( $untagged, $result['ids'], 'タグ外の記事は除外される' );
	}

	public function test_search_filter_works() {
		$hit  = self::factory()->post->create( [ 'post_status' => 'publish', 'post_title' => 'Zephyrium unique keyword' ] );
		$miss = self::factory()->post->create( [ 'post_status' => 'publish', 'post_title' => 'Unrelated content' ] );

		$result = $this->run_scroll( [ 's' => 'Zephyrium' ] );

		$this->assertContains( $hit, $result['ids'], 'query[s] の検索語に一致する記事は含まれる' );
		$this->assertNotContains( $miss, $result['ids'], '検索語に一致しない記事は除外される' );
	}

	public function test_node_series_filter_works() {
		$term_id  = self::factory()->term->create( [ 'taxonomy' => 'node_series', 'slug' => 'nodeflow-series' ] );
		$in_ser   = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $in_ser, [ $term_id ], 'node_series' );
		$out_ser  = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$result = $this->run_scroll( [ 'node_series' => 'nodeflow-series' ] );

		$this->assertContains( $in_ser, $result['ids'], 'query[node_series] のシリーズ記事は含まれる' );
		$this->assertNotContains( $out_ser, $result['ids'], 'シリーズ外の記事は除外される' );
	}

	// --- ページング整合 ---

	public function test_has_more_reflects_total_pages() {
		$term_id = self::factory()->category->create( [ 'slug' => 'nodeflow-paged' ] );
		// 既定 posts_per_page を跨いで2ページ以上になる件数を投入。
		$per_page = (int) get_option( 'posts_per_page', 10 );
		$total    = $per_page + 3;
		for ( $i = 0; $i < $total; $i++ ) {
			$pid = self::factory()->post->create( [ 'post_status' => 'publish' ] );
			wp_set_object_terms( $pid, [ $term_id ], 'category' );
		}

		$first = $this->run_scroll( [ 'cat' => $term_id ], 1 );
		$this->assertTrue( $first['response']->get_data()['hasMore'], '1ページ目では続きがある（hasMore=true）' );

		$last = $this->run_scroll( [ 'cat' => $term_id ], 2 );
		$this->assertFalse( $last['response']->get_data()['hasMore'], '最終ページでは hasMore=false' );
	}
}
