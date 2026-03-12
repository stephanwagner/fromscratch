<?php
/*
Plugin Name: Developer Performance Panel
Description: Lightweight WordPress performance metrics and slow query logger.
Version: 1.0
Author: Stephan Wagner
*/

defined('ABSPATH') || exit;

define('FS_PERF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FS_PERF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FS_PERF_AS_PLUGIN', true);
define('FS_PERF_SETTINGS_PAGE', 'fs-performance-panel');

require FS_PERF_PLUGIN_DIR . 'inc/performance.php';
require FS_PERF_PLUGIN_DIR . 'inc/settings-page.php';

add_action('init', function (): void {
	if (!is_admin()) {
		return;
	}
	load_plugin_textdomain('fs-performance-panel', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 0);

register_activation_hook(__FILE__, function () {

	if (get_option('fromscratch_perf_slow_queries_enabled') === '1') {
		if (function_exists('fs_developer_perf_slow_queries_install_db_dropin')) {
			fs_developer_perf_slow_queries_install_db_dropin();
		}
	}

});

register_deactivation_hook(__FILE__, function () {

	if (function_exists('fs_developer_perf_slow_queries_uninstall_db_dropin')) {
		fs_developer_perf_slow_queries_uninstall_db_dropin();
	}

});