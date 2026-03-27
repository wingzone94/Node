<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header style="padding: 2rem; background: var(--md-sys-color-primary-container);">
    <div style="max-width: var(--max-width); margin: 0 auto;">
        <h1 style="margin: 0; font-weight: 700; color: var(--md-sys-color-on-primary-container);">
            <?php bloginfo('name'); ?>
        </h1>
    </div>
</header>