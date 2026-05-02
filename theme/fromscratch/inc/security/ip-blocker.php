<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/client-ip.php';

/**
 * Blocked IP addresses and failed login tracking.
 * Gated by Developer → Features: Blocked IPs.
 */

const FS_FAILED_LOGIN_OPTION = 'fromscratch_failed_login_attempts';
const FS_BLOCKED_IPS_OPTION = 'fromscratch_blocked_ips';
/** Map of IP => unix time when we sent the suspicious-block notice (at most one email per IP). */
const FS_SUSPICIOUS_BLOCK_EMAIL_SENT_OPTION = 'fromscratch_suspicious_block_email_sent_ips';
const FS_FAILED_LOGIN_MAX_AGE = 86400; // 24 hours
const FS_FAILED_LOGIN_MAX_ENTRIES = 500;

/**
 * Current visitor IP (same as fs_client_ip()).
 */
function fs_blocked_ips_visitor_ip(): string
{
	return fs_client_ip();
}

/**
 * Parse blocked IP text (one per line) into rules: exact, wildcard (prefix + *), or cidr.
 *
 * @return array<int, array{type: string, value: string}>
 */
function fs_blocked_ips_parse_rules(string $text): array
{
	$lines = preg_split('/\r\n|\r|\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
	$rules = [];
	$seen = [];
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		if (isset($seen[$line])) {
			continue;
		}
		$seen[$line] = true;
		if (strpos($line, '/') !== false && preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $line)) {
			$rules[] = ['type' => 'cidr', 'value' => $line];
			continue;
		}
		if (strpos($line, '*') !== false && preg_match('/^[0-9.*]+$/', $line)) {
			$rules[] = ['type' => 'wildcard', 'value' => $line];
			continue;
		}
		if (filter_var($line, FILTER_VALIDATE_IP)) {
			$rules[] = ['type' => 'exact', 'value' => $line];
		}
	}
	return $rules;
}

/**
 * Check if an IP matches any rule.
 */
function fs_blocked_ips_match(string $ip, array $rules): bool
{
	if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
		return false;
	}
	foreach ($rules as $rule) {
		if ($rule['type'] === 'exact' && $ip === $rule['value']) {
			return true;
		}
		if ($rule['type'] === 'wildcard') {
			$pattern = preg_quote($rule['value'], '/');
			$pattern = str_replace('\*', '[0-9]+', $pattern);
			if (preg_match('/^' . $pattern . '$/', $ip)) {
				return true;
			}
		}
		if ($rule['type'] === 'cidr' && fs_blocked_ips_ip_in_cidr($ip, $rule['value'])) {
			return true;
		}
	}
	return false;
}

function fs_blocked_ips_ip_in_cidr(string $ip, string $cidr): bool
{
	$parts = explode('/', $cidr, 2);
	$range = $parts[0];
	$bits = isset($parts[1]) ? (int) $parts[1] : 32;
	$ip_long = ip2long($ip);
	$range_long = ip2long($range);
	if ($ip_long === false || $range_long === false) {
		return false;
	}
	$mask = -1 << (32 - $bits);
	if ($bits <= 0) {
		$mask = 0;
	}
	$range_net = $range_long & $mask;
	return ($ip_long & $mask) === $range_net;
}

/**
 * Run block check early; exit with 403 if visitor IP is blocked.
 */
function fs_blocked_ips_maybe_block(): void
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('blocked_ips')) {
		return;
	}
	$ip = fs_blocked_ips_visitor_ip();
	if ($ip !== '' && fs_blocked_ips_is_suspicious_locked($ip)) {
		if (!defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}
		$title = __('Access denied', 'fromscratch');
		$body = '<div class="notice">' . esc_html(__('You do not have access to this site.', 'fromscratch')) . '</div>';
		fs_block_page($title, $body, ['status' => 403]);
	}
	$raw = get_option(FS_BLOCKED_IPS_OPTION, '');
	if (trim($raw) === '') {
		return;
	}
	$rules = fs_blocked_ips_parse_rules($raw);
	if (empty($rules)) {
		return;
	}
	if (fs_blocked_ips_match($ip, $rules)) {
		if (!defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}
		$title = __('Access denied', 'fromscratch');
		$body = '<div class="notice">' . esc_html(__('You do not have access to this site.', 'fromscratch')) . '</div>';
		fs_block_page($title, $body, ['status' => 403]);
	}
}

/**
 * Transient key: temporary full-site block when suspicious threshold is hit.
 */
function fs_blocked_ips_suspicious_lock_key(string $ip): string
{
	return 'fromscratch_suspicious_block_' . md5($ip);
}

/**
 * Whether the IP is under an active suspicious-attempts block.
 */
function fs_blocked_ips_is_suspicious_locked(string $ip): bool
{
	if ($ip === '') {
		return false;
	}
	$raw = get_transient(fs_blocked_ips_suspicious_lock_key($ip));
	if ($raw === false) {
		return false;
	}
	$until = (int) $raw;
	if (time() >= $until) {
		delete_transient(fs_blocked_ips_suspicious_lock_key($ip));
		return false;
	}
	return true;
}

/**
 * Record a failed login attempt for the current IP.
 */
function fs_blocked_ips_record_failed_login(): void
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('blocked_ips')) {
		return;
	}
	$ip = fs_blocked_ips_visitor_ip();
	if ($ip === '') {
		return;
	}
	$data = get_option(FS_FAILED_LOGIN_OPTION, []);
	if (!is_array($data)) {
		$data = [];
	}
	$now = time();
	if (!isset($data[$ip]) || !is_array($data[$ip])) {
		$data[$ip] = ['attempts' => 0, 'last' => 0];
	}
	$data[$ip]['attempts'] = (int) ($data[$ip]['attempts'] ?? 0) + 1;
	$data[$ip]['last'] = $now;

	if (count($data) > FS_FAILED_LOGIN_MAX_ENTRIES) {
		uasort($data, function ($a, $b) {
			return ($a['last'] ?? 0) <=> ($b['last'] ?? 0);
		});
		$remove = array_slice(array_keys($data), 0, count($data) - FS_FAILED_LOGIN_MAX_ENTRIES, true);
		foreach ($remove as $old_ip) {
			unset($data[$old_ip]);
		}
	}
	update_option(FS_FAILED_LOGIN_OPTION, $data);
	fs_blocked_ips_suspicious_maybe_enforce($ip);
}

/**
 * Get failed login attempts, optionally cleaning entries older than max age.
 *
 * @return array<string, array{attempts?: int, last?: int, blocked_until?: int, lockout_minutes?: int}>
 */
function fs_blocked_ips_get_failed_attempts(bool $cleanup = true): array
{
	$data = get_option(FS_FAILED_LOGIN_OPTION, []);
	if (!is_array($data)) {
		return [];
	}
	$cutoff = time() - FS_FAILED_LOGIN_MAX_AGE;
	$dirty = false;
	$now = time();
	foreach ($data as $ip => $row) {
		if (!is_array($row) || empty($row['last']) || (int) $row['last'] < $cutoff) {
			unset($data[$ip]);
			$dirty = true;
			continue;
		}
		if (!empty($row['blocked_until']) && (int) $row['blocked_until'] < $now) {
			unset($data[$ip]['blocked_until'], $data[$ip]['lockout_minutes']);
			$dirty = true;
		}
	}
	if ($cleanup && $dirty) {
		update_option(FS_FAILED_LOGIN_OPTION, $data);
	}
	return $data;
}

/**
 * Remove an IP from the failed attempts list.
 */
function fs_blocked_ips_remove_failed(string $ip): void
{
	$data = get_option(FS_FAILED_LOGIN_OPTION, []);
	if (!is_array($data)) {
		return;
	}
	unset($data[$ip]);
	update_option(FS_FAILED_LOGIN_OPTION, $data);
}

/**
 * Clear all failed login attempts.
 */
function fs_blocked_ips_clear_failed(): void
{
	update_option(FS_FAILED_LOGIN_OPTION, []);
}

/**
 * End the temporary suspicious-login auto-block for one IP (site access + list metadata).
 */
function fs_blocked_ips_clear_suspicious_auto_block(string $ip): void
{
	if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
		return;
	}
	delete_transient(fs_blocked_ips_suspicious_lock_key($ip));
	$data = get_option(FS_FAILED_LOGIN_OPTION, []);
	if (!is_array($data) || !isset($data[$ip]) || !is_array($data[$ip])) {
		return;
	}
	unset($data[$ip]['blocked_until'], $data[$ip]['lockout_minutes']);
	update_option(FS_FAILED_LOGIN_OPTION, $data);
}

/**
 * Add an IP to the blocked list (append line, no duplicate).
 */
function fs_blocked_ips_add_blocked(string $ip): void
{
	if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
		return;
	}
	$raw = get_option(FS_BLOCKED_IPS_OPTION, '');
	$lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY)));
	if (in_array($ip, $lines, true)) {
		return;
	}
	$lines[] = $ip;
	update_option(FS_BLOCKED_IPS_OPTION, implode("\n", $lines));
}

/**
 * Get suspicious attempts settings from config.
 *
 * @return array{enabled: bool, attempts: int, window_minutes: int, lockout_minutes: int, send_email: bool}
 */
function fs_blocked_ips_suspicious_config(): array
{
	$config = function_exists('fs_config') ? fs_config('login_suspicious_attempts') : null;
	$defaults = [
		'enabled' => true,
		'attempts' => 10,
		'window_minutes' => 30,
		'lockout_minutes' => 60 * 24, // 24 hours
		'send_email' => true,
	];
	if (!is_array($config)) {
		return $defaults;
	}
	$window = $config['window'] ?? $config['minutes'] ?? 30;
	return [
		'enabled' => array_key_exists('enabled', $config) ? (bool) $config['enabled'] : true,
		'attempts' => max(1, (int) ($config['attempts'] ?? 10)),
		'window_minutes' => max(1, (int) $window),
		'lockout_minutes' => max(1, (int) ($config['lockout'] ?? $config['lockout_minutes'] ?? 60 * 24)), // 24 hours
		'send_email' => !array_key_exists('send_email', $config) ? true : (bool) $config['send_email'],
	];
}

/**
 * Human-readable lockout duration for UI and email.
 */
function fs_blocked_ips_format_lockout_human(int $lockout_minutes): string
{
	if ($lockout_minutes >= 60 && $lockout_minutes % 60 === 0) {
		$h = (int) ($lockout_minutes / 60);
		return sprintf(_n('%d hour', '%d hours', $h, 'fromscratch'), $h);
	}
	if ($lockout_minutes >= 60) {
		$h = (int) floor($lockout_minutes / 60);
		$m = $lockout_minutes % 60;
		return sprintf(
			/* translators: 1: hours, 2: minutes */
			__('%1$d hours %2$d minutes', 'fromscratch'),
			$h,
			$m
		);
	}
	return sprintf(_n('%d minute', '%d minutes', $lockout_minutes, 'fromscratch'), $lockout_minutes);
}

/**
 * When threshold is met: temporary block, optional developer email, persist block end on failed-login row.
 */
function fs_blocked_ips_suspicious_maybe_enforce(string $ip): void
{
	if ($ip === '') {
		return;
	}
	$cfg = fs_blocked_ips_suspicious_config();
	if (empty($cfg['enabled'])) {
		return;
	}
	if (fs_blocked_ips_is_suspicious_locked($ip)) {
		return;
	}
	$failed = fs_blocked_ips_get_failed_attempts(false);
	if (!isset($failed[$ip]) || !is_array($failed[$ip])) {
		return;
	}
	$row = $failed[$ip];
	$attempts = (int) ($row['attempts'] ?? 0);
	$last = (int) ($row['last'] ?? 0);
	$now = time();
	$window_sec = (int) $cfg['window_minutes'] * 60;
	if ($attempts < (int) $cfg['attempts'] || ($now - $last) > $window_sec) {
		return;
	}
	$lockout_min = (int) $cfg['lockout_minutes'];
	$until = $now + $lockout_min * 60;
	$lock_key = fs_blocked_ips_suspicious_lock_key($ip);
	set_transient($lock_key, (string) $until, $lockout_min * 60 + 120);
	$failed[$ip]['blocked_until'] = $until;
	$failed[$ip]['lockout_minutes'] = $lockout_min;
	update_option(FS_FAILED_LOGIN_OPTION, $failed);
	if (!empty($cfg['send_email'])) {
		fs_blocked_ips_send_suspicious_block_email($ip, $lockout_min, $attempts, (int) $cfg['window_minutes']);
	}
}

/**
 * Whether we already sent the suspicious-block email for this IP (once per IP, stored in options).
 */
function fs_blocked_ips_suspicious_block_email_already_sent(string $ip): bool
{
	if ($ip === '') {
		return false;
	}
	$map = get_option(FS_SUSPICIOUS_BLOCK_EMAIL_SENT_OPTION, []);
	return is_array($map) && isset($map[$ip]);
}

/**
 * Record that the suspicious-block email was sent for this IP.
 */
function fs_blocked_ips_suspicious_block_email_mark_sent(string $ip): void
{
	if ($ip === '') {
		return;
	}
	$map = get_option(FS_SUSPICIOUS_BLOCK_EMAIL_SENT_OPTION, []);
	if (!is_array($map)) {
		$map = [];
	}
	$map[$ip] = time();
	update_option(FS_SUSPICIOUS_BLOCK_EMAIL_SENT_OPTION, $map);
}

/**
 * @param int $window_minutes Observation window (for email context).
 */
function fs_blocked_ips_send_suspicious_block_email(string $ip, int $lockout_minutes, int $attempts, int $window_minutes): void
{
	if (fs_blocked_ips_suspicious_block_email_already_sent($ip)) {
		return;
	}
	$to = function_exists('fs_developer_email') ? fs_developer_email() : '';
	if ($to === '' || !is_email($to)) {
		$to = (string) get_option('admin_email', '');
	}
	if ($to === '' || !is_email($to)) {
		return;
	}
	if (!function_exists('fs_compose_email_document')) {
		return;
	}
	$duration = fs_blocked_ips_format_lockout_human($lockout_minutes);
	$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	$sent_at = wp_date(get_option('date_format') . ' ' . get_option('time_format'));
	$page_title = __('Suspicious login — IP blocked', 'fromscratch');
	$body = fs_compose_email_document('suspicious-ip-blocked', [
		'site_name' => $site_name,
		'blocked_ip' => $ip,
		'lockout_duration' => $duration,
		'attempts' => $attempts,
		'window_minutes' => $window_minutes,
		'to_email' => $to,
		'sent_at' => $sent_at,
		'email_page_title' => $page_title,
		'email_html_lang' => str_replace('_', '-', determine_locale()),
		'email_footer_html' => esc_html__('This is an automatic security notice from your WordPress site.', 'fromscratch'),
	]);
	if ($body === '') {
		return;
	}
	$subject = sprintf(
		/* translators: %s: site name */
		__('[%s] IP blocked after suspicious login attempts', 'fromscratch'),
		$site_name
	);
	$headers = ['Content-Type: text/html; charset=UTF-8'];
	if (wp_mail($to, $subject, $body, $headers)) {
		fs_blocked_ips_suspicious_block_email_mark_sent($ip);
	}
}

/**
 * Get IPs that exceed the suspicious-attempts threshold within the time window.
 *
 * @return array<string, array{attempts: int, last: int}>
 */
function fs_blocked_ips_suspicious_list(): array
{
	$failed = fs_blocked_ips_get_failed_attempts(false);
	$config = fs_blocked_ips_suspicious_config();
	$threshold_attempts = (int) $config['attempts'];
	$threshold_seconds = (int) $config['window_minutes'] * 60;
	$now = time();
	$out = [];
	foreach ($failed as $ip => $row) {
		$attempts = (int) ($row['attempts'] ?? 0);
		$last = (int) ($row['last'] ?? 0);
		if ($attempts >= $threshold_attempts && ($now - $last) <= $threshold_seconds) {
			$until = !empty($row['blocked_until']) ? (int) $row['blocked_until'] : 0;
			if ($until <= 0 && fs_blocked_ips_is_suspicious_locked($ip)) {
				$raw = get_transient(fs_blocked_ips_suspicious_lock_key($ip));
				$until = $raw !== false ? (int) $raw : 0;
			}
			$out[$ip] = array_merge($row, ['effective_blocked_until' => $until]);
		}
	}
	uasort($out, function ($a, $b) {
		return ($b['attempts'] ?? 0) <=> ($a['attempts'] ?? 0);
	});
	return $out;
}

add_action('init', 'fs_blocked_ips_maybe_block', 1);
add_action('wp_login_failed', 'fs_blocked_ips_record_failed_login', 10, 1);
