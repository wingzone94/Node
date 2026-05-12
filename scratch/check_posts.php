<?php
define('WP_USE_THEMES', false);
require_once('wp-load.php');

$count = wp_count_posts();
echo "Published posts: " . $count->publish . "\n";

$query = new WP_Query(['post_type' => 'post', 'posts_per_page' => -1]);
echo "Total posts found: " . $query->post_count . "\n";

foreach ($query->posts as $post) {
    echo "ID: " . $post->ID . " - Title: " . $post->post_title . "\n";
}
