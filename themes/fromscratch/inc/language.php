<?php

defined('ABSPATH') || exit;

/**
 * Load the translations for the theme.
 */
add_action('after_setup_theme', function () {
	load_theme_textdomain(
		'fromscratch'
	);

	// Fallback
	if (!is_textdomain_loaded('fromscratch')) {

		$mofile = get_template_directory() . '/languages/fromscratch-' . determine_locale() . '.mo';

		if (file_exists($mofile)) {
			load_textdomain('fromscratch', $mofile);
		}
	}
});


/**
 * Get the current language code (first two chars of locale, e.g. "en", "de").
 *
 * @return string Two-letter language code.
 */
function fs_get_lang(): string
{
	$locale = function_exists('determine_locale')
		? determine_locale()
		: get_locale();

	return substr($locale, 0, 2);
}

/**
 * Load the translations for a given language (cached per request).
 * Used by fs_t() for theme areas still using the PHP lang files (e.g. settings).
 *
 * @param string $lang Two-letter language code (e.g. "en", "de").
 * @return array<string, string> Key => translated string.
 */
function fs_load_translations(string $lang): array
{
	static $cache = [];

	if (isset($cache[$lang])) {
		return $cache[$lang];
	}

	$base = get_template_directory() . '/lang/';
	$file = $base . $lang . '.php';

	if (file_exists($file)) {
		$cache[$lang] = require $file;
	} else {
		$cache[$lang] = [];
	}

	return $cache[$lang];
}

/**
 * Translate a key using theme lang files; supports %KEY% placeholders in replacements.
 * Use __() / esc_html__() etc. for new code; this remains for backward compatibility.
 *
 * @param string $key Translation key (e.g. "MENU_MAIN_MENU").
 * @param array<string, string> $replace Optional associative array to replace %KEY% in the string.
 * @return string Translated string or key if not found.
 */
function fs_t(string $key, array $replace = []): string
{
	$lang = fs_get_lang();
	$translations = fs_load_translations($lang);
	$fallback = fs_load_translations('en');

	$text = $key;
	if (isset($translations[$key])) {
		$text = $translations[$key];
	} elseif (isset($fallback[$key])) {
		$text = $fallback[$key];
	}

	foreach ($replace as $k => $value) {
		$text = str_replace('%' . $k . '%', $value, $text);
	}

	return $key; // $text;
}
