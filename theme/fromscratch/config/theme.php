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
	 * Comments.
	 * enabled: when false, comments are disabled globally in frontend and admin.
	 */
	'comments' => false,

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
	 * Login limit (wp-login failed attempts per IP).
	 * attempts / window / lockout — times in minutes (same shape as login_suspicious_attempts).
	 */
	'login_limit' => [
		'enabled' => false, // Enable login limit
		'attempts' => 5,   // Failed attempts per time window per IP
		'window' => 5,     // Time window in minutes
		'lockout' => 10,   // Lockout duration in minutes
	],

	/**
	 * Suspicious login attempts (Developer → Blocked IPs failed-login list).
	 * When threshold is reached within the observation window, the IP is temporarily blocked for lockout minutes,
	 * an email is sent to the developer, and the failed-login list shows how long the block lasts.
	 */
	'login_suspicious_attempts' => [
		'enabled' => true,
		'attempts' => 10,     // Failed attempts per time window per IP
		'window' => 30,       // Time window in minutes
		'lockout' => 60 * 24, // Lockout duration in minutes
		'send_email' => true, // Send email to developer when threshold is reached
	],

	/**
	 * Site password
	 * Cookie duration in days.
	 */
	'site_password_cookie_days' => 14,

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
	 */
	'nginx_site_cache' => [
		'enabled' => true,
	],
];
