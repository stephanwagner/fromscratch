<?php

defined('ABSPATH') || exit;

/**
 * Remove generator, RSD, shortlink, emoji and REST discovery from head.
 *
 * @return void
 */
function fs_clean_up_head(): void
{
	// Head
	remove_action('wp_head', 'wp_generator');
	remove_action('wp_head', 'wlwmanifest_link');
	remove_action('wp_head', 'rsd_link');
	remove_action('wp_head', 'wp_shortlink_wp_head');

	// Emoji
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('admin_print_styles', 'print_emoji_styles');

	// Rest discovery
	remove_action('wp_head', 'rest_output_link_wp_head');
}
add_action('init', 'fs_clean_up_head');

/**
 * Dequeue classic theme styles (block wrapper styles) when not needed.
 *
 * @return void
 */
function fs_clean_up_styles(): void
{
	wp_dequeue_style('classic-theme-styles');
}
add_action('wp_enqueue_scripts', 'fs_clean_up_styles', 999);

/**
 * Disable and remove comments everywhere
 */
add_filter('comments_open', '__return_false');
add_filter('pings_open', '__return_false');

add_action('admin_menu', function () {
	remove_menu_page('edit-comments.php');
	remove_submenu_page('options-general.php', 'options-discussion.php');
}, 999);

add_action('admin_init', function () {
	global $pagenow;
	$blocked = ['edit-comments.php', 'options-discussion.php'];
	if (in_array($pagenow, $blocked, true)) {
		wp_safe_redirect(admin_url());
		exit;
	}
});

add_action('admin_init', function () {
	foreach (get_post_types([], 'names') as $post_type) {
		remove_post_type_support($post_type, 'comments');
		remove_post_type_support($post_type, 'trackbacks');
	}
});

add_action('wp_before_admin_bar_render', function () {
	global $wp_admin_bar;
	$wp_admin_bar->remove_menu('comments');
});

add_action('template_redirect', function () {
	if (is_comment_feed()) {
		wp_redirect(home_url(), 301);
		exit;
	}
}, 1);
