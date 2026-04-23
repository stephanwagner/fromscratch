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

// General tab: send weekly report immediately to developer email (manual test/send).
add_action('admin_init', function () {
	global $pagenow;
	if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if ($pagenow !== 'options-general.php' || (isset($_GET['page']) ? $_GET['page'] : '') !== 'fs-theme-settings') {
		return;
	}
	$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'general';
	if ($tab !== 'general') {
		return;
	}
	if (empty($_POST['fromscratch_send_weekly_report_to_developer'])) {
		return;
	}
	if (empty($_POST['_wpnonce']) || !wp_verify_nonce((string) $_POST['_wpnonce'], 'fromscratch_send_weekly_report_to_developer')) {
		return;
	}
	$url = admin_url('options-general.php?page=fs-theme-settings&tab=general');
	$posted = isset($_POST['fromscratch_weekly_report_test_recipient'])
		? sanitize_email(wp_unslash((string) $_POST['fromscratch_weekly_report_test_recipient']))
		: '';
	$developer_email = function_exists('fs_developer_email') ? fs_developer_email() : '';
	$recipient = $posted !== '' ? $posted : $developer_email;
	if ($recipient === '' || !is_email($recipient)) {
		set_transient(
			'fromscratch_weekly_report_send_error',
			__('Please enter a valid email address, or configure the developer email as the default.', 'fromscratch'),
			30
		);
		wp_safe_redirect($url);
		exit;
	}
	if (!function_exists('fs_weekly_report_send')) {
		set_transient('fromscratch_weekly_report_send_error', __('Weekly report sender is not available.', 'fromscratch'), 30);
		wp_safe_redirect($url);
		exit;
	}
	$sent = fs_weekly_report_send([$recipient]);
	if ($sent) {
		set_transient('fromscratch_weekly_report_send_success', $recipient, 30);
	} else {
		set_transient('fromscratch_weekly_report_send_error', __('Weekly website report could not be sent.', 'fromscratch'), 30);
	}
	wp_safe_redirect($url);
	exit;
}, 2);

// AJAX: toggle "Show developer options" on Content tab (saved per user).
add_action('wp_ajax_fromscratch_toggle_content_developer_options', function () {
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fromscratch_toggle_content_developer_options')) {
		wp_send_json_error(['message' => 'Invalid nonce']);
	}
	if (!current_user_can('manage_options') || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		wp_send_json_error(['message' => 'Forbidden']);
	}
	$visible = isset($_POST['visible']) && $_POST['visible'] === '1';
	update_user_meta(get_current_user_id(), 'fromscratch_show_content_developer_options', $visible ? '1' : '0');
	wp_send_json_success(['visible' => $visible]);
});

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
const FS_THEME_OPTION_GROUP_LANGUAGES = 'fs_theme_languages';

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
	register_setting(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL, 'fromscratch_report_email', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_report_email_list',
	]);
	register_setting(FS_THEME_OPTION_GROUP_DEVELOPER_GENERAL, 'fromscratch_developer_email', [
		'type' => 'string',
		'sanitize_callback' => 'sanitize_email',
	]);
}, 5);

/**
 * Report email (Developer › System). Used for automated reports e.g. weekly analytics.
 *
 * @return string Sanitized email or empty string.
 */
function fs_report_email(): string
{
	$list = fs_report_emails();
	return $list[0] ?? '';
}

/**
 * Parse and sanitize report recipient list (one email per line).
 *
 * @param mixed $value Raw option value.
 * @return string
 */
function fs_sanitize_report_email_list($value): string
{
	$text = is_string($value) ? $value : '';
	$lines = preg_split('/\r\n|\r|\n/', $text);
	if (!is_array($lines)) {
		return '';
	}
	$out = [];
	foreach ($lines as $line) {
		$email = sanitize_email(trim((string) $line));
		if ($email === '' || !is_email($email)) {
			continue;
		}
		$out[] = strtolower($email);
	}
	$out = array_values(array_unique($out));
	return implode("\n", $out);
}

/**
 * @return array<int, string> Report recipient emails.
 */
function fs_report_emails(): array
{
	$raw = get_option('fromscratch_report_email', '');
	if (!is_string($raw) || trim($raw) === '') {
		return [];
	}
	$lines = preg_split('/\r\n|\r|\n/', $raw);
	if (!is_array($lines)) {
		return [];
	}
	$out = [];
	foreach ($lines as $line) {
		$email = sanitize_email(trim((string) $line));
		if ($email === '' || !is_email($email)) {
			continue;
		}
		$out[] = strtolower($email);
	}
	return array_values(array_unique($out));
}

/**
 * Developer email (Developer › System). Used for system alerts, errors and security warnings.
 *
 * @return string Sanitized email or empty string.
 */
function fs_developer_email(): string
{
	$email = get_option('fromscratch_developer_email', '');
	return is_string($email) && is_email($email) ? $email : '';
}

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
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'posts_per_page', [
		'type' => 'integer',
		'sanitize_callback' => 'fs_sanitize_posts_per_page',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_client_logo', [
		'type' => 'integer',
		'sanitize_callback' => 'fs_sanitize_client_logo',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_og_image_fallback', [
		'type' => 'integer',
		'sanitize_callback' => 'fs_sanitize_og_image_fallback',
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_weekly_report_enabled', [
		'type' => 'string',
		'sanitize_callback' => static function ($value): string {
			return !empty($value) ? '1' : '0';
		},
	]);
	register_setting(FS_THEME_OPTION_GROUP_GENERAL, 'fromscratch_report_email', [
		'type' => 'string',
		'sanitize_callback' => 'fs_sanitize_report_email_list',
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

add_action('admin_init', function () {
	register_setting(FS_THEME_OPTION_GROUP_LANGUAGES, 'fs_theme_languages', [
		'type' => 'array',
		'sanitize_callback' => 'fs_sanitize_theme_languages',
	]);
}, 5);

/**
 * Sanitize Languages tab data: list of { id, name, nameNative }, plus default id.
 *
 * @param mixed $value Raw POST value.
 * @return array{list: array<int, array{id: string, name: string, nameNative: string}>, default: string}
 */
function fs_sanitize_theme_languages($value): array
{
	$list = [];
	$seen_ids = [];
	if (isset($value['list']) && is_array($value['list'])) {
		foreach ($value['list'] as $row) {
			if (!is_array($row)) {
				continue;
			}
			$id = isset($row['id']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $row['id']) : '';
			if ($id === '') {
				continue;
			}
			$id_lower = strtolower($id);
			if (isset($seen_ids[$id_lower])) {
				continue;
			}
			$seen_ids[$id_lower] = true;
			$list[] = [
				'id' => $id,
				'name' => isset($row['name']) ? sanitize_text_field((string) $row['name']) : '',
				'nameNative' => isset($row['nameNative']) ? sanitize_text_field((string) $row['nameNative']) : '',
			];
		}
	}
	$ids = array_column($list, 'id');
	$default = isset($value['default']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $value['default']) : '';
	if ($default === '' || !in_array($default, $ids, true)) {
		$default = $ids[0] ?? '';
	}
	// Ensure default language is first in the list.
	if ($default !== '' && count($list) > 1 && strtolower((string) ($list[0]['id'] ?? '')) !== strtolower($default)) {
		$default_index = null;
		foreach ($list as $i => $row) {
			if (strtolower((string) $row['id']) === strtolower($default)) {
				$default_index = $i;
				break;
			}
		}
		if ($default_index !== null) {
			$default_row = $list[$default_index];
			array_splice($list, $default_index, 1);
			array_unshift($list, $default_row);
		}
	}
	$use_url_prefix = isset($value['use_url_prefix']) ? (bool) $value['use_url_prefix'] : true;
	$prefix_default = $use_url_prefix && !empty($value['prefix_default']);
	$no_translation = isset($value['no_translation']) && in_array($value['no_translation'], ['hide', 'disabled', 'home'], true)
		? $value['no_translation'] : 'disabled';
	return ['list' => $list, 'default' => $default, 'use_url_prefix' => $use_url_prefix, 'prefix_default' => $prefix_default, 'no_translation' => $no_translation];
}

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

/**
 * Core option `posts_per_page` (Settings → Reading). Same value saved from Theme → General.
 *
 * @param mixed $value Raw value from form.
 */
function fs_sanitize_posts_per_page($value): int
{
	if ($value === '' || $value === null) {
		return max(1, (int) get_option('posts_per_page', 10));
	}
	$n = absint($value);
	if ($n < 1) {
		$n = 1;
	}
	return min($n, 999);
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
	if (empty($out['enable_webp'])) {
		$out['enable_webp_convert_original'] = 0;
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
	$weekly_send_success = get_transient('fromscratch_weekly_report_send_success');
	if ($weekly_send_success !== false) {
		delete_transient('fromscratch_weekly_report_send_success');
	}
	$weekly_send_error = get_transient('fromscratch_weekly_report_send_error');
	if ($weekly_send_error !== false) {
		delete_transient('fromscratch_weekly_report_send_error');
	}
?>
	<div class="wrap">
		<h1><?= esc_html__('Theme settings', 'fromscratch') ?></h1>
		<?php
		$notices = [];
		if ($redirects_saved !== false || $css_saved !== false) {
			$notices[] = __('Settings saved.', 'fromscratch');
		}
		if ($weekly_send_success !== false) {
			$notices[] = sprintf(__('Weekly website report sent to %s.', 'fromscratch'), (string) $weekly_send_success);
		}
		if ($weekly_send_error !== false) {
			$notices[] = (string) $weekly_send_error;
		}
		foreach ($notices as $msg) : ?>
			<div class="notice <?= ($weekly_send_error !== false && $msg === (string) $weekly_send_error) ? 'notice-error' : 'notice-success' ?> is-dismissible">
				<p><strong><?= esc_html($msg) ?></strong></p>
			</div>
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
			$weekly_test_developer_email = function_exists('fs_developer_email') ? fs_developer_email() : '';
			?>
			<form method="post" action="options.php" class="fs-page-settings-form">
				<?php settings_fields(FS_THEME_OPTION_GROUP_GENERAL); ?>
				<h2 class="title"><?= esc_html__('Weekly website report', 'fromscratch') ?></h2>
				<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Sends a weekly summary every Monday with analytics (when Matomo is enabled).', 'fromscratch') ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Weekly reports', 'fromscratch') ?></th>
						<td>
							<label>
								<input type="hidden" name="fromscratch_weekly_report_enabled" value="0">
								<input type="checkbox" name="fromscratch_weekly_report_enabled" value="1" <?= checked(get_option('fromscratch_weekly_report_enabled', '0'), '1', false) ?>>
								<?= esc_html__('Enable reports', 'fromscratch') ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fromscratch_report_email"><?= esc_html__('Report recipents', 'fromscratch') ?></label></th>
						<td>
							<textarea name="fromscratch_report_email" id="fromscratch_report_email" rows="3" class="regular-text" style="width: 100%; max-width: 420px;" placeholder="<?= esc_attr__('stephanwagner.me+bericht@gmail.com', 'fromscratch') ?>"><?= esc_textarea((string) get_option('fromscratch_report_email', '')) ?></textarea>
							<p class="description"><?= esc_html__('One email address per line.', 'fromscratch') ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('Test email', 'fromscratch') ?></th>
						<td>
							<div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; max-width:100%;">
								<label for="fromscratch_weekly_report_test_recipient" class="screen-reader-text"><?= esc_html__('Recipient email', 'fromscratch') ?></label>
								<input
									type="email"
									name="fromscratch_weekly_report_test_recipient"
									id="fromscratch_weekly_report_test_recipient"
									form="fs-send-weekly-report-to-developer"
									value="<?= esc_attr($weekly_test_developer_email) ?>"
									class="regular-text"
									style="flex:1; min-width:200px; max-width:420px;"
									autocomplete="email"
								>
								<button type="submit" form="fs-send-weekly-report-to-developer" class="button"><?= esc_html__('Send email', 'fromscratch') ?></button>
							</div>
							<p class="description">
								<?= esc_html__('Sends the current weekly report to provided email address.', 'fromscratch') ?>
							</p>
						</td>
					</tr>
				</table>
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>

				<hr>

				<h2 class="title"><?= esc_html__('Posts per page', 'fromscratch') ?></h2>
				<p class="description" style="margin-bottom: 12px;"><?= esc_html__('How many posts appear per page on the blog, archives, and search results.', 'fromscratch') ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="posts_per_page"><?= esc_html__('Posts per page', 'fromscratch') ?></label>
						</th>
						<td>
							<?php $posts_per_page_val = max(1, (int) get_option('posts_per_page', 10)); ?>
							<input type="number" name="posts_per_page" id="posts_per_page" value="<?= esc_attr((string) $posts_per_page_val) ?>" min="1" max="999" step="1" class="small-text">
							<p class="description"><?= esc_html__('Same as Settings → Reading → “Blog pages show at most”.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>

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
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>

				<hr>

				<h2 class="title"><?= esc_html__('Client logo', 'fromscratch') ?></h2>
				<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Shown on the login page instead of the WordPress logo. Also used for branding in emails.', 'fromscratch') ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?= esc_html__('Image', 'fromscratch') ?></th>
						<td>
							<div class="fs-image-picker" data-fs-image-picker>
								<input type="hidden" name="fromscratch_client_logo" id="fromscratch_client_logo" value="<?= esc_attr($client_logo_id) ?>" data-fs-image-picker-input>
								<div class="fs-image-picker-preview" data-fs-image-picker-preview>
									<?php if ($client_logo_url) : ?>
										<img src="<?= esc_url($client_logo_url) ?>" alt="">
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
							<div class="fs-image-picker" data-fs-image-picker>
								<input type="hidden" name="fromscratch_og_image_fallback" id="fromscratch_og_image_fallback" value="<?= esc_attr($og_fallback_id) ?>" data-fs-image-picker-input>
								<div class="fs-image-picker-preview" data-fs-image-picker-preview>
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
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>
			</form>
			<form method="post" action="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings&tab=general')) ?>" id="fs-send-weekly-report-to-developer" style="display:none;">
				<?php wp_nonce_field('fromscratch_send_weekly_report_to_developer'); ?>
				<input type="hidden" name="fromscratch_send_weekly_report_to_developer" value="1">
			</form>
		<?php elseif ($tab === 'texts') : ?>
			<form method="post" action="options.php" class="fs-page-settings-form" id="fs-content-form">
				<?php
				$content_tabs = fs_config_settings('content.tabs');
				$content_tabs = is_array($content_tabs) ? $content_tabs : [];
				$content_developer_options_visible = function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id()) && (string) get_user_meta(get_current_user_id(), 'fromscratch_show_content_developer_options', true) === '1';
				$content_languages = function_exists('fs_get_content_languages') ? fs_get_content_languages() : [];
				$content_default_lang = function_exists('fs_get_default_language') ? fs_get_default_language() : '';
				if ($content_default_lang === '' && !empty($content_languages)) {
					$content_default_lang = (string) ($content_languages[0]['id'] ?? '');
				}
				$show_lang_switcher = count($content_languages) >= 2;
				?>
				<h2><?= esc_html__('Global Content', 'fromscratch') ?></h2>
				<p class="description">
					<?= esc_html__('Define content that is used across the website.', 'fromscratch') ?>
				</p>
				<p class="description">
					<?= esc_html__('Updating these values will automatically update them wherever they are used.', 'fromscratch') ?>
				</p>

				<hr>

				<?php settings_fields(FS_THEME_OPTION_GROUP_TEXTE); ?>

				<div class="fs-tabs" data-fs-tabs>
					<nav class="fs-tabs-nav" data-fs-tabs-nav role="tablist">
						<?php foreach ($content_tabs as $i => $ct) : ?>
							<button type="button" class="button fs-tabs-btn fs-button-can-toggle <?= ($i === 0) ? 'active' : '' ?>" role="tab" aria-selected="<?= ($i === 0) ? 'true' : 'false' ?>" aria-controls="fs-content-panel-<?= esc_attr($ct['id']) ?>" data-fs-tabs-btn data-tab="<?= esc_attr($ct['id']) ?>"><?= esc_html($ct['title'] ?? $ct['id']) ?></button>
						<?php endforeach; ?>
					</nav>
					<div class="fs-tabs-panels" data-fs-tabs-panels>
						<?php if ($show_lang_switcher) : ?>
							<div class="fs-content-lang-switcher" data-fs-content-lang-switcher data-fs-content-lang-default="<?= esc_attr($content_default_lang) ?>">
								<?php foreach ($content_languages as $lang) :
									$lang_id = isset($lang['id']) ? (string) $lang['id'] : '';
									$lang_label = function_exists('fs_content_language_label') ? fs_content_language_label($lang, 'native') : $lang_id;
									$is_default = $lang_id === $content_default_lang;
								?>
									<button type="button" class="button fs-content-lang-btn fs-button-can-toggle <?= $is_default ? 'active' : '' ?>" data-fs-content-lang-btn data-fs-content-lang="<?= esc_attr($lang_id) ?>"><?= esc_html($lang_label) ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php foreach ($content_tabs as $i => $ct) : ?>
							<div id="fs-content-panel-<?= esc_attr($ct['id']) ?>" class="fs-tabs-panel <?= $i === 0 ? 'fs-tabs-panel--active' : '' ?>" data-fs-tabs-panel role="tabpanel" data-tab="<?= esc_attr($ct['id']) ?>" <?= $i === 0 ? 'data-fs-tabs-panel-active="1"' : '' ?>>
								<?php
								if (!empty($ct['sections']) && is_array($ct['sections'])) {
									foreach ($ct['sections'] as $index => $section) {
										if ($index > 0) {
											echo '<hr>';
										}
										do_settings_sections(FS_THEME_CONTENT_OPTION_PREFIX . $ct['id'] . '_' . $section['id']);
									}
								}
								?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<hr>

				<div class="fs-submit-row" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
					<button type="submit" name="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>

					<?php if (function_exists('fs_is_developer_user') && fs_is_developer_user((int) get_current_user_id())) : ?>
						<button type="button"
							class="button"
							id="fs-content-developer-options-toggle"
							data-fs-content-developer-options-visible="<?= $content_developer_options_visible ? '1' : '0' ?>"
							data-nonce="<?= esc_attr(wp_create_nonce('fromscratch_toggle_content_developer_options')) ?>">
							<?= $content_developer_options_visible
								? esc_html__('Hide developer options', 'fromscratch')
								: esc_html__('Show developer options', 'fromscratch') ?>
						</button>
					<?php endif; ?>
				</div>
			</form>
			<script>
				(function() {
					var btn = document.getElementById('fs-content-developer-options-toggle');
					if (!btn) return;
					btn.addEventListener('click', function() {
						var visible = btn.getAttribute('data-fs-content-developer-options-visible') === '1';
						var newVisible = !visible;
						var formData = new FormData();
						formData.append('action', 'fromscratch_toggle_content_developer_options');
						formData.append('nonce', btn.getAttribute('data-nonce'));
						formData.append('visible', newVisible ? '1' : '0');
						fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '<?= esc_url(admin_url('admin-ajax.php')) ?>', {
								method: 'POST',
								body: formData,
								credentials: 'same-origin'
							})
							.then(function(r) {
								return r.json();
							})
							.then(function(data) {
								if (data.success) {
									btn.setAttribute('data-fs-content-developer-options-visible', newVisible ? '1' : '0');
									btn.textContent = newVisible ? '<?= esc_js(__('Hide developer options', 'fromscratch')) ?>' : '<?= esc_js(__('Show developer options', 'fromscratch')) ?>';
									document.querySelectorAll('[data-fs-content-developer-options-container]').forEach(function(el) {
										el.classList.toggle('fs-content-developer-options-hidden', !newVisible);
									});
								}
							});
					});
				})();
			</script>
			<script>
				(function() {
					function run() {
						var switcher = document.querySelector('[data-fs-content-lang-switcher]');
						if (!switcher) return;
						var form = document.getElementById('fs-content-form');
						if (!form) return;

						function fieldContainers() {
							return form.querySelectorAll('.fs-content-lang-container[data-fs-content-lang]');
						}

						function pickInitialLang(requested) {
							var containers = fieldContainers();
							var ids = {};
							for (var i = 0; i < containers.length; i++) {
								var lid = containers[i].getAttribute('data-fs-content-lang');
								if (lid) ids[lid] = true;
							}
							if (requested && ids[requested]) return requested;
							var btns = switcher.querySelectorAll('[data-fs-content-lang-btn]');
							for (var j = 0; j < btns.length; j++) {
								var bid = btns[j].getAttribute('data-fs-content-lang');
								if (bid && ids[bid]) return bid;
							}
							return containers[0] ? (containers[0].getAttribute('data-fs-content-lang') || '') : '';
						}

						function setContentLang(langId) {
							switcher.querySelectorAll('[data-fs-content-lang-btn]').forEach(function(b) {
								b.classList.toggle('active', b.getAttribute('data-fs-content-lang') === langId);
							});
							fieldContainers().forEach(function(container) {
								var containerLang = container.getAttribute('data-fs-content-lang');
								var show = containerLang === langId;
								var tr = container.closest('tr');
								if (tr) {
									tr.style.display = show ? '' : 'none';
								} else {
									container.style.display = show ? '' : 'none';
								}
							});
						}

						var requestedDefault = switcher.getAttribute('data-fs-content-lang-default') || '';
						// Capture phase so other handlers cannot swallow the click.
						document.addEventListener('click', function onLangBtnClick(e) {
							var btn = e.target.closest('[data-fs-content-lang-btn]');
							if (!btn || !switcher.contains(btn)) return;
							e.preventDefault();
							setContentLang(btn.getAttribute('data-fs-content-lang') || '');
						}, true);

						setContentLang(pickInitialLang(requestedDefault));
					}
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', run);
					} else {
						run();
					}
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
			<form method="post" action="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings&tab=redirects')) ?>" class="fs-page-settings-form" id="fs-redirects-form">
				<?php wp_nonce_field('fromscratch_save_redirects'); ?>
				<input type="hidden" name="fromscratch_save_redirects" value="1">
				<?php
				$redirect_method = function_exists('fs_config_redirects') ? fs_config_redirects('method') : 'wordpress';
				if (
					function_exists('fs_can_use_htaccess_redirects') && fs_can_use_htaccess_redirects() &&
					$redirect_method === 'wordpress'
				) : ?>
					<div class="notice notice-info inline">
						<p><?= esc_html__('You are running Apache. For better performance, you can set the redirect method to htaccess in config/theme.php under redirects.', 'fromscratch') ?></p>
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
								<td><button type="button" class="button fs-redirect-remove"><?= esc_html__('Remove', 'fromscratch') ?></button></td>
							</tr>
						<?php endforeach; ?>
						<tr class="fs-redirect-row fs-redirect-template" style="display: none;">
							<td><input type="text" name="fs_redirects[__i__][from]" value="" class="regular-text" style="width: 100%;" placeholder="<?= esc_attr__('/old-path', 'fromscratch') ?>" disabled></td>
							<td><input type="text" name="fs_redirects[__i__][to]" value="" class="regular-text" style="width: 100%;" placeholder="<?= esc_attr__('/new-path', 'fromscratch') ?>" disabled></td>
							<td><select name="fs_redirects[__i__][code]" disabled>
									<option value="301"><?= esc_html__('301 (Permanent)', 'fromscratch') ?></option>
									<option value="302"><?= esc_html__('302 (Temporary)', 'fromscratch') ?></option>
								</select></td>
							<td><button type="button" class="button fs-redirect-remove"><?= esc_html__('Remove', 'fromscratch') ?></button></td>
						</tr>
					</tbody>
				</table>
				<p style="margin-top: 12px;">
					<button type="button" class="button" id="fs-redirect-add"><?= esc_html__('Add redirect', 'fromscratch') ?></button>
				</p>
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>
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
			<form method="post" action="<?= esc_url(admin_url('options-general.php?page=fs-theme-settings&tab=css')) ?>" class="fs-page-settings-form">
				<?php wp_nonce_field('fromscratch_save_css'); ?>
				<input type="hidden" name="fromscratch_save_css" value="1">
				<h2 class="title"><?= esc_html__('Custom CSS', 'fromscratch') ?></h2>
				<p class="description"><?= esc_html__('The CSS is output after the design variables (:root).', 'fromscratch') ?></p>
				<p class="description"><?= esc_html__('You can use design variables, e.g. var(--color-primary).', 'fromscratch') ?></p>

				<table class="form-table" style="margin-top: 24px;" role="presentation">
					<tr>
						<td colspan="2" style="padding: 0;">
							<div class="fs-custom-css-editor-wrap">
								<label for="fromscratch_custom_css" class="screen-reader-text"><?= esc_html__('Custom CSS', 'fromscratch') ?></label>
								<textarea name="fromscratch_custom_css" id="fromscratch_custom_css" rows="16" class="large-text code" style="width: 100%; font-family: Consolas, Monaco, monospace;"><?= esc_textarea(get_option('fromscratch_custom_css', '')) ?></textarea>
							</div>
						</td>
					</tr>
				</table>
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>
			</form>
		<?php else : ?>
			<form method="post" action="options.php" class="fs-page-settings-form">
				<h2 class="title"><?= esc_html__('Design', 'fromscratch') ?></h2>
				<p class="description"><?= esc_html__('Define design values used across the website.', 'fromscratch') ?></p>
				<p class="description"><?= esc_html__('Changes are automatically applied wherever they are used.', 'fromscratch') ?></p>

				<hr>

				<?php settings_fields(FS_THEME_OPTION_GROUP_DESIGN); ?>
				<?php
				$design_sections = fs_get_design_sections_resolved();
				$design_tabs = is_array(fs_config('design')) ? fs_config('design') : [];
				?>
				<div class="fs-tabs" data-fs-tabs>
					<nav class="fs-tabs-nav" data-fs-tabs-nav role="tablist">
						<?php foreach ($design_tabs as $i => $dt) :
							$tab_id = isset($dt['title']) ? sanitize_title($dt['title']) : 'tab-' . $i;
						?>
							<button type="button" class="button fs-tabs-btn fs-button-can-toggle <?= ($i === 0) ? 'active' : '' ?>" role="tab" aria-selected="<?= ($i === 0) ? 'true' : 'false' ?>" aria-controls="fs-design-panel-<?= esc_attr($tab_id) ?>" data-fs-tabs-btn data-tab="<?= esc_attr($tab_id) ?>"><?= esc_html(_x($dt['title'] ?? $tab_id, 'Design tab', 'fromscratch')) ?></button>
						<?php endforeach; ?>
					</nav>
					<div class="fs-tabs-panels" data-fs-tabs-panels>
						<?php foreach ($design_tabs as $i => $dt) :
							$tab_id = isset($dt['title']) ? sanitize_title($dt['title']) : 'tab-' . $i;
							$tab_sections = isset($dt['sections']) && is_array($dt['sections']) ? $dt['sections'] : [];
						?>
							<div id="fs-design-panel-<?= esc_attr($tab_id) ?>" class="fs-tabs-panel <?= $i === 0 ? 'fs-tabs-panel--active' : '' ?>" data-fs-tabs-panel role="tabpanel" data-tab="<?= esc_attr($tab_id) ?>" <?= $i === 0 ? 'data-fs-tabs-panel-active="1"' : '' ?>>
								<?php foreach ($tab_sections as $section_config) :
									$section_id = $section_config['id'] ?? null;
									if ($section_id === null || !isset($design_sections[$section_id])) {
										continue;
									}
									$section = $design_sections[$section_id];
									$section_title = $section['title'];
								?>
									<div class="fromscratch-design-section">
										<h3 class="title"><?= esc_html(_x($section_title, 'Design section', 'fromscratch')) ?></h3>
										<table class="widefat striped fs-design-section-table" role="presentation">
											<tbody>
												<?php foreach ($section['variables'] as $v) :
													$var_id = $v['id'] ?? '';
													$var_title = $v['title'] ?? $var_id;
													$var_type = isset($v['type']) && is_string($v['type']) && $v['type'] !== '' ? $v['type'] : 'text';
													$override = fs_design_variable_override($var_id);
													$default = $v['default'] ?? '';
													$input_name = 'fromscratch_design[' . esc_attr($var_id) . ']';
													$type_class = '-' . sanitize_html_class($var_type);
													$row_type_class = 'fromscratch-design-row -' . sanitize_html_class($var_type);
													$preview_color = ($override !== '') ? $override : $default;
												?>
													<tr class="<?= esc_attr($row_type_class) ?>">
														<th scope="row">
															<code class="fs-code-small">--<?= esc_html($var_id) ?></code>
														</th>
														<td>
															<div class="fromscratch-design-field <?= $type_class ?>">
																<label for="fromscratch_design_<?= esc_attr($var_id) ?>" class="screen-reader-text"><?= esc_html($var_title) ?></label>
																<input type="text" name="<?= $input_name ?>" id="fromscratch_design_<?= esc_attr($var_id) ?>" value="<?= esc_attr($override) ?>" placeholder="<?= esc_attr($default) ?>" class="code fs-code-small fromscratch-design-input <?= esc_attr($type_class) ?>" <?= $var_type === 'color' ? 'maxlength="22" data-design-color-input' : '' ?>>
																<?php if ($var_type === 'color') : ?>
																	<span class="fromscratch-design-color-preview<?= $preview_color === '' ? ' -empty' : '' ?>" <?= $preview_color !== '' ? ' style="background-color: ' . esc_attr($preview_color) . ';"' : '' ?> aria-hidden="true" data-design-color-preview></span>
																<?php endif; ?>
																<span class="fromscratch-design-field-description"><?= esc_html($var_title) ?></span>
															</div>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<hr>

				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>
			</form>
		<?php endif; ?>
	</div>
<?php
}

/**
 * Base content option id (without language suffix) for display and fs_content_option snippet.
 * When variableId is language-specific (e.g. fs_content_general_company_name_en), returns the base id; otherwise returns variableId.
 */
function fs_content_base_option_id(string $variableId): string
{
	if (!function_exists('fs_get_content_languages')) {
		return $variableId;
	}
	$languages = fs_get_content_languages();
	foreach ($languages as $lang) {
		$lid = isset($lang['id']) ? (string) $lang['id'] : '';
		if ($lid === '') {
			continue;
		}
		$suffix = '_' . $lid;
		if (str_ends_with($variableId, $suffix)) {
			return substr($variableId, 0, -strlen($suffix));
		}
	}
	return $variableId;
}

/**
 * Output option name row (for developers only): copy option name, copy snippet (fs_content_option or get_option), monospace option id.
 * Shows the actual option key for this row (including _lang suffix when applicable) so it matches the field above.
 * The snippet still uses the base id with fs_content_option() for correct theme API usage.
 */
function fs_content_field_option_name_row(string $variableId, array $variable = []): void
{
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$show = (string) get_user_meta(get_current_user_id(), 'fromscratch_show_content_developer_options', true) === '1';
	$base_id = fs_content_base_option_id($variableId);
	$type = $variable['type'] ?? 'textfield';
	$default = ($type === 'multiselect') ? '[]' : (($type === 'image') ? '0' : "''");
	$snippet = "fs_content_option('" . $base_id . "', " . $default . ")";
	$display_id = $variableId;
	$id_attr = 'fs-opt-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $variableId);
	$id_snippet_attr = 'fs-opt-snippet-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $variableId);
	$hidden_class = $show ? '' : ' fs-content-developer-options-hidden';
?>
	<div class="fs-content-developer-options-container<?= esc_attr($hidden_class) ?>" data-fs-content-developer-options-container>
		<div class="fs-content-developer-options">
			<button type="button" class="button fs-content-developer-options-button" data-fs-copy-from-source="<?= esc_attr($id_attr) ?>" title="<?= esc_attr__('Copy option name', 'fromscratch') ?>">
				<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
					<path d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360ZM200-80q-33 0-56.5-23.5T120-160v-520q0-17 11.5-28.5T160-720q17 0 28.5 11.5T200-680v520h400q17 0 28.5 11.5T640-120q0 17-11.5 28.5T600-80H200Z" />
				</svg>
			</button>
			<button type="button" class="button fs-content-developer-options-button" data-fs-copy-from-source="<?= esc_attr($id_snippet_attr) ?>" title="<?= esc_attr__('Copy snippet', 'fromscratch') ?>">
				<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
					<path d="m353-480 59-59q12-12 12-28t-12-28q-12-12-28.5-12T355-595l-87 87q-6 6-8.5 13t-2.5 15q0 8 2.5 15t8.5 13l87 87q12 12 28.5 12t28.5-12q12-12 12-28t-12-28l-59-59Zm254 0-59 59q-12 12-12 28t12 28q12 12 28.5 12t28.5-12l87-87q6-6 8.5-13t2.5-15q0-8-2.5-15t-8.5-13l-87-87q-6-6-13.5-9t-15-3q-7.5 0-15 3t-13.5 9q-12 12-12 28t12 28l59 59ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Z" />
				</svg>
			</button>
			<code id="<?= esc_attr($id_attr) ?>" class="fs-code-small fs-content-developer-options-code"><?= esc_html($display_id) ?></code>
			<span id="<?= esc_attr($id_snippet_attr) ?>" style="display: none;"><?= esc_html($snippet) ?></span>
		</div>
	</div>
<?php
}

function display_custom_info_field($variable, $variableId, $languageId = null): void
{
	if ($languageId) {
		echo '<div class="fs-content-lang-container -lang-' . esc_attr($languageId) . '" data-fs-content-lang="' . esc_attr($languageId) . '">';
	}
	$value = get_option($variableId, '');
	$type = $variable['type'] ?? 'textfield';
	echo '<div class="fs-content-field-wrap">';
	switch ($type) {
		case 'textfield':
			$tw = isset($variable['width']) ? (int) $variable['width'] : 0;
			$tw_style = $tw > 0 ? 'width: 100%; max-width: ' . $tw . 'px' : 'width: 100%';
			echo '<input class="settings-page-textfield" type="text" name="' . esc_attr($variableId) . '" value="' . esc_attr($value) . '" style="' . $tw_style . '"' . (isset($variable['placeholder']) ? ' placeholder="' . esc_attr($variable['placeholder']) . '"' : '') . '>';
			break;
		case 'textarea':
			$taw = isset($variable['width']) ? (int) $variable['width'] : 0;
			$taw_style = $taw > 0 ? 'width: 100%; max-width: ' . $taw . 'px;' : 'width: 100%;';
			$taw_style .= ' display: block;';
			$textarea_placeholder = isset($variable['placeholder']) ? ' placeholder="' . esc_attr($variable['placeholder']) . '"' : '';
			echo '<textarea class="settings-page-textfield" name="' . esc_attr($variableId) . '" rows="' . (int) ($variable['rows'] ?? 4) . '" style="' . $taw_style . '"' . $textarea_placeholder . '>' . esc_textarea($value) . '</textarea>';
			break;
		case 'select':
			$options = $variable['options'] ?? [];
			$sw = isset($variable['width']) ? (int) $variable['width'] : 0;
			$sw_style = $sw > 0 ? 'width: 100%; max-width: ' . $sw . 'px' : 'min-width: 200px;';
			echo '<select name="' . esc_attr($variableId) . '" class="settings-page-select" style="' . $sw_style . '">';
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
			echo '<span class="fs-content-toggle-label">' . esc_html(!empty($variable['label']) ? $variable['label'] : __('On', 'fromscratch')) . '</span>';
			echo '</label>';
			break;
		case 'multiselect':
			$options = $variable['options'] ?? [];
			$selected = is_array($value) ? $value : (array) json_decode((string) $value, true);
			$selected = array_map('strval', $selected);
			echo '<div class="fs-content-multiselect">';
			echo '<input type="hidden" name="' . esc_attr($variableId) . '[]" value="">';
			foreach ($options as $opt_value => $opt_label) {
				if (is_int($opt_value) && is_array($opt_label)) {
					$opt_value = $opt_label['value'] ?? '';
					$opt_label = $opt_label['label'] ?? $opt_value;
				}
				$opt_value = (string) $opt_value;
				$checked = in_array($opt_value, $selected, true);
				$cb_id = esc_attr($variableId . '_' . preg_replace('/[^a-z0-9_-]/i', '_', $opt_value));
				echo '<label>';
				echo '<input type="checkbox" name="' . esc_attr($variableId) . '[]" id="' . $cb_id . '" value="' . esc_attr($opt_value) . '"' . ($checked ? ' checked' : '') . '>';
				echo '<span>' . esc_html($opt_label) . '</span>';
				echo '</label>';
			}
			echo '</div>';
			break;
		case 'image':
			$img_id = (int) $value;
			$img_url = $img_id > 0 ? wp_get_attachment_image_url($img_id, 'medium') : '';
			echo '<div class="fs-image-picker" data-fs-image-picker>';
			echo '<input type="hidden" name="' . esc_attr($variableId) . '" id="' . esc_attr($variableId) . '" value="' . esc_attr($img_id > 0 ? $img_id : '0') . '" data-fs-image-picker-input>';
			echo '<div class="fs-image-picker-preview" data-fs-image-picker-preview>';
			if ($img_url) {
				echo '<img src="' . esc_url($img_url) . '" alt="">';
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
	echo '</div>';
	if (!empty($variable['description'])) {
		echo '<p class="description">' . esc_html($variable['description']) . '</p>';
	}
	fs_content_field_option_name_row($variableId, $variable);
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
			$content_page = FS_THEME_CONTENT_OPTION_PREFIX . $tab['id'] . '_' . $section['id'];
			add_settings_section('section', $section_title, null, $content_page);
			foreach ($section['variables'] as $variable) {
				$variableId = FS_THEME_CONTENT_OPTION_PREFIX . $tab['id'] . '_' . $section['id'] . '_' . $variable['id'];
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
					$content_langs = fs_get_content_languages();
					if (!empty($content_langs)) {
						foreach ($content_langs as $language) {
							$variableIdLang = $variableId . '_' . $language['id'];
							add_settings_field($variableIdLang, $variable_title, function () use ($variable, $variableIdLang, $language) {
								display_custom_info_field($variable, $variableIdLang, $language['id']);
							}, $content_page, 'section', ['class' => '-is-translatable']);
							register_setting(FS_THEME_OPTION_GROUP_TEXTE, $variableIdLang, $register_args);
						}
					} else {
						add_settings_field($variableId, $variable_title, function () use ($variable, $variableId) {
							display_custom_info_field($variable, $variableId);
						}, $content_page, 'section');
						register_setting(FS_THEME_OPTION_GROUP_TEXTE, $variableId, $register_args);
					}
				} else {
					add_settings_field($variableId, $variable_title, function () use ($variable, $variableId) {
						display_custom_info_field($variable, $variableId);
					}, $content_page, 'section');
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
	if (!current_user_can('manage_options')) {
		return;
	}
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
