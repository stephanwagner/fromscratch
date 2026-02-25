<?php

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
