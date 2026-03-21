<?php

defined('ABSPATH') || exit;

/**
 * Per-post flag: exclude this item from front-end and REST search results.
 */
const FS_EXCLUDE_FROM_SEARCH_META = '_fs_exclude_from_search';

/**
 * Post types that get the editor control + meta registration.
 *
 * @return array<int, string>
 */
function fs_exclude_from_search_post_types(): array
{
	$types = fs_theme_post_types();
	$result = [];
	foreach ($types as $name) {
		if (post_type_supports($name, 'editor')) {
			$result[] = $name;
		}
	}

	return apply_filters('fs_exclude_from_search_post_types', $result);
}

/**
 * Register post meta for the block editor (REST).
 *
 * @return void
 */
function fs_exclude_from_search_register_meta(): void
{
	$post_types = fs_exclude_from_search_post_types();
	$auth = function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};
	$args = [
		'type' => 'boolean',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
		'sanitize_callback' => static function ($value): bool {
			return (bool) $value;
		},
		'default' => false,
	];
	foreach ($post_types as $post_type) {
		register_post_meta($post_type, FS_EXCLUDE_FROM_SEARCH_META, $args);
	}
}
add_action('init', 'fs_exclude_from_search_register_meta');

/**
 * Exclude flagged posts from search SQL.
 *
 * Uses `posts_where` so it applies to every `WP_Query` search (main query, block Query Loop,
 * etc.). The previous `pre_get_posts` + `is_main_query()` approach missed block-theme search
 * templates that run a secondary query.
 *
 * @param string    $where SQL WHERE clause.
 * @param \WP_Query $query Query instance.
 * @return string
 */
function fs_exclude_from_search_posts_where(string $where, \WP_Query $query): string
{
	if (is_admin()) {
		return $where;
	}
	if (apply_filters('fs_exclude_from_search_apply_to_query', true, $query) === false) {
		return $where;
	}
	if (!$query->is_search()) {
		return $where;
	}
	$s = $query->get('s');
	if ($s === null || $s === '') {
		return $where;
	}

	global $wpdb;
	$where .= $wpdb->prepare(
		" AND {$wpdb->posts}.ID NOT IN (
			SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = %s AND meta_value IN ('1', 'true', 'yes', 'on')
		)",
		FS_EXCLUDE_FROM_SEARCH_META
	);

	return $where;
}
add_filter('posts_where', 'fs_exclude_from_search_posts_where', 10, 2);

/**
 * REST collection search (?search=): same exclusion when `posts_where` is suppressed.
 *
 * @param array<string, mixed>            $args    Query args.
 * @param \WP_REST_Request<string, mixed> $request Request.
 * @return array<string, mixed>
 */
function fs_exclude_from_search_rest_query(array $args, \WP_REST_Request $request): array
{
	if (!$request->has_param('search')) {
		return $args;
	}
	$search = $request->get_param('search');
	if ($search === null || $search === '' || (is_string($search) && trim($search) === '')) {
		return $args;
	}

	$clause = [
		'relation' => 'OR',
		[
			'key' => FS_EXCLUDE_FROM_SEARCH_META,
			'compare' => 'NOT EXISTS',
		],
		[
			'key' => FS_EXCLUDE_FROM_SEARCH_META,
			'value' => ['1', 'true', 'yes', 'on'],
			'compare' => 'NOT IN',
		],
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
 * Register `rest_{$post_type}_query` for each supported type (post, page, CPTs).
 *
 * @return void
 */
function fs_exclude_from_search_register_rest_filters(): void
{
	foreach (fs_exclude_from_search_post_types() as $post_type) {
		add_filter('rest_' . $post_type . '_query', 'fs_exclude_from_search_rest_query', 10, 2);
	}
}
add_action('init', 'fs_exclude_from_search_register_rest_filters', 20);

/**
 * Strings + post types for the block editor script.
 *
 * @return void
 */
function fs_exclude_from_search_editor_localize(): void
{
	wp_localize_script('fromscratch-editor', 'fromscratchExcludeFromSearch', [
		'postTypes' => fs_exclude_from_search_post_types(),
		'label' => __('Exclude from search', 'fromscratch'),
	]);
}
add_action('enqueue_block_editor_assets', 'fs_exclude_from_search_editor_localize', 11);
