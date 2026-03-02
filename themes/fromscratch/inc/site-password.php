<?php

defined('ABSPATH') || exit;

/**
 * Site password protection: frontend and admin behind one shared password.
 * Logged-in administrators and editors bypass the gate. Options in Settings → Developer → Security.
 */

/**
 * Run the site password gate: allow if disabled, or user is admin/editor, or valid cookie, or correct POST.
 * Otherwise output password form and exit.
 *
 * @return void
 */
function fs_site_password_gate(): void
{
	if (get_option('fromscratch_site_password_protection') !== '1') {
		return;
	}
	$hash = get_option('fromscratch_site_password_hash', '');
	if ($hash === '') {
		return;
	}
	if (defined('DOING_CRON') && DOING_CRON) {
		return;
	}
	if (defined('WP_CLI') && constant('WP_CLI')) {
		return;
	}
	// Logged-in admins and editors skip the gate
	if (is_user_logged_in()) {
		$user = wp_get_current_user();
		if ($user->exists() && (user_can($user, 'edit_posts') || user_can($user, 'manage_options'))) {
			return;
		}
	}
	$cookie_name = 'fromscratch_site_access';
	$cookie_value = isset($_COOKIE[$cookie_name]) ? sanitize_text_field(wp_unslash($_COOKIE[$cookie_name])) : '';
	if ($cookie_value !== '') {
		$transient_key = 'fromscratch_site_access_' . $cookie_value;
		if (get_transient($transient_key) !== false) {
			return;
		}
	}
	// POST with password
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fromscratch_site_password'])) {
		$submitted = trim((string) wp_unslash($_POST['fromscratch_site_password']));
		if ($submitted !== '' && wp_check_password($submitted, $hash)) {
			$token = bin2hex(random_bytes(32));
			$days = (int) (function_exists('fs_config') ? fs_config('site_password_cookie_days') : 1);
			$days = max(1, min(365, $days));
			$expire = time() + ($days * DAY_IN_SECONDS);
			set_transient('fromscratch_site_access_' . $token, '1', $days * DAY_IN_SECONDS);
			$secure = is_ssl();
			setcookie($cookie_name, $token, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
			if (COOKIEPATH !== '/') {
				setcookie($cookie_name, $token, $expire, '/', COOKIE_DOMAIN, $secure, true);
			}
			$redirect = $_SERVER['REQUEST_URI'] ?? '/';
			wp_safe_redirect(esc_url_raw($redirect), 302);
			exit;
		}
	}
	fs_site_password_show_form();
}

/**
 * Output minimal HTML password form and exit.
 *
 * @return void
 */
function fs_site_password_show_form(): void
{
	$title = __('Protected Area', 'fromscratch');
	$notice = __('This site is password protected. Enter the password to continue.', 'fromscratch');
	$label = __('Password', 'fromscratch');
	$submit = __('Log in', 'fromscratch');
	$current_url = $_SERVER['REQUEST_URI'] ?? '/';
	$current_url = esc_url($current_url);
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
				padding: 0;
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				background: #f0f0f1;
			}

			.box {
				background: #fff;
				padding: 32px 24px;
				max-width: 360px;
				width: 100%;
				box-shadow: 0 1px 3px rgba(0, 0, 0, .13);
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				text-align: center;
				font-size: 16px;
				line-height: 1.4;
			}

			h1 {
				margin: 0 0 24px;
				font-size: 22px;
				line-height: 1.2;
				font-weight: 600;
			}

			.notice {
				margin: 0 0 24px;
				color: #50575e;
			}

			form {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 16px;
			}

			input[type="password"] {
				width: 100%;
				max-width: 260px;
				padding: 10px 16px;
				font-size: 16px;
				line-height: 1.5;
				box-sizing: border-box;
				text-align: center;
				border-radius: 4px;
				border: 1px solid #c3c4c7;
				background: #fff;
				font-weight: 400;
				outline: none;
			}

			input[type="password"]:focus {
				border-color: #2271b1;
			}

			button {
				margin: 0;
				padding: 8px 32px;
				font-size: 16px;
				line-height: 1.5;
				cursor: pointer;
				background: #2271b1;
				color: #fff;
				border: none;
				font-weight: 400;
				border-radius: 4px;
			}

			button:hover {
				background: #135e96;
			}

			.error {
				margin: -4px 0 24px;
				color: #e33;
				font-weight: 600;
			}
		</style>
	</head>

	<body>
		<div class="box">
			<h1><?= esc_html($title) ?></h1>
			<div class="notice"><span><?= esc_html($notice) ?></span></div>
			<?php if (isset($_POST['fromscratch_site_password'])) : ?>
				<div class="error"><?= esc_html__('Incorrect password.', 'fromscratch') ?></div>
			<?php endif; ?>
			<form method="post" action="<?= $current_url ?>">
				<input type="password" name="fromscratch_site_password" id="fromscratch_site_password" autocomplete="current-password" placeholder="<?= esc_html($label) ?>" autofocus>
				<button type="submit"><?= esc_html($submit) ?></button>
			</form>
		</div>
	</body>

	</html>
<?php
	exit;
}

add_action('init', 'fs_site_password_gate', 1);
