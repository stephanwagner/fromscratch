<?php

defined('ABSPATH') || exit;

/**
 * Limit login attempts by IP: after N failed attempts per minute, block and show wait message.
 * Options from Settings → Theme → Security.
 */

/**
 * Get client IP for login limit (same as used elsewhere).
 *
 * @return string
 */
function fs_login_limit_client_ip(): string
{
	return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

/**
 * Check if IP is currently locked out; if so, return WP_Error with wait message.
 *
 * @param WP_User|WP_Error|null $user     User or error from previous filter.
 * @param string                $username Username.
 * @param string                $password Password.
 * @return WP_User|WP_Error|null
 */
function fs_login_limit_authenticate($user, $username, $password)
{
	$ip = fs_login_limit_client_ip();
	if ($ip === '') {
		return $user;
	}
	$key = 'fromscratch_lla_lockout_' . md5($ip);
	$lockout_until = get_transient($key);
	if ($lockout_until !== false && time() < (int) $lockout_until) {
		$remaining_sec = (int) $lockout_until - time();
		$minutes = max(1, (int) ceil($remaining_sec / 60));
		$message = $minutes === 1
			? '<strong>Error:</strong> ' . fs_t('LOGIN_LIMIT_ERROR_WAIT_ONE_MINUTE')
			: '<strong>Error:</strong> ' . sprintf(fs_t('LOGIN_LIMIT_ERROR_WAIT_MINUTES'), $minutes);
		return new WP_Error('login_limit_exceeded', $message);
	}
	return $user;
}

/**
 * On failed login: increment attempt count; if over limit, set lockout.
 *
 * @param string $username Username (unused).
 * @return void
 */
function fs_login_limit_on_failed(string $username): void
{
	$ip = fs_login_limit_client_ip();
	if ($ip === '') {
		return;
	}
	$attempts_default = (int) (function_exists('fs_config') ? fs_config('login_limit_attempts_default') : 5);
	$attempts_min = (int) (function_exists('fs_config') ? fs_config('login_limit_attempts_min') : 3);
	$attempts_max = (int) (function_exists('fs_config') ? fs_config('login_limit_attempts_max') : 10);
	$limit = (int) get_option('fromscratch_login_limit_attempts', $attempts_default);
	$limit = max($attempts_min, min($attempts_max, $limit));

	$lockout_default = (int) (function_exists('fs_config') ? fs_config('login_limit_lockout_default') : 15);
	$lockout_min = (int) (function_exists('fs_config') ? fs_config('login_limit_lockout_min') : 1);
	$lockout_max = (int) (function_exists('fs_config') ? fs_config('login_limit_lockout_max') : 120);
	$lockout_minutes = (int) get_option('fromscratch_login_limit_lockout_minutes', $lockout_default);
	$lockout_minutes = max($lockout_min, min($lockout_max, $lockout_minutes));

	$key_attempts = 'fromscratch_lla_' . md5($ip);
	$key_lockout = 'fromscratch_lla_lockout_' . md5($ip);
	$window_sec = 60;
	$now = time();

	$data = get_transient($key_attempts);
	if ($data === false || !is_array($data)) {
		set_transient($key_attempts, ['c' => 1, 'start' => $now], $window_sec + 30);
		return;
	}
	$start = (int) ($data['start'] ?? 0);
	$count = (int) ($data['c'] ?? 0);
	if ($now - $start >= $window_sec) {
		set_transient($key_attempts, ['c' => 1, 'start' => $now], $window_sec + 30);
		return;
	}
	$count++;
	set_transient($key_attempts, ['c' => $count, 'start' => $start], $window_sec + 30);
	if ($count >= $limit) {
		delete_transient($key_attempts);
		$lockout_until = $now + ($lockout_minutes * 60);
		set_transient($key_lockout, (string) $lockout_until, $lockout_minutes * 60 + 60);
	}
}

/**
 * On successful login: clear attempt count for this IP.
 *
 * @param string  $username Username (unused).
 * @param WP_User $user     User (unused).
 * @return void
 */
function fs_login_limit_on_success(string $username, WP_User $user): void
{
	$ip = fs_login_limit_client_ip();
	if ($ip === '') {
		return;
	}
	delete_transient('fromscratch_lla_' . md5($ip));
}

add_filter('authenticate', 'fs_login_limit_authenticate', 5, 3);
add_action('wp_login_failed', 'fs_login_limit_on_failed', 10, 1);
add_action('wp_login', 'fs_login_limit_on_success', 10, 2);
