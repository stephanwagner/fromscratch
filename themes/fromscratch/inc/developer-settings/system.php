<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'system';
$fs_developer_page_slug = fs_developer_settings_page_slug($fs_developer_tab);

add_action('admin_menu', function () use ($fs_developer_tab, $fs_developer_page_slug) {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$tabs = fs_developer_settings_available_tabs();
	if (!isset($tabs[$fs_developer_tab])) {
		return;
	}
	$label = $tabs[$fs_developer_tab]['label'];
	add_submenu_page(
		'options-general.php',
		__('Developer settings', 'fromscratch') . ' – ' . $label,
		sprintf(__('Developer › %s', 'fromscratch'), $label),
		'manage_options',
		$fs_developer_page_slug,
		'fs_render_developer_system',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

add_action('admin_init', function () use ($fs_developer_page_slug) {
	global $pagenow;
	if ($pagenow !== 'options-general.php' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if ((isset($_GET['page']) ? $_GET['page'] : '') !== $fs_developer_page_slug) {
		return;
	}
	if (!current_user_can('manage_options') || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$url = admin_url('options-general.php?page=fs-developer-system');

	// Administrator email
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL . '-options')) {
		$email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
		if (is_email($email)) {
			update_option('admin_email', $email);
		}
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}

	// Password protection only
	if (!empty($_POST['fromscratch_save_password']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_password')) {
		$prot = function_exists('fs_sanitize_site_password_protection') ? fs_sanitize_site_password_protection($_POST['fromscratch_site_password_protection'] ?? '') : '';
		update_option('fromscratch_site_password_protection', $prot);
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}

	// Maintenance mode only
	if (!empty($_POST['fromscratch_save_maintenance']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_maintenance')) {
		$mode = function_exists('fs_sanitize_maintenance_mode') ? fs_sanitize_maintenance_mode($_POST['fromscratch_maintenance_mode'] ?? '') : '';
		update_option('fromscratch_maintenance_mode', $mode);
		$title = function_exists('fs_sanitize_maintenance_title') ? fs_sanitize_maintenance_title($_POST['fromscratch_maintenance_title'] ?? '') : '';
		update_option('fromscratch_maintenance_title', $title);
		$desc = function_exists('fs_sanitize_maintenance_description') ? fs_sanitize_maintenance_description($_POST['fromscratch_maintenance_description'] ?? '') : '';
		update_option('fromscratch_maintenance_description', $desc);
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}
}, 1);

function fs_render_developer_system(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$system_saved = get_transient('fromscratch_system_saved');
	if ($system_saved !== false) {
		delete_transient('fromscratch_system_saved');
	}

	$site_password_on = get_option('fromscratch_site_password_protection') === '1';
	$site_password_hash = get_option('fromscratch_site_password_hash', '');
	$maintenance_on = get_option('fromscratch_maintenance_mode') === '1';
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($system_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<?php if (($site_password_on || $maintenance_on) && is_user_logged_in() && (current_user_can('edit_posts') || current_user_can('manage_options'))) : ?>
			<div class="notice notice-info inline" style="margin: 16px 0 0;">
				<p><?= esc_html__('Because you are logged in as an administrator or editor, you can still access the frontend. Open the site in a private or incognito window (or log out) to see the maintenance or password page.', 'fromscratch') ?></p>
			</div>
		<?php endif; ?>
		<?php if ($site_password_on && $site_password_hash === '') : ?>
			<div class="notice notice-warning inline" style="margin: 16px 0 0;">
				<p><?= esc_html__('No password set. Set a password below to activate protection.', 'fromscratch') ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Administrator email', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Changes the Administrator email instantly without notifying.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="admin_email"><?= esc_html__('Email address', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="admin_email" id="admin_email" value="<?= esc_attr(get_option('admin_email')) ?>" class="regular-text" autocomplete="email">
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>

		<form method="post" action="" class="page-settings-form">
			<?php wp_nonce_field('fromscratch_system_password'); ?>
			<input type="hidden" name="fromscratch_save_password" value="1">
			<h2 class="title"><?= esc_html__('Password protection', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('When enabled, visitors must enter a password before viewing any part of the site.', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('Logged-in administrators and editors skip the prompt.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Activate', 'fromscratch') ?></th>
					<td>
						<label>
							<input type="hidden" name="fromscratch_site_password_protection" value="0">
							<input type="checkbox" name="fromscratch_site_password_protection" value="1" <?= checked(get_option('fromscratch_site_password_protection'), '1', false) ?>>
							<?= esc_html__('Activate password protection', 'fromscratch') ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_site_password_new"><?= esc_html__('Password', 'fromscratch') ?></label></th>
					<td>
						<input type="password" name="fromscratch_site_password_new" id="fromscratch_site_password_new" class="small-text" style="width: 220px;" value="<?= esc_attr(get_option('fromscratch_site_password_plain', '')) ?>" autocomplete="new-password">
						<button type="button" class="button" id="fromscratch_site_password_copy" data-copy="<?= esc_attr__('Copy', 'fromscratch') ?>" data-copied="<?= esc_attr__('Copied!', 'fromscratch') ?>"><?= esc_html__('Copy', 'fromscratch') ?></button>
						<div style="margin-top: 8px;">
							<a class="fs-description-link -gray -has-icon" href="https://passwordcopy.app" target="_blank">
								<span class="fs-description-link-icon">
									<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
										<path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z" />
									</svg>
								</span>
								<span>passwordcopy.app</span>
							</a>
						</div>
						<p class="description">
							<?= esc_html__('Set or change the password. Leave blank and save to clear or reset the password.', 'fromscratch') ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>

		<form method="post" action="" class="page-settings-form">
			<?php wp_nonce_field('fromscratch_system_maintenance'); ?>
			<input type="hidden" name="fromscratch_save_maintenance" value="1">
			<h2 class="title"><?= esc_html__('Maintenance mode', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('When enabled, the entire frontend is blocked with HTTP 503.', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('Logged-in administrators and editors can still view the site.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Activate', 'fromscratch') ?></th>
					<td>
						<label>
							<input type="hidden" name="fromscratch_maintenance_mode" value="0">
							<input type="checkbox" name="fromscratch_maintenance_mode" value="1" <?= checked(get_option('fromscratch_maintenance_mode'), '1', false) ?>>
							<?= esc_html__('Enable maintenance mode', 'fromscratch') ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_maintenance_title"><?= esc_html__('Title', 'fromscratch') ?></label></th>
					<td>
						<input type="text" name="fromscratch_maintenance_title" id="fromscratch_maintenance_title" value="<?= esc_attr(get_option('fromscratch_maintenance_title', '')) ?>" class="regular-text" placeholder="<?= esc_attr__('Maintenance', 'fromscratch') ?>">
						<p class="description"><?= esc_html__('Heading shown on the maintenance page. Leave blank for default.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_maintenance_description"><?= esc_html__('Description', 'fromscratch') ?></label></th>
					<td>
						<textarea name="fromscratch_maintenance_description" id="fromscratch_maintenance_description" rows="3" class="large-text" placeholder="<?= esc_attr__('We are currently performing scheduled maintenance. Please check back shortly.', 'fromscratch') ?>" style="display: block;"><?= esc_textarea(get_option('fromscratch_maintenance_description', '')) ?></textarea>
						<p class="description"><?= esc_html__('Short message shown below the title. Leave blank for default.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var copyBtn = document.getElementById('fromscratch_site_password_copy');
				if (!copyBtn) return;
				copyBtn.addEventListener('click', function() {
					var input = document.getElementById('fromscratch_site_password_new');
					if (!input || !input.value) return;
					navigator.clipboard.writeText(input.value).then(function() {
						copyBtn.textContent = copyBtn.getAttribute('data-copied');
						setTimeout(function() {
							copyBtn.textContent = copyBtn.getAttribute('data-copy');
						}, 1500);
					});
				});
			});
		</script>
	</div>
	<?php
}
