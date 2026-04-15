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
	$is_developer = function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
	$system_url = admin_url('options-general.php?page=' . fs_developer_settings_page_slug('system') . '#fs-search-visibility');
	$security_url = admin_url('options-general.php?page=fs-developer-security');
	$stats_url = fs_dashboard_statistics_url();
	$today_matomo = fs_dashboard_get_matomo_daily_visits(1);
	$today_visits = !empty($today_matomo) ? (int) ($today_matomo[0]['visits'] ?? 0) : 0;

	$scheduled = get_posts([
		'post_type'      => fs_theme_post_types(),
		'post_status'    => 'future',
		'posts_per_page' => 5,
		'orderby'        => 'date',
		'order'          => 'ASC',
	]);
?>
	<div class="fs-dashboard-panel">
		<div class="welcome-panel-content">

			<h2><?php esc_html_e('FromScratch', 'fromscratch'); ?></h2>

			<p class="about-description">
				<?php esc_html_e('Your development environment is ready.', 'fromscratch'); ?>
			</p>

			<div class="notice inline" style="margin: 0 0 1em 0; padding: 10px 12px;">
				<p style="margin: 0;">
					<strong><?= esc_html__('Today', 'fromscratch') ?>:</strong>
					<?= esc_html(sprintf(__('%1$s visits', 'fromscratch'), number_format_i18n($today_visits))) ?>
					· <a href="<?= esc_url($stats_url) ?>"><?= esc_html__('More analytics', 'fromscratch') ?></a>
				</p>
			</div>

			<?php if (!empty($scheduled)) : ?>
				<div class="notice inline" style="margin: 0 0 1em 0;">
					<p><strong><?= esc_html__('Scheduled posts', 'fromscratch') ?></strong></p>
					<ul style="margin: 0 0 0 16px;">
						<?php foreach ($scheduled as $item) : ?>
							<li>
								<a href="<?= esc_url(get_edit_post_link((int) $item->ID)) ?>"><?= esc_html(get_the_title((int) $item->ID) ?: __('(no title)', 'fromscratch')) ?></a>
								<span style="color:#646970;"> – <?= esc_html(get_date_from_gmt((string) $item->post_date_gmt, get_option('date_format') . ' ' . get_option('time_format'))) ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php
			$suspicious_ips = function_exists('fs_blocked_ips_suspicious_list') ? fs_blocked_ips_suspicious_list() : [];
			if (!empty($suspicious_ips)) :
			?>
				<div class="notice notice-warning inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Suspicious login attempts', 'fromscratch'); ?></strong></p>
					<p><?php esc_html_e('The following IPs exceeded the configured threshold and can be blocked.', 'fromscratch'); ?></p>
					<ul style="list-style: none; padding-left: 0;">
						<?php foreach ($suspicious_ips as $ip => $row) :
							$attempts = (int) ($row['attempts'] ?? 0);
						?>
							<li style="margin-bottom: 0.5em;">
								<code><?php echo esc_html($ip); ?></code> – <?php echo (int) $attempts; ?> <?php echo esc_html(_n('attempt', 'attempts', $attempts, 'fromscratch')); ?>
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

			<?php if ($is_developer && (int) get_option('blog_public', 1) === 0) : ?>
				<div class="notice notice-warning inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Search engines are asked not to index this site.', 'fromscratch'); ?></strong></p>
					<p><a href="<?php echo esc_url($system_url); ?>"><?php esc_html_e('Enable search engine indexing in Developer → System', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ($is_developer && get_option('fromscratch_site_password_protection') === '1') : ?>
				<div class="notice notice-info inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Password protection is active.', 'fromscratch'); ?></strong></p>
					<p><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<?php if ($is_developer && get_option('fromscratch_maintenance_mode') === '1') : ?>
				<div class="notice notice-info inline" style="margin: 0 0 1em 0;">
					<p><strong><?php esc_html_e('Maintenance mode is active.', 'fromscratch'); ?></strong></p>
					<p><a href="<?php echo esc_url($security_url); ?>"><?php esc_html_e('Manage in Developer → Security', 'fromscratch'); ?></a></p>
				</div>
			<?php endif; ?>

			<div class="welcome-panel-column-container">
				<div class="welcome-panel-column">
					<h3><?= esc_html__('Quick links', 'fromscratch') ?></h3>
					<ul>
						<li><a href="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings')) ?>"><?= esc_html__('Theme settings', 'fromscratch') ?></a></li>
						<li><a href="<?= esc_url(admin_url('post-new.php?post_type=page')) ?>"><?= esc_html__('Create page', 'fromscratch') ?></a></li>
						<li><a href="<?= esc_url($stats_url) ?>"><?= esc_html__('Open analytics', 'fromscratch') ?></a></li>
					</ul>
				</div>

			</div>

		</div>
	</div>
<?php
}
add_action('welcome_panel', 'fs_dashboard_panel');
