<?php

defined('ABSPATH') || exit;

/**
 * Settings → Developer. Developer-only page with tabs: General, Features, Access, Tools.
 * Requires theme-settings.php for option group constants.
 */

const FS_DEVELOPER_TABS = [
	'general'  => ['label' => 'General'],
	'features' => ['label' => 'Features'],
	'access'   => ['label' => 'User rights'],
	'tools'    => ['label' => 'Tools'],
];

function fs_developer_settings_current_tab(): string
{
	$slugs = array_keys(FS_DEVELOPER_TABS);
	$requested = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	if ($requested !== '' && in_array($requested, $slugs, true)) {
		return $requested;
	}
	return $slugs[0];
}

// Bump asset version (on Developer page)
add_action('load-settings_page_fs-developer-settings', function () {
	if (!current_user_can('manage_options') || empty($_GET['fromscratch_bump']) || empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_bump_asset_version')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$current = get_option('fromscratch_asset_version', '1');
	$next = is_numeric($current) ? (string) ((int) $current + 1) : '2';
	update_option('fromscratch_asset_version', $next);
	set_transient('fromscratch_bump_notice', $next, 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-developer-settings&tab=general'));
	exit;
});

add_action('admin_menu', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	add_options_page(
		__('Developer', 'fromscratch'),
		__('Developer', 'fromscratch'),
		'manage_options',
		'fs-developer-settings',
		'fs_render_developer_settings_page',
		1
	);
}, 20);

add_action('load-settings_page_fs-developer-settings', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings'));
	exit;
}, 1);

function fs_render_developer_settings_page(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}
	$tab = fs_developer_settings_current_tab();
	$base_url = admin_url('options-general.php?page=fs-developer-settings');

	$bump_notice = get_transient('fromscratch_bump_notice');
	if ($bump_notice !== false) {
		delete_transient('fromscratch_bump_notice');
	}
	$bump_url = wp_nonce_url(add_query_arg(['fromscratch_bump' => '1', 'tab' => 'general'], $base_url), 'fromscratch_bump_asset_version');
?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer', 'fromscratch')) ?></h1>
		<?php if ($bump_notice !== false && $tab === 'general') : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html(sprintf(__('Asset version increased to %s.', 'fromscratch'), $bump_notice)) ?></strong></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
			<?php foreach (FS_DEVELOPER_TABS as $slug => $def) : ?>
				<a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>" class="nav-tab <?= $tab === $slug ? 'nav-tab-active' : '' ?>"><?= esc_html(__($def['label'], 'fromscratch')) ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ($tab === 'general') : ?>
		<?php $asset_version = get_option('fromscratch_asset_version', '1'); ?>
		<h2 class="title"><?= esc_html__('Cache', 'fromscratch') ?></h2>
		<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Use fs_asset_url( \'/path/to/file.css\' ) in templates to output the asset URL with ?ver= so the browser cache updates when you bump the version.', 'fromscratch') ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?= esc_html__('Cache version', 'fromscratch') ?></th>
				<td>
					<code style="font-size: 14px;"><?= esc_html($asset_version) ?></code>
					<a href="<?= esc_url($bump_url) ?>" class="button" style="margin-left: 8px;"><?= esc_html__('Bump version', 'fromscratch') ?></a>
					<p class="description"><?= esc_html__('Bump when static theme files have been changed so the cache of the files is updated.', 'fromscratch') ?></p>
				</td>
			</tr>
		</table>
		<h2 class="title" style="margin-top: 24px;"><?= esc_html__('Administrator email', 'fromscratch') ?></h2>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="admin_email"><?= esc_html__('Administrator email', 'fromscratch') ?></label>
					</th>
					<td>
						<input type="email" name="admin_email" id="admin_email" value="<?= esc_attr(get_option('admin_email')) ?>" class="regular-text">
						<p class="description"><?= esc_html__('Changes the Administrator email instantly without notifying.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<p class="submit"><?php submit_button(); ?></p>
		</form>

		<?php elseif ($tab === 'features') : ?>
		<?php
			$features = get_option('fromscratch_features', []);
			if (!is_array($features)) {
				$features = [];
			}
			$feat = function ($key) use ($features) {
				return isset($features[$key]) ? (int) $features[$key] : 1;
			};
		?>
		<h2 class="title"><?= esc_html__('Features', 'fromscratch') ?></h2>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_FEATURES); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Blogs', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_blogs]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_blogs]" value="1" <?= checked($feat('enable_blogs'), 1, false) ?>> <?= esc_html__('Allow posts', 'fromscratch') ?></label>
						<p class="description fs-description-adjust-checkbox"><?= esc_html__('Shows the Posts menu in the admin and allows creating and editing blog posts.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?= esc_html__('SVG support', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_svg]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_svg]" value="1" <?= checked($feat('enable_svg'), 1, false) ?>> <?= esc_html__('Allow SVG uploads', 'fromscratch') ?></label>
						<p class="description fs-description-adjust-checkbox"><?= esc_html__('SVGs are sanitized on upload.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?= esc_html__('Duplicate', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_duplicate_post]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_duplicate_post]" value="1" <?= checked($feat('enable_duplicate_post'), 1, false) ?>> <?= esc_html__('Allow duplication', 'fromscratch') ?></label>
						<p class="description fs-description-adjust-checkbox"><?= esc_html__('Shows a "Duplicate" row action for posts, pages, and custom post types.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?= esc_html__('SEO', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_seo]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_seo]" value="1" <?= checked($feat('enable_seo'), 1, false) ?>> <?= esc_html__('SEO panel', 'fromscratch') ?></label>
						<p class="description fs-description-adjust-checkbox"><?= esc_html__('Adds a section to pages, posts and custom post types to enter SEO info (title, description, OG image, noindex).', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<p class="submit"><?php submit_button(); ?></p>
		</form>

		<?php elseif ($tab === 'access') : ?>
		<?php
			$admin_access = get_option('fromscratch_admin_access', function_exists('fs_admin_access_defaults') ? fs_admin_access_defaults() : []);
			$admin_access_labels = [
				'plugins' => __('Plugins', 'fromscratch'),
				'options_general' => __('Settings: General', 'fromscratch'),
				'options_writing' => __('Settings: Writing', 'fromscratch'),
				'options_reading' => __('Settings: Reading', 'fromscratch'),
				'options_media' => __('Settings: Media', 'fromscratch'),
				'options_permalink' => __('Settings: Permalinks', 'fromscratch'),
				'options_privacy' => __('Settings: Privacy', 'fromscratch'),
				'tools' => __('Tools', 'fromscratch'),
				'themes' => __('Appearance (Themes)', 'fromscratch'),
				'theme_settings_general' => __('Theme settings: General', 'fromscratch'),
				'theme_settings_security' => __('Theme settings: Security', 'fromscratch'),
				'theme_settings_texts' => __('Theme settings: Texts', 'fromscratch'),
				'theme_settings_design' => __('Theme settings: Design', 'fromscratch'),
			];
		?>
		<h2 class="title"><?= esc_html__('User rights', 'fromscratch') ?></h2>
		<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Control which admin pages and Settings sections are visible to Administrators (Admin) and users with developer rights (Developer). Uncheck to hide from that role.', 'fromscratch') ?></p>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER); ?>
			<table class="form-table" role="presentation">
				<thead>
					<tr>
						<th scope="col" class="row-title"><?= esc_html__('Page / Section', 'fromscratch') ?></th>
						<th scope="col"><?= esc_html__('Admin', 'fromscratch') ?></th>
						<th scope="col"><?= esc_html__('Developer', 'fromscratch') ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($admin_access_labels as $key => $label) :
					$val = isset($admin_access[$key]) ? $admin_access[$key] : ['admin' => 0, 'developer' => 1];
					$admin_checked = !empty($val['admin']);
					$dev_checked = !empty($val['developer']);
				?>
					<tr>
						<td class="row-title"><?= esc_html($label) ?></td>
						<td>
							<input type="hidden" name="fromscratch_admin_access[<?= esc_attr($key) ?>][admin]" value="0">
							<label><input type="checkbox" name="fromscratch_admin_access[<?= esc_attr($key) ?>][admin]" value="1" <?= checked($admin_checked, true, false) ?>></label>
						</td>
						<td>
							<input type="hidden" name="fromscratch_admin_access[<?= esc_attr($key) ?>][developer]" value="0">
							<label><input type="checkbox" name="fromscratch_admin_access[<?= esc_attr($key) ?>][developer]" value="1" <?= checked($dev_checked, true, false) ?>></label>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="submit"><?php submit_button(); ?></p>
		</form>

		<?php else : ?>
		<h2 class="title"><?= esc_html__('Tools', 'fromscratch') ?></h2>
		<p class="description"><?= esc_html__('More developer tools (e.g. sitemap generator, post expirator) can be added here in future updates.', 'fromscratch') ?></p>
		<?php endif; ?>
	</div>
<?php
}

add_filter('submenu_file', function ($submenu_file, $parent_file) {
	if ($parent_file === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'fs-developer-settings') {
		return 'fs-developer-settings';
	}
	return $submenu_file;
}, 10, 2);
