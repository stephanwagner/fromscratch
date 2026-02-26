<?php

defined('ABSPATH') || exit;

/**
 * Asset version for static assets (logo, etc.). Bump in Theme settings → General when you change files.
 *
 * @return string Version string for ?ver= query arg (default '1').
 */
function fs_asset_version(): string
{
	$v = get_option('fromscratch_asset_version', '1');
	return $v !== '' ? (string) $v : '1';
}

/**
 * URL for a static theme asset with cache-busting version (Theme settings → General).
 * Escaped for HTML attributes; use directly in src, href, etc.
 *
 * @param string $path Path relative to theme root, e.g. '/img/logo.png' or '/files/guide.pdf'.
 * @return string Escaped URL safe for HTML output.
 */
function fs_asset_url(string $path): string
{
	$path = ltrim($path, '/');
	$url = get_template_directory_uri() . '/' . $path . '?ver=' . fs_asset_version();
	return esc_url($url);
}

/**
 * Short name for asset URL. Only defined if not already provided (e.g. by a plugin).
 *
 * @param string $path Path relative to theme root.
 * @return string Escaped URL safe for HTML output.
 */
if (!function_exists('asset_url')) {
	function asset_url(string $path): string
	{
		return fs_asset_url($path);
	}
}

/**
 * Get the hash for assets
 */
function fs_asset_hash($file)
{
	// Return time when debugging
	if (fs_is_debug()) {
		return time();
	}

	return filemtime(get_template_directory() . $file);
}

/**
 * Stylesheets
 */
function fs_styles()
{
	$min = fs_is_debug() ? '' : '.min';

	$file = '/css/main' . $min . '.css';

	wp_enqueue_style(
		'main-styles',
		get_template_directory_uri() . $file,
		[],
		fs_asset_hash($file),
	);
}
add_action('wp_enqueue_scripts', 'fs_styles');

/**
 * Admin stylesheets
 */
function fs_admin_styles()
{
	$min = fs_is_debug() ? '' : '.min';

	$file = '/css/admin' . $min . '.css';
	wp_enqueue_style(
		'main-admin-styles',
		get_template_directory_uri() . $file,
		[],
		fs_asset_hash($file),
	);
}
add_action('admin_enqueue_scripts', 'fs_admin_styles');

/**
 * Scripts
 */
function fs_scripts()
{
	$min = fs_is_debug() ? '' : '.min';

	$file = '/js/main' . $min . '.js';

	wp_enqueue_script(
		'main-scripts',
		get_template_directory_uri() . $file,
		[],
		fs_asset_hash($file),
		true
	);
}
add_action('wp_enqueue_scripts', 'fs_scripts');

/**
 * Admin scripts
 */
function fs_admin_scripts()
{
	$min = fs_is_debug() ? '' : '.min';

	$file = '/js/admin' . $min . '.js';

	wp_enqueue_script(
		'main-admin-scripts',
		get_template_directory_uri() . $file,
		[],
		fs_asset_hash($file),
		true
	);
}
add_action('admin_enqueue_scripts', 'fs_admin_scripts');

/**
 * Block editor scripts
 */
function fs_editor_scripts()
{
	$min = fs_is_debug() ? '' : '.min';

	$file = '/js/editor' . $min . '.js';

	wp_enqueue_script(
		'fromscratch-editor',
		get_template_directory_uri() . $file,
		[
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-i18n',
		],
		fs_asset_hash($file),
		true
	);
}
add_action('enqueue_block_editor_assets', 'fs_editor_scripts');
