<?php

defined('ABSPATH') || exit;

/**
 * Limit login attempts by IP: after N failed attempts per minute, block and show wait message.
 * Config: login_limit (bool), login_limit_attempts (int), login_limit_lockout (minutes).
 */

function fs_login_limit_client_ip(): string
{
	return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
}

function fs_login_limit_authenticate($user, $username, $password)
{
	if (!function_exists('fs_config') || !fs_config('login_limit')) {
		return $user;
	}
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
			? '<strong>Error:</strong> ' . esc_html__('Too many login attempts. Please try again in 1 minute.', 'fromscratch')
			: '<strong>Error:</strong> ' . esc_html(sprintf(__('Too many login attempts. Please try again in %d minutes.', 'fromscratch'), $minutes));
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
	if (!function_exists('fs_config') || !fs_config('login_limit')) {
		return;
	}
	$ip = fs_login_limit_client_ip();
	if ($ip === '') {
		return;
	}
	$limit = (int) (fs_config('login_limit_attempts') ?? 5);
	$lockout_minutes = (int) (fs_config('login_limit_lockout') ?? 3);

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

if (function_exists('fs_config') && fs_config('login_limit')) {
	add_filter('authenticate', 'fs_login_limit_authenticate', 5, 3);
	add_action('wp_login_failed', 'fs_login_limit_on_failed', 10, 1);
	add_action('wp_login', 'fs_login_limit_on_success', 10, 2);
}
