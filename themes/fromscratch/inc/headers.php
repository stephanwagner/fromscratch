<?php

/**
 * Send cache headers so browsers/proxies revalidate every time.
 * No stale content: after you update a page or assets, visitors always get the new version.
 * Site Health will see cache-related headers; repeat visits can still get 304 Not Modified.
 */

defined('ABSPATH') || exit;

add_action('send_headers', function () {
	// Skip admin, AJAX, REST
	if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
		return;
	}

	// Apply headers from config
	$headers = fs_config('headers');
	if (is_array($headers)) {
		foreach ($headers as $name => $value) {
			if ($value !== '' && $value !== null) {
				header(sprintf('%s: %s', $name, $value));
			}
		}
	}

	// Never cache logged-in users
	if (is_user_logged_in()) {
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
	}
}, 0);
