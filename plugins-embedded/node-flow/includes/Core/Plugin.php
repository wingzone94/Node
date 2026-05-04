<?php
namespace Node\Flow\Core;

use Node\Flow\Frontend\Scroller;

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
        Scroller::get_instance();
    }
}
