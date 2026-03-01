<?php

defined('ABSPATH') || exit;

// ─── Foundation ─────────────────────────────────────────────────────────────
require_once 'inc/bootstrap.php';
require_once 'inc/config.php';
require_once 'inc/language.php';
require_once 'inc/features.php';

// ─── HTTP & Global ──────────────────────────────────────────────────────────
require_once 'inc/headers.php';
require_once 'inc/clean-up.php';
require_once 'inc/head.php';

// ─── Core Theme ──────────────────────────────────────────────────────────────
require_once 'inc/theme-setup.php';
require_once 'inc/menu.php';
require_once 'inc/design.php';

// ─── Admin ───────────────────────────────────────────────────────────────────
if (is_admin()) {
	// Install wizard (when setup not completed or viewing success page)
	if (!fs_setup_completed() || isset($_GET['fromscratch_success'])) {
		require_once 'inc/install.php';
	}
	require_once 'inc/user-rights.php';
	require_once 'inc/theme-settings.php';
	require_once 'inc/dashboard.php';
	require_once 'inc/media-sizes.php';
}

// ─── Features ────────────────────────────────────────────────────────────────
require_once 'inc/login-limit.php';
require_once 'inc/site-password.php';
require_once 'inc/assets.php';
require_once 'inc/cpt.php';

// Optional features (gated by Settings → Theme → General)
if (fs_theme_feature_enabled('svg')) {
	require_once 'inc/svg-support.php';
}
if (fs_theme_feature_enabled('duplicate_post')) {
	require_once 'inc/duplicate-post.php';
}
if (fs_theme_feature_enabled('seo')) {
	require_once 'inc/seo.php';
}
