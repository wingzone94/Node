<?php
/**
 * Plugin Name:  Node Series
 * Plugin URI:   https://github.com/wingzone94/Node
 * Description:  連載/シリーズ機能。記事をシリーズにまとめ、シリーズ内の前後記事・目次を取得する機能を提供。
 * Version:      1.0.0
 * Author:       Luminous Core Teams
 * License:      MIT
 * Text Domain:  node-series
 *
 * @package Node_Series
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NODE_SERIES_VERSION', '1.0.0' );
define( 'NODE_SERIES_ORDER_META_KEY', '_node_series_order' );
define( 'NODE_SERIES_COLOR_TERM_META_KEY', 'node_series_color' );
define( 'NODE_SERIES_COLOR_OVERRIDE_META_KEY', '_node_series_color_override' );
define( 'NODE_SERIES_DEFAULT_COLOR', '#FF9900' );
define( 'NODE_SERIES_MAX_POSTS', 10 );

/**
 * Node Series Main Class
 */
final class Node_Series {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_taxonomy' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_order_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_order_meta_box' ] );
		add_action( 'node_series_add_form_fields', [ $this, 'render_term_color_field_add' ] );
		add_action( 'node_series_edit_form_fields', [ $this, 'render_term_color_field_edit' ] );
		add_action( 'created_node_series', [ $this, 'save_term_color_field' ] );
		add_action( 'edited_node_series', [ $this, 'save_term_color_field' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_color_picker' ] );
		add_action( 'save_post', [ $this, 'enforce_max_posts_per_series' ], 20 );
		add_action( 'admin_notices', [ $this, 'render_limit_notice' ] );
		add_filter( 'rest_pre_insert_post', [ $this, 'validate_series_limit_via_rest' ], 10, 2 );
	}

	/**
	 * ブロックエディタ（REST API経由の保存）向けの上限チェック。
	 * admin_noticesフックはブロックエディタの画面ではコアによって無効化されるため、
	 * 表示されない通知を出す代わりに、保存自体をエラーとして拒否し、
	 * エディタの保存失敗通知（赤いスナックバー）にメッセージを表示させる。
	 */
	public function validate_series_limit_via_rest( $prepared_post, WP_REST_Request $request ) {
		if ( is_wp_error( $prepared_post ) || ! isset( $request['node_series'] ) ) {
			return $prepared_post;
		}

		$post_id = (int) ( $request['id'] ?? 0 );

		foreach ( (array) $request['node_series'] as $term_id ) {
			$term_id = (int) $term_id;
			$already_in_term = $post_id && has_term( $term_id, 'node_series', $post_id );
			$effective_count = node_series_count_posts_in_term( $term_id ) + ( $already_in_term ? 0 : 1 );

			if ( $effective_count > NODE_SERIES_MAX_POSTS ) {
				$term = get_term( $term_id, 'node_series' );
				return new WP_Error(
					'node_series_limit_exceeded',
					sprintf(
						'シリーズ「%1$s」は登録上限（%2$d件）に達しているため、この記事を追加できません。',
						$term instanceof WP_Term ? $term->name : '',
						NODE_SERIES_MAX_POSTS
					),
					[ 'status' => 400 ]
				);
			}
		}

		return $prepared_post;
	}

	/**
	 * シリーズ内の登録上限（NODE_SERIES_MAX_POSTS件）を超えていた場合、
	 * この記事のシリーズ割り当てを取り消す。ステータスを問わず数える（公開済みのみだと
	 * 下書きが多いシリーズで上限判定が緩くなるため）。
	 *
	 * ブロックエディタの保存は validate_series_limit_via_rest() が先に保存自体を拒否するため
	 * 通常はここに到達しない。クラシックエディタやWP-CLI等、REST API経由ではない保存経路に対する
	 * 保険（バックストップ）として残す。
	 */
	public function enforce_max_posts_per_series( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$term = node_series_get_term( $post_id );
		if ( null === $term ) {
			return;
		}

		$count = node_series_count_posts_in_term( $term->term_id );
		if ( $count <= NODE_SERIES_MAX_POSTS ) {
			return;
		}

		wp_remove_object_terms( $post_id, $term->term_id, 'node_series' );
		set_transient( 'node_series_limit_notice_' . get_current_user_id(), $term->name, 60 );
	}

	/**
	 * 上限超過でシリーズ割り当てを取り消した場合の管理画面通知。
	 */
	public function render_limit_notice(): void {
		$user_id   = get_current_user_id();
		$term_name = get_transient( 'node_series_limit_notice_' . $user_id );

		if ( ! $term_name ) {
			return;
		}

		delete_transient( 'node_series_limit_notice_' . $user_id );
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					'シリーズ「%1$s」は登録上限（%2$d件）に達しているため、この記事のシリーズ割り当てを解除しました。',
					$term_name,
					NODE_SERIES_MAX_POSTS
				)
			)
		);
	}

	/**
	 * シリーズ編集画面でカラーピッカーを使うためのアセット読み込み。
	 */
	public function enqueue_color_picker( string $hook_suffix ): void {
		$is_term_screen = in_array( $hook_suffix, [ 'edit-tags.php', 'term.php' ], true )
			&& isset( $_GET['taxonomy'] ) && 'node_series' === $_GET['taxonomy'];
		$is_post_screen = in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true );

		if ( ! $is_term_screen && ! $is_post_screen ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			"jQuery(function($){ \$('.node-series-color-field').wpColorPicker(); });"
		);
	}

	/**
	 * 新規シリーズ作成画面: プライマリカラー入力欄。
	 */
	public function render_term_color_field_add(): void {
		?>
		<div class="form-field">
			<label for="node_series_color">プライマリカラー</label>
			<input
				type="text"
				id="node_series_color"
				name="node_series_color"
				class="node-series-color-field"
				value=""
				data-default-color="<?php echo esc_attr( NODE_SERIES_DEFAULT_COLOR ); ?>"
				placeholder="<?php echo esc_attr( NODE_SERIES_DEFAULT_COLOR ); ?>"
			>
			<p class="description">このシリーズのカード表示などに使う色。未設定の場合は既定のブランドオレンジ（<?php echo esc_html( NODE_SERIES_DEFAULT_COLOR ); ?>）になります。</p>
		</div>
		<?php
	}

	/**
	 * シリーズ編集画面: プライマリカラー入力欄。
	 */
	public function render_term_color_field_edit( WP_Term $term ): void {
		$color = get_term_meta( $term->term_id, NODE_SERIES_COLOR_TERM_META_KEY, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="node_series_color">プライマリカラー</label></th>
			<td>
				<input
					type="text"
					id="node_series_color"
					name="node_series_color"
					class="node-series-color-field"
					value="<?php echo esc_attr( $color ); ?>"
					data-default-color="<?php echo esc_attr( NODE_SERIES_DEFAULT_COLOR ); ?>"
					placeholder="<?php echo esc_attr( NODE_SERIES_DEFAULT_COLOR ); ?>"
				>
				<p class="description">このシリーズのカード表示などに使う色。未設定の場合は既定のブランドオレンジ（<?php echo esc_html( NODE_SERIES_DEFAULT_COLOR ); ?>）になります。</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * シリーズのプライマリカラーをterm metaに保存。
	 */
	public function save_term_color_field( int $term_id ): void {
		if ( ! isset( $_POST['node_series_color'] ) ) {
			return;
		}

		$color = sanitize_text_field( wp_unslash( $_POST['node_series_color'] ) );

		if ( '' === $color || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
			delete_term_meta( $term_id, NODE_SERIES_COLOR_TERM_META_KEY );
			return;
		}

		update_term_meta( $term_id, NODE_SERIES_COLOR_TERM_META_KEY, $color );
	}

	/**
	 * カスタムタクソノミー node_series を登録。
	 * 作成・編集・記事への割当はWP標準のtaxonomy管理画面UIをそのまま利用する。
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			'node_series',
			[ 'post' ],
			[
				'labels'            => [
					'name'          => 'シリーズ',
					'singular_name' => 'シリーズ',
					'search_items'  => 'シリーズを検索',
					'all_items'     => 'すべてのシリーズ',
					'edit_item'     => 'シリーズを編集',
					'add_new_item'  => '新規シリーズを追加',
					'menu_name'     => 'シリーズ',
				],
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => [ 'slug' => 'series' ],
			]
		);
	}

	/**
	 * シリーズ内の表示順序を設定するメタボックス。
	 */
	public function add_order_meta_box(): void {
		add_meta_box(
			'node_series_order',
			'シリーズ内の表示順序',
			[ $this, 'render_order_meta_box' ],
			'post',
			'side',
			'default'
		);
	}

	public function render_order_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'node_series_save_order', 'node_series_order_nonce' );
		$order          = get_post_meta( $post->ID, NODE_SERIES_ORDER_META_KEY, true );
		$color_override = get_post_meta( $post->ID, NODE_SERIES_COLOR_OVERRIDE_META_KEY, true );
		?>
		<p>
			<label for="node_series_order_input">表示順（小さい順に表示。未設定可）</label>
			<input
				type="number"
				id="node_series_order_input"
				name="node_series_order"
				value="<?php echo esc_attr( $order ); ?>"
				step="1"
				style="width: 100%;"
			>
		</p>
		<p class="description">この記事が属するシリーズは、下の「シリーズ」ボックスで選択してください。</p>
		<p>
			<label for="node_series_color_override_input">この記事だけのプライマリカラー（任意）</label>
			<input
				type="text"
				id="node_series_color_override_input"
				name="node_series_color_override"
				class="node-series-color-field"
				value="<?php echo esc_attr( $color_override ); ?>"
				placeholder="シリーズ共通の色を使用"
				style="width: 100%;"
			>
		</p>
		<p class="description">未設定の場合はシリーズ共通のプライマリカラーが使われます。</p>
		<?php
	}

	public function save_order_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['node_series_order_nonce'] ) || ! wp_verify_nonce( $_POST['node_series_order_nonce'], 'node_series_save_order' ) ) {
			return;
		}

		if ( ! isset( $_POST['node_series_order'] ) || '' === $_POST['node_series_order'] ) {
			delete_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY );
		} else {
			update_post_meta( $post_id, NODE_SERIES_ORDER_META_KEY, (int) $_POST['node_series_order'] );
		}

		$color_override = isset( $_POST['node_series_color_override'] ) ? sanitize_text_field( wp_unslash( $_POST['node_series_color_override'] ) ) : '';

		if ( '' === $color_override || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color_override ) ) {
			delete_post_meta( $post_id, NODE_SERIES_COLOR_OVERRIDE_META_KEY );
			return;
		}

		update_post_meta( $post_id, NODE_SERIES_COLOR_OVERRIDE_META_KEY, $color_override );
	}
}

/**
 * 投稿が属する最初の node_series term を返す。
 *
 * @param int $post_id 投稿ID。
 * @return WP_Term|null
 */
function node_series_get_term( int $post_id ): ?WP_Term {
	$terms = get_the_terms( $post_id, 'node_series' );

	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return null;
	}

	return $terms[0];
}

/**
 * シリーズ内の投稿一覧を表示順（_node_series_order昇順→投稿日昇順）で返す。
 *
 * @param int $term_id node_series term ID。
 * @return WP_Post[]
 */
function node_series_get_posts( int $term_id ): array {
	$query = new WP_Query(
		[
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => [
				[
					'taxonomy' => 'node_series',
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
			'meta_key'       => NODE_SERIES_ORDER_META_KEY,
			'orderby'        => [
				'meta_value_num' => 'ASC',
				'date'           => 'ASC',
			],
			'no_found_rows'  => true,
		]
	);

	return $query->posts;
}

/**
 * シリーズ内の登録件数を、投稿ステータスを問わず数える（上限チェック用）。
 *
 * @param int $term_id node_series term ID。
 * @return int
 */
function node_series_count_posts_in_term( int $term_id ): int {
	$query = new WP_Query(
		[
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => [
				[
					'taxonomy' => 'node_series',
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
		]
	);

	return count( $query->posts );
}

/**
 * シリーズ内の前後記事を返す。
 *
 * @param int    $post_id   投稿ID。
 * @param string $direction 'prev' | 'next'。
 * @return WP_Post|null
 */
function node_series_get_adjacent( int $post_id, string $direction ): ?WP_Post {
	$term = node_series_get_term( $post_id );

	if ( null === $term ) {
		return null;
	}

	$posts = node_series_get_posts( $term->term_id );
	$index = null;

	foreach ( $posts as $i => $post ) {
		if ( $post->ID === $post_id ) {
			$index = $i;
			break;
		}
	}

	if ( null === $index ) {
		return null;
	}

	$target_index = 'prev' === $direction ? $index - 1 : $index + 1;

	return $posts[ $target_index ] ?? null;
}

/**
 * テンプレート表示用のシリーズ目次データを返す。
 * シリーズ内の記事が1件以下の場合はnullを返す（タクソノミーの登録自体は維持される）。
 *
 * @param int $post_id 投稿ID。
 * @return array{term: WP_Term, items: array<int, array{id:int, title:string, url:string, is_current:bool}>}|null
 */
function node_series_get_toc_data( int $post_id ): ?array {
	$term = node_series_get_term( $post_id );

	if ( null === $term ) {
		return null;
	}

	$posts = node_series_get_posts( $term->term_id );

	// シリーズ内の記事が1件以下の場合は、タクソノミー上の登録は維持したまま
	// （内部的には登録可能）、記事上にはシリーズ表記を一切表示しない。
	if ( count( $posts ) <= 1 ) {
		return null;
	}

	$items = [];

	foreach ( $posts as $post ) {
		$items[] = [
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'url'        => get_permalink( $post ),
			'is_current' => $post->ID === $post_id,
		];
	}

	return [
		'term'  => $term,
		'items' => $items,
	];
}

/**
 * カード等に表示するための、シリーズ内の位置情報を返す。
 *
 * @param int $post_id 投稿ID。
 * @return array{term: WP_Term, index:int, total:int}|null
 */
function node_series_get_position( int $post_id ): ?array {
	$toc = node_series_get_toc_data( $post_id );

	if ( null === $toc ) {
		return null;
	}

	foreach ( $toc['items'] as $i => $item ) {
		if ( $item['is_current'] ) {
			return [
				'term'  => $toc['term'],
				'index' => $i + 1,
				'total' => count( $toc['items'] ),
			];
		}
	}

	return null;
}

/**
 * 投稿に表示すべきシリーズのプライマリカラーを返す。
 * 優先順位: 記事ごとの上書き色 > シリーズ共通色 > 既定のブランドオレンジ。
 *
 * @param int $post_id 投稿ID。
 * @return string|null 投稿がどのシリーズにも属さない場合はnull。
 */
function node_series_get_color( int $post_id ): ?string {
	$term = node_series_get_term( $post_id );

	if ( null === $term ) {
		return null;
	}

	$override = get_post_meta( $post_id, NODE_SERIES_COLOR_OVERRIDE_META_KEY, true );

	if ( $override ) {
		return $override;
	}

	$term_color = get_term_meta( $term->term_id, NODE_SERIES_COLOR_TERM_META_KEY, true );

	if ( $term_color ) {
		return $term_color;
	}

	return NODE_SERIES_DEFAULT_COLOR;
}

function node_series_init() {
	Node_Series::instance();
}
add_action( 'plugins_loaded', 'node_series_init' );
