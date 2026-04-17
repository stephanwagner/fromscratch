<?php

defined('ABSPATH') || exit;

/** Option name prefix for Content tab fields (Settings → Theme → Content). Format: {prefix}{section_id}_{variable_id}. */
if (!defined('FS_THEME_CONTENT_OPTION_PREFIX')) {
	define('FS_THEME_CONTENT_OPTION_PREFIX', 'theme_content_');
}

/**
 * Get theme config: config/theme.php merged with config/theme-design.php.
 *
 * @param string|null $key Optional. Dot path, e.g. 'menus', 'design.sections'.
 * @return array|mixed Full config if $key is null, else value at $key.
 */
function fs_config(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$base = include get_template_directory() . '/config/theme.php';
		$design = include get_template_directory() . '/config/theme-design.php';
		$config = array_merge($base, $design);
	}
	return fs_config_resolve($config, $key);
}

/**
 * Get theme settings: Content (config/theme-content.php). Used for Settings → Theme → Content.
 *
 * @param string|null $key Optional. Dot path, e.g. 'content.tabs', 'languages'.
 * @return array|mixed Full config if $key is null, else value at $key.
 */
function fs_config_settings(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$config = include get_template_directory() . '/config/theme-content.php';
	}
	return fs_config_resolve($config, $key);
}

/**
 * Get custom post types config (config/custom-post-types.php).
 *
 * @param string|null $key Optional. Dot path, e.g. 'cpts', 'cpts.project'.
 * @return array|mixed Full config if $key is null, else value at $key.
 */
function fs_config_cpt(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$file = get_template_directory() . '/config/custom-post-types.php';
		$config = is_file($file) ? include $file : ['cpts' => []];
	}
	return fs_config_resolve($config, $key);
}

/**
 * Get redirect config from theme config (config/theme.php → redirects). Method (wordpress/htaccess) is not exposed in the UI.
 *
 * @param string|null $key Optional. Dot path, e.g. 'method'.
 * @return array|mixed Full redirect config if $key is null, else value at $key.
 */
function fs_config_redirects(?string $key = null)
{
	$config = fs_config('redirects');
	if (!is_array($config)) {
		$config = ['method' => 'wordpress'];
	}
	return fs_config_resolve($config, $key);
}

/**
 * Whether Redis integration is enabled in theme config.
 */
function fs_config_redis_enabled(): bool
{
	$v = fs_config('redis_object_cache.enabled');
	if ($v === null) {
		// Backward compatibility with old key.
		$v = fs_config('redis.enabled');
	}
	if ($v === null) {
		return true;
	}
	return (bool) $v;
}

/**
 * Resolve dot-path key into config value.
 *
 * @param array $config Config array.
 * @param string|null $key Dot path or null.
 * @return array|mixed
 */
function fs_config_resolve(array $config, ?string $key)
{
	if ($key === null || $key === '') {
		return $config;
	}
	$keys = explode('.', $key);
	$val = $config;
	foreach ($keys as $k) {
		if (!is_array($val) || !array_key_exists($k, $val)) {
			return null;
		}
		$val = $val[$k];
	}
	return $val;
}
