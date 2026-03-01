<?php

defined('ABSPATH') || exit;

/**
 * Theme settings page: Settings → Theme.
 * Developer-first: some tabs/sections are only visible to users with developer rights.
 * Requires inc/design.php for Design tab.
 */

/** Tab definitions: slug => [ 'label' => string, 'developer_only' => bool ] */
const FS_THEME_SETTINGS_TABS = [
	'general'  => ['label' => 'General', 'developer_only' => false],
	'texts'    => ['label' => 'Texts', 'developer_only' => false],
	'design'   => ['label' => 'Design', 'developer_only' => false],
	'security' => ['label' => 'Security', 'developer_only' => false],
	'developer' => ['label' => 'Developer', 'developer_only' => true],
];

function fs_theme_settings_available_tabs(): array
{
	$is_dev = function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
	$tabs = [];
	foreach (FS_THEME_SETTINGS_TABS as $slug => $def) {
		if ($def['developer_only'] && !$is_dev) {
			continue;
		}
		$tabs[$slug] = $def['label'];
	}
	return $tabs;
}

function fs_theme_settings_current_tab(): string
{
	$available = array_keys(fs_theme_settings_available_tabs());
	$requested = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	if ($requested !== '' && in_array($requested, $available, true)) {
		return $requested;
	}
	return $available[0] ?? 'general';
}

// Bump asset version (developer only)
add_action('load-settings_page_fs-theme-settings', function () {
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
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=developer'));
	exit;
});

// Clear design overrides (developer only)
add_action('load-settings_page_fs-theme-settings', function () {
	if (!current_user_can('manage_options') || empty($_GET['fromscratch_clear_design']) || empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_clear_design')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	delete_option('fromscratch_design');
	set_transient('fromscratch_clear_design_notice', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=developer'));
	exit;
});

// Redirect non-developers away from developer tab
add_action('load-settings_page_fs-theme-settings', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	$requested = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	if ($requested !== 'developer') {
		return;
	}
	if (function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=general'));
	exit;
}, 1);

const FS_THEME_OPTION_GROUP_GENERAL = 'fs_theme_general';
const FS_THEME_OPTION_GROUP_TEXTE = 'fs_theme_texte';
const FS_THEME_OPTION_GROUP_DESIGN = 'fs_theme_design';
const FS_THEME_OPTION_GROUP_SECURITY = 'fs_theme_security';
const FS_THEME_OPTION_GROUP_DEVELOPER = 'fs_theme_developer';

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_DEVELOPER, 'fromscratch_admin_access', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_admin_access',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_asset_version', [
		'type' => 'string',
		'default' => '1',
		'sanitize_callback' => 'sanitize_text_field',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_features', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_features',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'admin_email', [
		'type' => 'string',
		'sanitize_callback' => 'sanitize_email',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_DESIGN, 'fromscratch_design', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_design_variables',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_SECURITY, 'fromscratch_site_password_protection', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_site_password_protection',
	]);
}, 5);

function fs_sanitize_features($value): array
{
	if (!is_array($value)) {
		return [];
	}
	$keys = ['enable_blogs', 'enable_svg', 'enable_duplicate_post', 'enable_seo'];
	$out = [];
	foreach ($keys as $key) {
		$out[$key] = (!empty($value[$key])) ? 1 : 0;
	}
	return $out;
}

function fs_sanitize_admin_access($value): array
{
	$defaults = function_exists('fs_admin_access_defaults') ? fs_admin_access_defaults() : [];
	if (!is_array($value)) {
		return $defaults;
	}
	$out = [];
	foreach (array_keys($defaults) as $item) {
		$out[$item] = [
			'admin' => !empty($value[$item]['admin']) ? 1 : 0,
			'developer' => !empty($value[$item]['developer']) ? 1 : 0,
		];
	}
	return $out;
}

function fs_sanitize_site_password_protection($value): string
{
	$enabled = !empty($value) ? '1' : '';
	$new_password = isset($_POST['fromscratch_site_password_new']) ? trim((string) wp_unslash($_POST['fromscratch_site_password_new'])) : '';
	if ($new_password !== '') {
		update_option('fromscratch_site_password_hash', wp_hash_password($new_password), true);
		update_option('fromscratch_site_password_plain', $new_password, true);
	} else {
		update_option('fromscratch_site_password_hash', '', true);
		update_option('fromscratch_site_password_plain', '', true);
	}
	return $enabled;
}

function theme_settings_page(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}
	$tab = fs_theme_settings_current_tab();
	$available_tabs = fs_theme_settings_available_tabs();
	$base_url = admin_url('options-general.php?page=fs-theme-settings');

	$bump_notice = get_transient('fromscratch_bump_notice');
	if ($bump_notice !== false) {
		delete_transient('fromscratch_bump_notice');
	}
	$clear_design_notice = get_transient('fromscratch_clear_design_notice');
	if ($clear_design_notice !== false) {
		delete_transient('fromscratch_clear_design_notice');
	}
	$bump_url = wp_nonce_url(add_query_arg('fromscratch_bump', '1', add_query_arg('tab', 'developer', $base_url)), 'fromscratch_bump_asset_version');
	$clear_design_url = wp_nonce_url(add_query_arg(['fromscratch_clear_design' => '1', 'tab' => 'developer'], $base_url), 'fromscratch_clear_design');
?>
	<div class="wrap">
		<h1><?= esc_html(__(fs_config_settings('title_page'), 'fromscratch')) ?></h1>
		<?php if ($bump_notice !== false && $tab === 'developer') : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html(sprintf(__('Asset version increased to %s.', 'fromscratch'), $bump_notice)) ?></strong></p></div>
		<?php endif; ?>
		<?php if ($clear_design_notice !== false && $tab === 'developer') : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html__('Design overrides cleared. All values reset to defaults.', 'fromscratch') ?></strong></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
			<?php foreach ($available_tabs as $slug => $label) : ?>
				<a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>" class="nav-tab <?= $tab === $slug ? 'nav-tab-active' : '' ?>"><?= esc_html(__($label, 'fromscratch')) ?><?php if ($slug === 'security' && get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') : ?> <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 2px;" aria-hidden="true"></span><?php endif; ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ($tab === 'general') : ?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Site', 'fromscratch') ?></h2>
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
		<?php elseif ($tab === 'texts') : ?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_TEXTE); ?>
			<?php
			foreach (fs_config_settings('variables.sections') as $section) {
				do_settings_sections('theme_variables_' . $section['id']);
			}
			?>
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
		<?php elseif ($tab === 'developer') : ?>
		<?php
			$asset_version = get_option('fromscratch_asset_version', '1');
			$features = get_option('fromscratch_features', []);
			if (!is_array($features)) {
				$features = [];
			}
			$feat = function ($key) use ($features) {
				return isset($features[$key]) ? (int) $features[$key] : 1;
			};
		?>
		<h2 class="title"><?= esc_html__('Cache & assets', 'fromscratch') ?></h2>
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
		<hr>
		<h2 class="title"><?= esc_html__('Theme features', 'fromscratch') ?></h2>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_GENERAL); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Blogs', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_blogs]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_blogs]" value="1" <?= checked($feat('enable_blogs'), 1, false) ?>> <?= esc_html__('Enable the Posts menu and blog/post editing.', 'fromscratch') ?></label>
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
						<label><input type="checkbox" name="fromscratch_features[enable_seo]" value="1" <?= checked($feat('enable_seo'), 1, false) ?>> <?= esc_html__('SEO panel (title, description, OG image, noindex) for posts and pages.', 'fromscratch') ?></label>
					</td>
				</tr>
			</table>
			<p class="submit"><?php submit_button(); ?></p>
		</form>
		<hr>
		<h2 class="title"><?= esc_html__('Admin access', 'fromscratch') ?></h2>
		<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Control which admin pages and Settings sections are visible to Administrators (Admin) and users with developer rights (Developer). Uncheck to hide from that role.', 'fromscratch') ?></p>
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
			];
		?>
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
		<hr>
		<h2 class="title"><?= esc_html__('Design', 'fromscratch') ?></h2>
		<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Clear all design variable overrides and reset to theme defaults.', 'fromscratch') ?></p>
		<p>
			<a href="<?= esc_url($clear_design_url) ?>" class="button" onclick="return confirm('<?= esc_js(__('Reset all design overrides to defaults?', 'fromscratch')) ?>');"><?= esc_html__('Clear all overrides', 'fromscratch') ?></a>
		</p>
		<hr>
		<h2 class="title"><?= esc_html__('Tools', 'fromscratch') ?></h2>
		<p class="description"><?= esc_html__('More developer tools (e.g. sitemap generator, post expirator) can be added here in future updates.', 'fromscratch') ?></p>
		<?php else : ?>
		<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Override SCSS design variables. Values are output as CSS custom properties (:root). Add new variables in config/theme.php under design.sections.', 'fromscratch') ?></p>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_DESIGN); ?>
			<?php
			$design_sections = fs_get_design_sections_resolved();
			foreach ($design_sections as $section_id => $section) :
				$section_title = $section['title'];
			?>
			<div class="fromscratch-design-section" style="margin-bottom: 24px;">
				<h2 class="title"><?= esc_html($section_title) ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ($section['variables'] as $v) :
						$var_id = $v['id'] ?? '';
						$var_title = $v['title'] ?? $var_id;
						$var_type = (isset($v['type']) && $v['type'] === 'color') ? 'color' : 'text';
						$override = fs_design_variable_override($var_id);
						$default = $v['default'] ?? '';
						$input_name = 'fromscratch_design[' . esc_attr($var_id) . ']';
					?>
						<tr>
							<th scope="row" style="font-weight: normal; width: 220px;">
								<code style="font-size: 12px;">--<?= esc_html($var_id) ?></code>
							</th>
							<td>
								<label for="fromscratch_design_<?= esc_attr($var_id) ?>" class="screen-reader-text"><?= esc_html($var_title) ?></label>
								<?php if ($var_type === 'color') : ?>
								<input type="text" name="<?= $input_name ?>" id="fromscratch_design_<?= esc_attr($var_id) ?>" value="<?= esc_attr($override) ?>" placeholder="<?= esc_attr($default) ?>" class="code" style="width: 120px;" maxlength="22">
								<?php else : ?>
								<input type="text" name="<?= $input_name ?>" id="fromscratch_design_<?= esc_attr($var_id) ?>" value="<?= esc_attr($override) ?>" placeholder="<?= esc_attr($default) ?>" class="regular-text" style="width: 200px;">
								<?php endif; ?>
								<span class="description" style="margin-left: 8px; color: #646970;"><?= esc_html($var_title) ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endforeach; ?>
			<p class="submit"><?php submit_button(); ?></p>
		</form>
		<?php endif; ?>
	</div>
<?php
}

function display_custom_info_field($variable, $variableId, $languageId = null): void
{
	if ($languageId) {
		echo '<div class="page-settings-language-container page-settings-language-container-' . $languageId . '">';
	}
	switch ($variable['type']) {
		case 'textfield':
			echo '<input class="settings-page-textfield" type="text" name="' . esc_attr($variableId) . '" value="' . esc_attr(get_option($variableId, '')) . '" style="width: ' . (int) ($variable['width'] ?? 400) . 'px">';
			echo '<div style="color: #999; font-size: 12px; margin: 4px 0 0 4px; font-family: monospace;">' . esc_html($variableId) . '</div>';
			break;
		case 'textarea':
			echo '<textarea class="settings-page-textfield" name="' . esc_attr($variableId) . '" rows="' . (int) ($variable['rows'] ?? 4) . '" style="width: ' . (int) ($variable['width'] ?? 400) . 'px">' . esc_textarea(get_option($variableId, '')) . '</textarea>';
			echo '<div style="color: #999; font-size: 12px; margin: 4px 0 0 4px; font-family: monospace;">' . esc_html($variableId) . '</div>';
			break;
	}
	if (!empty($variable['description'])) {
		echo '<div class="page-settings-description">' . esc_html($variable['description']) . '</div>';
	}
	if ($languageId) {
		echo '</div>';
	}
}

function display_custom_info_fields(): void
{
	$sections = fs_config_settings('variables.sections');
	if (!is_array($sections)) {
		return;
	}
	foreach ($sections as $section) {
		add_settings_section('section', $section['title'], null, 'theme_variables_' . $section['id']);
		foreach ($section['variables'] as $variable) {
			$variableId = 'theme_variables_' . $section['id'] . '_' . $variable['id'];
			if (!empty($variable['translate'])) {
				foreach (fs_config_settings('languages') as $language) {
					$variableIdLang = $variableId . '_' . $language['id'];
					add_settings_field($variableIdLang, $variable['title'], function () use ($variable, $variableIdLang, $language) {
						display_custom_info_field($variable, $variableIdLang, $language['id']);
					}, 'theme_variables_' . $section['id'], 'section');
					register_setting(FS_THEME_OPTION_GROUP_TEXTE, $variableIdLang);
				}
			} else {
				add_settings_field($variableId, $variable['title'], function () use ($variable, $variableId) {
					display_custom_info_field($variable, $variableId);
				}, 'theme_variables_' . $section['id'], 'section');
				register_setting(FS_THEME_OPTION_GROUP_TEXTE, $variableId);
			}
		}
	}
}
add_action('admin_init', 'display_custom_info_fields');

function add_theme_settings_menu_item(): void
{
		add_submenu_page(
		'options-general.php',
		__(fs_config_settings('title_page'), 'fromscratch'),
		__(fs_config_settings('title_menu'), 'fromscratch'),
		'manage_options',
		'fs-theme-settings',
		'theme_settings_page',
		0
	);
}
add_action('admin_menu', 'add_theme_settings_menu_item', 1);

add_filter('submenu_file', function ($submenu_file, $parent_file) {
	if ($parent_file === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'fs-theme-settings') {
		return 'fs-theme-settings';
	}
	return $submenu_file;
}, 10, 2);
