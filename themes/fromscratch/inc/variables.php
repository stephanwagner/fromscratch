<?php

defined('ABSPATH') || exit;

/**
 * Theme settings: handle "Bump" asset version
 */
add_action('load-settings_page_fs-theme-settings', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (empty($_GET['fromscratch_bump']) || empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_bump_asset_version')) {
		return;
	}
	$current = get_option('fromscratch_asset_version', '1');
	$next = is_numeric($current) ? (string) ((int) $current + 1) : '2';
	update_option('fromscratch_asset_version', $next);
	set_transient('fromscratch_bump_notice', $next, 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings'));
	exit;
});

/**
 * Theme settings: handle "Clear all" design overrides
 */
add_action('load-settings_page_fs-theme-settings', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (empty($_GET['fromscratch_clear_design']) || empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_clear_design')) {
		return;
	}
	delete_option('fromscratch_design');
	set_transient('fromscratch_clear_design_notice', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=design'));
	exit;
});

/** Option group per tab so saving one tab does not affect others. */
const FS_THEME_OPTION_GROUP_GENERAL = 'fs_theme_general';
const FS_THEME_OPTION_GROUP_TEXTE = 'fs_theme_texte';
const FS_THEME_OPTION_GROUP_DESIGN = 'fs_theme_design';
const FS_THEME_OPTION_GROUP_SECURITY = 'fs_theme_security';

/**
 * Register General tab options.
 */
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

/**
 * Register Design tab options.
 */
add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_DESIGN, 'fromscratch_design', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_design_variables',
	]);
}, 5);

/**
 * Register Security tab options.
 */
add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_SECURITY, 'fromscratch_site_password_protection', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_site_password_protection',
	]);
}, 5);

/**
 * Sanitize theme features option (checkboxes: enable_svg, enable_duplicate_post, enable_seo).
 *
 * @param array<string, int>|mixed $value Raw POST value.
 * @return array<string, int>
 */
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

/**
 * Sanitize site password protection checkbox and update stored password from field.
 * Non-empty field: set new password. Empty field and save: clear password (reset).
 *
 * @param mixed $value Raw POST value for the checkbox.
 * @return string '1' or ''
 */
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

/**
 * Check whether a theme feature is enabled (Settings → Theme → General).
 * When the option was never saved, all features are considered enabled.
 *
 * @param string $feature One of: blogs, svg, duplicate_post, seo.
 * @return bool
 */
function fs_theme_feature_enabled(string $feature): bool
{
	$map = [
		'blogs' => 'enable_blogs',
		'svg' => 'enable_svg',
		'duplicate_post' => 'enable_duplicate_post',
		'seo' => 'enable_seo',
	];
	$key = $map[$feature] ?? '';
	if ($key === '') {
		return false;
	}
	$options = get_option('fromscratch_features', []);
	if (!is_array($options) || !array_key_exists($key, $options)) {
		return true;
	}
	return (int) $options[$key] === 1;
}

/**
 * Sanitize a string for use as CSS custom property value (prevent breaking out of :root block).
 *
 * @param string $value Raw value.
 * @return string Safe value (allowed: alphanumeric, space, #.,()%-_/:;"' and common units).
 */
function fs_sanitize_css_custom_property_value(string $value): string
{
	$value = preg_replace('/[^\w\s#.,()%\-\/_\\:;"\']/', '', $value);
	$value = str_replace(["\r", "\n", "\t", '<', '>'], '', $value);
	return substr($value, 0, 500);
}

/**
 * Get all design variables as a flat list from config (id, title, default, type).
 * Sections with 'from' => 'theme_colors' are built from config theme_colors (single source for WP panel + Design tab).
 *
 * @return array<int, array{id: string, title: string, default: string, type: string}>
 */
function fs_get_design_variables_list(): array
{
	$sections = fs_config('design.sections');
	if (!is_array($sections)) {
		return [];
	}
	$list = [];
	foreach ($sections as $section) {
		$variables = [];

		if (!empty($section['from']) && $section['from'] === 'theme_colors') {
			$theme_colors = fs_config('theme_colors');
			if (is_array($theme_colors)) {
				foreach ($theme_colors as $tc) {
					if (!empty($tc['slug']) && isset($tc['color'])) {
						$variables[] = [
							'id' => 'color-' . (string) $tc['slug'],
							'title' => isset($tc['name']) ? (string) $tc['name'] : (string) $tc['slug'],
							'default' => (string) $tc['color'],
							'type' => 'color',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_gradients') {
			$theme_gradients = fs_config('theme_gradients');
			if (is_array($theme_gradients)) {
				foreach ($theme_gradients as $tg) {
					if (!empty($tg['slug']) && isset($tg['gradient'])) {
						$variables[] = [
							'id' => 'gradient-' . (string) $tg['slug'],
							'title' => isset($tg['name']) ? (string) $tg['name'] : (string) $tg['slug'],
							'default' => (string) $tg['gradient'],
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_font_sizes') {
			$theme_font_sizes = fs_config('theme_font_sizes');
			if (is_array($theme_font_sizes)) {
				foreach ($theme_font_sizes as $tfs) {
					if (!empty($tfs['slug']) && isset($tfs['size'])) {
						$variables[] = [
							'id' => 'font-size-' . (string) $tfs['slug'],
							'title' => isset($tfs['name']) ? (string) $tfs['name'] : (string) $tfs['slug'],
							'default' => (string) $tfs['size'] . 'px',
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['variables']) && is_array($section['variables'])) {
			foreach ($section['variables'] as $v) {
				if (!empty($v['id']) && isset($v['default'])) {
					$variables[] = [
						'id' => (string) $v['id'],
						'title' => isset($v['title']) ? (string) $v['title'] : $v['id'],
						'default' => (string) $v['default'],
						'type' => isset($v['type']) && in_array($v['type'], ['color', 'text'], true) ? $v['type'] : 'text',
					];
				}
			}
		}

		foreach ($variables as $v) {
			$list[] = $v;
		}
	}
	return $list;
}

/**
 * Get design sections with variables resolved (theme_colors, theme_gradients, theme_font_sizes expanded).
 * Use this for the Design tab UI so each section has a full 'variables' array.
 *
 * @return array<string, array{title: string, variables: array<int, array{id: string, title: string, default: string, type: string}>}>
 */
function fs_get_design_sections_resolved(): array
{
	$sections = fs_config('design.sections');
	if (!is_array($sections)) {
		return [];
	}
	$resolved = [];
	foreach ($sections as $section_id => $section) {
		$variables = [];

		if (!empty($section['from']) && $section['from'] === 'theme_colors') {
			$theme_colors = fs_config('theme_colors');
			if (is_array($theme_colors)) {
				foreach ($theme_colors as $tc) {
					if (!empty($tc['slug']) && isset($tc['color'])) {
						$variables[] = [
							'id' => 'color-' . (string) $tc['slug'],
							'title' => isset($tc['name']) ? (string) $tc['name'] : (string) $tc['slug'],
							'default' => (string) $tc['color'],
							'type' => 'color',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_gradients') {
			$theme_gradients = fs_config('theme_gradients');
			if (is_array($theme_gradients)) {
				foreach ($theme_gradients as $tg) {
					if (!empty($tg['slug']) && isset($tg['gradient'])) {
						$variables[] = [
							'id' => 'gradient-' . (string) $tg['slug'],
							'title' => isset($tg['name']) ? (string) $tg['name'] : (string) $tg['slug'],
							'default' => (string) $tg['gradient'],
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_font_sizes') {
			$theme_font_sizes = fs_config('theme_font_sizes');
			if (is_array($theme_font_sizes)) {
				foreach ($theme_font_sizes as $tfs) {
					if (!empty($tfs['slug']) && isset($tfs['size'])) {
						$variables[] = [
							'id' => 'font-size-' . (string) $tfs['slug'],
							'title' => isset($tfs['name']) ? (string) $tfs['name'] : (string) $tfs['slug'],
							'default' => (string) $tfs['size'] . 'px',
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['variables']) && is_array($section['variables'])) {
			foreach ($section['variables'] as $v) {
				if (!empty($v['id']) && isset($v['default'])) {
					$variables[] = [
						'id' => (string) $v['id'],
						'title' => isset($v['title']) ? (string) $v['title'] : $v['id'],
						'default' => (string) $v['default'],
						'type' => isset($v['type']) && in_array($v['type'], ['color', 'text'], true) ? $v['type'] : 'text',
					];
				}
			}
		}

		if ($variables !== []) {
			$resolved[$section_id] = [
				'title' => isset($section['title']) ? (string) $section['title'] : $section_id,
				'variables' => $variables,
			];
		}
	}
	return $resolved;
}

/**
 * Get the override value for a design variable (saved custom value only). Empty string when using default.
 * Use for the form input value: show override if set, else empty so placeholder (default) is visible.
 *
 * @param string $id Variable id (e.g. "color-primary").
 * @return string Override value or '' when using default.
 */
function fs_design_variable_override(string $id): string
{
	$saved = get_option('fromscratch_design', []);
	if (is_array($saved) && array_key_exists($id, $saved) && $saved[$id] !== '') {
		return (string) $saved[$id];
	}
	return '';
}

/**
 * Get effective value for a design variable (override if set, otherwise default from config).
 * Use for CSS output and anywhere the final value is needed.
 *
 * @param string $id Variable id (e.g. "color-primary").
 * @return string
 */
function fs_design_variable_value(string $id): string
{
	$override = fs_design_variable_override($id);
	if ($override !== '') {
		return $override;
	}
	foreach (fs_get_design_variables_list() as $v) {
		if ($v['id'] === $id) {
			return $v['default'];
		}
	}
	return '';
}

/**
 * Sanitize design variables on save: only persist non-empty overrides. Empty field = use default (do not store).
 *
 * @param array<string, string>|mixed $input Posted values.
 * @return array<string, string>
 */
function fs_sanitize_design_variables($input): array
{
	$vars = fs_get_design_variables_list();
	$by_id = [];
	foreach ($vars as $v) {
		$by_id[$v['id']] = $v;
	}
	$result = [];
	$input = is_array($input) ? $input : [];
	foreach ($by_id as $id => $def) {
		$val = isset($input[$id]) ? $input[$id] : '';
		$val = is_string($val) ? trim($val) : '';
		if ($val === '') {
			continue;
		}
		$result[$id] = sanitize_text_field($val);
	}
	return $result;
}

/**
 * Theme settings: one menu page with three tab-URLs (?tab=general|texte|design). Tabs are links to different URLs.
 *
 * @return void
 */
function theme_settings_page(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}
	$tabs = ['general', 'texte', 'design', 'security'];
	$tab = isset($_GET['tab']) && in_array($_GET['tab'], $tabs, true) ? $_GET['tab'] : 'general';
	$base_url = admin_url('options-general.php?page=fs-theme-settings');

	$bump_notice = get_transient('fromscratch_bump_notice');
	if ($bump_notice !== false) {
		delete_transient('fromscratch_bump_notice');
	}
	$clear_design_notice = get_transient('fromscratch_clear_design_notice');
	if ($clear_design_notice !== false) {
		delete_transient('fromscratch_clear_design_notice');
	}
	$bump_url = wp_nonce_url(add_query_arg('fromscratch_bump', '1', $base_url), 'fromscratch_bump_asset_version');
	$clear_design_url = wp_nonce_url(add_query_arg(['fromscratch_clear_design' => '1', 'tab' => 'design'], $base_url), 'fromscratch_clear_design');
?>
	<div class="wrap">
		<h1><?= esc_html(fs_config_variables('title_page')) ?></h1>
		<?php if ($bump_notice !== false && $tab === 'general') : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html(sprintf(__('Asset version increased to %s.', 'fromscratch'), $bump_notice)) ?></strong></p></div>
		<?php endif; ?>
		<?php if ($clear_design_notice !== false && $tab === 'design') : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html__('Design overrides cleared. All values reset to defaults.', 'fromscratch') ?></strong></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
			<a href="<?= esc_url($base_url . '&tab=general') ?>" class="nav-tab <?= $tab === 'general' ? 'nav-tab-active' : '' ?>"><?= esc_html__('General', 'fromscratch') ?></a>
			<a href="<?= esc_url($base_url . '&tab=texte') ?>" class="nav-tab <?= $tab === 'texte' ? 'nav-tab-active' : '' ?>"><?= esc_html__('Texts', 'fromscratch') ?></a>
			<a href="<?= esc_url($base_url . '&tab=design') ?>" class="nav-tab <?= $tab === 'design' ? 'nav-tab-active' : '' ?>"><?= esc_html__('Design', 'fromscratch') ?></a>
			<a href="<?= esc_url($base_url . '&tab=security') ?>" class="nav-tab <?= $tab === 'security' ? 'nav-tab-active' : '' ?>"><?= esc_html__('Security', 'fromscratch') ?><?php if (get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') : ?> <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 2px;" aria-hidden="true"></span><?php endif; ?></a>
		</nav>

		<?php if ($tab === 'general') : ?>
		<?php
			$features = get_option('fromscratch_features', []);
			if (!is_array($features)) {
				$features = [];
			}
			$feat = function ($key) use ($features) {
				return isset($features[$key]) ? (int) $features[$key] : 1;
			};
		?>
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
						<p class="description"><?= esc_html__('Used for account recovery and notifications.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<hr>
			<h2 class="title"><?= esc_html__('Cache', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Use fs_asset_url( \'/path/to/file.css\' ) in templates to output the asset URL with ?ver= so the browser cache updates when you bump the version below.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="fromscratch_asset_version"><?= esc_html__('Cache version', 'fromscratch') ?></label>
					</th>
					<td>
						<input type="text" name="fromscratch_asset_version" id="fromscratch_asset_version" value="<?= esc_attr(get_option('fromscratch_asset_version', '1')) ?>" class="small-text" style="width: 64px;">
						<a href="<?= esc_url($bump_url) ?>" class="button" style="margin-left: 8px;"><?= esc_html__('Bump version', 'fromscratch') ?></a>
						<p class="description"><?= esc_html__('Bump when static theme files have been changed so the cache of the files is updated.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<hr>
			<h2 class="title"><?= esc_html__('Theme features', 'fromscratch') ?></h2>
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
		<?php elseif ($tab === 'texte') : ?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_TEXTE); ?>
			<?php
			foreach (fs_config_variables('variables.sections') as $section) {
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
		<?php else : ?>
		<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Override SCSS design variables. Values are output as CSS custom properties (:root). Add new variables in config.php under design.sections.', 'fromscratch') ?></p>
		<p style="margin-bottom: 16px;">
			<a href="<?= esc_url($clear_design_url) ?>" class="button" onclick="return confirm('<?= esc_js(__('Reset all design overrides to defaults?', 'fromscratch')) ?>');"><?= esc_html__('Clear all', 'fromscratch') ?></a>
		</p>
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

/**
 * Output a single template variable field (text or textarea) for the theme settings page.
 *
 * @param array<string,mixed> $variable   Variable config (type, width, rows, description, etc.).
 * @param string              $variableId Option name / field name.
 * @param string|null         $languageId Optional language code for translatable fields.
 * @return void
 */
function display_custom_info_field($variable, $variableId, $languageId = null): void
{
	if ($languageId) {
		echo '<div class="page-settings-language-container page-settings-language-container-' . $languageId . '">';
	}

	switch ($variable['type']) {
		case 'textfield':
			echo '<input class="settings-page-textfield" type="text" name="' . $variableId . '" value="' . get_option($variableId) . '" style="width: ' . $variable['width'] . 'px">';
			echo '<div style="color: #999; font-size: 12px; margin: 4px 0 0 4px; font-family: monospace;">' . $variableId . '</div>';
			break;
		case 'textarea':
			echo '<textarea class="settings-page-textfield" name="' . $variableId . '" rows="' . $variable['rows'] . '" style="width: ' . $variable['width'] . 'px">' . get_option($variableId) . '</textarea>';
			echo '<div style="color: #999; font-size: 12px; margin: 4px 0 0 4px; font-family: monospace;">' . $variableId . '</div>';
			break;
	}

	if (!empty($variable['description'])) {
		echo '<div class="page-settings-description">' . $variable['description'] . '</div>';
	}

	if ($languageId) {
		echo '</div>';
	}
}

/**
 * Register settings sections and fields for theme variables (Texte tab).
 *
 * @return void
 */
function display_custom_info_fields(): void
{
	foreach (fs_config_variables('variables.sections') as $section) {
		add_settings_section('section', $section['title'], null, 'theme_variables_' . $section['id']);

		foreach ($section['variables'] as $variable) {
			$variableId = 'theme_variables_' . $section['id'] . '_' . $variable['id'];

			if (!empty($variable['translate'])) {
				foreach (fs_config_variables('languages') as $language) {
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

/**
 * Add theme settings (one menu item) under Settings. Tabs are separate URLs: ?page=fs-theme-settings&tab=general|texte|design.
 *
 * @return void
 */
function add_custom_info_menu_item(): void
{
	add_submenu_page(
		'options-general.php',
		fs_config_variables('title_page'),
		fs_config_variables('title_menu'),
		'manage_options',
		'fs-theme-settings',
		'theme_settings_page',
		0
	);
}
add_action('admin_menu', 'add_custom_info_menu_item', 1);

add_filter('submenu_file', function ($submenu_file, $parent_file) {
	if ($parent_file === 'options-general.php' && isset($_GET['page']) && $_GET['page'] === 'fs-theme-settings') {
		return 'fs-theme-settings';
	}
	return $submenu_file;
}, 10, 2);

/**
 * Output :root { --var: value; } for design variables so SCSS var() picks them up.
 *
 * @return void
 */
function fs_output_design_css(): void
{
	$vars = fs_get_design_variables_list();
	if ($vars === []) {
		return;
	}
	$lines = [];
	foreach ($vars as $v) {
		$value = fs_design_variable_value($v['id']);
		$value = fs_sanitize_css_custom_property_value($value);
		$lines[] = '  --' . $v['id'] . ': ' . $value . ';';
	}
	if ($lines === []) {
		return;
	}
	echo "\n<style id=\"fromscratch-design-vars\">\n:root {\n" . implode("\n", $lines) . "\n}\n</style>\n";
}
