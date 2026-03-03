<?php

defined('ABSPATH') || exit;

/**
 * Whether the server is Apache (for redirect method suggestion).
 */
function fs_is_apache(): bool
{
	if (function_exists('apache_get_version')) {
		return true;
	}
	if (function_exists('apache_get_modules')) {
		return true;
	}
	if (!empty($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
		return true;
	}
	if (!empty($_SERVER['APACHE_RUN_DIR']) || !empty($_SERVER['APACHE_PID_FILE'])) {
		return true;
	}
	return false;
}

/**
 * Whether Apache has mod_rewrite loaded (required for .htaccess redirects).
 */
function fs_has_mod_rewrite(): bool
{
	if (!fs_is_apache()) {
		return false;
	}
	if (function_exists('apache_get_modules')) {
		return in_array('mod_rewrite', apache_get_modules(), true);
	}
	return true;
}

/**
 * Whether .htaccess can be written (file writable, or missing and directory writable).
 */
function fs_is_htaccess_writable(): bool
{
	$file = ABSPATH . '.htaccess';
	if (file_exists($file)) {
		return is_writable($file);
	}
	return is_writable(ABSPATH);
}

/**
 * Whether its save to use .htaccess redirects.
 */
function fs_can_use_htaccess_redirects(): bool
{
	return fs_is_apache() && fs_has_mod_rewrite() && fs_is_htaccess_writable();
}

/**
 * Redirect manager: run redirects via WordPress when method is "wordpress".
 * When method is "htaccess", Apache handles redirects from .htaccess.
 */
add_action('template_redirect', function () {
	$method = function_exists('fs_config_redirects') ? fs_config_redirects('method') : get_option('fs_redirect_method', 'wordpress');
	if ($method !== 'wordpress') {
		return;
	}
	$redirects = get_option('fs_redirects', []);
	if (empty($redirects) || !is_array($redirects)) {
		return;
	}
	$path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	$path = trim((string) parse_url($path, PHP_URL_PATH), '/');
	$request = $path === '' ? '/' : '/' . $path;
	if (!isset($redirects[$request])) {
		return;
	}
	$item = $redirects[$request];
	$to = is_array($item) ? ($item['to'] ?? '') : (string) $item;
	$code = is_array($item) ? (int) ($item['code'] ?? 301) : 301;
	if ($to === '') {
		return;
	}
	if (strpos($to, 'http') !== 0) {
		$to = home_url($to);
	}
	wp_redirect($to, $code);
	exit;
}, 1);
