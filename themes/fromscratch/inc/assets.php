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
 * 
 * @param string $file Path relative to theme root.
 * @return int File modification time.
 */
function fs_asset_hash(string $file): int
{
	if (fs_is_debug()) {
		return time();
	}

	return filemtime(get_template_directory() . $file);
}

/**
 * Enqueue front-end stylesheets (main.css).
 *
 * @return void
 */
function fs_styles(): void
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
 * Enqueue admin stylesheets (admin.css).
 * Skipped on block editor screens (post.php, post-new.php) so the style is not
 * added to the editor iframe (WordPress expects iframe styles via block.json or enqueue_block_assets).
 *
 * @param string $hook_suffix Current admin page hook from admin_enqueue_scripts.
 * @return void
 */
function fs_admin_styles(string $hook_suffix): void
{
	$block_editor_hooks = ['post.php', 'post-new.php'];
	if (in_array($hook_suffix, $block_editor_hooks, true)) {
		return;
	}

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
 * Enqueue front-end scripts (main.js).
 *
 * @return void
 */
function fs_scripts(): void
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
 * Enqueue admin scripts (admin.js).
 *
 * @return void
 */
function fs_admin_scripts(): void
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
 * Enqueue block editor scripts (editor.js).
 *
 * @return void
 */
function fs_editor_scripts(): void
{
	$min = fs_is_debug() ? '' : '.min';

	$file = '/js/editor' . $min . '.js';

	wp_enqueue_script(
		'fromscratch-editor',
		get_template_directory_uri() . $file,
		[
			'wp-plugins',
			'wp-edit-post',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-data',
			'wp-core-data',
			'wp-i18n',
		],
		fs_asset_hash($file),
		true
	);
}
add_action('enqueue_block_editor_assets', 'fs_editor_scripts');
