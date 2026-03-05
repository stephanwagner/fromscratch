<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'tools';
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
		sprintf(__('Developer › %s', 'fromscratch'), $label),
		'manage_options',
		$fs_developer_page_slug,
		'fs_render_developer_tools',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

add_action('admin_init', function () use ($fs_developer_page_slug) {
	global $pagenow;
	if ($pagenow !== 'options-general.php' || (isset($_GET['page']) ? $_GET['page'] : '') !== $fs_developer_page_slug) {
		return;
	}
	if (!current_user_can('manage_options') || !function_exists('fs_is_developer_user') || !fs_is_developer_user((int) get_current_user_id())) {
		return;
	}
	$url = admin_url('options-general.php?page=fs-developer-tools');

	// Bump asset version (GET with nonce)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['fromscratch_bump']) && !empty($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_bump_asset_version')) {
		$current = get_option('fromscratch_asset_version', '1');
		$next = is_numeric($current) ? (string) ((int) $current + 1) : '2';
		update_option('fromscratch_asset_version', $next);
		set_transient('fromscratch_bump_notice', $next, 30);
		wp_safe_redirect($url);
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if (!empty($_POST['fromscratch_flush_redirect_cache']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_flush_redirect_cache')) {
		flush_rewrite_rules();
		set_transient('fromscratch_flush_redirect_cache_notice', '1', 30);
		wp_safe_redirect(admin_url('options-general.php?page=fs-developer-tools'));
		exit;
	}
	if (!empty($_POST['fromscratch_clean_revisions']) && !empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'fromscratch_clean_revisions')) {
		$keep = isset($_POST['fromscratch_revisions_keep']) ? max(0, (int) $_POST['fromscratch_revisions_keep']) : 5;
		$deleted = fs_clean_revisions($keep);
		set_transient('fromscratch_clean_revisions_notice', $deleted, 30);
		wp_safe_redirect(admin_url('options-general.php?page=fs-developer-tools'));
		exit;
	}
}, 1);

function fs_render_developer_tools(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$bump_notice = get_transient('fromscratch_bump_notice');
	if ($bump_notice !== false) {
		delete_transient('fromscratch_bump_notice');
	}
	$flush_notice = get_transient('fromscratch_flush_redirect_cache_notice');
	if ($flush_notice !== false) {
		delete_transient('fromscratch_flush_redirect_cache_notice');
	}
	$revisions_notice = get_transient('fromscratch_clean_revisions_notice');
	if ($revisions_notice !== false) {
		delete_transient('fromscratch_clean_revisions_notice');
	}

	$notices = [];
	if ($bump_notice !== false) {
		$notices[] = sprintf(__('Asset version increased to %s.', 'fromscratch'), $bump_notice);
	}
	if ($flush_notice !== false) {
		$notices[] = __('Permalink rules have been successfully refreshed.', 'fromscratch');
	}
	if ($revisions_notice !== false && is_numeric($revisions_notice)) {
		$notices[] = sprintf(_n('%s revision deleted.', '%s revisions deleted.', (int) $revisions_notice, 'fromscratch'), number_format_i18n((int) $revisions_notice));
	}

	global $wpdb;
	$revisions_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php foreach ($notices as $msg) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html($msg) ?></strong></p>
			</div>
		<?php endforeach; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<div class="page-settings-form">
			<?php $asset_version = get_option('fromscratch_asset_version', '1'); ?>
			<h2 class="title"><?= esc_html__('Asset Cache', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Bump when static theme files using fs_asset_url have been changed so the cache of the files is updated.', 'fromscratch') ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Cache version', 'fromscratch') ?></th>
					<td>
						<div style="display: flex; align-items: center;">
							<code style="font-size: 14px; height: 30px; line-height: 30px; padding: 0 8px; min-width: 30px; text-align: center; box-sizing: border-box; border-radius: 3px; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.05);">
								<?= esc_html($asset_version) ?>
							</code>
							<?php $bump_url = wp_nonce_url(add_query_arg(['page' => 'fs-developer-tools', 'fromscratch_bump' => '1'], admin_url('options-general.php')), 'fromscratch_bump_asset_version'); ?>
							<a href="<?= esc_url($bump_url) ?>" class="button" style="margin-left: 8px;"><?= esc_html__('Bump version', 'fromscratch') ?></a>
						</div>
					</td>
				</tr>
			</table>

			<hr>

			<h2 class="title" style="margin-top: 28px;"><?= esc_html__('Refresh Permalink Rules', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Updates the WordPress permalink structure and rewrite rules.', 'fromscratch') ?></p>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Run after structural changes.', 'fromscratch') ?></p>
			<form method="post" action="">
				<?php wp_nonce_field('fromscratch_flush_redirect_cache'); ?>
				<input type="hidden" name="fromscratch_flush_redirect_cache" value="1">
				<div style="margin-top: 20px;"><button type="submit" class="button button-primary"><?= esc_html_x('Refresh Permalink Rules', 'Button text', 'fromscratch') ?></button></div>
			</form>

			<hr>

			<h2 class="title" style="margin-top: 28px;"><?= esc_html__('Revision cleaner', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Delete old revisions for all posts and pages.', 'fromscratch') ?></p>
			<p class="description" style="margin-bottom: 12px;"><?= esc_html__('Set how many of the most recent revisions to keep per post, older ones will be removed.', 'fromscratch') ?></p>
			<p style="margin-bottom: 24px;"><strong><?= esc_html(sprintf(_n('%s revision in total.', '%s revisions in total.', $revisions_total, 'fromscratch'), number_format_i18n($revisions_total))) ?></strong></p>
			<form method="post" action="">
				<?php wp_nonce_field('fromscratch_clean_revisions'); ?>
				<input type="hidden" name="fromscratch_clean_revisions" value="1">
				<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<label for="fromscratch_revisions_keep"><?= esc_html__('Keep per post:', 'fromscratch') ?></label>
					<input type="number" name="fromscratch_revisions_keep" id="fromscratch_revisions_keep" value="5" min="0" max="99" step="1" class="small-text">
					<span><?= esc_html__('revisions (0 = delete all)', 'fromscratch') ?></span>
				</div>
				<div style="margin-top: 24px;"><button type="submit" class="button button-primary"><?= esc_html__('Clean revisions', 'fromscratch') ?></button></div>
			</form>
		</div>
	</div>
	<?php
}
