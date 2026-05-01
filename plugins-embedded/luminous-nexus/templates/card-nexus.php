<?php
/**
 * Nexus Abstract & Game Info Template
 *
 * @package Luminous_Nexus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = get_the_ID();
$ai_summary = apply_filters( 'luminous_get_ai_summary', '', $post_id );
$game_info  = get_post_meta( $post_id, '_node_game_info', true );

if ( empty( $ai_summary ) && ( ! is_array( $game_info ) || empty( $game_info['title'] ) ) ) {
    return;
}
?>

<div class="m3-nexus-card">
    <?php if ( ! empty( $ai_summary ) ) : ?>
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

    <?php if ( is_array( $game_info ) && ! empty( $game_info['title'] ) ) : ?>
    <aside class="m3-game-box">
        <div class="m3-game-box__header">
            <span class="material-symbols-outlined">sports_esports</span>
            <h3 class="m3-game-box__title"><?php echo esc_html( $game_info['title'] ); ?></h3>
        </div>
        <?php if ( ! empty( $game_info['summary'] ) ) : ?>
        <div class="m3-game-box__summary">
            <?php echo nl2br(esc_html( $game_info['summary'] )); ?>
        </div>
        <?php endif; ?>
        <?php if ( ! empty( $game_info['links'] ) && is_array( $game_info['links'] ) ) : ?>
        <div class="m3-game-box__links">
            <?php foreach ( $game_info['links'] as $label => $url ) : ?>
                <a href="<?php echo esc_url( $url ); ?>" class="m3-chip m3-chip--assistive" target="_blank" rel="noopener">
                    <span class="material-symbols-outlined">link</span>
                    <?php echo esc_html( $label ); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</div>
