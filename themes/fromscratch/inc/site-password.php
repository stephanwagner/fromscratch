<?php

defined('ABSPATH') || exit;

/**
 * Site password protection: frontend and admin behind one shared password.
 * Logged-in administrators and editors bypass the gate. Options in Settings → Theme → Security.
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
			$redirect = (is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
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
	$title = fs_t('SITE_PASSWORD_FORM_TITLE');
	$notice = fs_t('SITE_PASSWORD_FORM_NOTICE');
	$label = fs_t('SITE_PASSWORD_FORM_LABEL');
	$submit = fs_t('SITE_PASSWORD_FORM_SUBMIT');
	$current_url = (is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
	$current_url = esc_url($current_url);
	$lock_icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="stroke-width:2" aria-hidden="true"><path d="M7 11V7a5 5 0 0 1 10 0v4"/><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/></svg>';
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
		.box { background: #fff; padding: 2rem; max-width: 360px; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,.13); border: 1px solid #c3c4c7; }
		h1 { margin: 0 0 0.5rem 0; font-size: 1.5rem; font-weight: 600; }
		.notice { display: flex; align-items: flex-start; gap: 0.5rem; margin: 0 0 1.25rem 0; padding: 0.75rem; background: #f0f6fc; border-left: 4px solid #2271b1; color: #1e3a5f; }
		.notice svg { flex-shrink: 0; margin-top: 0.1rem; }
		p { margin: 0 0 1rem 0; color: #50575e; }
		label { display: block; margin-bottom: 0.25rem; font-weight: 600; }
		input[type="password"] { width: 100%; max-width: 220px; padding: 0.5rem; font-size: 1rem; box-sizing: border-box; }
		button { margin-top: 1rem; padding: 0.5rem 1rem; font-size: 1rem; cursor: pointer; background: #2271b1; color: #fff; border: none; }
		button:hover { background: #135e96; }
		.error { margin-bottom: 1rem; padding: 0.5rem; background: #fcf0f1; border-left: 4px solid #d63638; color: #3c434a; }
	</style>
</head>
<body>
	<div class="box">
		<h1><?= esc_html($title) ?></h1>
		<p class="notice"><?= $lock_icon ?><span><?= esc_html($notice) ?></span></p>
		<?php if (isset($_POST['fromscratch_site_password'])) : ?>
		<p class="error"><?= esc_html(fs_t('SITE_PASSWORD_FORM_ERROR')) ?></p>
		<?php endif; ?>
		<form method="post" action="<?= $current_url ?>">
			<label for="fromscratch_site_password"><?= esc_html($label) ?></label>
			<input type="password" name="fromscratch_site_password" id="fromscratch_site_password" autocomplete="current-password" autofocus>
			<button type="submit"><?= esc_html($submit) ?></button>
		</form>
	</div>
</body>
</html>
	<?php
	exit;
}

add_action('init', 'fs_site_password_gate', 1);

/**
 * Use "Login" as the document title on the WordPress login page.
 */
add_filter('login_title', function ($title) {
	return fs_t('SITE_PASSWORD_FORM_TITLE');
}, 10, 1);
