<?php
/**
 * Node Custom Scheduler & Missed Schedule Fixer
 * 
 * 1. Missed Schedule Fix: 予約投稿の失敗を自動検知して公開
 * 2. AI Task on Publish: 予約投稿が公開された瞬間にAI要約を生成
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Missed Schedule Fixer
 * サイト訪問時や管理画面操作時に、公開時間を過ぎている予約投稿を強制的に公開します。
 */
function node_check_missed_schedules() {
    // 5分に1回程度実行されるように一時的なロック（負荷対策）
    if ( false !== get_transient( 'node_missed_schedule_lock' ) ) {
        return;
    }
    set_transient( 'node_missed_schedule_lock', '1', 300 );

    global $wpdb;
    $now = current_time( 'mysql' );

    // 公開時間を過ぎている future ステータスの投稿を取得
    $missed_posts = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_status = 'future' AND post_date <= %s",
        $now
    ) );

    if ( ! empty( $missed_posts ) ) {
        foreach ( $missed_posts as $post_id ) {
            wp_publish_post( $post_id );
        }
    }
}
add_action( 'init', 'node_check_missed_schedules' );

/**
 * 2. AI Task on Publish & X (Twitter) Auto-post
 * 記事が公開された際、自動的にAI要約を生成し、Xに投稿します。
 */
function node_on_post_published( $new_status, $old_status, $post ) {
    // 記事が公開ステータスになった時（新規公開または予約投稿の公開）
    if ( $new_status === 'publish' && $old_status !== 'publish' && $post->post_type === 'post' ) {
        
        // --- AI 要約の生成 ---
        $existing = get_post_meta( $post->ID, '_node_ai_summary', true );
        if ( empty( $existing ) && class_exists( 'Node_AI_Tools' ) ) {
            Node_AI_Tools::instance()->auto_generate_ai_summary( $post->ID, $post, true );
        }

        // --- X (Twitter) 自動投稿 ---
        try {
            node_post_to_x( $post->ID );
        } catch ( Exception $e ) {
            error_log( 'X Posting Error: ' . $e->getMessage() );
        }
    }
}
add_action( 'transition_post_status', 'node_on_post_published', 10, 3 );

/**
 * X (Twitter) API v2 への投稿ロジック
 */
function node_post_to_x( $post_id ) {
    $api_key             = get_option( 'node_x_api_key' );
    $api_secret          = get_option( 'node_x_api_secret' );
    $access_token        = get_option( 'node_x_access_token' );
    $access_token_secret = get_option( 'node_x_access_token_secret' );

    if ( ! $api_key || ! $api_secret || ! $access_token || ! $access_token_secret ) {
        return;
    }

    // すでに投稿済みかチェック（二重投稿防止）
    if ( get_post_meta( $post_id, '_node_x_posted', true ) ) {
        return;
    }

    $title = get_the_title( $post_id );
    $url   = get_permalink( $post_id );
    
    // AI要約を取得、なければ通常の抜粋を使用
    $summary = get_post_meta( $post_id, '_node_ai_summary', true );
    if ( empty( $summary ) ) {
        $summary = get_the_excerpt( $post_id );
    }
    $clean_summary = wp_strip_all_tags( $summary );
    $excerpt = mb_strimwidth( $clean_summary, 0, 160, "...", "UTF-8" );

    // カテゴリ（ハッシュタグ用）
    $categories = get_the_category( $post_id );
    $category_name = ( ! empty( $categories ) ) ? $categories[0]->name : 'Node';

    // テンプレートの取得と置換（AIを使わない純粋な置換）
    $template = get_option( 'node_x_post_template', "【新着記事】{{title}}\n\n{{summary}}\n\n続きはこちら： {{url}}\n#Node #{{category}}" );
    $text = str_replace(
        [ '{{title}}', '{{url}}', '{{summary}}', '{{category}}' ],
        [ $title, $url, $excerpt, $category_name ],
        $template
    );

    $url_api = 'https://api.twitter.com/2/tweets';
    $method  = 'POST';

    // OAuth 1.0a 署名の作成
    $nonce = wp_generate_password( 32, false );
    $timestamp = time();
    
    $oauth = [
        'oauth_consumer_key'     => $api_key,
        'oauth_nonce'            => $nonce,
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp'        => $timestamp,
        'oauth_token'            => $access_token,
        'oauth_version'          => '1.0',
    ];

    $base_params = $oauth;
    $base_string = $method . '&' . rawurlencode( $url_api ) . '&' . rawurlencode( http_build_query( $base_params, '', '&', PHP_QUERY_RFC3986 ) );
    $composite_key = rawurlencode( $api_secret ) . '&' . rawurlencode( $access_token_secret );
    $oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base_string, $composite_key, true ) );

    $auth_header = 'OAuth ';
    $values = [];
    foreach ( $oauth as $key => $value ) {
        $values[] = rawurlencode( $key ) . '="' . rawurlencode( $value ) . '"';
    }
    $auth_header .= implode( ', ', $values );

    $response = wp_remote_post( $url_api, [
        'headers' => [
            'Authorization' => $auth_header,
            'Content-Type'  => 'application/json; charset=utf-8',
        ],
        'body'    => json_encode( [ 'text' => $text ] ),
    ] );

    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 201 ) {
        update_post_meta( $post_id, '_node_x_posted', time() );
    }
}

/**
 * 3. X (Twitter) 連携用専用 RSS フィード (予備として維持)
 */
function node_add_x_rss_feed() {
    add_feed( 'x-post', 'node_render_x_rss_feed' );
}
add_action( 'init', 'node_add_x_rss_feed' );

function node_render_x_rss_feed() {
    $posts = get_posts( [
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 5,
    ] );

    header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
    echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?' . '>';
    ?>
    <rss version="2.0">
    <channel>
        <title><?php bloginfo_rss( 'name' ); ?> - X Auto Post Feed</title>
        <link><?php bloginfo_rss( 'url' ); ?></link>
        <description>Feed for X automation</description>
        <?php foreach ( $posts as $post ) : setup_postdata( $post ); ?>
        <item>
            <title><?php echo get_the_title( $post->ID ); ?></title>
            <link><?php echo get_permalink( $post->ID ); ?></link>
            <guid><?php echo $post->ID; ?></guid>
            <pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', $post->post_date_gmt, false ); ?></pubDate>
            <description>
                <![CDATA[
                <?php 
                $summary = get_post_meta( $post->ID, '_node_ai_summary', true );
                echo esc_html( $summary ? wp_trim_words( $summary, 50 ) : '' );
                ?>
                ]]>
            </description>
        </item>
        <?php endforeach; wp_reset_postdata(); ?>
    </channel>
    </rss>
    <?php
}
