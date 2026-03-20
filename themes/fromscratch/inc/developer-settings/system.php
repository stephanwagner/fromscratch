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
		?>
		<div class="page-settings-form" style="margin-bottom: 24px;">
			<h2 class="title"><?= esc_html__('System info', 'fromscratch') ?></h2>
			<table class="fs-perf-table fs-perf-summary-table" style="width: auto; margin: 16px 0 12px; border-collapse: collapse;" role="presentation">
				<tbody>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('OPcache', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?php
							if (!$opcache_available) {
								echo $cross . ' ' . esc_html__('not installed', 'fromscratch');
							} else {
								echo $opcache_on ? $check . ' ' . esc_html__('enabled', 'fromscratch') : $cross . ' ' . esc_html__('disabled', 'fromscratch');
							}
						?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Object cache', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?php
							$object_cache_active = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
							$is_redis_installed = defined('WP_REDIS_VERSION')
								|| class_exists('\RedisCache\Plugin')
								|| function_exists('redis_cache_enable');

							if ($object_cache_active) {
								$active_label = $object_cache !== '' ? $object_cache : ($is_redis_installed ? 'Redis' : __('Object cache drop-in', 'fromscratch'));
								if ($active_label === 'external') {
									$active_label = __('Object cache drop-in', 'fromscratch');
								}
								echo esc_html($active_label) . ' ' . $check . ' ' . esc_html__('(active)', 'fromscratch');
							} elseif ($is_redis_installed) {
								echo esc_html__('Redis', 'fromscratch') . ' ' . $cross . ' ' . esc_html__('(installed, inactive)', 'fromscratch');
							} else {
								echo esc_html__('None', 'fromscratch') . ' ' . $cross;
							}
						?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Xdebug', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= $xdebug_on ? $check . ' ' . esc_html__('enabled', 'fromscratch') : $cross . ' ' . esc_html__('disabled', 'fromscratch') ?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Debug mode', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= (function_exists('fs_is_debug') && fs_is_debug()) ? $check . ' ' . esc_html__('enabled', 'fromscratch') : $cross . ' ' . esc_html__('disabled', 'fromscratch') ?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('PHP version', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= esc_html(PHP_VERSION) ?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Memory limit', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= esc_html($memory_limit !== false && $memory_limit !== '' ? $memory_limit : '—') ?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Max upload size', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= $upload_max !== false && $upload_max !== '' ? esc_html($upload_max) : '—' ?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Max post size', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= $post_max !== false && $post_max !== '' ? esc_html($post_max) : '—' ?></td></tr>
					<?php if ($db_server !== null) : ?>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Database', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= esc_html($db_server['type']) ?></td></tr>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('Database version', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><?= esc_html($db_server['version']) ?></td></tr>
					<?php endif; ?>
					<tr><td style="padding: 2px 12px 2px 0; color: #646970;"><?= esc_html__('phpinfo()', 'fromscratch') ?></td>
						<td style="padding: 2px 0;"><a href="<?= esc_url(add_query_arg('phpinfo', '1', $system_url)) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html__('Open in new window', 'fromscratch') ?></a></td></tr>
				</tbody>
			</table>
			<h2 class="title" style="margin-top: 28px;"><?= esc_html__('Performance', 'fromscratch') ?></h2>
			<form method="post" action="" style="margin-top: 12px;">
				<?php wp_nonce_field('fromscratch_perf'); ?>
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
				<?php submit_button(__('Save', 'fromscratch'), 'primary', '', false); ?>
			</form>
			<script>
			(function() {
				var cb = document.getElementById('fromscratch_perf_panel_guest');
				var wrap = document.getElementById('fs-perf-guest-ips-wrap');
				if (cb && wrap) {
					cb.addEventListener('change', function() { wrap.style.display = this.checked ? '' : 'none'; });
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
