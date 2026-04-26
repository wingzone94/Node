<?php
// Nexus: 商品カードショートコード — スタブ
function node_product_card_shortcode(array $atts): string {
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

    // 各ストアURLのドメイン検証
    $allowed_amazon  = ['amazon.co.jp', 'amazon.com', 'amazon.jp', 'amzn.to', 'amzn.asia', 'amzn.jp', 'a.co', 'amazon-adsystem.com'];
    $allowed_rakuten = ['rakuten.co.jp', 'rakuten.ne.jp', 'a.r10.to', 'hb.afl.rakuten.co.jp', 'rakuten.co.jp'];

    if ($has_amazon  && !node_validate_embed_url($atts['amazon_url'],  $allowed_amazon))  $has_amazon  = false;
    if ($has_rakuten && !node_validate_embed_url($atts['rakuten_url'], $allowed_rakuten)) $has_rakuten = false;

    if (!$has_amazon && !$has_rakuten) {
        return '<!-- Product Card: No valid Store URLs found among ' . esc_html($atts['amazon_url'] . ' ' . $atts['rakuten_url']) . ' -->';
    }

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
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                            <path d="M.045 18.02c.072-.116.187-.124.348-.022 3.636 2.11 7.594 3.166 11.87 3.166 2.852 0 5.668-.533 8.447-1.595.577-.23.877-.053.9.232.024.187-.057.377-.24.57-2.414 2.667-5.33 4-8.747 4-3.65 0-6.903-1.15-9.76-3.45-.286-.23-.336-.523-.155-.9zM12.022 2.9c1.08 0 2.264.277 3.547.832.47.204.594.454.374.748-.19.25-.44.34-.748.27-1.33-.31-2.405-.467-3.22-.467-1.063 0-2.122.22-3.177.658-.486.207-.783.133-.893-.222-.11-.355.057-.61.5-.763C9.534 3.155 10.71 2.9 12.022 2.9zm2.627 2.06c.48.078.97.24 1.47.488.5.25.617.6.35 1.05-.234.39-.534.5-.9.33-.49-.23-.863-.38-1.12-.45-.26-.07-.47-.1-.635-.1-.48 0-.737.15-.77.45-.032.247.112.455.43.622.318.168.83.347 1.535.54l.433.127c.622.183 1.083.41 1.384.68.3.27.45.66.45 1.17 0 .618-.205 1.1-.615 1.445-.41.345-.95.517-1.622.517-.39 0-.817-.065-1.284-.194s-.872-.304-1.216-.523c-.486-.304-.58-.66-.28-1.07.222-.314.504-.41.848-.29.42.146.76.265 1.02.36.26.093.52.14.775.14.307 0 .54-.054.697-.163.157-.11.23-.268.22-.475-.01-.187-.11-.34-.3-.458-.19-.12-.537-.256-1.04-.41-.38-.114-.742-.237-1.087-.37-.345-.133-.64-.31-.885-.527-.245-.218-.43-.48-.557-.784-.127-.305-.19-.66-.19-1.07 0-.58.19-1.055.57-1.424.38-.37.9-.554 1.558-.554zM12.022 1c-5.523 0-10 4.477-10 10s4.477 10 10 10 10-4.477 10-10-4.477-10-10-10z"/>
                        </svg>
                        Amazonで見る
                    </a>
                    <?php endif; ?>

                    <?php if ($has_rakuten) : ?>
                    <a href="<?php echo esc_url($atts['rakuten_url']); ?>"
                       class="m3-product-btn m3-product-btn--rakuten"
                       target="_blank"
                       rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm4.95 16.5h-2.193l-2.43-3.24H10.5V16.5H8.55V7.5h4.08c1.98 0 3.24 1.017 3.24 2.88 0 1.395-.765 2.34-1.98 2.745L16.95 16.5zm-4.65-4.83c.855 0 1.35-.45 1.35-1.215 0-.78-.495-1.23-1.35-1.23H10.5v2.445h1.8z"/>
                        </svg>
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
add_shortcode('product_card', 'node_product_card_shortcode');
if ( ! defined( 'ABSPATH' ) ) exit;
function luminous_nexus_product_card_shortcode( $atts ) { return ''; }
