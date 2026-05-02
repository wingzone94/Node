<?php
/**
 * Social Share Buttons Template
 */
$permalink = get_permalink();
$url = urlencode($permalink);
$title = urlencode(get_the_title());
?>

<div class="m3-share-section">
    <h3 class="m3-share-title">この記事をシェアする</h3>
    <div class="m3-share-buttons">
        <!-- X (Twitter) -->
        <a href="https://twitter.com/intent/tweet?url=<?php echo $url; ?>&text=<?php echo $title; ?>" 
           class="m3-share-btn m3-share-btn--x" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="Xでシェア" 
           aria-label="Xでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
            <span class="m3-share-btn__label">X</span>
        </a>

        <!-- Facebook -->
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--facebook" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="Facebookでシェア" 
           aria-label="Facebookでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Facebook</span>
        </a>

        <!-- LINE -->
        <a href="https://social-plugins.line.me/lineit/share?url=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--line" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="LINEで送る" 
           aria-label="LINEで送る" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-line" aria-hidden="true"></i>
            <span class="m3-share-btn__label">LINE</span>
        </a>

        <!-- はてなブックマーク -->
        <a href="https://b.hatena.ne.jp/add?mode=confirm&url=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--hatebu" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="はてなブックマークに追加" 
           aria-label="はてなブックマークに追加する" 
           target="_blank" 
           rel="noopener noreferrer">
            <span class="m3-share-btn__icon" aria-hidden="true">B!</span>
            <span class="m3-share-btn__label">はてな</span>
        </a>

        <!-- Threads -->
        <a href="https://www.threads.net/intent/post?text=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--threads" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="Threadsでシェア" 
           aria-label="Threadsでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-threads" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Threads</span>
        </a>

        <!-- BlueSky -->
        <a href="https://bsky.app/intent/compose?text=<?php echo $title . '%20' . $url; ?>" 
           class="m3-share-btn m3-share-btn--bluesky" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="BlueSkyでシェア" 
           aria-label="BlueSkyでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <span class="m3-share-btn__icon">
                <!-- Bluesky Butterfly Logo (0.4.0 Era) -->
                <svg viewBox="0 0 512 512" width="20" height="20" fill="currentColor">
                    <path d="M407 112.1C448.1 144.1 480 181.5 480 220.2c0 88.3-119.8 158.2-120 158.3l-1.9 .9c-6.8 3.3-14.7 4.1-22.1 2.1c-7.4-2.1-13.8-6.9-17.7-13.4l-11.2-18.4c-11.4 6.7-24.1 10.2-37.1 10.2c-40.4 0-73.2-32.8-73.2-73.2c0-21 8.8-40 23-53.5l-44-72.3C161.4 135.2 135.2 161.4 112.1 189.6c-31.1 38.1-48.1 84.1-48.1 131.6c0 102.5 83.1 185.6 185.6 185.6c43 0 83.3-14.8 115.1-41.9c31.8 27.1 72.1 41.9 115.1 41.9c102.5 0 185.6-83.1 185.6-185.6c0-47.5-17-93.5-48.1-131.6c-23.1-28.2-49.3-54.4-76.3-78.2l-3.3-2.9C434.3 84.2 420.7 70.6 407 57L407 112.1z"/>
                </svg>
            </span>
            <span class="m3-share-btn__label">BlueSky</span>
        </a>

        <!-- Misskey.io -->
        <a href="https://misskey-hub.net/share/?text=<?php echo $title; ?>&url=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--misskey" 
           data-url="<?php echo esc_url($permalink); ?>"
           title="Misskeyでシェア" 
           aria-label="Misskeyでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <span class="m3-share-btn__icon">
                <!-- Official Misskey "Mi" Symbol -->
                <svg viewBox="0 0 160 160" width="20" height="20" fill="currentColor">
                    <g transform="matrix(0.25, 0, 0, 0.25, -45, -45)">
                        <path d="M256.418,188.976C248.558,188.944 240.758,190.308 233.379,193.013C220.308,197.613 209.533,205.888 201.091,217.802C193.02,229.329 188.977,242.195 188.977,256.409L188.977,508.89C188.977,527.332 195.52,543.29 208.576,556.732C222.032,569.803 237.99,576.331 256.418,576.331C275.259,576.331 291.204,569.803 304.274,556.747C317.73,543.291 324.441,527.332 324.441,508.89L324.441,462.983C324.584,453.04 334.824,455.655 340.01,462.983C349.691,479.76 372.36,494.119 394.193,494.119C416.026,494.119 438.005,482.196 448.375,462.983C452.304,458.354 463.377,450.455 464.52,462.983L464.52,508.89C464.52,527.332 471.047,543.29 484.104,556.732C497.574,569.803 513.511,576.331 531.953,576.331C550.78,576.331 566.739,569.803 579.809,556.747C593.265,543.291 599.977,527.332 599.977,508.89L599.977,256.409C599.977,242.195 595.752,229.329 587.309,217.802C579.224,205.874 568.653,197.613 555.597,193.013C547.912,190.314 540.228,188.976 532.543,188.976C511.788,188.976 494.301,197.046 480.073,213.188L411.636,293.281C410.107,294.438 405.006,303.247 394.178,303.247C383.379,303.247 378.868,294.439 377.325,293.296L308.297,213.188C294.47,197.046 277.173,188.976 256.418,188.976ZM682.904,188.983C666.763,188.983 652.926,194.748 641.404,206.271C630.261,217.413 624.691,231.054 624.691,247.196C624.691,263.338 630.261,277.174 641.404,288.697C652.926,299.839 666.763,305.41 682.904,305.41C699.046,305.41 712.88,299.839 724.412,288.697C735.935,277.174 741.693,263.338 741.693,247.196C741.693,231.054 735.935,217.413 724.412,206.271C712.88,194.748 699.046,188.983 682.904,188.983ZM683.473,316.947C667.331,316.947 653.495,322.713 641.972,334.236C630.449,345.768 624.691,359.602 624.691,375.744L624.691,518.118C624.691,534.259 630.449,548.095 641.972,559.618C653.504,570.761 667.341,576.331 683.473,576.331C699.624,576.331 713.27,570.761 724.412,559.618C735.935,548.095 741.693,534.259 741.693,518.118L741.693,375.744C741.693,359.593 735.935,345.759 724.412,334.236C713.261,322.713 699.614,316.947 683.473,316.947Z" />
                    </g>
                </svg>
            </span>
            <span class="m3-share-btn__label">Misskey</span>
        </a>

        <!-- システムシェア (モバイルのみ) -->
        <?php if (wp_is_mobile()) : ?>
        <button class="m3-share-btn m3-share-btn--system" 
                id="m3-system-share-trigger" 
                data-url="<?php echo esc_url($permalink); ?>"
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
                title="リンクをコピー" 
                aria-label="この記事のURLをコピーする">
            <span class="material-symbols-outlined m3-copy-icon" aria-hidden="true">content_copy</span>
            <span class="m3-share-btn__label m3-copy-label">コピー</span>
        </button>
    </div>
</div>