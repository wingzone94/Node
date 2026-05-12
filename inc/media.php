<?php
// ==========================================================================
// 4. メディアライブラリ: AI生成メディアのメタデータ追加
// ==========================================================================

// メディアライブラリの編集画面にAI生成チェックボックスを追加
function node_add_attachment_ai_field($form_fields, $post) {
    $is_ai = get_post_meta($post->ID, '_node_is_ai_media', true);
    $form_fields['node_is_ai_media'] = array(
        'label' => 'AI生成メディア',
        'input' => 'html',
        'html'  => '<label><input type="checkbox" name="attachments[' . $post->ID . '][node_is_ai_media]" value="1" ' . checked($is_ai, '1', false) . ' /> この画像/動画はAIによって生成された</label>',
    );
    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'node_add_attachment_ai_field', 10, 2);

// メディアライブラリでのAI生成チェックボックスの保存
function node_save_attachment_ai_field($post, $attachment) {
    if (isset($attachment['node_is_ai_media'])) {
        update_post_meta($post['ID'], '_node_is_ai_media', '1');
    } else {
        delete_post_meta($post['ID'], '_node_is_ai_media');
    }
    return $post;
}
add_filter('attachment_fields_to_save', 'node_save_attachment_ai_field', 10, 2);

// REST APIでアタッチメントのメタデータを操作可能にする
function node_register_attachment_meta() {
    register_meta('post', '_node_is_ai_media', [
        'object_subtype' => 'attachment',
        'type'           => 'boolean',
        'single'         => true,
        'show_in_rest'   => true,
    ]);
}
add_action('init', 'node_register_attachment_meta');

// ==========================================================================
// 5. 画像最適化: 不要な画像サイズの生成停止 & JPEG品質調整
// ==========================================================================

/**
 * 不要な画像サイズの生成を停止する (サーバー容量の節約)
 */
function node_filter_image_sizes($sizes) {
    // 使わない巨大なサイズをリストから除外
    unset($sizes['1536x1536']);
    unset($sizes['2048x2048']);
    return $sizes;
}
add_filter('intermediate_image_sizes_advanced', 'node_filter_image_sizes');

/**
 * 巨大な画像の自動縮小時 (Big Image Threshold) の設定
 * 2560px 以上の画像がアップロードされた際に自動縮小される。
 */
add_filter('big_image_size_threshold', function() { return 2000; }); // 最大幅を 2000px に制限

/**
 * JPEG の圧縮品質を 80% に設定
 */
add_filter('jpeg_quality', function() { return 80; });



// ==========================================================================
// 6. パフォーマンス: LCP画像のPreload（事前読み込み）
// ==========================================================================

/**
 * ヒーロー画像（アイキャッチ画像）のPreloadヘッダーを出力し、LCPを改善する
 */
function node_preload_hero_image() {
    // 記事/固定ページでアイキャッチ画像が設定されている場合
    if ( is_singular() && has_post_thumbnail() ) {
        $post_id = get_the_ID();
        
        // アイキャッチ画像のURLとsrcset属性を取得してPreloadする
        $attachment_id = get_post_thumbnail_id( $post_id );
        $image_src     = wp_get_attachment_image_src( $attachment_id, 'full' );
        $image_srcset  = wp_get_attachment_image_srcset( $attachment_id, 'full' );
        $image_sizes   = wp_get_attachment_image_sizes( $attachment_id, 'full' );

        if ( $image_src ) {
            $url = $image_src[0];
            echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '"';
            if ( $image_srcset ) {
                echo ' imagesrcset="' . esc_attr( $image_srcset ) . '"';
            }
            if ( $image_sizes ) {
                echo ' imagesizes="' . esc_attr( $image_sizes ) . '"';
            }
            echo ">\n";
        }
    }
}
add_action( 'wp_head', 'node_preload_hero_image', 1 );


