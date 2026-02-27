<?php

defined('ABSPATH') || exit;

/**
 * Check if we are in debug mode (WP_DEBUG defined and true).
 *
 * @return bool True when WP_DEBUG is true, false otherwise.
 */
function fs_is_debug(): bool
{
	return defined('WP_DEBUG') && WP_DEBUG === true;
}

/**
 * Whether setup has been completed (install wizard finished successfully).
 *
 * @return bool True if setup is complete, false if not yet run.
 */
function fs_setup_completed(): bool
{
  return (bool) get_option('fromscratch_install_success');
}
