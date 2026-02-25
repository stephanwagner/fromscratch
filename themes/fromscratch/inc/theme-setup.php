<?php

/**
 * Menus
 */
function fs_menus()
{
	global $fs_config;

	add_theme_support('menus');
	register_nav_menus($fs_config['menus']);
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
 * Remove blogs
 */
function fs_remove_blogs()
{
	add_action('admin_menu', function () {
		remove_menu_page('edit.php');
	});
}

if (!empty($fs_config['disable_blogs'])) {
	fs_remove_blogs();
}

/**
 * Excerpt length
 */
function fs_excerpt_length()
{
	global $fs_config;
	return $fs_config['excerpt_length'];
}
add_filter('excerpt_length', 'fs_excerpt_length');

/**
 * Excerpt more
 */
function fs_excerpt_more()
{
	global $fs_config;
	return $fs_config['excerpt_more'];
}
add_filter('excerpt_more', 'fs_excerpt_more');

/**
 * Disable comments
 */
add_filter('comments_open', '__return_false');
add_filter('pings_open', '__return_false');

add_action('after_setup_theme', function () {
	foreach (get_post_types([], 'names') as $post_type) {
		remove_post_type_support($post_type, 'comments');
		remove_post_type_support($post_type, 'trackbacks');
	}
});

/**
 * Add custom colors and sizes
 */
add_filter('wp_theme_json_data_default', function ($theme_json) {
	global $fs_config;

	$data = $theme_json->get_data();

	$data['settings']['color']['palette'] = $fs_config['theme_colors'];
	$data['settings']['color']['gradients'] = $fs_config['theme_gradients'];
	$data['settings']['typography']['fontSizes'] = $fs_config['theme_font_sizes'];
	// $data['settings']['spacing']['spacingSizes'] = [];
	// $data['settings']['shadow']['presets'] = [];

	return new WP_Theme_JSON_Data($data, 'default');
});
