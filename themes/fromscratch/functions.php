<?php

// Bootstrap
require_once 'inc/bootstrap.php';

// Config
require_once 'inc/config.php';

// Cache headers
require_once 'inc/headers.php';

// Languages
require_once 'inc/language.php';

// Install (wizard and success page)
if (is_admin() && (!fs_setup_completed() || isset($_GET['fromscratch_success']))) {
    require_once 'inc/install.php';
}

// User rights (developer flag, restricted settings for non-developers)
if (is_admin()) {
    require_once 'inc/user-rights.php';
}

// Clean up
require_once 'inc/clean-up.php';

// Head
require_once 'inc/head.php';

// Theme setup
require_once 'inc/theme-setup.php';

// Menu
require_once 'inc/menu.php';

// Dashboard
require_once 'inc/dashboard.php';

// Login attempt limiting
require_once 'inc/login-limit.php';

// Site password protection
require_once 'inc/site-password.php';

// Assets
require_once 'inc/assets.php';

// Custom post types
require_once 'inc/cpt.php';

// Variables
require_once 'inc/variables.php';

// Media: extra image size options on Settings → Media (from config image_sizes_extra)
if (is_admin()) {
    require_once 'inc/media-sizes.php';
}

// Support SVG
if (fs_theme_feature_enabled('svg')) {
    require_once 'inc/svg-support.php';
}

// Duplicate post/page/custom post type
if (fs_theme_feature_enabled('duplicate_post')) {
    require_once 'inc/duplicate-post.php';
}

// SEO (post/page meta and document panel)
if (fs_theme_feature_enabled('seo')) {
    require_once 'inc/seo.php';
}
