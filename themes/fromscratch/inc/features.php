<?php

defined('ABSPATH') || exit;

/**
 * Check whether a theme feature is enabled (Settings → Theme → General).
 * When the option was never saved, all features are considered enabled.
 *
 * @param string $feature One of: blogs, svg, duplicate_post, seo.
 * @return bool
 */
function fs_theme_feature_enabled(string $feature): bool
{
	$map = [
		'blogs' => 'enable_blogs',
		'svg' => 'enable_svg',
		'duplicate_post' => 'enable_duplicate_post',
		'seo' => 'enable_seo',
	];
	$key = $map[$feature] ?? '';
	if ($key === '') {
		return false;
	}
	$options = get_option('fromscratch_features', []);
	if (!is_array($options) || !array_key_exists($key, $options)) {
		return true;
	}
	return (int) $options[$key] === 1;
}
