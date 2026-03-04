<?php

defined('ABSPATH') || exit;

/**
 * Settings → Developer. Developer-only page with tabs: General, Features, Access, Tools.
 * Requires theme-settings.php for option group constants.
 */

const FS_DEVELOPER_TABS_BASE = [
	'general'  => ['label' => 'General'],
	'features' => ['label' => 'Features'],
	'access'   => ['label' => 'User rights'],
	'security' => ['label' => 'Security'],
	'tools'    => ['label' => 'Tools'],
];

/** Tab definitions: base tabs + Languages when feature is on. */
function fs_developer_settings_available_tabs(): array
{
	$tabs = FS_DEVELOPER_TABS_BASE;
	if (function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('languages')) {
		$tabs['languages'] = ['label' => 'Languages'];
	}
	return $tabs;
}

function fs_developer_settings_current_tab(): string
{
	$slugs = array_keys(fs_developer_settings_available_tabs());
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

/**
 * Handle all form submissions on the Developer page (self-POST, no options.php).
 * Processes POST, sets notice transient, redirects to same page with correct tab.
 */
add_action('load-settings_page_fs-developer-settings', function () {
	if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}

	$base = admin_url('options-general.php?page=fs-developer-settings');

	// Flush redirect cache (Tools)
	if (!empty($_POST['fromscratch_flush_redirect_cache']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_flush_redirect_cache')) {
		flush_rewrite_rules();
		set_transient('fromscratch_flush_redirect_cache_notice', '1', 30);
		wp_safe_redirect($base . '&tab=tools');
		exit;
	}

	// Revision cleaner (Tools)
	if (!empty($_POST['fromscratch_clean_revisions']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_clean_revisions')) {
		$keep = isset($_POST['fromscratch_revisions_keep']) ? max(0, (int) $_POST['fromscratch_revisions_keep']) : 5;
		$deleted = fs_clean_revisions($keep);
		set_transient('fromscratch_clean_revisions_notice', $deleted, 30);
		wp_safe_redirect($base . '&tab=tools');
		exit;
	}

	// Features
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_FEATURES && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_FEATURES . '-options')) {
		$value = isset($_POST['fromscratch_features']) && is_array($_POST['fromscratch_features']) ? $_POST['fromscratch_features'] : [];
		$sanitized = function_exists('fs_sanitize_features') ? fs_sanitize_features($value) : [];
		update_option('fromscratch_features', $sanitized);
		set_transient('fromscratch_features_saved', '1', 30);
		wp_safe_redirect($base . '&tab=features');
		exit;
	}

	// User rights (Access)
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_DEVELOPER && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER . '-options')) {
		$value = isset($_POST['fromscratch_admin_access']) && is_array($_POST['fromscratch_admin_access']) ? $_POST['fromscratch_admin_access'] : [];
		$sanitized = function_exists('fs_sanitize_admin_access') ? fs_sanitize_admin_access($value) : [];
		update_option('fromscratch_admin_access', $sanitized);
		set_transient('fromscratch_access_saved', '1', 30);
		wp_safe_redirect($base . '&tab=access');
		exit;
	}

	// General (admin email)
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL . '-options')) {
		$email = isset($_POST['admin_email']) ? sanitize_email(wp_unslash($_POST['admin_email'])) : '';
		if (is_email($email)) {
			update_option('admin_email', $email);
		}
		set_transient('fromscratch_general_saved', '1', 30);
		wp_safe_redirect($base . '&tab=general');
		exit;
	}

	// Security (password, maintenance)
	if (!empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_SECURITY && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_SECURITY . '-options')) {
		$prot = function_exists('fs_sanitize_site_password_protection') ? fs_sanitize_site_password_protection($_POST['fromscratch_site_password_protection'] ?? '') : '';
		update_option('fromscratch_site_password_protection', $prot);
		$mode = function_exists('fs_sanitize_maintenance_mode') ? fs_sanitize_maintenance_mode($_POST['fromscratch_maintenance_mode'] ?? '') : '';
		update_option('fromscratch_maintenance_mode', $mode);
		$title = function_exists('fs_sanitize_maintenance_title') ? fs_sanitize_maintenance_title($_POST['fromscratch_maintenance_title'] ?? '') : '';
		update_option('fromscratch_maintenance_title', $title);
		$desc = function_exists('fs_sanitize_maintenance_description') ? fs_sanitize_maintenance_description($_POST['fromscratch_maintenance_description'] ?? '') : '';
		update_option('fromscratch_maintenance_description', $desc);
		set_transient('fromscratch_security_saved', '1', 30);
		wp_safe_redirect($base . '&tab=security');
		exit;
	}

	// Languages (Developer tab when Languages feature is on)
	if (function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('languages')
		 && !empty($_POST['option_page']) && $_POST['option_page'] === FS_THEME_OPTION_GROUP_LANGUAGES
		 && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_LANGUAGES . '-options')) {
		$value = isset($_POST['fs_theme_languages']) && is_array($_POST['fs_theme_languages']) ? $_POST['fs_theme_languages'] : [];
		$sanitized = function_exists('fs_sanitize_theme_languages') ? fs_sanitize_theme_languages($value) : ['list' => [], 'default' => '', 'use_url_prefix' => true, 'prefix_default' => false];
		update_option('fs_theme_languages', $sanitized);
		set_transient('fromscratch_languages_saved', '1', 30);
		wp_safe_redirect($base . '&tab=languages');
		exit;
	}
}, 5);

/**
 * Delete revisions, keeping the N most recent per post.
 *
 * @param int $keep Number of revisions to keep per post (0 = delete all).
 * @return int Number of revisions deleted.
 */
function fs_clean_revisions(int $keep): int
{
	global $wpdb;
	$deleted = 0;
	$parent_ids = $wpdb->get_col("SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent > 0");
	if (empty($parent_ids)) {
		return 0;
	}
	foreach ($parent_ids as $parent_id) {
		$revisions = wp_get_post_revisions((int) $parent_id, ['orderby' => 'date', 'order' => 'DESC']);
		if (empty($revisions)) {
			continue;
		}
		$to_delete = $keep === 0 ? $revisions : array_slice($revisions, $keep);
		foreach ($to_delete as $revision) {
			if (wp_delete_post_revision($revision->ID)) {
				$deleted++;
			}
		}
	}
	return $deleted;
}

function fs_render_developer_settings_page(): void
{
	$password_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5 58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm296.5-223.5Q560-327 560-360t-23.5-56.5Q513-440 480-440t-56.5 23.5Q400-393 400-360t23.5 56.5Q447-280 480-280t56.5-23.5ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg>';
	$maintenance_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-360q-100 0-170-70t-70-170q0-20 3-40t11-38q5-10 12.5-15t16.5-7q9-2 18.5.5T199-689l105 105 72-72-105-105q-8-8-10.5-17.5T260-797q2-9 7-16.5t15-12.5q18-8 38-11t40-3q100 0 170 70t70 170q0 23-4 43.5T584-516l202 200q29 29 29 71t-29 71q-29 29-71 29t-71-30L444-376q-20 8-40.5 12t-43.5 4Z"/></svg>';

	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}
	$tab = fs_developer_settings_current_tab();
	$base_url = admin_url('options-general.php?page=fs-developer-settings');

	$bump_notice = get_transient('fromscratch_bump_notice');
	if ($bump_notice !== false) {
		delete_transient('fromscratch_bump_notice');
	}
	$flush_notice = get_transient('fromscratch_flush_redirect_cache_notice');
	if ($flush_notice !== false) {
		delete_transient('fromscratch_flush_redirect_cache_notice');
	}
	$revisions_notice = get_transient('fromscratch_clean_revisions_notice');
	if ($revisions_notice !== false) {
		delete_transient('fromscratch_clean_revisions_notice');
	}
	$features_saved = get_transient('fromscratch_features_saved');
	if ($features_saved !== false) {
		delete_transient('fromscratch_features_saved');
	}
	$access_saved = get_transient('fromscratch_access_saved');
	if ($access_saved !== false) {
		delete_transient('fromscratch_access_saved');
	}
	$general_saved = get_transient('fromscratch_general_saved');
	if ($general_saved !== false) {
		delete_transient('fromscratch_general_saved');
	}
	$security_saved = get_transient('fromscratch_security_saved');
	if ($security_saved !== false) {
		delete_transient('fromscratch_security_saved');
	}
	$languages_saved = get_transient('fromscratch_languages_saved');
	if ($languages_saved !== false) {
		delete_transient('fromscratch_languages_saved');
	}
	$bump_url = wp_nonce_url(add_query_arg(['fromscratch_bump' => '1', 'tab' => 'general'], $base_url), 'fromscratch_bump_asset_version');
?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php
		$notices = [];
		if ($bump_notice !== false) {
			$notices[] = sprintf(__('Asset version increased to %s.', 'fromscratch'), $bump_notice);
		}
		if ($flush_notice !== false) {
			$notices[] = __('Permalink rules have been successfully refreshed.', 'fromscratch');
		}
		if ($revisions_notice !== false && is_numeric($revisions_notice)) {
			$notices[] = sprintf(_n('%s revision deleted.', '%s revisions deleted.', (int) $revisions_notice, 'fromscratch'), number_format_i18n((int) $revisions_notice));
		}
		if ($features_saved !== false || $access_saved !== false || $general_saved !== false || $security_saved !== false || $languages_saved !== false) {
			$notices[] = __('Settings saved.', 'fromscratch');
		}
		foreach ($notices as $msg) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html($msg) ?></strong></p>
			</div>
		<?php endforeach; ?>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
			<?php
			foreach (fs_developer_settings_available_tabs() as $slug => $def) {
				$icons = '';
				if ($slug === 'security') {
					if (get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') {
						$icons .= '<div class="fs-tab-icon">' . $password_icon . '</div>';
					}
					if (get_option('fromscratch_maintenance_mode') === '1') {
						$icons .= '<div class="fs-tab-icon">' . $maintenance_icon . '</div>';
					}
				}

				echo '<a href="' . esc_url(add_query_arg('tab', $slug, $base_url)) . '" class="nav-tab ' . ($tab === $slug ? 'nav-tab-active' : '') . ($icons !== '' ? ' has-icons' : '') . '">';
				echo '<span>' . esc_html(__($def['label'], 'fromscratch')) . '</span>';
				if ($icons !== '') {
					echo '<span class="fs-tab-icons">' . $icons . '</span>';
				}
				echo '</a>';
			}
			?>
		</nav>

		<?php if ($tab === 'general') : ?>
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

		<?php elseif ($tab === 'features') : ?>
			<?php
			$features = get_option('fromscratch_features', []);
			if (!is_array($features)) {
				$features = [];
			}
			$defaults = function_exists('fs_theme_feature_defaults') ? fs_theme_feature_defaults() : [];
			$feat = function ($key) use ($features, $defaults) {
				return isset($features[$key]) ? (int) $features[$key] : (int) ($defaults[$key] ?? 0);
			};
			?>
			<h2 class="title"><?= esc_html__('Features', 'fromscratch') ?></h2>
			<form method="post" action="" class="page-settings-form">
				<?php settings_fields(FS_THEME_OPTION_GROUP_FEATURES); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Blogs', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_blogs]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_blogs]" id="fromscratch_features_enable_blogs" value="1" <?= checked($feat('enable_blogs'), 1, false) ?>> <?= esc_html__('Allow posts', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Shows the Posts menu in the admin and allows creating and editing blog posts.', 'fromscratch') ?></p>
							<div class="fs-feature-sub fs-indent-checkbox" id="fs-feature-sub-blogs" style="margin-top: 12px; <?= $feat('enable_blogs') !== 1 ? 'display:none;' : '' ?>">
								<input type="hidden" name="fromscratch_features[enable_remove_post_tags]" value="0">
								<label><input type="checkbox" name="fromscratch_features[enable_remove_post_tags]" value="1" <?= checked($feat('enable_remove_post_tags'), 1, false) ?>> <?= esc_html__('Disable tags', 'fromscratch') ?></label>
								<p class="description fs-indent-checkbox" style="margin-top: 4px;"><?= esc_html__('Unregisters the Tags taxonomy for posts.', 'fromscratch') ?></p>
							</div>
						</td>
					</tr>
				</table>

				<hr class="fs-small">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Duplicate', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_duplicate_post]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_duplicate_post]" value="1" <?= checked($feat('enable_duplicate_post'), 1, false) ?>> <?= esc_html__('Allow duplication', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Shows a "Duplicate" row action for posts, pages, and custom post types.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr class="fs-small">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Post expirator', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_post_expirator]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_post_expirator]" value="1" <?= checked($feat('enable_post_expirator'), 1, false) ?>> <?= esc_html__('Enable post expirator', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Adds an expiration date to posts, pages and custom post types.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr class="fs-small">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('SEO', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_seo]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_seo]" value="1" <?= checked($feat('enable_seo'), 1, false) ?>> <?= esc_html__('SEO panel', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Adds a section to pages, posts and custom post types to enter SEO info.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr class="fs-small">

						<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('SVG support', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_svg]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_svg]" value="1" <?= checked($feat('enable_svg'), 1, false) ?>> <?= esc_html__('Allow SVG uploads', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Uploaded SVG files are automatically sanitized to remove potentially unsafe code.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr class="fs-small">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Languages', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_languages]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_languages]" id="fromscratch_features_enable_languages" value="1" <?= checked($feat('enable_languages'), 1, false) ?>> <?= esc_html__('Enable languages', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Adds a Languages tab here where you can manage content languages and set the default language.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<script>
				(function() {
					var blogs = document.getElementById('fromscratch_features_enable_blogs');
					var sub = document.getElementById('fs-feature-sub-blogs');
					if (!blogs || !sub) return;

					function toggle() {
						sub.style.display = blogs.checked ? '' : 'none';
					}
					blogs.addEventListener('change', toggle);
				})();
			</script>

		<?php elseif ($tab === 'access') : ?>
			<?php
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
			<form method="post" action="" class="page-settings-form">
				<h2 class="title"><?= esc_html__('User rights', 'fromscratch') ?></h2>
				<p class="description" style="margin-bottom: 16px;"><?= esc_html__('Control which admin pages and Settings sections are visible to Administrators (Admin) and users with developer rights (Developer). Uncheck to hide from that role.', 'fromscratch') ?></p>
				<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER); ?>
				<?php foreach ($admin_access_groups as $group) : ?>
					<?php if ($group['title']) : ?>
						<h3 class="title" style="margin-top: 24px; margin-bottom: 12px;"><?= esc_html($group['title']) ?></h3>
					<?php endif; ?>
					<table class="widefat striped" role="presentation" style="margin-bottom: 0; width: auto;">
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
									<td class="row-title" style="width: 200px;"><?= esc_html($label) ?></td>
									<td style="width: 120px;">
										<input type="hidden" name="fromscratch_admin_access[<?= esc_attr($key) ?>][admin]" value="0">
										<label><input type="checkbox" name="fromscratch_admin_access[<?= esc_attr($key) ?>][admin]" value="1" <?= checked($admin_checked, true, false) ?>></label>
									</td>
									<td style="width: 120px;">
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

		<?php elseif ($tab === 'security') : ?>
			<?php
			$site_password_on = get_option('fromscratch_site_password_protection') === '1';
			$site_password_hash = get_option('fromscratch_site_password_hash', '');
			$maintenance_on = get_option('fromscratch_maintenance_mode') === '1';
			?>
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
				<?php settings_fields(FS_THEME_OPTION_GROUP_SECURITY); ?>
				<h2 class="title"><?= esc_html__('Password protection', 'fromscratch') ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Activate', 'fromscratch') ?></th>
						<td>
							<label>
								<input type="hidden" name="fromscratch_site_password_protection" value="0">
								<input type="checkbox" name="fromscratch_site_password_protection" value="1" <?= checked(get_option('fromscratch_site_password_protection'), '1', false) ?>>
								<?= esc_html__('Activate password protection', 'fromscratch') ?>
							</label>
							<p class="description"><?= wp_kses(__('When enabled, visitors must enter a password before viewing any part of the site.<br>Logged-in administrators and editors skip the prompt.', 'fromscratch'), ['br' => []]) ?></p>
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

				<hr>

				<h2 class="title"><?= esc_html__('Maintenance mode', 'fromscratch') ?></h2>
				<p class="description"><?= esc_html__('When enabled, the entire frontend is blocked with HTTP 503.', 'fromscratch') ?></p>
				<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Logged-in administrators and editors can still view the site.', 'fromscratch') ?></p>
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
							<textarea name="fromscratch_maintenance_description" id="fromscratch_maintenance_description" rows="3" class="large-text" placeholder="<?= esc_attr__('We are currently performing scheduled maintenance. Please check back shortly.', 'fromscratch') ?>"><?= esc_textarea(get_option('fromscratch_maintenance_description', '')) ?></textarea>
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

		<?php elseif ($tab === 'languages') : ?>
			<?php
			$lang_data = get_option('fs_theme_languages', ['list' => [], 'default' => '', 'use_url_prefix' => true, 'prefix_default' => false]);
			$lang_list = isset($lang_data['list']) && is_array($lang_data['list']) ? $lang_data['list'] : [];
			$lang_default = isset($lang_data['default']) ? (string) $lang_data['default'] : '';
			$lang_use_url_prefix = isset($lang_data['use_url_prefix']) ? (bool) $lang_data['use_url_prefix'] : true;
			$lang_prefix_default = !empty($lang_data['prefix_default']);
			if ($lang_default === '' && !empty($lang_list)) {
				$lang_default = $lang_list[0]['id'] ?? '';
			}
			?>
			<h2 class="title"><?= esc_html__('Languages', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 16px;"><?= esc_html__('Manage languages for translatable content (Settings → Theme → Content). Set the default language used when no translation is selected.', 'fromscratch') ?></p>
			<form method="post" action="" class="page-settings-form" id="fs-languages-form">
				<?php settings_fields(FS_THEME_OPTION_GROUP_LANGUAGES); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Default language', 'fromscratch') ?></th>
						<td>
							<select name="fs_theme_languages[default]" id="fs_theme_languages_default" class="regular-text">
								<?php foreach ($lang_list as $l) : ?>
									<option value="<?= esc_attr($l['id']) ?>" <?= selected($lang_default, $l['id'], false) ?>><?= esc_html($l['nameEnglish'] !== '' ? $l['nameEnglish'] : $l['id']) ?></option>
								<?php endforeach; ?>
								<?php if (empty($lang_list)) : ?>
									<option value=""><?= esc_html__('— Add at least one language below —', 'fromscratch') ?></option>
								<?php endif; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('URL prefix', 'fromscratch') ?></th>
						<td>
							<label><input type="checkbox" name="fs_theme_languages[use_url_prefix]" id="fs_use_url_prefix" value="1" <?= checked($lang_use_url_prefix, true, false) ?>> <?= esc_html__('Use language prefix in URL', 'fromscratch') ?></label>
							<p class="description"><?= esc_html__('When on: URLs include a language segment (e.g. /de/ueber-uns/, /en/about/). When off: no language segment is used.', 'fromscratch') ?></p>
							<div id="fs-prefix-default-wrap" class="fs-url-prefix-sub" style="margin-top: 12px; <?= $lang_use_url_prefix ? '' : 'display:none;' ?>">
								<input type="hidden" name="fs_theme_languages[prefix_default]" value="0">
								<label><input type="checkbox" name="fs_theme_languages[prefix_default]" id="fs_prefix_default" value="1" <?= checked($lang_prefix_default, true, false) ?>> <?= esc_html__('Prefix default language in URL', 'fromscratch') ?></label>
								<p class="description"><?= esc_html__('When off: default language has no prefix (e.g. /about/). When on: all languages use a prefix (e.g. /en/about/, /de/ueber-uns/).', 'fromscratch') ?></p>
							</div>
						</td>
					</tr>
				</table>
				<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Language list', 'fromscratch') ?></h3>
				<table class="widefat striped" id="fs-languages-table" style="max-width: 640px;">
					<thead>
						<tr>
							<th style="width: 100px;"><?= esc_html__('Code', 'fromscratch') ?></th>
							<th><?= esc_html__('Name (English)', 'fromscratch') ?></th>
							<th><?= esc_html__('Name (original)', 'fromscratch') ?></th>
							<th style="width: 80px;"></th>
						</tr>
					</thead>
					<tbody id="fs-languages-tbody">
						<?php foreach ($lang_list as $i => $l) : ?>
							<tr class="fs-language-row">
								<td><input type="text" name="fs_theme_languages[list][<?= (int) $i ?>][id]" value="<?= esc_attr($l['id']) ?>" class="small-text" placeholder="en" maxlength="20" pattern="[a-zA-Z0-9_-]+" required></td>
								<td><input type="text" name="fs_theme_languages[list][<?= (int) $i ?>][nameEnglish]" value="<?= esc_attr($l['nameEnglish']) ?>" class="regular-text" placeholder="<?= esc_attr__('English', 'fromscratch') ?>"></td>
								<td><input type="text" name="fs_theme_languages[list][<?= (int) $i ?>][nameOriginalLanguage]" value="<?= esc_attr($l['nameOriginalLanguage']) ?>" class="regular-text" placeholder="<?= esc_attr__('English', 'fromscratch') ?>"></td>
								<td><button type="button" class="button button-small fs-remove-language" aria-label="<?= esc_attr__('Remove', 'fromscratch') ?>"><?= esc_html__('Remove', 'fromscratch') ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p style="margin-top: 12px;">
					<button type="button" class="button" id="fs-add-language"><?= esc_html__('Add language', 'fromscratch') ?></button>
				</p>
				<script>
				(function() {
					var form = document.getElementById('fs-languages-form');
					var tbody = document.getElementById('fs-languages-tbody');
					var addBtn = document.getElementById('fs-add-language');
					var usePrefix = document.getElementById('fs_use_url_prefix');
					var prefixWrap = document.getElementById('fs-prefix-default-wrap');
					var prefixDefault = document.getElementById('fs_prefix_default');
					function togglePrefixDefault() {
						var on = usePrefix && usePrefix.checked;
						if (prefixWrap) prefixWrap.style.display = on ? '' : 'none';
						if (prefixDefault) prefixDefault.disabled = !on;
					}
					if (usePrefix) usePrefix.addEventListener('change', togglePrefixDefault);
					togglePrefixDefault();

					if (!form || !tbody || !addBtn) return;
					var rowIndex = <?= (int) count($lang_list) ?>;
					addBtn.addEventListener('click', function() {
						var tr = document.createElement('tr');
						tr.className = 'fs-language-row';
						tr.innerHTML = '<td><input type="text" name="fs_theme_languages[list][' + rowIndex + '][id]" value="" class="small-text" placeholder="en" maxlength="20" required></td>' +
							'<td><input type="text" name="fs_theme_languages[list][' + rowIndex + '][nameEnglish]" value="" class="regular-text"></td>' +
							'<td><input type="text" name="fs_theme_languages[list][' + rowIndex + '][nameOriginalLanguage]" value="" class="regular-text"></td>' +
							'<td><button type="button" class="button button-small fs-remove-language" aria-label="<?= esc_js(__('Remove', 'fromscratch')) ?>"><?= esc_js(__('Remove', 'fromscratch')) ?></button></td>';
						tbody.appendChild(tr);
						rowIndex++;
					});
					tbody.addEventListener('click', function(e) {
						if (e.target.classList.contains('fs-remove-language')) {
							e.target.closest('tr').remove();
						}
					});
				})();
				</script>
				<?php submit_button(); ?>
			</form>

		<?php elseif ($tab === 'tools') : ?>
			<div class="page-settings-form">
				<h2 class="title"><?= esc_html__('Refresh Permalink Rules', 'fromscratch') ?></h2>
				<p class="description"><?= esc_html__('Updates the WordPress permalink structure and rewrite rules.', 'fromscratch') ?></p>
				<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Run after structural changes.', 'fromscratch') ?></p>
				<form method="post" action="">
					<?php wp_nonce_field('fromscratch_flush_redirect_cache'); ?>
					<input type="hidden" name="fromscratch_flush_redirect_cache" value="1">
					<div style="margin-top: 20px;"><button type="submit" class="button button-primary"><?= esc_html_x('Refresh Permalink Rules', 'Button text', 'fromscratch') ?></button></div>
				</form>

				<hr>

				<h2 class="title" style="margin-top: 28px;"><?= esc_html__('Revision cleaner', 'fromscratch') ?></h2>
				<?php
				global $wpdb;
				$revisions_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
				?>
				<p class="description"><?= esc_html__('Delete old revisions for all posts and pages.', 'fromscratch') ?></p>
				<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Set how many of the most recent revisions to keep per post, older ones will be removed.', 'fromscratch') ?></p>
				<p style="margin-bottom: 24px;"><strong><?= esc_html(sprintf(_n('%s revision in total.', '%s revisions in total.', $revisions_total, 'fromscratch'), number_format_i18n($revisions_total))) ?></strong></p>
				<form method="post" action="">
					<?php wp_nonce_field('fromscratch_clean_revisions'); ?>
					<input type="hidden" name="fromscratch_clean_revisions" value="1">
					<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
						<label for="fromscratch_revisions_keep"><?= esc_html__('Keep per post:', 'fromscratch') ?></label>
						<input type="number" name="fromscratch_revisions_keep" id="fromscratch_revisions_keep" value="5" min="0" max="99" step="1" class="small-text">
						<span><?= esc_html__('revisions (0 = delete all)', 'fromscratch') ?></span>
					</div>
					<div style="margin-top: 24px;"><button type="submit" class="button button-primary"><?= esc_html__('Clean revisions', 'fromscratch') ?></button></div>
				</form>
			</div>
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
