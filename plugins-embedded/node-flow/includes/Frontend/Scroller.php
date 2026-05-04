<?php
namespace Node\Flow\Frontend;

class Scroller {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // JSのエンキュー
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        // WP REST API のエンドポイント登録
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    public function enqueue_scripts() {
        // 記事一覧が表示されるページ（ホーム、アーカイブ、検索等）のみ読み込む
        if ( ! is_home() && ! is_archive() && ! is_search() ) {
            return;
        }

        wp_enqueue_script(
            'node-flow-scroller',
            NODE_FLOW_PLUGIN_URL . 'assets/js/scroller.js',
            [],
            NODE_FLOW_VERSION,
            true
        );

        // API URL や Nonce などの設定をJSに渡す
        wp_localize_script( 'node-flow-scroller', 'nodeFlowSettings', [
            'restUrl' => esc_url_raw( rest_url( 'node-flow/v1/posts' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            // 現在のクエリパラメータ（カテゴリIDや検索キーワード）を渡す
            'query'   => $this->get_current_query_vars()
        ]);
    }

    /**
     * 現在のクエリ変数をJSに渡せる形に整形
     */
    private function get_current_query_vars() {
        global $wp_query;
        $vars = $wp_query->query_vars;
        // 不要なパラメータを整理
        unset( $vars['paged'], $vars['posts_per_page'], $vars['error'], $vars['m'] );
        return $vars;
    }

    public function register_rest_route() {
        register_rest_route( 'node-flow/v1', '/posts', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_posts_html' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'page' => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * REST API コールバック: 投稿のHTML片を返す
     */
    public function get_posts_html( \WP_REST_Request $request ) {
        $page = $request->get_param( 'page' );
        $query_params = $request->get_param( 'query' );
        
        if ( ! is_array( $query_params ) ) {
            $query_params = [];
        }

        $args = array_merge( $query_params, [
            'paged'       => $page,
            'post_status' => 'publish',
        ]);

        $query = new \WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return new \WP_REST_Response( [
                'html'    => '',
                'hasMore' => false
            ], 200 );
        }

        ob_start();
        while ( $query->have_posts() ) {
            $query->the_post();
            // テーマの template-parts/card.php などを呼び出してHTMLを生成
            // ここでテーマ（Node）のファイルに依存するが、これはプレゼンテーションへの委譲として許容される
            get_template_part( 'template-parts/card', null, [ 'card_class' => 'card-standard' ] );
        }
        $html = ob_get_clean();

        $has_more = $query->max_num_pages > $page;

        return new \WP_REST_Response( [
            'html'    => $html,
            'hasMore' => $has_more
        ], 200 );
    }
}
