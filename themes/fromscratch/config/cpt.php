<?php

/**
 * Custom post types. Registered CPTs are included in the SEO panel when they support the editor.
 *
 * @see register_post_type() for all supported args.
 */
return [
	'cpts' => [
		// Example: uncomment and adjust to add a "Project" post type.
		// 'project' => [
		// 	'labels' => [
		// 		'name' => 'Projects',
		// 		'singular_name' => 'Project',
		// 		'menu_name' => 'Projects',
		// 		...
		// 	],
		// 	'public' => true,
		// 	'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes'],
		// 	'has_archive' => true,
		// 	'rewrite' => ['slug' => 'projects'],
		// 	...
		// ],
	],
];
