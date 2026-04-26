<?php
// Nexus: ゲーム情報メタボックス — スタブ
function node_game_info_callback($post) {
    $info = get_post_meta($post->ID, '_node_game_info', true);
    if (!is_array($info)) {
        $info = ['title' => '', 'summary' => '', 'links' => []];
    }
    ?>
    <p><label>タイトル: <input type="text" name="node_game_title" value="<?php echo esc_attr($info['title']); ?>" style="width:100%"></label></p>
    <p><label>要約: <textarea name="node_game_summary" style="width:100%"><?php echo esc_textarea($info['summary']); ?></textarea></label></p>
    <textarea name="node_game_links" style="width:100%;"><?php echo esc_textarea(json_encode($info['links'])); ?></textarea>
    <?php
}
if ( ! defined( 'ABSPATH' ) ) exit;
