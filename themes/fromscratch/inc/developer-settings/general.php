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


function fs_render_developer_general(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	global $wpdb;
	$wp_perf_time = function_exists('timer_stop') ? timer_stop(0, 3) : 0;
	$wp_perf_memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
	$wp_perf_queries = $wpdb instanceof \wpdb ? (int) $wpdb->num_queries : 0;
	global $wp_actions;
	$wp_perf_hooks = is_array($wp_actions) ? count($wp_actions) : 0;
	$wp_perf_score = $wp_perf_time * $wp_perf_queries;

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

		<div class="page-settings-form" style="margin-bottom: 24px;">
			<h2 class="title"><?= esc_html__('WordPress resources', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Current request metrics (this page load). Lower is better for time, queries and score.', 'fromscratch') ?></p>
			<table class="widefat striped fs-perf-table" style="max-width: 720px; margin-top: 8px;">
				<tbody>
					<tr>
						<td style="width: 160px;"><?= esc_html__('Execution time', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $wp_perf_time) ?>s</strong> <?= $scale_html($wp_perf_time, 'time', 's', __('Execution time', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('Peak memory', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $wp_perf_memory) ?> MB</strong> <?= $scale_html($wp_perf_memory, 'memory', ' MB', __('Peak memory', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('DB queries', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $wp_perf_queries) ?></strong> <?= $scale_html($wp_perf_queries, 'queries', '', __('DB queries', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('Hooks fired', 'fromscratch') ?></td>
						<td><strong><?= esc_html((string) $wp_perf_hooks) ?></strong> <?= $scale_html($wp_perf_hooks, 'hooks', '', __('Hooks fired', 'fromscratch')) ?></td>
					</tr>
					<tr>
						<td><?= esc_html__('Score (time × queries)', 'fromscratch') ?></td>
						<td><strong><?= esc_html(round($wp_perf_score, 1)) ?></strong> <?= $scale_html($wp_perf_score, 'score', '', __('Score', 'fromscratch')) ?></td>
					</tr>
				</tbody>
			</table>
		</div>

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
