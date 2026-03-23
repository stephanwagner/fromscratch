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
	 * WordPress image threshold
	 * Set to false to disable the generation of the scaled 'full' image.
	 */
	'image_threshold' => false,

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
	'svg_max_size' => 4, // Max file size in MB

	/**
	 * WebP images
	 * The quality of the lossy WebP conversion if enabled.
	 * JPEG → lossy WebP
	 * PNG → lossless WebP
	 */
	'webp_quality' => 82, // Lossy WebP quality 1–100

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
	 * Suspicious login attempts threshold
	 * When an IP has at least this many attempts within the time window, it is shown as suspicious and can be blocked.
	 */
	'login_suspicious_attempts' => [
		'attempts' => 10,
		'minutes' => 30,
	],

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

	/**
	 * Redis integration in Developer settings.
	 * When false, Redis-specific UI/actions are hidden.
	 */
	'redis_object_cache' => [
		'enabled' => true,
	],

	/**
	 * Nginx site cache integration.
	 * enabled: globally enable/disable cache purge actions.
	 * purge_url: endpoint URL (relative like '/purge' or absolute URL).
	 */
	'nginx_site_cache' => [
		'enabled' => true,
		'purge_url' => '/purge',
	],
];
