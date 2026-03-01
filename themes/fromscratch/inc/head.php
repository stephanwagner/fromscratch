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
 * Favicon fallback when no site icon is set in Customizer.
 * Uses theme assets/img/favicon.png and favicon-192.png (after wp_site_icon at priority 99).
 *
 * @return void
 */
function fs_favicon_fallback(): void
{
	if (has_site_icon()) {
		return;
	}
	$dir = get_template_directory() . '/assets/img';
	if (file_exists($dir . '/favicon.png')) {
		echo '<link rel="icon" href="' . esc_url(fs_asset_url('/img/favicon.png')) . '" sizes="any">' . "\n";
	}
	if (file_exists($dir . '/favicon-192.png')) {
		echo '<link rel="icon" href="' . esc_url(fs_asset_url('/img/favicon-192.png')) . '" sizes="192x192">' . "\n";
	}
}
add_action('wp_head', 'fs_favicon_fallback', 100);

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
