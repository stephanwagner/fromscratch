<?php

defined('ABSPATH') || exit;

/**
 * Settings → Developer. Each section is its own page at options-general.php?page=fs-developer[-*].
 * Requires theme-settings.php for option group constants.
 */

const FS_DEVELOPER_TABS_BASE = [
	'general'  => ['label' => 'General'],
	'system'   => ['label' => 'System'],
	'security' => ['label' => 'Security'],
	'features' => ['label' => 'Features'],
	'access'   => ['label' => 'User rights'],
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

/**
 * Shared navigation for Developer settings (tab bar). Include this in each developer page.
 * Expects fs_developer_settings_current_tab_from_page() and fs_developer_settings_available_tabs().
 */
function fs_developer_settings_render_nav(): void
{
	$current = fs_developer_settings_current_tab_from_page();
	$tabs = fs_developer_settings_available_tabs();
	$base_url = admin_url('options-general.php');

	$password_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5 58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm296.5-223.5Q560-327 560-360t-23.5-56.5Q513-440 480-440t-56.5 23.5Q400-393 400-360t23.5 56.5Q447-280 480-280t56.5-23.5ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg>';
	$maintenance_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-360q-100 0-170-70t-70-170q0-20 3-40t11-38q5-10 12.5-15t16.5-7q9-2 18.5.5T199-689l105 105 72-72-105-105q-8-8-10.5-17.5T260-797q2-9 7-16.5t15-12.5q18-8 38-11t40-3q100 0 170 70t70 170q0 23-4 43.5T584-516l202 200q29 29 29 71t-29 71q-29 29-71 29t-71-30L444-376q-20 8-40.5 12t-43.5 4Z"/></svg>';
	$search_visibility_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-62 17-117.5T146-701l-91-91q-12-12-12-28.5T55-849q12-12 28.5-12t28.5 12l736 736q12 12 12 28t-12 28q-12 12-28.5 12T791-57l-90-89q-48 32-103.5 49T480-80Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm440-318q0 45-10 86.5T843-314q-7 14-22.5 18.5T791-299q-14-8-19.5-24t1.5-31q13-30 20-61.5t7-64.5q0-98-54.5-179T600-776v16q0 33-23.5 56.5T520-680h-60v17q0 14-12 19t-22-5L308-767q-18-18-14.5-43t26.5-36q37-17 77-25.5t83-8.5q83 0 156 31.5T763-763q54 54 85.5 127T880-480Z"/></svg>';
?>
	<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
		<?php
		foreach ($tabs as $slug => $def) {
			$icons = '';
			if ($slug === 'system' && (int) get_option('blog_public', 1) === 0) {
				$icons .= '<div class="fs-tab-icon -warning">' . $search_visibility_icon . '</div>';
			}
			if ($slug === 'security') {
				if (get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') {
					$icons .= '<div class="fs-tab-icon">' . $password_icon . '</div>';
				}
				if (get_option('fromscratch_maintenance_mode') === '1') {
					$icons .= '<div class="fs-tab-icon">' . $maintenance_icon . '</div>';
				}
			}
			$url = add_query_arg('page', fs_developer_settings_page_slug($slug), $base_url);
			echo '<a href="' . esc_url($url) . '" class="nav-tab ' . ($current === $slug ? 'nav-tab-active' : '') . ($icons !== '' ? ' has-icons' : '') . '">';
			echo '<span>' . esc_html(__($def['label'], 'fromscratch')) . '</span>';
			if ($icons !== '') {
				echo '<span class="fs-tab-icons">' . $icons . '</span>';
			}
			echo '</a>';
		}
		?>
	</nav>
<?php
}

$fs_developer_settings_dir = __DIR__ . '/developer-settings/';
require_once $fs_developer_settings_dir . 'general.php';
require_once $fs_developer_settings_dir . 'system.php';
require_once $fs_developer_settings_dir . 'security.php';
require_once $fs_developer_settings_dir . 'features.php';
require_once $fs_developer_settings_dir . 'access.php';
require_once $fs_developer_settings_dir . 'tools.php';
require_once __DIR__ . '/performance-indicator.php';
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
}, 25);

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
