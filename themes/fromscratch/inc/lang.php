<?php

defined('ABSPATH') || exit;

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
 *
 * @param string $key Translation key (e.g. "MENU_MAIN_MENU").
 * @param array<string> $replace Optional associative array to replace %KEY% in the string.
 * @return string Translated string or key if not found.
 */
function fs_t(string $key, array $replace = []): string
{
    $lang = fs_get_lang();

    $translations = fs_load_translations($lang);
    $fallback     = fs_load_translations('en');

    $text = $key;

    if (isset($translations[$key])) {
        $text = $translations[$key];
    } else if (isset($fallback[$key])) {
        $text = $fallback[$key];
    }

    foreach ($replace as $key => $value) {
        $text = str_replace('%' . $key . '%', $value, $text);
    }

    return $text;
}
