<?php

/**
 * Custom post types: define each CPT and its settings.
 * Registered CPTs are automatically included in the SEO panel (title, description, OG image, noindex)
 * when they support the editor. Registration adds custom-fields support so the SEO meta is visible in the editor.
 *
 * @see register_post_type() for all supported args.
 */
return [
	'cpts' => [
		// Example: uncomment and adjust to add a "Project" post type.

		'project' => [
			'labels' => [
				'name'               => 'Projects',
				'singular_name'      => 'Project',
				'menu_name'          => 'Projects',
				'add_new'            => 'Add New',
				'add_new_item'       => 'Add New Project',
				'edit_item'          => 'Edit Project',
				'new_item'           => 'New Project',
				'view_item'          => 'View Project',
				'view_items'         => 'View Projects',
				'search_items'       => 'Search Projects',
				'not_found'          => 'No projects found.',
				'not_found_in_trash' => 'No projects found in Trash.',
				'all_items'          => 'All Projects',
				'archives'           => 'Project Archives',
				'attributes'         => 'Project Attributes',
				'insert_into_item'   => 'Insert into project',
				'uploaded_to_this_item' => 'Uploaded to this project',
				'filter_items_list'  => 'Filter projects list',
				'items_list_navigation' => 'Projects list navigation',
				'items_list'         => 'Projects list',
			],
			'description'    => '',
			'public'         => true,
			'exclude_from_search' => false,
			'publicly_queryable' => true,
			'show_ui'        => true,
			'show_in_nav_menus' => true,
			'show_in_menu'   => true,
			'show_in_admin_bar' => true,
			'menu_position'  => 5,
			'menu_icon'      => 'dashicons-portfolio',
			'capability_type' => 'post',
			'map_meta_cap'   => true,
			'supports'       => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes'],
			'has_archive'    => true,
			'rewrite'        => ['slug' => 'projects'],
			'query_var'      => true,
			'can_export'     => true,
			'show_in_rest'   => true,

			// TODO Test
			'hierarchical' => true,
		],
	],
];
