<?php
/**
 * Template part for displaying the writer information card.
 */
$author_id = get_the_author_meta('ID');
$description = get_the_author_meta('description');
if (empty($description)) {
    $description = 'このライターはまだ自己紹介を記載していません。';
}

// ユーザー連絡先メソッドを取得（SNSリンク用）
$user_contact = wp_get_user_contact_methods();
$sns_links = [];

// 一般的なSNSキーのリスト
$sns_keys = [
    'twitter'   => ['icon' => 'brand_family', 'label' => 'X (Twitter)'], // もしアイコンがあれば
    'github'    => ['icon' => 'code', 'label' => 'GitHub'],
    'youtube'   => ['icon' => 'play_circle', 'label' => 'YouTube'],
    'facebook'  => ['icon' => 'facebook', 'label' => 'Facebook'],
    'instagram' => ['icon' => 'photo_camera', 'label' => 'Instagram'],
    'custom_link_1' => ['icon' => 'link', 'label' => 'Link 1'],
    'custom_link_2' => ['icon' => 'link', 'label' => 'Link 2'],
    'custom_link_3' => ['icon' => 'link', 'label' => 'Link 3'],
    'custom_link_4' => ['icon' => 'link', 'label' => 'Link 4'],
    'custom_link_5' => ['icon' => 'link', 'label' => 'Link 5'],
];

foreach ($sns_keys as $key => $meta) {
    $val = get_user_meta($author_id, $key, true);
    if ($val) {
        $sns_links[$key] = [
            'url'   => $val,
            'icon'  => $meta['icon'],
            'label' => $meta['label']
        ];
    }
}
?>

<section class="m3-writer-card m3-reveal">
    <div class="m3-writer-card__header">
        <span class="m3-writer-card__label">WRITER INFO</span>
    </div>
    <div class="m3-writer-card__body">
        <div class="m3-writer-card__avatar">
            <?php echo get_avatar($author_id, 160); ?>
        </div>
        <div class="m3-writer-card__info">
            <h3 class="m3-writer-card__name"><?php the_author(); ?></h3>
            <div class="m3-writer-card__bio">
                <?php echo wp_kses_post(wpautop($description)); ?>
            </div>
            
            <?php if (!empty($sns_links)) : ?>
                <div class="m3-writer-card__sns">
                    <?php foreach ($sns_links as $sns) : ?>
                        <a href="<?php echo esc_url($sns['url']); ?>" 
                           class="m3-sns-icon m3-ripple-host" 
                           target="_blank" 
                           rel="noopener"
                           title="<?php echo esc_attr($sns['label']); ?>">
                            <span class="material-symbols-outlined"><?php echo esc_html($sns['icon']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>