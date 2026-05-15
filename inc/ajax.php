<?php
/**
 * Luminous Core AJAX Handlers (Theme side)
 * 
 * Note: AI related handlers have been migrated to the Luminous AI Core plugin.
 */

/**
 * Check for Theme Updates
 */
add_action('wp_ajax_luminous_check_update', function() {
    check_ajax_referer('luminous_update_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    $local_version = wp_get_theme()->get('Version');
    $remote_url = 'https://raw.githubusercontent.com/wingzone94/Node/master/style.css';
    
    $response = wp_remote_get($remote_url);
    if (is_wp_error($response)) wp_send_json_error('Failed to fetch remote version');

    $body = wp_remote_retrieve_body($response);
    preg_match('/Version:\s*([\d\.]+)/i', $body, $matches);
    $remote_version = isset($matches[1]) ? $matches[1] : '0.0.0';

    $update_available = version_compare($remote_version, $local_version, '>');

    wp_send_json_success([
        'local_version' => $local_version,
        'remote_version' => $remote_version,
        'update_available' => $update_available
    ]);
});

/**
 * Install Theme Update
 */
add_action('wp_ajax_luminous_install_update', function() {
    check_ajax_referer('luminous_update_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    // GitHubのZIP直接URL
    $zip_url = 'https://github.com/wingzone94/Node/raw/master/node-theme-production.zip';
    
    error_log('Luminous Update: Starting update from ' . $zip_url);

    // 1. Download ZIP
    $temp_file = download_url($zip_url);
    if (is_wp_error($temp_file)) {
        error_log('Luminous Update: Download failed - ' . $temp_file->get_error_message());
        wp_send_json_error('Download failed: ' . $temp_file->get_error_message());
    }

    // 2. Setup Filesystem
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    WP_Filesystem();
    global $wp_filesystem;
    if (!$wp_filesystem) {
        error_log('Luminous Update: Filesystem API failed');
        wp_send_json_error('Filesystem API failed');
    }

    // 3. Extract to temporary folder
    $theme_dir = get_template_directory();
    $temp_extract_dir = $theme_dir . '_temp_update';
    
    // 既存の一時フォルダがあれば削除
    if ($wp_filesystem->exists($temp_extract_dir)) {
        $wp_filesystem->delete($temp_extract_dir, true);
    }
    
    $unzipped = unzip_file($temp_file, $temp_extract_dir);
    unlink($temp_file); // cleanup zip

    if (is_wp_error($unzipped)) {
        error_log('Luminous Update: Extraction failed - ' . $unzipped->get_error_message());
        wp_send_json_error('Extraction failed: ' . $unzipped->get_error_message());
    }

    // 4. Move files
    // ZIPのルートに直接ファイルがある場合と、フォルダにラップされている場合の両方に対応
    $source_dir = $temp_extract_dir . '/node-theme-production/';
    if (!$wp_filesystem->exists($source_dir)) {
        $source_dir = $temp_extract_dir . '/';
    }

    error_log('Luminous Update: Copying from ' . $source_dir . ' to ' . $theme_dir);
    $copy_result = copy_dir($source_dir, $theme_dir);
    
    // 5. Cleanup
    $wp_filesystem->delete($temp_extract_dir, true);

    if (is_wp_error($copy_result)) {
        error_log('Luminous Update: Copy failed - ' . $copy_result->get_error_message());
        wp_send_json_error('Copy failed: ' . $copy_result->get_error_message());
    }

    error_log('Luminous Update: Update installed successfully to v0.9.0');
    wp_send_json_success('Update installed successfully');
});
