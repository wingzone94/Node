<?php
/**
 * Luminous Core AJAX Handlers (Theme side)
 *
 * Note: AI related handlers have been migrated to the Luminous AI Core plugin.
 */

/**
 * Check for Theme Updates
 */
add_action(
	'wp_ajax_luminous_check_update',
	function () {
		check_ajax_referer( 'luminous_update_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$local_version = node_get_theme_version();
		$remote_url    = 'https://raw.githubusercontent.com/wingzone94/Node/master/style.css';

		$response = wp_remote_get( $remote_url );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to fetch remote version' );
		}

		$body = wp_remote_retrieve_body( $response );
		preg_match( '/Version:\s*([\d\.]+)/i', $body, $matches );
		$remote_version = isset( $matches[1] ) ? $matches[1] : '0.0.0';

		$update_available = version_compare( $remote_version, $local_version, '>' );
		$install_available = version_compare( $remote_version, $local_version, '>=' );

		wp_send_json_success(
			array(
				'local_version'    => $local_version,
				'remote_version'   => $remote_version,
				'update_available'  => $update_available,
				'install_available' => $install_available,
				'same_version'      => ! $update_available && $install_available,
			)
		);
	}
);

/**
 * Install Theme Update
 */
add_action(
	'wp_ajax_luminous_install_update',
	function () {
		check_ajax_referer( 'luminous_update_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$zip_url = 'https://github.com/wingzone94/Node/raw/master/node.zip';

		error_log( 'Luminous Update: Starting update from ' . $zip_url );

		$temp_file = download_url( $zip_url );
		if ( is_wp_error( $temp_file ) ) {
			error_log( 'Luminous Update: Download failed - ' . $temp_file->get_error_message() );
			wp_send_json_error( 'Download failed: ' . $temp_file->get_error_message() );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			error_log( 'Luminous Update: Filesystem API failed' );
			wp_send_json_error( 'Filesystem API failed' );
		}

		$theme_dir        = get_template_directory();
		$temp_extract_dir = $theme_dir . '_temp_update';

		if ( $wp_filesystem->exists( $temp_extract_dir ) ) {
			$wp_filesystem->delete( $temp_extract_dir, true );
		}

		$unzipped = unzip_file( $temp_file, $temp_extract_dir );
		unlink( $temp_file );

		if ( is_wp_error( $unzipped ) ) {
			error_log( 'Luminous Update: Extraction failed - ' . $unzipped->get_error_message() );
			wp_send_json_error( 'Extraction failed: ' . $unzipped->get_error_message() );
		}

		$source_dir = node_resolve_theme_update_source_dir( $temp_extract_dir );
		if ( null === $source_dir ) {
			$wp_filesystem->delete( $temp_extract_dir, true );
			error_log( 'Luminous Update: Could not locate Node theme folder inside ZIP' );
			wp_send_json_error( 'ZIP 内に Node テーマフォルダが見つかりませんでした。' );
		}

		error_log( 'Luminous Update: Copying from ' . $source_dir . ' to ' . $theme_dir );
		$copy_result = copy_dir( $source_dir, $theme_dir );

		$wp_filesystem->delete( $temp_extract_dir, true );

		if ( is_wp_error( $copy_result ) ) {
			error_log( 'Luminous Update: Copy failed - ' . $copy_result->get_error_message() );
			wp_send_json_error( 'Copy failed: ' . $copy_result->get_error_message() );
		}

		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache();
		}

		error_log( 'Luminous Update: Update installed successfully' );
		wp_send_json_success( 'Update installed successfully' );
	}
);
