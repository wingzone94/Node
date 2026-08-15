<?php
/**
 * Google preferred source CTA.
 *
 * @package Luminous_Core
 */

$context = isset( $args['context'] ) ? sanitize_key( $args['context'] ) : 'article';

if ( ! in_array( $context, array( 'article', 'footer' ), true ) ) {
    return;
}

if ( '1' !== get_option( 'node_preferred_source_enabled', '1' ) ) {
    return;
}

$placement = get_option( 'node_preferred_source_placement', 'article' );
$visible   = 'both' === $placement || $context === $placement;

if ( ! $visible ) {
    return;
}

$default_url = defined( 'NODE_PREFERRED_SOURCE_DEFAULT_URL' ) ? NODE_PREFERRED_SOURCE_DEFAULT_URL : 'https://google.com/preferences/source?q=luminous-core.net';
$url         = get_option( 'node_preferred_source_url', $default_url );
$url         = apply_filters( 'node_preferred_source_url', $url, $context );
$badge_base  = defined( 'NODE_PREFERRED_SOURCE_BADGE_BASE_URL' ) ? NODE_PREFERRED_SOURCE_BADGE_BASE_URL : 'https://luminous-core.net/wp-content/uploads/2026/07/';
$badge_base  = trailingslashit( apply_filters( 'node_preferred_source_badge_base_url', $badge_base, $context ) );

if ( ! $url ) {
    return;
}
?>

<div class="node-preferred-source node-preferred-source--<?php echo esc_attr( $context ); ?>">
    <a class="node-preferred-source__button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Googleで優先ソースに追加">
        <img
            class="node-preferred-source__badge node-preferred-source__badge--light"
            src="<?php echo esc_url( $badge_base . 'google_preferred_source_badge_light_JA.png' ); ?>"
            srcset="<?php echo esc_url( $badge_base . 'google_preferred_source_badge_light_JA.png' ); ?> 1x, <?php echo esc_url( $badge_base . 'google_preferred_source_badge_light_JA@2x.png' ); ?> 2x"
            width="338"
            height="106"
            alt="Googleで優先ソースに追加"
            loading="lazy"
            decoding="async"
        >
        <img
            class="node-preferred-source__badge node-preferred-source__badge--dark"
            src="<?php echo esc_url( $badge_base . 'google_preferred_source_badge_dark_JA.png' ); ?>"
            srcset="<?php echo esc_url( $badge_base . 'google_preferred_source_badge_dark_JA.png' ); ?> 1x, <?php echo esc_url( $badge_base . 'google_preferred_source_badge_dark_JA@2x.png' ); ?> 2x"
            width="338"
            height="106"
            alt=""
            loading="lazy"
            decoding="async"
            aria-hidden="true"
        >
    </a>
    <p class="node-preferred-source__text">Google検索でLuminous Coreを優先ソースに追加できます。トップニュースやAIによる概要で見つけやすくなる場合があります。</p>
</div>
