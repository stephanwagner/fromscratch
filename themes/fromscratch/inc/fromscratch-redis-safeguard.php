<?php
/**
 * FromScratch Redis Guard
 * Prevents site break if Redis is unavailable.
 */

if (!class_exists('Redis')) {
	if (!defined('WP_REDIS_DISABLED')) {
		define('WP_REDIS_DISABLED', true);
	}
	return;
}

$host = defined('WP_REDIS_HOST') ? (string) constant('WP_REDIS_HOST') : '127.0.0.1';
$port = defined('WP_REDIS_PORT') ? (int) constant('WP_REDIS_PORT') : 6379;

try {
	$redis_class = 'Redis';
	$r = new $redis_class();
	if (!@$r->connect($host, $port, 0.5)) {
		if (!defined('WP_REDIS_DISABLED')) {
			define('WP_REDIS_DISABLED', true);
		}
	}
} catch (Throwable $e) {
	if (!defined('WP_REDIS_DISABLED')) {
		define('WP_REDIS_DISABLED', true);
	}
}
