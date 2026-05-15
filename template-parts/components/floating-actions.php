<div class="m3-action-stack <?php echo is_singular() ? 'is-singular' : ''; ?>">
    <!-- 1. Back to Top -->
    <button id="m3-back-to-top" class="m3-fab">
        <span class="material-symbols-outlined">arrow_upward</span>
        <span class="m3-fab-text">TOP</span>
        <span class="m3-fab-label-top">最上部へ戻る</span>
    </button>

    <?php if (is_singular()) : 
        $post_id = get_the_ID();
        $has_ai = !empty(apply_filters('luminous_get_ai_summary', '', $post_id));
        $has_comments = comments_open($post_id) || get_comments_number($post_id) > 0;
    ?>
    <!-- 2. Comment Trigger -->
    <?php if ($has_comments) : ?>
    <button id="m3-scroll-to-comments" class="m3-fab m3-fab--extended">
        <span class="material-symbols-outlined">chat_bubble</span>
        <span class="m3-fab-text">コメント</span>
        <span class="m3-fab-label-top">コメント欄へ</span>
    </button>
    <?php endif; ?>

    <!-- 3. AI Summary Jump -->
    <?php if ($has_ai) : ?>
    <button id="m3-jump-to-ai" class="m3-fab m3-fab--ai-expressive">
        <span class="material-symbols-outlined">auto_awesome</span>
        <span class="m3-fab-text">AI要約</span>
        <span class="m3-fab-label-top">AI要約へ</span>
    </button>
    <?php endif; ?>

    <!-- 4. TOC Trigger -->
    <button id="m3-toc-trigger" class="m3-fab m3-fab--extended">
        <span class="material-symbols-outlined">list</span>
        <span class="m3-fab-text">目次</span>
        <span class="m3-fab-label-top">目次を表示</span>
    </button>
    <?php endif; ?>
</div>