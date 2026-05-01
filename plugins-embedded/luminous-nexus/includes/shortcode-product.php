<?php
/**
 * 商品カードショートコード
 *
 * @package Luminous_Nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 商品カードショートコード [product_card title="..." price="..." image_url="..." amazon_url="..." rakuten_url="..."]
 */
function luminous_nexus_product_card_shortcode($atts) {
    $atts = shortcode_atts([
        'title'       => '',
        'price'       => '',
        'image_url'   => '',
        'amazon_url'  => '',
        'rakuten_url' => '',
    ], $atts, 'product_card');

    // 少なくとも1つのリンクが必要
    $has_amazon  = !empty($atts['amazon_url']);
    $has_rakuten = !empty($atts['rakuten_url']);

    if (!$has_amazon && !$has_rakuten) return '';

    // ストアURLの簡易バリデーション (ドメインチェック)
    $validate_url = function($url, $allowed_domains) {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        foreach ($allowed_domains as $domain) {
            if (str_ends_with($host, $domain)) return true;
        }
        return false;
    };

    $allowed_amazon  = ['amazon.co.jp', 'amazon.com', 'amzn.to', 'amzn.asia', 'amzn.jp', 'a.co'];
    $allowed_rakuten = ['rakuten.co.jp', 'rakuten.ne.jp', 'r10.to', 'rakuten.jp'];

    if ($has_amazon  && !$validate_url($atts['amazon_url'],  $allowed_amazon))  $has_amazon  = false;
    if ($has_rakuten && !$validate_url($atts['rakuten_url'], $allowed_rakuten)) $has_rakuten = false;

    if (!$has_amazon && !$has_rakuten) return '';

    ob_start();
    ?>
    <div class="m3-block-container">
        <div class="m3-product-card">
            <?php if (!empty($atts['image_url'])) : ?>
            <div class="m3-product-card__image">
                <img src="<?php echo esc_url($atts['image_url']); ?>"
                     alt="<?php echo esc_attr($atts['title']); ?>"
                     loading="lazy"
                     width="120"
                     height="120">
            </div>
            <?php endif; ?>

            <div class="m3-product-card__body">
                <?php if (!empty($atts['title'])) : ?>
                <h4 class="m3-product-card__title"><?php echo esc_html($atts['title']); ?></h4>
                <?php endif; ?>

                <?php if (!empty($atts['price'])) : ?>
                <p class="m3-product-card__price"><?php echo esc_html($atts['price']); ?></p>
                <?php endif; ?>

                <div class="m3-product-card__buttons">
                    <?php if ($has_amazon) : ?>
                    <a href="<?php echo esc_url($atts['amazon_url']); ?>"
                       class="m3-product-btn m3-product-btn--amazon"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span class="material-symbols-outlined">shopping_cart</span>
                        Amazonで見る
                    </a>
                    <?php endif; ?>

                    <?php if ($has_rakuten) : ?>
                    <a href="<?php echo esc_url($atts['rakuten_url']); ?>"
                       class="m3-product-btn m3-product-btn--rakuten"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span class="material-symbols-outlined">shopping_bag</span>
                        楽天市場で見る
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
