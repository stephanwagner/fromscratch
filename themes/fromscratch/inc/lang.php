<?php

/**
 * Get the current language
 */
function fs_get_lang(): string
{
    $locale = function_exists('determine_locale')
        ? determine_locale()
        : get_locale();

    return substr($locale, 0, 2);
}

/**
 * Load the translations for a given language
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
 * Translate a given key
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
