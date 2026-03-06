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
			<div class="notice notice-warning inline" style="margin: 0 0 1em 0;">
				<p><strong><?= esc_html__('Search engines are asked not to index this site.', 'fromscratch') ?></strong></p>
				<p><a href="#fs-search-visibility"><?= esc_html__('Enable search engine indexing below', 'fromscratch') ?></a></p>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="page-settings-form" id="fs-search-visibility">
			<?php wp_nonce_field('fromscratch_system_search_visibility'); ?>
			<input type="hidden" name="fromscratch_save_search_visibility" value="1">
			<h2 class="title"><?= esc_html__('Search engine visibility', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('This setting matches Settings → Reading. When enabled, search engines are asked not to index this site.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Visibility', 'fromscratch') ?></th>
					<td>
						<label>
							<input type="checkbox" name="blog_public_discourage" value="1" <?= checked((int) get_option('blog_public', 1), 0, false) ?>>
							<?= esc_html__('Discourage search engines from indexing this site', 'fromscratch') ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>

		<form method="post" action="" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Administrator email', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('The administrator email address is used for system notifications and account recovery.', 'fromscratch') ?></p>
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
	</div>
	<?php
}
