<?php

defined('ABSPATH') || exit;

/**
 * Theme settings page: Settings → Theme.
 * Developer-first: some tabs/sections are only visible to users with developer rights.
 * Requires inc/design.php for Design tab.
 */

/** Tab definitions: slug => [ 'label' => string, 'developer_only' => bool ] */
const FS_THEME_SETTINGS_TABS = [
	'general'   => ['label' => 'General', 'developer_only' => false],
	'texts'     => ['label' => 'Texts', 'developer_only' => false],
	'design'    => ['label' => 'Design', 'developer_only' => false],
	'css'       => ['label' => 'CSS', 'developer_only' => false],
	'redirects' => ['label' => 'Redirects', 'developer_only' => false],
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
			} elseif ($slug === 'texts') {
				$access_key = 'theme_settings_texts';
			} elseif ($slug === 'design') {
				$access_key = 'theme_settings_design';
			} elseif ($slug === 'css') {
				$access_key = 'theme_settings_css';
			} elseif ($slug === 'redirects') {
				$access_key = 'theme_settings_redirects';
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

// Enqueue media picker for General tab (fallback OG image)
add_action('admin_enqueue_scripts', function ($hook_suffix) {
	if ($hook_suffix !== 'settings_page_fs-theme-settings') {
		return;
	}
	$requested_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	$is_general = ($requested_tab === 'general' || $requested_tab === '');
	if (!$is_general) {
		return;
	}
	if (!current_user_can('manage_options') || (function_exists('fs_admin_can_access') && !fs_admin_can_access('theme_settings_general'))) {
		return;
	}
	wp_enqueue_media();
	$script_path = get_theme_file_path('src/js/admin/og-fallback-picker.js');
	$script_url = get_theme_file_uri('src/js/admin/og-fallback-picker.js');
	$version = file_exists($script_path) ? (string) filemtime($script_path) : '1';
	wp_enqueue_script(
		'fs-og-fallback-picker',
		$script_url,
		['jquery', 'media-editor'],
		$version,
		true
	);
}, 10);

// Enqueue WordPress code editor (syntax highlight, lint) for CSS tab
add_action('admin_enqueue_scripts', function ($hook_suffix) {
	if ($hook_suffix !== 'settings_page_fs-theme-settings') {
		return;
	}
	$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	if ($tab !== 'css') {
		return;
	}
	if (!current_user_can('manage_options') || (function_exists('fs_admin_can_access') && !fs_admin_can_access('theme_settings_css'))) {
		return;
	}
	$settings = wp_enqueue_code_editor([
		'type' => 'text/css',
	]);
	if ($settings === false) {
		return;
	}
	wp_add_inline_style('code-editor', '
		.fs-custom-css-editor-wrap { resize: vertical; overflow: auto; display: block; }
		.fs-custom-css-editor-wrap .CodeMirror { min-height: 100% !important; }
	');
	wp_add_inline_script('code-editor', sprintf(
		'jQuery(function() { if (wp.codeEditor && document.getElementById("fromscratch_custom_css")) { wp.codeEditor.initialize("fromscratch_custom_css", %s); } });',
		wp_json_encode($settings)
	));
}, 10);

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
	if ($requested === 'texts') {
		$access_key = 'theme_settings_texts';
	} elseif ($requested === 'design') {
		$access_key = 'theme_settings_design';
	} elseif ($requested === 'css') {
		$access_key = 'theme_settings_css';
	} elseif ($requested === 'redirects') {
		$access_key = 'theme_settings_redirects';
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
const FS_THEME_OPTION_GROUP_CSS = 'fs_theme_css';
const FS_THEME_OPTION_GROUP_SECURITY = 'fs_theme_security';
const FS_THEME_OPTION_GROUP_REDIRECTS = 'fs_theme_redirects';
const FS_THEME_OPTION_GROUP_DEVELOPER = 'fs_theme_developer';
const FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL = 'fs_theme_developer_general';

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_DEVELOPER, 'fromscratch_admin_access', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_admin_access',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL, 'admin_email', [
		'type' => 'string',
		'sanitize_callback' => 'sanitize_email',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_asset_version', [
		'type' => 'string',
		'default' => '1',
		'sanitize_callback' => 'fs_sanitize_asset_version',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_excerpt_length', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_excerpt_length',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_excerpt_more', [
		'type' => 'string',
		'sanitize_callback' => 'sanitize_text_field',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_og_image_fallback', [
		'type' => 'integer',
		'sanitize_callback' => 'fs_sanitize_og_image_fallback',
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
	register_setting(FS_THEME_OPTION_GROUP_CSS, 'fromscratch_custom_css', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_custom_css',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_SECURITY, 'fromscratch_site_password_protection', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_site_password_protection',
	]);
	register_setting(FS_THEME_OPTION_GROUP_SECURITY, 'fromscratch_maintenance_mode', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_maintenance_mode',
	]);
	register_setting(FS_THEME_OPTION_GROUP_SECURITY, 'fromscratch_maintenance_title', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_maintenance_title',
	]);
	register_setting(FS_THEME_OPTION_GROUP_SECURITY, 'fromscratch_maintenance_description', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_maintenance_description',
	]);
}, 5);

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_REDIRECTS, 'fs_redirects', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_redirects',
	]);
	register_setting(FS_THEME_OPTION_GROUP_REDIRECTS, 'fs_redirect_method', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_redirect_method',
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

function fs_sanitize_og_image_fallback($value): int
{
	$id = absint($value);
	if ($id <= 0) {
		return 0;
	}
	return wp_attachment_is_image($id) ? $id : 0;
}

/**
 * Sanitize custom CSS: strip HTML, limit length. Output is escaped when printed.
 */
function fs_sanitize_custom_css($value): string
{
	$value = is_string($value) ? $value : '';
	$value = wp_strip_all_tags($value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
	return substr($value, 0, 256 * 1024);
}

/**
 * Sanitize redirect method: wordpress or htaccess.
 */
function fs_sanitize_redirect_method($value): string
{
	$value = is_string($value) ? trim($value) : '';
	$value = in_array($value, ['wordpress', 'htaccess'], true) ? $value : 'wordpress';
	if ($value === 'wordpress') {
		fs_remove_redirects_htaccess_block();
	}
	return $value;
}

/**
 * Sanitize redirects list. Expects POST fs_redirects as array of [from, to, code]. Saves as fs_redirects keyed by from path.
 */
function fs_sanitize_redirects($value): array
{
	$out = [];
	if (!is_array($value)) {
		$value = [];
	}
	$allowed_codes = [301, 302];
	foreach ($value as $row) {
		if (!is_array($row)) {
			continue;
		}
		$from = isset($row['from']) ? trim((string) $row['from']) : '';
		$to = isset($row['to']) ? trim((string) $row['to']) : '';
		$code = isset($row['code']) ? absint($row['code']) : 301;
		if ($from === '' || $to === '') {
			continue;
		}
		$from = fs_normalize_redirect_from_path($from);
		$to = fs_normalize_redirect_to_path($to);
		if ($from === '' || $to === '') {
			continue;
		}
		$code = in_array($code, $allowed_codes, true) ? $code : 301;
		$out[$from] = ['to' => $to, 'code' => $code];
	}
	// Use POSTed method so we act on the same save; get_option can still be old when both options save in one request.
	$method = isset($_POST['fs_redirect_method']) ? sanitize_text_field(wp_unslash($_POST['fs_redirect_method'])) : get_option('fs_redirect_method', 'wordpress');
	if ($method === 'htaccess') {
		fs_write_redirects_htaccess($out);
	}
	return $out;
}

/**
 * Normalize "from" path: no protocol, leading slash, no query string.
 */
function fs_normalize_redirect_from_path(string $path): string
{
	$path = preg_replace('#^https?://[^/]*#i', '', $path);
	$path = trim($path, '/');
	return $path === '' ? '' : '/' . $path;
}

/**
 * Normalize "to" path or URL: allow full URL or path starting with /.
 */
function fs_normalize_redirect_to_path(string $path): string
{
	$path = trim($path);
	if ($path === '') {
		return '';
	}
	if (preg_match('#^https?://#i', $path)) {
		return esc_url_raw($path, ['http', 'https']);
	}
	$path = trim($path, '/');
	return $path === '' ? '/' : '/' . $path;
}

const FS_HTACCESS_REDIRECTS_MARKER_START = '# BEGIN FROMSCRATCH REDIRECTS';
const FS_HTACCESS_REDIRECTS_MARKER_END = '# END FROMSCRATCH REDIRECTS';

/**
 * Remove the FromScratch redirects block from .htaccess.
 */
function fs_remove_redirects_htaccess_block(): bool
{
	$file = ABSPATH . '.htaccess';
	if (!file_exists($file) || !is_writable($file)) {
		return false;
	}
	$content = (string) file_get_contents($file);
	$pattern = '/\s*' . preg_quote(FS_HTACCESS_REDIRECTS_MARKER_START, '/') . '.*?' . preg_quote(FS_HTACCESS_REDIRECTS_MARKER_END, '/') . '\s*/s';
	if (!preg_match($pattern, $content)) {
		return true;
	}
	$content = preg_replace($pattern, "\n", $content);
	return (bool) file_put_contents($file, trim($content) . "\n", LOCK_EX);
}

/**
 * Write redirect rules to .htaccess between markers.
 */
function fs_write_redirects_htaccess(array $redirects): bool
{
	$file = ABSPATH . '.htaccess';
	if (file_exists($file) && !is_writable($file)) {
		return false;
	}
	if (!file_exists($file) && !is_writable(ABSPATH)) {
		return false;
	}
	$content = file_exists($file) ? (string) file_get_contents($file) : '';
	$pattern = '/\s*' . preg_quote(FS_HTACCESS_REDIRECTS_MARKER_START, '/') . '.*?' . preg_quote(FS_HTACCESS_REDIRECTS_MARKER_END, '/') . '\s*/s';
	if (preg_match($pattern, $content)) {
		$content = preg_replace($pattern, "\n", $content);
	}
	if ($redirects !== []) {
		$block = FS_HTACCESS_REDIRECTS_MARKER_START . "\n";
		foreach ($redirects as $from => $item) {
			$to = $item['to'];
			$code = (int) ($item['code'] ?? 301);
			$block .= 'Redirect ' . $code . ' ' . $from . ' ' . $to . "\n";
		}
		$block .= FS_HTACCESS_REDIRECTS_MARKER_END . "\n";
		$content .= "\n" . $block;
	}
	$content = trim($content) . "\n";
	return (bool) file_put_contents($file, $content, LOCK_EX);
}

function fs_sanitize_features($value): array
{
	if (!is_array($value)) {
		return [];
	}
	$defaults = function_exists('fs_theme_feature_defaults') ? fs_theme_feature_defaults() : [];
	$out = [];
	foreach (array_keys($defaults) as $key) {
		$out[$key] = (!empty($value[$key])) ? 1 : 0;
	}
	if (empty($out['enable_blogs'])) {
		$out['enable_remove_post_tags'] = 0;
	}
	if (empty($out['enable_post_expirator'])) {
		wp_clear_scheduled_hook('fs_expire_post');
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

function fs_sanitize_maintenance_mode($value): string
{
	return !empty($value) ? '1' : '';
}

function fs_sanitize_maintenance_title($value): string
{
	$value = is_string($value) ? trim($value) : '';
	return sanitize_text_field($value);
}

function fs_sanitize_maintenance_description($value): string
{
	$value = is_string($value) ? trim($value) : '';
	return sanitize_textarea_field($value);
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
				<a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>" class="nav-tab <?= $tab === $slug ? 'nav-tab-active' : '' ?>"><?= esc_html(__($label, 'fromscratch')) ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>

		<?php if ($tab === 'general') : ?>
		<?php
			$og_fallback_id = (int) get_option('fromscratch_og_image_fallback', 0);
			$og_fallback_url = $og_fallback_id > 0 ? wp_get_attachment_image_url($og_fallback_id, 'medium') : '';
		?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Fallback OG image', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Used as the social preview image (og:image) when a page or post has no SEO image and no featured image. Best size: 1200 × 630 px.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Image', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_og_image_fallback" id="fromscratch_og_image_fallback" value="<?= esc_attr($og_fallback_id) ?>">
						<div id="fs_og_fallback_preview" class="fs-og-fallback-preview" style="margin-bottom: 8px;">
							<?php if ($og_fallback_url) : ?>
								<img src="<?= esc_url($og_fallback_url) ?>" alt="" style="max-width: 300px; height: auto; display: block;">
							<?php endif; ?>
						</div>
						<p>
							<button type="button" class="button" id="fs_og_fallback_select"><?= esc_html__('Select image', 'fromscratch') ?></button>
							<button type="button" class="button" id="fs_og_fallback_remove" <?= $og_fallback_id <= 0 ? ' style="display:none;"' : '' ?>><?= esc_html__('Remove', 'fromscratch') ?></button>
						</p>
					</td>
				</tr>
			</table>
			<h2 class="title" style="margin-top: 28px;"><?= esc_html__('Excerpt', 'fromscratch') ?></h2>
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
		<?php elseif ($tab === 'redirects') : ?>
		<?php
			$redirect_method = get_option('fs_redirect_method', 'wordpress');
			$redirects_raw = get_option('fs_redirects', []);
			$redirects_list = [];
			foreach ($redirects_raw as $from => $item) {
				$redirects_list[] = [
					'from' => $from,
					'to' => is_array($item) ? ($item['to'] ?? '') : (string) $item,
					'code' => is_array($item) ? (int) ($item['code'] ?? 301) : 301,
				];
			}
		?>
		<form method="post" action="options.php" class="page-settings-form" id="fs-redirects-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_REDIRECTS); ?>
			<h2 class="title"><?= esc_html__('Method', 'fromscratch') ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Method', 'fromscratch') ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="fs_redirect_method" value="wordpress" <?= checked($redirect_method, 'wordpress', false) ?>> <?= esc_html__('WordPress (template_redirect)', 'fromscratch') ?></label><br>
							<label><input type="radio" name="fs_redirect_method" value="htaccess" <?= checked($redirect_method, 'htaccess', false) ?>> <?= esc_html__('.htaccess', 'fromscratch') ?></label>
						</fieldset>
						<p class="description"><?= esc_html__('WordPress runs redirects in PHP. .htaccess writes rules to your server config (Apache); the file must be writable.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<h2 class="title" style="margin-top: 24px;"><?= esc_html__('Redirects', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Enter paths without the domain (e.g. /old-page). From URL is the requested path; To URL can be a path or full URL.', 'fromscratch') ?></p>
			<table class="wp-list-table widefat fixed striped" id="fs-redirects-table">
				<thead>
					<tr>
						<th scope="col" class="column-from"><?= esc_html__('From URL', 'fromscratch') ?></th>
						<th scope="col" class="column-to"><?= esc_html__('To URL', 'fromscratch') ?></th>
						<th scope="col" class="column-code"><?= esc_html__('Code', 'fromscratch') ?></th>
						<th scope="col" class="column-delete"><?= esc_html__('Delete', 'fromscratch') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($redirects_list as $i => $r) : ?>
					<tr class="fs-redirect-row">
						<td><input type="text" name="fs_redirects[<?= (int) $i ?>][from]" value="<?= esc_attr($r['from']) ?>" class="regular-text" placeholder="<?= esc_attr__('/old-path', 'fromscratch') ?>"></td>
						<td><input type="text" name="fs_redirects[<?= (int) $i ?>][to]" value="<?= esc_attr($r['to']) ?>" class="regular-text" placeholder="<?= esc_attr__('/new-path', 'fromscratch') ?>"></td>
						<td>
							<select name="fs_redirects[<?= (int) $i ?>][code]">
								<option value="301" <?= selected($r['code'], 301, false) ?>><?= esc_html__('301 (Permanent)', 'fromscratch') ?></option>
								<option value="302" <?= selected($r['code'], 302, false) ?>><?= esc_html__('302 (Temporary)', 'fromscratch') ?></option>
							</select>
						</td>
						<td><button type="button" class="button fs-redirect-remove"><?= esc_html__('Delete', 'fromscratch') ?></button></td>
					</tr>
					<?php endforeach; ?>
					<tr class="fs-redirect-row fs-redirect-template" style="display: none;">
						<td><input type="text" name="fs_redirects[__i__][from]" value="" class="regular-text" placeholder="<?= esc_attr__('/old-path', 'fromscratch') ?>" disabled></td>
						<td><input type="text" name="fs_redirects[__i__][to]" value="" class="regular-text" placeholder="<?= esc_attr__('/new-path', 'fromscratch') ?>" disabled></td>
						<td><select name="fs_redirects[__i__][code]" disabled><option value="301"><?= esc_html__('301 (Permanent)', 'fromscratch') ?></option><option value="302"><?= esc_html__('302 (Temporary)', 'fromscratch') ?></option></select></td>
						<td><button type="button" class="button fs-redirect-remove"><?= esc_html__('Delete', 'fromscratch') ?></button></td>
					</tr>
				</tbody>
			</table>
			<p style="margin-top: 12px;">
				<button type="button" class="button" id="fs-redirect-add"><?= esc_html__('Add redirect', 'fromscratch') ?></button>
			</p>
			<p class="submit"><?php submit_button(); ?></p>
		</form>
		<script>
		(function() {
			var form = document.getElementById('fs-redirects-form');
			if (!form) return;
			var tbody = form.querySelector('#fs-redirects-table tbody');
			var template = form.querySelector('.fs-redirect-template');
			var index = tbody.querySelectorAll('.fs-redirect-row:not(.fs-redirect-template)').length;
			form.querySelector('#fs-redirect-add').addEventListener('click', function() {
				var tr = template.cloneNode(true);
				tr.classList.remove('fs-redirect-template');
				tr.style.display = '';
				tr.querySelectorAll('[name]').forEach(function(inp) {
					inp.name = inp.name.replace(/__i__/g, index);
					inp.removeAttribute('disabled');
				});
				tbody.insertBefore(tr, template);
				index++;
			});
			tbody.addEventListener('click', function(e) {
				if (e.target.classList.contains('fs-redirect-remove')) {
					e.target.closest('tr').remove();
				}
			});
		})();
		</script>
		<?php elseif ($tab === 'css') : ?>
		<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Custom CSS is output after the design variables (:root). You can use design variables, e.g. var(--primary).', 'fromscratch') ?></p>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_CSS); ?>
			<table class="form-table" role="presentation">
				<tr>
					<td colspan="2" style="padding: 0;">
						<div class="fs-custom-css-editor-wrap">
							<label for="fromscratch_custom_css" class="screen-reader-text"><?= esc_html__('Custom CSS', 'fromscratch') ?></label>
							<textarea name="fromscratch_custom_css" id="fromscratch_custom_css" rows="16" class="large-text code" style="width: 100%; font-family: Consolas, Monaco, monospace;"><?= esc_textarea(get_option('fromscratch_custom_css', '')) ?></textarea>
						</div>
					</td>
				</tr>
			</table>
			<p class="submit"><?php submit_button(); ?></p>
		</form>
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
	$keys = ['theme_settings_general', 'theme_settings_texts', 'theme_settings_design', 'theme_settings_css', 'theme_settings_redirects'];
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
