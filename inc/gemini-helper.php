<?php
/**
 * Static 404 Copyright Helper (API usage removed for load performance)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the original copyright notice for each theme
 * 
 * @param string $theme The current 404 theme key
 * @return string The copyright notice
 */
function node_get_404_copyright($theme) {
    $copyrights = [
        'minecraft-lava'    => '© Mojang AB',
        'minecraft-creeper' => '© Mojang AB',
        'minecraft-raid'    => '© Mojang AB',
        'fortnite'          => '© Epic Games, Inc.',
        'material-expressive' => '© Google LLC',
        'windows-bsod'      => '© Microsoft Corporation'
    ];

    return isset($copyrights[$theme]) ? $copyrights[$theme] : '© ' . date('Y') . ' ' . get_bloginfo('name');
}
