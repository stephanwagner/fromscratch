<?php

defined('ABSPATH') || exit;

/**
 * Maintenance mode: when enabled, the whole frontend returns 503 with an editable title and description.
 * Logged-in administrators and editors bypass. Options in Settings → Developer → Security.
 */

/**
 * If maintenance mode is on, block frontend with 503 and show maintenance page. Admins/editors bypass.
 */
function fs_maintenance_gate(): void
{
	if (get_option('fromscratch_maintenance_mode') !== '1') {
		return;
	}
	if (is_admin()) {
		return;
	}
	if (defined('DOING_CRON') && DOING_CRON) {
		return;
	}
	if (defined('WP_CLI') && constant('WP_CLI')) {
		return;
	}
	// Logged-in admins and editors bypass
	if (is_user_logged_in()) {
		$user = wp_get_current_user();
		if ($user->exists() && (user_can($user, 'edit_posts') || user_can($user, 'manage_options'))) {
			return;
		}
	}
	fs_maintenance_show_page();
}

/**
 * Output maintenance page with 503 status and exit.
 */
function fs_maintenance_show_page(): void
{
	// Load text domain for maintenance page
	if (!is_textdomain_loaded('fromscratch')) {
		load_theme_textdomain('fromscratch');
		if (!is_textdomain_loaded('fromscratch')) {
			$mofile = get_template_directory() . '/languages/fromscratch-' . determine_locale() . '.mo';
			if (file_exists($mofile)) {
				load_textdomain('fromscratch', $mofile);
			}
		}
	}

	$title = get_option('fromscratch_maintenance_title', '');
	if ($title === '') {
		$title = __('Maintenance', 'fromscratch');
	}
	$description = get_option('fromscratch_maintenance_description', '');
	if ($description === '') {
		$description = __('We are currently performing scheduled maintenance. Please check back shortly.', 'fromscratch');
	}
	$body = '<div class="notice">' . esc_html($description) . '</div>';
	fs_block_page($title, $body, ['status' => 503]);
}

/**
 * Initialize maintenance gate.
 */
add_action('init', 'fs_maintenance_gate', 0);
