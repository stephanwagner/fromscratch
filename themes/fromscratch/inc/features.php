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
		'enable_languages'        => 0,
	];
}

/**
 * Keys that default to off when the option was never saved (backward compat for options added later).
 *
 * @return string[]
 */
function fs_theme_feature_default_off_when_missing(): array
{
	return ['enable_remove_post_tags', 'enable_languages'];
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
		'languages'         => 'enable_languages',
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

/**
 * Get the effective content languages list (for Settings → Theme → Content translatable fields).
 * When Languages feature is off: returns config from theme-content.php. When on: returns the list from Developer → Languages.
 *
 * @return list<array{id: string, nameEnglish: string, nameOriginalLanguage: string}>
 */
function fs_get_content_languages(): array
{
	if (fs_theme_feature_enabled('languages')) {
		$data = get_option('fs_theme_languages', ['list' => [], 'default' => '']);
		$list = isset($data['list']) && is_array($data['list']) ? $data['list'] : [];
		return array_values($list);
	}
	$config = function_exists('fs_config_settings') ? fs_config_settings('languages') : [];
	return is_array($config) ? $config : [];
}

/**
 * Get the default content language id.
 * When Languages feature is off: first language in config. When on: value from Developer → Languages.
 *
 * @return string
 */
function fs_get_default_language(): string
{
	if (fs_theme_feature_enabled('languages')) {
		$data = get_option('fs_theme_languages', ['list' => [], 'default' => '']);
		$default = isset($data['default']) ? (string) $data['default'] : '';
		if ($default !== '') {
			return $default;
		}
		$list = isset($data['list']) && is_array($data['list']) ? $data['list'] : [];
		return (string) ($list[0]['id'] ?? '');
	}
	$config = function_exists('fs_config_settings') ? fs_config_settings('languages') : [];
	if (!is_array($config) || empty($config)) {
		return '';
	}
	return (string) ($config[0]['id'] ?? '');
}
