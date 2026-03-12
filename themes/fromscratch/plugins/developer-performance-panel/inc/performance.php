<?php

defined('ABSPATH') || exit;

// Context: plugin (standalone) or theme (when file is copied back). Define before require: FS_PERF_AS_PLUGIN, FS_PERF_SETTINGS_PAGE.
if (!defined('FS_PERF_AS_PLUGIN')) {
	define('FS_PERF_AS_PLUGIN', false);
}
if (!defined('FS_PERF_SETTINGS_PAGE')) {
	define('FS_PERF_SETTINGS_PAGE', 'fs-developer');
}

/** Text domain: plugin uses its own, theme uses fromscratch. */
function fs_perf_text_domain(): string
{
	return FS_PERF_AS_PLUGIN ? 'fs-performance-panel' : 'fromscratch';
}

/** Who can see the panel and settings: in plugin = manage_options; in theme = developer user. */
function fs_perf_user_can_see(): bool
{
	if (FS_PERF_AS_PLUGIN) {
		return current_user_can('manage_options');
	}
	return function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
}

/** Settings page URL (plugin or Developer). */
function fs_perf_settings_url(array $args = []): string
{
	$url = admin_url('options-general.php?page=' . FS_PERF_SETTINGS_PAGE);
	if (!empty($args)) {
		$url = add_query_arg($args, $url);
	}
	return $url;
}

/** Plugin-only: used by generated db.php when theme does not define fs_is_developer_user. Never define fs_is_developer_user here to avoid redeclare with theme. */
if (FS_PERF_AS_PLUGIN && !function_exists('fs_perf_is_developer_user')) {
	function fs_perf_is_developer_user(int $user_id): bool
	{
		return user_can($user_id, 'manage_options');
	}
}

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
		__('Excellent', fs_perf_text_domain()),
		__('Good', fs_perf_text_domain()),
		__('Acceptable', fs_perf_text_domain()),
		__('Heavy', fs_perf_text_domain()),
		__('Problematic', fs_perf_text_domain()),
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
		'score'   => ['boundaries' => [20, 50, 100, 200, 200 + 20]],
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
 * Current request performance metrics. Use in Developer → General, admin bar, and pinned panel.
 *
 * @return array{time: float, memory: float, queries: int, hooks: int, score: float}
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
		'score'   => round((float) $time * $queries, 1),
	];
	return $snapshot;
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
	return $labels[4] ?? __('Problematic', fs_perf_text_domain());
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
		? sprintf(/* translators: 1: metric name, 2: value, 3: band label */ __('%1$s %2$s: %3$s', fs_perf_text_domain()), $aria_metric, $value . $unit, $label)
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
 * Whether the current user may see or trigger expensive-query recording (developer or guest at allowed IP).
 */
function fs_developer_perf_may_see_expensive_queries(): bool
{
	if (is_user_logged_in() && fs_perf_user_can_see()) {
		return true;
	}
	return function_exists('fs_developer_perf_panel_guest_visible') && fs_developer_perf_panel_guest_visible();
}

/**
 * Whether the slow-query logger (wp-content/db.php written by this module) is active.
 */
function fs_developer_perf_slow_queries_dropin_active(): bool
{
	return defined('FS_DB_QUERY_LOGGER_ACTIVE') && FS_DB_QUERY_LOGGER_ACTIVE;
}

/**
 * Get last recorded slow-query run (recorded_at, request_uri, queries, optional message).
 *
 * @return array{recorded_at?: string, request_uri?: string, queries?: array, message?: string}|null
 */
function fs_developer_perf_slow_queries_get(): ?array
{
	$raw = get_option('fs_perf_slow_queries', null);
	if (!is_array($raw)) {
		return null;
	}
	return $raw;
}

/** Whether the expensive-query logging feature is enabled (Developer settings checkbox). Records when enabled and user is dev or allowed IP. */
function fs_developer_perf_slow_queries_enabled(): bool
{
	return get_option('fromscratch_perf_slow_queries_enabled', '0') === '1';
}

/** Count of slow queries from the last recorded run (for "Expensive queries (N)" label). */
function fs_developer_perf_slow_queries_count(): int
{
	$data = fs_developer_perf_slow_queries_get();
	if ($data === null || !isset($data['queries']) || !is_array($data['queries'])) {
		return 0;
	}
	return count($data['queries']);
}

/**
 * Classify a single query by table/type for grouping (posts, postmeta, terms, options, other).
 */
function fs_developer_perf_slow_queries_classify_query(string $sql): string
{
	$sql = strtolower($sql);
	if (strpos($sql, 'postmeta') !== false) {
		return 'postmeta';
	}
	if (strpos($sql, 'options') !== false) {
		return 'options';
	}
	if (strpos($sql, 'term_') !== false || strpos($sql, 'terms') !== false) {
		return 'terms';
	}
	if (strpos($sql, 'posts') !== false) {
		return 'posts';
	}
	return 'other';
}

/**
 * Group recorded queries by type (posts, postmeta, terms, options, other). Input: list of { query, time?, source? }.
 *
 * @return array<string, int>
 */
function fs_developer_perf_slow_queries_group_by_type(array $queries): array
{
	$groups = ['posts' => 0, 'postmeta' => 0, 'terms' => 0, 'options' => 0, 'other' => 0];
	foreach ($queries as $row) {
		$sql = isset($row['query']) ? (string) $row['query'] : '';
		$type = fs_developer_perf_slow_queries_classify_query($sql);
		if (!isset($groups[$type])) {
			$groups[$type] = 0;
		}
		$groups[$type]++;
	}
	return $groups;
}

/**
 * Top N slowest queries by time. Input: list of { query, time, source? }.
 *
 * @return array<int, array{query: string, time: float, source: string}>
 */
function fs_developer_perf_slow_queries_top_n(array $queries, int $n = 5): array
{
	$with_time = [];
	foreach ($queries as $row) {
		$time = isset($row['time']) ? (float) $row['time'] : 0.0;
		$with_time[] = [
			'query'  => isset($row['query']) ? (string) $row['query'] : '',
			'time'   => $time,
			'source' => isset($row['source']) ? (string) $row['source'] : '—',
		];
	}
	usort($with_time, static function ($a, $b) {
		return $b['time'] <=> $a['time'];
	});
	return array_slice($with_time, 0, $n);
}

/**
 * Render the full "Last recorded" slow-queries block (groups, top 5, table). All display logic in one place.
 *
 * @param array{recorded_at?: string, request_uri?: string, queries?: array, message?: string}|null $slow_data
 */
function fs_developer_perf_slow_queries_render_list(?array $slow_data): string
{
	if ($slow_data === null) {
		return '';
	}
	$request_uri = isset($slow_data['request_uri']) && $slow_data['request_uri'] !== '' ? $slow_data['request_uri'] : '—';
	$recorded_at = $slow_data['recorded_at'] ?? '';
	$queries = isset($slow_data['queries']) && is_array($slow_data['queries']) ? $slow_data['queries'] : [];
	$message = $slow_data['message'] ?? '';

	$out = '<div class="fs-slow-queries-list" style="margin-top: 16px;">';
	$out .= '<h4 class="title">' . esc_html__('Last recorded', fs_perf_text_domain()) . '</h4>';
	$out .= '<p class="description">';
	$out .= esc_html(sprintf(__('Page: %s', fs_perf_text_domain()), $request_uri));
	if ($recorded_at !== '') {
		$out .= ' · ' . esc_html(sprintf(__('Recorded at %s', fs_perf_text_domain()), $recorded_at));
	}
	$out .= '</p>';

	if ($message === 'no_slow_queries' && count($queries) === 0) {
		$threshold_sec = fs_developer_perf_slow_queries_threshold();
		$threshold_ms = round($threshold_sec * 1000, 2);
		$out .= '<p>' . esc_html(sprintf(
			__('No queries over the threshold (%s ms) were recorded for that page load.', fs_perf_text_domain()),
			$threshold_ms
		)) . '</p>';
		$out .= '<p class="description">' . esc_html__('Tip: Re-save the settings above to reinstall the recorder (db.php), then load any page while logged in as a developer.', fs_perf_text_domain()) . '</p>';
	} elseif (count($queries) > 0) {
		$groups = fs_developer_perf_slow_queries_group_by_type($queries);
		$group_labels = [
			'posts'    => __('posts', fs_perf_text_domain()),
			'postmeta' => __('postmeta', fs_perf_text_domain()),
			'terms'    => __('terms', fs_perf_text_domain()),
			'options'  => __('options', fs_perf_text_domain()),
			'other'    => __('other', fs_perf_text_domain()),
		];
		$out .= '<p class="description" style="margin-top: 8px;"><strong>' . esc_html__('Query groups:', fs_perf_text_domain()) . '</strong> ';
		$parts = [];
		foreach ($groups as $type => $count) {
			if ($count > 0) {
				$label = $group_labels[$type] ?? $type;
				$parts[] = $label . ': ' . $count;
			}
		}
		$out .= esc_html(implode(' · ', $parts));
		$out .= '</p>';

		$top5 = fs_developer_perf_slow_queries_top_n($queries, 5);
		if (count($top5) > 0) {
			$out .= '<p class="description" style="margin-top: 6px;"><strong>' . esc_html__('Top 5 slowest:', fs_perf_text_domain()) . '</strong></p>';
			$out .= '<ol style="margin: 4px 0 12px 0; padding-left: 20px;">';
			foreach ($top5 as $row) {
				$ms = round($row['time'] * 1000);
				$q_short = strlen($row['query']) > 80 ? substr($row['query'], 0, 80) . '…' : $row['query'];
				$out .= '<li style="margin-bottom: 2px;">' . (int) $ms . 'ms ' . esc_html($q_short) . '</li>';
			}
			$out .= '</ol>';
		}

		$out .= '<table class="widefat striped" style="margin-top: 8px;"><thead><tr>';
		$out .= '<th style="width: 80px;">' . esc_html__('Time', fs_perf_text_domain()) . '</th>';
		$out .= '<th style="width: 140px;">' . esc_html__('Source', fs_perf_text_domain()) . '</th>';
		$out .= '<th>' . esc_html__('Query', fs_perf_text_domain()) . '</th></tr></thead><tbody>';
		foreach ($queries as $row) {
			$q = isset($row['query']) ? $row['query'] : '';
			$time = isset($row['time']) ? (float) $row['time'] : 0;
			$source = isset($row['source']) ? $row['source'] : '—';
			$q_short = strlen($q) > 200 ? substr($q, 0, 200) . '…' : $q;
			$out .= '<tr><td><strong>' . esc_html(sprintf('%.3fs', $time)) . '</strong></td>';
			$out .= '<td><code>' . esc_html($source) . '</code></td>';
			$out .= '<td style="word-break: break-all;"><code title="' . esc_attr($q) . '">' . esc_html($q_short) . '</code></td></tr>';
		}
		$out .= '</tbody></table>';
	} else {
		$out .= '<p>' . esc_html__('No recorded data.', fs_perf_text_domain()) . '</p>';
		$out .= '<p class="description">' . esc_html__('Enable the feature above, save (to install db.php), then load another page while logged in as a developer.', fs_perf_text_domain()) . '</p>';
	}

	$out .= '<form method="post" action="' . esc_url(fs_perf_settings_url(['fs_slow_queries' => '1'])) . '" style="margin-top: 12px;">';
	$out .= wp_nonce_field('fromscratch_clear_slow_queries', '_wpnonce', true, false);
	$out .= '<input type="hidden" name="fromscratch_clear_slow_queries" value="1">';
	$out .= '<button type="submit" class="button button-secondary">' . esc_html__('Clear', fs_perf_text_domain()) . '</button>';
	$out .= '</form>';
	$out .= '</div>';
	return $out;
}

/**
 * Render the full performance settings block (metrics, admin bar, guest IP, expensive query log).
 * Used by the plugin settings page; theme can use the same when embedding in Developer.
 */
function fs_perf_render_settings_page(): void
{
	$perf_admin_bar_saved = get_transient('fromscratch_perf_admin_bar_saved');
	if ($perf_admin_bar_saved) {
		delete_transient('fromscratch_perf_admin_bar_saved');
	}
	$perf = function_exists('fs_developer_perf_metrics') ? fs_developer_perf_metrics() : ['time' => 0, 'memory' => 0, 'queries' => 0, 'hooks' => 0, 'score' => 0];
	$scale_html = function ($value, $metric, $unit = '', $aria_name = '') {
		return function_exists('fs_developer_perf_scale_html')
			? fs_developer_perf_scale_html((float) $value, $metric, [
				'compact' => true,
				'show_min_max' => true,
				'unit' => $unit,
				'aria_label_metric' => $aria_name,
			])
			: '';
	};
	$td = fs_perf_text_domain();
	?>
	<?php if ($perf_admin_bar_saved) : ?>
		<div class="notice notice-success is-dismissible"><p><?= esc_html__('Settings saved.', $td) ?></p></div>
	<?php endif; ?>
	<div class="page-settings-form" style="margin-bottom: 24px;">
		<h2 class="title"><?= esc_html__('Performance', $td) ?></h2>
		<p class="description"><?= esc_html__('Current request metrics (this page load).', $td) ?></p>
		<table class="widefat striped fs-perf-table" style="width: auto; margin: 16px 0 12px;">
			<tbody>
				<tr>
					<td><?= esc_html__('Execution time', $td) ?></td>
					<td><strong><?= esc_html((string) $perf['time']) ?>s</strong></td>
					<td><?= $scale_html($perf['time'], 'time', 's', __('Execution time', $td)) ?></td>
				</tr>
				<tr>
					<td><?= esc_html__('Peak memory', $td) ?></td>
					<td><strong><?= esc_html((string) $perf['memory']) ?> MB</strong></td>
					<td><?= $scale_html($perf['memory'], 'memory', ' MB', __('Peak memory', $td)) ?></td>
				</tr>
				<tr>
					<td><?= esc_html__('DB queries', $td) ?></td>
					<td><strong><?= esc_html((string) $perf['queries']) ?></strong></td>
					<td><?= $scale_html($perf['queries'], 'queries', '', __('DB queries', $td)) ?></td>
				</tr>
				<tr>
					<td><?= esc_html__('Hooks fired', $td) ?></td>
					<td><strong><?= esc_html((string) $perf['hooks']) ?></strong></td>
					<td><?= $scale_html($perf['hooks'], 'hooks', '', __('Hooks fired', $td)) ?></td>
				</tr>
				<tr>
					<td><?= esc_html__('Score (time × queries)', $td) ?></td>
					<td><strong><?= esc_html((string) $perf['score']) ?></strong></td>
					<td><?= $scale_html($perf['score'], 'score', '', __('Score', $td)) ?></td>
				</tr>
			</tbody>
		</table>
		<form method="post" action="">
			<?php wp_nonce_field('fromscratch_perf_admin_bar'); ?>
			<input type="hidden" name="fromscratch_save_perf_admin_bar" value="1">
			<label>
				<input type="hidden" name="fromscratch_perf_admin_bar" value="0">
				<input type="checkbox" name="fromscratch_perf_admin_bar" value="1" <?= checked(get_option('fromscratch_perf_admin_bar', '1'), '1', false) ?>>
				<?= esc_html__('Show performance in admin bar', $td) ?>
			</label>
			<button type="submit" class="button button-small" style="margin-left: 8px;"><?= esc_html__('Save', $td) ?></button>
		</form>

		<h3 class="title" style="margin-top: 20px;"><?= esc_html__('Performance panel for guests (by IP)', $td) ?></h3>
		<p class="description"><?= esc_html__('Show the sticky performance panel to visitors who are not logged in, only for the IP addresses listed below.', $td) ?></p>
		<?php
		$current_ip = function_exists('fs_developer_perf_current_ip') ? fs_developer_perf_current_ip() : '';
		$guest_ips = get_option('fromscratch_perf_panel_guest_ips', '');
		?>
		<form method="post" action="" style="margin-top: 8px;">
			<?php wp_nonce_field('fromscratch_perf_guest'); ?>
			<input type="hidden" name="fromscratch_save_perf_guest" value="1">
			<table class="form-table" role="presentation" style="max-width: 480px;">
				<tr>
					<th scope="row"><?= esc_html__('Your current IP', $td) ?></th>
					<td><code id="fs-perf-current-ip"><?= $current_ip !== '' ? esc_html($current_ip) : esc_html__('—', $td) ?></code></td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_perf_panel_guest"><?= esc_html__('Enable for guests', $td) ?></label></th>
					<td>
						<label>
							<input type="hidden" name="fromscratch_perf_panel_guest" value="0">
							<input type="checkbox" name="fromscratch_perf_panel_guest" id="fromscratch_perf_panel_guest" value="1" <?= checked(get_option('fromscratch_perf_panel_guest', '0'), '1', false) ?>>
							<?= esc_html__('Show performance panel to guests at allowed IPs', $td) ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_perf_panel_guest_ips"><?= esc_html__('Allowed IP addresses', $td) ?></label></th>
					<td>
						<input type="text" name="fromscratch_perf_panel_guest_ips" id="fromscratch_perf_panel_guest_ips" value="<?= esc_attr($guest_ips) ?>" class="regular-text" placeholder="192.168.1.1, 10.0.0.1">
						<p class="description"><?= esc_html__('Comma-separated. Only these IPs will see the panel when not logged in.', $td) ?></p>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?= esc_html__('Save', $td) ?></button></p>
		</form>

		<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Expensive query log', $td) ?></h3>
		<?php
		$slow_queries_enabled = function_exists('fs_developer_perf_slow_queries_enabled') && fs_developer_perf_slow_queries_enabled();
		$show_slow_list = isset($_GET['fs_slow_queries']) && $_GET['fs_slow_queries'] === '1';
		$slow_data = function_exists('fs_developer_perf_slow_queries_get') ? fs_developer_perf_slow_queries_get() : null;
		$install_result = get_transient('fromscratch_perf_slow_queries_install_result');
		if ($install_result !== false) {
			delete_transient('fromscratch_perf_slow_queries_install_result');
		}
		$slow_threshold_sec = function_exists('fs_developer_perf_slow_queries_threshold') ? fs_developer_perf_slow_queries_threshold() : 0.05;
		$slow_threshold_ms = $slow_threshold_sec * 1000;
		?>
		<form method="post" action="" style="margin-top: 8px;">
			<?php wp_nonce_field('fromscratch_perf_slow_queries'); ?>
			<input type="hidden" name="fromscratch_save_perf_slow_queries" value="1">
			<p style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
				<label>
					<input type="hidden" name="fromscratch_perf_slow_queries_enabled" value="0">
					<input type="checkbox" name="fromscratch_perf_slow_queries_enabled" value="1" <?= checked($slow_queries_enabled, true, false) ?>>
					<?= esc_html__('Enable expensive query logging', $td) ?>
				</label>
				<button type="submit" class="button button-small" style="margin-left: 8px;"><?= esc_html__('Save', $td) ?></button>
			</p>
			<p style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
				<label for="fromscratch_perf_slow_queries_threshold"><?= esc_html__('Threshold (ms)', $td) ?></label>
				<input type="number" name="fromscratch_perf_slow_queries_threshold" id="fromscratch_perf_slow_queries_threshold" value="<?= esc_attr((string) $slow_threshold_ms) ?>" step="0.01" min="0" style="width: 120px;">
				<span class="description"><?= esc_html__('Queries slower than this are recorded. Use e.g. 1 for 1 ms.', $td) ?></span>
			</p>
		</form>
		<p class="description" style="margin-top: 4px;"><?= esc_html__('When enabled, queries above the threshold are recorded for requests where you are logged in as a developer or your IP is in the performance panel allowlist. No impact when disabled.', $td) ?></p>
		<?php if ($install_result === '0') : ?>
			<p class="description" style="margin-top: 8px; color: #d63638;"><?= esc_html__('Recorder could not be installed (wp-content may not be writable). Create wp-content/db.php manually or fix permissions.', $td) ?></p>
		<?php endif; ?>
		<?php if ($slow_queries_enabled) : ?>
			<p class="description" style="margin-top: 12px;"><?= esc_html__('Queries are recorded on each page load; view the list below.', $td) ?></p>
			<?php if ($slow_data !== null) : ?>
				<p style="margin-top: 8px;"><a href="<?= esc_url(fs_perf_settings_url(['fs_slow_queries' => '1'])) ?>" class="button button-secondary"><?= esc_html__('View last recorded slow queries', $td) ?></a></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ($show_slow_list && $slow_data !== null && function_exists('fs_developer_perf_slow_queries_render_list')) : ?>
			<?= fs_developer_perf_slow_queries_render_list($slow_data) ?>
		<?php endif; ?>
	</div>
	<?php
}

/** Option key for the db.php recorder; must match what the written db.php uses. */
function fs_developer_perf_slow_queries_enabled_option(): string
{
	return 'fromscratch_perf_slow_queries_enabled';
}

/** Option key for the threshold (seconds); db.php reads this at runtime. */
function fs_developer_perf_slow_queries_threshold_option(): string
{
	return 'fromscratch_perf_slow_queries_threshold';
}

/** Default and current threshold in seconds; queries slower than this are recorded. */
function fs_developer_perf_slow_queries_threshold(): float
{
	$v = get_option(fs_developer_perf_slow_queries_threshold_option(), '0.05');
	return max(0.0, (float) $v);
}

/** Marker in written db.php so we only remove our own file. */
function fs_developer_perf_slow_queries_db_php_marker(): string
{
	return 'FS_PERF_DB_LOGGER';
}

/**
 * PHP code for wp-content/db.php. Records slow queries when option is on; saves only for dev or allowed IP. No cookie.
 *
 * Why not define the class here and load it when the checkbox is set? WordPress creates $wpdb very early in
 * wp-settings.php (before plugins, before the theme). By the time the theme and the checkbox are available,
 * the first queries have already run. To wrap every query from the first one, the logger must be the file
 * WordPress loads at DB init time: wp-content/db.php. So we generate this code and write it there when the
 * feature is enabled; the class cannot live in the theme.
 */
function fs_developer_perf_slow_queries_db_php_content(): string
{
	$marker = fs_developer_perf_slow_queries_db_php_marker();
	$opt = fs_developer_perf_slow_queries_enabled_option();
	$thresh_opt = fs_developer_perf_slow_queries_threshold_option();
	return '<?php
// ' . $marker . ' - FromScratch performance query logger. Do not edit.
if (!defined(\'ABSPATH\')) return;
if (!class_exists(\'wpdb\')) return;
if (!defined(\'FS_DB_QUERY_LOGGER_ACTIVE\')) define(\'FS_DB_QUERY_LOGGER_ACTIVE\', true);
class FS_Wpdb_Query_Logger extends wpdb {
	private const MAX_STORED = 25;
	private const OPTION_NAME = \'fs_perf_slow_queries\';
	private const ENABLED_OPTION = \'' . addslashes($opt) . '\';
	private const THRESHOLD_OPTION = \'' . addslashes($thresh_opt) . '\';
	private static $recording = null;
	private static $threshold = null;
	private static $slow_queries = [];
	private static $request_uri = \'\';
	private static $shutdown_registered = false;
	public function query($query = \'\') {
		if ($query === \'\' || $query === null) return parent::query($query);
		if (self::$recording === null) {
			$this->read_options_once();
			if (self::$recording && self::$request_uri === \'\')
				self::$request_uri = isset($_SERVER[\'REQUEST_URI\']) ? sanitize_text_field(wp_unslash($_SERVER[\'REQUEST_URI\'])) : \'\';
		}
		if (!self::$recording || count(self::$slow_queries) >= self::MAX_STORED) return parent::query($query);
		$start = microtime(true);
		$result = parent::query($query);
		$elapsed = microtime(true) - $start;
		if (self::$threshold !== null && $elapsed >= self::$threshold) {
			$source = $this->get_query_source();
			self::$slow_queries[] = [\'query\' => is_string($query) ? $query : \'\', \'time\' => round($elapsed, 4), \'source\' => $source];
			if (!self::$shutdown_registered) { self::$shutdown_registered = true; add_action(\'shutdown\', [__CLASS__, \'save_and_clear\'], 0); }
		}
		return $result;
	}
	private function get_query_source(): string {
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
		$norm = function_exists(\'wp_normalize_path\') ? \'wp_normalize_path\' : static function ($p) { return str_replace(\'\\\\\', \'/\', (string)$p); };
		$content_dir = defined(\'WP_CONTENT_DIR\') ? $norm(WP_CONTENT_DIR) : \'\';
		$plugins_dir = $content_dir ? $content_dir . \'/plugins/\' : \'\';
		$themes_dir = $content_dir ? $content_dir . \'/themes/\' : \'\';
		$abspath = defined(\'ABSPATH\') ? $norm(ABSPATH) : \'\';
		$inc = $abspath ? $abspath . \'wp-includes/\' : \'\';
		foreach ($trace as $frame) {
			$file = isset($frame[\'file\']) ? $norm($frame[\'file\']) : \'\';
			if ($file === \'\') continue;
			if ($inc !== \'\' && strpos($file, $inc) === 0) continue;
			if ($plugins_dir !== \'\' && strpos($file, $plugins_dir) === 0) { $rel = substr($file, strlen($plugins_dir)); $parts = explode(\'/\', $rel, 2); return \'plugin:\' . $parts[0]; }
			if ($themes_dir !== \'\' && strpos($file, $themes_dir) === 0) { $rel = substr($file, strlen($themes_dir)); $parts = explode(\'/\', $rel, 2); return \'theme:\' . $parts[0]; }
			if ($abspath !== \'\' && strpos($file, $abspath) === 0) return \'core\';
			return basename($file) . (isset($frame[\'line\']) ? \':\' . $frame[\'line\'] : \'\');
		}
		return \'unknown\';
	}
	private function read_options_once(): void {
		$enabled = \'0\';
		$threshold = \'0.05\';
		$sql = "SELECT option_name, option_value FROM " . $this->prefix . "options WHERE option_name IN (\'" . self::ENABLED_OPTION . "\', \'" . self::THRESHOLD_OPTION . "\')";
		parent::query($sql);
		$rows = is_array($this->last_result) ? $this->last_result : [];
		foreach ($rows as $row) {
			$name = is_object($row) ? ($row->option_name ?? \'\') : ($row[\'option_name\'] ?? \'\');
			$val = is_object($row) ? ($row->option_value ?? \'\') : ($row[\'option_value\'] ?? \'\');
			if ($name === self::ENABLED_OPTION) $enabled = $val;
			if ($name === self::THRESHOLD_OPTION) $threshold = $val;
		}
		self::$recording = $enabled === \'1\';
		self::$threshold = max(0.0, (float) $threshold);
	}
	public static function save_and_clear(): void {
		$may_save = false;
		if (is_user_logged_in() && (function_exists(\'fs_is_developer_user\') ? fs_is_developer_user((int)get_current_user_id()) : (function_exists(\'fs_perf_is_developer_user\') && fs_perf_is_developer_user((int)get_current_user_id())))) $may_save = true;
		elseif (!is_user_logged_in() && get_option(\'fromscratch_perf_panel_guest\', \'0\') === \'1\') {
			$ip = isset($_SERVER[\'REMOTE_ADDR\']) ? sanitize_text_field(wp_unslash($_SERVER[\'REMOTE_ADDR\'])) : \'\';
			if ($ip !== \'\' && filter_var($ip, FILTER_VALIDATE_IP)) {
				$allowed = get_option(\'fromscratch_perf_panel_guest_ips\', \'\');
				$list = array_filter(array_map(\'trim\', explode(\',\', $allowed)));
				$list = array_filter($list, static function ($a) { return filter_var($a, FILTER_VALIDATE_IP) !== false; });
				if (in_array($ip, $list, true)) $may_save = true;
			}
		}
		if ($may_save) {
			$data = count(self::$slow_queries) === 0 ? [\'recorded_at\' => current_time(\'mysql\'), \'request_uri\' => self::$request_uri, \'queries\' => [], \'message\' => \'no_slow_queries\'] : [\'recorded_at\' => current_time(\'mysql\'), \'request_uri\' => self::$request_uri, \'queries\' => self::$slow_queries];
			update_option(self::OPTION_NAME, $data, false);
		}
	}
}
$wpdb = new FS_Wpdb_Query_Logger(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
';
}

/**
 * Install the query logger by writing wp-content/db.php. Call when enabling the feature.
 */
function fs_developer_perf_slow_queries_install_db_dropin(): bool
{
	$file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/db.php' : '';
	if ($file === '' || !is_writable(dirname($file))) {
		return false;
	}
	return (bool) file_put_contents($file, fs_developer_perf_slow_queries_db_php_content(), LOCK_EX);
}

/**
 * Uninstall the query logger by removing wp-content/db.php only if it is our file.
 */
function fs_developer_perf_slow_queries_uninstall_db_dropin(): bool
{
	$file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/db.php' : '';
	if ($file === '' || !is_file($file)) {
		return true;
	}
	$content = (string) @file_get_contents($file, false, null, 0, 512);
	$marker = fs_developer_perf_slow_queries_db_php_marker();
	if (strpos($content, $marker) === false) {
		return true; // Not our file, don't remove.
	}
	return @unlink($file);
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
	if (!fs_developer_perf_show_in_admin_bar() || !is_user_logged_in() || !fs_perf_user_can_see() || !is_admin_bar_showing()) {
		return;
	}
	wp_add_inline_style('admin-bar', fs_developer_perf_admin_bar_inline_css());
}, 20);

add_action('admin_enqueue_scripts', function (): void {
	if (!fs_developer_perf_show_in_admin_bar() || !fs_perf_user_can_see()) {
		return;
	}
	wp_add_inline_style('admin-bar', fs_developer_perf_admin_bar_inline_css());
}, 20);

/**
 * Show performance metrics in the admin bar for developer users (backend and frontend). Click to expand details with scale per metric.
 */
add_action('admin_bar_menu', function ($admin_bar): void {
	if (!fs_developer_perf_show_in_admin_bar()) {
		return;
	}
	if (!$admin_bar instanceof \WP_Admin_Bar || !is_user_logged_in()) {
		return;
	}
	if (!fs_perf_user_can_see()) {
		return;
	}
	$perf = fs_developer_perf_metrics();
	$scale = function ($value, $metric, $unit = '', $aria = '') {
		return function_exists('fs_developer_perf_scale_html')
			? fs_developer_perf_scale_html((float) $value, $metric, ['compact' => true, 'show_min_max' => true, 'unit' => $unit, 'aria_label_metric' => $aria])
			: '';
	};

	$perf_icon = '<svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 100%;" width="24px" height="24px" viewBox="0 -960 960 960" fill="currentColor"><path d="M245-474q26-66 62.5-127T390-716l-52-11q-20-4-39 2t-33 20L140-579q-15 15-11.5 36t23.5 29l93 40Zm588-390q-106-5-201.5 41T461-702q-48 48-84.5 104T313-480q-5 13-5 26.5t10 23.5l125 125q10 10 23.5 10t26.5-5q62-27 118-63.5T715-448q75-75 121-170.5T877-820q0-8-4-16t-10-14q-6-6-14-10t-16-4ZM556-622.5q0-33.5 23-56.5t56.5-23q33.5 0 56.5 23t23 56.5q0 33.5-23 56.5t-56.5 23q-33.5 0-56.5-23t-23-56.5ZM487-232l40 93q8 20 29 24t36-11l126-126q14-14 20-33.5t2-39.5l-10-52q-55 46-115.5 82.5T487-232Zm-325-86q35-35 85-35.5t85 34.5q35 35 35 85t-35 85q-48 48-113.5 57T87-74q9-66 18.5-131.5T162-318Z"/></svg>';
	$admin_bar->add_node([
		'id'    => 'fs_wp_perf',
		'title' => $perf_icon,
		'href'  => fs_perf_settings_url(),
		'meta'  => ['title' => __('WordPress resources (Developer settings)', fs_perf_text_domain())],
	]);
	$admin_bar->add_node([
		'parent' => 'fs_wp_perf',
		'id'     => 'fs_wp_perf_pin',
		'title'  => __('Pin to bottom-left', fs_perf_text_domain()),
		'href'   => '#',
		'meta'   => ['class' => 'fs-perf-pin-trigger', 'title' => __('Keep performance panel visible while navigating', fs_perf_text_domain())],
	]);
	$exp_queries_on = function_exists('fs_developer_perf_slow_queries_enabled') && fs_developer_perf_slow_queries_enabled();
	$exp_may_see = function_exists('fs_developer_perf_may_see_expensive_queries') && fs_developer_perf_may_see_expensive_queries();
	if ($exp_queries_on && $exp_may_see) {
		$count = function_exists('fs_developer_perf_slow_queries_count') ? fs_developer_perf_slow_queries_count() : 0;
		$title = $count > 0
			? sprintf(/* translators: %d = number of expensive queries */ __('Expensive queries (%d)', fs_perf_text_domain()), $count)
			: __('Expensive queries', fs_perf_text_domain());
		$admin_bar->add_node([
			'parent' => 'fs_wp_perf',
			'id'     => 'fs_wp_perf_slow_queries',
			'title'  => $title,
			'href'   => fs_perf_settings_url(['fs_slow_queries' => '1']),
			'meta'   => ['title' => __('View last recorded slow queries', fs_perf_text_domain())],
		]);
	}
	$admin_bar->add_node([
		'parent' => 'fs_wp_perf',
		'id'     => 'fs_wp_perf_time',
		'title'  => __('Execution time', fs_perf_text_domain()) . ': ' . esc_html((string) $perf['time']) . 's ' . $scale($perf['time'], 'time', 's', __('Execution time', fs_perf_text_domain())),
	]);
	$admin_bar->add_node([
		'parent' => 'fs_wp_perf',
		'id'     => 'fs_wp_perf_memory',
		'title'  => __('Peak memory', fs_perf_text_domain()) . ': ' . esc_html((string) $perf['memory']) . ' MB ' . $scale($perf['memory'], 'memory', ' MB', __('Peak memory', fs_perf_text_domain())),
	]);
	$admin_bar->add_node([
		'parent' => 'fs_wp_perf',
		'id'     => 'fs_wp_perf_queries',
		'title'  => __('DB queries', fs_perf_text_domain()) . ': ' . esc_html((string) $perf['queries']) . ' ' . $scale($perf['queries'], 'queries', '', __('DB queries', fs_perf_text_domain())),
	]);
	$admin_bar->add_node([
		'parent' => 'fs_wp_perf',
		'id'     => 'fs_wp_perf_hooks',
		'title'  => __('Hooks fired', fs_perf_text_domain()) . ': ' . esc_html((string) $perf['hooks']) . ' ' . $scale($perf['hooks'], 'hooks', '', __('Hooks fired', fs_perf_text_domain())),
	]);
	$admin_bar->add_node([
		'parent' => 'fs_wp_perf',
		'id'     => 'fs_wp_perf_score',
		'title'  => __('Score', fs_perf_text_domain()) . ': ' . esc_html((string) $perf['score']) . ' ' . $scale($perf['score'], 'score', '', __('Score', fs_perf_text_domain())),
		'meta'   => ['title' => __('Lower is better.', fs_perf_text_domain())],
	]);
}, 999);

/**
 * Render the pinned performance panel (fixed bottom-left). Shown when developer pinned (localStorage) or when guest by IP (always visible).
 */
function fs_developer_perf_pinned_panel_render(): void
{
	$is_guest = function_exists('fs_developer_perf_panel_guest_visible') && fs_developer_perf_panel_guest_visible();
	$is_developer = is_user_logged_in() && fs_perf_user_can_see() && fs_developer_perf_show_in_admin_bar();
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
		'score'   => fs_developer_perf_scale_config('score'),
		'labels'  => fs_developer_perf_band_labels(),
		'i18n'    => [
			'pages_one'       => __('1 page', fs_perf_text_domain()),
			'pages_many'      => __('%s pages', fs_perf_text_domain()),
			'average'         => __('Average', fs_perf_text_domain()),
			'clear'           => __('Clear', fs_perf_text_domain()),
			'no_data'         => __('No data yet.', fs_perf_text_domain()),
			'execution_time'  => __('Execution time', fs_perf_text_domain()),
			'peak_memory'     => __('Peak memory', fs_perf_text_domain()),
			'db_queries'      => __('DB queries', fs_perf_text_domain()),
			'hooks_fired'     => __('Hooks fired', fs_perf_text_domain()),
			'score'           => __('Score', fs_perf_text_domain()),
		],
	];
	$perf_data_attr = ' data-perf-time="' . esc_attr((string) $perf['time']) . '" data-perf-memory="' . esc_attr((string) $perf['memory']) . '" data-perf-queries="' . esc_attr((string) $perf['queries']) . '" data-perf-hooks="' . esc_attr((string) $perf['hooks']) . '" data-perf-score="' . esc_attr((string) $perf['score']) . '"';
	?>
	<div id="fs-perf-pinned-panel" class="fs-perf-pinned-panel" style="display: none; position: fixed; bottom: 12px; left: 12px; z-index: 999999; max-width: 320px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,.15); font-size: 12px; line-height: 1.4;"<?= $guest_attr ?><?= $perf_data_attr ?>>
		<script type="application/json" id="fs-perf-scale-config"><?= wp_json_encode($scale_config) ?></script>
		<style><?= $panel_css ?></style>
		<div class="fs-perf-pinned-panel__content">
			<div style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-bottom: 1px solid #c3c4c7; background: #f0f0f1;">
				<strong><?= esc_html__('Performance', fs_perf_text_domain()) ?></strong>
				<?php if ($is_guest) : ?>
					<div class="fs-perf-minimize" style="padding: 2px 8px; font-size: 11px; cursor: pointer;"><?= esc_html__('Minimize', fs_perf_text_domain()) ?></div>
				<?php else : ?>
					<div class="fs-perf-unpin" style="padding: 2px 8px; cursor: pointer;"><?= esc_html__('Unpin', fs_perf_text_domain()) ?></div>
				<?php endif; ?>
			</div>
			<div style="padding: 8px 10px;">
				<div style="margin-bottom: 4px;"><?= esc_html__('Execution time', fs_perf_text_domain()) ?>: <strong><?= esc_html((string) $perf['time']) ?>s</strong> <?= $scale($perf['time'], 'time', 's') ?></div>
				<div style="margin-bottom: 4px;"><?= esc_html__('Peak memory', fs_perf_text_domain()) ?>: <strong><?= esc_html((string) $perf['memory']) ?> MB</strong> <?= $scale($perf['memory'], 'memory', ' MB') ?></div>
				<div style="margin-bottom: 4px;"><?= esc_html__('DB queries', fs_perf_text_domain()) ?>: <strong><?= esc_html((string) $perf['queries']) ?></strong> <?= $scale($perf['queries'], 'queries', '') ?></div>
				<div style="margin-bottom: 4px;"><?= esc_html__('Hooks fired', fs_perf_text_domain()) ?>: <strong><?= esc_html((string) $perf['hooks']) ?></strong> <?= $scale($perf['hooks'], 'hooks', '') ?></div>
				<div style="margin-bottom: 8px;"><?= esc_html__('Score', fs_perf_text_domain()) ?>: <strong><?= esc_html((string) $perf['score']) ?></strong> <?= $scale($perf['score'], 'score', '') ?></div>
				<?php
				$exp_on = function_exists('fs_developer_perf_slow_queries_enabled') && fs_developer_perf_slow_queries_enabled();
				$exp_may_see = function_exists('fs_developer_perf_may_see_expensive_queries') && fs_developer_perf_may_see_expensive_queries();
				if ($is_developer && $exp_on && $exp_may_see) :
					$exp_count = function_exists('fs_developer_perf_slow_queries_count') ? fs_developer_perf_slow_queries_count() : 0;
					$exp_label = $exp_count > 0
						? sprintf(/* translators: %d = number of expensive queries */ __('Expensive queries (%d)', fs_perf_text_domain()), $exp_count)
						: __('Expensive queries', fs_perf_text_domain());
				?>
				<div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #c3c4c7;">
					<a href="<?= esc_url(fs_perf_settings_url(['fs_slow_queries' => '1'])) ?>" style="font-size: 11px;"><?= esc_html($exp_label) ?></a>
				</div>
				<?php endif; ?>
			</div>
			<div id="fs-perf-average-section" style="padding: 0 10px 8px; border-top: 1px solid #c3c4c7; margin-top: 4px; padding-top: 8px;"></div>
		</div>
		<?php if ($is_guest) : ?>
			<button type="button" class="fs-perf-pinned-panel__tab" style="display: none; padding: 6px 12px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,.1); font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;" title="<?= esc_attr__('Show performance panel', fs_perf_text_domain()) ?>"><?= esc_html__('Performance', fs_perf_text_domain()) ?> ▲</button>
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
			hooks: parseInt(panel.getAttribute('data-perf-hooks'), 10) || 0,
			score: parseFloat(panel.getAttribute('data-perf-score')) || 0
		};

		var raw = null;
		try { raw = document.getElementById('fs-perf-scale-config'); } catch (e) {}
		var config = raw && raw.textContent ? JSON.parse(raw.textContent) : null;

		var history = [];
		try { history = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'); } catch (e) {}
		if (!Array.isArray(history)) history = [];
		history.push(current);
		history = history.slice(-MAX_HISTORY);
		try { localStorage.setItem(HISTORY_KEY, JSON.stringify(history)); } catch (e) {}

		var avgSection = document.getElementById('fs-perf-average-section');
		if (config && avgSection) {
			var labels = config.labels || ['Excellent', 'Good', 'Acceptable', 'Heavy', 'Problematic'];
			function bandLabel(value, boundaries) {
				for (var i = 0; i < boundaries.length; i++) { if (value <= boundaries[i]) return labels[i] || labels[4]; }
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
				var avg = count ? (function() {
					var raw = {
						time: data.reduce(function(s, p) { return s + (p.time || 0); }, 0) / count,
						memory: data.reduce(function(s, p) { return s + (p.memory || 0); }, 0) / count,
						queries: data.reduce(function(s, p) { return s + (p.queries || 0); }, 0) / count,
						hooks: data.reduce(function(s, p) { return s + (p.hooks || 0); }, 0) / count,
						score: data.reduce(function(s, p) { return s + (p.score || 0); }, 0) / count
					};
					return {
						time: Math.round(raw.time * 1000) / 1000,
						memory: Math.round(raw.memory),
						queries: Math.round(raw.queries),
						hooks: Math.round(raw.hooks),
						score: Math.round(raw.score * 10) / 10
					};
				})() : null;
				var headerRow = '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px"><div style="font-weight:600">' + avgTitle + '</div>' +
					(count > 0 ? '<div class="fs-perf-clear-history" style="padding:2px 6px;font-size:10px;cursor:pointer">' + clearLabel + '</div>' : '') + '</div>';
				var body = count > 0 && avg
					? '<div style="font-size:11px;color:#646970;margin-bottom:6px">(' + pagesLabel + ')</div>' +
						'<div style="margin-bottom:4px">' + (m.execution_time || 'Execution time') + ': <strong>' + avg.time.toFixed(3) + 's</strong> ' + scaleHtml(avg.time, 'time') + '</div>' +
						'<div style="margin-bottom:4px">' + (m.peak_memory || 'Peak memory') + ': <strong>' + Math.round(avg.memory) + ' MB</strong> ' + scaleHtml(avg.memory, 'memory') + '</div>' +
						'<div style="margin-bottom:4px">' + (m.db_queries || 'DB queries') + ': <strong>' + Math.round(avg.queries) + '</strong> ' + scaleHtml(avg.queries, 'queries') + '</div>' +
						'<div style="margin-bottom:4px">' + (m.hooks_fired || 'Hooks fired') + ': <strong>' + Math.round(avg.hooks) + '</strong> ' + scaleHtml(avg.hooks, 'hooks') + '</div>' +
						'<div>' + (m.score || 'Score') + ': <strong>' + avg.score.toFixed(1) + '</strong> ' + scaleHtml(avg.score, 'score') + '</div>'
					: '<div style="font-size:11px;color:#646970;margin-bottom:4px">(' + pagesLabel + ')</div><div style="font-size:11px;color:#646970">' + noDataLabel + '</div>';
				avgSection.innerHTML = headerRow + body;
				var clearBtn = avgSection.querySelector('.fs-perf-clear-history');
				if (clearBtn) {
					clearBtn.addEventListener('click', function() {
						try { localStorage.setItem(HISTORY_KEY, '[]'); } catch (e) {}
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
		function show() { panel.style.display = 'block'; }
		function hide() { panel.style.display = 'none'; }
		function setExpanded(expanded) {
			if (content) content.style.display = expanded ? 'block' : 'none';
			if (tab) tab.style.display = expanded ? 'none' : 'block';
			if (isGuest) try { sessionStorage.setItem(MINIMIZED_KEY, expanded ? '0' : '1'); } catch (e) {}
		}
		if (isGuest) {
			show();
			var startMinimized = false;
			try { startMinimized = sessionStorage.getItem(MINIMIZED_KEY) === '1'; } catch (e) {}
			setExpanded(!startMinimized);
			var minBtn = panel.querySelector('.fs-perf-minimize');
			if (minBtn) minBtn.addEventListener('click', function() { setExpanded(false); });
			if (tab) tab.addEventListener('click', function() { setExpanded(true); });
		} else {
			if (localStorage.getItem('fs_perf_pinned') === '1') show();
			var unpin = panel.querySelector('.fs-perf-unpin');
			if (unpin) unpin.addEventListener('click', function() { localStorage.removeItem('fs_perf_pinned'); hide(); });
			document.addEventListener('click', function(e) {
				var t = e.target.closest && e.target.closest('.fs-perf-pin-trigger');
				if (t && t.querySelector('a')) { e.preventDefault(); localStorage.setItem('fs_perf_pinned', '1'); show(); }
			});
		}
	})();
	</script>
	<?php
}

add_action('wp_footer', 'fs_developer_perf_pinned_panel_render', 20);
add_action('admin_footer', 'fs_developer_perf_pinned_panel_render', 20);
