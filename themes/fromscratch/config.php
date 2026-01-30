<?php
return [
	// Disable blogs
	'disable_blogs' => false,

	// Meta tags
	'meta' => [
		'viewport' => 'width=device-width, initial-scale=1',
	],

	// Menus
	'menus' => [
		'main_menu' => fs_t('MENU_MAIN_MENU'),
		'footer_menu' => fs_t('MENU_FOOTER_MENU'),
	],

	// The length of excerpts
	'excerpt_length' => 60,

	// The text to show after the excerpt if it was truncated
	'excerpt_more' => '...',

	// Colors
	'theme_colors' => [
		// Primary colors
		['slug' => 'primary', 'color' => '#00aaff', 'name' => 'Primärfarbe'],
		['slug' => 'secondary', 'color' => '#00ddff', 'name' => 'Sekundärfarbe'],

		// Grayscale
		['slug' => 'black', 'color' => '#000', 'name' => 'Schwarz'],
		['slug' => 'off-black', 'color' => '#222', 'name' => 'Helleres Schwarz'],
		['slug' => 'gray-600', 'color' => '#666', 'name' => 'Grau 600'],
		['slug' => 'gray-500', 'color' => '#999', 'name' => 'Grau 500'],
		['slug' => 'gray-400', 'color' => '#ccc', 'name' => 'Grau 400'],
		['slug' => 'gray-300', 'color' => '#ddd', 'name' => 'Grau 300'],
		['slug' => 'gray-200', 'color' => '#eee', 'name' => 'Grau 200'],
		['slug' => 'gray-100', 'color' => '#f6f6f6', 'name' => 'Grau 100'],
		['slug' => 'white', 'color' => '#fff', 'name' => 'Weiß'],
	],

	// Gradients
	'theme_gradients' => [
		[
			'slug' => 'primary',
			'name' => 'Primär',
			'gradient' => 'linear-gradient(to bottom, #00aaff, #00ddff)',
		],
	],

	// Font sizes
	'theme_font_sizes' => [
		[
			'name' => 'Klein',
			'shortName' => 'S',
			'size' => 14,
			'slug' => 's',
		],
		[
			'name' => 'Normal',
			'shortName' => 'M',
			'size' => 16,
			'slug' => 'm',
		],
		[
			'name' => 'Groß',
			'shortName' => 'L',
			'size' => 18,
			'slug' => 'l',
		],
		[
			'name' => 'Extra groß',
			'shortName' => 'XL',
			'size' => 22,
			'slug' => 'xl',
		],
	]
];
