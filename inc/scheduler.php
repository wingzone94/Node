<?php

declare(strict_types=1);
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
 * 2. AI Task on Publish
 * 記事が公開された際、自動的にAI要約を生成します。
 *
 * X (Twitter) 自動投稿は node-connect プラグイン（Node_Connect_X_Poster）へ移管済み。
 * オプション名（node_x_*）と投稿済みメタ（_node_x_posted）は互換のまま引き継がれている。
 */
function node_on_post_published( $new_status, $old_status, $post ) {
    // 記事が公開ステータスになった時（新規公開または予約投稿の公開）
    if ( $new_status === 'publish' && $old_status !== 'publish' && $post->post_type === 'post' ) {

        // --- AI 要約の生成 ---
        $existing = get_post_meta( $post->ID, '_node_ai_summary', true );
        if ( empty( $existing ) && class_exists( 'Node_AI_Tools' ) ) {
            Node_AI_Tools::instance()->auto_generate_ai_summary( $post->ID, $post, true );
        }
    }
}
add_action( 'transition_post_status', 'node_on_post_published', 10, 3 );

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
            <guid><?php echo esc_url( get_permalink( $post->ID ) ); ?></guid>
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
