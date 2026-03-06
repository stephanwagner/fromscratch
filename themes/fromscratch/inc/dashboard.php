<?php

defined('ABSPATH') || exit;

/**
 * Remove WordPress Events and News panel
 */
add_action('wp_dashboard_setup', function () {
	remove_meta_box('dashboard_primary', 'dashboard', 'side');
}, 999);

/**
 * Force welcome panel to be shown
 */
add_filter('get_user_metadata', function ($value, $user_id, $meta_key) {
	if ($meta_key === 'show_welcome_panel') {
		return 1;
	}
	return $value;
}, 10, 3);

/**
 * Remove Welcome panel
 */
add_action('admin_init', function () {
	remove_action('welcome_panel', 'wp_welcome_panel');
});

/**
 * Hide Welcome panel checkbox
 */
add_action('admin_head', function () {

	$screen = get_current_screen();

	if ($screen->id !== 'dashboard') {
		return;
	}

	echo '<style>
        #screen-options-wrap label[for="wp_welcome_panel-hide"] {
            display:none;
        }
    </style>';
});

/**
 * Add a custom welcome panel
 */
function fs_dashboard_panel()
{
?>
	<div class="fs-dashboard-panel welcome-panel">
		<div class="welcome-panel-content">

			<h2><?php esc_html_e('FromScratch', 'fromscratch'); ?></h2>

			<p class="about-description">
				<?php esc_html_e('Your development environment is ready.', 'fromscratch'); ?>
			</p>

			<div class="welcome-panel-column-container">

				<div class="welcome-panel-column">
					<h3>Status</h3>
					<ul>
						<li>✔ Dev tools enabled</li>
						<li>✔ Login protection active</li>
					</ul>
				</div>

				<div class="welcome-panel-column">
					<h3>Quick links</h3>
					<ul>
						<li><a href="#">Theme settings</a></li>
						<li><a href="#">Create page</a></li>
					</ul>
				</div>

			</div>

		</div>
	</div>
<?php
}
add_action('welcome_panel', 'fs_dashboard_panel');
