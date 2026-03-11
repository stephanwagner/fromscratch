<?php

defined('ABSPATH') || exit;

// Foundation
require_once 'inc/bootstrap.php';
require_once 'inc/config.php';
require_once 'inc/language.php';
require_once 'inc/features.php';

// HTTP & Global
require_once 'inc/headers.php';
require_once 'inc/clean-up.php';
require_once 'inc/head.php';

// Core Theme
require_once 'inc/theme-setup.php';
require_once 'inc/menu.php';
require_once 'inc/design.php';
require_once 'inc/redirects.php';

// User rights + theme settings + developer settings (needed on frontend for admin bar performance node)
require_once 'inc/user-rights.php';
require_once 'inc/theme-settings.php';
require_once 'inc/developer-settings.php';

// Admin-only
if (is_admin()) {
	// Install wizard (when setup not completed or viewing success page)
	if (!fs_setup_completed() || isset($_GET['fromscratch_success'])) {
		require_once 'inc/install.php';
	}
	require_once 'inc/dashboard.php';
	require_once 'inc/media-sizes.php';
}

// Helpers
require_once 'inc/helpers/page-blocker.php';

// Features
require_once 'inc/login-client-logo.php';
require_once 'inc/assets.php';
require_once 'inc/cpt.php';

// Mail (SMTP / SendGrid from Developer › System)
require_once 'inc/mail.php';

// Security
require_once 'inc/security/password-protection.php';
require_once 'inc/security/maintenance-mode.php';
require_once 'inc/security/login-limit.php';

// Optional features
if (fs_theme_feature_enabled('svg')) {
	require_once 'inc/svg-support.php';
}
if (fs_theme_feature_enabled('duplicate_post')) {
	require_once 'inc/duplicate-post.php';
}
if (fs_theme_feature_enabled('seo')) {
	require_once 'inc/seo.php';
}
if (fs_theme_feature_enabled('post_expirator')) {
	require_once 'inc/post-expirator.php';
}
if (fs_theme_feature_enabled('languages')) {
	require_once 'inc/content-languages.php';
}
if (fs_theme_feature_enabled('blocked_ips')) {
	require_once 'inc/security/ip-blocker.php';
}
if (fs_theme_feature_enabled('webp')) {
	require_once 'inc/image-webp.php';
}
if (fs_theme_feature_enabled('media_folders')) {
	require_once 'inc/media-library-folders.php';
}