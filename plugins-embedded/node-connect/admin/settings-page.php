<?php
/**
 * Node Connect 外部連携設定画面。
 *
 * Webhook URL はフロントHTMLへ一切出力せず、この画面（manage_options 限定）でも
 * 末尾数文字以外はマスク表示する。保存済みURLを input value として再出力しない。
 *
 * @package Node_Connect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	static function (): void {
		add_options_page(
			'外部連携設定',
			'外部連携',
			'manage_options',
			'node-connect',
			'node_connect_render_settings_page'
		);
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'node_connect_group',
			Node_Connect_Event_Bus::OPTION_ENABLED,
			[
				'sanitize_callback' => static fn( $value ) => '1' === (string) $value ? '1' : '',
			]
		);
		register_setting(
			'node_connect_group',
			Node_Connect_Event_Bus::OPTION_PAUSED,
			[
				'sanitize_callback' => static fn( $value ) => '1' === (string) $value ? '1' : '',
			]
		);
		register_setting(
			'node_connect_group',
			Node_Connect_Event_Bus::OPTION_WEBHOOKS,
			[
				'sanitize_callback' => 'node_connect_sanitize_webhooks',
			]
		);
	}
);

/**
 * Webhook設定の保存。URL欄が空なら保存済みURLを維持する（マスク表示のため
 * 画面には既存URLを出さない方式の裏側）。「削除」チェックで行ごと破棄。
 *
 * @param mixed $input
 * @return array<int, array{label: string, url: string, events: string[], enabled: bool}>
 */
function node_connect_sanitize_webhooks( $input ): array {
	$existing  = Node_Connect_Event_Bus::get_webhooks();
	$catalog   = array_keys( Node_Connect_Event_Bus::get_event_catalog() );
	$sanitized = [];

	if ( ! is_array( $input ) ) {
		return $existing;
	}

	for ( $i = 0; $i < NODE_CONNECT_MAX_WEBHOOKS; $i++ ) {
		$row = is_array( $input[ $i ] ?? null ) ? $input[ $i ] : [];

		if ( ! empty( $row['remove'] ) ) {
			continue;
		}

		$url = trim( (string) ( $row['url'] ?? '' ) );
		if ( '' !== $url ) {
			$url = sanitize_url( $url );
			if ( ! str_starts_with( $url, 'https://' ) ) {
				$url = '';
			}
		}
		if ( '' === $url ) {
			$url = $existing[ $i ]['url'] ?? '';
		}
		if ( '' === $url ) {
			continue;
		}

		$sanitized[] = [
			'label'   => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			'url'     => $url,
			'events'  => array_values( array_intersect( array_map( 'strval', (array) ( $row['events'] ?? [] ) ), $catalog ) ),
			'enabled' => ! empty( $row['enabled'] ),
		];
	}

	return $sanitized;
}

/**
 * URLの末尾数文字以外をマスクする。
 */
function node_connect_mask_url( string $url ): string {
	$tail = mb_substr( $url, -6 );
	return 'https://…' . $tail;
}

/**
 * 接続テスト（admin-post）。テストEmbedを同期送信して結果を通知する。
 */
add_action(
	'admin_post_node_connect_test',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'node_connect_test' );

		$index  = isset( $_GET['webhook'] ) ? (int) $_GET['webhook'] : 0;
		$result = Node_Connect_Webhook_Sender::send_test( $index );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'        => 'node-connect',
					'test_result' => $result['ok'] ? 'ok' : 'ng',
					'test_status' => rawurlencode( $result['status'] ),
				],
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
);

/**
 * 送信履歴のクリア（admin-post）。
 */
add_action(
	'admin_post_node_connect_clear_log',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'node_connect_clear_log' );

		Node_Connect_Delivery_Log::clear();

		wp_safe_redirect( add_query_arg( [ 'page' => 'node-connect' ], admin_url( 'options-general.php' ) ) );
		exit;
	}
);

/**
 * 設定画面の描画。
 */
function node_connect_render_settings_page(): void {
	$enabled  = Node_Connect_Event_Bus::is_enabled();
	$paused   = Node_Connect_Event_Bus::is_paused();
	$webhooks = Node_Connect_Event_Bus::get_webhooks();
	$catalog  = Node_Connect_Event_Bus::get_event_catalog();
	$log      = Node_Connect_Delivery_Log::get();

	$card_style = 'background: #fff; padding: 25px; border-radius: 16px; margin-bottom: 25px; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);';
	$h2_style   = 'margin-top: 0; color: #FF9900; display: flex; align-items: center; gap: 10px;';
	?>
	<div class="wrap">
		<h1 style="margin-bottom: 30px;">外部連携設定（Node Connect）</h1>

		<?php if ( isset( $_GET['test_result'] ) ) : ?>
			<?php $test_ok = 'ok' === $_GET['test_result']; ?>
			<div class="notice <?php echo $test_ok ? 'notice-success' : 'notice-error'; ?> is-dismissible">
				<p>
					<?php echo $test_ok ? '✅ テスト通知を送信しました。Discord側で受信を確認してください。' : '❌ テスト通知の送信に失敗しました。'; ?>
					（<?php echo esc_html( rawurldecode( (string) ( $_GET['test_status'] ?? '' ) ) ); ?>）
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'node_connect_group' ); ?>

			<div class="m3-admin-card" style="<?php echo esc_attr( $card_style ); ?>">
				<h2 style="<?php echo esc_attr( $h2_style ); ?>">
					<span class="dashicons dashicons-share-alt2"></span> 全体設定
				</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Webhook通知</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Node_Connect_Event_Bus::OPTION_ENABLED ); ?>" value="1" <?php checked( $enabled ); ?> />
								有効にする
							</label>
							<p class="description">オフにすると、すべてのイベント通知が送信されません。</p>
						</td>
					</tr>
					<tr>
						<th scope="row">一時停止</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Node_Connect_Event_Bus::OPTION_PAUSED ); ?>" value="1" <?php checked( $paused ); ?> />
								すべての通知を一時停止する
							</label>
							<p class="description">設定を残したまま送信だけを止めたいとき（メンテナンス作業中など）に使います。</p>
						</td>
					</tr>
				</table>
			</div>

			<?php for ( $i = 0; $i < NODE_CONNECT_MAX_WEBHOOKS; $i++ ) : ?>
				<?php
				$webhook   = $webhooks[ $i ] ?? null;
				$row_label = $webhook['label'] ?? '';
				?>
				<div class="m3-admin-card" style="<?php echo esc_attr( $card_style ); ?>">
					<h2 style="<?php echo esc_attr( $h2_style ); ?>">
						<span class="dashicons dashicons-admin-links"></span> Webhook <?php echo (int) ( $i + 1 ); ?>
						<?php if ( null !== $webhook && ! $webhook['enabled'] ) : ?>
							<span style="font-size: 12px; color: #999; font-weight: normal;">（停止中）</span>
						<?php endif; ?>
					</h2>
					<table class="form-table">
						<tr>
							<th scope="row">ラベル</th>
							<td>
								<input type="text" name="node_connect_webhooks[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $row_label ); ?>" class="regular-text" placeholder="例: 新着通知用チャンネル" />
								<p class="description">送信履歴に表示される宛先の名前です。</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Discord Webhook URL</th>
							<td>
								<?php if ( null !== $webhook && '' !== $webhook['url'] ) : ?>
									<p style="margin-top: 0;"><code><?php echo esc_html( node_connect_mask_url( $webhook['url'] ) ); ?></code>（設定済み）</p>
								<?php endif; ?>
								<input type="url" name="node_connect_webhooks[<?php echo (int) $i; ?>][url]" value="" class="large-text" placeholder="<?php echo ( null !== $webhook && '' !== $webhook['url'] ) ? '変更する場合のみ入力' : 'https://discord.com/api/webhooks/…'; ?>" autocomplete="off" />
								<p class="description">Discordのチャンネル設定 → 連携サービス → ウェブフックで発行したURLを貼り付けます（https:// のみ）。</p>
								<?php if ( null !== $webhook && '' !== $webhook['url'] ) : ?>
									<label>
										<input type="checkbox" name="node_connect_webhooks[<?php echo (int) $i; ?>][remove]" value="1" />
										このWebhookを削除する
									</label>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">このWebhookの通知</th>
							<td>
								<label>
									<input type="checkbox" name="node_connect_webhooks[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked( null === $webhook || $webhook['enabled'] ); ?> />
									有効にする
								</label>
								<p class="description">オフにすると、このWebhookだけ送信を止められます。</p>
							</td>
						</tr>
						<tr>
							<th scope="row">通知するイベント</th>
							<td>
								<fieldset>
									<?php foreach ( $catalog as $event_id => $event_label ) : ?>
										<label style="display: block; margin-bottom: 4px;">
											<input type="checkbox" name="node_connect_webhooks[<?php echo (int) $i; ?>][events][]" value="<?php echo esc_attr( $event_id ); ?>" <?php checked( null !== $webhook && in_array( $event_id, $webhook['events'], true ) ); ?> />
											<?php echo esc_html( $event_label ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description">チェックしたイベントだけが、このWebhookへ送信されます。AI関連のイベントは今後のアップデートで発火します。</p>
							</td>
						</tr>
						<?php if ( null !== $webhook && '' !== $webhook['url'] ) : ?>
							<tr>
								<th scope="row">接続テスト</th>
								<td>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=node_connect_test&webhook=' . $i ), 'node_connect_test' ) ); ?>" class="button">テスト通知を送信</a>
									<p class="description">保存済みの設定でテストEmbedを送信します（変更がある場合は先に保存してください）。</p>
								</td>
							</tr>
						<?php endif; ?>
					</table>
				</div>
			<?php endfor; ?>

			<?php submit_button( '設定を保存' ); ?>
		</form>

		<div class="m3-admin-card" style="<?php echo esc_attr( $card_style ); ?>">
			<h2 style="<?php echo esc_attr( $h2_style ); ?>">
				<span class="dashicons dashicons-list-view"></span> 送信履歴（直近<?php echo (int) Node_Connect_Delivery_Log::MAX_ENTRIES; ?>件）
			</h2>
			<?php if ( [] === $log ) : ?>
				<p>まだ送信履歴はありません。</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>日時</th>
							<th>イベント</th>
							<th>宛先</th>
							<th>結果</th>
							<th>試行</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $log as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( Node_Connect_Event_Bus::get_event_catalog()[ $entry['event'] ?? '' ] ?? (string) ( $entry['event'] ?? '' ) ); ?></td>
								<td><?php echo esc_html( (string) ( $entry['label'] ?? '' ) ); ?></td>
								<td><?php echo ! empty( $entry['ok'] ) ? '✅' : '❌'; ?> <?php echo esc_html( (string) ( $entry['status'] ?? '' ) ); ?></td>
								<td><?php echo (int) ( $entry['attempt'] ?? 1 ); ?>回目</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top: 12px;">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=node_connect_clear_log' ), 'node_connect_clear_log' ) ); ?>" class="button">履歴をクリア</a>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
