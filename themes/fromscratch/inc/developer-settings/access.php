<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'access';
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
		'fs_render_developer_access',
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
	// User rights form only
	if (empty($_POST['option_page']) || $_POST['option_page'] !== FS_THEME_OPTION_GROUP_DEVELOPER || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER . '-options')) {
		return;
	}
	$value = isset($_POST['fromscratch_admin_access']) && is_array($_POST['fromscratch_admin_access']) ? $_POST['fromscratch_admin_access'] : [];
	$sanitized = function_exists('fs_sanitize_admin_access') ? fs_sanitize_admin_access($value) : [];
	update_option('fromscratch_admin_access', $sanitized);
	set_transient('fromscratch_access_saved', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-developer-access'));
	exit;
}, 1);

function fs_render_developer_access(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$access_saved = get_transient('fromscratch_access_saved');
	if ($access_saved !== false) {
		delete_transient('fromscratch_access_saved');
	}

	$admin_access = get_option('fromscratch_admin_access', function_exists('fs_admin_access_defaults') ? fs_admin_access_defaults() : []);
	$admin_access_groups = [
		['title' => null, 'items' => [
			'plugins' => __('Plugins', 'fromscratch'),
			'tools' => __('Tools', 'fromscratch'),
			'themes' => __('Appearance (Themes)', 'fromscratch'),
		]],
		['title' => __('Settings', 'fromscratch'), 'items' => [
			'options_general' => __('General', 'fromscratch'),
			'options_writing' => __('Writing', 'fromscratch'),
			'options_reading' => __('Reading', 'fromscratch'),
			'options_media' => __('Media', 'fromscratch'),
			'options_permalink' => __('Permalinks', 'fromscratch'),
			'options_privacy' => __('Privacy', 'fromscratch'),
		]],
		['title' => __('Theme settings', 'fromscratch'), 'items' => [
			'theme_settings_general' => __('General', 'fromscratch'),
			'theme_settings_texts' => __('Content', 'fromscratch'),
			'theme_settings_design' => __('Design', 'fromscratch'),
			'theme_settings_css' => __('CSS', 'fromscratch'),
			'theme_settings_redirects' => __('Redirects', 'fromscratch'),
		]],
	];
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($access_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<form method="post" action="" class="page-settings-form">
			<h2 class="title"><?= esc_html__('User rights', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 16px;"><?= esc_html__('Control which admin pages and Settings sections are visible to Administrators (Admin) and users with developer rights (Developer). Uncheck to hide from that role.', 'fromscratch') ?></p>
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER); ?>
			<?php foreach ($admin_access_groups as $group) : ?>
				<?php if ($group['title']) : ?>
					<h3 class="title" style="margin-top: 24px; margin-bottom: 12px;"><?= esc_html($group['title']) ?></h3>
				<?php endif; ?>
				<table class="widefat striped fs-table-small-gaps" role="presentation" style="margin-bottom: 0; width: auto;">
					<thead>
						<tr>
							<th scope="col" class="row-title"><?= esc_html__('Section', 'fromscratch') ?></th>
							<th scope="col"><?= esc_html__('Admin', 'fromscratch') ?></th>
							<th scope="col"><?= esc_html__('Developer', 'fromscratch') ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($group['items'] as $key => $label) :
							$val = isset($admin_access[$key]) ? $admin_access[$key] : ['admin' => 0, 'developer' => 1];
							$admin_checked = !empty($val['admin']);
							$dev_checked = !empty($val['developer']);
						?>
							<tr>
								<td class="row-title" style="width: 180px;"><?= esc_html($label) ?></td>
								<td style="width: auto; min-width: 80px;">
									<input type="hidden" name="fromscratch_admin_access[<?= esc_attr($key) ?>][admin]" value="0">
									<label><input type="checkbox" name="fromscratch_admin_access[<?= esc_attr($key) ?>][admin]" value="1" <?= checked($admin_checked, true, false) ?>></label>
								</td>
								<td style="width: auto; min-width: 100px;">
									<input type="hidden" name="fromscratch_admin_access[<?= esc_attr($key) ?>][developer]" value="0">
									<label><input type="checkbox" name="fromscratch_admin_access[<?= esc_attr($key) ?>][developer]" value="1" <?= checked($dev_checked, true, false) ?>></label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
