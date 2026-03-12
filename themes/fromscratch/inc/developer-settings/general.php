<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'general';
$fs_developer_page_slug = fs_developer_settings_page_slug($fs_developer_tab); // fs-developer

add_action('admin_menu', function () use ($fs_developer_tab, $fs_developer_page_slug) {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$tabs = fs_developer_settings_available_tabs();
	$label = $tabs[$fs_developer_tab]['label'] ?? $fs_developer_tab;
	add_submenu_page(
		'options-general.php',
		__('Developer settings', 'fromscratch') . ' – ' . $label,
		__('Developer', 'fromscratch'),
		'manage_options',
		$fs_developer_page_slug,
		'fs_render_developer_general',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

// Save performance options on load (before any output) so redirect works.
add_action(fs_developer_settings_load_hook($fs_developer_page_slug), function (): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!empty($_POST['fromscratch_save_perf_admin_bar']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf_admin_bar')) {
		$on = isset($_POST['fromscratch_perf_admin_bar']) && $_POST['fromscratch_perf_admin_bar'] === '1';
		update_option('fromscratch_perf_admin_bar', $on ? '1' : '0');
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', 'fs-developer', admin_url('options-general.php')));
		exit;
	}
	if (!empty($_POST['fromscratch_save_perf_guest']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf_guest')) {
		$on = isset($_POST['fromscratch_perf_panel_guest']) && $_POST['fromscratch_perf_panel_guest'] === '1';
		update_option('fromscratch_perf_panel_guest', $on ? '1' : '0');
		$raw = isset($_POST['fromscratch_perf_panel_guest_ips']) ? sanitize_text_field(wp_unslash($_POST['fromscratch_perf_panel_guest_ips'])) : '';
		$ips = array_filter(array_map('trim', explode(',', $raw)));
		$ips = array_filter($ips, static function ($ip) {
			return filter_var($ip, FILTER_VALIDATE_IP) !== false;
		});
		update_option('fromscratch_perf_panel_guest_ips', implode(', ', $ips));
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', 'fs-developer', admin_url('options-general.php')));
		exit;
	}
	// Enable/disable expensive query logging; install or uninstall db.php when toggled.
	if (!empty($_POST['fromscratch_save_perf_slow_queries']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_perf_slow_queries')) {
		$on = isset($_POST['fromscratch_perf_slow_queries_enabled']) && $_POST['fromscratch_perf_slow_queries_enabled'] === '1';
		update_option('fromscratch_perf_slow_queries_enabled', $on ? '1' : '0');
		if (isset($_POST['fromscratch_perf_slow_queries_threshold']) && function_exists('fs_developer_perf_slow_queries_threshold_option')) {
			$thresh = max(0.0, (float) sanitize_text_field(wp_unslash($_POST['fromscratch_perf_slow_queries_threshold'])));
			update_option(fs_developer_perf_slow_queries_threshold_option(), (string) $thresh);
		}
		if ($on && function_exists('fs_developer_perf_slow_queries_install_db_dropin')) {
			$installed = fs_developer_perf_slow_queries_install_db_dropin();
			set_transient('fromscratch_perf_slow_queries_install_result', $installed ? '1' : '0', 30);
		} else {
			if (function_exists('fs_developer_perf_slow_queries_uninstall_db_dropin')) {
				fs_developer_perf_slow_queries_uninstall_db_dropin();
			}
			delete_transient('fromscratch_perf_slow_queries_install_result');
		}
		set_transient('fromscratch_perf_admin_bar_saved', '1', 30);
		wp_safe_redirect(add_query_arg('page', 'fs-developer', admin_url('options-general.php')));
		exit;
	}
});

function fs_render_developer_general(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$perf_admin_bar_saved = get_transient('fromscratch_perf_admin_bar_saved');
	if ($perf_admin_bar_saved) {
		delete_transient('fromscratch_perf_admin_bar_saved');
	}

	$perf = function_exists('fs_developer_perf_metrics') ? fs_developer_perf_metrics() : ['time' => 0, 'memory' => 0, 'queries' => 0, 'hooks' => 0, 'score' => 0];
	$scale_html = function ($value, $metric, $unit = '', $aria_name = '') {
		return function_exists('fs_developer_perf_scale_html')
			? fs_developer_perf_scale_html((float) $value, $metric, [
				'compact' => true,
				'show_min_max' => true,
				'unit' => $unit,
				'aria_label_metric' => $aria_name,
			])
			: '';
	};
?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>

		<?php fs_developer_settings_render_nav(); ?>

		<?php /* if ($perf_admin_bar_saved) : ?>
			<div class="notice notice-success is-dismissible"><p><?= esc_html__('Settings saved.', 'fromscratch') ?></p></div>
		<?php endif; ?>

		<div class="page-settings-form" style="margin-bottom: 24px;">
			<h2 class="title"><?= esc_html__('Performance', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Current request metrics (this page load).', 'fromscratch') ?></p>
			<table class="widefat striped fs-perf-table" style="width: auto; margin: 16px 0 12px;">
				<tbody>
					<tr>
						<td><?= esc_html__('Execution time', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $perf['time']) ?>s</strong></td>
						<td><?= $scale_html($perf['time'], 'time', 's', __('Execution time', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('Peak memory', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $perf['memory']) ?> MB</strong></td>
						<td><?= $scale_html($perf['memory'], 'memory', ' MB', __('Peak memory', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('DB queries', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $perf['queries']) ?></strong></td>
						<td><?= $scale_html($perf['queries'], 'queries', '', __('DB queries', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('Hooks fired', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $perf['hooks']) ?></strong></td>
						<td><?= $scale_html($perf['hooks'], 'hooks', '', __('Hooks fired', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('Score (time × queries)', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $perf['score']) ?></strong></td>
						<td><?= $scale_html($perf['score'], 'score', '', __('Score', 'fromscratch')) ?></td>
					</tr>
				</tbody>
			</table>
			<form method="post" action="" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
				<?php wp_nonce_field('fromscratch_perf_admin_bar'); ?>
				<input type="hidden" name="fromscratch_save_perf_admin_bar" value="1">
				<label>
					<input type="hidden" name="fromscratch_perf_admin_bar" value="0">
					<input type="checkbox" name="fromscratch_perf_admin_bar" value="1" <?= checked(get_option('fromscratch_perf_admin_bar', '1'), '1', false) ?>>
					<?= esc_html__('Show performance in admin bar', 'fromscratch') ?>
				</label>
				<button type="submit" class="button button-small" style="margin-left: 8px;"><?= esc_html__('Save', 'fromscratch') ?></button>
			</form>

			<h3 class="title" style="margin-top: 20px;"><?= esc_html__('Performance panel for guests (by IP)', 'fromscratch') ?></h3>
			<p class="description"><?= esc_html__('Show the sticky performance panel to visitors who are not logged in, only for the IP addresses listed below.', 'fromscratch') ?></p>
			<?php
			$current_ip = function_exists('fs_developer_perf_current_ip') ? fs_developer_perf_current_ip() : '';
			$guest_ips = get_option('fromscratch_perf_panel_guest_ips', '');
			?>
			<form method="post" action="" style="margin-top: 8px;">
				<?php wp_nonce_field('fromscratch_perf_guest'); ?>
				<input type="hidden" name="fromscratch_save_perf_guest" value="1">
				<table class="form-table" role="presentation" style="max-width: 480px;">
					<tr>
						<th scope="row"><?= esc_html__('Your current IP', 'fromscratch') ?></th>
						<td>
							<code id="fs-perf-current-ip"><?= $current_ip !== '' ? esc_html($current_ip) : esc_html__('—', 'fromscratch') ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fromscratch_perf_panel_guest"><?= esc_html__('Enable for guests', 'fromscratch') ?></label></th>
						<td>
							<label>
								<input type="hidden" name="fromscratch_perf_panel_guest" value="0">
								<input type="checkbox" name="fromscratch_perf_panel_guest" id="fromscratch_perf_panel_guest" value="1" <?= checked(get_option('fromscratch_perf_panel_guest', '0'), '1', false) ?>>
								<?= esc_html__('Show performance panel to guests at allowed IPs', 'fromscratch') ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="fromscratch_perf_panel_guest_ips"><?= esc_html__('Allowed IP addresses', 'fromscratch') ?></label></th>
						<td>
							<input type="text" name="fromscratch_perf_panel_guest_ips" id="fromscratch_perf_panel_guest_ips" value="<?= esc_attr($guest_ips) ?>" class="regular-text" placeholder="192.168.1.1, 10.0.0.1">
							<p class="description"><?= esc_html__('Comma-separated. Only these IPs will see the panel when not logged in.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?= esc_html__('Save', 'fromscratch') ?></button></p>
			</form>

			<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Expensive query log', 'fromscratch') ?></h3>
			<?php
			$slow_queries_enabled = function_exists('fs_developer_perf_slow_queries_enabled') && fs_developer_perf_slow_queries_enabled();
			$show_slow_list = isset($_GET['fs_slow_queries']) && $_GET['fs_slow_queries'] === '1';
			$slow_data = function_exists('fs_developer_perf_slow_queries_get') ? fs_developer_perf_slow_queries_get() : null;
			$install_result = get_transient('fromscratch_perf_slow_queries_install_result');
			if ($install_result !== false) {
				delete_transient('fromscratch_perf_slow_queries_install_result');
			}
			?>
			<?php
			$slow_threshold = function_exists('fs_developer_perf_slow_queries_threshold') ? fs_developer_perf_slow_queries_threshold() : 0.05;
			?>
			<form method="post" action="" style="margin-top: 8px;">
				<?php wp_nonce_field('fromscratch_perf_slow_queries'); ?>
				<input type="hidden" name="fromscratch_save_perf_slow_queries" value="1">
				<p style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<label>
						<input type="hidden" name="fromscratch_perf_slow_queries_enabled" value="0">
						<input type="checkbox" name="fromscratch_perf_slow_queries_enabled" value="1" <?= checked($slow_queries_enabled, true, false) ?>>
						<?= esc_html__('Enable expensive query logging', 'fromscratch') ?>
					</label>
					<button type="submit" class="button button-small" style="margin-left: 8px;"><?= esc_html__('Save', 'fromscratch') ?></button>
				</p>
				<p style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
					<label for="fromscratch_perf_slow_queries_threshold"><?= esc_html__('Threshold (seconds)', 'fromscratch') ?></label>
					<input type="number" name="fromscratch_perf_slow_queries_threshold" id="fromscratch_perf_slow_queries_threshold" value="<?= esc_attr((string) $slow_threshold) ?>" step="any" min="0" style="width: 120px;">
					<span class="description"><?= esc_html__('Queries slower than this are recorded. Use e.g. 0.00001 to log almost all.', 'fromscratch') ?></span>
				</p>
			</form>
			<p class="description" style="margin-top: 4px;"><?= esc_html__('When enabled, queries above the threshold are recorded for requests where you are logged in as a developer or your IP is in the performance panel allowlist. No impact when disabled.', 'fromscratch') ?></p>
			<?php if ($install_result === '0') : ?>
				<p class="description" style="margin-top: 8px; color: #d63638;"><?= esc_html__('Recorder could not be installed (wp-content may not be writable). Create wp-content/db.php manually or fix permissions.', 'fromscratch') ?></p>
			<?php endif; ?>
			<?php if ($slow_queries_enabled) : ?>
				<p class="description" style="margin-top: 12px;"><?= esc_html__('Queries are recorded on each page load; view the list below.', 'fromscratch') ?></p>
				<?php if ($slow_data !== null) : ?>
					<p style="margin-top: 8px;"><a href="<?= esc_url(add_query_arg('fs_slow_queries', '1', admin_url('options-general.php?page=fs-developer'))) ?>" class="button button-secondary"><?= esc_html__('View last recorded slow queries', 'fromscratch') ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ($show_slow_list && $slow_data !== null && function_exists('fs_developer_perf_slow_queries_render_list')) : ?>
				<?= fs_developer_perf_slow_queries_render_list($slow_data) ?>
			<?php endif; ?>
		</div>
		*/
		?>

		<div class="page-settings-form">
			<h2 class="title"><?= esc_html__('Cheat sheet', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Common functions for use in templates and theme code.', 'fromscratch') ?></p>

			<div class="fs-cheatsheet" style="max-width: 720px;">
				<h3 class="title" style="margin-top: 20px;"><?= esc_html__('Assets', 'fromscratch') ?></h3>
				<table class="widefat striped" style="margin-top: 8px;">
					<tbody>
						<tr>
							<td style="width: 220px; vertical-align: top;"><code>fs_asset_url( $path )</code></td>
							<td><?= esc_html__('URL for a static theme asset with cache-busting. Path is relative to theme root; files live under assets/.', 'fromscratch') ?>
								<br><code>fs_asset_url( '/img/logo.png' )</code> → <code>.../assets/img/logo.png?ver=1</code>
							</td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>asset_url( $path )</code></td>
							<td><?= esc_html__('Short alias for fs_asset_url().', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_asset_hash( $file )</code></td>
							<td><?= esc_html__('File modification time (for cache-busting). Use when enqueuing scripts/styles manually; path under assets/.', 'fromscratch') ?>
								<br><code>fs_asset_hash( '/assets/css/main.css' )</code>
							</td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_asset_version()</code></td>
							<td><?= esc_html__('Current asset version string (from Settings). In debug mode returns time() so cache is bypassed.', 'fromscratch') ?></td>
						</tr>
					</tbody>
				</table>

				<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Config', 'fromscratch') ?></h3>

				<table class="widefat striped" style="margin-top: 8px;">
					<tbody>
						<tr>
							<td style="width: 220px; vertical-align: top;"><code>fs_config( $key )</code></td>
							<td><?= esc_html__('Theme config (config/theme.php + theme-design.php). Optional dot path, e.g. "menus", "design.sections".', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_config_settings( $key )</code></td>
							<td><?= esc_html__('Content/settings config (config/theme-content.php).', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_config_cpt( $key )</code></td>
							<td><?= esc_html__('Custom post types config (config/cpt.php).', 'fromscratch') ?></td>
						</tr>
					</tbody>
				</table>

				<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Design', 'fromscratch') ?></h3>
				<table class="widefat striped" style="margin-top: 8px;">
					<tbody>
						<tr>
							<td style="width: 220px; vertical-align: top;"><code>fs_design_variable_value( $id )</code></td>
							<td><?= esc_html__('Effective value of a design variable (override from Settings → Theme → Design or default). Use in templates or inline styles.', 'fromscratch') ?>
								<br><code>fs_design_variable_value( 'primary_color' )</code>
							</td>
						</tr>
					</tbody>
				</table>

				<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Email', 'fromscratch') ?></h3>
				<table class="widefat striped" style="margin-top: 8px;">
					<tbody>
						<tr>
							<td style="width: 220px; vertical-align: top;"><code>fs_report_email()</code></td>
							<td><?= esc_html__('Report email from Developer › System. Use for automated reports (e.g. weekly analytics).', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_developer_email()</code></td>
							<td><?= esc_html__('Developer email from Developer › System. Use for system alerts, error notifications and security warnings.', 'fromscratch') ?></td>
						</tr>
					</tbody>
				</table>

				<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Features &amp; debug', 'fromscratch') ?></h3>
				<table class="widefat striped" style="margin-top: 8px;">
					<tbody>
						<tr>
							<td style="width: 220px; vertical-align: top;"><code>fs_theme_feature_enabled( $feature )</code></td>
							<td><?= esc_html__('Check if a feature is on (e.g. "seo", "enable_languages", "enable_blogs").', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_is_debug()</code></td>
							<td><?= esc_html__('True when WP_DEBUG is on. Asset version and hashes bypass cache in debug.', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_is_developer_user( $user_id )</code></td>
							<td><?= esc_html__('True if the user has developer rights (for conditional output).', 'fromscratch') ?></td>
						</tr>
					</tbody>
				</table>

				<?php if (function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('languages')) : ?>
					<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Languages', 'fromscratch') ?></h3>
					<table class="widefat striped" style="margin-top: 8px;">
						<tbody>
							<tr>
								<td style="width: 220px; vertical-align: top;"><code>fs_language_current_request_lang()</code></td>
								<td><?= esc_html__('Current language slug for this request.', 'fromscratch') ?></td>
							</tr>
							<tr>
								<td style="vertical-align: top;"><code>fs_language_home_url( $lang_slug )</code></td>
								<td><?= esc_html__('Home URL for a given language (respects prefix settings).', 'fromscratch') ?></td>
							</tr>
							<tr>
								<td style="vertical-align: top;"><code>fs_get_content_languages()</code></td>
								<td><?= esc_html__('List of configured languages (id, label, etc.).', 'fromscratch') ?></td>
							</tr>
							<tr>
								<td style="vertical-align: top;"><code>fs_get_default_language()</code></td>
								<td><?= esc_html__('Default language slug.', 'fromscratch') ?></td>
							</tr>
						</tbody>
					</table>
				<?php endif; ?>

				<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Helpers', 'fromscratch') ?></h3>
				<table class="widefat striped" style="margin-top: 8px;">
					<tbody>
						<tr>
							<td style="width: 220px; vertical-align: top;"><code>fs_nav_menu( $args )</code></td>
							<td>
								<?= esc_html__('Render a menu with the custom accessible walker from config/nav-walker.php.', 'fromscratch') ?>
								<br><code>fs_nav_menu([ 'theme_location' =&gt; 'main_menu' ]);</code>
							</td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>FS_Walker_Nav_Menu</code></td>
							<td><?= esc_html__('Minimal custom walker for clean markup: menu-link, menu-label, menu-depth-* and accessible submenu toggle buttons.', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_content_option( $option_id, $default )</code></td>
							<td><?= esc_html__('Get a content option with language fallback: current language → default language → key without suffix. Use the base option id (no _en/_de).', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_get_page_id_by_slug( $slug )</code></td>
							<td><?= esc_html__('Get page ID by post slug. Returns null if not found.', 'fromscratch') ?></td>
						</tr>
						<tr>
							<td style="vertical-align: top;"><code>fs_get_or_create_menu_id( $menu_slug )</code></td>
							<td><?= esc_html__('Get (or create) a nav menu ID by slug.', 'fromscratch') ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php
}
