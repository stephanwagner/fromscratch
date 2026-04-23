<?php

defined('ABSPATH') || exit;

/**
 * Add "Duplicate" row action for posts, pages, and custom post types.
 * Pages (hierarchical) use page_row_actions; posts and non-hierarchical CPTs use post_row_actions.
 */

function fs_add_duplicate_row_action(array $actions, WP_Post $post): array {
	if ($post->post_type === 'attachment') {
		return $actions;
	}
	if (!in_array($post->post_type, fs_theme_post_types(), true)) {
		return $actions;
	}
	if (!current_user_can('edit_post', $post->ID)) {
		return $actions;
	}
	$url = wp_nonce_url(
		admin_url('admin.php?action=fs_duplicate_post&post=' . $post->ID),
		'fs_duplicate_post_' . $post->ID
	);
	$actions['duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicate', 'fromscratch') . '</a>';
	return $actions;
}

add_filter('post_row_actions', 'fs_add_duplicate_row_action', 10, 2);
add_filter('page_row_actions', 'fs_add_duplicate_row_action', 10, 2);

add_action('admin_action_fs_duplicate_post', function (): void {
	if (!isset($_GET['post'])) {
		wp_die(esc_html__('No post to duplicate.', 'fromscratch'));
	}

	$post_id = (int) $_GET['post'];
	check_admin_referer('fs_duplicate_post_' . $post_id);

	$post = get_post($post_id);
	if (!$post || $post->post_type === 'attachment' || !in_array($post->post_type, fs_theme_post_types(), true)) {
		wp_die(esc_html__('Post not found.', 'fromscratch'));
	}

	if (!current_user_can('edit_post', $post_id)) {
		wp_die(esc_html__('You do not have permission to duplicate this item.', 'fromscratch'));
	}

	$new_post = [
		'post_title'     => $post->post_title . ' ' . __('(Copy)', 'fromscratch'),
		'post_content'  => $post->post_content,
		'post_excerpt'   => $post->post_excerpt,
		'post_status'    => 'draft',
		'post_type'      => $post->post_type,
		'post_author'    => get_current_user_id(),
		'post_parent'    => $post->post_parent,
		'menu_order'     => $post->menu_order,
		'comment_status' => $post->comment_status,
		'ping_status'    => $post->ping_status,
		'post_password'  => $post->post_password,
	];

	$new_post_id = wp_insert_post($new_post);
	if (is_wp_error($new_post_id)) {
		wp_die(esc_html__('Failed to create duplicate.', 'fromscratch'));
	}

	// Copy meta (excluding internal keys that shouldn't be cloned)
	$meta = get_post_meta($post_id);
	$exclude_keys = ['_edit_lock', '_edit_last'];
	foreach ($meta as $key => $values) {
		if (in_array($key, $exclude_keys, true)) {
			continue;
		}
		foreach ($values as $value) {
			add_post_meta($new_post_id, $key, maybe_unserialize($value));
		}
	}

	// Copy taxonomies
	$taxonomies = get_object_taxonomies($post->post_type);
	foreach ($taxonomies as $taxonomy) {
		$terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
		if (!is_wp_error($terms) && !empty($terms)) {
			wp_set_object_terms($new_post_id, $terms, $taxonomy);
		}
	}

	wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
	exit;
});
