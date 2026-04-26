<?php
/**
 * スポイラー（目隠し）機能
 *
 * @package Luminous_Interactivity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * スポイラーショートコード [spoiler]ネタバレ内容[/spoiler]
 */
function luminous_spoiler_shortcode( $atts, $content = '' ) {
    if ( empty( $content ) ) {
        return '';
    }

    // ネストされたショートコードを処理
    $content = do_shortcode( $content );

    ob_start();
    ?>
    <span class="node-spoiler" 
          role="button" 
          aria-expanded="false" 
          title="クリックで表示" 
          tabindex="0">
        <span class="node-spoiler__content">
            <?php echo $content; ?>
        </span>
    </span>
    <?php
    return ob_get_clean();
}
