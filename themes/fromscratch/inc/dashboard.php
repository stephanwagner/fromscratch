<?php
// TODO

// Security messages

// Analytics
//  - Within WP / Own URL
//  - Disable in settings

/**
 * Remove WordPress Events and News
 */
add_action('wp_dashboard_setup', function () {
	remove_meta_box('dashboard_primary', 'dashboard', 'side');
}, 999);