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
	// This runs at init priority 0, before the theme loads its text domain (init 1). Load it now so __() translates.
	if (! is_textdomain_loaded('fromscratch')) {
		load_theme_textdomain('fromscratch');
		if (! is_textdomain_loaded('fromscratch')) {
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
	header('HTTP/1.1 503 Service Unavailable');
	header('Status: 503 Service Unavailable');
	header('Retry-After: 300');
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-cache, no-store, must-revalidate');
?>
	<!DOCTYPE html>
	<html lang="<?= esc_attr(get_bloginfo('language') ?: 'en') ?>">

	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?= esc_html($title) ?></title>
		<style>
			body {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, sans-serif;
				margin: 0;
				padding: 0 16px;
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				background: #f0f0f1;
				color: #3c434a;
			}

			.box {
				background: #fff;
				padding: 32px 24px;
				max-width: 420px;
				width: 100%;
				box-shadow: 0 1px 3px rgba(0, 0, 0, .13);
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				text-align: center;
				font-size: 16px;
				line-height: 1.4;
			}

			@media (max-width: 600px) {
				.box {
					padding: 24px 16px;
				}
			}

			h1 {
				margin: 0 0 24px;
				font-size: 22px;
				line-height: 1.2;
				font-weight: 600;
				color: #1d2327;
			}

			@media (max-width: 600px) {
				h1 {
					margin-bottom: 16px;
				}
			}

			.notice {
				margin: 0 0 24px;
				color: #50575e;
				padding: 0 0 8px;
			}

			@media (max-width: 600px) {
				.notice {
					padding-bottom: 0;
				}
			}

			.notice:last-child {
				margin-bottom: 0;
			}
		</style>
	</head>

	<body>
		<div class="box">
			<h1><?= esc_html($title) ?></h1>
			<div class="notice"><?= esc_html($description) ?></div>
		</div>
	</body>

	</html>
<?php
	exit;
}

/**
 * Initialize maintenance gate.
 */
add_action('init', 'fs_maintenance_gate', 0);
