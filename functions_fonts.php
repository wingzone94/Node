<?php
/**
 * テーマのフォント読み込み設定 (functions.php に追加)
 */
function node_enqueue_fonts() {
    // Google Fonts: Inter (Latin) & Noto Sans JP (Japanese)
    // Variable font axes: wght (400-900)
    wp_enqueue_style( 'google-fonts', 'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800;900&family=Inter:wght@400;500;700;900&family=Noto+Sans+JP:wght@400;500;700;900&display=swap', array(), null );
    
    // Material Symbols Outlined (opsz, wght, FILL, GRAD をサポート)
    wp_enqueue_style( 'material-symbols-outlined', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200', array(), null );

    // Font Awesome 6.5.1
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
}
add_action( 'wp_enqueue_scripts', 'node_enqueue_fonts' );
