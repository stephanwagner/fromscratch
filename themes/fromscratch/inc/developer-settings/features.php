<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'features';
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
		'fs_render_developer_features',
		fs_developer_tab_position($fs_developer_tab)
	);
}, 20);

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
	if (empty($_POST['option_page']) || $_POST['option_page'] !== FS_THEME_OPTION_GROUP_FEATURES || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_FEATURES . '-options')) {
		return;
	}
	$value = isset($_POST['fromscratch_features']) && is_array($_POST['fromscratch_features']) ? $_POST['fromscratch_features'] : [];
	$sanitized = function_exists('fs_sanitize_features') ? fs_sanitize_features($value) : [];
	update_option('fromscratch_features', $sanitized);
	set_transient('fromscratch_features_saved', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-developer-features'));
	exit;
}, 1);

function fs_render_developer_features(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$features_saved = get_transient('fromscratch_features_saved');
	if ($features_saved !== false) {
		delete_transient('fromscratch_features_saved');
	}

	$features = get_option('fromscratch_features', []);
	if (!is_array($features)) {
		$features = [];
	}
	$defaults = function_exists('fs_theme_feature_defaults') ? fs_theme_feature_defaults() : [];
	$feat = function ($key) use ($features, $defaults) {
		return isset($features[$key]) ? (int) $features[$key] : (int) ($defaults[$key] ?? 0);
	};
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($features_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<h2 class="title"><?= esc_html__('Features', 'fromscratch') ?></h2>
		<form method="post" action="" class="page-settings-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_FEATURES); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Blogs', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_blogs]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_blogs]" id="fromscratch_features_enable_blogs" value="1" <?= checked($feat('enable_blogs'), 1, false) ?>> <?= esc_html__('Allow posts', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Shows the Posts menu in the admin and allows creating and editing blog posts.', 'fromscratch') ?></p>
						<div class="fs-feature-sub fs-indent-checkbox" id="fs-feature-sub-blogs" style="margin-top: 12px; <?= $feat('enable_blogs') !== 1 ? 'display:none;' : '' ?>">
							<input type="hidden" name="fromscratch_features[enable_remove_post_tags]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_remove_post_tags]" value="1" <?= checked($feat('enable_remove_post_tags'), 1, false) ?>> <?= esc_html__('Disable tags', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox" style="margin-top: 4px;"><?= esc_html__('Unregisters the Tags taxonomy for posts.', 'fromscratch') ?></p>
						</div>
					</td>
				</tr>
			</table>

			<hr class="fs-small">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Duplicate', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_duplicate_post]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_duplicate_post]" value="1" <?= checked($feat('enable_duplicate_post'), 1, false) ?>> <?= esc_html__('Allow duplication', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Shows a "Duplicate" row action for posts, pages, and custom post types.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>

			<hr class="fs-small">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Post expirator', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_post_expirator]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_post_expirator]" value="1" <?= checked($feat('enable_post_expirator'), 1, false) ?>> <?= esc_html__('Enable post expirator', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Adds an expiration date to posts, pages and custom post types.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>

			<hr class="fs-small">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('SEO', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_seo]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_seo]" value="1" <?= checked($feat('enable_seo'), 1, false) ?>> <?= esc_html__('SEO panel', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Adds a section to pages, posts and custom post types to enter SEO info.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>

			<hr class="fs-small">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('SVG support', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_svg]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_svg]" value="1" <?= checked($feat('enable_svg'), 1, false) ?>> <?= esc_html__('Allow SVG uploads', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Uploaded SVG files are automatically sanitized to remove potentially unsafe code.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>

			<hr class="fs-small">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Languages', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fromscratch_features[enable_languages]" value="0">
						<label><input type="checkbox" name="fromscratch_features[enable_languages]" id="fromscratch_features_enable_languages" value="1" <?= checked($feat('enable_languages'), 1, false) ?>> <?= esc_html__('Enable languages', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Adds a Languages tab here where you can manage content languages and set the default language.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<script>
			(function() {
				var blogs = document.getElementById('fromscratch_features_enable_blogs');
				var sub = document.getElementById('fs-feature-sub-blogs');
				if (!blogs || !sub) return;

				function toggle() {
					sub.style.display = blogs.checked ? '' : 'none';
				}
				blogs.addEventListener('change', toggle);
			})();
		</script>
	</div>
	<?php
}
