<?php

/**
 * Send cache headers so browsers/proxies revalidate every time.
 * No stale content: after you update a page or assets, visitors always get the new version.
 * Site Health will see cache-related headers; repeat visits can still get 304 Not Modified.
 */

defined('ABSPATH') || exit;

add_action('send_headers', function () {
	if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
		return;
	}

	// Revalidate every time — never serve an old page from cache
	header('Cache-Control: public, max-age=0, must-revalidate');
}, 0);
