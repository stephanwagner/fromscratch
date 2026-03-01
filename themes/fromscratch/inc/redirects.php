<?php

defined('ABSPATH') || exit;

/**
 * Redirect manager: run redirects via WordPress when method is "wordpress".
 * When method is "htaccess", Apache handles redirects from .htaccess.
 */
add_action('template_redirect', function () {
	if (get_option('fs_redirect_method') !== 'wordpress') {
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
