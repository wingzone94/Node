<?php
namespace Node\Signal\Core;

use Node\Signal\Admin\RateManager;

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

    private function init_modules() {
        // 管理画面の機能
        if ( is_admin() ) {
            RateManager::get_instance();
        }

        // フロントエンドの AdBlock 検知は廃止
    }
}
