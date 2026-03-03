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
	'texts'     => ['label' => 'Content', 'developer_only' => false],
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

// Redirects form (self-POST to avoid options.php redirect flicker). Use admin_init so we run regardless of load-hook.
add_action('admin_init', function () {
	global $pagenow;
	if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if ($pagenow !== 'options-general.php' || (isset($_GET['page']) ? $_GET['page'] : '') !== 'fs-theme-settings') {
		return;
	}
	if (empty($_POST['fromscratch_save_redirects']) || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_save_redirects')) {
		return;
	}
	$value = isset($_POST['fs_redirects']) && is_array($_POST['fs_redirects']) ? $_POST['fs_redirects'] : [];
	$sanitized = fs_sanitize_redirects($value);
	update_option('fs_redirects', $sanitized);
	set_transient('fromscratch_redirects_saved', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=redirects'));
	exit;
}, 1);

// CSS form (self-POST to avoid options.php redirect flicker)
add_action('admin_init', function () {
	global $pagenow;
	if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if ($pagenow !== 'options-general.php' || (isset($_GET['page']) ? $_GET['page'] : '') !== 'fs-theme-settings') {
		return;
	}
	if (empty($_POST['fromscratch_save_css']) || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_save_css')) {
		return;
	}
	$value = isset($_POST['fromscratch_custom_css']) ? $_POST['fromscratch_custom_css'] : '';
	$sanitized = fs_sanitize_custom_css($value);
	update_option('fromscratch_custom_css', $sanitized);
	set_transient('fromscratch_css_saved', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-theme-settings&tab=css'));
	exit;
}, 1);

// Ensure media modal is available on General tab (client logo, OG image) and Content tab (image fields).
add_action('admin_enqueue_scripts', function ($hook_suffix) {
	if ($hook_suffix !== 'settings_page_fs-theme-settings') {
		return;
	}
	$can_general = current_user_can('manage_options') && (!function_exists('fs_admin_can_access') || fs_admin_can_access('theme_settings_general'));
	$can_content = current_user_can('manage_options') && (!function_exists('fs_admin_can_access') || fs_admin_can_access('theme_settings_texts'));
	if (!$can_general && !$can_content) {
		return;
	}
	wp_enqueue_media();
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
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_client_logo', [
		'type' => 'integer',
		'sanitize_callback' => 'fs_sanitize_client_logo',
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

function fs_sanitize_client_logo($value): int
{
	$id = absint($value);
	if ($id <= 0) {
		return 0;
	}
	return wp_attachment_is_image($id) ? $id : 0;
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
	$method = function_exists('fs_config_redirects') ? fs_config_redirects('method') : 'wordpress';
	if (!in_array($method, ['wordpress', 'htaccess'], true)) {
		$method = 'wordpress';
	}
	if ($method === 'htaccess') {
		fs_write_redirects_htaccess($out);
	} else {
		fs_remove_redirects_htaccess_block();
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

const FS_HTACCESS_REDIRECTS_MARKER_START = '# BEGIN FromScratch redirects';
const FS_HTACCESS_REDIRECTS_MARKER_END = '# END FromScratch redirects';

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
 * Write redirect rules to .htaccess. Block is always placed at the start of the file (before WordPress) so rules run first.
 */
function fs_write_redirects_htaccess(array $redirects): bool
{
	$file = rtrim(ABSPATH, '/\\') . '/.htaccess';
	if (file_exists($file) && !is_writable($file)) {
		return false;
	}
	$dir = dirname($file);
	if (!file_exists($file) && (!is_writable($dir) || !is_dir($dir))) {
		return false;
	}
	$content = file_exists($file) ? (string) file_get_contents($file) : '';
	$block_pattern = '/\s*' . preg_quote(FS_HTACCESS_REDIRECTS_MARKER_START, '/') . '.*?' . preg_quote(FS_HTACCESS_REDIRECTS_MARKER_END, '/') . '\s*/s';
	$content = preg_replace($block_pattern, "\n", $content);
	$content = trim($content);

	if ($redirects !== []) {
		$block = FS_HTACCESS_REDIRECTS_MARKER_START . "\n";
		$block .= "<IfModule mod_rewrite.c>\nRewriteEngine On\n";
		foreach ($redirects as $from => $item) {
			$to = $item['to'];
			$code = (int) ($item['code'] ?? 301);
			$path_for_regex = ltrim($from, '/');
			$rewrite_pattern = $path_for_regex === '' ? '^/?$' : '^' . preg_quote($path_for_regex, '#') . '/?$';
			$target = strpos($to, ' ') !== false ? '"' . $to . '"' : $to;
			$block .= 'RewriteRule ' . $rewrite_pattern . ' ' . $target . ' [R=' . $code . ',L,NC]' . "\n";
		}
		$block .= "</IfModule>\n";
		$block .= FS_HTACCESS_REDIRECTS_MARKER_END . "\n";
		$content = $block . ($content !== '' ? "\n\n" . $content : '');
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
	$redirects_saved = get_transient('fromscratch_redirects_saved');
	if ($redirects_saved !== false) {
		delete_transient('fromscratch_redirects_saved');
	}
	$css_saved = get_transient('fromscratch_css_saved');
	if ($css_saved !== false) {
		delete_transient('fromscratch_css_saved');
	}
?>
	<div class="wrap">
		<h1><?= esc_html__('Theme settings', 'fromscratch') ?></h1>
		<?php
		$notices = [];
		if ($redirects_saved !== false || $css_saved !== false) {
			$notices[] = __('Settings saved.', 'fromscratch');
		}
		foreach ($notices as $msg) : ?>
			<div class="notice notice-success is-dismissible"><p><strong><?= esc_html($msg) ?></strong></p></div>
		<?php endforeach; ?>

		<?php if (count($available_tabs) > 1) : ?>
		<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
			<?php foreach ($available_tabs as $slug => $label) : ?>
				<a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>" class="nav-tab <?= $tab === $slug ? 'nav-tab-active' : '' ?>"><?= esc_html(__($label, 'fromscratch')) ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>

		<?php if ($tab === 'general') : ?>
		<?php
			$client_logo_id = (int) get_option('fromscratch_client_logo', 0);
			$client_logo_url = $client_logo_id > 0 ? wp_get_attachment_image_url($client_logo_id, 'medium') : '';
			$og_fallback_id = (int) get_option('fromscratch_og_image_fallback', 0);
			$og_fallback_url = $og_fallback_id > 0 ? wp_get_attachment_image_url($og_fallback_id, 'medium') : '';
		?>
		<form method="post" action="options.php" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_GENERAL); ?>
			<h2 class="title"><?= esc_html__('Client logo', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Shown on the login page instead of the WordPress logo.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Image', 'fromscratch') ?></th>
					<td>
						<div data-fs-image-picker>
							<input type="hidden" name="fromscratch_client_logo" id="fromscratch_client_logo" value="<?= esc_attr($client_logo_id) ?>" data-fs-image-picker-input>
							<div class="fs-client-logo-preview" style="margin-bottom: 8px;" data-fs-image-picker-preview>
								<?php if ($client_logo_url) : ?>
									<img src="<?= esc_url($client_logo_url) ?>" alt="" style="max-width: 240px; height: auto; display: block;">
								<?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" data-fs-image-picker-select><?= esc_html__('Select image', 'fromscratch') ?></button>
								<button type="button" class="button" data-fs-image-picker-remove<?= $client_logo_id <= 0 ? ' style="display:none;"' : '' ?>><?= esc_html__('Remove', 'fromscratch') ?></button>
							</p>
						</div>
					</td>
				</tr>
			</table>

			<hr>

			<h2 class="title"><?= esc_html__('Fallback OG image', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Used as the social preview image (og:image) when a page or post has no SEO image and no featured image.', 'fromscratch') ?></p>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Best size: 1200 × 630 px.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Image', 'fromscratch') ?></th>
					<td>
						<div data-fs-image-picker>
							<input type="hidden" name="fromscratch_og_image_fallback" id="fromscratch_og_image_fallback" value="<?= esc_attr($og_fallback_id) ?>" data-fs-image-picker-input>
							<div class="fs-og-fallback-preview" style="margin-bottom: 8px;" data-fs-image-picker-preview>
								<?php if ($og_fallback_url) : ?>
									<img src="<?= esc_url($og_fallback_url) ?>" alt="" style="max-width: 240px; height: auto; display: block; border-radius: 3px;">
								<?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" data-fs-image-picker-select><?= esc_html__('Select image', 'fromscratch') ?></button>
								<button type="button" class="button" data-fs-image-picker-remove<?= $og_fallback_id <= 0 ? ' style="display:none;"' : '' ?>><?= esc_html__('Remove', 'fromscratch') ?></button>
							</p>
						</div>
					</td>
				</tr>
			</table>

			<hr>

			<h2 class="title"><?= esc_html__('Excerpt', 'fromscratch') ?></h2>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Defines how automatically generated excerpts are shortened.', 'fromscratch') ?></p>
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
			<?php submit_button(); ?>
		</form>
		<?php elseif ($tab === 'texts') : ?>
		<?php
			$content_tabs = fs_config_settings('content.tabs');
			$content_tabs = is_array($content_tabs) ? $content_tabs : [];
		?>
		<form method="post" action="options.php" class="page-settings-form" id="fs-content-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_TEXTE); ?>
			<div class="fs-content-tabs-wrap" style="display: flex; gap: 24px; margin-bottom: 24px;">
				<nav class="fs-content-tabs-nav" role="tablist" style="flex-shrink: 0; width: 180px;">
					<?php foreach ($content_tabs as $i => $ct) : ?>
						<button type="button" class="button fs-content-tab-btn <?= ($i === 0) ? 'active' : '' ?>" role="tab" aria-selected="<?= ($i === 0) ? 'true' : 'false' ?>" aria-controls="fs-content-panel-<?= esc_attr($ct['id']) ?>" data-tab="<?= esc_attr($ct['id']) ?>" style="display: block; width: 100%; margin-bottom: 4px; text-align: left;"><?= esc_html($ct['title'] ?? $ct['id']) ?></button>
					<?php endforeach; ?>
				</nav>
				<div class="fs-content-tabs-panels" style="flex: 1;">
					<?php foreach ($content_tabs as $i => $ct) : ?>
						<div id="fs-content-panel-<?= esc_attr($ct['id']) ?>" class="fs-content-tab-panel" role="tabpanel" data-tab="<?= esc_attr($ct['id']) ?>" style="display: <?= $i === 0 ? 'block' : 'none' ?>;">
							<?php
							if (!empty($ct['sections']) && is_array($ct['sections'])) {
								foreach ($ct['sections'] as $section) {
									do_settings_sections(FS_THEME_CONTENT_OPTION_PREFIX . $section['id']);
								}
							}
							?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php submit_button(); ?>
		</form>
		<style>.fs-content-tabs-wrap .fs-content-tab-btn.active { background: #2271b1; color: #fff; border-color: #2271b1; }</style>
		<script>
		(function() {
			var form = document.getElementById('fs-content-form');
			if (!form) return;
			var nav = form.querySelector('.fs-content-tabs-nav');
			var panels = form.querySelectorAll('.fs-content-tab-panel');
			nav.addEventListener('click', function(e) {
				var btn = e.target.closest('.fs-content-tab-btn');
				if (!btn) return;
				var tabId = btn.getAttribute('data-tab');
				nav.querySelectorAll('.fs-content-tab-btn').forEach(function(b) { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
				btn.classList.add('active');
				btn.setAttribute('aria-selected', 'true');
				panels.forEach(function(p) {
					var show = p.getAttribute('data-tab') === tabId;
					p.style.display = show ? 'block' : 'none';
				});
			});
		})();
		</script>
		<?php elseif ($tab === 'redirects') : ?>
		<?php
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
		<form method="post" action="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings&tab=redirects')) ?>" class="page-settings-form" id="fs-redirects-form">
			<?php wp_nonce_field('fromscratch_save_redirects'); ?>
			<input type="hidden" name="fromscratch_save_redirects" value="1">
			<?php
			$redirect_method = function_exists('fs_config_redirects') ? fs_config_redirects('method') : 'wordpress';
			if (
				function_exists('fs_can_use_htaccess_redirects') && fs_can_use_htaccess_redirects() &&
				$redirect_method === 'wordpress'
			) : ?>
				<div class="notice notice-info inline">
					<p><?= esc_html__('You are running Apache. For better performance, you can set the redirect method to .htaccess in config/theme.php under redirects.', 'fromscratch') ?></p>
				</div>
			<?php endif; ?>
			<h2 class="title"><?= esc_html__('Redirects', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Enter paths without the domain (e.g. /old-path).', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('The source URL represents the requested path.', 'fromscratch') ?></p>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('The target URL can be an internal path or a full URL.', 'fromscratch') ?></p>
			<table class="wp-list-table widefat fixed striped" id="fs-redirects-table" style="width: auto;">
				<thead>
					<tr>
						<th scope="col" class="column-from" style="width: 50%;"><?= esc_html__('From URL', 'fromscratch') ?></th>
						<th scope="col" class="column-to" style="width: 50%;"><?= esc_html__('To URL', 'fromscratch') ?></th>
						<th scope="col" class="column-code"><?= esc_html__('Code', 'fromscratch') ?></th>
						<th scope="col" class="column-delete"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($redirects_list as $i => $r) : ?>
					<tr class="fs-redirect-row">
						<td><input type="text" name="fs_redirects[<?= (int) $i ?>][from]" value="<?= esc_attr($r['from']) ?>" class="regular-text" style="width: 100%;" placeholder="<?= esc_attr__('/old-path', 'fromscratch') ?>"></td>
						<td><input type="text" name="fs_redirects[<?= (int) $i ?>][to]" value="<?= esc_attr($r['to']) ?>" class="regular-text" style="width: 100%;" placeholder="<?= esc_attr__('/new-path', 'fromscratch') ?>"></td>
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
			<?php submit_button(); ?>
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
		<form method="post" action="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings&tab=css')) ?>" class="page-settings-form">
			<?php wp_nonce_field('fromscratch_save_css'); ?>
			<input type="hidden" name="fromscratch_save_css" value="1">
			<h2 class="title"><?= esc_html__('Custom CSS', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('The CSS is output after the design variables (:root).', 'fromscratch') ?></p>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('You can use design variables, e.g. var(--color-primary).', 'fromscratch') ?></p>
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
			<?php submit_button(); ?>
		</form>
		<?php else : ?>
		<p class="description" style="margin-bottom: 8px;"><?= esc_html__('Override SCSS design variables. Values are output as CSS custom properties (:root). Add new variables in config/theme-design.php under design.sections.', 'fromscratch') ?></p>
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
			<?php submit_button(); ?>
		</form>
		<?php endif; ?>
	</div>
<?php
}

/**
 * Output option name row (for developers only): copy button + monospace option id. Uses copy-from-source; no label on button.
 */
function fs_content_field_option_name_row(string $variableId): void
{
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$id_attr = 'fs-opt-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $variableId);
	?>
	<div class="fs-content-option-name-row" style="display: flex; align-items: center; gap: 6px; margin: 4px 0 0 4px;">
		<button type="button" class="button button-small fs-copy-option-name" style="padding: 0 6px; min-height: 28px;" data-fs-copy-from-source="<?= esc_attr($id_attr) ?>" aria-label="<?= esc_attr__('Copy option name', 'fromscratch') ?>"><span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px;"></span></button>
		<span id="<?= esc_attr($id_attr) ?>" style="color: #999; font-size: 12px; font-family: monospace;"><?= esc_html($variableId) ?></span>
	</div>
	<?php
}

function display_custom_info_field($variable, $variableId, $languageId = null): void
{
	if ($languageId) {
		echo '<div class="page-settings-language-container page-settings-language-container-' . $languageId . '">';
	}
	$value = get_option($variableId, '');
	$type = $variable['type'] ?? 'textfield';
	switch ($type) {
		case 'textfield':
			$tw = isset($variable['width']) ? (int) $variable['width'] : 0;
			$tw_style = $tw > 0 ? 'width: ' . $tw . 'px' : 'width: 100%';
			echo '<input class="settings-page-textfield" type="text" name="' . esc_attr($variableId) . '" value="' . esc_attr($value) . '" style="' . $tw_style . '"' . (isset($variable['placeholder']) ? ' placeholder="' . esc_attr($variable['placeholder']) . '"' : '') . '>';
			break;
		case 'textarea':
			$taw = isset($variable['width']) ? (int) $variable['width'] : 0;
			$taw_style = $taw > 0 ? 'width: ' . $taw . 'px' : 'width: 100%';
			$textarea_placeholder = isset($variable['placeholder']) ? ' placeholder="' . esc_attr($variable['placeholder']) . '"' : '';
			echo '<textarea class="settings-page-textfield" name="' . esc_attr($variableId) . '" rows="' . (int) ($variable['rows'] ?? 4) . '" style="' . $taw_style . '"' . $textarea_placeholder . '>' . esc_textarea($value) . '</textarea>';
			break;
		case 'select':
			$options = $variable['options'] ?? [];
			echo '<select name="' . esc_attr($variableId) . '" class="settings-page-select" style="min-width: ' . (int) ($variable['width'] ?? 200) . 'px">';
			if (!empty($variable['placeholder'])) {
				echo '<option value="">' . esc_html($variable['placeholder']) . '</option>';
			}
			foreach ($options as $opt_value => $opt_label) {
				if (is_int($opt_value) && is_array($opt_label)) {
					$opt_value = $opt_label['value'] ?? '';
					$opt_label = $opt_label['label'] ?? $opt_value;
				}
				echo '<option value="' . esc_attr($opt_value) . '"' . selected($value, (string) $opt_value, false) . '>' . esc_html($opt_label) . '</option>';
			}
			echo '</select>';
			break;
		case 'toggle':
			$checked = ($value === '1' || $value === 'on' || $value === true);
			echo '<label class="fs-content-toggle-wrap">';
			echo '<input type="hidden" name="' . esc_attr($variableId) . '" value="0">';
			echo '<input type="checkbox" name="' . esc_attr($variableId) . '" value="1" class="settings-page-toggle"' . ($checked ? ' checked' : '') . '>';
			echo '<span class="fs-content-toggle-label">' . esc_html(!empty($variable['toggle_label']) ? $variable['toggle_label'] : __('On', 'fromscratch')) . '</span>';
			echo '</label>';
			break;
		case 'multiselect':
			$options = $variable['options'] ?? [];
			$selected = is_array($value) ? $value : (array) json_decode((string) $value, true);
			$selected = array_map('strval', $selected);
			echo '<div class="fs-content-multiselect" style="display: flex; flex-direction: column; gap: 6px;">';
			echo '<input type="hidden" name="' . esc_attr($variableId) . '[]" value="">';
			foreach ($options as $opt_value => $opt_label) {
				if (is_int($opt_value) && is_array($opt_label)) {
					$opt_value = $opt_label['value'] ?? '';
					$opt_label = $opt_label['label'] ?? $opt_value;
				}
				$opt_value = (string) $opt_value;
				$checked = in_array($opt_value, $selected, true);
				$cb_id = esc_attr($variableId . '_' . preg_replace('/[^a-z0-9_-]/i', '_', $opt_value));
				echo '<label style="display: flex; align-items: center; gap: 8px;">';
				echo '<input type="checkbox" name="' . esc_attr($variableId) . '[]" id="' . $cb_id . '" value="' . esc_attr($opt_value) . '"' . ($checked ? ' checked' : '') . '>';
				echo '<span>' . esc_html($opt_label) . '</span>';
				echo '</label>';
			}
			echo '</div>';
			break;
		case 'image':
			$img_id = (int) $value;
			$img_url = $img_id > 0 ? wp_get_attachment_image_url($img_id, 'medium') : '';
			echo '<div data-fs-image-picker>';
			echo '<input type="hidden" name="' . esc_attr($variableId) . '" id="' . esc_attr($variableId) . '" value="' . esc_attr($img_id > 0 ? $img_id : '0') . '" data-fs-image-picker-input>';
			echo '<div class="fs-image-picker-preview" style="margin-bottom: 8px;" data-fs-image-picker-preview>';
			if ($img_url) {
				echo '<img src="' . esc_url($img_url) . '" alt="" style="max-width: 240px; height: auto; display: block;">';
			}
			echo '</div>';
			echo '<p><button type="button" class="button" data-fs-image-picker-select>' . esc_html__('Select image', 'fromscratch') . '</button> ';
			echo '<button type="button" class="button" data-fs-image-picker-remove' . ($img_id <= 0 ? ' style="display:none;"' : '') . '>' . esc_html__('Remove', 'fromscratch') . '</button></p>';
			echo '</div>';
			break;
		default:
			$defw = isset($variable['width']) ? (int) $variable['width'] : 0;
			$defw_style = $defw > 0 ? 'width: ' . $defw . 'px' : 'width: 100%';
			$def_placeholder = isset($variable['placeholder']) ? ' placeholder="' . esc_attr($variable['placeholder']) . '"' : '';
			echo '<input class="settings-page-textfield" type="text" name="' . esc_attr($variableId) . '" value="' . esc_attr($value) . '" style="' . $defw_style . '"' . $def_placeholder . '>';
			break;
	}
	if (!empty($variable['description'])) {
		echo '<p class="description" style="margin-top: 6px; margin-bottom: 0;">' . esc_html($variable['description']) . '</p>';
	}
	fs_content_field_option_name_row($variableId);
	if ($languageId) {
		echo '</div>';
	}
}

function display_custom_info_fields(): void
{
	$tabs = fs_config_settings('content.tabs');
	if (!is_array($tabs)) {
		return;
	}
	foreach ($tabs as $tab) {
		if (empty($tab['sections']) || !is_array($tab['sections'])) {
			continue;
		}
		foreach ($tab['sections'] as $section) {
			$section_title = $section['title'] ?? $section['id'] ?? '';
			add_settings_section('section', $section_title, null, FS_THEME_CONTENT_OPTION_PREFIX . $section['id']);
			foreach ($section['variables'] as $variable) {
				$variableId = FS_THEME_CONTENT_OPTION_PREFIX . $section['id'] . '_' . $variable['id'];
				$variable_title = $variable['title'] ?? $variable['id'] ?? '';
				$is_multiselect = isset($variable['type']) && $variable['type'] === 'multiselect';
				$register_args = [];
				if ($is_multiselect && !empty($variable['options'])) {
					$allowed = [];
					foreach ($variable['options'] as $k => $v) {
						$allowed[] = is_int($k) && is_array($v) ? (string) ($v['value'] ?? '') : (string) $k;
					}
					$allowed = array_filter($allowed);
					$register_args['sanitize_callback'] = function ($input) use ($allowed) {
						if (!is_array($input)) {
							return [];
						}
						return array_values(array_intersect(array_map('strval', $input), $allowed));
					};
				}
				if (!empty($variable['translate'])) {
					foreach (fs_config_settings('languages') as $language) {
						$variableIdLang = $variableId . '_' . $language['id'];
						add_settings_field($variableIdLang, $variable_title, function () use ($variable, $variableIdLang, $language) {
							display_custom_info_field($variable, $variableIdLang, $language['id']);
						}, FS_THEME_CONTENT_OPTION_PREFIX . $section['id'], 'section');
						register_setting(FS_THEME_OPTION_GROUP_TEXTE, $variableIdLang, $register_args);
					}
				} else {
					add_settings_field($variableId, $variable_title, function () use ($variable, $variableId) {
						display_custom_info_field($variable, $variableId);
					}, FS_THEME_CONTENT_OPTION_PREFIX . $section['id'], 'section');
					register_setting(FS_THEME_OPTION_GROUP_TEXTE, $variableId, $register_args);
				}
			}
		}
	}
}
add_action('admin_init', 'display_custom_info_fields');

/**
 * Whether the current user has access to at least one Theme settings tab (for menu visibility).
 * When all Theme settings tabs are disabled in User rights for that role, the menu is hidden.
 */
function fs_theme_settings_has_any_access(): bool
{
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
		__('Theme settings', 'fromscratch'),
		__('Theme', 'fromscratch'),
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
