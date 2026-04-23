<?php

defined('ABSPATH') || exit;

/** @var string Block editor + front: show `<h1>` on the page (default on). */
const FS_SHOW_PAGE_TITLE_META = '_fs_show_page_title';

/** @var string Admin dashboard welcome panel: list under “Pinned”. */
const FS_PIN_TO_DASHBOARD_META = '_fs_pin_to_dashboard';

/**
 * Post types that get “Pin to dashboard” (block editor + theme types: post, page, CPTs).
 *
 * @return array<int, string>
 */
function fs_pin_to_dashboard_post_types(): array
{
	$result = [];
	foreach (fs_theme_post_types() as $name) {
		if (post_type_supports($name, 'editor')) {
			$result[] = $name;
		}
	}

	return apply_filters('fs_pin_to_dashboard_post_types', $result);
}

/**
 * Whether this post type supports the “Show title” Summary control and singular &lt;h1&gt; toggle.
 * Pages always do. Blog (`post`) and CPTs only when `has_page_title_toggle` is true in config/custom-post-types.php.
 *
 * @param string $post_type Post type slug.
 */
function fs_post_type_has_page_title_toggle(string $post_type): bool
{
	if ($post_type === 'page') {
		return true;
	}
	if ($post_type === 'post') {
		$post_cfg = fs_config_cpt('post');

		return is_array($post_cfg) && !empty($post_cfg['has_page_title_toggle']);
	}
	$cpts = fs_config_cpt('cpts');
	if (!is_array($cpts) || !isset($cpts[$post_type]) || !is_array($cpts[$post_type])) {
		return false;
	}

	return !empty($cpts[$post_type]['has_page_title_toggle']);
}

/**
 * Post types that register {@see FS_SHOW_PAGE_TITLE_META} and show the editor control.
 *
 * @return array<int, string>
 */
function fs_show_title_toggle_post_types(): array
{
	$result = [];
	foreach (fs_theme_post_types() as $slug) {
		if (fs_post_type_has_page_title_toggle($slug)) {
			$result[] = $slug;
		}
	}

	return apply_filters('fs_show_title_toggle_post_types', array_unique($result));
}

/**
 * Register post meta for pages (REST / block editor).
 *
 * @return void
 */
function fs_page_editor_options_register_meta(): void
{
	$auth = static function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};
	$show_title_args = [
		'type' => 'boolean',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
		'sanitize_callback' => static function ($value): bool {
			return (bool) $value;
		},
		'default' => true,
	];
	foreach (fs_show_title_toggle_post_types() as $post_type) {
		register_post_meta($post_type, FS_SHOW_PAGE_TITLE_META, $show_title_args);
	}
	$pin_args = [
		'type' => 'boolean',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
		'sanitize_callback' => static function ($value): bool {
			return (bool) $value;
		},
		'default' => false,
	];
	foreach (fs_pin_to_dashboard_post_types() as $post_type) {
		register_post_meta($post_type, FS_PIN_TO_DASHBOARD_META, $pin_args);
	}
}
add_action('init', 'fs_page_editor_options_register_meta');

/**
 * Whether the singular template should output a visible &lt;h1&gt; title (default: yes).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function fs_page_should_show_title(int $post_id): bool
{
	$type = get_post_type($post_id);
	if (!$type || !fs_post_type_has_page_title_toggle($type)) {
		return true;
	}
	$raw = get_post_meta($post_id, FS_SHOW_PAGE_TITLE_META, true);
	if ($raw === '' || $raw === false) {
		return true;
	}

	return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
}

/**
 * Strings for the block editor (Summary sidebar).
 *
 * @return void
 */
function fs_page_editor_options_localize(): void
{
	wp_localize_script('fromscratch-editor', 'fromscratchPageSidebarOptions', [
		'labelShowTitlePage' => __('Show page title', 'fromscratch'),
		'labelPinDashboard' => __('Pin to dashboard', 'fromscratch'),
		'pinPostTypes' => fs_pin_to_dashboard_post_types(),
		'showTitlePostTypes' => fs_show_title_toggle_post_types(),
	]);
}
add_action('enqueue_block_editor_assets', 'fs_page_editor_options_localize', 12);
