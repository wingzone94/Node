<?php
/**
 * Template part for displaying game/app information (Node Library).
 * プラットフォームに応じたブランドカラーを適用した Material 3 形式のカード。
 */
if ( ! isset( $game_info ) ) {
    return;
}

$title        = $game_info['title'];
$type         = ( $game_info['type'] ?? 'game' ) === 'app' ? 'app' : 'game';
$header_icon  = 'app' === $type ? 'smartphone' : 'sports_esports';
$summary      = $game_info['summary'] ?? '';
$links        = array_values(
    array_filter(
        is_array( $game_info['links'] ?? null ) ? $game_info['links'] : [],
        static function ( $link ) {
            return is_array( $link ) && ! empty( $link['platform'] ) && ! empty( $link['url'] );
        }
    )
);
$header_text  = get_option( 'node_library_header_text', 'GAME / APP INFO' );
$button_text  = get_option( 'node_library_button_text', 'で見る' );
$store_groups = [
    'pc' => [
        'label'       => 'PC',
        'short_label' => 'PC',
        'icon'        => 'computer',
        'links'       => [],
    ],
    'mobile' => [
        'label'       => 'スマートフォン・タブレット',
        'short_label' => 'スマホ・タブレット',
        'icon'        => 'devices',
        'links'       => [],
    ],
    'console' => [
        'label'       => 'コンソール',
        'short_label' => 'コンソール',
        'icon'        => 'sports_esports',
        'links'       => [],
    ],
];

foreach ( $links as $link ) {
    $platform = (string) ( $link['platform'] ?? '' );

    if (
        false !== stripos( $platform, 'nintendo' ) ||
        false !== stripos( $platform, 'switch' ) ||
        false !== stripos( $platform, 'playstation' ) ||
        preg_match( '/(^|\s)ps[345](\s|$)/i', $platform ) ||
        false !== stripos( $platform, 'xbox' )
    ) {
        $store_groups['console']['links'][] = $link;
    } elseif (
        false !== stripos( $platform, 'ios' ) ||
        false !== stripos( $platform, 'apple' ) ||
        false !== stripos( $platform, 'ipad' ) ||
        false !== stripos( $platform, 'app store' ) ||
        false !== stripos( $platform, 'android' ) ||
        false !== stripos( $platform, 'google play' )
    ) {
        $store_groups['mobile']['links'][] = $link;
    } else {
        $store_groups['pc']['links'][] = $link;
    }
}

$store_groups = array_filter(
    $store_groups,
    static function ( $group ) {
        return ! empty( $group['links'] );
    }
);

if ( count( $store_groups ) > 1 ) {
    $store_groups['all'] = [
        'label'       => '全てを表示',
        'short_label' => 'すべて',
        'icon'        => 'apps',
        'links'       => $links,
    ];
}

$store_tabs_id = wp_unique_id( 'node-library-store-tabs-' );
$render_store_link  = static function ( $link ) use ( $button_text ) {
    $platform = $link['platform'] ?? 'other';
    if ( stripos( $platform, 'switch' ) !== false || stripos( $platform, 'nintendo' ) !== false ) $platform = 'Nintendo Store';
    if ( stripos( $platform, 'xbox' ) !== false ) $platform = 'Microsoft Store（Xbox）';
    if ( stripos( $platform, 'playstation' ) !== false || preg_match( '/(^|\s)ps[345](\s|$)/i', $platform ) ) $platform = 'PS Store';
    $platform_slug = strtolower( str_replace( ' ', '', $platform ) );

    if ( stripos( $platform, 'switch' ) !== false || stripos( $platform, 'nintendo' ) !== false ) $platform_slug = 'nintendo';
    if ( stripos( $platform, 'ps' ) !== false || stripos( $platform, 'playstation' ) !== false ) $platform_slug = 'playstation';
    if ( stripos( $platform, 'xbox' ) !== false ) $platform_slug = 'xbox';
    if ( stripos( $platform, 'steam' ) !== false ) $platform_slug = 'steam';
    if ( stripos( $platform, 'ios' ) !== false || stripos( $platform, 'apple' ) !== false || stripos( $platform, 'app store' ) !== false ) $platform_slug = 'ios';
    if ( stripos( $platform, 'android' ) !== false || stripos( $platform, 'google' ) !== false ) $platform_slug = 'android';
    if ( stripos( $platform, 'windows' ) !== false || stripos( $platform, 'pc' ) !== false ) $platform_slug = 'windows';
    $supports_qr = in_array( $platform_slug, [ 'ios', 'android' ], true );
    $qr_panel_id = $supports_qr ? wp_unique_id( 'node-library-qr-' ) : '';
    $qr_title_id = $supports_qr ? $qr_panel_id . '-title' : '';
    $button_label = match ( $platform_slug ) {
        'nintendo'    => 'Nintendo Storeで見る',
        'playstation' => 'PS Storeで見る',
        default       => $platform . ' ' . $button_text,
    };
    $badge_file = match ( $platform_slug ) {
        'ios'     => 'app-store-badge-ja.svg',
        'android' => 'google-play-badge-ja.png',
        default   => '',
    };
    $badge_url = $badge_file
        ? plugins_url( 'assets/images/' . $badge_file, dirname( __DIR__ ) . '/node-library.php' )
        : '';
    if ( $supports_qr ) :
    ?>
        <div class="m3-platform-action m3-platform-action--qr">
            <div class="m3-platform-action__row">
                <a href="<?php echo esc_url( $link['url'] ); ?>"
                   class="m3-platform-button m3-platform-button--<?php echo esc_attr( $platform_slug ); ?> m3-platform-button--desktop m3-ripple-host"
                   target="_blank"
                   rel="noopener">
                    <span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>
                    <?php echo esc_html( $button_label ); ?>
                </a>
                <a href="<?php echo esc_url( $link['url'] ); ?>"
                   class="m3-platform-store-badge-link m3-platform-store-badge-link--<?php echo esc_attr( $platform_slug ); ?>"
                   target="_blank"
                   rel="noopener"
                   aria-label="<?php echo esc_attr( $button_label ); ?>">
                    <img class="m3-platform-store-badge" src="<?php echo esc_url( $badge_url ); ?>" alt="<?php echo esc_attr( $button_label ); ?>">
                </a>
                <button class="m3-platform-qr-toggle" type="button" aria-label="<?php echo esc_attr( $platform . 'のQRコードを表示' ); ?>" aria-controls="<?php echo esc_attr( $qr_panel_id ); ?>" aria-expanded="false" title="QRコードを表示" data-node-library-qr-toggle>
                    <span class="material-symbols-outlined" aria-hidden="true">qr_code_2</span>
                </button>
            </div>
            <dialog class="m3-platform-qr-dialog" id="<?php echo esc_attr( $qr_panel_id ); ?>" aria-labelledby="<?php echo esc_attr( $qr_title_id ); ?>" data-qr-url="<?php echo esc_url( $link['url'] ); ?>" data-node-library-qr-dialog>
                <button class="m3-platform-qr-close" type="button" aria-label="QRコードを閉じる" title="閉じる" data-node-library-qr-close>
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
                <h5 id="<?php echo esc_attr( $qr_title_id ); ?>"><?php echo esc_html( $platform ); ?></h5>
                <span class="m3-platform-qr-status" data-node-library-qr-status>QRコードを生成中…</span>
                <canvas class="m3-platform-qr-canvas" aria-label="<?php echo esc_attr( $platform . 'ストアページのQRコード' ); ?>" data-node-library-qr-canvas hidden></canvas>
                <div class="m3-platform-qr-link-row">
                    <a class="m3-platform-qr-url" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a>
                    <button class="m3-platform-qr-copy" type="button" data-copy-text="<?php echo esc_url( $link['url'] ); ?>" data-node-library-qr-copy>
                        <span class="material-symbols-outlined" aria-hidden="true">content_copy</span>
                        コピー
                    </button>
                </div>
                <span class="m3-platform-qr-copy-status" aria-live="polite" data-node-library-qr-copy-status></span>
            </dialog>
        </div>
    <?php else : ?>
        <a href="<?php echo esc_url( $link['url'] ); ?>"
           class="m3-platform-button m3-platform-button--<?php echo esc_attr( $platform_slug ); ?> m3-ripple-host"
           target="_blank"
           rel="noopener">
            <span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>
            <?php echo esc_html( $button_label ); ?>
        </a>
    <?php endif; ?>
    <?php
};
?>
<section class="m3-game-card m3-reveal node-library-card">
    <div class="m3-game-card__header">
        <span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( $header_icon ); ?></span>
        <h3><?php echo esc_html( $header_text ); ?></h3>
    </div>
    
    <div class="m3-game-card__body">
        <h4 class="m3-game-card__title"><?php echo esc_html($title); ?></h4>
        <?php if ($summary) : ?>
            <p class="m3-game-card__summary"><?php echo esc_html($summary); ?></p>
        <?php endif; ?>

        <?php if ( ! empty( $store_groups ) ) : ?>
            <div class="m3-game-card__stores" data-node-library-tabs>
                <?php if ( count( $store_groups ) > 1 ) : ?>
                    <div class="m3-game-card__tabs-shell">
                    <div class="m3-game-card__tabs<?php echo count( $store_groups ) > 3 ? ' m3-game-card__tabs--overflow' : ''; ?>" role="tablist" aria-label="ストアの種類">
                        <?php $tab_index = 0; ?>
                        <?php foreach ( $store_groups as $group_key => $group ) : ?>
                            <?php
                            $tab_id   = $store_tabs_id . '-' . $group_key . '-tab';
                            $panel_id = $store_tabs_id . '-' . $group_key . '-panel';
                            ?>
                            <button class="m3-game-card__tab" id="<?php echo esc_attr( $tab_id ); ?>" type="button" role="tab" aria-selected="<?php echo 0 === $tab_index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>" tabindex="<?php echo 0 === $tab_index ? '0' : '-1'; ?>" data-node-library-tab="<?php echo esc_attr( $group_key ); ?>">
                                <span class="material-symbols-outlined" aria-hidden="true"><?php echo esc_html( $group['icon'] ); ?></span>
                                <span class="m3-game-card__tab-label m3-game-card__tab-label--full"><?php echo esc_html( $group['label'] ); ?></span>
                                <span class="m3-game-card__tab-label m3-game-card__tab-label--compact"><?php echo esc_html( $group['short_label'] ); ?></span>
                            </button>
                            <?php $tab_index++; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( count( $store_groups ) > 3 ) : ?>
                        <button class="m3-game-card__tab-next" type="button" aria-label="次のタブを表示" title="次のタブを表示" data-node-library-tab-next>
                            <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
                        </button>
                    <?php endif; ?>
                    <button class="m3-game-card__tab-back" type="button" data-node-library-tab-back hidden>
                        <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                        デバイスタイプ別に戻る
                    </button>
                    </div>
                <?php endif; ?>

                <?php $panel_index = 0; ?>
                <?php foreach ( $store_groups as $group_key => $group ) : ?>
                    <?php
                    $tab_id   = $store_tabs_id . '-' . $group_key . '-tab';
                    $panel_id = $store_tabs_id . '-' . $group_key . '-panel';
                    ?>
                    <div class="m3-game-card__store-panel" id="<?php echo esc_attr( $panel_id ); ?>" <?php if ( count( $store_groups ) > 1 ) : ?>role="tabpanel" aria-labelledby="<?php echo esc_attr( $tab_id ); ?>" data-node-library-panel="<?php echo esc_attr( $group_key ); ?>"<?php endif; ?> <?php echo 0 === $panel_index ? '' : 'hidden'; ?>>
                        <div class="m3-game-card__actions">
                            <?php foreach ( $group['links'] as $link ) $render_store_link( $link ); ?>
                        </div>
                    </div>
                    <?php $panel_index++; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
