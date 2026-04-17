<?php 
$ai_summary = get_post_meta(get_the_ID(), '_node_ai_summary', true);
if (!empty($ai_summary)) : ?>
    <aside class="m3-nexus-abstract">
        <div class="m3-nexus-abstract__badge">
            <span class="material-symbols-outlined">psychology</span>
            NEXUS ABSTRACT
        </div>
        <div class="m3-nexus-abstract__content">
            <?php echo nl2br(esc_html($ai_summary)); ?>
        </div>
    </aside>
<?php endif; ?>