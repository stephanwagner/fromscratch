<?php

/**
 * Bootstrap
 */
require_once 'inc/bootstrap.php';

/**
 * Cache headers
 */
require_once 'inc/headers.php';

/**
 * Languages
 */
require_once 'inc/lang.php';

/**
 * Install
 */
if (is_admin()) {
    require_once 'inc/install.php';
}

/**
 * Developer user (flag and Edit User section)
 */
if (is_admin()) {
    require_once 'inc/developer-user.php';
}

/**
 * Clean up
 */
require_once 'inc/clean-up.php';

/**
 * Head
 */
require_once 'inc/head.php';

/**
 * Theme setup
 */
require_once 'inc/theme-setup.php';

/**
 * Menu
 */
require_once 'inc/menu.php';

/**
 * Dashboard
 */
require_once 'inc/dashboard.php';

/**
 * Login attempt limiting
 */
require_once 'inc/login-limit.php';

/**
 * Site password protection
 */
require_once 'inc/site-password.php';

/**
 * Assets
 */
require_once 'inc/assets.php';

/**
 * Custom post types
 */
require_once 'inc/cpt.php';

/**
 * Variables
 */
require_once 'inc/variables.php';

/**
 * Media: extra image size options on Settings → Media (from config image_sizes_extra)
 */
if (is_admin()) {
	require_once 'inc/media-sizes.php';
}

/**
 * Support SVG
 */
if (fs_theme_feature_enabled('svg')) {
    require_once 'inc/svg-support.php';
}

/**
 * Duplicate post/page/custom post type
 */
if (fs_theme_feature_enabled('duplicate_post')) {
    require_once 'inc/duplicate-post.php';
}

/**
 * SEO (post/page meta and document panel)
 */
if (fs_theme_feature_enabled('seo')) {
    require_once 'inc/seo.php';
}
