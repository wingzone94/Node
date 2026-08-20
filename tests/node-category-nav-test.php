<?php
/**
 * カテゴリ回遊導線（トップ / フッター）の取得ロジックのテスト。
 *
 * @package Node
 */

if ( ! function_exists( 'node_get_navigation_categories' ) ) {
	require_once dirname( __DIR__ ) . '/inc/utilities.php';
}

class Node_Category_Nav_Test extends WP_UnitTestCase {

	/**
	 * 指定件数の公開記事を持つカテゴリを作る。
	 */
	private function create_category_with_posts( string $name, int $post_count ): int {
		$term_id = (int) self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => $name,
			)
		);

		for ( $i = 0; $i < $post_count; $i++ ) {
			$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
			wp_set_object_terms( $post_id, array( $term_id ), 'category' );
		}

		return $term_id;
	}

	/**
	 * @param array<int, WP_Term> $terms
	 * @return array<int, string>
	 */
	private function names( array $terms ): array {
		return array_map( static fn( WP_Term $term ): string => $term->name, $terms );
	}

	public function test_categories_are_ordered_by_post_count(): void {
		$this->create_category_with_posts( 'Few Posts', 1 );
		$this->create_category_with_posts( 'Many Posts', 5 );
		$this->create_category_with_posts( 'Some Posts', 3 );

		$names = $this->names( node_get_navigation_categories( 10 ) );

		$this->assertSame(
			array( 'Many Posts', 'Some Posts', 'Few Posts' ),
			$names,
			'記事数の多い順に並ぶ'
		);
	}

	public function test_limit_is_respected(): void {
		$this->create_category_with_posts( 'Alpha', 4 );
		$this->create_category_with_posts( 'Bravo', 3 );
		$this->create_category_with_posts( 'Charlie', 2 );

		$this->assertCount( 2, node_get_navigation_categories( 2 ) );
	}

	public function test_empty_categories_are_excluded(): void {
		$this->create_category_with_posts( 'Has Posts', 2 );
		self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Empty Category',
			)
		);

		$this->assertNotContains(
			'Empty Category',
			$this->names( node_get_navigation_categories( 10 ) ),
			'記事の無いカテゴリは行き先が空になるので出さない'
		);
	}

	public function test_default_category_is_excluded(): void {
		$default_id = (int) get_option( 'default_category' );
		$this->assertGreaterThan( 0, $default_id, '前提: 既定カテゴリが存在する' );

		// 既定カテゴリ（未分類）にも記事を付け、件数では最上位にしておく。
		for ( $i = 0; $i < 6; $i++ ) {
			$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
			wp_set_object_terms( $post_id, array( $default_id ), 'category' );
		}
		$this->create_category_with_posts( 'Real Category', 2 );

		$ids = array_map(
			static fn( WP_Term $term ): int => (int) $term->term_id,
			node_get_navigation_categories( 10 )
		);

		$this->assertNotContains( $default_id, $ids, '「未分類」は行き先として意味がないので除外する' );
		$this->assertNotEmpty( $ids, '除外しても他のカテゴリは残る' );
	}

	public function test_limit_is_clamped_to_at_least_one(): void {
		$this->create_category_with_posts( 'Alpha', 2 );

		$this->assertCount( 1, node_get_navigation_categories( 0 ), '0 以下でも最低1件は返す' );
	}
}
