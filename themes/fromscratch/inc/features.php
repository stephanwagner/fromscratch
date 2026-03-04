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

/**
 * Whether language prefixes are used in URLs at all (e.g. /de/, /fr/).
 * When false: no language segment in URLs; when true: URLs can use a language prefix.
 *
 * @return bool
 */
function fs_use_language_url_prefix(): bool
{
	if (!fs_theme_feature_enabled('languages')) {
		return false;
	}
	$data = get_option('fs_theme_languages', ['list' => [], 'default' => '', 'prefix_default' => false, 'use_url_prefix' => true]);
	return isset($data['use_url_prefix']) ? (bool) $data['use_url_prefix'] : true;
}

/**
 * Whether the default language should have a URL prefix (e.g. /en/).
 * When false: default language has no prefix; when true: all languages use a prefix.
 * Only applies when fs_use_language_url_prefix() is true.
 *
 * @return bool
 */
function fs_prefix_default_language(): bool
{
	if (!fs_use_language_url_prefix()) {
		return false;
	}
	$data = get_option('fs_theme_languages', ['list' => [], 'default' => '', 'prefix_default' => false]);
	return !empty($data['prefix_default']);
}
