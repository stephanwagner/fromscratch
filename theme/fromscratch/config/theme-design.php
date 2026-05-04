<?php

/**
 * Design tokens merged into theme config (fs_config()). Used for the block editor palette / gradients / font sizes (see inc/theme-setup.php).
 */
return [
	// Colors
	'colors' => [
		// Primary colors
		['slug' => 'primary', 'color' => '#4080ff', 'name' => 'Primary color'],
		['slug' => 'secondary', 'color' => '#00ddff', 'name' => 'Secondary color'],

		// Grayscale
		['slug' => 'white', 'color' => '#fff', 'name' => 'White'],
		['slug' => 'black', 'color' => '#000', 'name' => 'Black'],
		['slug' => 'off-black', 'color' => '#222', 'name' => 'Lighter black'],
		['slug' => 'gray-600', 'color' => '#666', 'name' => 'Gray 600'],
		['slug' => 'gray-500', 'color' => '#999', 'name' => 'Gray 500'],
		['slug' => 'gray-400', 'color' => '#ccc', 'name' => 'Gray 400'],
		['slug' => 'gray-300', 'color' => '#ddd', 'name' => 'Gray 300'],
		['slug' => 'gray-200', 'color' => '#eee', 'name' => 'Gray 200'],
		['slug' => 'gray-100', 'color' => '#f6f6f6', 'name' => 'Gray 100'],
	],

	// Gradients
	'gradients' => [
		[
			'slug' => 'primary',
			'name' => 'Primary gradient',
			'gradient' => 'linear-gradient(to right, #4080ff, #00ddff)',
		],
	],

	// Font sizes
	'font_sizes' => [
		['name' => 'Small', 'shortName' => 'S', 'size' => 14, 'slug' => 's'],
		['name' => 'Normal', 'shortName' => 'M', 'size' => 16, 'slug' => 'm'],
		['name' => 'Large', 'shortName' => 'L', 'size' => 18, 'slug' => 'l'],
		['name' => 'Extra large', 'shortName' => 'XL', 'size' => 22, 'slug' => 'xl'],
	],
];
