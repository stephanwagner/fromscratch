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
	'security' => ['label' => 'Security'],
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

// Flush redirect cache (Tools tab)
add_action('load-settings_page_fs-developer-settings', function () {
	if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	if (empty($_POST['fromscratch_flush_redirect_cache']) || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_flush_redirect_cache')) {
		return;
	}
	flush_rewrite_rules();
	set_transient('fromscratch_flush_redirect_cache_notice', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-developer-settings&tab=tools'));
	exit;
}, 5);

// Revision cleaner (Tools tab)
add_action('load-settings_page_fs-developer-settings', function () {
	if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	if (empty($_POST['fromscratch_clean_revisions']) || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_clean_revisions')) {
		return;
	}
	$keep = isset($_POST['fromscratch_revisions_keep']) ? (int) $_POST['fromscratch_revisions_keep'] : 5;
	$keep = max(0, $keep);
	$deleted = fs_clean_revisions($keep);
	set_transient('fromscratch_clean_revisions_notice', $deleted, 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-developer-settings&tab=tools'));
	exit;
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
				<a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>" class="nav-tab <?= $tab === $slug ? 'nav-tab-active' : '' ?>"><?= esc_html(__($def['label'], 'fromscratch')) ?><?php if ($slug === 'security' && get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') : ?> <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 2px;" aria-hidden="true"></span><?php endif; ?></a>
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
			<?php settings_fields(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL); ?>
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
				<tr>
					<th scope="row"><?= esc_html__('Post expirator', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_post_expirator]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_post_expirator]" value="1" <?= checked($feat('enable_post_expirator'), 1, false) ?>> <?= esc_html__('Enable post expirator', 'fromscratch') ?></label>
						<p class="description fs-description-adjust-checkbox"><?= esc_html__('Adds an expiration date/time to posts, pages and theme CPTs. When reached, the post is set to draft.', 'fromscratch') ?></p>
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
				'theme_settings_texts' => __('Theme settings: Texts', 'fromscratch'),
				'theme_settings_design' => __('Theme settings: Design', 'fromscratch'),
				'theme_settings_css' => __('Theme settings: CSS', 'fromscratch'),
				'theme_settings_redirects' => __('Theme settings: Redirect manager', 'fromscratch'),
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

		<?php elseif ($tab === 'security') : ?>
		<?php
			$site_password_on = get_option('fromscratch_site_password_protection') === '1';
			$site_password_hash = get_option('fromscratch_site_password_hash', '');
		?>
		<?php if ($site_password_on && $site_password_hash === '') : ?>
		<div class="notice notice-warning inline" style="margin: 0 0 16px 0;"><p><?= esc_html__('No password set. Set a password below to activate protection.', 'fromscratch') ?></p></div>
		<?php endif; ?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_SECURITY); ?>
			<h2 class="title"><?= esc_html__('Site password protection', 'fromscratch') ?></h2>
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
						<p class="description">
							<?= esc_html__('Set or change the password. Leave blank and save to clear or reset the password.', 'fromscratch') ?>
							<a class="fs-description-link -gray -has-icon" href="https://passwordcopy.app" target="_blank">
								<span class="fs-description-link-icon">
									<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
										<path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h240q17 0 28.5 11.5T480-800q0 17-11.5 28.5T440-760H200v560h560v-240q0-17 11.5-28.5T800-480q17 0 28.5 11.5T840-440v240q0 33-23.5 56.5T760-120H200Zm560-584L416-360q-11 11-28 11t-28-11q-11-11-11-28t11-28l344-344H600q-17 0-28.5-11.5T560-800q0-17 11.5-28.5T600-840h200q17 0 28.5 11.5T840-800v200q0 17-11.5 28.5T800-560q-17 0-28.5-11.5T760-600v-104Z" />
									</svg>
								</span>
								<span>passwordcopy.app</span>
							</a>
						</p>
					</td>
				</tr>
			</table>
			<p class="submit"><?php submit_button(); ?></p>
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
					setTimeout(function() { copyBtn.textContent = copyBtn.getAttribute('data-copy'); }, 1500);
				});
			});
		});
		</script>

		<?php elseif ($tab === 'tools') : ?>
		<?php
			$flush_notice = get_transient('fromscratch_flush_redirect_cache_notice');
			if ($flush_notice !== false) {
				delete_transient('fromscratch_flush_redirect_cache_notice');
			}
			$revisions_notice = get_transient('fromscratch_clean_revisions_notice');
			if ($revisions_notice !== false) {
				delete_transient('fromscratch_clean_revisions_notice');
			}
		?>
		<?php if ($flush_notice !== false) : ?>
		<div class="notice notice-success is-dismissible"><p><strong><?= esc_html__('Redirect cache flushed.', 'fromscratch') ?></strong></p></div>
		<?php endif; ?>
		<?php if ($revisions_notice !== false && is_numeric($revisions_notice)) : ?>
		<div class="notice notice-success is-dismissible"><p><strong><?= esc_html(sprintf(_n('%s revision deleted.', '%s revisions deleted.', (int) $revisions_notice, 'fromscratch'), number_format_i18n((int) $revisions_notice))) ?></strong></p></div>
		<?php endif; ?>
		<h2 class="title"><?= esc_html__('Flush redirect cache', 'fromscratch') ?></h2>
		<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Flushes WordPress rewrite rules so that redirect and permalink changes take effect immediately. Use this after changing redirects or permalink structure.', 'fromscratch') ?></p>
		<form method="post" action="">
			<?php wp_nonce_field('fromscratch_flush_redirect_cache'); ?>
			<input type="hidden" name="fromscratch_flush_redirect_cache" value="1">
			<p><button type="submit" class="button button-primary"><?= esc_html__('Flush redirect cache', 'fromscratch') ?></button></p>
		</form>

		<h2 class="title" style="margin-top: 28px;"><?= esc_html__('Revision cleaner', 'fromscratch') ?></h2>
		<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Delete old revisions for all posts and pages. Set how many of the most recent revisions to keep per post; older ones will be removed.', 'fromscratch') ?></p>
		<form method="post" action="">
			<?php wp_nonce_field('fromscratch_clean_revisions'); ?>
			<input type="hidden" name="fromscratch_clean_revisions" value="1">
			<p>
				<label for="fromscratch_revisions_keep"><?= esc_html__('Keep per post', 'fromscratch') ?></label>
				<input type="number" name="fromscratch_revisions_keep" id="fromscratch_revisions_keep" value="5" min="0" max="99" step="1" class="small-text" style="margin-left: 6px; margin-right: 8px;">
				<?= esc_html__('revisions (0 = delete all)', 'fromscratch') ?>
			</p>
			<p><button type="submit" class="button button-primary"><?= esc_html__('Clean revisions', 'fromscratch') ?></button></p>
		</form>
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
