<?php
/**
 * Node Library reverse-reference synchronization tests.
 *
 * @package Node
 */

require_once dirname( __DIR__ ) . '/plugins-embedded/node-library/node-library.php';

class Node_Library_Sync_Test extends WP_UnitTestCase {
	private Node_Library $library;

	public function set_up(): void {
		parent::set_up();
		$this->library = Node_Library::instance();

		if ( ! post_type_exists( 'node_library' ) ) {
			$this->library->register_cpt();
		}
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

	private function library_block( int $library_id ): string {
		return sprintf( '<!-- wp:node-library/item-card {"libraryId":%d} /-->', $library_id );
	}

	/**
	 * @return array<int, int>
	 */
	private function indexed_library_ids( int $post_id ): array {
		$ids = array_values(
			array_unique(
				array_filter( array_map( 'absint', get_post_meta( $post_id, '_node_library_card_reference', false ) ) )
			)
		);
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/**
	 * @return array<int, int>
	 */
	private function indexed_dependency_ids( int $post_id, string $meta_key ): array {
		$ids = array_values(
			array_unique(
				array_filter( array_map( 'absint', get_post_meta( $post_id, $meta_key, false ) ) )
			)
		);
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	public function test_plugin_header_version_matches_runtime_version(): void {
		$plugin_data = get_file_data(
			dirname( __DIR__ ) . '/plugins-embedded/node-library/node-library.php',
			array( 'Version' => 'Version' )
		);

		$this->assertSame( NODE_LIBRARY_VERSION, $plugin_data['Version'] );
	}

	public function test_create_update_and_card_removal_replace_the_index_immediately(): void {
		$first_library_id  = $this->create_library_item( 'First Library Item' );
		$second_library_id = $this->create_library_item( 'Second Library Item' );
		$post_id           = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $first_library_id ),
			)
		);

		$this->assertSame( array( $first_library_id ), $this->indexed_library_ids( $post_id ) );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:group -->' . $this->library_block( $second_library_id ) . '<!-- /wp:group -->',
			)
		);
		$this->assertSame( array( $second_library_id ), $this->indexed_library_ids( $post_id ) );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:paragraph --><p>No library card.</p><!-- /wp:paragraph -->',
			)
		);
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );
	}

	public function test_trash_restore_and_permanent_delete_leave_no_stale_index(): void {
		$library_id = $this->create_library_item( 'Lifecycle Library Item' );
		$post_id    = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $library_id ),
			)
		);

		wp_trash_post( $post_id );
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );

		wp_untrash_post( $post_id );
		$this->assertSame( array( $library_id ), $this->indexed_library_ids( $post_id ) );

		wp_delete_post( $post_id, true );
		$this->assertNull( get_post( $post_id ) );
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );
	}

	public function test_full_synchronization_repairs_missing_wrong_and_trashed_data(): void {
		$expected_library_id = $this->create_library_item( 'Expected Library Item' );
		$wrong_library_id    = $this->create_library_item( 'Wrong Library Item' );
		$post_id             = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $expected_library_id ),
			)
		);

		delete_post_meta( $post_id, '_node_library_card_reference' );
		add_post_meta( $post_id, '_node_library_card_reference', (string) $wrong_library_id, false );
		$this->library->synchronize_all_post_library_card_references();
		$this->assertSame( array( $expected_library_id ), $this->indexed_library_ids( $post_id ) );

		wp_trash_post( $post_id );
		add_post_meta( $post_id, '_node_library_card_reference', (string) $wrong_library_id, false );
		$this->library->synchronize_all_post_library_card_references();
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );
	}

	public function test_reusable_block_changes_are_synchronized_to_referencing_posts(): void {
		$first_library_id  = $this->create_library_item( 'Reusable First Item' );
		$second_library_id = $this->create_library_item( 'Reusable Second Item' );
		$reusable_block_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $first_library_id ),
			)
		);
		$post_id           = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => sprintf( '<!-- wp:block {"ref":%d} /-->', $reusable_block_id ),
			)
		);

		$this->assertSame( array( $first_library_id ), $this->indexed_library_ids( $post_id ) );
		$this->assertSame(
			array( $reusable_block_id ),
			$this->indexed_dependency_ids( $post_id, '_node_library_reusable_block_dependency' )
		);

		wp_update_post(
			array(
				'ID'           => $reusable_block_id,
				'post_content' => $this->library_block( $second_library_id ),
			)
		);
		$this->assertSame( array( $second_library_id ), $this->indexed_library_ids( $post_id ) );

		wp_delete_post( $reusable_block_id, true );
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );
	}

	public function test_library_item_trash_restore_and_delete_update_referencing_posts(): void {
		$library_id = $this->create_library_item( 'Library Item Lifecycle' );
		$post_id    = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $library_id ),
			)
		);

		$this->assertSame( array( $library_id ), $this->indexed_library_ids( $post_id ) );
		$this->assertSame(
			array( $library_id ),
			$this->indexed_dependency_ids( $post_id, '_node_library_card_dependency' )
		);

		wp_trash_post( $library_id );
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );

		wp_untrash_post( $library_id );
		$this->assertSame( array( $library_id ), $this->indexed_library_ids( $post_id ) );

		wp_delete_post( $library_id, true );
		$this->assertSame( array(), $this->indexed_library_ids( $post_id ) );
	}

	public function test_version_two_index_is_fully_rebuilt_to_version_three(): void {
		$library_id = $this->create_library_item( 'Migration Library Item' );
		$post_id    = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $library_id ),
			)
		);

		delete_post_meta( $post_id, '_node_library_card_reference' );
		update_option( 'node_library_card_reference_index_version', '2', false );
		$this->library->migrate_library_card_references();

		$this->assertSame( '3', get_option( 'node_library_card_reference_index_version' ) );
		$this->assertSame( array( $library_id ), $this->indexed_library_ids( $post_id ) );
		$this->assertSame(
			array( $library_id ),
			$this->indexed_dependency_ids( $post_id, '_node_library_card_dependency' )
		);
	}

	public function test_dependency_save_reindexes_only_dependent_posts(): void {
		$library_id = $this->create_library_item( 'Targeted Library Item' );
		$dependent_post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $this->library_block( $library_id ),
			)
		);
		$unrelated_post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Unrelated.</p><!-- /wp:paragraph -->',
			)
		);
		add_post_meta( $unrelated_post_id, '_node_library_card_reference', '999999', false );

		wp_update_post(
			array(
				'ID'         => $library_id,
				'post_title' => 'Targeted Library Item Updated',
			)
		);

		$this->assertSame( array( $library_id ), $this->indexed_library_ids( $dependent_post_id ) );
		$this->assertSame( array( 999999 ), $this->indexed_library_ids( $unrelated_post_id ) );
	}
}
