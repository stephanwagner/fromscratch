<?php

defined('ABSPATH') || exit;

/**
 * Post expirator: set expiration date/time on posts, pages and theme CPTs.
 * When the date/time is reached, the post is set to draft.
 * Gated by Settings → Developer → Features → Post expirator.
 */

const FS_EXPIRATION_META_KEY = '_fs_expiration_date';
const FS_EXPIRATION_ENABLED_KEY = '_fs_expiration_enabled';
const FS_EXPIRATION_ACTION_KEY = '_fs_expiration_action';
const FS_EXPIRATION_REDIRECT_KEY = '_fs_expiration_redirect_url';
const FS_EXPIRE_POST_HOOK = 'fs_expire_post';

const FS_EXPIRATION_ACTIONS = ['draft', 'private', 'redirect'];

/**
 * Unschedule any existing expiration event for a post.
 */
function fs_unschedule_expire_post(int $post_id): void
{
	$timestamp = wp_next_scheduled(FS_EXPIRE_POST_HOOK, [$post_id]);
	if ($timestamp !== false) {
		wp_unschedule_event($timestamp, FS_EXPIRE_POST_HOOK, [$post_id]);
	}
}

/**
 * Schedule a one-time expiration at the given date/time (site timezone string Y-m-d H:i).
 */
function fs_schedule_expire_post(int $post_id, string $expiration_value): void
{
	$expiration_value = trim($expiration_value);
	if ($expiration_value === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $expiration_value)) {
		return;
	}
	$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string());
	$dt = \DateTime::createFromFormat('Y-m-d H:i', $expiration_value, $tz);
	if (!$dt || $dt->getTimestamp() <= time()) {
		return;
	}
	fs_unschedule_expire_post($post_id);
	wp_schedule_single_event($dt->getTimestamp(), FS_EXPIRE_POST_HOOK, [$post_id]);
}

/**
 * Register expiration post meta (REST + block editor panel).
 */
add_action('init', function () {
	$types = fs_theme_post_types();
	$auth = function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};
	$args = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'sanitize_callback' => function ($value) {
			$value = is_string($value) ? trim($value) : '';
			if ($value === '') {
				return '';
			}
			$value = str_replace('T', ' ', substr($value, 0, 16));
			return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) ? $value : '';
		},
		'auth_callback' => $auth,
	];
	foreach ($types as $post_type) {
		register_post_meta($post_type, FS_EXPIRATION_META_KEY, $args);
	}
	$enabled_args = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'sanitize_callback' => function ($value) {
			return ($value === '1' || $value === true) ? '1' : '';
		},
		'auth_callback' => $auth,
	];
	foreach ($types as $post_type) {
		register_post_meta($post_type, FS_EXPIRATION_ENABLED_KEY, $enabled_args);
	}
	$action_args = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'default' => 'draft',
		'sanitize_callback' => function ($value) {
			$value = is_string($value) ? trim($value) : '';
			return in_array($value, FS_EXPIRATION_ACTIONS, true) ? $value : 'draft';
		},
		'auth_callback' => $auth,
	];
	$redirect_args = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'sanitize_callback' => function ($value) {
			$value = is_string($value) ? trim($value) : '';
			return $value === '' ? '' : esc_url_raw($value);
		},
		'auth_callback' => $auth,
	];
	foreach ($types as $post_type) {
		register_post_meta($post_type, FS_EXPIRATION_ACTION_KEY, $action_args);
		register_post_meta($post_type, FS_EXPIRATION_REDIRECT_KEY, $redirect_args);
	}
});

/**
 * Pass labels to the block editor expirator panel.
 */
add_action('enqueue_block_editor_assets', function () {
	$time_format = get_option('time_format', 'H:i');
	// Match 12-hour format: g (1-12), h (01-12), a (am/pm), A (AM/PM). 24-hour uses H or G.
	$is_12_hour = (bool) preg_match('/[ghaA]/', $time_format);
	$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string());
	$d_am = new \DateTime('today 00:00', $tz);
	$d_pm = new \DateTime('today 12:00', $tz);
	$am_label = date_i18n('a', $d_am->getTimestamp());
	$pm_label = date_i18n('a', $d_pm->getTimestamp());
	wp_localize_script('fromscratch-editor', 'fromscratchExpirator', [
		'postTypes'   => fs_theme_post_types(),
		'panelTitle' => __('Expiration', 'fromscratch'),
		'dateLabel'  => __('Expiration date and time', 'fromscratch'),
		'timeLabel'  => $is_12_hour ? __('Time', 'fromscratch') : __('Time (24h)', 'fromscratch'),
		'dateHelp'   => __('When this date and time is reached, the post will be set to draft. Leave empty for no expiration.', 'fromscratch'),
		'timezone'   => wp_timezone_string(),
		'is12Hour'   => $is_12_hour ? 1 : 0,
		'startOfWeek' => (int) get_option('start_of_week', 0),
		'nowLabel'   => __('Now', 'fromscratch'),
		'clearLabel' => __('Clear', 'fromscratch'),
		'enableLabel' => __('Activate expire', 'fromscratch'),
		'enableHelp'  => __('Uncheck to disable expiration.', 'fromscratch'),
		'amLabel'    => $am_label,
		'pmLabel'    => $pm_label,
		'timePlaceholder' => $is_12_hour ? 'e.g. 2:30 ' . $pm_label : 'HH:mm',
		'actionLabel' => __('After expiration', 'fromscratch'),
		'actionDraft' => __('Set to draft', 'fromscratch'),
		'actionPrivate' => __('Set to private', 'fromscratch'),
		'actionRedirect' => __('Redirect to', 'fromscratch'),
		'redirectPlaceholder' => __('https://example.com', 'fromscratch'),
		'redirectLabel' => __('Redirect URL', 'fromscratch'),
	], 11);
});

/**
 * Only schedule expiration when the post has expiration enabled and a valid date.
 */
function fs_maybe_schedule_expire_post(int $post_id): void
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('post_expirator')) {
		return;
	}
	$enabled = get_post_meta($post_id, FS_EXPIRATION_ENABLED_KEY, true);
	if ($enabled !== '1') {
		fs_unschedule_expire_post($post_id);
		return;
	}
	$date = get_post_meta($post_id, FS_EXPIRATION_META_KEY, true);
	if (is_string($date) && $date !== '') {
		fs_schedule_expire_post($post_id, $date);
	} else {
		fs_unschedule_expire_post($post_id);
	}
}

add_action('added_post_meta', function (int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void {
	if ($meta_key !== FS_EXPIRATION_META_KEY && $meta_key !== FS_EXPIRATION_ENABLED_KEY) {
		return;
	}
	fs_maybe_schedule_expire_post($object_id);
}, 10, 4);

add_action('updated_post_meta', function (int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void {
	if ($meta_key !== FS_EXPIRATION_META_KEY && $meta_key !== FS_EXPIRATION_ENABLED_KEY) {
		return;
	}
	fs_maybe_schedule_expire_post($object_id);
}, 10, 4);

add_action('deleted_post_meta', function (array $deleted_meta_ids, int $object_id, string $meta_key): void {
	if ($meta_key !== FS_EXPIRATION_META_KEY && $meta_key !== FS_EXPIRATION_ENABLED_KEY) {
		return;
	}
	fs_unschedule_expire_post($object_id);
}, 10, 3);

/**
 * Run when the scheduled time is reached. Action: draft → set to draft; private → set to private; redirect → keep published, redirect on visit.
 */
add_action(FS_EXPIRE_POST_HOOK, function (int $post_id): void {
	if (get_post_meta($post_id, FS_EXPIRATION_ENABLED_KEY, true) !== '1') {
		fs_unschedule_expire_post($post_id);
		return;
	}
	if (get_post_status($post_id) !== 'publish') {
		return;
	}
	$action = get_post_meta($post_id, FS_EXPIRATION_ACTION_KEY, true);
	if (!in_array($action, FS_EXPIRATION_ACTIONS, true)) {
		$action = 'draft';
	}
	fs_unschedule_expire_post($post_id);
	if ($action === 'redirect') {
		// Leave post published; redirect happens on template_redirect when visiting the URL.
		return;
	}
	wp_update_post([
		'ID'          => $post_id,
		'post_status' => $action === 'private' ? 'private' : 'draft',
	]);
	delete_post_meta($post_id, FS_EXPIRATION_META_KEY);
	delete_post_meta($post_id, FS_EXPIRATION_ENABLED_KEY);
	delete_post_meta($post_id, FS_EXPIRATION_ACTION_KEY);
	delete_post_meta($post_id, FS_EXPIRATION_REDIRECT_KEY);
});

/**
 * Catch-up for past-due expirations (missed cron or date set in the past).
 * Runs at most once per hour via transient to avoid a DB query on every request.
 */
add_action('init', function (): void {
	if (wp_doing_cron() || wp_doing_ajax()) {
		return;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('post_expirator')) {
		return;
	}
	if (get_transient('fs_expirator_checked')) {
		return;
	}
	set_transient('fs_expirator_checked', 1, HOUR_IN_SECONDS);

	$types = fs_theme_post_types();
	$now = current_time('Y-m-d H:i');
	$query = new \WP_Query([
		'post_type'      => $types,
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => FS_EXPIRATION_ENABLED_KEY,
				'value'   => '1',
				'compare' => '=',
			],
			[
				'key'     => FS_EXPIRATION_META_KEY,
				'value'   => $now,
				'compare' => '<=',
				'type'    => 'CHAR',
			],
		],
		'fields' => 'ids',
	]);
	foreach ($query->posts as $post_id) {
		$action = get_post_meta($post_id, FS_EXPIRATION_ACTION_KEY, true);
		if (!in_array($action, FS_EXPIRATION_ACTIONS, true)) {
			$action = 'draft';
		}
		fs_unschedule_expire_post($post_id);
		if ($action === 'redirect') {
			continue;
		}
		wp_update_post([
			'ID'          => $post_id,
			'post_status' => $action === 'private' ? 'private' : 'draft',
		]);
		delete_post_meta($post_id, FS_EXPIRATION_META_KEY);
		delete_post_meta($post_id, FS_EXPIRATION_ENABLED_KEY);
		delete_post_meta($post_id, FS_EXPIRATION_ACTION_KEY);
		delete_post_meta($post_id, FS_EXPIRATION_REDIRECT_KEY);
	}
}, 20);

/**
 * Redirect to the configured URL when visiting an expired post with action "redirect".
 */
add_action('template_redirect', function (): void {
	if (!is_singular()) {
		return;
	}
	$post_id = get_queried_object_id();
	if (!$post_id) {
		return;
	}
	if (get_post_meta($post_id, FS_EXPIRATION_ENABLED_KEY, true) !== '1') {
		return;
	}
	$date = get_post_meta($post_id, FS_EXPIRATION_META_KEY, true);
	if (!is_string($date) || $date === '') {
		return;
	}
	$now = current_time('Y-m-d H:i');
	if (strcmp($date, $now) > 0) {
		return;
	}
	if (get_post_meta($post_id, FS_EXPIRATION_ACTION_KEY, true) !== 'redirect') {
		return;
	}
	$url = get_post_meta($post_id, FS_EXPIRATION_REDIRECT_KEY, true);
	if (!is_string($url) || $url === '') {
		return;
	}
	wp_redirect(esc_url_raw($url), 301);
	exit;
});
