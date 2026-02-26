<?php

defined('ABSPATH') || exit;

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
