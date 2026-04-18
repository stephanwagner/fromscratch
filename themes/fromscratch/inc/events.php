<?php

defined('ABSPATH') || exit;

/**
 * Event CPT: start/end date, optional times, sidebar panel in block editor.
 * Archive: upcoming/current only, ordered by start; template groups by month.
 */

const FS_EVENT_POST_TYPE = 'event';

const FS_EVENT_META_START_DATE = '_fs_event_start_date';
const FS_EVENT_META_END_DATE = '_fs_event_end_date';
const FS_EVENT_META_START_TIME = '_fs_event_start_time';
const FS_EVENT_META_END_TIME = '_fs_event_end_time';
const FS_EVENT_META_START_TS = '_fs_event_start_ts';
const FS_EVENT_META_END_TS = '_fs_event_end_ts';

/**
 * @return \DateTimeZone
 */
function fs_event_timezone(): \DateTimeZone
{
	return function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string() ?: 'UTC');
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
	$has_time = is_string($time) && preg_match('/^\d{2}:\d{2}$/', trim($time));
	if ($has_time) {
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . trim($time), $tz);
	} elseif ($end_day) {
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59', $tz);
	} else {
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
	}

	return $dt instanceof \DateTimeImmutable ? $dt->getTimestamp() : 0;
}

/**
 * Persist sort/filter timestamps from date/time meta.
 */
function fs_event_save_timestamps(int $post_id): void
{
	if (get_post_type($post_id) !== FS_EVENT_POST_TYPE) {
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

	$start_date = get_post_meta($post_id, FS_EVENT_META_START_DATE, true);
	$end_date = get_post_meta($post_id, FS_EVENT_META_END_DATE, true);
	$start_time = get_post_meta($post_id, FS_EVENT_META_START_TIME, true);
	$end_time = get_post_meta($post_id, FS_EVENT_META_END_TIME, true);

	$start_date = is_string($start_date) ? trim($start_date) : '';
	$end_date = is_string($end_date) ? trim($end_date) : '';
	$start_time = is_string($start_time) ? trim($start_time) : '';
	$end_time = is_string($end_time) ? trim($end_time) : '';

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

add_action('save_post_' . FS_EVENT_POST_TYPE, 'fs_event_save_timestamps', 20);

/**
 * Register meta for REST / block editor.
 */
add_action('init', function (): void {
	$auth = static function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};

	$string_meta = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
	];

	register_post_meta(FS_EVENT_POST_TYPE, FS_EVENT_META_START_DATE, array_merge($string_meta, [
		'sanitize_callback' => static function ($value): string {
			$value = is_string($value) ? trim($value) : '';
			return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
		},
	]));
	register_post_meta(FS_EVENT_POST_TYPE, FS_EVENT_META_END_DATE, array_merge($string_meta, [
		'sanitize_callback' => static function ($value): string {
			$value = is_string($value) ? trim($value) : '';
			return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
		},
	]));
	register_post_meta(FS_EVENT_POST_TYPE, FS_EVENT_META_START_TIME, array_merge($string_meta, [
		'sanitize_callback' => static function ($value): string {
			$value = is_string($value) ? trim($value) : '';
			return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
		},
	]));
	register_post_meta(FS_EVENT_POST_TYPE, FS_EVENT_META_END_TIME, array_merge($string_meta, [
		'sanitize_callback' => static function ($value): string {
			$value = is_string($value) ? trim($value) : '';
			return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
		},
	]));

	foreach ([FS_EVENT_META_START_TS, FS_EVENT_META_END_TS] as $key) {
		register_post_meta(FS_EVENT_POST_TYPE, $key, [
			'type' => 'integer',
			'single' => true,
			'show_in_rest' => false,
			'auth_callback' => $auth,
			'sanitize_callback' => static function ($value): int {
				return (int) $value;
			},
		]);
	}
}, 20);

/**
 * Frontend archive: upcoming and in-progress events only; sort by start.
 */
add_action('pre_get_posts', function (\WP_Query $query): void {
	if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive(FS_EVENT_POST_TYPE)) {
		return;
	}
	$now = time();
	$query->set('meta_query', [
		[
			'key' => FS_EVENT_META_END_TS,
			'value' => $now,
			'compare' => '>=',
			'type' => 'NUMERIC',
		],
	]);
	$query->set('meta_key', FS_EVENT_META_START_TS);
	$query->set('orderby', 'meta_value_num');
	$query->set('order', 'ASC');
}, 25);

/**
 * Block editor: strings for the Event panel (same script as expirator).
 */
add_action('enqueue_block_editor_assets', function (): void {
	if (! post_type_exists(FS_EVENT_POST_TYPE)) {
		return;
	}
	wp_localize_script('fromscratch-editor', 'fromscratchEvents', [
		'postType' => FS_EVENT_POST_TYPE,
		'panelTitle' => __('Event', 'fromscratch'),
		'startDateLabel' => __('Start date', 'fromscratch'),
		'endDateLabel' => __('End date', 'fromscratch'),
		'includeTimesLabel' => __('Include times', 'fromscratch'),
		'startTimeLabel' => __('Start time', 'fromscratch'),
		'endTimeLabel' => __('End time', 'fromscratch'),
	]);
}, 12);

/**
 * Human-readable range for templates.
 */
function fs_event_format_range_text(int $post_id): string
{
	$start_date = get_post_meta($post_id, FS_EVENT_META_START_DATE, true);
	if (!is_string($start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
		return '';
	}
	$end_date = get_post_meta($post_id, FS_EVENT_META_END_DATE, true);
	if (!is_string($end_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
		$end_date = $start_date;
	}
	$st = get_post_meta($post_id, FS_EVENT_META_START_TIME, true);
	$et = get_post_meta($post_id, FS_EVENT_META_END_TIME, true);
	$st = is_string($st) && preg_match('/^\d{2}:\d{2}$/', trim($st)) ? trim($st) : '';
	$et = is_string($et) && preg_match('/^\d{2}:\d{2}$/', trim($et)) ? trim($et) : '';

	$tz = fs_event_timezone();
	$start_ts = fs_event_to_timestamp($start_date, $st, false);
	$end_ts = fs_event_to_timestamp($end_date, $et, $et === '');

	if ($start_ts <= 0 || $end_ts <= 0) {
		return '';
	}

	$df = get_option('date_format', 'F j, Y');
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
