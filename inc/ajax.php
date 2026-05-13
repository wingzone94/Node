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

    $zip_url = 'https://github.com/wingzone94/Node/raw/master/node-theme-production.zip';
    
    // 1. Download ZIP
    $temp_file = download_url($zip_url);
    if (is_wp_error($temp_file)) wp_send_json_error('Download failed: ' . $temp_file->get_error_message());

    // 2. Setup Filesystem
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    WP_Filesystem();
    global $wp_filesystem;
    if (!$wp_filesystem) wp_send_json_error('Filesystem API failed');

    // 3. Extract to temporary folder
    $theme_dir = get_template_directory();
    $temp_extract_dir = $theme_dir . '_temp_update';
    
    $unzipped = unzip_file($temp_file, $temp_extract_dir);
    unlink($temp_file); // cleanup zip

    if (is_wp_error($unzipped)) wp_send_json_error('Extraction failed: ' . $unzipped->get_error_message());

    // 4. Move files from the "node-theme-production" folder inside the zip
    $source_dir = $temp_extract_dir . '/node-theme-production/';
    if (!$wp_filesystem->exists($source_dir)) {
        // ZIP構造が違う場合は抽出先をそのまま使う
        $source_dir = $temp_extract_dir . '/';
    }

    $copy_result = copy_dir($source_dir, $theme_dir);
    
    // 5. Cleanup
    $wp_filesystem->delete($temp_extract_dir, true);

    if (is_wp_error($copy_result)) wp_send_json_error('Copy failed: ' . $copy_result->get_error_message());

    wp_send_json_success('Update installed successfully');
});
