<?php
/**
 * Gemini API 設定 — ライター個別（user_meta）
 *
 * @package Luminous_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 指定ユーザーの Gemini 設定を表示・編集できるか
 *
 * @param int $target_user_id 対象ユーザーID。
 */
function node_can_edit_gemini_settings( int $target_user_id ): bool {
	$current_user_id = get_current_user_id();

	if ( $target_user_id === $current_user_id ) {
		return current_user_can( 'edit_posts' );
	}

	return current_user_can( 'edit_user', $target_user_id ) && user_can( $target_user_id, 'edit_posts' );
}

/**
 * プロフィールに Gemini 設定欄を出すか（投稿権限のあるユーザーのみ）
 *
 * @param int $target_user_id 対象ユーザーID。
 */
function node_should_show_gemini_profile_fields( int $target_user_id ): bool {
	if ( ! user_can( $target_user_id, 'edit_posts' ) ) {
		return false;
	}

	if ( get_current_user_id() === $target_user_id ) {
		return current_user_can( 'edit_posts' );
	}

	return current_user_can( 'edit_user', $target_user_id );
}

/**
 * user_meta から API キーを取得
 *
 * @param int $user_id ユーザーID。0 の場合は現在のユーザー。
 */
function node_get_user_gemini_api_key( int $user_id = 0 ): string {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	if ( $user_id <= 0 ) {
		return '';
	}

	$key = get_user_meta( $user_id, 'node_gemini_api_key', true );
	return is_string( $key ) ? trim( $key ) : '';
}

/**
 * user_meta からモデル名を取得
 *
 * @param int $user_id ユーザーID。0 の場合は現在のユーザー。
 */
function node_get_user_gemini_model( int $user_id = 0 ): string {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	$model   = $user_id > 0 ? get_user_meta( $user_id, 'node_gemini_model', true ) : '';
	$model   = is_string( $model ) ? trim( $model ) : '';

	if ( '' !== $model && node_is_valid_gemini_model_id( $model ) ) {
		return $model;
	}

	return node_get_default_gemini_model();
}

/**
 * Gemini 設定フィールドを出力
 *
 * @param WP_User $user           対象ユーザー。
 * @param bool    $is_settings_page Luminous Settings 用の文言切り替え。
 */
function node_render_gemini_user_fields( WP_User $user, bool $is_settings_page = false ): void {
	if ( ! node_can_edit_gemini_settings( $user->ID ) ) {
		return;
	}

	$has_key        = '' !== node_get_user_gemini_api_key( $user->ID );
	$current_model  = node_get_user_gemini_model( $user->ID );
	$model_options  = node_get_gemini_model_options_for_user( $user->ID );
	$models_meta    = node_get_gemini_models_meta( $user->ID );
	$is_own         = get_current_user_id() === (int) $user->ID;
	?>
	<h2><?php esc_html_e( 'Gemini API（個人設定）', 'node' ); ?></h2>
	<p class="description">
		<?php if ( $is_settings_page && $is_own ) : ?>
			<?php esc_html_e( 'この API キーはあなた専用です。他のライターとは共有されません。', 'node' ); ?>
		<?php elseif ( $is_own ) : ?>
			<?php esc_html_e( 'AI 要約・ファクトチェックで使用する、あなた専用の Gemini API キーです。', 'node' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'このライター個人の Gemini API 設定です。', 'node' ); ?>
		<?php endif; ?>
		<?php if ( ! $is_settings_page && $is_own ) : ?>
			<br><?php esc_html_e( '設定場所: ユーザー → プロフィール', 'node' ); ?>
		<?php endif; ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="node_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'node' ); ?></label></th>
			<td>
				<input
					type="password"
					name="node_gemini_api_key"
					id="node_gemini_api_key"
					class="regular-text"
					value=""
					autocomplete="new-password"
					placeholder="<?php echo $has_key ? esc_attr__( '登録済み（変更する場合のみ入力）', 'node' ) : esc_attr__( 'API キーを入力', 'node' ); ?>"
				/>
				<?php if ( $has_key ) : ?>
					<p class="description">
						<?php esc_html_e( 'キーは登録済みです。変更しない場合は空欄のまま保存してください。', 'node' ); ?>
					</p>
					<label>
						<input type="checkbox" name="node_gemini_api_key_clear" value="1" />
						<?php esc_html_e( 'API キーを削除する', 'node' ); ?>
					</label>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e( 'Google AI Studio 等で発行したキーを入力してください。', 'node' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="node_gemini_model"><?php esc_html_e( 'Gemini Model', 'node' ); ?></label></th>
			<td>
				<select name="node_gemini_model" id="node_gemini_model">
					<?php foreach ( $model_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_model, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'AI 要約・ファクトチェックで使用するモデルです。', 'node' ); ?>
					<?php if ( $models_meta['from_api'] && $models_meta['fetched_at'] > 0 ) : ?>
						<br>
						<?php
						printf(
							/* translators: %s: human-readable time difference */
							esc_html__( '一覧は Gemini API から取得済み（%s 前）', 'node' ),
							esc_html( human_time_diff( $models_meta['fetched_at'], time() ) )
						);
						?>
					<?php elseif ( ! $has_key ) : ?>
						<br><?php esc_html_e( 'API キーを登録すると、利用可能な最新モデルを自動取得します。', 'node' ); ?>
					<?php else : ?>
						<br><?php esc_html_e( 'API からの取得に失敗したため、フォールバック一覧を表示しています。', 'node' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Gemini 設定を user_meta に保存
 *
 * @param int $user_id 保存対象ユーザーID。
 */
function node_save_gemini_user_meta( int $user_id ): void {
	if ( $user_id <= 0 || ! node_can_edit_gemini_settings( $user_id ) ) {
		return;
	}

	$key_changed = false;

	if ( ! empty( $_POST['node_gemini_api_key_clear'] ) ) {
		delete_user_meta( $user_id, 'node_gemini_api_key' );
		$key_changed = true;
	} elseif ( isset( $_POST['node_gemini_api_key'] ) ) {
		$new_key = sanitize_text_field( wp_unslash( (string) $_POST['node_gemini_api_key'] ) );
		if ( '' !== $new_key ) {
			update_user_meta( $user_id, 'node_gemini_api_key', $new_key );
			$key_changed = true;
		}
	}

	if ( $key_changed ) {
		node_clear_gemini_models_cache();
	}

	if ( isset( $_POST['node_gemini_model'] ) ) {
		$model   = sanitize_text_field( wp_unslash( (string) $_POST['node_gemini_model'] ) );
		$options = node_get_gemini_model_options_for_user( $user_id );
		if ( isset( $options[ $model ] ) || node_is_valid_gemini_model_id( $model ) ) {
			update_user_meta( $user_id, 'node_gemini_model', $model );
		}
	}
}

/**
 * 自分のプロフィール画面
 *
 * @param WP_User $user ユーザー。
 */
function node_render_gemini_profile_fields( WP_User $user ): void {
	if ( ! node_should_show_gemini_profile_fields( $user->ID ) ) {
		return;
	}

	node_render_gemini_user_fields( $user, false );
}

/**
 * プロフィール保存（自分）
 *
 * @param int $user_id ユーザーID。
 */
function node_save_gemini_profile_fields( int $user_id ): void {
	node_save_gemini_user_meta( $user_id );
}

add_action( 'show_user_profile', 'node_render_gemini_profile_fields' );
add_action( 'edit_user_profile', 'node_render_gemini_profile_fields' );
add_action( 'personal_options_update', 'node_save_gemini_profile_fields' );
add_action( 'edit_user_profile_update', 'node_save_gemini_profile_fields' );

/**
 * Luminous Settings からの保存（管理者自身のキー）
 */
function node_save_user_gemini_settings_from_options_page(): void {
	if ( ! isset( $_POST['node_gemini_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['node_gemini_settings_nonce'] ) ), 'node_save_gemini' ) ) {
		return;
	}

	node_save_gemini_user_meta( get_current_user_id() );
}
add_action( 'admin_init', 'node_save_user_gemini_settings_from_options_page' );
