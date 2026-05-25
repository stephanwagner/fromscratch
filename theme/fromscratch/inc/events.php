<?php

defined('ABSPATH') || exit;

/**
 * Event CPTs (`type` => `event` in config/content-types/): dates, archive query, editor panel.
 */

const FS_EVENT_META_START_DATE = '_fs_event_start_date';
const FS_EVENT_META_END_DATE = '_fs_event_end_date';
const FS_EVENT_META_START_TIME = '_fs_event_start_time';
const FS_EVENT_META_END_TIME = '_fs_event_end_time';
const FS_EVENT_META_START_TS = '_fs_event_start_ts';
const FS_EVENT_META_END_TS = '_fs_event_end_ts';

/**
 * @return string[]
 */
function fs_event_post_types(): array
{
	return fs_cpt_slugs_by_type('event');
}

function fs_is_event_post_type(?string $post_type = null): bool
{
	if ($post_type === null || $post_type === '') {
		$post_type = function_exists('get_post_type') ? (string) get_post_type() : '';
	}

	return $post_type !== '' && fs_cpt_type($post_type) === 'event';
}

/**
 * @return \DateTimeZone
 */
function fs_event_timezone(): \DateTimeZone
{
	return function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
}

function fs_event_normalize_time(string $time): string
{
	$time = trim($time);
	if (preg_match('/^(\d{2}:\d{2})(?::\d{2})?$/', $time, $m)) {
		return $m[1];
	}

	return '';
}

/**
 * Combine date + optional time into a Unix timestamp (site timezone).
 *
 * @param string    $date     Y-m-d
 * @param string    $time     H:i or ''
 * @param bool      $end_day  If no time, use end of day (23:59:59) instead of start (00:00:00).
 */
function fs_event_to_timestamp(string $date, string $time, bool $end_day): int
{
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
		return 0;
	}
	$tz = fs_event_timezone();
	$time = fs_event_normalize_time($time);
	$has_time = $time !== '';
	if ($has_time) {
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $tz);
	} elseif ($end_day) {
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59', $tz);
	} else {
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
	}

	return $dt instanceof \DateTimeImmutable ? $dt->getTimestamp() : 0;
}

/**
 * Normalized event schedule from post meta, or null when no valid start date.
 *
 * @return array{start_date: string, end_date: string, start_time: string, end_time: string}|null
 */
function fs_event_get_schedule(int $post_id): ?array
{
	if (!fs_is_event_post_type(get_post_type($post_id))) {
		return null;
	}

	$start_date = get_post_meta($post_id, FS_EVENT_META_START_DATE, true);
	if (!is_string($start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($start_date))) {
		return null;
	}
	$start_date = trim($start_date);

	$end_date = get_post_meta($post_id, FS_EVENT_META_END_DATE, true);
	if (!is_string($end_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($end_date))) {
		$end_date = $start_date;
	} else {
		$end_date = trim($end_date);
	}

	$start_time = get_post_meta($post_id, FS_EVENT_META_START_TIME, true);
	$end_time = get_post_meta($post_id, FS_EVENT_META_END_TIME, true);
	$start_time = is_string($start_time) ? fs_event_normalize_time($start_time) : '';
	$end_time = is_string($end_time) ? fs_event_normalize_time($end_time) : '';

	return [
		'start_date' => $start_date,
		'end_date' => $end_date,
		'start_time' => $start_time,
		'end_time' => $end_time,
	];
}

function fs_event_get_start_timestamp(int $post_id): int
{
	$schedule = fs_event_get_schedule($post_id);
	if ($schedule === null) {
		return 0;
	}

	return fs_event_to_timestamp($schedule['start_date'], $schedule['start_time'], false);
}

function fs_event_get_end_timestamp(int $post_id): int
{
	$schedule = fs_event_get_schedule($post_id);
	if ($schedule === null) {
		return 0;
	}

	$end_ts = fs_event_to_timestamp($schedule['end_date'], $schedule['end_time'], $schedule['end_time'] === '');
	if ($end_ts <= 0) {
		return 0;
	}

	$start_ts = fs_event_get_start_timestamp($post_id);
	if ($start_ts > 0 && $end_ts < $start_ts) {
		return $start_ts;
	}

	return $end_ts;
}

function fs_event_is_upcoming(int $post_id, ?int $now = null): bool
{
	$end_ts = fs_event_get_end_timestamp($post_id);
	if ($end_ts <= 0) {
		return false;
	}

	return $end_ts >= ($now ?? time());
}

/**
 * Derive and persist sort/filter timestamps from date/time meta.
 */
function fs_event_recalculate_timestamps(int $post_id): void
{
	if (!fs_is_event_post_type(get_post_type($post_id))) {
		return;
	}
	if (wp_is_post_revision($post_id)) {
		return;
	}

	$start_date = get_post_meta($post_id, FS_EVENT_META_START_DATE, true);
	$end_date = get_post_meta($post_id, FS_EVENT_META_END_DATE, true);
	$start_time = get_post_meta($post_id, FS_EVENT_META_START_TIME, true);
	$end_time = get_post_meta($post_id, FS_EVENT_META_END_TIME, true);

	$start_date = is_string($start_date) ? trim($start_date) : '';
	$end_date = is_string($end_date) ? trim($end_date) : '';
	$start_time = is_string($start_time) ? fs_event_normalize_time($start_time) : '';
	$end_time = is_string($end_time) ? fs_event_normalize_time($end_time) : '';

	if ($start_date === '') {
		delete_post_meta($post_id, FS_EVENT_META_START_TS);
		delete_post_meta($post_id, FS_EVENT_META_END_TS);
		return;
	}

	if ($end_date === '') {
		$end_date = $start_date;
	}

	$start_ts = fs_event_to_timestamp($start_date, $start_time, false);
	$end_ts = fs_event_to_timestamp($end_date, $end_time, $end_time === '');

	if ($start_ts <= 0 || $end_ts <= 0) {
		delete_post_meta($post_id, FS_EVENT_META_START_TS);
		delete_post_meta($post_id, FS_EVENT_META_END_TS);
		return;
	}

	if ($end_ts < $start_ts) {
		$end_ts = $start_ts;
	}

	update_post_meta($post_id, FS_EVENT_META_START_TS, $start_ts);
	update_post_meta($post_id, FS_EVENT_META_END_TS, $end_ts);
}

/**
 * Persist sort/filter timestamps from date/time meta (classic save path).
 */
function fs_event_save_timestamps(int $post_id): void
{
	if (!fs_is_event_post_type(get_post_type($post_id))) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (wp_is_post_revision($post_id)) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	fs_event_recalculate_timestamps($post_id);
}

/**
 * Register meta, save hooks, and admin columns for every CPT with `type` => `event`.
 */
function fs_event_register_post_type_hooks(): void
{
	static $registered = false;
	if ($registered) {
		return;
	}
	$registered = true;

	$event_types = fs_event_post_types();
	if ($event_types === []) {
		return;
	}

	foreach ($event_types as $post_type) {
		if (!post_type_exists($post_type)) {
			continue;
		}
		add_action('save_post_' . $post_type, 'fs_event_save_timestamps', 20);
	}

	$auth = static function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};

	$string_meta = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
	];

	foreach ($event_types as $post_type) {
		if (!post_type_exists($post_type)) {
			continue;
		}

		register_post_meta($post_type, FS_EVENT_META_START_DATE, array_merge($string_meta, [
			'sanitize_callback' => static function ($value): string {
				$value = is_string($value) ? trim($value) : '';
				return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
			},
		]));
		register_post_meta($post_type, FS_EVENT_META_END_DATE, array_merge($string_meta, [
			'sanitize_callback' => static function ($value): string {
				$value = is_string($value) ? trim($value) : '';
				return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
			},
		]));
		register_post_meta($post_type, FS_EVENT_META_START_TIME, array_merge($string_meta, [
			'sanitize_callback' => static function ($value): string {
				$value = is_string($value) ? trim($value) : '';
				return fs_event_normalize_time($value);
			},
		]));
		register_post_meta($post_type, FS_EVENT_META_END_TIME, array_merge($string_meta, [
			'sanitize_callback' => static function ($value): string {
				$value = is_string($value) ? trim($value) : '';
				return fs_event_normalize_time($value);
			},
		]));

		foreach ([FS_EVENT_META_START_TS, FS_EVENT_META_END_TS] as $key) {
			register_post_meta($post_type, $key, [
				'type' => 'integer',
				'single' => true,
				'show_in_rest' => false,
				'auth_callback' => $auth,
				'sanitize_callback' => static function ($value): int {
					return (int) $value;
				},
			]);
		}

		add_filter('manage_' . $post_type . '_posts_columns', 'fs_event_posts_columns');
		add_action('manage_' . $post_type . '_posts_custom_column', 'fs_event_posts_custom_column', 10, 2);
	}
}

add_action('init', 'fs_event_register_post_type_hooks', 21);

/**
 * Frontend archive: show events that have not ended yet; sort by start date/time.
 */
function fs_event_archive_pre_get_posts(\WP_Query $query): void
{
	if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive()) {
		return;
	}
	$pt = $query->get('post_type');
	if (is_array($pt)) {
		$pt = (string) reset($pt);
	}
	if (!is_string($pt) || $pt === '' || !fs_is_event_post_type($pt)) {
		return;
	}

	$now = time();
	$candidate_ids = get_posts([
		'post_type' => $pt,
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'orderby' => 'ID',
		'order' => 'ASC',
		'no_found_rows' => true,
	]);

	$upcoming = [];
	foreach ($candidate_ids as $post_id) {
		$post_id = (int) $post_id;
		if (fs_event_is_upcoming($post_id, $now)) {
			$upcoming[] = $post_id;
		}
	}

	usort($upcoming, static function (int $a, int $b): int {
		$start_a = fs_event_get_start_timestamp($a);
		$start_b = fs_event_get_start_timestamp($b);
		if ($start_a === $start_b) {
			return $a <=> $b;
		}

		return $start_a <=> $start_b;
	});

	$query->set('post__in', $upcoming !== [] ? $upcoming : [0]);
	$query->set('orderby', 'post__in');
}

add_action('pre_get_posts', 'fs_event_archive_pre_get_posts', 25);

/**
 * Published event IDs that have ended (same rule as the events archive).
 *
 * @return int[]
 */
function fs_event_past_published_ids(?int $now = null): array
{
	static $cache = null;
	if (is_array($cache)) {
		return $cache;
	}

	$now = $now ?? time();
	$past = [];
	foreach (fs_event_post_types() as $post_type) {
		$candidate_ids = get_posts([
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
			'no_found_rows' => true,
		]);
		foreach ($candidate_ids as $post_id) {
			$post_id = (int) $post_id;
			if ($post_id > 0 && !fs_event_is_upcoming($post_id, $now)) {
				$past[] = $post_id;
			}
		}
	}

	$cache = $past;

	return $past;
}

/**
 * Exclude ended events from front-end search (keep published for direct URLs / SEO).
 *
 * @param string    $where SQL WHERE clause.
 * @param \WP_Query $query Query instance.
 */
function fs_event_search_exclude_past_posts_where(string $where, \WP_Query $query): string
{
	if (is_admin()) {
		return $where;
	}
	if (apply_filters('fs_event_search_exclude_past_apply', true, $query) === false) {
		return $where;
	}
	if (!$query->is_search()) {
		return $where;
	}
	$s = $query->get('s');
	if ($s === null || $s === '') {
		return $where;
	}

	$event_types = fs_event_post_types();
	if ($event_types === []) {
		return $where;
	}

	$past_ids = fs_event_past_published_ids();
	if ($past_ids === []) {
		return $where;
	}

	global $wpdb;
	$type_list = "'" . implode("','", array_map('esc_sql', $event_types)) . "'";
	$id_list = implode(',', array_map('intval', $past_ids));
	$where .= " AND NOT ({$wpdb->posts}.post_type IN ({$type_list}) AND {$wpdb->posts}.ID IN ({$id_list}))";

	return $where;
}

add_filter('posts_where', 'fs_event_search_exclude_past_posts_where', 10, 2);

/**
 * REST collection search: hide ended events via stored end timestamp.
 *
 * @param array<string, mixed>            $args    Query args.
 * @param \WP_REST_Request<string, mixed> $request Request.
 * @return array<string, mixed>
 */
function fs_event_rest_search_exclude_past(array $args, \WP_REST_Request $request): array
{
	if (!$request->has_param('search')) {
		return $args;
	}
	$search = $request->get_param('search');
	if ($search === null || $search === '' || (is_string($search) && trim($search) === '')) {
		return $args;
	}

	$clause = [
		'key' => FS_EVENT_META_END_TS,
		'value' => time(),
		'compare' => '>=',
		'type' => 'NUMERIC',
	];
	$old = isset($args['meta_query']) ? $args['meta_query'] : null;
	if (empty($old)) {
		$args['meta_query'] = $clause;

		return $args;
	}
	$args['meta_query'] = [
		'relation' => 'AND',
		$old,
		$clause,
	];

	return $args;
}

/**
 * @return void
 */
function fs_event_register_rest_search_filters(): void
{
	foreach (fs_event_post_types() as $post_type) {
		add_filter('rest_' . $post_type . '_query', 'fs_event_rest_search_exclude_past', 10, 2);
	}
}

add_action('init', 'fs_event_register_rest_search_filters', 22);

/**
 * Block editor: strings for the Event panel (same script as expirator).
 */
add_action('enqueue_block_editor_assets', function (): void {
	$post_types = fs_event_post_types();
	if ($post_types === []) {
		return;
	}
	wp_localize_script('fromscratch-editor', 'fromscratchEvents', [
		'postTypes' => $post_types,
		'postType' => $post_types[0],
		'panelTitle' => __('Event', 'fromscratch'),
		'startDateLabel' => __('Start date', 'fromscratch'),
		'endDateLabel' => __('End date', 'fromscratch'),
		'includeTimesLabel' => __('Include times', 'fromscratch'),
		'startTimeLabel' => __('Start time', 'fromscratch'),
		'endTimeLabel' => __('End time', 'fromscratch'),
	]);
}, 12);

/**
 * Admin list table: Event dates column (display only).
 *
 * @param array<string, string> $columns
 * @return array<string, string>
 */
function fs_event_posts_columns(array $columns): array
{
	$label = __('Event dates', 'fromscratch');
	$new = [];
	$inserted = false;
	foreach ($columns as $key => $heading) {
		if ($key === 'date' && !$inserted) {
			$new['fs_event_dates'] = $label;
			$inserted = true;
		}
		$new[$key] = $heading;
	}
	if ($inserted) {
		return $new;
	}
	$out = [];
	foreach ($columns as $key => $heading) {
		$out[$key] = $heading;
		if ($key === 'title') {
			$out['fs_event_dates'] = $label;
		}
	}

	return $out;
}

function fs_event_posts_custom_column(string $column, int $post_id): void
{
	if ($column !== 'fs_event_dates' || !fs_is_event_post_type(get_post_type($post_id))) {
		return;
	}
	$range = fs_event_format_range_text($post_id, true);
	if ($range === '') {
		echo '<span aria-hidden="true">—</span>';
		return;
	}
	echo esc_html($range);
}

add_action('admin_head', static function (): void {
	global $pagenow;
	if ($pagenow !== 'edit.php') {
		return;
	}
	$screen_type = sanitize_key(wp_unslash((string) ($_GET['post_type'] ?? '')));
	if ($screen_type === '' || !fs_is_event_post_type($screen_type)) {
		return;
	}
	echo '<style>.column-fs_event_dates{width:14em;} @media(min-width:900px){.column-fs_event_dates{width:20em}}</style>';
});

/**
 * Adapt a php date()/wp_date format string so full-month tokens (F) become abbreviated months (M).
 * Respects backslash escapes: e.g. \F stays a literal letter F in output.
 *
 * @param string $php_format Same style as Options → Date format option.
 */
function fs_event_abbr_month_datetime_format(string $php_format): string
{
	$out = '';
	$len = strlen($php_format);
	for ($i = 0; $i < $len; ++$i) {
		$c = $php_format[$i];
		if ($c === '\\') {
			$out .= '\\';
			if (++$i < $len) {
				$out .= $php_format[$i];
			}
			continue;
		}
		$out .= $c === 'F' ? 'M' : $c;
	}

	return $out;
}

/**
 * Human-readable range for templates.
 *
 * @param bool $abbr_month_names When true (e.g. admin list column), formatted months use abbreviated names (M not F).
 */
function fs_event_format_range_text(int $post_id, bool $abbr_month_names = false): string
{
	$schedule = fs_event_get_schedule($post_id);
	if ($schedule === null) {
		return '';
	}

	$start_ts = fs_event_get_start_timestamp($post_id);
	$end_ts = fs_event_get_end_timestamp($post_id);
	if ($start_ts <= 0 || $end_ts <= 0) {
		return '';
	}

	$start_date = $schedule['start_date'];
	$end_date = $schedule['end_date'];
	$st = $schedule['start_time'];
	$et = $schedule['end_time'];
	$tz = fs_event_timezone();

	$df = get_option('date_format', 'F j, Y');
	if ($abbr_month_names) {
		$df = fs_event_abbr_month_datetime_format((string) $df);
	}
	$tf = get_option('time_format', 'g:i a');
	$ds = wp_date($df, $start_ts, $tz);
	$de = wp_date($df, $end_ts, $tz);

	if ($start_date === $end_date) {
		if ($st !== '' || $et !== '') {
			$ts_fmt = $df . ' ' . $tf;
			return trim(wp_date($ts_fmt, $start_ts, $tz) . ' – ' . wp_date($tf, $end_ts, $tz));
		}
		return $ds;
	}

	if ($st !== '' || $et !== '') {
		return trim(wp_date($df . ' ' . $tf, $start_ts, $tz) . ' – ' . wp_date($df . ' ' . $tf, $end_ts, $tz));
	}

	return $ds . ' – ' . $de;
}
