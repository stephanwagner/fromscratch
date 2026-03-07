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

	// Search engine visibility (blog_public)
	if (!empty($_POST['fromscratch_save_search_visibility']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_search_visibility')) {
		$discourage = !empty($_POST['blog_public_discourage']);
		update_option('blog_public', $discourage ? '0' : '1');
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url . '#fs-search-visibility');
		exit;
	}

	// Email addresses (admin, report, developer)
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL . '-options')) {
		$admin_email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
		if (is_email($admin_email)) {
			update_option('admin_email', $admin_email);
		}
		$report_email = isset($_POST['fromscratch_report_email']) ? sanitize_email(wp_unslash($_POST['fromscratch_report_email'])) : '';
		update_option('fromscratch_report_email', is_email($report_email) ? $report_email : '');
		$developer_email = isset($_POST['fromscratch_developer_email']) ? sanitize_email(wp_unslash($_POST['fromscratch_developer_email'])) : '';
		update_option('fromscratch_developer_email', is_email($developer_email) ? $developer_email : '');
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
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($system_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<?php if ((int) get_option('blog_public', 1) === 0) : ?>
			<div class="notice notice-warning inline" style="margin: 16px 0 0;">
				<p><strong><?= esc_html__('Search engines are asked not to index this site.', 'fromscratch') ?></strong></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Email addresses', 'fromscratch') ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="admin_email"><?= esc_html__('Admin email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="admin_email" id="admin_email" value="<?= esc_attr(get_option('admin_email')) ?>" class="regular-text" autocomplete="email">
						<p class="description"><?= esc_html__('WordPress default admin email used for WordPress core notifications.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_report_email"><?= esc_html__('Report email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="fromscratch_report_email" id="fromscratch_report_email" value="<?= esc_attr(get_option('fromscratch_report_email', '')) ?>" class="regular-text" autocomplete="email">
						<p class="description"><?= esc_html__('Used for automated reports such as weekly analytics summaries.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fromscratch_developer_email"><?= esc_html__('Developer email', 'fromscratch') ?></label></th>
					<td>
						<input type="email" name="fromscratch_developer_email" id="fromscratch_developer_email" value="<?= esc_attr(get_option('fromscratch_developer_email', '')) ?>" class="regular-text" autocomplete="email">
						<p class="description"><?= esc_html__('Used for system alerts, error notifications and security warnings.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		
		<hr>

		<form method="post" action="" class="page-settings-form" id="fs-search-visibility">
			<?php wp_nonce_field('fromscratch_system_search_visibility'); ?>
			<input type="hidden" name="fromscratch_save_search_visibility" value="1">
			<h2 class="title"><?= esc_html__('Search engine visibility', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('This setting matches Settings → Reading.', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('When enabled, search engines are asked not to index this site.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Visibility', 'fromscratch') ?></th>
					<td>
						<label>
							<input type="checkbox" name="blog_public_discourage" value="1" <?= checked((int) get_option('blog_public', 1), 0, false) ?>>
							<?= esc_html__('Discourage search engines from indexing this site', 'fromscratch') ?>
						</label>
						<p class="description fs-indent-checkbox"><?= esc_html__('It is up to search engines whether they follow this request.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
