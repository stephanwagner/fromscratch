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

	if (!function_exists('fs_webp_supported')) {
		require_once get_template_directory() . '/inc/image-webp.php';
	}
	$webp_enabled_no_support = ($feat('enable_webp') === 1 && !fs_webp_supported());
?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>

		<?php if ($features_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php if ($webp_enabled_no_support) : ?>
			<div class="notice notice-warning is-dismissible">
				<p><strong><?= esc_html__('WebP conversion is enabled but no suitable image library was detected.', 'fromscratch') ?></strong></p>
				<p><?php
					echo wp_kses(
						sprintf(
							/* translators: 1: link to PHP GD manual, 2: link to PHP Imagick manual */
							__('Convert images to WebP requires the PHP %1$s extension (with WebP support) or the %2$s extension. Neither is available on this server. New uploads will not be converted to WebP until you install one of them.', 'fromscratch'),
							'<a href="' . esc_url('https://www.php.net/manual/en/book.image.php') . '" target="_blank" rel="noopener noreferrer">GD</a>',
							'<a href="' . esc_url('https://www.php.net/manual/en/book.imagick.php') . '" target="_blank" rel="noopener noreferrer">ImageMagick</a>'
						),
						['a' => ['href' => true, 'target' => true, 'rel' => true]]
					);
					?></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<form method="post" action="" class="fs-page-settings-form">
			<h2 class="title"><?= esc_html__('Features', 'fromscratch') ?></h2>
			<p class="description"><?= esc_html__('Enable the features your project needs.', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('All features are modular and can be toggled at any time to keep the theme lean and maintainable.', 'fromscratch') ?></p>

			<h3 style="margin-top: 24px;"><?= esc_html__('Content', 'fromscratch') ?></h3>

			<div class="fs-feature-group">

				<?php settings_fields(FS_THEME_OPTION_GROUP_FEATURES); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('Blogs', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_blogs]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_blogs]" id="fromscratch_features_enable_blogs" value="1" <?= checked($feat('enable_blogs'), 1, false) ?>> <?= esc_html__('Allow posts', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Shows the Posts menu in the admin and allows creating and editing blog posts.', 'fromscratch') ?></p>
							<div class="fs-feature-sub" id="fs-feature-sub-blogs" style="margin-top: 12px; <?= $feat('enable_blogs') !== 1 ? 'display:none;' : '' ?>">
								<input type="hidden" name="fromscratch_features[enable_remove_post_tags]" value="0">
								<label><input type="checkbox" name="fromscratch_features[enable_remove_post_tags]" value="1" <?= checked($feat('enable_remove_post_tags'), 1, false) ?>> <?= esc_html__('Disable tags', 'fromscratch') ?></label>
								<p class="description fs-indent-checkbox" style="margin-top: 4px;"><?= esc_html__('Unregisters the Tags taxonomy for posts.', 'fromscratch') ?></p>
							</div>
						</td>
					</tr>
				</table>

				<hr>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('Duplicate', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_duplicate_post]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_duplicate_post]" value="1" <?= checked($feat('enable_duplicate_post'), 1, false) ?>> <?= esc_html__('Allow duplication', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Shows a "Duplicate" row action for pages and posts.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('Post expirator', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_post_expirator]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_post_expirator]" value="1" <?= checked($feat('enable_post_expirator'), 1, false) ?>> <?= esc_html__('Enable post expirator', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Adds an expiration date to pages and posts.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('SEO', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_seo]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_seo]" value="1" <?= checked($feat('enable_seo'), 1, false) ?>> <?= esc_html__('Enable SEO panel', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Adds a section to pages and posts to enter SEO info.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('Languages', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_languages]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_languages]" id="fromscratch_features_enable_languages" value="1" <?= checked($feat('enable_languages'), 1, false) ?>> <?= esc_html__('Enable languages', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Enables built-in support for multiple content languages.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

			</div>

			<h3 style="margin-top: 32px;"><?= esc_html__('Media', 'fromscratch') ?></h3>

			<div class="fs-feature-group">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('Media folders', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_media_folders]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_media_folders]" value="1" <?= checked($feat('enable_media_folders'), 1, false) ?>> <?= esc_html__('Enable media folders', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Adds folders to the Media Library with a sidebar for organizing.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('SVG support', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_svg]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_svg]" value="1" <?= checked($feat('enable_svg'), 1, false) ?>> <?= esc_html__('Allow SVG uploads', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Uploaded SVG files are automatically sanitized to remove potentially unsafe code.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

				<hr>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('WebP images', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_webp]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_webp]" id="fromscratch_features_enable_webp" value="1" <?= checked($feat('enable_webp'), 1, false) ?>> <?= esc_html__('Convert images to WebP', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Convert generated JPEG and PNG image to WebP. Requires GD or Imagick with WebP support.', 'fromscratch') ?></p>
							<div class="fs-feature-sub" id="fs-feature-sub-webp" style="margin-top: 12px; <?= $feat('enable_webp') !== 1 ? 'display:none;' : '' ?>">
								<input type="hidden" name="fromscratch_features[enable_webp_convert_original]" value="0">
								<label><input type="checkbox" name="fromscratch_features[enable_webp_convert_original]" value="1" <?= checked($feat('enable_webp_convert_original'), 1, false) ?>> <?= esc_html__('Also convert the original image', 'fromscratch') ?></label>
								<p class="description fs-indent-checkbox"><?= esc_html__('By default, only resized versions of an image are converted. The original upload remains unchanged.', 'fromscratch') ?></p>
							</div>
						</td>
					</tr>
				</table>

			</div>

			<h3 style="margin-top: 32px;"><?= esc_html__('Security', 'fromscratch') ?></h3>

			<div class="fs-feature-group">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row" class="form-table-checkbox-label"><?= esc_html__('IP Blocking', 'fromscratch') ?></th>
						<td>
							<input type="hidden" name="fromscratch_features[enable_blocked_ips]" value="0">
							<label><input type="checkbox" name="fromscratch_features[enable_blocked_ips]" value="1" <?= checked($feat('enable_blocked_ips'), 1, false) ?>> <?= esc_html__('Enable IP blocking', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Allows blocking specific IP addresses and detects suspicious login attempts.', 'fromscratch') ?></p>
						</td>
					</tr>
				</table>

			</div>

			<div class="fs-submit-row">
				<button type="submit" class="button button-primary"><?= esc_html__('Save Changes') ?></button>
			</div>
		</form>
		<script>
			(function() {
				function bindToggle(mainId, subId) {
					var main = document.getElementById(mainId);
					var sub = document.getElementById(subId);
					if (!main || !sub) return;

					function toggle() {
						sub.style.display = main.checked ? '' : 'none';
					}
					main.addEventListener('change', toggle);
				}
				bindToggle('fromscratch_features_enable_blogs', 'fs-feature-sub-blogs');
				bindToggle('fromscratch_features_enable_webp', 'fs-feature-sub-webp');
			})();
		</script>
	</div>
<?php
}
