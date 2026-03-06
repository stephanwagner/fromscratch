<?php

defined('ABSPATH') || exit;

/**
 * Blocked IP addresses and failed login tracking.
 * Gated by Developer → Features: Blocked IPs.
 */

const FS_FAILED_LOGIN_OPTION = 'fromscratch_failed_login_attempts';
const FS_BLOCKED_IPS_OPTION = 'fromscratch_blocked_ips';
const FS_FAILED_LOGIN_MAX_AGE = 86400; // 24 hours
const FS_FAILED_LOGIN_MAX_ENTRIES = 500;

function fs_blocked_ips_visitor_ip(): string
{
	return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
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
	$raw = get_option(FS_BLOCKED_IPS_OPTION, '');
	if (trim($raw) === '') {
		return;
	}
	$rules = fs_blocked_ips_parse_rules($raw);
	if (empty($rules)) {
		return;
	}
	$ip = fs_blocked_ips_visitor_ip();
	if (fs_blocked_ips_match($ip, $rules)) {
		if (!defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true);
		}
		status_header(403);
		nocache_headers();
		header('Content-Type: text/html; charset=utf-8');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access denied</title></head><body><h1>Access denied</h1></body></html>';
		exit;
	}
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
}

/**
 * Get failed login attempts, optionally cleaning entries older than max age.
 *
 * @return array<string, array{attempts: int, last: int}>
 */
function fs_blocked_ips_get_failed_attempts(bool $cleanup = true): array
{
	$data = get_option(FS_FAILED_LOGIN_OPTION, []);
	if (!is_array($data)) {
		return [];
	}
	$cutoff = time() - FS_FAILED_LOGIN_MAX_AGE;
	$dirty = false;
	foreach ($data as $ip => $row) {
		if (!is_array($row) || empty($row['last']) || (int) $row['last'] < $cutoff) {
			unset($data[$ip]);
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
 * Get suspicious attempts threshold from config.
 *
 * @return array{attempts: int, minutes: int}
 */
function fs_blocked_ips_suspicious_config(): array
{
	$config = function_exists('fs_config') ? fs_config('login_suspicious_attempts') : null;
	if (!is_array($config)) {
		return ['attempts' => 10, 'minutes' => 10];
	}
	return [
		'attempts' => max(1, (int) ($config['attempts'] ?? 10)),
		'minutes' => max(1, (int) ($config['minutes'] ?? 10)),
	];
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
	$threshold_seconds = (int) $config['minutes'] * 60;
	$now = time();
	$out = [];
	foreach ($failed as $ip => $row) {
		$attempts = (int) ($row['attempts'] ?? 0);
		$last = (int) ($row['last'] ?? 0);
		if ($attempts >= $threshold_attempts && ($now - $last) <= $threshold_seconds) {
			$out[$ip] = $row;
		}
	}
	uasort($out, function ($a, $b) {
		return ($b['attempts'] ?? 0) <=> ($a['attempts'] ?? 0);
	});
	return $out;
}

add_action('init', 'fs_blocked_ips_maybe_block', 1);
add_action('wp_login_failed', 'fs_blocked_ips_record_failed_login', 10, 1);
