<?php

/**
 * Theme config: menus, meta, colors, design, image sizes, security, headers.
 * Edit this file to customize the theme. Design overrides can also be set in Settings → Theme → Design.
 */
return [
	/**
	 * Meta tags: Added to the head section of the HTML document.
	 */
	'meta' => [
		'viewport' => 'width=device-width, initial-scale=1',
	],

	/**
	 * Menus: Registered navigation menus.
	 */
	'menus' => [
		'main_menu' => 'Main menu',
		'footer_menu' => 'Footer menu',
	],

	/**
	 * Colors: used for colors in Settings → Theme → Design.
	 */
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

	/**
	 * Gradients: used for gradients in Settings → Theme → Design.
	 */
	'theme_gradients' => [
		[
			'slug' => 'primary',
			'name' => 'Primär',
			'gradient' => 'linear-gradient(to bottom, #00aaff, #00ddff)',
		],
	],

	/**
	 * Font sizes: used for typography in Settings → Theme → Design.
	 */
	'theme_font_sizes' => [
		['name' => 'Klein', 'shortName' => 'S', 'size' => 14, 'slug' => 's'],
		['name' => 'Normal', 'shortName' => 'M', 'size' => 16, 'slug' => 'm'],
		['name' => 'Groß', 'shortName' => 'L', 'size' => 18, 'slug' => 'l'],
		['name' => 'Extra groß', 'shortName' => 'XL', 'size' => 22, 'slug' => 'xl'],
	],

	/**
	 * Design variables: overridable in Settings → Theme → Design.
	 * IDs become CSS custom properties (e.g. "color-primary" → --color-primary).
	 * Sections with 'from' => 'theme_colors' etc. are derived from the arrays above.
	 */
	'design' => [
		'sections' => [
			'colors' => [
				'title' => 'Farben',
				'from' => 'theme_colors',
				'variables' => [
					['id' => 'color-error', 'title' => 'Fehler', 'default' => '#f33', 'type' => 'color'],
					['id' => 'color-warning', 'title' => 'Warnung', 'default' => '#fc0', 'type' => 'color'],
					['id' => 'color-success', 'title' => 'Erfolg', 'default' => '#5d5', 'type' => 'color'],
				],
			],
			'gradients' => ['title' => 'Verläufe', 'from' => 'theme_gradients'],
			'font_sizes' => ['title' => 'Schriftgrößen', 'from' => 'theme_font_sizes'],
			'typography' => [
				'title' => 'Typografie',
				'variables' => [
					['id' => 'primary-font', 'title' => 'Schriftart', 'default' => 'Open Sans, sans-serif', 'type' => 'text'],
					['id' => 'default-text-color', 'title' => 'Standard Textfarbe', 'default' => '#222', 'type' => 'color'],
					['id' => 'default-font-size', 'title' => 'Standard Schriftgröße', 'default' => '16px', 'type' => 'text'],
					['id' => 'default-line-height', 'title' => 'Standard Zeilenhöhe', 'default' => '1.6', 'type' => 'text'],
				],
			],
			'content' => [
				'title' => 'Inhalt & Abstände',
				'variables' => [
					['id' => 'max-content-width', 'title' => 'Max. Inhaltsbreite', 'default' => '1200px', 'type' => 'text'],
					['id' => 'narrow-content-width', 'title' => 'Schmale Inhaltsbreite', 'default' => '900px', 'type' => 'text'],
					['id' => 'very-narrow-content-width', 'title' => 'Sehr schmale Breite', 'default' => '600px', 'type' => 'text'],
					['id' => 'content-padding-xl', 'title' => 'Innenabstand XL', 'default' => '64px', 'type' => 'text'],
					['id' => 'content-padding-l', 'title' => 'Innenabstand L', 'default' => '64px', 'type' => 'text'],
					['id' => 'content-padding-m', 'title' => 'Innenabstand M', 'default' => '48px', 'type' => 'text'],
					['id' => 'content-padding-s', 'title' => 'Innenabstand S', 'default' => '32px', 'type' => 'text'],
					['id' => 'content-padding-xs', 'title' => 'Innenabstand XS', 'default' => '24px', 'type' => 'text'],
				],
			],
			'header' => [
				'title' => 'Header',
				'variables' => [
					['id' => 'header-height', 'title' => 'Header-Höhe', 'default' => '120px', 'type' => 'text'],
					['id' => 'header-height-mobile', 'title' => 'Header-Höhe (mobil)', 'default' => '80px', 'type' => 'text'],
					['id' => 'header-height-scrolled', 'title' => 'Header-Höhe (gescrollt)', 'default' => '62px', 'type' => 'text'],
				],
			],
			'menu' => [
				'title' => 'Menü',
				'variables' => [
					['id' => 'menu-width', 'title' => 'Menübreite', 'default' => '280px', 'type' => 'text'],
				],
			],
			'breakpoints' => [
				'title' => 'Breakpoints',
				'variables' => [
					['id' => 'mobile-breakpoint', 'title' => 'Mobil-Umschlag', 'default' => '900px', 'type' => 'text'],
					['id' => 'breakpoint-xl', 'title' => 'Breakpoint XL', 'default' => '1400px', 'type' => 'text'],
					['id' => 'breakpoint-l', 'title' => 'Breakpoint L', 'default' => '1200px', 'type' => 'text'],
					['id' => 'breakpoint-m', 'title' => 'Breakpoint M', 'default' => '900px', 'type' => 'text'],
					['id' => 'breakpoint-s', 'title' => 'Breakpoint S', 'default' => '600px', 'type' => 'text'],
					['id' => 'breakpoint-xs', 'title' => 'Breakpoint XS', 'default' => '400px', 'type' => 'text'],
				],
			],
			'transitions' => [
				'title' => 'Übergänge',
				'variables' => [
					['id' => 'default-transition-speed', 'title' => 'Standard Übergangsdauer', 'default' => '280ms', 'type' => 'text'],
					['id' => 'slow-transition-speed', 'title' => 'Langsame Übergangsdauer', 'default' => '460ms', 'type' => 'text'],
				],
			],
			'border-radius' => [
				'title' => 'Eckenradius',
				'variables' => [
					['id' => 'small-border-radius', 'title' => 'Klein', 'default' => '8px', 'type' => 'text'],
					['id' => 'default-border-radius', 'title' => 'Standard', 'default' => '16px', 'type' => 'text'],
				],
			],
		],
	],

	/**
	 * Extra image sizes: added to Settings → Media and registered with add_image_size().
	 * Width and height can be overridden in Settings → Media.
	 */
	'image_sizes_extra' => [
		['slug' => 'small', 'name' => 'Small', 'width' => 600, 'height' => 600],
	],

	/**
	 * SVG support: max file size in MB
	 * SVGs are sanitized on upload, large filesizes can cause memory issues.
	 */
	'svg_max_size' => 2,

	/**
	 * Login limit: failed attempts per minute per IP, lockout duration (from config only).
	 */
	'login_limit' => true, // Enable login limit
	'login_limit_attempts' => 5, // failed attempts per minute per IP
	'login_limit_lockout' => 3, // lockout duration in minutes

	/**
	 * Site password: cookie duration in days.
	 */
	'site_password_cookie_days' => 14,

	/**
	 * HTTP response headers (front-end only).
	 */
	'headers' => [
		'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options' => 'SAMEORIGIN',
		'Referrer-Policy' => 'strict-origin-when-cross-origin',
		'X-Permitted-Cross-Domain-Policies' => 'none',
	],
];
