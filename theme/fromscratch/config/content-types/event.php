<?php
return [
	'event' => [
		'enabled' => true,
		'type' => 'event',
		'public' => true,
		'hierarchical' => true,

		'labels' => [
			'name' => 'Events',
			'singular_name' => 'Event',
			'menu_name' => 'Events',
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
			'event_category' => [
				'label' => 'Categories',
				'singular_label' => 'Category',
				'url' => 'event-category',
			],
		],
		'wp_categories' => false,

		'archive' => [
			'enabled' => true,
			'slug' => 'events',
			'design' => 'list',
			'texts' => [
				'heading' => __('Events', 'fromscratch'),
				'empty' => __('No events found.', 'fromscratch'),
			],
		],

		'query' => [
			'orderby' => 'date',
			'order' => 'DESC',
		],

		'admin' => [
			'menu_icon' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M212.31-100Q182-100 161-121q-21-21-21-51.31v-535.38Q140-738 161-759q21-21 51.31-21h55.38v-53.85q0-13.15 8.81-21.96 8.81-8.8 21.96-8.8 13.16 0 21.96 8.8 8.81 8.81 8.81 21.96V-780h303.08v-54.61q0-12.77 8.61-21.39 8.62-8.61 21.39-8.61 12.77 0 21.38 8.61 8.62 8.62 8.62 21.39V-780h55.38Q778-780 799-759q21 21 21 51.31v535.38Q820-142 799-121q-21 21-51.31 21H212.31Zm0-60h535.38q4.62 0 8.46-3.85 3.85-3.84 3.85-8.46v-375.38H200v375.38q0 4.62 3.85 8.46 3.84 3.85 8.46 3.85ZM320-410q-12.77 0-21.38-8.62Q290-427.23 290-440t8.62-21.38Q307.23-470 320-470h320q12.77 0 21.38 8.62Q670-452.77 670-440t-8.62 21.38Q652.77-410 640-410H320Zm0 160q-12.77 0-21.38-8.62Q290-267.23 290-280t8.62-21.38Q307.23-310 320-310h200q12.77 0 21.38 8.62Q550-292.77 550-280t-8.62 21.38Q532.77-250 520-250H320Z"/></svg>',
			'menu_position' => 5,
			'page_title_toggle' => false,
		],

	],
];
