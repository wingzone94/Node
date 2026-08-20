<?php
namespace Node\Flow\Core;

class Plugin {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_modules();
    }

    /**
     * フロントエンドUXのモジュールを起動する。
     *
     * 1.3.0 でハイブリッド・スクローラー（無限スクロール）を削除したため、
     * 現在起動するモジュールはない。ページ送りはテーマ側の
     * `.m3-archive-pill-wrapper` / `.m3-pager` が正本。
     */
    private function init_modules() {
    }
}
