<?php

defined('ABSPATH') || exit;

/**
 * Performance indicator (admin bar + floating panel + guest-by-IP panel).
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
 * Current request performance metrics. Use in Developer → General, admin bar, and floating panel.
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
 * @param array        $opts   Optional. compact (bool), unit (string), aria_label_metric (string).
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
	$out .= '<span class="fs-perf-scale__bar-wrap" aria-hidden="true"><span class="fs-perf-scale__bar"></span><span class="fs-perf-scale__indicator"></span></span>';
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

function fs_developer_perf_show_nginx_purge_in_admin_bar(): bool
{
	if (!function_exists('fs_config')) {
		return true;
	}
	$enabled = fs_config('nginx_site_cache.enabled');
	if ($enabled !== null) {
		return (bool) $enabled;
	}
	// Backward compat with old flat config key.
	$legacy = fs_config('nginx_cache_purge_show_in_admin_bar');
	return $legacy === null ? true : (bool) $legacy;
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

/**
 * Handle page-cache purge action.
 * Endpoint: /wp-admin/admin-post.php?action=fs_purge_cache&_wpnonce=...
 */
add_action('admin_post_fs_purge_cache', function (): void {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized');
	}
	check_admin_referer('fs_purge_cache');

	$ok = true;

	// Purge cache via shell script.
	if (function_exists('exec')) {
		$code = 1;
		exec('sudo /usr/local/bin/purge-nginx-cache.sh 2>&1', $out, $code);
		$ok = $ok && $code === 0;
	} else {
		$ok = false;
	}

	// Optional object-cache flush.
	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
	}

	$uid = (int) get_current_user_id();
	if ($uid > 0) {
		fs_admin_notice(
			$uid,
			$ok ? 'success' : 'error',
			$ok
				? __('Cache purged.', 'fromscratch')
				: __('Cache purge finished with issues.', 'fromscratch')
		);
	}

	wp_safe_redirect(wp_get_referer() ?: admin_url());
	exit;
}, 1);

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
			? fs_developer_perf_scale_html((float) $value, $metric, ['compact' => true, 'unit' => $unit, 'aria_label_metric' => $aria])
			: '';
	};
	$admin_bar_row = function (string $label, string $value_text, float $value, string $metric, string $unit = '') use ($scale): string {
		return '<span class="fs-perf-adminbar-row"><span class="fs-perf-adminbar-row__label">' . esc_html($label) . ': ' . esc_html($value_text) . '</span>' . $scale($value, $metric, $unit, $label, false) . '</span>';
	};

	$perf_icon = '<svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 100%;" width="24px" height="24px" viewBox="0 -960 960 960" fill="currentColor"><path d="M245-474q26-66 62.5-127T390-716l-52-11q-20-4-39 2t-33 20L140-579q-15 15-11.5 36t23.5 29l93 40Zm588-390q-106-5-201.5 41T461-702q-48 48-84.5 104T313-480q-5 13-5 26.5t10 23.5l125 125q10 10 23.5 10t26.5-5q62-27 118-63.5T715-448q75-75 121-170.5T877-820q0-8-4-16t-10-14q-6-6-14-10t-16-4ZM556-622.5q0-33.5 23-56.5t56.5-23q33.5 0 56.5 23t23 56.5q0 33.5-23 56.5t-56.5 23q-33.5 0-56.5-23t-23-56.5ZM487-232l40 93q8 20 29 24t36-11l126-126q14-14 20-33.5t2-39.5l-10-52q-55 46-115.5 82.5T487-232Zm-325-86q35-35 85-35.5t85 34.5q35 35 35 85t-35 85q-48 48-113.5 57T87-74q9-66 18.5-131.5T162-318Z"/></svg>';
	if ($show_perf) {
		// Purge cache (top-level): add before performance node so it appears first in the bar.
		if (current_user_can('manage_options') && fs_developer_perf_show_nginx_purge_in_admin_bar()) {
			$wp_purge_url = wp_nonce_url(admin_url('admin-post.php?action=fs_purge_cache'), 'fs_purge_cache');
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
			'id'     => 'fs_wp_perf_time',
			'title'  => (function () use ($perf, $admin_bar_row): string {
				$t = fs_developer_perf_format_time((float) $perf['time']);
				return $admin_bar_row(__('Execution time', 'fromscratch'), (string) $t['value'] . (string) $t['unit'], (float) $perf['time'], 'time', 's');
			})(),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_memory',
			'title'  => $admin_bar_row(__('Peak memory', 'fromscratch'), (string) $perf['memory'] . ' MB', (float) $perf['memory'], 'memory', ' MB'),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_queries',
			'title'  => $admin_bar_row(__('DB queries', 'fromscratch'), (string) $perf['queries'], (float) $perf['queries'], 'queries', ''),
		]);

		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_hooks',
			'title'  => $admin_bar_row(__('Hooks fired', 'fromscratch'), (string) $perf['hooks'], (float) $perf['hooks'], 'hooks', ''),
		]);
	}
}, 999);

/**
 * Render the floating performance panel (fixed bottom-left). Only for logged-out visitors on allowed IPs — never when a user is logged in.
 */
function fs_developer_perf_pinned_panel_render(): void
{
	if (is_user_logged_in()) {
		return;
	}
	if (!function_exists('fs_developer_perf_panel_guest_visible') || !fs_developer_perf_panel_guest_visible()) {
		return;
	}
	$perf = fs_developer_perf_metrics();
	$panel_css = '
		.fs-perf-pinned-panel { --fs-perf-s1: 8%; --fs-perf-s2: 20%; --fs-perf-s3: 40%; --fs-perf-s4: 80%; }
		.fs-perf-pinned-panel { display: none; position: fixed; bottom: 12px; left: 12px; z-index: 999999; max-width: calc(100vw - 24px); background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,.15); font-size: 12px; line-height: 1.4; overflow: hidden; }
		.fs-perf-pinned-panel__header { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid #c3c4c7; background: #f0f0f1; }
		.fs-perf-pinned-panel__title { font-weight: 600; font-size: 13px; }
		.fs-perf-pinned-panel__minimize { font-size: 11px; color: #2271b1; cursor: pointer; }
		.fs-perf-pinned-panel__minimize:hover { color: #1c548a; }
		.fs-perf-pinned-panel__body { padding: 8px; }
		.fs-perf-pinned-panel__table { border-collapse: collapse; margin: 0; table-layout: fixed; }
		.fs-perf-pinned-panel__table th,
		.fs-perf-pinned-panel__table td { min-width: 75px; padding: 6px 16px 6px 0; vertical-align: middle; border-bottom: 1px solid #dcdcde; }
		.fs-perf-pinned-panel__table th:last-child,
		.fs-perf-pinned-panel__table td:last-child { padding-right: 0; }
		.fs-perf-pinned-panel__table tbody tr:last-child th,
		.fs-perf-pinned-panel__table tbody tr:last-child td { border-bottom: 0; }
		.fs-perf-pinned-panel__table thead th { font-weight: 600; text-align: left; font-size: 11px; }
		.fs-perf-pinned-panel__table thead th.fs-perf-pinned-panel__th-avg { white-space: nowrap; }
		.fs-perf-pinned-panel__avg-header { display: flex; align-items: center; gap: 6px; }
		.fs-perf-pinned-panel__pages { color: #646970; font-weight: normal; white-space: nowrap; }
		.fs-perf-pinned-panel__table tbody th { font-weight: normal; color: #646970; text-align: left; }
		.fs-perf-pinned-panel__table td.fs-perf-pinned-panel__avg-cell { white-space: nowrap; }
		.fs-perf-pinned-panel__table td.fs-perf-pinned-panel__scale-cell { vertical-align: middle; text-align: right; }
		.fs-perf-pinned-panel__table .fs-perf-clear-history { margin-left: auto; cursor: pointer; color: #2271b1; font-weight: normal; }
		.fs-perf-pinned-panel__table .fs-perf-clear-history:hover { color: #1c548a; }
		.fs-perf-pinned-panel .fs-perf-pinned-panel__scale-cell .fs-perf-scale { display: inline-block; margin-left: 0; vertical-align: middle; line-height: 1; }
		.fs-perf-pinned-panel .fs-perf-scale__inner { display: inline-flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
		.fs-perf-pinned-panel .fs-perf-scale__label { order: 1; font-weight: 600; font-size: 11px; white-space: nowrap; }
		.fs-perf-pinned-panel .fs-perf-scale__bar-wrap { order: 2; position: relative; display: inline-block; width: 64px; height: 10px; }
		.fs-perf-pinned-panel .fs-perf-scale__bar { position: absolute; inset: 0; height: 6px; top: 2px; border-radius: 3px; background: linear-gradient(to right, #22c55e 0%, #22c55e var(--fs-perf-s1), #84cc16 var(--fs-perf-s1), #84cc16 var(--fs-perf-s2), #f97316 var(--fs-perf-s2), #f97316 var(--fs-perf-s3), #ef4444 var(--fs-perf-s3), #ef4444 var(--fs-perf-s4), #b91c1c var(--fs-perf-s4), #b91c1c 100%); }
		.fs-perf-pinned-panel .fs-perf-scale__indicator { position: absolute; top: 0; bottom: 0; left: calc(var(--fs-perf-pct, 0) * 1%); transform: translateX(-50%); width: 2px; border-radius: 2px; pointer-events: none; background: #fff; border: 1px solid #000; box-sizing: content-box; }
		.fs-perf-pinned-panel__tab { display: none; padding: 6px 12px; background: #f0f0f1; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
	';
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
	<div id="fs-perf-pinned-panel" class="fs-perf-pinned-panel" <?= $perf_data_attr ?>>
		<script type="application/json" id="fs-perf-scale-config">
			<?= wp_json_encode($scale_config) ?>
		</script>
		<style>
			<?= $panel_css ?>
		</style>
		<div class="fs-perf-pinned-panel__content">
			<div class="fs-perf-pinned-panel__header">
				<div class="fs-perf-pinned-panel__title"><?= esc_html__('Performance', 'fromscratch') ?></div>
				<div class="fs-perf-pinned-panel__minimize" data-fs-perf-minimize>
					<?= esc_html__('Minimize', 'fromscratch') ?>
				</div>
			</div>
			<div class="fs-perf-pinned-panel__body">
				<?php $t = fs_developer_perf_format_time((float) $perf['time']); ?>
				<table class="fs-perf-pinned-panel__table widefat striped" role="presentation">
					<thead>
						<tr>
							<th scope="col"></th>
							<th scope="col"><?= esc_html__('Current', 'fromscratch') ?></th>
							<th scope="col" colspan="2" class="fs-perf-pinned-panel__th-avg">
								<div class="fs-perf-pinned-panel__avg-header">
									<span class="fs-perf-pinned-panel__avg-title"><?= esc_html__('Average', 'fromscratch') ?></span>
									<span id="fs-perf-pages-caption" class="fs-perf-pinned-panel__pages"></span>
									<div class="fs-perf-clear-history" data-fs-perf-clear-history><?= esc_html(_x('Clear', 'performance panel history', 'fromscratch')) ?></div>
								</div>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<th scope="row"><?= esc_html__('Execution time', 'fromscratch') ?></th>
							<td><strong><?= esc_html($t['value']) ?> <?= esc_html($t['unit']) ?></strong></td>
							<td id="fs-perf-avg-time" class="fs-perf-pinned-panel__avg-cell">–</td>
							<td id="fs-perf-avg-scale-time" class="fs-perf-pinned-panel__scale-cell">–</td>
						</tr>
						<tr>
							<th scope="row"><?= esc_html__('Peak memory', 'fromscratch') ?></th>
							<td><strong><?= esc_html((string) $perf['memory']) ?> MB</strong></td>
							<td id="fs-perf-avg-memory" class="fs-perf-pinned-panel__avg-cell">–</td>
							<td id="fs-perf-avg-scale-memory" class="fs-perf-pinned-panel__scale-cell">–</td>
						</tr>
						<tr>
							<th scope="row"><?= esc_html__('DB queries', 'fromscratch') ?></th>
							<td><strong><?= esc_html((string) $perf['queries']) ?></strong></td>
							<td id="fs-perf-avg-queries" class="fs-perf-pinned-panel__avg-cell">–</td>
							<td id="fs-perf-avg-scale-queries" class="fs-perf-pinned-panel__scale-cell">–</td>
						</tr>
						<tr>
							<th scope="row"><?= esc_html__('Hooks fired', 'fromscratch') ?></th>
							<td><strong><?= esc_html((string) $perf['hooks']) ?></strong></td>
							<td id="fs-perf-avg-hooks" class="fs-perf-pinned-panel__avg-cell">–</td>
							<td id="fs-perf-avg-scale-hooks" class="fs-perf-pinned-panel__scale-cell">–</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<div class="fs-perf-pinned-panel__tab"><?= esc_html__('Performance', 'fromscratch') ?></div>
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

			var captionEl = document.getElementById('fs-perf-pages-caption');
			var avgTimeEl = document.getElementById('fs-perf-avg-time');
			var avgMemoryEl = document.getElementById('fs-perf-avg-memory');
			var avgQueriesEl = document.getElementById('fs-perf-avg-queries');
			var avgHooksEl = document.getElementById('fs-perf-avg-hooks');
			var avgScaleTimeEl = document.getElementById('fs-perf-avg-scale-time');
			var avgScaleMemoryEl = document.getElementById('fs-perf-avg-scale-memory');
			var avgScaleQueriesEl = document.getElementById('fs-perf-avg-scale-queries');
			var avgScaleHooksEl = document.getElementById('fs-perf-avg-scale-hooks');
			var clearBtn = panel.querySelector('.fs-perf-clear-history');

			if (config) {
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
					return '<span class="fs-perf-scale" style="--fs-perf-pct:' + pct + ';--fs-perf-s1:' + s1 + ';--fs-perf-s2:' + s2 + ';--fs-perf-s3:' + s3 + ';--fs-perf-s4:' + s4 + '">' +
						'<span class="fs-perf-scale__inner">' +
						'<span class="fs-perf-scale__bar-wrap">' +
						'<span class="fs-perf-scale__bar"></span>' +
						'<span class="fs-perf-scale__indicator"></span></span>' +
						'<span class="fs-perf-scale__label">' + label + '</span></span></span>';
				}

				function renderAverageSection(data) {
					var count = Array.isArray(data) ? data.length : 0;
					var i18n = config.i18n || {};
					var pagesLabel = count === 1 ? (i18n.pages_one || '1 page') : (i18n.pages_many || '%s pages').replace('%s', String(count));
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

					if (captionEl) {
						captionEl.textContent = count > 0 ? pagesLabel : '';
					}
					if (clearBtn) {
						clearBtn.style.display = count > 0 ? 'inline-block' : 'none';
					}

					if (!count || !avg) {
						var dash = '&mdash;';
						if (avgTimeEl) avgTimeEl.innerHTML = dash;
						if (avgMemoryEl) avgMemoryEl.innerHTML = dash;
						if (avgQueriesEl) avgQueriesEl.innerHTML = dash;
						if (avgHooksEl) avgHooksEl.innerHTML = dash;
						if (avgScaleTimeEl) avgScaleTimeEl.innerHTML = dash;
						if (avgScaleMemoryEl) avgScaleMemoryEl.innerHTML = dash;
						if (avgScaleQueriesEl) avgScaleQueriesEl.innerHTML = dash;
						if (avgScaleHooksEl) avgScaleHooksEl.innerHTML = dash;
						return;
					}

					if (avgTimeEl) {
						avgTimeEl.innerHTML = '<strong>' + (avg.time < 1 ? Math.round(avg.time * 1000) + ' ms' : avg.time.toFixed(3) + ' s') + '</strong>';
					}
					if (avgScaleTimeEl) {
						avgScaleTimeEl.innerHTML = scaleHtml(avg.time, 'time');
					}
					if (avgMemoryEl) {
						avgMemoryEl.innerHTML = '<strong>' + avg.memory + ' MB</strong>';
					}
					if (avgScaleMemoryEl) {
						avgScaleMemoryEl.innerHTML = scaleHtml(avg.memory, 'memory');
					}
					if (avgQueriesEl) {
						avgQueriesEl.innerHTML = '<strong>' + Math.round(avg.queries) + '</strong>';
					}
					if (avgScaleQueriesEl) {
						avgScaleQueriesEl.innerHTML = scaleHtml(avg.queries, 'queries');
					}
					if (avgHooksEl) {
						avgHooksEl.innerHTML = '<strong>' + Math.round(avg.hooks) + '</strong>';
					}
					if (avgScaleHooksEl) {
						avgScaleHooksEl.innerHTML = scaleHtml(avg.hooks, 'hooks');
					}
				}

				if (clearBtn) {
					clearBtn.addEventListener('click', function() {
						try {
							localStorage.setItem(HISTORY_KEY, '[]');
						} catch (e) {}
						renderAverageSection([]);
					});
				}
				renderAverageSection(history);
			}

			var content = panel.querySelector('.fs-perf-pinned-panel__content');
			var tab = panel.querySelector('.fs-perf-pinned-panel__tab');
			var MINIMIZED_KEY = 'fs_perf_minimized';

			function show() {
				panel.style.display = 'block';
			}

			function setExpanded(expanded) {
				if (content) content.style.display = expanded ? 'block' : 'none';
				if (tab) tab.style.display = expanded ? 'none' : 'block';
				try {
					sessionStorage.setItem(MINIMIZED_KEY, expanded ? '0' : '1');
				} catch (e) {}
			}

			show();
			var startMinimized = false;
			try {
				startMinimized = sessionStorage.getItem(MINIMIZED_KEY) === '1';
			} catch (e) {}
			setExpanded(!startMinimized);
			var minBtn = panel.querySelector('[data-fs-perf-minimize]');
			if (minBtn) minBtn.addEventListener('click', function() {
				setExpanded(false);
			});
			if (tab) tab.addEventListener('click', function() {
				setExpanded(true);
			});
		})();
	</script>
<?php
}

add_action('wp_footer', 'fs_developer_perf_pinned_panel_render', 20);
