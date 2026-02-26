<?php

defined('ABSPATH') || exit;

/**
 * Write recommended .htaccess rules to WP root (Apache only).
 * Adds: MIME types, mod_expires, no Set-Cookie on static assets, manifest cache, mod_deflate.
 * Safe to call multiple times; replaces existing FromScratch block when present.
 *
 * @return bool True if block was written or already present, false if skipped (Nginx, unwritable, etc.)
 */
function fs_write_htaccess(): bool
{
	if (!isset($_SERVER['SERVER_SOFTWARE']) || stripos($_SERVER['SERVER_SOFTWARE'], 'Apache') === false) {
		return false;
	}

	$htaccess     = ABSPATH . '.htaccess';
	$marker_start = '# BEGIN FromScratch';
	$marker_end   = '# END FromScratch';

	$template = __DIR__ . '/htaccess-file.txt';
	if (!is_readable($template)) {
		return false;
	}
	$block = $marker_start . "\n" . trim(file_get_contents($template)) . "\n" . $marker_end . "\n";

	if (!file_exists($htaccess)) {
		if (!is_writable(ABSPATH)) {
			return false;
		}
		return file_put_contents($htaccess, $block, LOCK_EX) !== false;
	}

	$content = file_get_contents($htaccess);
	if ($content === false || !is_writable($htaccess)) {
		return false;
	}

	$has_start = strpos($content, $marker_start) !== false;
	$has_end   = strpos($content, $marker_end) !== false;

	if ($has_start && $has_end) {
		$start  = strpos($content, $marker_start);
		$end    = strpos($content, $marker_end) + strlen($marker_end);
		$content = substr($content, 0, $start) . $block . substr($content, $end);
		return file_put_contents($htaccess, $content, LOCK_EX) !== false;
	}
	if (!$has_start && !$has_end) {
		$content = rtrim($content) . "\n\n" . $block;
		return file_put_contents($htaccess, $content, LOCK_EX) !== false;
	}
	return true;
}
