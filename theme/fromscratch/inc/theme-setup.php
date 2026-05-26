<?php

defined('ABSPATH') || exit;

/**
 * Post types used by theme features: post, page, and CPTs from config/content-types/.
 * Used by SEO panel, post expirator, duplicate row action, and similar.
 *
 * @return string[]
 */
function fs_theme_post_types(): array
{
	$types = ['page'];
	if (function_exists('fs_content_type_enabled') && fs_content_type_enabled('post')) {
		$types[] = 'post';
	}
	$cpts = fs_config_cpt('all');
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
 * Theme setting: featured image fallback (Settings → Theme → General).
 * When a post has no `_thumbnail_id`, expose the fallback attachment so `has_post_thumbnail()` and `the_post_thumbnail()` work.
 *
 * @param mixed $thumbnail_id Existing attachment ID or falsey.
 * @param \WP_Post|int|null $post Post object or ID.
 * @return int|string|false
 */
function fs_post_thumbnail_id_fallback($thumbnail_id, $post)
{
	if ((int) $thumbnail_id > 0) {
		return $thumbnail_id;
	}
	$post_obj = $post instanceof \WP_Post ? $post : (is_numeric($post) ? get_post((int) $post) : null);
	if (!$post_obj instanceof \WP_Post || !post_type_supports($post_obj->post_type, 'thumbnail')) {
		return $thumbnail_id;
	}
	$fallback = (int) get_option('fromscratch_feature_image_fallback', 0);
	if ($fallback <= 0 || !wp_attachment_is_image($fallback)) {
		return $thumbnail_id;
	}

	return $fallback;
}
add_filter('post_thumbnail_id', 'fs_post_thumbnail_id_fallback', 10, 2);

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
 * Remove Posts admin menu when `config/content-types/post.php` has `enabled` => false.
 */
function fs_disable_builtin_posts_admin(): void
{
	add_action('admin_menu', function (): void {
		remove_menu_page('edit.php');
	});

	add_action('admin_init', function (): void {
		global $pagenow;

		if ($pagenow === 'edit.php') {
			$post_type = isset($_GET['post_type'])
				? sanitize_key((string) wp_unslash($_GET['post_type']))
				: 'post';
			if ($post_type === 'post') {
				wp_safe_redirect(admin_url());
				exit;
			}

			return;
		}

		if ($pagenow === 'post-new.php') {
			$post_type = isset($_GET['post_type'])
				? sanitize_key((string) wp_unslash($_GET['post_type']))
				: 'post';
			if ($post_type === 'post') {
				wp_safe_redirect(admin_url());
				exit;
			}

			return;
		}

		if ($pagenow === 'post.php') {
			$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
			if ($post_id > 0 && get_post_type($post_id) === 'post') {
				wp_safe_redirect(admin_url());
				exit;
			}
		}
	});
}

add_action('after_setup_theme', function (): void {
	if (function_exists('fs_content_type_enabled') && !fs_content_type_enabled('post')) {
		fs_disable_builtin_posts_admin();
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
