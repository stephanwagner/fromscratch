<?php

/**
 * Theme config: menus, meta, colors, design, image sizes, security, headers.
 * Edit this file to customize the theme. Design overrides can also be set in Settings → Theme → Design.
 */
return [
	/**
	 * Meta tags
	 * Added to the head section of the HTML document.
	 */
	'meta' => [
		'viewport' => 'width=device-width, initial-scale=1',
	],

	/**
	 * Menus
	 * Registered navigation menus.
	 */
	'menus' => [
		'main_menu' => 'Main menu',
		'footer_menu' => 'Footer menu',
	],

	/**
	 * Extra image sizes
	 * Width and height can be overridden in Settings → Media.
	 */
	'image_sizes_extra' => [
		['slug' => 'small', 'name' => 'Small', 'width' => 600, 'height' => 600],
	],

	/**
	 * Redirects
	 * Sets the redirect method.
	 *
	 * method: 'wordpress' = run redirects in PHP (template_redirect).
	 *         'htaccess'  = write rules to .htaccess (Apache; file must be writable).
	 * 
	 * If you run Apache, use 'htaccess' for better performance.
	 */
	'redirects' => [
		'method' => 'wordpress',
	],

	/**
	 * SVG support
	 * Large filesizes can cause memory issues.
	 */
	'svg_max_size' => 2, // Max file size in MB

	/**
	 * Login limit
	 * Locks out IP after N failed attempts per minute for M minutes.
	 */
	'login_limit' => true,       // Enable login limit
	'login_limit_attempts' => 5, // Failed attempts per minute per IP
	'login_limit_lockout' => 3,  // Lockout duration in minutes

	/**
	 * Site password
	 * Cookie duration in days.
	 */
	'site_password_cookie_days' => 14,

	/**
	 * HTTP response headers
	 * Front-end only.
	 */
	'headers' => [
		'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options' => 'SAMEORIGIN',
		'Referrer-Policy' => 'strict-origin-when-cross-origin',
		'X-Permitted-Cross-Domain-Policies' => 'none',
	],
];
