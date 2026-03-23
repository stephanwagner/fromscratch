<?php

defined('ABSPATH') || exit;

/**
 * Performance indicator (admin bar + pinned panel + guest-by-IP panel).
 *
 * Two modes:
 *
 * 1. Normal mode (very cheap)
 *    Uses only $wpdb->num_queries for the query count. No SAVEQUERIES, no overhead.
 *
 * 2. Debug mode (expensive-query logging)
 *    When enabled via checkbox: a generated wp-content/db.php wraps queries and only tracks
 *    those slower than the threshold. Backtrace is taken only for slow queries to attribute
 *    plugin/theme/core. Data is saved only for developer or allowed-IP requests.
 *    Display: query count by type (posts, postmeta, terms, options, other), top N slowest,
 *    then full table. Threshold and “only track slow” keep profiler overhead near zero.
 *
 * Portability goal: copy this file into another WP project and adjust only the config array
 * in fs_developer_perf_scale_defaults() (and optionally the option ids below).
 */

/**
 * Performance scale band labels (universal).
 */
function fs_developer_perf_band_labels(): array
{
	return [
		__('Excellent', 'fromscratch'),
		__('Good', 'fromscratch'),
		__('Acceptable', 'fromscratch'),
		__('Heavy', 'fromscratch'),
		__('Problematic', 'fromscratch'),
	];
}

/**
 * Default performance scale config per metric. Each entry has one array: boundaries (5 numbers).
 * First 4 = band boundaries (Excellent → Good → Acceptable → Heavy → Problematic); last value = scale max.
 */
function fs_developer_perf_scale_defaults(): array
{
	return [
		'time'    => ['boundaries' => [0.3, 0.6, 1.2, 3, 3 + 0.3]],
		'memory'  => ['boundaries' => [32, 64, 128, 256, 256 + 32]],
		'queries' => ['boundaries' => [30, 60, 100, 150, 150 + 30]],
		'hooks'   => ['boundaries' => [120, 200, 350, 600, 600 + 120]],
	];
}

/**
 * Get performance scale config for a metric. Edit fs_developer_perf_scale_defaults() to adjust boundaries.
 * Normalizes to max (last value) + boundaries (first 4) for internal use.
 */
function fs_developer_perf_scale_config(string $metric): array
{
	$defaults = fs_developer_perf_scale_defaults();
	if (!isset($defaults[$metric])) {
		return ['max' => 100, 'boundaries' => [20, 40, 60, 80]];
	}
	$b = array_values($defaults[$metric]['boundaries'] ?? []);
	$max = !empty($b) ? (float) end($b) : 100;
	$boundaries = array_map('floatval', array_slice($b, 0, 4));
	while (count($boundaries) < 4) {
		$boundaries[] = $max;
	}
	return ['max' => $max, 'boundaries' => $boundaries];
}

/**
 * Whether OPcache is available and enabled.
 */
function fs_developer_perf_opcache_enabled(): bool
{
	if (!function_exists('opcache_get_status')) {
		return false;
	}
	$status = opcache_get_status(false);
	return is_array($status) && !empty($status['opcache_enabled']);
}

/**
 * Database server type and version (MySQL vs MariaDB). Returns ['type' => 'MariaDB'|'MySQL', 'version' => string] or null.
 */
function fs_developer_perf_db_server(): ?array
{
	global $wpdb;
	if (!$wpdb instanceof \wpdb) {
		return null;
	}
	$version = $wpdb->db_version();
	if ($version === null || $version === '') {
		return null;
	}
	$version = (string) $version;
	$is_mariadb = (stripos($version, 'mariadb') !== false);
	return [
		'type'    => $is_mariadb ? 'MariaDB' : 'MySQL',
		'version' => $version,
	];
}

/**
 * Human-readable object cache type (Redis, Memcached, external, or none).
 */
function fs_developer_perf_object_cache_label(): string
{
	if (!function_exists('wp_using_ext_object_cache') || !wp_using_ext_object_cache()) {
		return '';
	}
	$obj = $GLOBALS['wp_object_cache'] ?? null;
	if (!$obj || !is_object($obj)) {
		return 'external';
	}
	$class = get_class($obj);
	if (stripos($class, 'Redis') !== false) {
		return 'Redis';
	}
	if (stripos($class, 'Memcached') !== false) {
		return 'Memcached';
	}
	return 'external';
}

/**
 * Whether Xdebug extension is loaded.
 */
function fs_developer_perf_xdebug_enabled(): bool
{
	return extension_loaded('xdebug');
}

/**
 * Current request performance metrics. Use in Developer → General, admin bar, and pinned panel.
 *
 * @return array{time: float, memory: float, queries: int, hooks: int}
 */
function fs_developer_perf_metrics(): array
{
	static $snapshot = null;
	if (is_array($snapshot)) {
		return $snapshot;
	}
	global $wpdb, $wp_actions;
	$time = 0;
	if (function_exists('timer_stop')) {
		$time = (float) timer_stop(0, 3);
	}
	if ($time <= 0 && isset($_SERVER['REQUEST_TIME_FLOAT'])) {
		$time = round(microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT'], 3);
	}
	$queries = $wpdb instanceof \wpdb ? (int) $wpdb->num_queries : 0;
	$snapshot = [
		'time'    => (float) $time,
		'memory'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
		'queries' => $queries,
		'hooks'   => is_array($wp_actions) ? count($wp_actions) : 0,
	];
	return $snapshot;
}

/**
 * Format execution time for display. Shows ms for values < 1s.
 *
 * @return array{value: string, unit: string}
 */
function fs_developer_perf_format_time(float $seconds): array
{
	if ($seconds > 0 && $seconds < 1) {
		$ms = (int) round($seconds * 1000);
		return [
			'value' => (string) $ms,
			'unit'  => ' ms',
		];
	}

	return [
		'value' => (string) $seconds,
		'unit'  => ' s',
	];
}

/**
 * Get band label for a value given boundaries (4 numbers) and optional labels.
 *
 * @param float       $value      Current value.
 * @param array       $boundaries [b1, b2, b3, b4] for bands 0–b1, b1–b2, b2–b3, b3–b4, b4+.
 * @param array|null  $labels     Optional; default fs_developer_perf_band_labels().
 * @return string Band label.
 */
function fs_developer_perf_band(float $value, array $boundaries, ?array $labels = null): string
{
	$labels = $labels ?? fs_developer_perf_band_labels();
	foreach (array_values($boundaries) as $i => $b) {
		if ((float) $value <= (float) $b) {
			return $labels[$i] ?? $labels[4];
		}
	}
	return $labels[4] ?? __('Problematic', 'fromscratch');
}

/**
 * Output HTML for the universal performance scale: min | gradient bar + indicator | max, then label.
 *
 * @param float        $value  Current value.
 * @param string|array $metric_or_config Metric key or config array with max + boundaries.
 * @param array        $opts   Optional. compact (bool), show_min_max (bool), unit (string), aria_label_metric (string).
 */
function fs_developer_perf_scale_html(float $value, $metric_or_config, array $opts = []): string
{
	$config = is_array($metric_or_config)
		? $metric_or_config
		: fs_developer_perf_scale_config((string) $metric_or_config);
	$max = (float) ($config['max'] ?? 250);
	$boundaries = array_values($config['boundaries'] ?? [20, 50, 100, 200]);
	$labels = fs_developer_perf_band_labels();
	$label = fs_developer_perf_band($value, $boundaries, $labels);

	$pct = $max > 0 ? min(100, (float) $value / $max * 100) : 0;
	$stops = [];
	foreach ($boundaries as $b) {
		$stops[] = $max > 0 ? round((float) $b / $max * 100, 2) . '%' : '0%';
	}

	$compact = !empty($opts['compact']);
	$show_min_max = isset($opts['show_min_max']) ? (bool) $opts['show_min_max'] : true;
	$unit = isset($opts['unit']) ? (string) $opts['unit'] : '';
	$aria_metric = isset($opts['aria_label_metric']) ? (string) $opts['aria_label_metric'] : '';

	$min_label = '0';
	$max_label = (string) $max;
	if ($unit === 's') {
		$max_label .= 's';
	} elseif ($unit === ' MB') {
		$max_label .= ' MB';
	}

	$aria = $aria_metric
		? sprintf(/* translators: 1: metric name, 2: value, 3: band label */__('%1$s %2$s: %3$s', 'fromscratch'), $aria_metric, $value . $unit, $label)
		: '';

	$bar_style = "--fs-perf-pct: {$pct}; --fs-perf-s1: {$stops[0]}; --fs-perf-s2: {$stops[1]}; --fs-perf-s3: {$stops[2]}; --fs-perf-s4: {$stops[3]};";
	$out = '<span class="fs-perf-scale' . ($compact ? ' fs-perf-scale--compact' : '') . '" style="' . esc_attr($bar_style) . '"' . ($aria ? ' role="img" aria-label="' . esc_attr($aria) . '"' : '') . '>';
	$out .= '<span class="fs-perf-scale__inner">';
	if ($show_min_max) {
		$out .= '<span class="fs-perf-scale__min" aria-hidden="true">' . esc_html($min_label) . '</span>';
	}
	$out .= '<span class="fs-perf-scale__bar-wrap" aria-hidden="true"><span class="fs-perf-scale__bar"></span><span class="fs-perf-scale__indicator"></span></span>';
	if ($show_min_max) {
		$out .= '<span class="fs-perf-scale__max" aria-hidden="true">' . esc_html($max_label) . '</span>';
	}
	$out .= ' <span class="fs-perf-scale__label">' . esc_html($label) . '</span>';
	$out .= '</span></span>';
	return $out;
}

/**
 * Whether the performance block is shown in the admin bar. Default on.
 */
function fs_developer_perf_show_in_admin_bar(): bool
{
	return get_option('fromscratch_perf_admin_bar', '1') === '1';
}

/**
 * Current request client IP (for display and guest panel allowlist).
 */
function fs_developer_perf_current_ip(): string
{
	$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
	if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
		return $ip;
	}
	return '';
}

/**
 * Nginx purge endpoint URL from config/theme.php.
 */
function fs_developer_perf_nginx_purge_url(): string
{
	$url = function_exists('fs_config') ? fs_config('nginx_cache_purge_url') : null;
	if (!is_string($url) || $url === '') {
		$url = '/purge';
	}
	// Allow relative endpoint URLs in config.
	if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
		return $url;
	}
	return home_url($url);
}

function fs_developer_perf_show_nginx_purge_in_admin_bar(): bool
{
	$show = function_exists('fs_config') ? fs_config('nginx_cache_purge_show_in_admin_bar') : null;
	if ($show === null) {
		return true;
	}
	return (bool) $show;
}

/**
 * Whether the sticky performance panel is shown to guests (not logged in) for the current IP.
 */
function fs_developer_perf_panel_guest_visible(): bool
{
	if (is_user_logged_in()) {
		return false;
	}
	if (get_option('fromscratch_perf_panel_guest', '0') !== '1') {
		return false;
	}
	$current = fs_developer_perf_current_ip();
	if ($current === '') {
		return false;
	}
	$allowed = get_option('fromscratch_perf_panel_guest_ips', '');
	if ($allowed === '') {
		return false;
	}
	$list = array_map('trim', explode(',', $allowed));
	$list = array_filter($list, static function ($ip) {
		return filter_var($ip, FILTER_VALIDATE_IP);
	});
	return in_array($current, $list, true);
}

/** Inline CSS for the performance scale inside #wpadminbar (frontend and admin). */
function fs_developer_perf_admin_bar_inline_css(): string
{
	// !important so wp-admin.css cannot override.
	return '
		#wpadminbar .fs-perf-scale { --fs-perf-s1: 8%; --fs-perf-s2: 20%; --fs-perf-s3: 40%; --fs-perf-s4: 80%; display: inline-block !important; margin-left: 8px; vertical-align: middle !important; line-height: 1 !important; }
		#wpadminbar .fs-perf-scale__inner { display: inline-flex !important; align-items: center; gap: 6px; flex-wrap: nowrap; }
		#wpadminbar .fs-perf-scale__min, #wpadminbar .fs-perf-scale__max { font-size: 11px !important; color: #72aee6 !important; white-space: nowrap; line-height: 1 !important; }
		#wpadminbar .fs-perf-scale__bar-wrap { position: relative; display: inline-block !important; width: 72px !important; height: 12px !important; }
		#wpadminbar .fs-perf-scale__bar { position: absolute; inset: 0; height: 8px !important; top: 2px; border-radius: 4px; background: linear-gradient(to right, #22c55e 0%, #22c55e var(--fs-perf-s1), #84cc16 var(--fs-perf-s1), #84cc16 var(--fs-perf-s2), #f97316 var(--fs-perf-s2), #f97316 var(--fs-perf-s3), #ef4444 var(--fs-perf-s3), #ef4444 var(--fs-perf-s4), #b91c1c var(--fs-perf-s4), #b91c1c 100%) !important; }
		#wpadminbar .fs-perf-scale__indicator { position: absolute; top: 0; left: calc(var(--fs-perf-pct, 0) * 1%); transform: translateX(-50%); width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 6px solid #fff !important; pointer-events: none; }
		#wpadminbar .fs-perf-scale__label { font-weight: 600 !important; font-size: 12px !important; white-space: nowrap; line-height: 1 !important; color: inherit; }
	';
}

/**
 * Enqueue inline CSS for the performance scale in the admin bar. Frontend uses wp_enqueue_scripts; admin uses admin_enqueue_scripts.
 */
add_action('wp_enqueue_scripts', function (): void {
	if (!fs_developer_perf_show_in_admin_bar() || !is_user_logged_in() || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id()) || !is_admin_bar_showing()) {
		return;
	}
	wp_add_inline_style('admin-bar', fs_developer_perf_admin_bar_inline_css());
}, 20);

add_action('admin_enqueue_scripts', function (): void {
	if (!fs_developer_perf_show_in_admin_bar() || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	wp_add_inline_style('admin-bar', fs_developer_perf_admin_bar_inline_css());
}, 20);

/**
 * Handle page-cache purge endpoint: /?fs-purge-cache=1&_wpnonce=...
 */
add_action('init', function (): void {
	if (empty($_GET['fs-purge-cache']) || $_GET['fs-purge-cache'] !== '1') {
		return;
	}
	if (!is_user_logged_in() || !current_user_can('manage_options')) {
		return;
	}
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
	if ($nonce === '' || !wp_verify_nonce($nonce, 'fs_purge_cache')) {
		return;
	}

	$purge_url = fs_developer_perf_nginx_purge_url();

	/**
	 * Allow integrations to purge page/full-page caches.
	 */
	do_action('fromscratch_purge_page_cache');

	// Trigger nginx purge endpoint.
	$ok = true;
	if ($purge_url !== '' && function_exists('wp_remote_get')) {
		$response = wp_remote_get($purge_url, [
			'timeout' => 3,
			'redirection' => 0,
			'blocking' => true,
		]);
		if (is_wp_error($response)) {
			$ok = false;
		} else {
			$code = (int) wp_remote_retrieve_response_code($response);
			$ok = $code >= 200 && $code < 500;
		}
	}

	// Optional: flush object cache if available.
	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
	}

	set_transient('fromscratch_purge_cache_notice', $ok ? '1' : '0', 30);

	$redirect = wp_get_referer();
	if (!$redirect) {
		$redirect = home_url('/');
	}
	$redirect = remove_query_arg(['fs-purge-cache', '_wpnonce'], $redirect);
	wp_safe_redirect($redirect);
	exit;
}, 1);

add_action('admin_notices', function (): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!is_admin()) {
		return;
	}
	$notice = get_transient('fromscratch_purge_cache_notice');
	if ($notice === false) {
		return;
	}
	delete_transient('fromscratch_purge_cache_notice');
?>
	<div class="notice notice-success is-dismissible">
		<p><strong><?= esc_html($notice === '1' ? __('Cache purged.', 'fromscratch') : __('Cache purge finished with issues.', 'fromscratch')) ?></strong></p>
	</div>
<?php
}, 20);

/**
 * Show performance metrics in the admin bar for developer users (backend and frontend). Click to expand details with scale per metric.
 */
add_action('admin_bar_menu', function ($admin_bar): void {
	$show_perf = fs_developer_perf_show_in_admin_bar();
	if (!$admin_bar instanceof \WP_Admin_Bar || !is_user_logged_in()) {
		return;
	}
	if ($show_perf && (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id()))) {
		return;
	}
	$perf = fs_developer_perf_metrics();
	$scale = function ($value, $metric, $unit = '', $aria = '') {
		return function_exists('fs_developer_perf_scale_html')
			? fs_developer_perf_scale_html((float) $value, $metric, ['compact' => true, 'show_min_max' => true, 'unit' => $unit, 'aria_label_metric' => $aria])
			: '';
	};

	$perf_icon = '<svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 100%;" width="24px" height="24px" viewBox="0 -960 960 960" fill="currentColor"><path d="M245-474q26-66 62.5-127T390-716l-52-11q-20-4-39 2t-33 20L140-579q-15 15-11.5 36t23.5 29l93 40Zm588-390q-106-5-201.5 41T461-702q-48 48-84.5 104T313-480q-5 13-5 26.5t10 23.5l125 125q10 10 23.5 10t26.5-5q62-27 118-63.5T715-448q75-75 121-170.5T877-820q0-8-4-16t-10-14q-6-6-14-10t-16-4ZM556-622.5q0-33.5 23-56.5t56.5-23q33.5 0 56.5 23t23 56.5q0 33.5-23 56.5t-56.5 23q-33.5 0-56.5-23t-23-56.5ZM487-232l40 93q8 20 29 24t36-11l126-126q14-14 20-33.5t2-39.5l-10-52q-55 46-115.5 82.5T487-232Zm-325-86q35-35 85-35.5t85 34.5q35 35 35 85t-35 85q-48 48-113.5 57T87-74q9-66 18.5-131.5T162-318Z"/></svg>';
	if ($show_perf) {
		// Purge cache (top-level): add before performance node so it appears first in the bar.
		if (current_user_can('manage_options') && fs_developer_perf_show_nginx_purge_in_admin_bar()) {
			$wp_purge_url = wp_nonce_url(add_query_arg('fs-purge-cache', '1', home_url('/')), 'fs_purge_cache');
			$purge_icon = '<svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 100%;" width="24px" height="24px" viewBox="0 -960 960 960" fill="currentColor"><path d="M480-160q-134 0-227-93t-93-227q0-134 93-227t227-93q69 0 132 28.5T720-690v-70q0-17 11.5-28.5T760-800q17 0 28.5 11.5T800-760v200q0 17-11.5 28.5T760-520H560q-17 0-28.5-11.5T520-560q0-17 11.5-28.5T560-600h128q-32-56-87.5-88T480-720q-100 0-170 70t-70 170q0 100 70 170t170 70q68 0 124.5-34.5T692-367q8-14 22.5-19.5t29.5-.5q16 5 23 21t-1 30q-41 80-117 128t-169 48Z"/></svg>';
			$admin_bar->add_node([
				'id'    => 'fs-purge-cache',
				'title' => $purge_icon,
			]);
			$admin_bar->add_node([
				'parent' => 'fs-purge-cache',
				'id'     => 'fs-purge-cache-page',
				'title'  => __('Purge page cache', 'fromscratch'),
				'href'   => $wp_purge_url,
			]);
		}

		$admin_bar->add_node([
			'id'    => 'fs_wp_perf',
			'title' => $perf_icon,
			'href'  => admin_url('options-general.php?page=' . fs_developer_settings_page_slug('system')),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_pin',
			'title'  => __('Pin to bottom-left', 'fromscratch'),
			'meta'   => ['class' => 'fs-perf-pin-trigger'],
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_time',
			'title'  => (function () use ($perf, $scale): string {
				$t = fs_developer_perf_format_time((float) $perf['time']);
				return __('Execution time', 'fromscratch') . ': ' . esc_html($t['value']) . esc_html($t['unit']) . ' ' . $scale($perf['time'], 'time', 's', __('Execution time', 'fromscratch'));
			})(),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_memory',
			'title'  => __('Peak memory', 'fromscratch') . ': ' . esc_html((string) $perf['memory']) . ' MB ' . $scale($perf['memory'], 'memory', ' MB', __('Peak memory', 'fromscratch')),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_queries',
			'title'  => __('DB queries', 'fromscratch') . ': ' . esc_html((string) $perf['queries']) . ' ' . $scale($perf['queries'], 'queries', '', __('DB queries', 'fromscratch')),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_hooks',
			'title'  => __('Hooks fired', 'fromscratch') . ': ' . esc_html((string) $perf['hooks']) . ' ' . $scale($perf['hooks'], 'hooks', '', __('Hooks fired', 'fromscratch')),
		]);
	}
}, 999);

/**
 * Render the pinned performance panel (fixed bottom-left). Shown when developer pinned (localStorage) or when guest by IP (always visible).
 */
function fs_developer_perf_pinned_panel_render(): void
{
	$is_guest = function_exists('fs_developer_perf_panel_guest_visible') && fs_developer_perf_panel_guest_visible();
	$is_developer = is_user_logged_in() && function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id()) && fs_developer_perf_show_in_admin_bar();
	if (is_admin()) {
		if (!$is_developer) {
			return;
		}
	} else {
		if (!$is_guest && !$is_developer) {
			return;
		}
	}
	$perf = fs_developer_perf_metrics();
	$scale = function ($value, $metric, $unit = '') {
		return function_exists('fs_developer_perf_scale_html')
			? fs_developer_perf_scale_html((float) $value, $metric, ['compact' => true, 'show_min_max' => true, 'unit' => $unit])
			: '';
	};
	$panel_css = '
		.fs-perf-pinned-panel { --fs-perf-s1: 8%; --fs-perf-s2: 20%; --fs-perf-s3: 40%; --fs-perf-s4: 80%; }
		.fs-perf-pinned-panel .fs-perf-scale { display: inline-block !important; margin-left: 6px; vertical-align: middle !important; line-height: 1 !important; }
		.fs-perf-pinned-panel .fs-perf-scale__inner { display: inline-flex !important; align-items: center; gap: 4px; flex-wrap: nowrap; }
		.fs-perf-pinned-panel .fs-perf-scale__min, .fs-perf-pinned-panel .fs-perf-scale__max { font-size: 10px !important; color: #646970; white-space: nowrap; }
		.fs-perf-pinned-panel .fs-perf-scale__bar-wrap { position: relative; display: inline-block !important; width: 64px !important; height: 10px !important; }
		.fs-perf-pinned-panel .fs-perf-scale__bar { position: absolute; inset: 0; height: 6px !important; top: 2px; border-radius: 3px; background: linear-gradient(to right, #22c55e 0%, #22c55e var(--fs-perf-s1), #84cc16 var(--fs-perf-s1), #84cc16 var(--fs-perf-s2), #f97316 var(--fs-perf-s2), #f97316 var(--fs-perf-s3), #ef4444 var(--fs-perf-s3), #ef4444 var(--fs-perf-s4), #b91c1c var(--fs-perf-s4), #b91c1c 100%) !important; }
		.fs-perf-pinned-panel .fs-perf-scale__indicator { position: absolute; top: 0; left: calc(var(--fs-perf-pct, 0) * 1%); transform: translateX(-50%); width: 0; height: 0; border-left: 3px solid transparent; border-right: 3px solid transparent; border-top: 5px solid #1d2327; pointer-events: none; }
		.fs-perf-pinned-panel .fs-perf-scale__label { font-weight: 600 !important; font-size: 11px !important; white-space: nowrap; }
	';
	$guest_attr = $is_guest ? ' data-fs-perf-guest="1"' : '';
	$scale_config = [
		'time'    => fs_developer_perf_scale_config('time'),
		'memory'  => fs_developer_perf_scale_config('memory'),
		'queries' => fs_developer_perf_scale_config('queries'),
		'hooks'   => fs_developer_perf_scale_config('hooks'),
		'labels'  => fs_developer_perf_band_labels(),
		'i18n'    => [
			'pages_one'       => __('1 page', 'fromscratch'),
			'pages_many'      => __('%s pages', 'fromscratch'),
			'average'         => __('Average', 'fromscratch'),
			'clear'           => _x('Clear', 'performance panel history', 'fromscratch'),
			'no_data'         => __('No data yet.', 'fromscratch'),
			'execution_time'  => __('Execution time', 'fromscratch'),
			'peak_memory'     => __('Peak memory', 'fromscratch'),
			'db_queries'      => __('DB queries', 'fromscratch'),
			'hooks_fired'     => __('Hooks fired', 'fromscratch'),
		],
	];
	$perf_data_attr = ' data-perf-time="' . esc_attr((string) $perf['time']) . '" data-perf-memory="' . esc_attr((string) $perf['memory']) . '" data-perf-queries="' . esc_attr((string) $perf['queries']) . '" data-perf-hooks="' . esc_attr((string) $perf['hooks']) . '"';
?>
	<div id="fs-perf-pinned-panel" class="fs-perf-pinned-panel" style="display: none; position: fixed; bottom: 12px; left: 12px; z-index: 999999; max-width: 320px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,.15); font-size: 12px; line-height: 1.4;" <?= $guest_attr ?><?= $perf_data_attr ?>>
		<script type="application/json" id="fs-perf-scale-config">
			<?= wp_json_encode($scale_config) ?>
		</script>
		<style>
			<?= $panel_css ?>
		</style>
		<div class="fs-perf-pinned-panel__content">
			<div style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-bottom: 1px solid #c3c4c7; background: #f0f0f1;">
				<strong><?= esc_html__('Performance', 'fromscratch') ?></strong>
				<?php if ($is_guest) : ?>
					<button type="button" class="fs-perf-minimize button button-small" style="padding: 2px 8px; font-size: 11px;"><?= esc_html__('Minimize', 'fromscratch') ?></button>
				<?php else : ?>
					<button type="button" class="fs-perf-unpin button button-small" style="padding: 2px 8px;"><?= esc_html__('Unpin', 'fromscratch') ?></button>
				<?php endif; ?>
			</div>
			<div style="padding: 8px 10px;">
				<?php $t = fs_developer_perf_format_time((float) $perf['time']); ?>
				<div style="margin-bottom: 4px;"><?= esc_html__('Execution time', 'fromscratch') ?>: <strong><?= esc_html($t['value']) ?><?= esc_html($t['unit']) ?></strong> <?= $scale($perf['time'], 'time', 's') ?></div>
				<div style="margin-bottom: 4px;"><?= esc_html__('Peak memory', 'fromscratch') ?>: <strong><?= esc_html((string) $perf['memory']) ?> MB</strong> <?= $scale($perf['memory'], 'memory', ' MB') ?></div>
				<div style="margin-bottom: 4px;"><?= esc_html__('DB queries', 'fromscratch') ?>: <strong><?= esc_html((string) $perf['queries']) ?></strong> <?= $scale($perf['queries'], 'queries', '') ?></div>
				<div style="margin-bottom: 4px;"><?= esc_html__('Hooks fired', 'fromscratch') ?>: <strong><?= esc_html((string) $perf['hooks']) ?></strong> <?= $scale($perf['hooks'], 'hooks', '') ?></div>
			</div>
			<div id="fs-perf-average-section" style="padding: 0 10px 8px; border-top: 1px solid #c3c4c7; margin-top: 4px; padding-top: 8px;"></div>
		</div>
		<?php if ($is_guest) : ?>
			<button type="button" class="fs-perf-pinned-panel__tab" style="display: none; padding: 6px 12px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,.1); font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;"><?= esc_html__('Performance', 'fromscratch') ?> ▲</button>
		<?php endif; ?>
	</div>
	<script>
		(function() {
			var panel = document.getElementById('fs-perf-pinned-panel');
			if (!panel) return;

			var HISTORY_KEY = 'fs_perf_history';
			var MAX_HISTORY = 50;

			var current = {
				time: parseFloat(panel.getAttribute('data-perf-time')) || 0,
				memory: parseFloat(panel.getAttribute('data-perf-memory')) || 0,
				queries: parseInt(panel.getAttribute('data-perf-queries'), 10) || 0,
				hooks: parseInt(panel.getAttribute('data-perf-hooks'), 10) || 0
			};

			var raw = null;
			try {
				raw = document.getElementById('fs-perf-scale-config');
			} catch (e) {}
			var config = raw && raw.textContent ? JSON.parse(raw.textContent) : null;

			var history = [];
			try {
				history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
			} catch (e) {}
			if (!Array.isArray(history)) history = [];
			history.push(current);
			history = history.slice(-MAX_HISTORY);
			try {
				localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
			} catch (e) {}

			var avgSection = document.getElementById('fs-perf-average-section');
			if (config && avgSection) {
				var labels = config.labels || ['Excellent', 'Good', 'Acceptable', 'Heavy', 'Problematic'];

				function bandLabel(value, boundaries) {
					for (var i = 0; i < boundaries.length; i++) {
						if (value <= boundaries[i]) return labels[i] || labels[4];
					}
					return labels[4];
				}

				function scaleHtml(value, metric) {
					var c = config[metric];
					if (!c || !c.boundaries) return '';
					var max = parseFloat(c.max) || 100;
					var b = c.boundaries;
					var pct = max > 0 ? Math.min(100, (value / max) * 100) : 0;
					var s1 = max > 0 ? (b[0] / max * 100).toFixed(2) + '%' : '0%';
					var s2 = max > 0 ? (b[1] / max * 100).toFixed(2) + '%' : '0%';
					var s3 = max > 0 ? (b[2] / max * 100).toFixed(2) + '%' : '0%';
					var s4 = max > 0 ? (b[3] / max * 100).toFixed(2) + '%' : '0%';
					var label = bandLabel(value, b);
					var maxLabel = metric === 'time' ? max + 's' : metric === 'memory' ? max + ' MB' : String(max);
					return '<span class="fs-perf-scale" style="--fs-perf-pct:' + pct + ';--fs-perf-s1:' + s1 + ';--fs-perf-s2:' + s2 + ';--fs-perf-s3:' + s3 + ';--fs-perf-s4:' + s4 + ';display:inline-block!important;margin-left:6px;vertical-align:middle!important;line-height:1!important">' +
						'<span class="fs-perf-scale__inner" style="display:inline-flex!important;align-items:center;gap:4px;flex-wrap:nowrap">' +
						'<span class="fs-perf-scale__min" style="font-size:10px!important;color:#646970;white-space:nowrap">0</span>' +
						'<span class="fs-perf-scale__bar-wrap" style="position:relative;display:inline-block!important;width:64px!important;height:10px!important">' +
						'<span class="fs-perf-scale__bar" style="position:absolute;inset:0;height:6px!important;top:2px;border-radius:3px;background:linear-gradient(to right,#22c55e 0%,#22c55e var(--fs-perf-s1),#84cc16 var(--fs-perf-s1),#84cc16 var(--fs-perf-s2),#f97316 var(--fs-perf-s2),#f97316 var(--fs-perf-s3),#ef4444 var(--fs-perf-s3),#ef4444 var(--fs-perf-s4),#b91c1c var(--fs-perf-s4),#b91c1c 100%)!important"></span>' +
						'<span class="fs-perf-scale__indicator" style="position:absolute;top:0;left:calc(var(--fs-perf-pct,0)*1%);transform:translateX(-50%);width:0;height:0;border-left:3px solid transparent;border-right:3px solid transparent;border-top:5px solid #1d2327;pointer-events:none"></span></span>' +
						'<span class="fs-perf-scale__max" style="font-size:10px!important;color:#646970;white-space:nowrap">' + maxLabel + '</span> ' +
						'<span class="fs-perf-scale__label" style="font-weight:600!important;font-size:11px!important;white-space:nowrap">' + label + '</span></span></span>';
				}

				function renderAverageSection(data) {
					var count = Array.isArray(data) ? data.length : 0;
					var i18n = config.i18n || {};
					var pagesLabel = count === 1 ? (i18n.pages_one || '1 page') : (i18n.pages_many || '%s pages').replace('%s', count);
					var avgTitle = i18n.average || 'Average';
					var clearLabel = i18n.clear || 'Clear';
					var noDataLabel = i18n.no_data || 'No data yet.';
					var m = i18n;
					var avg = count ? {
						time: data.reduce(function(s, p) {
							return s + p.time;
						}, 0) / count,
						memory: data.reduce(function(s, p) {
							return s + p.memory;
						}, 0) / count,
						queries: data.reduce(function(s, p) {
							return s + p.queries;
						}, 0) / count,
						hooks: data.reduce(function(s, p) {
							return s + p.hooks;
						}, 0) / count
					} : null;
					var headerRow = '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px"><div style="font-weight:600">' + avgTitle + '</div>' +
						(count > 0 ? '<button type="button" class="fs-perf-clear-history button button-small" style="padding:2px 6px;font-size:10px">' + clearLabel + '</button>' : '') + '</div>';
					var body = count > 0 && avg ?
						(
							'<div style="font-size:11px;color:#646970;margin-bottom:6px">(' + pagesLabel + ')</div>' +
							'<div style="margin-bottom:4px">' + (m.execution_time || 'Execution time') + ': <strong>' + (avg.time < 1 ? Math.round(avg.time * 1000) + 'ms' : avg.time.toFixed(3) + 's') + '</strong> ' + scaleHtml(avg.time, 'time') + '</div>' +
							'<div style="margin-bottom:4px">' + (m.peak_memory || 'Peak memory') + ': <strong>' + avg.memory.toFixed(2) + ' MB</strong> ' + scaleHtml(avg.memory, 'memory') + '</div>' +
							'<div style="margin-bottom:4px">' + (m.db_queries || 'DB queries') + ': <strong>' + Math.round(avg.queries) + '</strong> ' + scaleHtml(avg.queries, 'queries') + '</div>' +
							'<div style="margin-bottom:4px">' + (m.hooks_fired || 'Hooks fired') + ': <strong>' + Math.round(avg.hooks) + '</strong> ' + scaleHtml(avg.hooks, 'hooks') + '</div>'
						) :
						'<div style="font-size:11px;color:#646970">' + noDataLabel + '</div>';
					avgSection.innerHTML = headerRow + body;
					var clearBtn = avgSection.querySelector('.fs-perf-clear-history');
					if (clearBtn) {
						clearBtn.addEventListener('click', function() {
							try {
								localStorage.setItem(HISTORY_KEY, '[]');
							} catch (e) {}
							renderAverageSection([]);
						});
					}
				}
				renderAverageSection(history);
			}

			var isGuest = panel.getAttribute('data-fs-perf-guest') === '1';
			var content = panel.querySelector('.fs-perf-pinned-panel__content');
			var tab = panel.querySelector('.fs-perf-pinned-panel__tab');
			var MINIMIZED_KEY = 'fs_perf_minimized';

			function show() {
				panel.style.display = 'block';
			}

			function hide() {
				panel.style.display = 'none';
			}

			function setExpanded(expanded) {
				if (content) content.style.display = expanded ? 'block' : 'none';
				if (tab) tab.style.display = expanded ? 'none' : 'block';
				if (isGuest) try {
					sessionStorage.setItem(MINIMIZED_KEY, expanded ? '0' : '1');
				} catch (e) {}
			}
			if (isGuest) {
				show();
				var startMinimized = false;
				try {
					startMinimized = sessionStorage.getItem(MINIMIZED_KEY) === '1';
				} catch (e) {}
				setExpanded(!startMinimized);
				var minBtn = panel.querySelector('.fs-perf-minimize');
				if (minBtn) minBtn.addEventListener('click', function() {
					setExpanded(false);
				});
				if (tab) tab.addEventListener('click', function() {
					setExpanded(true);
				});
			} else {
				if (localStorage.getItem('fs_perf_pinned') === '1') show();
				var unpin = panel.querySelector('.fs-perf-unpin');
				if (unpin) unpin.addEventListener('click', function() {
					localStorage.removeItem('fs_perf_pinned');
					hide();
				});
				document.addEventListener('click', function(e) {
					var t = e.target.closest && e.target.closest('.fs-perf-pin-trigger');
					if (t && t.querySelector('a')) {
						e.preventDefault();
						localStorage.setItem('fs_perf_pinned', '1');
						show();
					}
				});
			}
		})();
	</script>
<?php
}

add_action('wp_footer', 'fs_developer_perf_pinned_panel_render', 20);
add_action('admin_footer', 'fs_developer_perf_pinned_panel_render', 20);
