<?php

/**
 * Theme settings: Design
 * Used by Settings → Theme → Design.
 *
 * Edit to customize the theme. Design overrides can also be set in Settings → Theme → Design.
 * User-facing strings are English (msgids); German lives in languages/fromscratch-de_DE.po.
 */
return [
	/**
	 * Colors: used for colors in Settings → Theme → Design.
	 */
	'colors' => [
		// Primary colors
		['slug' => 'primary', 'color' => '#00aaff', 'name' => 'Primary color'],
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

	/**
	 * Gradients: used for gradients in Settings → Theme → Design.
	 */
	'gradients' => [
		[
			'slug' => 'primary',
			'name' => 'Primary gradient',
			'gradient' => 'linear-gradient(to right, #00aaff, #00ddff)',
		],
	],

	/**
	 * Font sizes: used for typography in Settings → Theme → Design.
	 */
	'font_sizes' => [
		['name' => 'Small', 'shortName' => 'S', 'size' => 14, 'slug' => 's'],
		['name' => 'Normal', 'shortName' => 'M', 'size' => 16, 'slug' => 'm'],
		['name' => 'Large', 'shortName' => 'L', 'size' => 18, 'slug' => 'l'],
		['name' => 'Extra large', 'shortName' => 'XL', 'size' => 22, 'slug' => 'xl'],
	],

	/**
	 * Design tabs (Settings → Theme → Design). Each tab has title and sections.
	 * Tab/section titles are English; theme-settings wraps them with _x(…, 'Design tab'|'Design section').
	 */
	'design' => [
		[
			'title' => 'Colors',
			'sections' => [
				['id' => 'colors', 'title' => 'Colors', 'from' => 'colors'],
				['id' => 'gradients', 'title' => 'Gradients', 'from' => 'gradients'],
			],
		],
		[
			'title' => 'Text',
			'sections' => [
				[
					'id' => 'typography',
					'title' => 'Text',
					'variables' => [
						['id' => 'default-font-size', 'title' => 'Default font size', 'default' => '16px', 'type' => 'text'],
						['id' => 'default-line-height', 'title' => 'Default line height', 'default' => '1.6', 'type' => 'text'],
						['id' => 'default-text-color', 'title' => 'Default text color', 'default' => '#222', 'type' => 'color'],
						['id' => 'link-color', 'title' => 'Link color', 'default' => '#00aaff', 'type' => 'color'],
						['id' => 'link-hover-color', 'title' => 'Link color (hover)', 'default' => '#00ddff', 'type' => 'color'],
					],
				],
				['id' => 'font_sizes', 'title' => 'Font sizes', 'from' => 'font_sizes'],
			],
		],
		[
			'title' => 'Scaffold',
			'sections' => [
				[
					'id' => 'content',
					'title' => 'Content',
					'variables' => [
						['id' => 'max-content-width', 'title' => 'Max content width', 'default' => '1200px', 'type' => 'text'],
						['id' => 'narrow-content-width', 'title' => 'Narrow content width', 'default' => '900px', 'type' => 'text'],
						['id' => 'very-narrow-content-width', 'title' => 'Very narrow width', 'default' => '600px', 'type' => 'text'],
						['id' => 'content-padding-xl', 'title' => 'Padding XL', 'default' => '64px', 'type' => 'text'],
						['id' => 'content-padding-l', 'title' => 'Padding L', 'default' => '64px', 'type' => 'text'],
						['id' => 'content-padding-m', 'title' => 'Padding M', 'default' => '48px', 'type' => 'text'],
						['id' => 'content-padding-s', 'title' => 'Padding S', 'default' => '32px', 'type' => 'text'],
						['id' => 'content-padding-xs', 'title' => 'Padding XS', 'default' => '24px', 'type' => 'text'],
					],
				],
			],
		],
		[
			'title' => 'Dimensions',
			'sections' => [
				[
					'id' => 'dimensions',
					'title' => 'Dimensions',
					'variables' => [
						['id' => 'header-height', 'title' => 'Header height', 'default' => '120px', 'type' => 'text'],
						['id' => 'header-height-mobile', 'title' => 'Header height (mobile)', 'default' => '80px', 'type' => 'text'],
						['id' => 'header-height-scrolled', 'title' => 'Header height (scrolled)', 'default' => '62px', 'type' => 'text'],
						['id' => 'mobile-menu-width', 'title' => 'Mobile menu width', 'default' => '280px', 'type' => 'text'],
					],
				],
			],
		],
		[
			'title' => 'Breakpoints',
			'sections' => [
				[
					'id' => 'breakpoints',
					'title' => 'Breakpoints',
					'variables' => [
						['id' => 'mobile-breakpoint', 'title' => 'Mobile breakpoint', 'default' => '900px', 'type' => 'text'],
						['id' => 'breakpoint-xl', 'title' => 'Breakpoint XL', 'default' => '1400px', 'type' => 'text'],
						['id' => 'breakpoint-l', 'title' => 'Breakpoint L', 'default' => '1200px', 'type' => 'text'],
						['id' => 'breakpoint-m', 'title' => 'Breakpoint M', 'default' => '900px', 'type' => 'text'],
						['id' => 'breakpoint-s', 'title' => 'Breakpoint S', 'default' => '600px', 'type' => 'text'],
						['id' => 'breakpoint-xs', 'title' => 'Breakpoint XS', 'default' => '400px', 'type' => 'text'],
					],
				],
			],
		],
		[
			'title' => 'Other',
			'sections' => [
				[
					'id' => 'transitions',
					'title' => 'Transitions',
					'variables' => [
						['id' => 'default-transition-speed', 'title' => 'Default transition duration', 'default' => '280ms', 'type' => 'text'],
						['id' => 'slow-transition-speed', 'title' => 'Slow transition duration', 'default' => '460ms', 'type' => 'text'],
					],
				],
				[
					'id' => 'border-radius',
					'title' => 'Border radius',
					'variables' => [
						['id' => 'small-border-radius', 'title' => 'Small radius', 'default' => '8px', 'type' => 'text'],
						['id' => 'default-border-radius', 'title' => 'Default radius', 'default' => '16px', 'type' => 'text'],
					],
				],
			],
		],
	],
];
