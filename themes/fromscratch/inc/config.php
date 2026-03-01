<?php

defined('ABSPATH') || exit;

/**
 * Get theme config (config/theme.php).
 *
 * @param string|null $key Optional. Dot path, e.g. 'menus', 'design.sections'.
 * @return array|mixed Full config if $key is null, else value at $key.
 */
function fs_config(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$config = include get_template_directory() . '/config/theme.php';
	}
	return fs_config_resolve($config, $key);
}

/**
 * Get theme settings config (config/settings.php). Used for Settings → Theme page.
 *
 * @param string|null $key Optional. Dot path, e.g. 'title_page', 'variables.sections'.
 * @return array|mixed Full config if $key is null, else value at $key.
 */
function fs_config_settings(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$config = include get_template_directory() . '/config/settings.php';
	}
	return fs_config_resolve($config, $key);
}

/**
 * Get custom post types config (config/cpt.php).
 *
 * @param string|null $key Optional. Dot path, e.g. 'cpts', 'cpts.project'.
 * @return array|mixed Full config if $key is null, else value at $key.
 */
function fs_config_cpt(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$file = get_template_directory() . '/config/cpt.php';
		$config = is_file($file) ? include $file : ['cpts' => []];
	}
	return fs_config_resolve($config, $key);
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
