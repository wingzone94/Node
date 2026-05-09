<?php 
$ai_summary = get_post_meta(get_the_ID(), '_node_ai_summary', true);
if (!empty($ai_summary)) : ?>
    <details class="m3-nexus-abstract ai-summary-accordion" style="--ai-vibe-color: #FF9800;">
        <summary class="m3-nexus-abstract__badge">
            <span class="material-symbols-outlined">psychology</span>
            INTELLIGENCE SUMMARY
            <span class="material-symbols-outlined expand-icon">expand_more</span>
        </summary>
        <div class="m3-nexus-abstract__content">
            <?php echo nl2br(esc_html(strip_tags($ai_summary))); ?>
        </div>
    </details>
<?php endif; ?>