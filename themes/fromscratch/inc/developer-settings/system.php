<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'system';
$fs_developer_page_slug = fs_developer_settings_page_slug($fs_developer_tab);

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
?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($system_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>
		<?php if ($perf_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<?php if ((int) get_option('blog_public', 1) === 0) : ?>
			<div class="notice notice-warning inline" style="margin: 16px 0 0;">
				<p><strong><?= esc_html__('Search engines are asked not to index this site.', 'fromscratch') ?></strong></p>
			</div>
		<?php endif; ?>

		<?php
		$check = '✔';
		$cross = '✖';
		$opcache_on = fs_developer_perf_opcache_enabled();
		$opcache_available = function_exists('opcache_get_status');
		$object_cache = fs_developer_perf_object_cache_label();
		$xdebug_on = fs_developer_perf_xdebug_enabled();
		$memory_limit = ini_get('memory_limit');
		$db_server = function_exists('fs_developer_perf_db_server') ? fs_developer_perf_db_server() : null;
		$upload_max = ini_get('upload_max_filesize');
		$post_max = ini_get('post_max_size');
		$cache_hits = null;
		if (function_exists('fs_developer_perf_object_cache_hits')) {
			$cache_hits = (int) call_user_func('fs_developer_perf_object_cache_hits');
		}
		$current_ip = function_exists('fs_developer_perf_current_ip') ? fs_developer_perf_current_ip() : '';
		$guest_ips = get_option('fromscratch_perf_panel_guest_ips', '');
		$guest_panel_on = get_option('fromscratch_perf_panel_guest', '0') === '1';
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

			$warning_label = esc_html(__('Warning', 'fromscratch'));
			$warning_text = esc_html($message);

			return '<div style="margin-top: 4px; font-size: 12px; color: #92400e;">'
				. '<span style="display:inline-block; padding: 0 6px; border-radius: 999px; font-weight: 700; background: #fef3c7; border: 1px solid #f59e0b; margin-right: 8px;">'
				. $warning_label
				. '</span>'
				. $warning_text
				. '</div>';
		};

		// PHP recommendations: based on latest stable PHP from GitHub releases (cached).
		$fs_latest_php_major = 8;
		$fs_latest_php_minor = 5;

		$fs_latest_php_version = get_transient('fs_latest_stable_php_version');
		// WordPress returns `false` when the transient is not set.
		if ($fs_latest_php_version === false) {
			$fs_latest_php_version = null;
			if (function_exists('wp_remote_get')) {
				$resp = wp_remote_get('https://api.github.com/repos/php/php-src/releases?per_page=100', [
					'timeout' => 8,
					'headers' => [
						'Accept' => 'application/vnd.github+json',
						'User-Agent' => 'fromscratch-php-version-check',
					],
				]);
				if (!is_wp_error($resp)) {
					$data = json_decode(wp_remote_retrieve_body($resp), true);
					if (is_array($data)) {
						$best = null;
						foreach ($data as $r) {
							$tag_name = isset($r['tag_name']) ? (string) $r['tag_name'] : '';
							if ($tag_name === '') {
								continue;
							}
							if (!empty($r['prerelease']) || !empty($r['draft'])) {
								continue;
							}
							if (preg_match('/(\d+\.\d+\.\d+)/', $tag_name, $m) !== 1) {
								continue;
							}
							$ver = (string) $m[1];
							if ($best === null || version_compare($ver, (string) $best, '>')) {
								$best = $ver;
							}
						}
						$fs_latest_php_version = is_string($best) ? $best : null;
					}
				}
			}

			// Cache even failures briefly to avoid repeated calls.
			set_transient('fs_latest_stable_php_version', (string) ($fs_latest_php_version ?? ''), 12 * HOUR_IN_SECONDS);
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
		if (!$opcache_available) {
			$opcache_warning = __('OPcache is not installed.', 'fromscratch');
		} elseif (!$opcache_on) {
			$opcache_warning = __('OPcache is disabled. Enable it for significantly better performance.', 'fromscratch');
		}

		$object_cache_active = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
		$is_redis_installed = defined('WP_REDIS_VERSION')
			|| class_exists('\RedisCache\Plugin')
			|| function_exists('redis_cache_enable');

		$object_cache_warning = null;
		if (!$object_cache_active) {
			$object_cache_warning = $is_redis_installed
				? __('Redis is installed, but object cache is not active. Enable Redis object caching.', 'fromscratch')
				: __('No persistent object cache detected. Consider enabling Redis/Memcached for better performance.', 'fromscratch');
		}

		$object_cache_row_value = '';
		if ($object_cache_active) {
			$active_label = $object_cache !== '' ? $object_cache : ($is_redis_installed ? 'Redis' : __('Object cache drop-in', 'fromscratch'));
			if ($active_label === 'external') {
				$active_label = __('Object cache drop-in', 'fromscratch');
			}
			$object_cache_row_value = esc_html($active_label) . ' ' . $check . ' ' . esc_html__('(active)', 'fromscratch');
		} elseif ($is_redis_installed) {
			$object_cache_row_value = esc_html__('Redis', 'fromscratch') . ' ' . $cross . ' ' . esc_html__('(installed, inactive)', 'fromscratch');
		} else {
			$object_cache_row_value = esc_html__('None', 'fromscratch') . ' ' . $cross;
		}

		$db_version_warning = null;
		if ($db_server !== null && isset($db_server['type'], $db_server['version']) && stripos((string) $db_server['type'], 'mysql') !== false) {
			if (preg_match('/^(\d+)/', (string) $db_server['version'], $m) === 1) {
				$db_major = (int) $m[1];
				if ($db_major > 0 && $db_major < 8) {
					$db_version_warning = sprintf(
						/* translators: 1: MySQL/MariaDB version */
						__('Database version (%1$s) is quite old. Consider upgrading for security and performance.', 'fromscratch'),
						$db_server['version']
					);
				}
			}
		}
		?>
		<div class="page-settings-form" style="margin-bottom: 24px;">
			<h2 class="title"><?= esc_html__('System info', 'fromscratch') ?></h2>
			<table class="fs-perf-table fs-perf-summary-table" style="width: auto; margin: 16px 0 12px; border-collapse: collapse;" role="presentation">
				<tbody>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('PHP version', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<div>
								<?= esc_html(PHP_VERSION) ?> <a href="<?= esc_url(add_query_arg('phpinfo', '1', $system_url)) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html__('phpinfo', 'fromscratch') ?></a>
							</div>
							<?= $fs_render_warning($php_version_warning) ?>
						</td>
					</tr>

					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('OPcache', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?php
							if (!$opcache_available) {
								echo $cross . ' ' . esc_html__('not installed', 'fromscratch');
							} else {
								echo $opcache_on ? $check . ' ' . esc_html__('enabled', 'fromscratch') : $cross . ' ' . esc_html__('disabled', 'fromscratch');
							}
							?>
							<?= $fs_render_warning($opcache_warning) ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Object cache', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?= $object_cache_row_value ?>
							<?= $fs_render_warning($object_cache_warning) ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Xdebug', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?= $xdebug_on ? $check . ' ' . esc_html__('enabled', 'fromscratch') : $cross . ' ' . esc_html__('disabled', 'fromscratch') ?>
							<?= $fs_render_warning($xdebug_warning) ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Debug mode', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?= $debug_enabled ? $check . ' ' . esc_html__('enabled', 'fromscratch') : $cross . ' ' . esc_html__('disabled', 'fromscratch') ?>
							<?= $fs_render_warning($debugmode_warning) ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Memory limit', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?= esc_html($memory_limit !== false && $memory_limit !== '' ? $memory_limit : '—') ?>
							<?= $fs_render_warning($memory_warning) ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Max upload size', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?= $upload_max !== false && $upload_max !== '' ? esc_html($upload_max) : '—' ?>
							<?= $fs_render_warning($upload_warning) ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Max post size', 'fromscratch') ?></td>
						<td style="padding: 2px 0;">
							<?= $post_max !== false && $post_max !== '' ? esc_html($post_max) : '—' ?>
							<?= $fs_render_warning($post_warning) ?>
							<?= $fs_render_warning($upload_post_warning) ?>
						</td>
					</tr>
					<?php if ($db_server !== null) : ?>
						<tr>
							<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Database', 'fromscratch') ?></td>
							<td style="padding: 2px 0;"><?= esc_html($db_server['type']) ?></td>
						</tr>
						<tr>
							<td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Database version', 'fromscratch') ?></td>
							<td style="padding: 2px 0;">
								<?= esc_html($db_server['version']) ?>
								<?= $fs_render_warning($db_version_warning) ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<hr class="fs-small">

			<form method="post" action="" style="margin-top: 12px;">
				<?php wp_nonce_field('fromscratch_perf'); ?>
				<h2 class="title"><?= esc_html__('Performance', 'fromscratch') ?></h2>
				<input type="hidden" name="fromscratch_save_perf" value="1">
				<p style="margin-bottom: 8px;">
					<label>
						<input type="hidden" name="fromscratch_perf_admin_bar" value="0">
						<input type="checkbox" name="fromscratch_perf_admin_bar" value="1" <?= checked(get_option('fromscratch_perf_admin_bar', '1'), '1', false) ?>>
						<?= esc_html__('Show performance in admin bar', 'fromscratch') ?>
					</label>
				</p>
				<p style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<label>
						<input type="hidden" name="fromscratch_perf_panel_guest" value="0">
						<input type="checkbox" name="fromscratch_perf_panel_guest" id="fromscratch_perf_panel_guest" value="1" <?= checked($guest_panel_on, true, false) ?>>
						<?= esc_html__('Enable performance panel for logged out users.', 'fromscratch') ?>
					</label>
				</p>
				<div id="fs-perf-guest-ips-wrap" class="fs-perf-guest-ips-wrap" style="margin-top: 12px; <?= $guest_panel_on ? '' : 'display: none;' ?>">
					<p style="margin-bottom: 6px;">
						<?= esc_html__('Your current IP:', 'fromscratch') ?> <code id="fs-perf-current-ip"><?= $current_ip !== '' ? esc_html($current_ip) : esc_html__('—', 'fromscratch') ?></code>
					</p>
					<p style="margin-bottom: 0;">
						<label for="fromscratch_perf_panel_guest_ips"><?= esc_html__('Allowed IP addresses', 'fromscratch') ?></label><br>
						<input type="text" name="fromscratch_perf_panel_guest_ips" id="fromscratch_perf_panel_guest_ips" value="<?= esc_attr($guest_ips) ?>" class="regular-text" placeholder="192.168.1.1, 10.0.0.1" style="margin-top: 4px; max-width: 320px;">
						<span class="description" style="display: block; margin-top: 4px;"><?= esc_html__('Comma-separated. Only these IPs will see the panel when logged out.', 'fromscratch') ?></span>
					</p>
				</div>
				<?php submit_button(); ?>
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

		<hr class="fs-small">

		<form method="post" action="" class="page-settings-form" id="fs-search-visibility">
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
			<?php submit_button(); ?>
		</form>

	</div>
<?php
}
