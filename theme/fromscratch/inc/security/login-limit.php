<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/client-ip.php';

/**
 * Limit login attempts by IP using config login_limit (attempts / window / lockout, minutes).
 */

/**
 * Resolved login-limit settings from config/theme.php.
 *
 * @return array{enabled: bool, attempts: int, window_minutes: int, lockout_minutes: int}
 */
function fs_login_limit_settings(): array
{
	$defaults = [
		'enabled' => false,
		'attempts' => 5,
		'window_minutes' => 5,
		'lockout_minutes' => 10,
	];
	if (!function_exists('fs_config')) {
		return $defaults;
	}
	$c = fs_config('login_limit');
	if ($c === true) {
		return array_merge($defaults, ['enabled' => true]);
	}
	if (!is_array($c)) {
		return $defaults;
	}
	$enabled = !empty($c['enabled']);
	$legacy = isset($c['windows'][0]) && is_array($c['windows'][0]) ? $c['windows'][0] : [];
	return [
		'enabled' => $enabled,
		'attempts' => max(1, (int) ($c['attempts'] ?? $legacy['attempts'] ?? $defaults['attempts'])),
		'window_minutes' => max(1, (int) ($c['window'] ?? $legacy['window'] ?? $defaults['window_minutes'])),
		'lockout_minutes' => max(1, (int) ($c['lockout'] ?? $legacy['lockout'] ?? $defaults['lockout_minutes'])),
	];
}

/**
 * Block login when this IP is in lockout.
 *
 * Must run after WordPress core’s wp_authenticate_username_password (priority 20): that callback does not
 * forward a WP_Error from earlier hooks and would overwrite our lockout error with a user lookup.
 *
 * @param null|\WP_User|\WP_Error $user      Result of prior authenticate filters.
 * @param string                   $username Username (unused when locked).
 * @param string                   $password Password (unused when locked).
 * @return \WP_User|\WP_Error|null
 */
function fs_login_limit_authenticate($user, $username, $password)
{
	if (!fs_login_limit_settings()['enabled']) {
		return $user;
	}
	$ip = fs_client_ip();
	if ($ip === '') {
		return $user;
	}
	$key = 'fromscratch_lla_lockout_' . md5($ip);
	$lockout_until = get_transient($key);
	if ($lockout_until !== false && time() < (int) $lockout_until) {
		$remaining_sec = (int) $lockout_until - time();
		$minutes = max(1, (int) ceil($remaining_sec / 60));
		$message = $minutes === 1
			? '<strong>' . esc_html__('Error', 'fromscratch') . ':</strong> ' . esc_html__('Too many login attempts. Please try again in 1 minute.', 'fromscratch')
			: '<strong>' . esc_html__('Error', 'fromscratch') . ':</strong> ' . esc_html(sprintf(__('Too many login attempts. Please try again in %d minutes.', 'fromscratch'), $minutes));
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
	$s = fs_login_limit_settings();
	if (!$s['enabled']) {
		return;
	}
	$ip = fs_client_ip();
	if ($ip === '') {
		return;
	}
	$limit = (int) $s['attempts'];
	$lockout_minutes = (int) $s['lockout_minutes'];
	$window_sec = (int) $s['window_minutes'] * 60;

	$key_attempts = 'fromscratch_lla_' . md5($ip);
	$key_lockout = 'fromscratch_lla_lockout_' . md5($ip);
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
	$ip = fs_client_ip();
	if ($ip === '') {
		return;
	}
	delete_transient('fromscratch_lla_' . md5($ip));
}

if (fs_login_limit_settings()['enabled']) {
	add_filter('authenticate', 'fs_login_limit_authenticate', 999, 3);
	add_action('wp_login_failed', 'fs_login_limit_on_failed', 10, 1);
	add_action('wp_login', 'fs_login_limit_on_success', 10, 2);
}
