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
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, sans-serif; margin: 0; padding: 2rem; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0f0f1; }
		.box { background: #fff; padding: 2rem; max-width: 480px; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,.13); border: 1px solid #c3c4c7; text-align: center; }
		h1 { margin: 0 0 1rem 0; font-size: 1.75rem; font-weight: 600; }
		p { margin: 0; color: #50575e; line-height: 1.6; }
		.icon { margin-bottom: 1rem; color: #8c8f94; }
	</style>
</head>
<body>
	<div class="box">
		<?php
		$maintenance_icon_allowed = [
			'svg'  => ['width' => [], 'height' => [], 'viewbox' => [], 'fill' => [], 'stroke' => [], 'style' => [], 'aria-hidden' => []],
			'path' => ['d' => []],
		];
		?>
		<p class="icon" aria-hidden="true"><?= wp_kses(fs_maintenance_icon(), $maintenance_icon_allowed) ?></p>
		<h1><?= esc_html($title) ?></h1>
		<p><?= esc_html($description) ?></p>
	</div>
</body>
</html>
	<?php
	exit;
}

/**
 * Return inline SVG wrench/maintenance icon.
 */
function fs_maintenance_icon(): string
{
	return '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>';
}

add_action('init', 'fs_maintenance_gate', 0);
