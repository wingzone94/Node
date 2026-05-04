<?php
namespace Node\Signal\Admin;

class RateManager {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // カラムの追加
        add_filter( 'manage_post_posts_columns', [ $this, 'add_rate_columns' ] );
        // カラムの内容出力
        add_action( 'manage_post_posts_custom_column', [ $this, 'output_rate_columns' ], 10, 2 );
        // カラムのソート対応
        add_filter( 'manage_edit-post_sortable_columns', [ $this, 'sortable_rate_columns' ] );
        // ソート時のクエリ調整
        add_action( 'pre_get_posts', [ $this, 'sort_rate_query' ] );
    }

    /**
     * 投稿一覧にカスタムカラムを追加
     */
    public function add_rate_columns( $columns ) {
        // 'author' の次に配置するための配列操作例
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            if ( 'author' === $key ) {
                $new_columns['ad_reward_rate'] = '報酬レート';
                $new_columns['is_special_rate'] = '特単';
            }
        }
        return $new_columns;
    }

    /**
     * カスタムカラムの中身を出力
     */
    public function output_rate_columns( $column_name, $post_id ) {
        if ( 'ad_reward_rate' === $column_name ) {
            $rate = get_post_meta( $post_id, 'ad_reward_rate', true );
            if ( $rate ) {
                echo '¥' . esc_html( number_format( (float) $rate, 2 ) );
            } else {
                echo '<span style="color:#aaa;">-</span>';
            }
        }

        if ( 'is_special_rate' === $column_name ) {
            $is_special = get_post_meta( $post_id, 'is_special_rate', true );
            if ( $is_special ) {
                echo '<span style="color:green; font-weight:bold;">✔ 特単</span>';
            } else {
                echo '<span style="color:#aaa;">-</span>';
            }
        }
    }

    /**
     * 報酬レートカラムをソート可能にする
     */
    public function sortable_rate_columns( $columns ) {
        $columns['ad_reward_rate'] = 'ad_reward_rate';
        return $columns;
    }

    /**
     * 報酬レートでソートした際のクエリ（並び替えロジック）をフック
     */
    public function sort_rate_query( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'ad_reward_rate' === $query->get( 'orderby' ) ) {
            $query->set( 'meta_key', 'ad_reward_rate' );
            $query->set( 'orderby', 'meta_value_num' );
        }
    }
}
