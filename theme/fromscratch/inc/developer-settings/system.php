<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'system';
$fs_developer_page_slug = fs_developer_settings_page_slug($fs_developer_tab);

function fs_developer_redis_safeguard_file_path(): string
{
	return trailingslashit(get_stylesheet_directory()) . 'inc/fromscratch-redis-safeguard.php';
}

function fs_developer_redis_safeguard_wpconfig_path(): string
{
	return trailingslashit(ABSPATH) . 'wp-config.php';
}

function fs_developer_redis_safeguard_block(): string
{
	$stylesheet = sanitize_key((string) get_option('stylesheet', 'fromscratch'));
	if ($stylesheet === '') {
		$stylesheet = 'fromscratch';
	}
	$relative_path = '/wp-content/themes/' . $stylesheet . '/inc/fromscratch-redis-safeguard.php';
	return implode("\n", [
		'// BEGIN FromScratch Redis safeguard',
		'if (!defined(\'WP_REDIS_DISABLE_COMMENT\')) {',
		"\tdefine('WP_REDIS_DISABLE_COMMENT', true);",
		'}',
		'$fs_redis_safeguard = __DIR__ . \'' . $relative_path . '\';',
		'if (file_exists($fs_redis_safeguard)) {',
		"\trequire_once \$fs_redis_safeguard;",
		'}',
		'// END FromScratch Redis safeguard',
	]) . "\n\n";
}

function fs_developer_redis_safeguard_is_installed(): bool
{
	$file_path = fs_developer_redis_safeguard_file_path();
	$wp_config_path = fs_developer_redis_safeguard_wpconfig_path();
	if (!is_file($file_path) || !is_readable($wp_config_path)) {
		return false;
	}
	$wp_config = file_get_contents($wp_config_path);
	if ($wp_config === false) {
		return false;
	}
	return strpos($wp_config, '// BEGIN FromScratch Redis safeguard') !== false
		&& strpos($wp_config, '// END FromScratch Redis safeguard') !== false;
}

const FS_LATEST_PHP_VERSION_HOOK = 'fromscratch_refresh_latest_php_version';

/**
 * Fetch latest stable PHP version from upstream releases.
 * Returns empty string when unavailable.
 */
function fs_fetch_latest_stable_php_version(): string
{
	if (!function_exists('wp_remote_get')) {
		return '';
	}
	$resp = wp_remote_get('https://api.github.com/repos/php/php-src/releases?per_page=100', [
		'timeout' => 8,
		'headers' => [
			'Accept' => 'application/vnd.github+json',
			'User-Agent' => 'fromscratch-php-version-check',
		],
	]);
	if (is_wp_error($resp)) {
		return '';
	}
	$data = json_decode(wp_remote_retrieve_body($resp), true);
	if (!is_array($data)) {
		return '';
	}
	$best = null;
	foreach ($data as $release) {
		$tag_name = isset($release['tag_name']) ? (string) $release['tag_name'] : '';
		if ($tag_name === '' || !empty($release['prerelease']) || !empty($release['draft'])) {
			continue;
		}
		if (preg_match('/(\d+\.\d+\.\d+)/', $tag_name, $m) !== 1) {
			continue;
		}
		$version = (string) $m[1];
		if ($best === null || version_compare($version, (string) $best, '>')) {
			$best = $version;
		}
	}
	return is_string($best) ? $best : '';
}

add_action(FS_LATEST_PHP_VERSION_HOOK, function (): void {
	$version = fs_fetch_latest_stable_php_version();
	set_transient('fs_latest_stable_php_version', $version, 24 * HOUR_IN_SECONDS * 7 * 2);
});

/**
 * Schedule PHP version metadata refresh in background.
 */
function fs_schedule_latest_php_version_refresh(): void
{
	if (get_transient('fs_latest_stable_php_version') !== false) {
		return;
	}
	if (wp_next_scheduled(FS_LATEST_PHP_VERSION_HOOK)) {
		return;
	}
	wp_schedule_single_event(time() + 1, FS_LATEST_PHP_VERSION_HOOK);
}

function fs_developer_redis_enabled(): bool
{
	return function_exists('fs_config_redis_enabled') && fs_config_redis_enabled();
}

function fs_developer_redis_safeguard_install(): array
{
	$file_path = fs_developer_redis_safeguard_file_path();
	$wp_config_path = fs_developer_redis_safeguard_wpconfig_path();
	$block = fs_developer_redis_safeguard_block();

	if (!is_file($file_path) || !is_readable($file_path)) {
		return ['ok' => false, 'message' => __('Safeguard file is missing in theme /inc folder.', 'fromscratch')];
	}
	if (!is_file($wp_config_path) || !is_readable($wp_config_path) || !is_writable($wp_config_path)) {
		return ['ok' => false, 'message' => __('Cannot update wp-config.php for Redis safeguard.', 'fromscratch')];
	}

	$wp_config = file_get_contents($wp_config_path);
	if ($wp_config === false) {
		return ['ok' => false, 'message' => __('Failed to read wp-config.php.', 'fromscratch')];
	}
	if (strpos($wp_config, '// BEGIN FromScratch Redis safeguard') === false) {
		$needle = "/* That's all, stop editing! Happy publishing. */";
		if (strpos($wp_config, $needle) !== false) {
			$wp_config = str_replace($needle, "\n" . $block . "\n" . $needle, $wp_config);
		} else {
			$wp_config .= "\n" . $block;
		}
	} else {
		$wp_config = (string) preg_replace(
			'/\/\/ BEGIN FromScratch Redis safeguard.*?\/\/ END FromScratch Redis safeguard\s*/s',
			$block,
			$wp_config
		);
	}
	if (file_put_contents($wp_config_path, $wp_config) === false) {
		return ['ok' => false, 'message' => __('Failed to write Redis safeguard include to wp-config.php.', 'fromscratch')];
	}

	return ['ok' => true, 'message' => __('Redis safeguard installed.', 'fromscratch')];
}

add_action('admin_menu', function () use ($fs_developer_tab, $fs_developer_page_slug) {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$tabs = fs_developer_settings_available_tabs();
	if (!isset($tabs[$fs_developer_tab])) {
		return;
	}
	$label = $tabs[$fs_developer_tab]['label'];
	add_submenu_page(
		'options-general.php',
		__('Developer settings', 'fromscratch') . ' – ' . $label,
		__('Developer', 'fromscratch'),
		'manage_options',
		$fs_developer_page_slug,
		'fs_render_developer_system',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

// phpinfo() in new window (must run before any output).
add_action(fs_developer_settings_load_hook($fs_developer_page_slug), function (): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (function_exists('fs_is_developer_user') && !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	if (!empty($_GET['phpinfo']) && $_GET['phpinfo'] === '1') {
		phpinfo();
		exit;
	}
}, 1);

add_action('admin_init', function () use ($fs_developer_page_slug) {
	global $pagenow;
	if ($pagenow !== 'options-general.php' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if ((isset($_GET['page']) ? $_GET['page'] : '') !== $fs_developer_page_slug) {
		return;
	}
	if (!current_user_can('manage_options') || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$url = admin_url('options-general.php?page=' . fs_developer_settings_page_slug('system'));

	// Redis safeguard install (manual action).
	if (fs_developer_redis_enabled() && !empty($_POST['fromscratch_install_redis_safeguard']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_install_redis_safeguard')) {
		$result = fs_developer_redis_safeguard_install();
		set_transient('fromscratch_redis_safeguard_notice', $result, 30);
		wp_safe_redirect($url);
		exit;
	}

	// Performance settings
	if (!empty($_POST['fromscratch_save_perf']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf')) {
		$on = isset($_POST['fromscratch_perf_admin_bar']) && $_POST['fromscratch_perf_admin_bar'] === '1';
		update_option('fromscratch_perf_admin_bar', $on ? '1' : '0');

		$guest_on = isset($_POST['fromscratch_perf_panel_guest']) && $_POST['fromscratch_perf_panel_guest'] === '1';
		update_option('fromscratch_perf_panel_guest', $guest_on ? '1' : '0');
		$raw = isset($_POST['fromscratch_perf_panel_guest_ips']) ? sanitize_text_field(wp_unslash($_POST['fromscratch_perf_panel_guest_ips'])) : '';
		$ips = array_filter(array_map('trim', explode(',', $raw)));
		$ips = array_filter($ips, static function ($ip) {
			return filter_var($ip, FILTER_VALIDATE_IP) !== false;
		});
		update_option('fromscratch_perf_panel_guest_ips', implode(', ', $ips));
		if (fs_developer_redis_enabled()) {
			$redis_guard_result = fs_developer_redis_safeguard_install();
			if (is_array($redis_guard_result) && empty($redis_guard_result['ok'])) {
				set_transient('fromscratch_redis_safeguard_notice', $redis_guard_result, 30);
			}
		}
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}

	// Search engine visibility (blog_public)
	if (!empty($_POST['fromscratch_save_search_visibility']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_search_visibility')) {
		$discourage = !empty($_POST['blog_public_discourage']);
		update_option('blog_public', $discourage ? '0' : '1');
		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}

	// Matomo settings
	if (!empty($_POST['fromscratch_save_matomo']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_system_matomo')) {
		$matomo_url = isset($_POST['fromscratch_matomo_url']) ? esc_url_raw(trim((string) wp_unslash($_POST['fromscratch_matomo_url']))) : '';
		$site_id = isset($_POST['fromscratch_matomo_site_id']) ? (int) $_POST['fromscratch_matomo_site_id'] : 1;
		$token = isset($_POST['fromscratch_matomo_token_auth']) ? sanitize_text_field((string) wp_unslash($_POST['fromscratch_matomo_token_auth'])) : '';
		$custom_js = isset($_POST['fromscratch_matomo_custom_js']) ? trim((string) wp_unslash($_POST['fromscratch_matomo_custom_js'])) : '';

		update_option('fromscratch_matomo_url', $matomo_url);
		update_option('fromscratch_matomo_site_id', max(1, $site_id));
		update_option('fromscratch_matomo_token_auth', $token);
		update_option('fromscratch_matomo_custom_js', $custom_js);

		set_transient('fromscratch_system_saved', '1', 30);
		wp_safe_redirect($url);
		exit;
	}
}, 1);

function fs_render_developer_system(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$system_saved = get_transient('fromscratch_system_saved');
	if ($system_saved !== false) {
		delete_transient('fromscratch_system_saved');
	}
	$perf_saved = get_transient('fromscratch_perf_admin_bar_saved');
	if ($perf_saved !== false) {
		delete_transient('fromscratch_perf_admin_bar_saved');
	}
	$redis_guard_notice = null;
	if (fs_developer_redis_enabled()) {
		$redis_guard_notice = get_transient('fromscratch_redis_safeguard_notice');
		if ($redis_guard_notice !== false) {
			delete_transient('fromscratch_redis_safeguard_notice');
		}
	}
?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($system_saved !== false || $perf_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>
		<?php if (is_array($redis_guard_notice) && !empty($redis_guard_notice['message'])) : ?>
			<div class="notice <?= !empty($redis_guard_notice['ok']) ? 'notice-success' : 'notice-warning' ?> is-dismissible">
				<p><strong><?= esc_html((string) $redis_guard_notice['message']) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<?php if ((int) get_option('blog_public', 1) === 0) : ?>
			<div class="notice notice-warning inline" style="margin: 16px 0 0;">
				<p><strong><?= esc_html__('Search engines are asked not to index this site.', 'fromscratch') ?></strong></p>
			</div>
		<?php endif; ?>

		<?php
		$fs_system_status = static function (bool $check, string $label): string {
			$check_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="m389-369 299-299q11-11 25.5-11t25.5 11q11 11 11 25.5T739-617L415-292q-11 11-25.5 11T364-292L221-435q-11-11-11-25.5t11-25.5q11-11 25.5-11t25.5 11l117 117Z"/></svg>';
			$cross_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M480-429 316-265q-11 11-25 10.5T266-266q-11-11-11-25.5t11-25.5l163-163-164-164q-11-11-10.5-25.5T266-695q11-11 25.5-11t25.5 11l163 164 164-164q11-11 25.5-11t25.5 11q11 11 11 25.5T695-644L531-480l164 164q11 11 11 25t-11 25q-11 11-25.5 11T644-266L480-429Z"/></svg>';
			$label = esc_html($label);
			return '<span class="fs-system-status-icon-wrap">' . ($check ? $check_icon : $cross_icon) . '<span class="fs-system-status-icon-label">' . $label . '</span></span>';
		};

		$opcache_ext_loaded  = extension_loaded('Zend OPcache') || extension_loaded('opcache');
		$opcache_enable_read = $opcache_ext_loaded ? ini_get('opcache.enable') : null;
		$opcache_ini_is_one  = $opcache_ext_loaded
			&& (string) (is_scalar($opcache_enable_read) ? trim((string) $opcache_enable_read) : '') === '1';

		$xdebug_on = fs_developer_perf_xdebug_enabled();
		$memory_limit = ini_get('memory_limit');
		$db_server = function_exists('fs_developer_perf_db_server') ? fs_developer_perf_db_server() : null;
		$upload_max = ini_get('upload_max_filesize');
		$post_max = ini_get('post_max_size');
		$current_ip = function_exists('fs_developer_perf_current_ip') ? fs_developer_perf_current_ip() : '';
		$guest_ips = get_option('fromscratch_perf_panel_guest_ips', '');
		$guest_panel_on = get_option('fromscratch_perf_panel_guest', '0') === '1';
		$matomo_url_value = (string) get_option('fromscratch_matomo_url', '');
		$matomo_site_id_value = (int) get_option('fromscratch_matomo_site_id', 1);
		$matomo_token_value = (string) get_option('fromscratch_matomo_token_auth', '');
		$matomo_custom_js_value = (string) get_option('fromscratch_matomo_custom_js', '');
		$matomo_feature_enabled = function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('matomo');
		$system_url = admin_url('options-general.php?page=' . fs_developer_settings_page_slug('system'));

		$fs_parse_bytes = function ($value): ?int {
			if ($value === false || $value === null) {
				return null;
			}
			$value = trim((string) $value);
			if ($value === '' || $value === '-1') {
				return null; // Treat as unknown/unlimited.
			}
			if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])$/i', $value, $m) !== 1) {
				return null;
			}
			$amount = (float) $m[1];
			$unit = strtoupper($m[2]);
			$multiplier = match ($unit) {
				'K' => 1024,
				'M' => 1024 * 1024,
				'G' => 1024 * 1024 * 1024,
				default => 1,
			};
			return (int) floor($amount * $multiplier);
		};

		$fs_render_warning = function (?string $message): string {
			if ($message === null || $message === '') {
				return '';
			}

			$warning_label = esc_html(__('Warning:', 'fromscratch'));
			$warning_text = esc_html($message);

			return '<div class="fs-warning-wrap">'
				. '<span class="fs-warning-label">' . $warning_label . '</span>'
				. ' '
				. '<span class="fs-warning-text">' . $warning_text . '</span>'
				. '</div>';
		};

		// PHP recommendations: based on latest stable PHP from GitHub releases (cached).
		$fs_latest_php_major = 8;
		$fs_latest_php_minor = 5;

		$fs_latest_php_version = get_transient('fs_latest_stable_php_version');
		if ($fs_latest_php_version === false) {
			fs_schedule_latest_php_version_refresh();
			$fs_latest_php_version = '';
		}

		if (is_string($fs_latest_php_version) && $fs_latest_php_version !== '' && preg_match('/^(\d+)\.(\d+)\./', $fs_latest_php_version, $m) === 1) {
			$fs_latest_php_major = (int) $m[1];
			$fs_latest_php_minor = (int) $m[2];
		}

		$fs_php_min_minor = max(0, $fs_latest_php_minor - 2);
		$php_parts = explode('.', PHP_VERSION);
		$php_major = (int) ($php_parts[0] ?? 0);
		$php_minor = (int) ($php_parts[1] ?? 0);
		$php_version_warning = null;
		if ($php_major < $fs_latest_php_major || ($php_major === $fs_latest_php_major && $php_minor < $fs_php_min_minor)) {
			$php_version_warning = sprintf(
				/* translators: 1: current PHP version, 2: recommended minimum PHP version, 3: latest stable PHP major.minor */
				__('Your PHP version (%1$s) is older than recommended. Consider upgrading to at least %2$s (latest stable is %3$s).', 'fromscratch'),
				PHP_VERSION,
				$fs_latest_php_major . '.' . $fs_php_min_minor,
				$fs_latest_php_major . '.' . $fs_latest_php_minor
			);
		}

		$memory_warning = null;
		$memory_limit_bytes = $fs_parse_bytes($memory_limit);
		if ($memory_limit_bytes !== null && $memory_limit_bytes < 256 * 1024 * 1024) {
			$memory_warning = __('Consider increasing `memory_limit` to at least 256M.', 'fromscratch');
		}

		$upload_warning = null;
		$upload_bytes = $fs_parse_bytes($upload_max);
		if ($upload_bytes !== null && $upload_bytes < 16 * 1024 * 1024) {
			$upload_warning = __('Consider increasing `upload_max_filesize` to at least 16M.', 'fromscratch');
		}

		$post_warning = null;
		$post_bytes = $fs_parse_bytes($post_max);
		if ($post_bytes !== null && $post_bytes < 16 * 1024 * 1024) {
			$post_warning = __('Consider increasing `post_max_size` to at least 16M.', 'fromscratch');
		}

		$upload_post_warning = null;
		if ($upload_bytes !== null && $post_bytes !== null && $post_bytes < $upload_bytes) {
			$upload_post_warning = __('`post_max_size` is smaller than `upload_max_filesize`. Some uploads may fail. Increase `post_max_size`.', 'fromscratch');
		}

		$debug_enabled = function_exists('fs_is_debug') && fs_is_debug();
		$debugmode_warning = $debug_enabled ? __('Debug mode is enabled. Disable it in production.', 'fromscratch') : null;
		$xdebug_warning = $xdebug_on ? __('Xdebug is enabled. Disable it in production for better performance.', 'fromscratch') : null;

		$opcache_warning = null;
		if (! $opcache_ext_loaded) {
			$opcache_warning = __('Install PHP OPcache for significantly better performance.', 'fromscratch');
			$opcache_status  = $fs_system_status(false, esc_html__('Not installed', 'fromscratch'));
		} elseif ($opcache_ini_is_one) {
			$opcache_status = $fs_system_status(true, esc_html__('Active', 'fromscratch'));
		} else {
			$opcache_status = $fs_system_status(false, esc_html__('Unknown, See PHP info', 'fromscratch'));
		}

		$redis_enabled = fs_developer_redis_enabled();
		$object_cache_active = function_exists('wp_us§ing_ext_object_cache') && wp_using_ext_object_cache();
		$is_redis_installed = $redis_enabled && (
			defined('WP_REDIS_VERSION')
			|| class_exists('\RedisCache\Plugin')
			|| function_exists('redis_cache_enable')
		);
		$redis_guard_installed = $redis_enabled ? fs_developer_redis_safeguard_is_installed() : false;

		$object_cache_warning = null;
		if (!$object_cache_active) {
			$object_cache_warning = $redis_enabled && $is_redis_installed
				? __('Redis is installed, but object cache is not active. Enable Redis object caching.', 'fromscratch')
				: __('No persistent object cache detected. Consider enabling an object cache for better performance.', 'fromscratch');
		}

		$object_cache_row_value = $object_cache_active ? $fs_system_status(true, esc_html__('Active', 'fromscratch')) : $fs_system_status(false, esc_html__('Inactive', 'fromscratch'));
		$redis_safeguard_warning = null;
		if ($redis_enabled && $is_redis_installed && !$redis_guard_installed) {
			$redis_safeguard_warning = __('Redis object cache is active but safeguard is not installed. Install the safeguard to prevent outages when Redis is unavailable.', 'fromscratch');
		}

		$db_version_warning = null;
		if ($db_server !== null && isset($db_server['type'], $db_server['version']) && stripos((string) $db_server['type'], 'mysql') !== false) {
			if (preg_match('/^(\d+)/', (string) $db_server['version'], $m) === 1) {
				$db_major = (int) $m[1];
				if ($db_major > 0 && $db_major < 8) {
					$db_version_warning = sprintf(
						/* translators: %s: database server version */
						__('Database version (%s) is quite old. Consider upgrading.', 'fromscratch'),
						$db_server['version']
					);
				}
			}
		}
		?>
		<div class="fs-page-settings-form" style="margin-bottom: 24px;">
			<h2 class="title"><?= esc_html__('System info', 'fromscratch') ?></h2>
			<table class="widefat striped fs-system-info-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?= esc_html__('PHP version', 'fromscratch') ?></th>
						<td>
							<div>
								<?= esc_html(PHP_VERSION) ?><br>
								<a href="<?= esc_url(add_query_arg('phpinfo', '1', $system_url)) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html__('Open PHP info', 'fromscratch') ?></a>
							</div>
							<?= $fs_render_warning($php_version_warning) ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?= esc_html__('OPcache', 'fromscratch') ?></th>
						<td>
							<?= $opcache_status ?>
							<?= $fs_render_warning($opcache_warning) ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('Object cache', 'fromscratch') ?></th>
						<td>
							<?= $object_cache_row_value ?>
							<?= $fs_render_warning($object_cache_warning) ?>
						</td>
					</tr>
					<?php if ($redis_enabled) : ?>
						<tr>
							<th scope="row"><?= esc_html__('Redis safeguard', 'fromscratch') ?></th>
							<td>
								<?= $redis_guard_installed ? $fs_system_status(true, esc_html__('Installed', 'fromscratch')) : $fs_system_status(false, esc_html__('Not installed', 'fromscratch')) ?>
								<?php if (!$redis_guard_installed) : ?>
									<form method="post" action="" style="display: block;">
										<?php wp_nonce_field('fromscratch_install_redis_safeguard'); ?>
										<input type="hidden" name="fromscratch_install_redis_safeguard" value="1">
										<button type="submit" class="is-link"><?= esc_html__('Install safeguard file', 'fromscratch') ?></button>
									</form>
								<?php endif; ?>
								<?= $fs_render_warning($redis_safeguard_warning) ?>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?= esc_html__('Xdebug', 'fromscratch') ?></th>
						<td>
							<?= $xdebug_on ? $fs_system_status(true, esc_html__('Enabled', 'fromscratch')) : $fs_system_status(false, esc_html__('Disabled', 'fromscratch')) ?>
							<?= $fs_render_warning($xdebug_warning) ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('Debug mode', 'fromscratch') ?></th>
						<td>
							<?= $debug_enabled ? $fs_system_status(true, esc_html__('Enabled', 'fromscratch')) : $fs_system_status(false, esc_html__('Disabled', 'fromscratch')) ?>
							<?= $fs_render_warning($debugmode_warning) ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('Memory limit', 'fromscratch') ?></th>
						<td>
							<?= $memory_limit !== false && $memory_limit !== '' ? esc_html($memory_limit) : – ?>
							<?= $fs_render_warning($memory_warning) ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('Max upload size', 'fromscratch') ?></th>
						<td>
							<?= $upload_max !== false && $upload_max !== '' ? esc_html($upload_max) : – ?>
							<?= $fs_render_warning($upload_warning) ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?= esc_html__('Max post size', 'fromscratch') ?></th>
						<td>
							<?= $post_max !== false && $post_max !== '' ? esc_html($post_max) : – ?>
							<?= $fs_render_warning($post_warning) ?>
							<?= $fs_render_warning($upload_post_warning) ?>
						</td>
					</tr>
					<?php if ($db_server !== null) : ?>
						<tr>
							<th scope="row"><?= esc_html__('Database', 'fromscratch') ?></th>
							<td><?= esc_html($db_server['type']) ?></td>
						</tr>
						<tr>
							<th scope="row"><?= esc_html__('Database version', 'fromscratch') ?></th>
							<td>
								<?= esc_html($db_server['version']) ?>
								<?= $fs_render_warning($db_version_warning) ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<hr>

			<form method="post" action="" style="margin-top: 12px;">
				<?php wp_nonce_field('fromscratch_perf'); ?>
				<h2 class="title"><?= esc_html__('Performance', 'fromscratch') ?></h2>
				<input type="hidden" name="fromscratch_save_perf" value="1">
				<p style="margin-bottom: 8px;">
					<label>
						<input type="hidden" name="fromscratch_perf_admin_bar" value="0">
						<input type="checkbox" name="fromscratch_perf_admin_bar" value="1" <?= checked(get_option('fromscratch_perf_admin_bar', '1'), '1', false) ?>>
						<?= esc_html__('Show performance panel in admin bar', 'fromscratch') ?>
					</label>
				</p>
				<p style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<label>
						<input type="hidden" name="fromscratch_perf_panel_guest" value="0">
						<input type="checkbox" name="fromscratch_perf_panel_guest" id="fromscratch_perf_panel_guest" value="1" <?= checked($guest_panel_on, true, false) ?>>
						<?= esc_html__('Enable performance panel for logged out users', 'fromscratch') ?>
					</label>
				</p>
				<div id="fs-perf-guest-ips-wrap" class="fs-perf-guest-ips-wrap fs-indent-checkbox" style="margin-top: 12px; <?= $guest_panel_on ? '' : 'display: none;' ?>">
					<p style="margin-bottom: 6px;">
						<?= esc_html__('Your current IP address:', 'fromscratch') ?> <code id="fs-perf-current-ip"><?= $current_ip !== '' ? esc_html($current_ip) : – ?></code>
					</p>
					<p style="margin-bottom: 0;">
						<label for="fromscratch_perf_panel_guest_ips"><?= esc_html__('Allowed IP addresses', 'fromscratch') ?></label><br>
						<input type="text" name="fromscratch_perf_panel_guest_ips" id="fromscratch_perf_panel_guest_ips" value="<?= esc_attr($guest_ips) ?>" class="regular-text" placeholder="<?= esc_attr__('192.168.1.1, 10.0.0.1', 'fromscratch') ?>" style="margin-top: 4px; max-width: 320px;">
						<span class="description" style="display: block; margin-top: 4px;"><?= esc_html__('Comma-separated. Only these IPs will see the panel when logged out.', 'fromscratch') ?></span>
					</p>
				</div>
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>
			</form>
			<script>
				(function() {
					var cb = document.getElementById('fromscratch_perf_panel_guest');
					var wrap = document.getElementById('fs-perf-guest-ips-wrap');
					if (cb && wrap) {
						cb.addEventListener('change', function() {
							wrap.style.display = this.checked ? '' : 'none';
						});
					}
				})();
			</script>
		</div>

		<?php if ($matomo_feature_enabled) : ?>
			<hr class="fs-page-settings-divider">

			<form method="post" action="" class="fs-page-settings-form" id="fs-matomo-settings">
				<?php wp_nonce_field('fromscratch_system_matomo'); ?>
				<input type="hidden" name="fromscratch_save_matomo" value="1">
				<h2 class="title"><?= esc_html__('Matomo', 'fromscratch') ?></h2>
				<p class="description"><?= esc_html__('Loads the Matomo tracking script on the frontend and transmits page view and interaction data to the configured Matomo endpoint using the provided site ID.', 'fromscratch') ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fromscratch_matomo_url"><?= esc_html__('Matomo URL', 'fromscratch') ?></label></th>
						<td>
							<input type="url" name="fromscratch_matomo_url" id="fromscratch_matomo_url" value="<?= esc_attr($matomo_url_value) ?>" class="regular-text" placeholder="https://analytics.example.com" style="max-width: 420px;">
							<p class="description"><?= esc_html__('Base URL of your Matomo instance, e.g. https://analytics.example.com', 'fromscratch') ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fromscratch_matomo_site_id"><?= esc_html__('Site ID', 'fromscratch') ?></label></th>
						<td>
							<input type="number" min="1" step="1" name="fromscratch_matomo_site_id" id="fromscratch_matomo_site_id" value="<?= esc_attr((string) $matomo_site_id_value) ?>" class="small-text">
							<p class="description"><?= esc_html__('Matomo site ID (idSite). Default is 1.', 'fromscratch') ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fromscratch_matomo_token_auth"><?= esc_html__('Auth Token', 'fromscratch') ?></label></th>
						<td>
							<input type="text" name="fromscratch_matomo_token_auth" id="fromscratch_matomo_token_auth" value="<?= esc_attr($matomo_token_value) ?>" class="regular-text" style="max-width: 420px;">
							<p class="description"><?= esc_html__('To enable analytics on the dashboard or in emails, provide an auth token. You can create auth tokens in Matomo under Administration › Personal › Security › Auth tokens.', 'fromscratch') ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fromscratch_matomo_custom_js"><?= esc_html__('Tracking settings', 'fromscratch') ?></label></th>
						<td>
							<textarea name="fromscratch_matomo_custom_js" id="fromscratch_matomo_custom_js" rows="4" class="large-text code fs-code-small" placeholder="_paq.push(['setUserId', '123']);"><?= esc_textarea($matomo_custom_js_value) ?></textarea>
							<p class="description"><?= esc_html__('Optional additional _paq commands. One command per line.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>
				<div class="fs-submit-row">
					<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
				</div>
			</form>
		<?php endif; ?>

		<hr class="fs-page-settings-divider">

		<form method="post" action="" class="fs-page-settings-form" id="fs-search-visibility">
			<?php wp_nonce_field('fromscratch_system_search_visibility'); ?>
			<input type="hidden" name="fromscratch_save_search_visibility" value="1">
			<h2 class="title"><?= esc_html__('Search engine visibility', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('When enabled, search engines are asked not to index this site.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Visibility', 'fromscratch') ?></th>
					<td>
						<label>
							<input type="checkbox" name="blog_public_discourage" value="1" <?= checked((int) get_option('blog_public', 1), 0, false) ?>>
							<?= esc_html__('Discourage search engines from indexing this site', 'fromscratch') ?>
						</label>
						<p class="description fs-indent-checkbox"><?= esc_html__('It is up to search engines whether they follow this request.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<div class="fs-submit-row">
				<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
			</div>
		</form>

	</div>
<?php
}
