<?php

defined('ABSPATH') || exit;

/**
 * Post expirator: set expiration date/time on posts, pages and theme CPTs.
 * When the date/time is reached, the post is set to draft.
 * Gated by Settings → Developer → Features → Post expirator.
 */

const FS_EXPIRATION_META_KEY = '_fs_expiration_date';
const FS_EXPIRATION_CRON_HOOK = 'fs_post_expirator_run';

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
 * Schedule cron on theme load if not already scheduled.
 */
add_action('init', function () {
	if (wp_doing_cron() || wp_doing_ajax()) {
		return;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('post_expirator')) {
		return;
	}
	if (wp_next_scheduled(FS_EXPIRATION_CRON_HOOK)) {
		return;
	}
	wp_schedule_event(time(), 'hourly', FS_EXPIRATION_CRON_HOOK);
}, 20);

/**
 * Run expiration: set to draft all published posts whose expiration date has passed.
 */
add_action(FS_EXPIRATION_CRON_HOOK, function () {
	$types = fs_theme_post_types();
	$now = current_time('Y-m-d H:i');
	$query = new \WP_Query([
		'post_type'   => $types,
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'meta_query'  => [
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
	}
});
