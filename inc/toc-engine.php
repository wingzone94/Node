<?php
/**
 * TOC Engine — Heading ID Auto-Generation
 *
 * 記事内の見出し（h2-h6）に自動的にIDを付与し、目次機能のジャンプを確実に動作させます。
 *
 * @package Node
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 本文中の見出しタグに ID を自動付与する
 */
function node_add_heading_ids( $content ) {
    if ( ! is_singular() ) return $content;

    // 見出しタグを検索
    $pattern = '/<(h[2-6])(.*?)>(.*?)<\/h[2-6]>/i';

    return preg_replace_callback( $pattern, function( $matches ) {
        $tag = $matches[1];
        $attr = $matches[2];
        $text = $matches[3];

        // すでに ID が設定されている場合はそのまま
        if ( stripos( $attr, 'id=' ) !== false ) {
            return $matches[0];
        }

        // テキストから ID を生成（日本語対応）
        $id = sanitize_title( strip_tags( $text ) );

        // 空の場合はランダムなIDを生成
        if ( empty( $id ) ) {
            $id = 'section-' . substr( md5( $text ), 0, 6 );
        }

        // 重複回避用の静的変数
        static $ids = [];
        if ( in_array( $id, $ids ) ) {
            $count = 1;
            while ( in_array( $id . '-' . $count, $ids ) ) {
                $count++;
            }
            $id = $id . '-' . $count;
        }
        $ids[] = $id;

        return "<{$tag}{$attr} id=\"" . esc_attr( $id ) . "\">{$text}</{$tag}>";
    }, $content );
}
add_filter( 'the_content', 'node_add_heading_ids', 15 );
