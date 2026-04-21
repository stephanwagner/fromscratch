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
 * Register post meta for pages (REST / block editor).
 *
 * @return void
 */
function fs_page_editor_options_register_meta(): void
{
	$auth = static function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};
	register_post_meta('page', FS_SHOW_PAGE_TITLE_META, [
		'type' => 'boolean',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
		'sanitize_callback' => static function ($value): bool {
			return (bool) $value;
		},
		'default' => true,
	]);
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
 * Whether the page template should output a visible title (default: yes).
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function fs_page_should_show_title(int $post_id): bool
{
	if (get_post_type($post_id) !== 'page') {
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
		'labelShowTitle' => __('Show page title', 'fromscratch'),
		'labelPinDashboard' => __('Pin to dashboard', 'fromscratch'),
		'pinPostTypes' => fs_pin_to_dashboard_post_types(),
	]);
}
add_action('enqueue_block_editor_assets', 'fs_page_editor_options_localize', 12);
