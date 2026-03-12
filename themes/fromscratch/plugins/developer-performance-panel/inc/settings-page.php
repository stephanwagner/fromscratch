<?php

defined('ABSPATH') || exit;

$fs_perf_settings_slug = defined('FS_PERF_SETTINGS_PAGE') ? FS_PERF_SETTINGS_PAGE : 'fs-performance-panel';

add_action('admin_enqueue_scripts', function () use ($fs_perf_settings_slug): void {
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'settings_page_' . $fs_perf_settings_slug) {
		return;
	}
	wp_enqueue_style(
		'fs-perf-admin',
		FS_PERF_PLUGIN_URL . 'assets/admin-performance.css',
		[],
		'1.0'
	);
}, 10);

add_action('admin_menu', function () use ($fs_perf_settings_slug) {
	add_options_page(
		__('Performance', 'fs-performance-panel'),
		__('Performance', 'fs-performance-panel'),
		'manage_options',
		$fs_perf_settings_slug,
		'fs_perf_render_options_page',
		30
	);
}, 10);

add_action('load-settings_page_' . $fs_perf_settings_slug, function () use ($fs_perf_settings_slug): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!empty($_POST['fromscratch_clear_slow_queries']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_clear_slow_queries')) {
		delete_option('fs_perf_slow_queries');
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', $fs_perf_settings_slug, admin_url('options-general.php')));
		exit;
	}
	if (!empty($_POST['fromscratch_save_perf_admin_bar']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf_admin_bar')) {
		$on = isset($_POST['fromscratch_perf_admin_bar']) && $_POST['fromscratch_perf_admin_bar'] === '1';
		update_option('fromscratch_perf_admin_bar', $on ? '1' : '0');
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', $fs_perf_settings_slug, admin_url('options-general.php')));
		exit;
	}
	if (!empty($_POST['fromscratch_save_perf_guest']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf_guest')) {
		$on = isset($_POST['fromscratch_perf_panel_guest']) && $_POST['fromscratch_perf_panel_guest'] === '1';
		update_option('fromscratch_perf_panel_guest', $on ? '1' : '0');
		$raw = isset($_POST['fromscratch_perf_panel_guest_ips']) ? sanitize_text_field(wp_unslash($_POST['fromscratch_perf_panel_guest_ips'])) : '';
		$ips = array_filter(array_map('trim', explode(',', $raw)));
		$ips = array_filter($ips, static function ($ip) {
			return filter_var($ip, FILTER_VALIDATE_IP) !== false;
		});
		update_option('fromscratch_perf_panel_guest_ips', implode(', ', $ips));
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', $fs_perf_settings_slug, admin_url('options-general.php')));
		exit;
	}
	if (!empty($_POST['fromscratch_save_perf_slow_queries']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf_slow_queries')) {
		$on = isset($_POST['fromscratch_perf_slow_queries_enabled']) && $_POST['fromscratch_perf_slow_queries_enabled'] === '1';
		update_option('fromscratch_perf_slow_queries_enabled', $on ? '1' : '0');
		if (isset($_POST['fromscratch_perf_slow_queries_threshold']) && function_exists('fs_developer_perf_slow_queries_threshold_option')) {
			$thresh_ms = max(0.0, (float) sanitize_text_field(wp_unslash($_POST['fromscratch_perf_slow_queries_threshold'])));
			$thresh_sec = $thresh_ms / 1000;
			update_option(fs_developer_perf_slow_queries_threshold_option(), (string) $thresh_sec);
		}
		if ($on && function_exists('fs_developer_perf_slow_queries_install_db_dropin')) {
			$installed = fs_developer_perf_slow_queries_install_db_dropin();
			set_transient('fromscratch_perf_slow_queries_install_result', $installed ? '1' : '0', 30);
		} else {
			if (function_exists('fs_developer_perf_slow_queries_uninstall_db_dropin')) {
				fs_developer_perf_slow_queries_uninstall_db_dropin();
			}
			delete_transient('fromscratch_perf_slow_queries_install_result');
		}
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', $fs_perf_settings_slug, admin_url('options-general.php')));
		exit;
	}
}, 10);

function fs_perf_render_options_page(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fs-performance-panel'));
	}
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Performance', 'fs-performance-panel') . '</h1>';
	fs_perf_render_settings_page();
	echo '</div>';
}
