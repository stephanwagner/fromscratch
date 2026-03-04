<?php

defined('ABSPATH') || exit;

// TODO

// Security messages

// Analytics
//  - Within WP / Own URL
//  - Disable in settings

/**
 * Remove WordPress Events and News, and Activity (includes Recent comments).
 */
add_action('wp_dashboard_setup', function () {
	remove_meta_box('dashboard_primary', 'dashboard', 'side');

	// TODO Maybe allow?
	// remove_meta_box('dashboard_activity', 'dashboard', 'normal');
}, 999);