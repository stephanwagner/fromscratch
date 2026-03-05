<?php

defined('ABSPATH') || exit;

/**
 * Shared navigation for Developer settings (tab bar). Include this in each developer page.
 * Expects fs_developer_settings_current_tab_from_page() and fs_developer_settings_available_tabs().
 */
function fs_developer_settings_render_nav(): void
{
	$current = fs_developer_settings_current_tab_from_page();
	$tabs = fs_developer_settings_available_tabs();
	$base_url = admin_url('options-general.php');

	$password_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5 58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm296.5-223.5Q560-327 560-360t-23.5-56.5Q513-440 480-440t-56.5 23.5Q400-393 400-360t23.5 56.5Q447-280 480-280t56.5-23.5ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg>';
	$maintenance_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-360q-100 0-170-70t-70-170q0-20 3-40t11-38q5-10 12.5-15t16.5-7q9-2 18.5.5T199-689l105 105 72-72-105-105q-8-8-10.5-17.5T260-797q2-9 7-16.5t15-12.5q18-8 38-11t40-3q100 0 170 70t70 170q0 23-4 43.5T584-516l202 200q29 29 29 71t-29 71q-29 29-71 29t-71-30L444-376q-20 8-40.5 12t-43.5 4Z"/></svg>';
	?>
	<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
		<?php
		foreach ($tabs as $slug => $def) {
			$icons = '';
			if ($slug === 'security') {
				if (get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') {
					$icons .= '<div class="fs-tab-icon">' . $password_icon . '</div>';
				}
				if (get_option('fromscratch_maintenance_mode') === '1') {
					$icons .= '<div class="fs-tab-icon">' . $maintenance_icon . '</div>';
				}
			}
			$url = add_query_arg('page', fs_developer_settings_page_slug($slug), $base_url);
			echo '<a href="' . esc_url($url) . '" class="nav-tab ' . ($current === $slug ? 'nav-tab-active' : '') . ($icons !== '' ? ' has-icons' : '') . '">';
			echo '<span>' . esc_html(__($def['label'], 'fromscratch')) . '</span>';
			if ($icons !== '') {
				echo '<span class="fs-tab-icons">' . $icons . '</span>';
			}
			echo '</a>';
		}
		?>
	</nav>
	<?php
}
