<?php

return [
	'project' => [
		'enabled' => true,
		'type' => 'default',
		'public' => true,
		'hierarchical' => true,

		'labels' => [
			'name' => 'Projects',
			'singular_name' => 'Project',
			'menu_name' => 'Projects',
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
				'label' => 'Categories',
				'singular_label' => 'Category',
				'url' => 'project-category',
			],
		],
		'wp_categories' => false,

		'archive' => [
			'enabled' => true,
			'slug' => 'projects',
			'design' => 'list',
			'texts' => [
				'heading' => __('Projects', 'fromscratch'),
				'empty' => __('No projects found.', 'fromscratch'),
			],
		],

		'query' => [
			'orderby' => 'date',
			'order' => 'DESC',
		],

		'admin' => [
			'menu_icon' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M212.31-140Q182-140 161-161q-21-21-21-51.31v-535.38Q140-778 161-799q21-21 51.31-21h535.38Q778-820 799-799q21 21 21 51.31v535.38Q820-182 799-161q-21 21-51.31 21H212.31ZM320-290h200q12.75 0 21.37-8.63 8.63-8.63 8.63-21.38 0-12.76-8.63-21.37Q532.75-350 520-350H320q-12.75 0-21.37 8.63-8.63 8.63-8.63 21.38 0 12.76 8.63 21.37Q307.25-290 320-290Zm0-160h320q12.75 0 21.37-8.63 8.63-8.63 8.63-21.38 0-12.76-8.63-21.37Q652.75-510 640-510H320q-12.75 0-21.37 8.63-8.63 8.63-8.63 21.38 0 12.76 8.63 21.37Q307.25-450 320-450Zm0-160h320q12.75 0 21.37-8.63 8.63-8.63 8.63-21.38 0-12.76-8.63-21.37Q652.75-670 640-670H320q-12.75 0-21.37 8.63-8.63 8.63-8.63 21.38 0 12.76 8.63 21.37Q307.25-610 320-610Z"/></svg>',
			'menu_position' => 5,
			'page_title_toggle' => false,
		],
	],
];
