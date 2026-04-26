<?php
// --- Blog Card ---

/**
 * URLからOGP情報を取得する
 */
function node_get_ogp_data($url) {
    $transient_key = 'node_ogp_' . md5($url);
    $cached = get_transient($transient_key);
    if ($cached !== false) return $cached;

    $ogp = [
        'title'       => '',
        'description' => '',
        'image'       => '',
        'favicon'     => '',
        'site_name'   => '',
        'is_internal' => false,
    ];

    $home_url = home_url();
    if (str_contains($url, $home_url)) {
        $post_id = url_to_postid($url);
        if ($post_id) {
            $ogp['title']       = get_the_title($post_id);
            $ogp['description'] = get_the_excerpt($post_id);
            $ogp['image']       = get_the_post_thumbnail_url($post_id, 'large');
            $ogp['site_name']   = get_bloginfo('name');
            $ogp['is_internal'] = true;
            $ogp['favicon']     = get_site_icon_url(32);
            set_transient($transient_key, $ogp, WEEK_IN_SECONDS);
            return $ogp;
        }
    }

    $response = wp_safe_remote_get($url, ['timeout' => 10, 'sslverify' => false]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return false;

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) return false;

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);

    $ogp['title']       = $xpath->evaluate('string(//meta[@property="og:title"]/@content)') ?: $xpath->evaluate('string(//title)');
    $ogp['description'] = $xpath->evaluate('string(//meta[@property="og:description"]/@content)') ?: $xpath->evaluate('string(//meta[@name="description"]/@content)');
    $ogp['image']       = $xpath->evaluate('string(//meta[@property="og:image"]/@content)');
    $ogp['site_name']   = $xpath->evaluate('string(//meta[@property="og:site_name"]/@content)') ?: parse_url($url, PHP_URL_HOST);
    $ogp['favicon']     = 'https://www.google.com/s2/favicons?domain=' . parse_url($url, PHP_URL_HOST) . '&sz=64';

    set_transient($transient_key, $ogp, WEEK_IN_SECONDS);
    return $ogp;
}

function node_blogcard_shortcode($atts) {
    $atts = shortcode_atts(['url' => ''], $atts);
    if (empty($atts['url'])) return '';

    $ogp = node_get_ogp_data($atts['url']);
    if (!$ogp) return '<a href="' . esc_url($atts['url']) . '">' . esc_html($atts['url']) . '</a>';

    ob_start();
    ?>
    <div class="m3-blogcard" onclick="window.open('<?php echo esc_url($atts['url']); ?>', '_blank')">
        <div class="m3-blogcard__content">
            <div class="m3-blogcard__text">
                <h4 class="m3-blogcard__title"><?php echo esc_html($ogp['title']); ?></h4>
                <p class="m3-blogcard__description"><?php echo esc_html(wp_trim_words($ogp['description'], 60)); ?></p>
                <div class="m3-blogcard__footer">
                    <?php if ($ogp['favicon']) : ?>
                        <img src="<?php echo esc_url($ogp['favicon']); ?>" class="m3-blogcard__favicon" alt="" loading="lazy">
                    <?php endif; ?>
                    <span class="m3-blogcard__sitename"><?php echo esc_html($ogp['site_name']); ?></span>
                    <?php if ($ogp['is_internal']) : ?>
                        <span class="m3-blogcard__internal-badge">内部記事</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($ogp['image']) : ?>
                <div class="m3-blogcard__image">
                    <img src="<?php echo esc_url($ogp['image']); ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('blogcard', 'node_blogcard_shortcode');

function node_auto_blogcard($content) {
    $pattern = '/^(<p>)?(https?:\/\/[-_.!~*\'()a-zA-Z0-9;\/?:\@&=+\$,%#]+)(<\/p>)?$/im';
    return preg_replace_callback($pattern, function($matches) {
        return node_blogcard_shortcode(['url' => $matches[2]]);
    }, $content);
}
add_filter('the_content', 'node_auto_blogcard', 11);

// AmazonのデフォルトoEmbed（Get Book / Read Sample などのKindle電子書籍専用プレビュー）を確実に無効化する
add_filter('oembed_providers', function($providers) {
    foreach ($providers as $url => $provider) {
        if (strpos($url, 'amazon') !== false) {
            unset($providers[$url]);
        }
    }
    return $providers;
});
