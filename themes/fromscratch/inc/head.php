<?php

defined('ABSPATH') || exit;

/**
 * Enable theme support for title-tag (document title).
 *
 * @return void
 */
function fs_add_title_tag(): void
{
	add_theme_support('title-tag');
}
add_action('after_setup_theme', 'fs_add_title_tag');

/**
 * Output manifest link in head.
 *
 * @return void
 */
function fs_add_manifest(): void
{
	echo '<link rel="manifest" href="' . get_template_directory_uri() . '/manifest.json">' . "\n";
}
add_action('wp_head', 'fs_add_manifest');

/**
 * Output meta charset and config meta tags in head.
 *
 * @return void
 */
function fs_meta_tags(): void
{
	echo '<meta charset="utf-8">' . "\n";
	foreach (fs_config('meta') as $name => $content) {
		echo '<meta name="' . $name . '" content="' . $content . '">' . "\n";
	}
}
add_action('wp_head', 'fs_meta_tags');
