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
		'enable_blogs'              => 1,
		'enable_remove_post_tags'   => 1,
		'enable_svg'                => 1,
		'enable_duplicate_post'     => 1,
		'enable_seo'                => 1,
		'enable_post_expirator'     => 1,
		'enable_languages'          => 0,
		'enable_blocked_ips'        => 1,
		'enable_webp'               => 1,
		'enable_webp_convert_original' => 0,
		'enable_media_folders'      => 1,
	];
}

/**
 * Keys that default to off when the option was never saved (backward compat for options added later).
 *
 * @return string[]
 */
function fs_theme_feature_default_off_when_missing(): array
{
	return ['enable_remove_post_tags', 'enable_languages', 'enable_webp', 'enable_webp_convert_original'];
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
		'blocked_ips'       => 'enable_blocked_ips',
		'webp'              => 'enable_webp',
		'webp_convert_original' => 'enable_webp_convert_original',
		'media_folders'     => 'enable_media_folders',
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
 * When Languages feature is off: returns config from theme-content.php (keys: id, name, nameNative).
 * When on: returns the list from Developer → Languages (keys: id, nameEnglish, nameOriginalLanguage).
 *
 * @return list<array{id: string, name?: string, nameNative?: string, nameEnglish?: string, nameOriginalLanguage?: string}>
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
 * Get a language label from a language array. Supports config shape (label) and option shape (nameEnglish, nameOriginalLanguage).
 *
 * @param array<string, string> $lang Language item from fs_get_content_languages().
 * @param string $type 'native' for native/original name, 'name' for admin name; both use label when present.
 * @return string
 */
function fs_content_language_label(array $lang, string $type = 'native'): string
{
	$v = $lang['label'] ?? $lang['nameNative'] ?? $lang['nameOriginalLanguage'] ?? $lang['name'] ?? $lang['nameEnglish'] ?? '';
	return $v !== '' ? (string) $v : (string) ($lang['id'] ?? '');
}

/**
 * Get a content option value with language fallback. Option IDs stay the same (base id without _en/_de).
 * Resolves: current language key first, then default language key, then key without suffix (non-translated or legacy).
 *
 * @param string $option_id Full option name (e.g. FS_THEME_CONTENT_OPTION_PREFIX . 'footer_footer4_text'). Do not include _en/_de.
 * @param mixed  $default    Value to return when no option is set.
 * @return mixed
 */
function fs_content_option(string $option_id, $default = '')
{
	$sentinel = new \stdClass();
	$current_lang = function_exists('fs_language_current_request_lang') ? fs_language_current_request_lang() : '';
	$default_lang = fs_get_default_language();
	$candidates = [];
	if ($current_lang !== '') {
		$candidates[] = $option_id . '_' . $current_lang;
	}
	if ($default_lang !== '' && $default_lang !== $current_lang) {
		$candidates[] = $option_id . '_' . $default_lang;
	}
	$candidates[] = $option_id;
	foreach ($candidates as $key) {
		$val = get_option($key, $sentinel);
		if ($val !== $sentinel) {
			return $val;
		}
	}
	return $default;
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

/**
 * Behavior when the language toggler has no translation for the current page for a given language.
 * One of: 'hide' (do not show), 'disabled' (show but link disabled), 'home' (link to language homepage or /).
 *
 * @return string
 */
function fs_language_no_translation_behavior(): string
{
	if (!fs_theme_feature_enabled('languages')) {
		return 'disabled';
	}
	$data = get_option('fs_theme_languages', ['no_translation' => 'disabled']);
	$v = isset($data['no_translation']) ? $data['no_translation'] : 'disabled';
	return in_array($v, ['hide', 'disabled', 'home'], true) ? $v : 'disabled';
}
