<?php

defined('ABSPATH') || exit;

/**
 * Default values for Developer → Features. Used by the installer and when a key was never saved.
 * Single source of truth for “after setup” / new-install behavior.
 *
 * @return array<string, int> Option key => 1 (on) or 0 (off).
 */
function fs_theme_feature_defaults(): array
{
	return [
		'enable_blogs'             => 1,
		'enable_remove_post_tags'   => 1,
		'enable_svg'               => 1,
		'enable_duplicate_post'    => 1,
		'enable_seo'               => 1,
		'enable_post_expirator'    => 1,
	];
}

/**
 * Keys that default to off when the option was never saved (backward compat for options added later).
 *
 * @return string[]
 */
function fs_theme_feature_default_off_when_missing(): array
{
	return ['enable_remove_post_tags'];
}

/**
 * Check whether a theme feature is enabled (Settings → Developer → Features).
 * Uses saved option when present; otherwise central defaults or “off when missing” for backward compat.
 *
 * @param string $feature One of: blogs, remove_post_tags, svg, duplicate_post, seo, post_expirator.
 * @return bool
 */
function fs_theme_feature_enabled(string $feature): bool
{
	static $options = null;

	if ($options === null) {
		$options = get_option('fromscratch_features', []);
		if (!is_array($options)) {
			$options = [];
		}
	}

	$map = [
		'blogs'             => 'enable_blogs',
		'remove_post_tags'  => 'enable_remove_post_tags',
		'svg'               => 'enable_svg',
		'duplicate_post'    => 'enable_duplicate_post',
		'seo'               => 'enable_seo',
		'post_expirator'    => 'enable_post_expirator',
	];

	$key = $map[$feature] ?? '';
	if ($key === '') {
		return false;
	}

	if (!array_key_exists($key, $options)) {
		$default_off = fs_theme_feature_default_off_when_missing();
		if (in_array($key, $default_off, true)) {
			return false;
		}
		$defaults = fs_theme_feature_defaults();
		return (int) ($defaults[$key] ?? 1) === 1;
	}

	return (int) $options[$key] === 1;
}
