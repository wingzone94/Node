<?php
/**
 * Social Share Buttons Template
 */
$url = urlencode(get_permalink());
$title = urlencode(get_the_title());
?>

<div class="m3-share-section">
    <h3 class="m3-share-title">この記事をシェアする</h3>
    <div class="m3-share-buttons">
        <!-- X (Twitter) -->
        <a href="https://twitter.com/intent/tweet?url=<?php echo $url; ?>&text=<?php echo $title; ?>" 
           class="m3-share-btn m3-share-btn--x" 
           title="Xでシェア" 
           aria-label="Xでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Xでシェアする</span>
        </a>

        <!-- Facebook -->
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--facebook" 
           title="Facebookでシェア" 
           aria-label="Facebookでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Facebookでシェアする</span>
        </a>

        <!-- LINE -->
        <a href="https://social-plugins.line.me/lineit/share?url=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--line" 
           title="LINEで送る" 
           aria-label="LINEで送る" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-line" aria-hidden="true"></i>
            <span class="m3-share-btn__label">LINEで送る</span>
        </a>

        <!-- はてなブックマーク -->
        <a href="https://b.hatena.ne.jp/add?mode=confirm&url=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--hatebu" 
           title="はてなブックマークに追加" 
           aria-label="はてなブックマークに追加する" 
           target="_blank" 
           rel="noopener noreferrer">
            <span class="m3-share-btn__icon" aria-hidden="true">B!</span>
            <span class="m3-share-btn__label">はてなブックマークに追加する</span>
        </a>

        <!-- Threads -->
        <a href="https://www.threads.net/intent/post?text=<?php echo $url; ?>" 
           class="m3-share-btn m3-share-btn--threads" 
           title="Threadsでシェア" 
           aria-label="Threadsでシェアする" 
           target="_blank" 
           rel="noopener noreferrer">
            <i class="fa-brands fa-threads" aria-hidden="true"></i>
            <span class="m3-share-btn__label">Threadsでシェアする</span>
        </a>

        <!-- リンクをコピー -->
        <button class="m3-share-btn m3-share-btn--copy" 
                id="m3-copy-trigger" 
                title="リンクをコピー" 
                aria-label="この記事のURLをコピーする">
            <span class="material-symbols-outlined m3-copy-icon" aria-hidden="true">content_copy</span>
            <span class="m3-share-btn__label m3-copy-label">リンクをコピー</span>
        </button>
    </div>
</div>