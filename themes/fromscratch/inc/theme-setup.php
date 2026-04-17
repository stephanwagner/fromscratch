<?php

defined('ABSPATH') || exit;

/**
 * Post types used by theme features: post, page, and CPTs from config/custom-post-types.php.
 * Used by SEO panel, post expirator, duplicate row action, and similar.
 *
 * @return string[]
 */
function fs_theme_post_types(): array
{
	$types = ['post', 'page'];
	$cpts = fs_config_cpt('cpts');
	if (is_array($cpts) && $cpts !== []) {
		$types = array_merge($types, array_keys($cpts));
	}
	return array_unique($types);
}

/**
 * Register theme menus from config.
 * Runs on init so the text domain is loaded before translating labels (WordPress 6.7+).
 *
 * @return void
 */
function fs_menus(): void
{
	add_theme_support('menus');
	$menus = fs_config('menus');
	if (!is_array($menus)) {
		return;
	}
	$translated = [];
	foreach ($menus as $slug => $label) {
		$translated[$slug] = __($label, 'fromscratch');
	}
	register_nav_menus($translated);
}
add_action('init', 'fs_menus', 10);

/**
 * Unregister post_tag for posts when Settings → Developer → Features: Blogs is on and "Disable tags" is on.
 */
add_action('init', function (): void {
	if (!function_exists('fs_theme_feature_enabled')) {
		return;
	}
	if (!fs_theme_feature_enabled('blogs') || !fs_theme_feature_enabled('remove_post_tags')) {
		return;
	}
	unregister_taxonomy_for_object_type('post_tag', 'post');
}, 11);

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
 * Support post thumbnails (featured images) and register extra image sizes from config (options {slug}_size_w, {slug}_size_h on Settings → Media).
 *
 * @return void
 */
function fs_add_post_thumbnails(): void
{
	add_theme_support('post-thumbnails');

	$extra = fs_config('image_sizes_extra');
	if (!is_array($extra)) {
		return;
	}
	foreach ($extra as $size) {
		$slug = isset($size['slug']) ? $size['slug'] : '';
		if ($slug === '') {
			continue;
		}
		$default_w = isset($size['width']) ? (int) $size['width'] : 0;
		$default_h = isset($size['height']) ? (int) $size['height'] : 0;
		$w = (int) get_option($slug . '_size_w', $default_w);
		$h = (int) get_option($slug . '_size_h', $default_h);
		if ($w > 0) {
			add_image_size($slug, $w, $h, false);
		}
	}
}
add_action('after_setup_theme', 'fs_add_post_thumbnails');

/**
 * Setup image sizes
 * 
 * - Disable image threshold if set so in config theme.php
 * - Remove medium_large and the 1536x1536, 2048x2048 image sizes
 */
if (fs_config('image_threshold') === false) {
	add_filter('big_image_size_threshold', '__return_false');
}
add_filter('image_size_names_choose', function (array $sizes): array {
	unset($sizes['medium_large']);
	unset($sizes['1536x1536']);
	unset($sizes['2048x2048']);
	return $sizes;
});
add_filter('intermediate_image_sizes_advanced', function ($sizes) {
	unset($sizes['medium_large']);
    unset($sizes['1536x1536']);
    unset($sizes['2048x2048']);
    return $sizes;
});

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
 * Filter excerpt length from Theme settings (Settings → Theme → General).
 *
 * @return int Length used when trimming excerpts.
 */
function fs_excerpt_length(): int
{
	$opt = get_option('fromscratch_excerpt_length', '');
	if ($opt !== '') {
		return (int) $opt;
	}
	return 60;
}
add_filter('excerpt_length', 'fs_excerpt_length');

/**
 * Filter excerpt "more" string from Theme settings (Settings → Theme → General).
 *
 * @return string Text shown after truncated excerpt (e.g. "…").
 */
function fs_excerpt_more(): string
{
	$opt = get_option('fromscratch_excerpt_more');
	if ($opt !== false) {
		return (string) $opt;
	}
	return '…';
}
add_filter('excerpt_more', 'fs_excerpt_more');

/**
 * Add custom colors and sizes
 */
add_filter('wp_theme_json_data_default', function ($theme_json) {
	$data = $theme_json->get_data();

	$data['settings']['color']['palette'] = fs_config('colors');
	$data['settings']['color']['gradients'] = fs_config('gradients');
	$data['settings']['typography']['fontSizes'] = fs_config('font_sizes');
	// $data['settings']['spacing']['spacingSizes'] = [];
	// $data['settings']['shadow']['presets'] = [];

	return new WP_Theme_JSON_Data($data, 'default');
});
