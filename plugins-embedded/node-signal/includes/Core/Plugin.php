<?php
namespace Node\Signal\Core;

use Node\Signal\Admin\RateManager;
use Node\Signal\Frontend\AdBlockDetector;

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

        // フロントエンドの機能
        if ( ! is_admin() ) {
            AdBlockDetector::get_instance();
        }
    }
}
