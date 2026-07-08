<?php
/**
 * プライマリカテゴリ機能の自動テスト。
 *
 * @package Node
 */

if ( ! function_exists( 'node_get_primary_category' ) ) {
	require_once dirname( __DIR__ ) . '/inc/utilities.php';
}

if ( ! function_exists( 'node_save_primary_category_meta' ) ) {
	require_once dirname( __DIR__ ) . '/inc/meta-boxes.php';
}

class Node_Primary_Category_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tear_down(): void {
		$_POST = [];
		parent::tear_down();
	}

	private function create_post_with_categories(): array {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_a  = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Alpha Category' ] );
		$term_b  = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Beta Category' ] );

		wp_set_object_terms( $post_id, [ $term_a, $term_b ], 'category' );

		return [ $post_id, (int) $term_a, (int) $term_b ];
	}

	public function test_primary_category_helper_returns_saved_assigned_category(): void {
		[ $post_id ]    = $this->create_post_with_categories();
		$base_categories = node_deduplicate_post_categories( get_the_category( $post_id ) );
		$primary_id      = (int) $base_categories[1]->term_id;

		update_post_meta( $post_id, '_node_primary_category', (string) $primary_id );

		$primary = node_get_primary_category( $post_id );

		$this->assertInstanceOf( WP_Term::class, $primary );
		$this->assertSame( $primary_id, (int) $primary->term_id );
	}

	public function test_primary_category_helper_falls_back_to_first_display_category(): void {
		[ $post_id ]    = $this->create_post_with_categories();
		$base_categories = node_deduplicate_post_categories( get_the_category( $post_id ) );

		delete_post_meta( $post_id, '_node_primary_category' );

		$primary = node_get_primary_category( $post_id );

		$this->assertInstanceOf( WP_Term::class, $primary );
		$this->assertSame( (int) $base_categories[0]->term_id, (int) $primary->term_id );
	}

	public function test_save_deletes_unassigned_primary_category(): void {
		[ $post_id ] = $this->create_post_with_categories();
		$outside_term = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Outside Category' ] );

		update_post_meta( $post_id, '_node_primary_category', (string) $outside_term );
		$_POST = [
			'node_primary_category_nonce' => wp_create_nonce( 'node_save_primary_category' ),
			'node_primary_category'       => (string) $outside_term,
		];

		node_save_primary_category_meta( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_node_primary_category', true ) );
	}

	public function test_term_cleanup_deletes_primary_category_when_assignment_is_removed(): void {
		[ $post_id ]    = $this->create_post_with_categories();
		$base_categories = node_deduplicate_post_categories( get_the_category( $post_id ) );
		$primary_id      = (int) $base_categories[1]->term_id;
		$remaining_id    = (int) $base_categories[0]->term_id;

		update_post_meta( $post_id, '_node_primary_category', (string) $primary_id );

		wp_set_object_terms( $post_id, [ $remaining_id ], 'category' );

		$this->assertSame( '', get_post_meta( $post_id, '_node_primary_category', true ) );
	}

	public function test_display_categories_are_reordered_with_primary_first(): void {
		[ $post_id ]    = $this->create_post_with_categories();
		$base_categories = node_deduplicate_post_categories( get_the_category( $post_id ) );
		$primary_id      = (int) $base_categories[1]->term_id;
		$fallback_id     = (int) $base_categories[0]->term_id;

		update_post_meta( $post_id, '_node_primary_category', (string) $primary_id );

		$categories = node_get_post_categories_for_display( $post_id );

		$this->assertSame( $primary_id, (int) $categories[0]->term_id );
		$this->assertSame( $fallback_id, (int) $categories[1]->term_id );
	}
}
