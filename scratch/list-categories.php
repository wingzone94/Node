<?php
require_once('wp-load.php');
$categories = get_categories(['hide_empty' => false]);
foreach ($categories as $cat) {
    echo "Name: " . $cat->name . " | Slug: " . $cat->slug . "\n";
}
