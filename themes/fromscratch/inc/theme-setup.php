<?php

defined('ABSPATH') || exit;

/**
 * Register theme menus from config.
 *
 * @return void
 */
function fs_menus(): void
{
	add_theme_support('menus');
	register_nav_menus(fs_config('menus'));
}
add_action('after_setup_theme', 'fs_menus');

/**
 * Support alignwide and alignfull for block editor.
 *
 * @return void
 */
function fs_add_alignwide(): void
{
	add_theme_support('align-wide');
}
add_action('after_setup_theme', 'fs_add_alignwide');

/**
 * Support post thumbnails (featured images).
 *
 * @return void
 */
function fs_add_post_thumbnails(): void
{
	add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'fs_add_post_thumbnails');

/**
 * Remove blogs menu and block direct access to post screens when blogs are disabled.
 *
 * @return void
 */
function fs_remove_blogs(): void
{
	add_action('admin_menu', function () {
		remove_menu_page('edit.php');
	});

	add_action('admin_init', function () {
		global $pagenow;
		$blocked = ['edit.php', 'post.php', 'post-new.php'];
		if (in_array($pagenow, $blocked, true)) {
			wp_safe_redirect(admin_url());
			exit;
		}
	});
}

// Disable blogs when the feature is turned off in Settings → Theme (default: enabled). Run late so options are available.
add_action('after_setup_theme', function () {
	if (function_exists('fs_theme_feature_enabled') && !fs_theme_feature_enabled('blogs')) {
		fs_remove_blogs();
	}
}, 20);

/**
 * Filter excerpt length from config.
 *
 * @return int Length used when trimming excerpts.
 */
function fs_excerpt_length(): int
{
	return (int) fs_config('excerpt_length');
}
add_filter('excerpt_length', 'fs_excerpt_length');

/**
 * Filter excerpt "more" string from config.
 *
 * @return string Text shown after truncated excerpt (e.g. "…").
 */
function fs_excerpt_more(): string
{
	return (string) fs_config('excerpt_more');
}
add_filter('excerpt_more', 'fs_excerpt_more');

/**
 * Add custom colors and sizes
 */
add_filter('wp_theme_json_data_default', function ($theme_json) {
	$data = $theme_json->get_data();

	$data['settings']['color']['palette'] = fs_config('theme_colors');
	$data['settings']['color']['gradients'] = fs_config('theme_gradients');
	$data['settings']['typography']['fontSizes'] = fs_config('theme_font_sizes');
	// $data['settings']['spacing']['spacingSizes'] = [];
	// $data['settings']['shadow']['presets'] = [];

	return new WP_Theme_JSON_Data($data, 'default');
});
