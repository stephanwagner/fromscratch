<?php

/**
 * Theme settings: Design
 * Used by Settings → Theme → Design.
 * 
 * Edit to customize the theme. Design overrides can also be set in Settings → Theme → Design.
 */
return [
	/**
	 * Colors: used for colors in Settings → Theme → Design.
	 */
	'colors' => [
		// Primary colors
		['slug' => 'primary', 'color' => '#00aaff', 'name' => 'Primärfarbe'],
		['slug' => 'secondary', 'color' => '#00ddff', 'name' => 'Sekundärfarbe'],

		// Grayscale
		['slug' => 'white', 'color' => '#fff', 'name' => 'Weiß'],
		['slug' => 'black', 'color' => '#000', 'name' => 'Schwarz'],
		['slug' => 'off-black', 'color' => '#222', 'name' => 'Helleres Schwarz'],
		['slug' => 'gray-600', 'color' => '#666', 'name' => 'Grau 600'],
		['slug' => 'gray-500', 'color' => '#999', 'name' => 'Grau 500'],
		['slug' => 'gray-400', 'color' => '#ccc', 'name' => 'Grau 400'],
		['slug' => 'gray-300', 'color' => '#ddd', 'name' => 'Grau 300'],
		['slug' => 'gray-200', 'color' => '#eee', 'name' => 'Grau 200'],
		['slug' => 'gray-100', 'color' => '#f6f6f6', 'name' => 'Grau 100'],
	],

	/**
	 * Gradients: used for gradients in Settings → Theme → Design.
	 */
	'gradients' => [
		[
			'slug' => 'primary',
			'name' => 'Primär-Verlauf',
			'gradient' => 'linear-gradient(to right, #00aaff, #00ddff)',
		],
	],

	/**
	 * Font sizes: used for typography in Settings → Theme → Design.
	 */
	'font_sizes' => [
		['name' => 'Klein', 'shortName' => 'S', 'size' => 14, 'slug' => 's'],
		['name' => 'Normal', 'shortName' => 'M', 'size' => 16, 'slug' => 'm'],
		['name' => 'Groß', 'shortName' => 'L', 'size' => 18, 'slug' => 'l'],
		['name' => 'Extra groß', 'shortName' => 'XL', 'size' => 22, 'slug' => 'xl'],
	],

	/**
	 * Design tabs (Settings → Theme → Design). Each tab has title and sections.
	 * Tab title: English, translated in languages/*.po (context: Design tab).
	 * Section title: English, translated (context: Design section). Section needs 'id' for variable resolution.
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
				['id' => 'typography', 'title' => 'Text', 'variables' => [
					['id' => 'primary-font', 'title' => 'Schriftart', 'default' => 'Open Sans, sans-serif', 'type' => 'long-text'],
					['id' => 'default-text-color', 'title' => 'Standard Textfarbe', 'default' => '#222', 'type' => 'color'],
					['id' => 'default-font-size', 'title' => 'Standard Schriftgröße', 'default' => '16px', 'type' => 'text'],
					['id' => 'default-line-height', 'title' => 'Standard Zeilenhöhe', 'default' => '1.6', 'type' => 'text'],
					['id' => 'link-color', 'title' => 'Linkfarbe', 'default' => '#00aaff', 'type' => 'color'],
					['id' => 'link-hover-color', 'title' => 'Linkfarbe (hover)', 'default' => '#00ddff', 'type' => 'color'],
				]],
				['id' => 'font_sizes', 'title' => 'Font sizes', 'from' => 'font_sizes'],
			],
		],
		[
			'title' => 'Scaffold',
			'sections' => [
				['id' => 'content', 'title' => 'Content & spacing', 'variables' => [
					['id' => 'max-content-width', 'title' => 'Max. Inhaltsbreite', 'default' => '1200px', 'type' => 'text'],
					['id' => 'narrow-content-width', 'title' => 'Schmale Inhaltsbreite', 'default' => '900px', 'type' => 'text'],
					['id' => 'very-narrow-content-width', 'title' => 'Sehr schmale Breite', 'default' => '600px', 'type' => 'text'],
					['id' => 'content-padding-xl', 'title' => 'Innenabstand XL', 'default' => '64px', 'type' => 'text'],
					['id' => 'content-padding-l', 'title' => 'Innenabstand L', 'default' => '64px', 'type' => 'text'],
					['id' => 'content-padding-m', 'title' => 'Innenabstand M', 'default' => '48px', 'type' => 'text'],
					['id' => 'content-padding-s', 'title' => 'Innenabstand S', 'default' => '32px', 'type' => 'text'],
					['id' => 'content-padding-xs', 'title' => 'Innenabstand XS', 'default' => '24px', 'type' => 'text'],
				]],
			],
		],
		[
			'title' => 'Dimensions',
			'sections' => [
				['id' => 'dimensions', 'title' => 'Dimensions', 'variables' => [
					['id' => 'header-height', 'title' => 'Header-Höhe', 'default' => '120px', 'type' => 'text'],
					['id' => 'header-height-mobile', 'title' => 'Header-Höhe (mobil)', 'default' => '80px', 'type' => 'text'],
					['id' => 'header-height-scrolled', 'title' => 'Header-Höhe (gescrollt)', 'default' => '62px', 'type' => 'text'],
					['id' => 'mobile-menu-width', 'title' => 'Breite mobiles Menü', 'default' => '280px', 'type' => 'text'],
				]],
			],
		],
		[
			'title' => 'Breakpoints',
			'sections' => [
				['id' => 'breakpoints', 'title' => 'Breakpoints', 'variables' => [
					['id' => 'mobile-breakpoint', 'title' => 'Mobil-Umschlag', 'default' => '900px', 'type' => 'text'],
					['id' => 'breakpoint-xl', 'title' => 'Breakpoint XL', 'default' => '1400px', 'type' => 'text'],
					['id' => 'breakpoint-l', 'title' => 'Breakpoint L', 'default' => '1200px', 'type' => 'text'],
					['id' => 'breakpoint-m', 'title' => 'Breakpoint M', 'default' => '900px', 'type' => 'text'],
					['id' => 'breakpoint-s', 'title' => 'Breakpoint S', 'default' => '600px', 'type' => 'text'],
					['id' => 'breakpoint-xs', 'title' => 'Breakpoint XS', 'default' => '400px', 'type' => 'text'],
				]],
			],
		],
		[
			'title' => 'Other',
			'sections' => [
				['id' => 'transitions', 'title' => 'Transitions', 'variables' => [
					['id' => 'default-transition-speed', 'title' => 'Standard Übergangsdauer', 'default' => '280ms', 'type' => 'text'],
					['id' => 'slow-transition-speed', 'title' => 'Langsame Übergangsdauer', 'default' => '460ms', 'type' => 'text'],
				]],
				['id' => 'border-radius', 'title' => 'Border radius', 'variables' => [
					['id' => 'small-border-radius', 'title' => 'Klein', 'default' => '8px', 'type' => 'text'],
					['id' => 'default-border-radius', 'title' => 'Standard', 'default' => '16px', 'type' => 'text'],
				]],
			],
		],
	],
];
