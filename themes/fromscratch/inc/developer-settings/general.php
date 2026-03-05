<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'general';
$fs_developer_page_slug = fs_developer_settings_page_slug($fs_developer_tab); // fs-developer

add_action('admin_menu', function () use ($fs_developer_tab, $fs_developer_page_slug) {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$tabs = fs_developer_settings_available_tabs();
	$label = $tabs[$fs_developer_tab]['label'] ?? $fs_developer_tab;
	add_submenu_page(
		'options-general.php',
		__('Developer settings', 'fromscratch') . ' – ' . $label,
		__('Developer', 'fromscratch'),
		'manage_options',
		$fs_developer_page_slug,
		'fs_render_developer_general',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

// Process forms on admin_init (same pattern as theme-settings)
add_action('admin_init', function () use ($fs_developer_page_slug) {
	global $pagenow;
	if ($pagenow !== 'options-general.php' || (isset($_GET['page']) ? $_GET['page'] : '') !== $fs_developer_page_slug) {
		return;
	}
	if (!current_user_can('manage_options') || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$url = admin_url('options-general.php?page=fs-developer');

	// Bump asset version (GET with nonce)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['fromscratch_bump']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_bump_asset_version')) {
		$current = get_option('fromscratch_asset_version', '1');
		$next = is_numeric($current) ? (string) ((int) $current + 1) : '2';
		update_option('fromscratch_asset_version', $next);
		set_transient('fromscratch_bump_notice', $next, 30);
		wp_safe_redirect($url);
		exit;
	}

	// Admin email form (POST)
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL . '-options')) {
		$email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
		if (is_email($email)) {
			update_option('admin_email', $email);
		}
		set_transient('fromscratch_general_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}
}, 1);

function fs_render_developer_general(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$bump_notice = get_transient('fromscratch_bump_notice');
	if ($bump_notice !== false) {
		delete_transient('fromscratch_bump_notice');
	}
	$general_saved = get_transient('fromscratch_general_saved');
	if ($general_saved !== false) {
		delete_transient('fromscratch_general_saved');
	}

	$notices = [];
	if ($bump_notice !== false) {
		$notices[] = sprintf(__('Asset version increased to %s.', 'fromscratch'), $bump_notice);
	}
	if ($general_saved !== false) {
		$notices[] = __('Settings saved.', 'fromscratch');
	}

	$bump_url = wp_nonce_url(add_query_arg(['page' => 'fs-developer', 'fromscratch_bump' => '1'], admin_url('options-general.php')), 'fromscratch_bump_asset_version');
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php foreach ($notices as $msg) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html($msg) ?></strong></p>
			</div>
		<?php endforeach; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<div class="page-settings-form">
			<?php $asset_version = get_option('fromscratch_asset_version', '1'); ?>
			<h2 class="title"><?= esc_html__('Asset Cache', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Bump when static theme files using fs_asset_url have been changed so the cache of the files is updated.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Cache version', 'fromscratch') ?></th>
					<td>
						<div style="display: flex; align-items: center;">
							<code style="font-size: 14px; height: 30px; line-height: 30px; padding: 0 8px; min-width: 30px; text-align: center; box-sizing: border-box; border-radius: 3px; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);">
								<?= esc_html($asset_version) ?>
							</code>
							<a href="<?= esc_url($bump_url) ?>" class="button" style="margin-left: 8px;"><?= esc_html__('Bump version', 'fromscratch') ?></a>
						</div>
					</td>
				</tr>
			</table>

			<hr>

			<h2 class="title" style="margin-top: 24px;"><?= esc_html__('Administrator email', 'fromscratch') ?></h2>
			<form method="post" action="">
				<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>

				<p class="description"><?= esc_html__('Changes the Administrator email instantly without notifying.', 'fromscratch') ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="admin_email"><?= esc_html__('Email address', 'fromscratch') ?></label>
						</th>
						<td>
							<input type="email" name="admin_email" id="admin_email" value="<?= esc_attr(get_option('admin_email')) ?>" class="regular-text">
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
	</div>
	<?php
}
