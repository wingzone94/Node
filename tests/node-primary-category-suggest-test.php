<?php
/**
 * Node Library と同名カテゴリに対するプライマリカテゴリ提案のテスト。
 *
 * @package Node
 */

require_once dirname( __DIR__ ) . '/plugins-embedded/node-library/node-library.php';

if ( ! function_exists( 'node_get_primary_category_library_matches' ) ) {
	require_once dirname( __DIR__ ) . '/inc/meta-boxes.php';
}

class Node_Primary_Category_Suggest_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		if ( ! post_type_exists( 'node_library' ) ) {
			Node_Library::instance()->register_cpt();
		}
	}

	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	private function create_library_item( string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'node_library',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
	}

	/**
	 * カテゴリ名を1つ持つ記事を作る。
	 *
	 * @return array{0:int, 1:int} 投稿IDとカテゴリID。
	 */
	private function create_post_in_category( string $category_name ): array {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => $category_name,
			)
		);
		wp_set_object_terms( $post_id, array( $term_id ), 'category' );

		return array( $post_id, $term_id );
	}

	/**
	 * @param array<int, array<string, mixed>> $matches
	 * @return array<int, int>
	 */
	private function matched_term_ids( array $matches ): array {
		return array_map(
			static fn( array $match ): int => (int) $match['term']->term_id,
			$matches
		);
	}

	public function test_same_named_category_is_suggested(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );
		[ $post_id, $term_id ] = $this->create_post_in_category( 'Zephyrium Chronicles' );

		$matches = node_get_primary_category_library_matches( $post_id, 0 );

		$this->assertSame( array( $term_id ), $this->matched_term_ids( $matches ) );
		$this->assertSame( 'Zephyrium Chronicles', $matches[0]['library']['title'] );
	}

	public function test_case_and_padding_differences_still_match(): void {
		$this->create_library_item( '  ZEPHYRIUM chronicles ' );
		[ $post_id, $term_id ] = $this->create_post_in_category( 'Zephyrium Chronicles' );

		$this->assertSame(
			array( $term_id ),
			$this->matched_term_ids( node_get_primary_category_library_matches( $post_id, 0 ) ),
			'前後の空白と英字の大小は無視して突き合わせる'
		);
	}

	public function test_partial_name_is_not_suggested(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );
		[ $post_id ] = $this->create_post_in_category( 'Zephyrium' );

		$this->assertSame(
			array(),
			node_get_primary_category_library_matches( $post_id, 0 ),
			'部分一致では提案しない（誤提案を避ける）'
		);
	}

	public function test_current_primary_category_is_not_suggested_again(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );
		[ $post_id, $term_id ] = $this->create_post_in_category( 'Zephyrium Chronicles' );

		$this->assertSame(
			array(),
			node_get_primary_category_library_matches( $post_id, $term_id ),
			'すでにプライマリなら提案しない'
		);
	}

	public function test_dismissed_category_is_not_suggested_again(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );
		[ $post_id, $term_id ] = $this->create_post_in_category( 'Zephyrium Chronicles' );

		$_POST['node_primary_category_suggest_dismissed'] = (string) $term_id;
		node_save_dismissed_primary_category_suggestions( $post_id );

		$this->assertSame( array( $term_id ), node_get_dismissed_primary_category_suggestions( $post_id ) );
		$this->assertSame(
			array(),
			node_get_primary_category_library_matches( $post_id, 0 ),
			'「設定しない」を選んだカテゴリは再提案しない'
		);
	}

	public function test_dismissals_accumulate_across_saves(): void {
		[ $post_id, $term_a ] = $this->create_post_in_category( 'Alpha Title' );
		$term_b = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Beta Title',
			)
		);
		wp_set_object_terms( $post_id, array( $term_a, $term_b ), 'category' );

		$_POST['node_primary_category_suggest_dismissed'] = (string) $term_a;
		node_save_dismissed_primary_category_suggestions( $post_id );

		$_POST['node_primary_category_suggest_dismissed'] = (string) $term_b;
		node_save_dismissed_primary_category_suggestions( $post_id );

		$dismissed = node_get_dismissed_primary_category_suggestions( $post_id );
		sort( $dismissed );
		$expected = array( $term_a, $term_b );
		sort( $expected );

		$this->assertSame( $expected, $dismissed, '過去の判断は上書きせず積み上げる' );
	}

	public function test_unrelated_category_is_not_suggested(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );
		[ $post_id ] = $this->create_post_in_category( 'ニュース' );

		$this->assertSame( array(), node_get_primary_category_library_matches( $post_id, 0 ) );
	}
}
