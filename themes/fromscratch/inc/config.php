<?php

defined('ABSPATH') || exit;

/**
 * Get theme config
 *
 * @param string|null $key Optional. Key or dot path, e.g. 'menus', 'meta.viewport'.
 * @return array|mixed Full config array if $key is null, or the value at $key.
 */
function fs_config(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$config = include get_template_directory() . '/config.php';
	}
	if ($key === null) {
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

/**
 * Get theme config-variables
 *
 * @param string|null $key Optional. Key or dot path.
 * @return array|mixed Full config array if $key is null, or the value at $key.
 */
function fs_config_variables(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$config = include get_template_directory() . '/config-variables.php';
	}
	if ($key === null) {
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

/**
 * Get custom post types config (config-cpt.php).
 *
 * @param string|null $key Optional. Key or dot path, e.g. 'cpts', 'cpts.project'.
 * @return array|mixed Full config array if $key is null, or the value at $key.
 */
function fs_config_cpt(?string $key = null)
{
	static $config = null;
	if ($config === null) {
		$file = get_template_directory() . '/config-cpt.php';
		$config = is_file($file) ? include $file : ['cpts' => []];
	}
	if ($key === null) {
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
