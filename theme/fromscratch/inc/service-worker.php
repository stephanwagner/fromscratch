<?php

defined('ABSPATH') || exit;

/**
 * Front-end service worker: registration data for main.js + PHP route for the worker script.
 *
 * Worker implementation: src/js/service-worker/index.js (+ modules). Built: assets/js/service-worker(.min).js.
 * Offline shells: assets/html/offline.html (en) and offline-de.html (de); PHP injects them into the worker response
 * so they work with no network and no Cache API.
 */

/**
 * Service worker registration scope and max path for `Service-Worker-Allowed` (WordPress in a subdirectory).
 */
function fs_service_worker_scope(): string
{
	$path = parse_url(home_url('/'), PHP_URL_PATH);
	if (!is_string($path) || $path === '' || $path === '/') {
		return '/';
	}

	return trailingslashit($path);
}

/**
 * Readable path to the built service worker script (dev vs min).
 */
function fs_service_worker_built_js_path(): string
{
	$min = function_exists('fs_is_debug') && fs_is_debug() ? '' : '.min';
	$path = get_template_directory() . '/assets/js/service-worker' . $min . '.js';
	if (!is_readable($path)) {
		$path = get_template_directory() . '/assets/js/service-worker.min.js';
	}

	return $path;
}

/**
 * Cache-bust value for the worker registration URL (theme release + file changes).
 * Bumps when style.css Version changes, when the worker bundle is rebuilt, or offline HTML is edited.
 */
function fs_service_worker_version_string(): string
{
	$theme = wp_get_theme(get_template());
	$ver = $theme->exists() ? (string) $theme->get('Version') : '0';

	$base = get_template_directory();
	$sw = fs_service_worker_built_js_path();
	$mtimes = [is_readable($sw) ? filemtime($sw) : 0];
	foreach (['assets/html/offline.html', 'assets/html/offline-de.html'] as $rel) {
		$p = $base . '/' . $rel;
		if (is_readable($p)) {
			$mtimes[] = filemtime($p);
		}
	}

	return $ver . '-' . (string) max($mtimes);
}

/**
 * Public URL that serves the service worker script (`Service-Worker-Allowed` matches scope; see init handler).
 * Version query forces the browser to fetch a new script after theme or asset updates.
 */
function fs_service_worker_script_url(): string
{
	return add_query_arg([
		'fromscratch_sw' => '1',
		'v' => fs_service_worker_version_string(),
	], home_url('/'));
}

/**
 * Prefer German offline shell when site locale is a German variant (de_DE, de_AT, …).
 */
function fs_service_worker_offline_language(): string
{
	$locale = function_exists('determine_locale') ? determine_locale() : (function_exists('get_locale') ? get_locale() : 'en_US');
	$locale = is_string($locale) ? strtolower($locale) : 'en_us';

	return strpos($locale, 'de') === 0 ? 'de' : 'en';
}

/**
 * Load offline HTML files for injection into the service worker (UTF-8).
 *
 * @return array{en: string, de: string}
 */
function fs_service_worker_offline_html_files(): array
{
	$dir = trailingslashit(get_template_directory()) . 'assets/html/';
	$en_path = $dir . 'offline.html';
	$de_path = $dir . 'offline-de.html';
	$fallback = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Offline</title></head><body><p>Offline</p></body></html>';
	$en = is_readable($en_path) ? (string) file_get_contents($en_path) : $fallback;
	$de = is_readable($de_path) ? (string) file_get_contents($de_path) : $en;

	return ['en' => $en, 'de' => $de];
}

/**
 * Serve built service worker (src/js/service-worker/index.js) when ?fromscratch_sw=1.
 */
add_action('init', function (): void {
	if (!isset($_GET['fromscratch_sw']) || (string) wp_unslash($_GET['fromscratch_sw']) !== '1') {
		return;
	}

	if (headers_sent()) {
		return;
	}

	$path = fs_service_worker_built_js_path();
	if (!is_readable($path)) {
		status_header(404);
		header('Content-Type: text/plain; charset=utf-8');
		echo '// Service worker not built. Run: npm run build:js';
		exit;
	}

	nocache_headers();
	header('Content-Type: application/javascript; charset=utf-8');
	header('Service-Worker-Allowed: ' . fs_service_worker_scope());

	$html = fs_service_worker_offline_html_files();
	$lang = fs_service_worker_offline_language();
	$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
		$json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
	}
	echo 'self.__FS_OFFLINE_HTML__=' . wp_json_encode($html, $json_flags) . ";\n";
	echo 'self.__FS_OFFLINE_LANG__=' . wp_json_encode($lang, $json_flags) . ";\n";

	readfile($path);
	exit;
}, 0);

/**
 * Pass registration URL + scope to the front-end bundle.
 */
add_action('wp_enqueue_scripts', function (): void {
	if (is_admin() || wp_doing_ajax() || (function_exists('wp_is_json_request') && wp_is_json_request())) {
		return;
	}
	if (function_exists('is_customize_preview') && is_customize_preview()) {
		return;
	}
	if (wp_installing()) {
		return;
	}
	if (!wp_script_is('main-scripts', 'enqueued')) {
		return;
	}

	wp_localize_script('main-scripts', 'fromscratchServiceWorker', [
		'url' => fs_service_worker_script_url(),
		'scope' => fs_service_worker_scope(),
	]);
}, 20);
