<?php
/**
 * Luminous Core Theme Functions
 */

// フォント読み込み設定の追加
require_once get_template_directory() . '/functions_fonts.php';

// カスタムブロックとメディアブリッジの追加
require_once get_template_directory() . '/functions-blocks.php';

// Luminous Core Includes
require_once get_template_directory() . '/inc/hooks.php';
require_once get_template_directory() . '/inc/theme-setup.php';
require_once get_template_directory() . '/inc/meta-boxes.php';
require_once get_template_directory() . '/inc/ajax.php';
require_once get_template_directory() . '/inc/category-meta.php';
require_once get_template_directory() . '/inc/media.php';
require_once get_template_directory() . '/inc/spotlight.php';
require_once get_template_directory() . '/inc/blog-card.php';
require_once get_template_directory() . '/inc/ogp-generator.php';
require_once get_template_directory() . '/inc/utilities.php';
