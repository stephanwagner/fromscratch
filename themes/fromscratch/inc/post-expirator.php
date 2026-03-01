<?php

defined('ABSPATH') || exit;

/**
 * Post expirator: set expiration date/time on posts, pages and theme CPTs.
 * When the date/time is reached, the post is set to draft.
 * Gated by Settings → Developer → Features → Post expirator.
 */

const FS_EXPIRATION_META_KEY = '_fs_expiration_date';
const FS_EXPIRE_POST_HOOK = 'fs_expire_post';

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
});

/**
 * Pass labels to the block editor expirator panel.
 */
add_action('enqueue_block_editor_assets', function () {
	wp_localize_script('fromscratch-editor', 'fromscratchExpirator', [
		'postTypes' => fs_theme_post_types(),
		'panelTitle' => __('Expiration', 'fromscratch'),
		'dateLabel' => __('Expiration date and time', 'fromscratch'),
		'dateHelp' => __('When this date and time is reached, the post will be set to draft. Leave empty for no expiration.', 'fromscratch'),
	], 11);
});

/**
 * When expiration meta is added or updated, schedule a one-time event at that date/time.
 */
add_action('added_post_meta', function (int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void {
	if ($meta_key !== FS_EXPIRATION_META_KEY || !function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('post_expirator')) {
		return;
	}
	fs_schedule_expire_post($object_id, (string) $meta_value);
}, 10, 4);

add_action('updated_post_meta', function (int $meta_id, int $object_id, string $meta_key, mixed $meta_value): void {
	if ($meta_key !== FS_EXPIRATION_META_KEY || !function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('post_expirator')) {
		return;
	}
	fs_unschedule_expire_post($object_id);
	if ($meta_value !== '' && $meta_value !== null) {
		fs_schedule_expire_post($object_id, (string) $meta_value);
	}
}, 10, 4);

add_action('deleted_post_meta', function (array $deleted_meta_ids, int $object_id, string $meta_key): void {
	if ($meta_key !== FS_EXPIRATION_META_KEY) {
		return;
	}
	fs_unschedule_expire_post($object_id);
}, 10, 3);

/**
 * Run when the scheduled time is reached: set post to draft and remove expiration meta.
 */
add_action(FS_EXPIRE_POST_HOOK, function (int $post_id): void {
	if (get_post_status($post_id) !== 'publish') {
		return;
	}
	wp_update_post([
		'ID'          => $post_id,
		'post_status' => 'draft',
	]);
	delete_post_meta($post_id, FS_EXPIRATION_META_KEY);
});

/**
 * On load, catch any past-due expirations (e.g. missed cron, or date set in the past).
 */
add_action('init', function (): void {
	if (wp_doing_cron() || wp_doing_ajax()) {
		return;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('post_expirator')) {
		return;
	}
	$types = fs_theme_post_types();
	$now = current_time('Y-m-d H:i');
	$query = new \WP_Query([
		'post_type'      => $types,
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'meta_query'     => [
			[
				'key'     => FS_EXPIRATION_META_KEY,
				'value'   => $now,
				'compare' => '<=',
				'type'    => 'DATETIME',
			],
		],
		'fields' => 'ids',
	]);
	foreach ($query->posts as $post_id) {
		wp_update_post([
			'ID'          => $post_id,
			'post_status' => 'draft',
		]);
		delete_post_meta($post_id, FS_EXPIRATION_META_KEY);
		fs_unschedule_expire_post($post_id);
	}
}, 20);
