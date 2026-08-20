<?php
/**
 * 検索キーワードのサジェスト（node/v1/search/suggest）のテスト。
 *
 * @package Node
 */

require_once dirname( __DIR__ ) . '/plugins-embedded/node-library/node-library.php';

if ( ! function_exists( 'node_search_suggest_response' ) ) {
	require_once dirname( __DIR__ ) . '/inc/search-suggest.php';
}

class Node_Search_Suggest_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! post_type_exists( 'node_library' ) ) {
			Node_Library::instance()->register_cpt();
		}
	}

	private function create_library_item( string $title, string $type = 'game' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'node_library',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
		update_post_meta( $id, '_node_library_type', $type );

		return $id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function suggest( string $query ): array {
		$request = new WP_REST_Request( 'GET', '/node/v1/search/suggest' );
		$request->set_param( 'q', $query );

		return (array) node_search_suggest_response( $request )->get_data();
	}

	/**
	 * @param array<int, array<string, mixed>> $suggestions
	 * @return array<int, string>
	 */
	private function keywords( array $suggestions ): array {
		return array_column( $suggestions, 'keyword' );
	}

	public function test_library_titles_are_suggested(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );

		$keywords = $this->keywords( $this->suggest( 'Zephyrium' ) );

		$this->assertContains( 'Zephyrium Chronicles', $keywords, 'Node Library の項目名が候補に出る' );
	}

	public function test_category_names_are_suggested(): void {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Quixotica Reviews',
			)
		);
		// hide_empty=true で拾えるように記事を1本紐づける。
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_object_terms( $post_id, array( (int) $term_id ), 'category' );

		$suggestions = $this->suggest( 'Quixotica' );

		$this->assertContains( 'Quixotica Reviews', $this->keywords( $suggestions ) );

		foreach ( $suggestions as $suggestion ) {
			if ( 'Quixotica Reviews' === $suggestion['keyword'] ) {
				$this->assertSame( 'category', $suggestion['source'] );
				$this->assertSame( 'カテゴリ', $suggestion['sourceLabel'] );
			}
		}
	}

	public function test_draft_library_items_are_not_suggested(): void {
		self::factory()->post->create(
			array(
				'post_type'   => 'node_library',
				'post_status' => 'draft',
				'post_title'  => 'Draftonia Unreleased',
			)
		);

		$this->assertSame( array(), $this->keywords( $this->suggest( 'Draftonia' ) ), '下書きの項目は漏れない' );
	}

	public function test_short_query_returns_nothing(): void {
		$this->create_library_item( 'Zephyrium Chronicles' );

		$this->assertSame( array(), $this->suggest( 'Z' ), '1文字では候補を返さない（絞り込みにならないため）' );
	}

	public function test_app_and_game_items_get_distinct_icons(): void {
		$this->create_library_item( 'Nimbusflow Editor', 'app' );
		$this->create_library_item( 'Nimbusflow Arena', 'game' );

		$icons = array();
		foreach ( $this->suggest( 'Nimbusflow' ) as $suggestion ) {
			$icons[ $suggestion['keyword'] ] = $suggestion['icon'];
		}

		$this->assertSame( 'smartphone', $icons['Nimbusflow Editor'] ?? null );
		$this->assertSame( 'sports_esports', $icons['Nimbusflow Arena'] ?? null );
	}
}
