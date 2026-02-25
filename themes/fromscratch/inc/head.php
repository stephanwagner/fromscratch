<?php

/**
 * Clean up
 */
function fs_clean_up_head()
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

function fs_clean_up_styles()
{
	// Classic styles
	wp_dequeue_style('classic-theme-styles');
}
add_action('wp_enqueue_scripts', 'fs_clean_up_styles', 100);

/**
 * Add title tag
 */
function fs_add_title_tag()
{
	add_theme_support('title-tag');
}
add_action('after_setup_theme', 'fs_add_title_tag');

/**
 * Add meta tags
 */
function fs_meta_tags()
{
	echo '<meta charset="utf-8">' . "\n";
	foreach (fs_config('meta') as $name => $content) {
		echo '<meta name="' . $name . '" content="' . $content . '">' . "\n";
	}
}
add_action('wp_head', 'fs_meta_tags');

/**
 * Add manifest
 */
function fs_add_manifest()
{
	echo '<link rel="manifest" href="' . get_template_directory_uri() . '/manifest.json">';
}
add_action('wp_head', 'fs_add_manifest');
