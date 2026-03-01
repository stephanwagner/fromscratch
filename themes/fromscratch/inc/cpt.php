<?php

defined('ABSPATH') || exit;

/**
 * Register custom post types from config/cpt.php.
 * Registered CPTs are included in fs_theme_post_types() (theme-setup.php) and thus in SEO, post expirator, duplicate, etc.
 */

/**
 * Register all CPTs defined in config/cpt.php.
 *
 * @return void
 */
function fs_register_cpts(): void
{
	$cpts = fs_config_cpt('cpts');
	if (!is_array($cpts) || $cpts === []) {
		return;
	}

	$defaults = [
		'public'            => true,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'supports'          => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
		'capability_type'   => 'post',
		'map_meta_cap'      => true,
		'has_archive'       => false,
		'rewrite'           => true,
		'query_var'         => true,
		'menu_position'     => 5,
	];

	foreach ($cpts as $post_type => $args) {
		if (!is_string($post_type) || $post_type === '' || !is_array($args)) {
			continue;
		}
		$args = array_merge($defaults, $args);
		// Block editor needs custom-fields support to expose/save post meta (e.g. SEO panel).
		if (isset($args['supports']) && is_array($args['supports']) && !in_array('custom-fields', $args['supports'], true)) {
			$args['supports'][] = 'custom-fields';
		}
		// Ensure labels exist (required by register_post_type)
		if (empty($args['labels']) || !is_array($args['labels'])) {
			$args['labels'] = fs_cpt_default_labels($post_type);
		}
		register_post_type($post_type, $args);
	}
}

/**
 * Build default labels from post type key (fallback when labels not provided).
 *
 * @param string $post_type Post type key (e.g. 'project').
 * @return array<string, string>
 */
function fs_cpt_default_labels(string $post_type): array
{
	$name = ucfirst($post_type);
	$plural = $name . 's';
	return [
		'name'                  => $plural,
		'singular_name'         => $name,
		'menu_name'             => $plural,
		'add_new'               => 'Add New',
		'add_new_item'          => 'Add New ' . $name,
		'edit_item'             => 'Edit ' . $name,
		'new_item'              => 'New ' . $name,
		'view_item'             => 'View ' . $name,
		'view_items'            => 'View ' . $plural,
		'search_items'          => 'Search ' . $plural,
		'not_found'             => 'No ' . strtolower($plural) . ' found.',
		'not_found_in_trash'    => 'No ' . strtolower($plural) . ' found in Trash.',
		'all_items'             => 'All ' . $plural,
		'archives'              => $name . ' Archives',
		'attributes'            => $name . ' Attributes',
		'insert_into_item'      => 'Insert into ' . strtolower($name),
		'uploaded_to_this_item' => 'Uploaded to this ' . strtolower($name),
		'filter_items_list'     => 'Filter ' . strtolower($plural) . ' list',
		'items_list_navigation'=> $plural . ' list navigation',
		'items_list'            => $plural . ' list',
	];
}

add_action('init', 'fs_register_cpts', 0);
