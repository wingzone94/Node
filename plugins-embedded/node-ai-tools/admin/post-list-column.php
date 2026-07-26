<?php
/**
 * 記事一覧のファクトチェック状況カラム
 *
 * 一覧を見ただけで「未実施の記事」と「未対応の指摘が残っている記事」が分かるようにする。
 *   ⚠️      = ファクトチェック未実施
 *   🚨XX    = 未対応の指摘が XX 件（要確認 / おそらく不正確）
 *
 * @package Node_AI_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'node_ai_count_unresolved_claims' ) ) {
	/**
	 * 未対応の指摘件数を数える。
	 *
	 * 「おそらく正確」「検証困難」は編集者の対応を要さないため数えない。
	 *
	 * @param array<string, mixed> $data 保存済みファクトチェック結果。
	 * @return int
	 */
	function node_ai_count_unresolved_claims( array $data ): int {
		$count = 0;

		foreach ( (array) ( $data['claims'] ?? array() ) as $claim ) {
			if ( ! is_array( $claim ) ) {
				continue;
			}

			if ( in_array( (string) ( $claim['status'] ?? '' ), array( 'uncertain', 'likely_incorrect' ), true ) ) {
				$count++;
			}
		}

		return $count;
	}
}

if ( ! function_exists( 'node_ai_get_fact_check_state' ) ) {
	/**
	 * 記事のファクトチェック状態を返す。
	 *
	 * @param int $post_id 投稿ID。
	 * @return array{state: string, unresolved: int} state は 'none' | 'unresolved' | 'clear'。
	 */
	function node_ai_get_fact_check_state( int $post_id ): array {
		$data = function_exists( 'node_ai_get_fact_check_data' ) ? node_ai_get_fact_check_data( $post_id ) : null;

		if ( null === $data ) {
			return array(
				'state'      => 'none',
				'unresolved' => 0,
			);
		}

		$unresolved = node_ai_count_unresolved_claims( $data );

		return array(
			'state'      => $unresolved > 0 ? 'unresolved' : 'clear',
			'unresolved' => $unresolved,
		);
	}
}

if ( ! function_exists( 'node_ai_add_fact_check_column' ) ) {
	/**
	 * 一覧にカラムを追加する（タイトルの直後）。
	 *
	 * @param array<string, string> $columns 既存カラム。
	 * @return array<string, string>
	 */
	function node_ai_add_fact_check_column( array $columns ): array {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['node_fact_check'] = 'FC';
			}
		}

		// title が無いレイアウトでも必ず出す
		if ( ! isset( $new['node_fact_check'] ) ) {
			$new['node_fact_check'] = 'FC';
		}

		return $new;
	}
}

if ( ! function_exists( 'node_ai_render_fact_check_column' ) ) {
	/**
	 * カラムの中身を描画する。
	 *
	 * @param string $column  カラムキー。
	 * @param int    $post_id 投稿ID。
	 */
	function node_ai_render_fact_check_column( $column, $post_id ): void {
		if ( 'node_fact_check' !== $column ) {
			return;
		}

		$status = node_ai_get_fact_check_state( (int) $post_id );

		if ( 'none' === $status['state'] ) {
			printf(
				'<span class="node-fc-flag node-fc-flag--none" title="%s" aria-label="%s">⚠️</span>',
				esc_attr__( 'ファクトチェック未実施', 'node-ai-tools' ),
				esc_attr__( 'ファクトチェック未実施', 'node-ai-tools' )
			);
			return;
		}

		if ( 'unresolved' === $status['state'] ) {
			$label = sprintf(
				/* translators: %d: 未対応の指摘件数 */
				__( '未対応の指摘が %d 件あります', 'node-ai-tools' ),
				$status['unresolved']
			);

			printf(
				'<span class="node-fc-flag node-fc-flag--unresolved" title="%s" aria-label="%s">🚨%d</span>',
				esc_attr( $label ),
				esc_attr( $label ),
				(int) $status['unresolved']
			);
			return;
		}

		printf(
			'<span class="node-fc-flag node-fc-flag--clear" title="%s" aria-label="%s">✅</span>',
			esc_attr__( '未対応の指摘はありません', 'node-ai-tools' ),
			esc_attr__( '未対応の指摘はありません', 'node-ai-tools' )
		);
	}
}

if ( ! function_exists( 'node_ai_fact_check_column_style' ) ) {
	/**
	 * カラム幅と体裁。
	 */
	function node_ai_fact_check_column_style(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-post' !== $screen->id ) {
			return;
		}
		?>
		<style>
			.column-node_fact_check { width: 68px; text-align: center; }
			.node-fc-flag { font-size: 14px; line-height: 1; white-space: nowrap; }
			.node-fc-flag--unresolved { font-weight: 700; color: #d63638; }
			.node-fc-flag--clear { opacity: 0.45; }
		</style>
		<?php
	}
}

add_filter( 'manage_post_posts_columns', 'node_ai_add_fact_check_column' );
add_action( 'manage_post_posts_custom_column', 'node_ai_render_fact_check_column', 10, 2 );
add_action( 'admin_head', 'node_ai_fact_check_column_style' );
