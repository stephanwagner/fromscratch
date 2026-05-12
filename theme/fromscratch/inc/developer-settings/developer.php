<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'developer';
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
		'fs_render_developer_cheatsheet',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

function fs_render_developer_cheatsheet(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

?>
	<div class="wrap">
		<?php fs_developer_settings_screen_heading(); ?>
		<?php fs_developer_settings_render_nav(); ?>

		<?php
		if (function_exists('fs_developer_render_system_info_panel')) {
			fs_developer_render_system_info_panel();
		}
		?>
		<hr class="fs-page-settings-divider">

		<div class="fs-page-settings-form" style="margin-top: 0;">

			<h2 class="title" style="margin-top: 0;"><?= esc_html__('Configs', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Optional defines in wp-config.php for local development and testing.', 'fromscratch') ?></p>

			<table class="widefat striped helpers-table__table">
				<tbody>
					<tr>
						<td>
							<strong><?= esc_html__('Simulate client IP', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Overrides the client IP with a fixed IP address. Active only when WP_DEBUG is true.', 'fromscratch') ?></span>
						</td>
						<td style="width: 100%;">
							<code class="fs-code-small">define('FS_SIMULATE_CLIENT_IP', '127.0.0.22');</code>
						</td>
					</tr>
				</tbody>
			</table>

			<hr style="margin: 28px 0;">

			<h2 class="title" style="margin-top: 0;"><?= esc_html__('Helpers', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Common helper functions and utilities for templates, theme code, and frontend scripts.', 'fromscratch') ?></p>

			<table class="widefat striped helpers-table__table">
				<tbody>
					<tr>
						<td>
							<strong><?= esc_html__('Asset Helper', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Builds versioned asset URLs from the theme assets folder.', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">PHP</code>
						</td>
						<td>
							<code class="fs-code-small"><?= esc_html("fs_asset_url('/img/logo.svg')") ?></code>
							<div class="helpers-table__preview-code">
								<span class="helpers-table__preview-pointer">→</span> <code class="fs-code-text fs-code-small">/assets/img/logo.svg?ver=1</code>
							</div>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?= esc_html__('Inline SVG Helper', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Reads an SVG file and returns inline markup you can echo in templates.', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">PHP</code>
						</td>
						<td>
							<code class="fs-code-small"><?= esc_html("fs_svg_code('/img/icon.svg', ['class' => 'my-class']);") ?></code>
							<div class="helpers-table__preview-code">
								<span class="helpers-table__preview-pointer">→</span> <code class="fs-code-text fs-code-small">&lt;svg class="my-class" ...&gt;...&lt;/svg&gt;</code>
							</div>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?= esc_html__('Image Helper', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Builds a WordPress image tag from an attachment ID (or WP_Post attachment).', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">PHP</code>
						</td>
						<td>
							<code class="fs-code-small"><?= esc_html("fs_img(123, 'medium', ['class' => 'my-class', 'loading' => 'eager'])") ?></code>
							<div class="helpers-table__preview-code">
								<span class="helpers-table__preview-pointer">→</span> <code class="fs-code-text fs-code-small">&lt;img src="..." srcset="..." class="my-class" loading="eager" ...&gt;</code>
							</div>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?= esc_html__('Config Helper', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Reads values from config/theme.php and config/theme-design.php via optional dot-path keys.', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">PHP</code>
						</td>
						<td>
							<code class="fs-code-small"><?= esc_html("fs_config('headers.Cache-Control')") ?></code><br>
							<code class="fs-code-small"><?= esc_html("fs_config_cpt('project')") ?></code>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?= esc_html__('Content Helper', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Reads saved Theme Content option values with an optional fallback default.', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">PHP</code>
						</td>
						<td>
							<code class="fs-code-small"><?= esc_html("fs_content('hero_title', 'Default headline')") ?></code>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?= esc_html__('Breadcrumbs', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Renders a breadcrumb trail for the current page, handling pages, posts, archives, and search.', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">PHP</code>
						</td>
						<td>
							<code class="fs-code-small" style="white-space: pre-wrap;"><?= esc_html('fs_breadcrumbs([
  \'home_label\' => \'Home\',
  \'home_url\' => home_url(\'/\'),
  \'separator\' => \'›\',
  \'separator_html\' => \'<b>→</b>\',
]);') ?></code>
						</td>
					</tr>
					<tr>
						<td>
							<strong><?= esc_html__('Modal', 'fromscratch') ?></strong><br>
							<span class="description"><?= esc_html__('Full-screen overlay. Match IDs between trigger and content. Content is moved into the modal on open.', 'fromscratch') ?></span>
						</td>
						<td>
							<code class="fs-code-text fs-code-small">JavaScript</code>
						</td>
						<td>
							<div class="helpers-table__code-description"><?= esc_html__('Attach modal:', 'fromscratch') ?></div>
							<code class="fs-code-small"><?= esc_html('<button data-modal="my-modal">Open</button>') ?></code>

							<div class="helpers-table__code-description"><?= esc_html__('Open manually:', 'fromscratch') ?></div>
							<code class="fs-code-small"><?= esc_html("openModal('my-modal');") ?></code>

							<div class="helpers-table__code-description"><?= esc_html__('Add content:', 'fromscratch') ?></div>
							<code class="fs-code-small"><?= esc_html('<div data-modal-content="my-modal">…</div>') ?></code>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
<?php
}
