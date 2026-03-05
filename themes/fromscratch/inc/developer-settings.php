<?php

defined('ABSPATH') || exit;

/**
 * Settings → Developer. Each section is its own page at options-general.php?page=fs-developer[-*].
 * Requires theme-settings.php for option group constants.
 */

const FS_DEVELOPER_TABS_BASE = [
	'general'  => ['label' => 'General'],
	'features' => ['label' => 'Features'],
	'access'   => ['label' => 'User rights'],
	'security' => ['label' => 'Security'],
	'tools'    => ['label' => 'Tools'],
];

/** Tab definitions: base tabs + Languages (before Tools) when feature is on. */
function fs_developer_settings_available_tabs(): array
{
	$tabs = FS_DEVELOPER_TABS_BASE;
	if (function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('languages')) {
		$out = [];
		foreach ($tabs as $key => $val) {
			if ($key === 'tools') {
				$out['languages'] = ['label' => 'Languages'];
			}
			$out[$key] = $val;
		}
		$tabs = $out;
	}
	return $tabs;
}

/** Page slug for a tab. General = fs-developer; others = fs-developer-features, etc. */
function fs_developer_settings_page_slug(string $tab): string
{
	return $tab === 'general' ? 'fs-developer' : 'fs-developer-' . $tab;
}

/** Current tab derived from $_GET['page'] (e.g. fs-developer-features → features). */
function fs_developer_settings_current_tab_from_page(): string
{
	$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
	$available = array_keys(fs_developer_settings_available_tabs());
	if ($page === 'fs-developer') {
		return 'general';
	}
	$prefix = 'fs-developer-';
	if (strpos($page, $prefix) !== 0) {
		return $available[0] ?? 'general';
	}
	$tab = substr($page, strlen($prefix));
	return in_array($tab, $available, true) ? $tab : ($available[0] ?? 'general');
}

/** Menu position for a tab (1-based) for consistent order under Settings. */
function fs_developer_tab_position(string $tab): int
{
	$tabs = array_keys(fs_developer_settings_available_tabs());
	$i = array_search($tab, $tabs, true);
	return $i === false ? 99 : (1 + $i);
}

/** All developer page slugs. */
function fs_developer_settings_page_slugs(): array
{
	$out = [];
	foreach (array_keys(fs_developer_settings_available_tabs()) as $tab) {
		$out[] = fs_developer_settings_page_slug($tab);
	}
	return $out;
}

/**
 * Load hook for a Settings submenu page (for non-developer redirect).
 */
function fs_developer_settings_load_hook(string $page_slug): string
{
	return 'load-settings_page_' . $page_slug;
}

// Non-developer: redirect to Theme settings when opening any Developer page.
// Set global $title so admin-header.php doesn't get null (we hide submenu items below).
foreach (fs_developer_settings_page_slugs() as $slug) {
	add_action(fs_developer_settings_load_hook($slug), function () use ($slug) {
		global $title;
		$tab = $slug === 'fs-developer' ? 'general' : substr($slug, strlen('fs-developer-'));
		$tabs = fs_developer_settings_available_tabs();
		$label = $tabs[$tab]['label'] ?? $slug;
		$title = $slug === 'fs-developer' ? __('Developer', 'fromscratch') : sprintf(__('Developer › %s', 'fromscratch'), $label);
		if (!current_user_can('manage_options')) {
			return;
		}
		if (function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id())) {
			return;
		}
		wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings'));
		exit;
	}, 1);
}

require_once __DIR__ . '/developer-settings-nav.php';

$fs_developer_settings_dir = __DIR__ . '/developer-settings/';
require_once $fs_developer_settings_dir . 'general.php';
require_once $fs_developer_settings_dir . 'features.php';
require_once $fs_developer_settings_dir . 'access.php';
require_once $fs_developer_settings_dir . 'security.php';
require_once $fs_developer_settings_dir . 'tools.php';
if (function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('languages')) {
	require_once $fs_developer_settings_dir . 'languages.php';
}

// Hide Developer sub-pages from the Settings menu; only "Developer" (General) remains visible.
add_action('admin_menu', function () {
	global $submenu;
	if (!isset($submenu['options-general.php']) || !current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$hide = array_diff(fs_developer_settings_page_slugs(), ['fs-developer']);
	foreach ($submenu['options-general.php'] as $i => $item) {
		if (isset($item[2]) && in_array($item[2], $hide, true)) {
			unset($submenu['options-general.php'][$i]);
		}
	}
}, 999);

// When on any Developer page, highlight "Developer" in the Settings menu.
add_filter('submenu_file', function ($submenu_file, $parent_file) {
	if ($parent_file !== 'options-general.php' || !isset($_GET['page'])) {
		return $submenu_file;
	}
	$page = sanitize_key($_GET['page']);
	if (in_array($page, fs_developer_settings_page_slugs(), true)) {
		return 'fs-developer';
	}
	return $submenu_file;
}, 10, 2);

/**
 * Delete revisions, keeping the N most recent per post.
 *
 * @param int $keep Number of revisions to keep per post (0 = delete all).
 * @return int Number of revisions deleted.
 */
function fs_clean_revisions(int $keep): int
{
	global $wpdb;
	$deleted = 0;
	$parent_ids = $wpdb->get_col("SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent > 0");
	if (empty($parent_ids)) {
		return 0;
	}
	foreach ($parent_ids as $parent_id) {
		$revisions = wp_get_post_revisions((int) $parent_id, ['orderby' => 'date', 'order' => 'DESC']);
		if (empty($revisions)) {
			continue;
		}
		$to_delete = $keep === 0 ? $revisions : array_slice($revisions, $keep);
		foreach ($to_delete as $revision) {
			if (wp_delete_post_revision($revision->ID)) {
				$deleted++;
			}
		}
	}
	return $deleted;
}
