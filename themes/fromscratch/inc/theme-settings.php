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
];

function fs_theme_settings_available_tabs(): array
{
	$is_dev = function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id());
	$tabs = [];
	foreach (FS_THEME_SETTINGS_TABS as $slug => $def) {
		if ($def['developer_only'] && !$is_dev) {
			continue;
		}
		if (function_exists('fs_admin_can_access')) {
			$access_key = null;
			if ($slug === 'general') {
				$access_key = 'theme_settings_general';
			} elseif ($slug === 'security') {
				$access_key = 'theme_settings_security';
			} elseif ($slug === 'texts') {
				$access_key = 'theme_settings_texts';
			} elseif ($slug === 'design') {
				$access_key = 'theme_settings_design';
			}
			if ($access_key !== null && !fs_admin_can_access($access_key)) {
				continue;
			}
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

// Clear design overrides (developer only); redirects back to Design tab
add_action('load-settings_page_fs-theme-settings', function () {
	if (!current_user_can('manage_options') || empty($_GET['fromscratch_clear_design']) || empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_clear_design')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	delete_option('fromscratch_design');
	set_transient('fromscratch_clear_design_notice', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=design'));
	exit;
});

// Redirect when access to requested tab is denied
add_action('load-settings_page_fs-theme-settings', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	$requested = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	if ($requested === '') {
		return;
	}
	if ($requested === 'general') {
		if (function_exists('fs_admin_can_access') && !fs_admin_can_access('theme_settings_general')) {
			wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings'));
			exit;
		}
		return;
	}
	if (!function_exists('fs_admin_can_access')) {
		return;
	}
	$access_key = null;
	if ($requested === 'security') {
		$access_key = 'theme_settings_security';
	} elseif ($requested === 'texts') {
		$access_key = 'theme_settings_texts';
	} elseif ($requested === 'design') {
		$access_key = 'theme_settings_design';
	}
	if ($access_key !== null && !fs_admin_can_access($access_key)) {
		wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=general'));
		exit;
	}
}, 1);

const FS_THEME_OPTION_GROUP_GENERAL = 'fs_theme_general';
const FS_THEME_OPTION_GROUP_FEATURES = 'fs_theme_features';
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
		'sanitize_callback' => 'fs_sanitize_asset_version',
	]);
	register_setting(FS_THEME_OPTION_GROUP_DEVELOPER, 'admin_email', [
		'type' => 'string',
		'sanitize_callback' => 'sanitize_email',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_excerpt_length', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_excerpt_length',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_excerpt_more', [
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_FEATURES, 'fromscratch_features', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_features',
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

/**
 * Preserve existing asset version when the option is not in the form (e.g. saving Features only).
 * Prevents cache version from being reset when saving other General-group forms.
 */
function fs_sanitize_asset_version($value): string
{
	$value = is_string($value) ? trim($value) : '';
	if ($value === '') {
		return (string) get_option('fromscratch_asset_version', '1');
	}
	return sanitize_text_field($value);
}

function fs_sanitize_excerpt_length($value): string
{
	$value = is_string($value) ? trim($value) : '';
	if ($value === '') {
		return '';
	}
	$n = absint($value);
	return (string) ($n > 0 ? $n : '');
}

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
	if (!fs_theme_settings_has_any_access()) {
		wp_safe_redirect(admin_url('options-general.php'));
		exit;
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
	$clear_design_url = wp_nonce_url(add_query_arg(['fromscratch_clear_design' => '1', 'tab' => 'design'], $base_url), 'fromscratch_clear_design');
?>
	<div class="wrap">
		<h1><?= esc_html(__(fs_config_settings('title_page'), 'fromscratch')) ?></h1>
		<?php if ($clear_design_notice !== false && $tab === 'design') : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html__('Design overrides cleared. All values reset to defaults.', 'fromscratch') ?></strong></p></div>
		<?php endif; ?>

		<?php if (count($available_tabs) > 1) : ?>
		<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
			<?php foreach ($available_tabs as $slug => $label) : ?>
				<a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>" class="nav-tab <?= $tab === $slug ? 'nav-tab-active' : '' ?>"><?= esc_html(__($label, 'fromscratch')) ?><?php if ($slug === 'security' && get_option('fromscratch_site_password_protection') === '1' && get_option('fromscratch_site_password_hash', '') !== '') : ?> <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; margin-left: 2px;" aria-hidden="true"></span><?php endif; ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>

		<?php if ($tab === 'general') : ?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Excerpt', 'fromscratch') ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="fromscratch_excerpt_length"><?= esc_html__('Excerpt length', 'fromscratch') ?></label>
					</th>
					<td>
						<?php
						$excerpt_length_opt = get_option('fromscratch_excerpt_length', '');
						$excerpt_length_val = $excerpt_length_opt !== '' ? $excerpt_length_opt : '60';
						?>
						<input type="number" name="fromscratch_excerpt_length" id="fromscratch_excerpt_length" value="<?= esc_attr($excerpt_length_val) ?>" min="1" max="999" step="1" class="small-text">
						<p class="description"><?= esc_html__('Number of words used when trimming excerpts.', 'fromscratch') ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fromscratch_excerpt_more"><?= esc_html__('Excerpt "more" text', 'fromscratch') ?></label>
					</th>
					<td>
						<?php
						$excerpt_more_opt = get_option('fromscratch_excerpt_more');
						$excerpt_more_val = $excerpt_more_opt !== false ? $excerpt_more_opt : '…';
						?>
						<input type="text" name="fromscratch_excerpt_more" id="fromscratch_excerpt_more" value="<?= esc_attr($excerpt_more_val) ?>" class="small-text" maxlength="20">
						<p class="description"><?= esc_html__('Text shown after the excerpt when it is truncated (e.g. …). Leave blank for none.', 'fromscratch') ?></p>
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
		<?php else : ?>
		<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Override SCSS design variables. Values are output as CSS custom properties (:root). Add new variables in config/theme.php under design.sections.', 'fromscratch') ?></p>
		<?php if (function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id())) : ?>
		<p style="margin-bottom: 16px;">
			<a href="<?= esc_url($clear_design_url) ?>" class="button" onclick="return confirm('<?= esc_js(__('Reset all design overrides to defaults?', 'fromscratch')) ?>');"><?= esc_html__('Clear all overrides', 'fromscratch') ?></a>
		</p>
		<?php endif; ?>
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

/**
 * Whether the current user has access to at least one Theme settings tab (for menu visibility).
 */
function fs_theme_settings_has_any_access(): bool
{
	if (function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id())) {
		return true;
	}
	if (!function_exists('fs_admin_can_access')) {
		return true;
	}
	$keys = ['theme_settings_general', 'theme_settings_texts', 'theme_settings_design', 'theme_settings_security'];
	foreach ($keys as $key) {
		if (fs_admin_can_access($key)) {
			return true;
		}
	}
	return false;
}

function add_theme_settings_menu_item(): void
{
	if (!fs_theme_settings_has_any_access()) {
		return;
	}
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
