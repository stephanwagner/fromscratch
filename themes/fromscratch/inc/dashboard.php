<?php

defined('ABSPATH') || exit;

/**
 * Remove WordPress Events and News panel
 */
add_action('wp_dashboard_setup', function () {
	remove_meta_box('dashboard_primary', 'dashboard', 'side');
}, 20);

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

			<?php
			$suspicious_ips = function_exists('fs_blocked_ips_suspicious_list') ? fs_blocked_ips_suspicious_list() : [];
			if (!empty($suspicious_ips)) :
				$security_url = admin_url('options-general.php?page=fs-developer-security');
			?>
				<div class="notice notice-warning inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Suspicious login attempts', 'fromscratch'); ?></strong></p>
					<p><?php esc_html_e('The following IPs exceeded the configured threshold and can be blocked.', 'fromscratch'); ?></p>
					<ul style="list-style: none; padding-left: 0;">
						<?php foreach ($suspicious_ips as $ip => $row) :
							$attempts = (int) ($row['attempts'] ?? 0);
						?>
							<li style="margin-bottom: 0.5em;">
								<code><?php echo esc_html($ip); ?></code> — <?php echo (int) $attempts; ?> <?php echo esc_html(_n('attempt', 'attempts', $attempts, 'fromscratch')); ?>
								<form method="post" action="<?php echo esc_url($security_url); ?>" style="display: inline; margin-left: 8px;">
									<?php wp_nonce_field('fromscratch_block_ip'); ?>
									<input type="hidden" name="fromscratch_block_ip" value="<?php echo esc_attr($ip); ?>">
									<button type="submit" name="fromscratch_do_block_ip" value="1" class="button button-small"><?php esc_html_e('Block IP', 'fromscratch'); ?></button>
								</form>
							</li>
						<?php endforeach; ?>
					</ul>
					<p><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage on Security page', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

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
