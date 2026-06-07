<?php
/**
 * Social Share Buttons Template
 */
$permalink = get_permalink();
$title = wp_strip_all_tags(get_the_title());
$share_text = trim($title . ' ' . $permalink);
$encoded_url = rawurlencode($permalink);
$encoded_title = rawurlencode($title);
$x_share_url = add_query_arg(
    [
        'text' => $share_text,
    ],
    'https://x.com/intent/tweet'
);
$facebook_share_url = 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url;
$line_share_url = 'https://social-plugins.line.me/lineit/share?url=' . $encoded_url;
$hatebu_share_url = 'https://b.hatena.ne.jp/add?mode=confirm&url=' . $encoded_url;
$threads_share_url = 'https://www.threads.net/intent/post?text=' . rawurlencode($share_text);
$bluesky_share_url = 'https://bsky.app/intent/compose?text=' . rawurlencode($share_text);
$misskey_share_url = 'https://misskey-hub.net/share/?text=' . $encoded_title . '&url=' . $encoded_url;
?>

<div class="m3-share-section">
    <h3 class="m3-share-title">この記事をシェアする</h3>
    <div class="m3-share-buttons">
        <!-- X (Twitter) -->
        <a href="<?php echo esc_url($x_share_url); ?>"
           class="m3-share-btn m3-share-btn--x" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="Xでシェア" 
           aria-label="Xでシェアする" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
            <span class="m3-share-btn__label">X</span>
        </a>

        <!-- Facebook -->
        <a href="<?php echo esc_url($facebook_share_url); ?>"
           class="m3-share-btn m3-share-btn--facebook" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="Facebookでシェア" 
           aria-label="Facebookでシェアする" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Facebook</span>
        </a>

        <!-- LINE -->
        <a href="<?php echo esc_url($line_share_url); ?>"
           class="m3-share-btn m3-share-btn--line" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="LINEで送る" 
           aria-label="LINEで送る" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <i class="fa-brands fa-line" aria-hidden="true"></i>
            <span class="m3-share-btn__label">LINE</span>
        </a>

        <!-- はてなブックマーク -->
        <a href="<?php echo esc_url($hatebu_share_url); ?>"
           class="m3-share-btn m3-share-btn--hatebu" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="はてなブックマークに追加" 
           aria-label="はてなブックマークに追加する" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <span class="m3-share-btn__icon" aria-hidden="true">B!</span>
            <span class="m3-share-btn__label">はてなブックマーク</span>
        </a>

        <!-- Threads -->
        <a href="<?php echo esc_url($threads_share_url); ?>"
           class="m3-share-btn m3-share-btn--threads" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="Threadsでシェア" 
           aria-label="Threadsでシェアする" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <i class="fa-brands fa-threads" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Threads</span>
        </a>

        <!-- BlueSky -->
        <a href="<?php echo esc_url($bluesky_share_url); ?>"
           class="m3-share-btn m3-share-btn--bluesky" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="BlueSkyでシェア" 
           aria-label="BlueSkyでシェアする" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <span class="m3-share-btn__icon">
                <!-- Bluesky Butterfly Logo (Latest) -->
                <svg viewBox="0 0 600 530" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="m135.72 44.03c95.596 6.581 148.85 168.42 164.28 215.39 15.427-46.973 68.682-208.81 164.28-215.39 92.354-6.353 148.1 49.988 116.14 135.53-27.135 72.63-107.03 126.9-172.46 122.84 81.332 4.062 165.73 54.588 165.73 158.5 0 94.707-73.125 146.4-152.03 120.48-69.213-22.734-111.45-103.77-121.65-123.63-10.198 19.855-52.434 100.89-121.65 123.63-78.905 25.922-152.03-25.77-152.03-120.48 0-103.92 84.402-154.44 165.73-158.5-65.433 4.062-145.33-50.211-172.46-122.84-31.956-85.539 23.784-141.88 116.14-135.53z"/>
                </svg>
            </span>
            <span class="m3-share-btn__label">BlueSky</span>
        </a>

        <!-- Misskey.io -->
        <a href="<?php echo esc_url($misskey_share_url); ?>"
           class="m3-share-btn m3-share-btn--misskey" 
           data-url="<?php echo esc_url($permalink); ?>"
           data-share-title="<?php echo esc_attr($title); ?>"
           data-share-popup="true"
           title="Misskeyでシェア" 
           aria-label="Misskeyでシェアする" 
           target="_blank" 
           rel="nofollow noopener noreferrer">
            <span class="m3-share-btn__icon" aria-hidden="true">Mi</span>
            <span class="m3-share-btn__label">Misskey</span>
        </a>

        <!-- システムシェア (モバイルのみ) -->
        <?php if (wp_is_mobile()) : ?>
        <button class="m3-share-btn m3-share-btn--system" 
                id="m3-system-share-trigger" 
                data-url="<?php echo esc_url($permalink); ?>"
                data-share-title="<?php echo esc_attr($title); ?>"
                title="システムシェア" 
                aria-label="システムのシェア機能を開く">
            <span class="material-symbols-outlined" aria-hidden="true">share</span>
            <span class="m3-share-btn__label">シェア</span>
        </button>
        <?php endif; ?>

        <!-- リンクをコピー -->
        <button class="m3-share-btn m3-share-btn--copy" 
                id="m3-copy-trigger" 
                data-url="<?php echo esc_url($permalink); ?>"
                data-share-title="<?php echo esc_attr($title); ?>"
                title="タイトルとURLをコピー" 
                aria-label="この記事のタイトルとURLをコピーする">
            <span class="material-symbols-outlined m3-copy-icon" aria-hidden="true">content_copy</span>
            <span class="m3-share-btn__label m3-copy-label">コピー</span>
        </button>
    </div>
</div>
