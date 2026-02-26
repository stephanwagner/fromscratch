<?php

defined('ABSPATH') || exit;

/**
 * Menus
 */
function fs_menus()
{
	add_theme_support('menus');
	register_nav_menus(fs_config('menus'));
}
add_action('after_setup_theme', 'fs_menus');

/**
 * Support alignwide and alignfull
 */
function fs_add_alignwide()
{
	add_theme_support('align-wide');
}
add_action('after_setup_theme', 'fs_add_alignwide');

/**
 * Support post thumbnails
 */
function fs_add_post_thumbnails()
{
	add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'fs_add_post_thumbnails');

/**
 * Remove blogs menu and block direct access to post screens
 */
function fs_remove_blogs()
{
	add_action('admin_menu', function () {
		remove_menu_page('edit.php');
	});

	add_action('admin_init', function () {
		if (empty(fs_config('disable_blogs'))) {
			return;
		}
		global $pagenow;
		$blocked = ['edit.php', 'post.php', 'post-new.php'];
		if (in_array($pagenow, $blocked, true)) {
			wp_safe_redirect(admin_url());
			exit;
		}
	});
}

if (!empty(fs_config('disable_blogs'))) {
	fs_remove_blogs();
}

/**
 * Excerpt length
 */
function fs_excerpt_length()
{
	return fs_config('excerpt_length');
}
add_filter('excerpt_length', 'fs_excerpt_length');

/**
 * Excerpt more
 */
function fs_excerpt_more()
{
	return fs_config('excerpt_more');
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
