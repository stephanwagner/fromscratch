<?php

/**
 * Post types (built-in `post` + custom types registered via {@see fs_register_cpts()}).
 * Registered CPTs are included in the SEO panel when they support the editor.
 *
 * @see register_post_type() for all supported args.
 * Use `url` for the public path segment (sets `rewrite.slug`); e.g. `'url' => 'projects'`.
 *
 * Archive listing (frontend only):
 * - `archive_design`: `grid` | `list` (optional). Adds `archive__items--design-grid` or `archive__items--design-list` on the archive template.
 * - `orderby`: `date` | `title` | `menu_order` (optional). Default: `date` unless `has_order` is true, then `menu_order`.
 * - `order`: `ASC` | `DESC` (optional). Sensible defaults per orderby (date DESC, title ASC, menu_order ASC).
 *
 * Title visibility (optional, default false everywhere except pages):
 * - `has_page_title_toggle` — when true, editors get “Show title” in Summary and the singular template can hide the &lt;h1&gt;.
 *
 * Taxonomies:
 * - `has_categories` — shorthand to attach the shared core `category` taxonomy.
 *   On built-in `post`, set `has_categories => false` to detach the shared core category taxonomy.
 * - `taxonomies` — list existing taxonomy slugs (e.g. `['category']`) and/or custom taxonomy
 *   definitions keyed by slug (e.g. `'project_category' => ['label' => 'Projekt-Kategorien']`).
 * - Custom taxonomies default to category-style behavior: hierarchical, REST-enabled, admin column.
 * - Use `url` inside a custom taxonomy definition to set its rewrite slug.
 *
 * Parent / child (like pages):
 * - Set `hierarchical` => true (passed through to register_post_type).
 * - Include `page-attributes` in `supports` so the editor shows Parent and Order.
 * After enabling hierarchy, visit Settings → Permalinks and save once if URLs look wrong.
 */
return [
	// Built-in `post` — not registered via register_post_type(); only theme options below apply.
	'post' => [
		// Custom taxonomies for posts (registers + attaches). Omit or [] for none.
		'taxonomies' => [
			'blog_category' => [
				'label' => 'Kategorien',
				'singular_label' => 'Kategorie',
				'url' => 'blog-category',
			],
		],
		'has_categories' => false,
		'has_page_title_toggle' => false,
		'archive_design' => 'list',
	],

	/**
	 * Example: uncomment and adjust to add a "Project" post type.
	 */
	// 'project' => [
	// 	'labels' => [
	// 		'name' => 'Projects',
	// 		'singular_name' => 'Project',
	// 		'menu_name' => 'Projects',
	// 	],
	// 	'supports' => [
	// 		'title',
	// 		'editor',
	// 		'thumbnail',
	// 		'excerpt',
	// 		'revisions',
	// 		'page-attributes',
	// 		'custom-fields',
	// 		'author',
	// 	],
	// 	'taxonomies' => [
	// 		'project_category' => [
	// 			'label' => 'Categories',
	// 			'singular_label' => 'Category',
	// 			'url' => 'project-category',
	// 		],
	// 	],
	// 	'public' => true,
	// 	'hierarchical' => true,
	// 	'has_categories' => false,
	// 	'has_order' => false,
	// 	'has_archive' => true,
	// 	'has_page_title_toggle' => false,
	// 	'url' => 'projects',
	// 	'orderby' => 'date',
	// 	'order' => 'DESC',
	// 	'menu_icon' => '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M216-144q-29.7 0-50.85-21.15Q144-186.3 144-216v-528q0-29.7 21.15-50.85Q186.3-816 216-816h171q8-31 33.5-51.5T480-888q34 0 59.5 20.5T573-816h171q29.7 0 50.85 21.15Q816-773.7 816-744v528q0 29.7-21.15 50.85Q773.7-144 744-144H216Zm108-144h216q15.3 0 25.65-10.29Q576-308.58 576-323.79t-10.35-25.71Q555.3-360 540-360H324q-15.3 0-25.65 10.29Q288-339.42 288-324.21t10.35 25.71Q308.7-288 324-288Zm0-156h312q15.3 0 25.65-10.29Q672-464.58 672-479.79t-10.35-25.71Q651.3-516 636-516H324q-15.3 0-25.65 10.29Q288-495.42 288-480.21t10.35 25.71Q308.7-444 324-444Zm0-156h312q15.3 0 25.65-10.29Q672-620.58 672-635.79t-10.35-25.71Q651.3-672 636-672H324q-15.3 0-25.65 10.29Q288-651.42 288-636.21t10.35 25.71Q308.7-600 324-600Zm173-175q7-7 7-17t-7-17q-7-7-17-7t-17 7q-7 7-7 17t7 17q7 7 17 7t17-7Z"/></svg>',
	// 	'menu_position' => 5,
	// ],

	'project' => [
		'labels' => [
			'name' => 'Projekte',
			'singular_name' => 'Projekt',
			'menu_name' => 'Projekte',
		],
		'supports' => [
			'title',
			'editor',
			'thumbnail',
			'excerpt',
			'revisions',
			'page-attributes',
			'custom-fields',
			'author',
		],
		'taxonomies' => [
			'project_category' => [
				'label' => 'Kategorien',
				'singular_label' => 'Kategorie',
				'url' => 'project-category',
			],
		],
		'has_categories' => false,
		'has_page_title_toggle' => false,
		'public' => true,
		'hierarchical' => true,
		'has_order' => false,
		'has_archive' => true,
		'archive_design' => 'list', // grid | list
		'url' => 'projects',
		'orderby' => 'date',
		'order' => 'DESC',
		'menu_icon' => '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M216-144q-29.7 0-50.85-21.15Q144-186.3 144-216v-528q0-29.7 21.15-50.85Q186.3-816 216-816h171q8-31 33.5-51.5T480-888q34 0 59.5 20.5T573-816h171q29.7 0 50.85 21.15Q816-773.7 816-744v528q0 29.7-21.15 50.85Q773.7-144 744-144H216Zm108-144h216q15.3 0 25.65-10.29Q576-308.58 576-323.79t-10.35-25.71Q555.3-360 540-360H324q-15.3 0-25.65 10.29Q288-339.42 288-324.21t10.35 25.71Q308.7-288 324-288Zm0-156h312q15.3 0 25.65-10.29Q672-464.58 672-479.79t-10.35-25.71Q651.3-516 636-516H324q-15.3 0-25.65 10.29Q288-495.42 288-480.21t10.35 25.71Q308.7-444 324-444Zm0-156h312q15.3 0 25.65-10.29Q672-620.58 672-635.79t-10.35-25.71Q651.3-672 636-672H324q-15.3 0-25.65 10.29Q288-651.42 288-636.21t10.35 25.71Q308.7-600 324-600Zm173-175q7-7 7-17t-7-17q-7-7-17-7t-17 7q-7 7-7 17t7 17q7 7 17 7t17-7Z"/></svg>',
		'menu_position' => 5,
	],

	// 'event' => [
	// 	'labels' => [
	// 		'name' => 'Events',
	// 		'singular_name' => 'Event',
	// 		'menu_name' => 'Events',
	// 	],
	// 	'public' => true,
	// 	'supports' => [
	// 		'title',
	// 		'editor',
	// 		'thumbnail',
	// 		'excerpt',
	// 		'revisions',
	// 		'custom-fields',
	// 		'author',
	// 	],
	// 	'has_archive' => true,
	// 	'menu_position' => 6,
	// 	'url' => 'events',
	// 	'menu_icon' => 'dashicons-calendar-alt',
	// ],
];
