<?php

defined('ABSPATH') || exit;

$fs_developer_tab = 'languages';
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
		'fs_render_developer_languages',
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
	if (empty($_POST['option_page']) || $_POST['option_page'] !== FS_THEME_OPTION_GROUP_LANGUAGES || empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], FS_THEME_OPTION_GROUP_LANGUAGES . '-options')) {
		return;
	}
	$value = isset($_POST['fs_theme_languages']) && is_array($_POST['fs_theme_languages']) ? $_POST['fs_theme_languages'] : [];
	$sanitized = function_exists('fs_sanitize_theme_languages') ? fs_sanitize_theme_languages($value) : ['list' => [], 'default' => '', 'use_url_prefix' => true, 'prefix_default' => false, 'no_translation' => 'disabled'];
	update_option('fs_theme_languages', $sanitized);
	flush_rewrite_rules(true);
	set_transient('fromscratch_languages_saved', '1', 30);
	wp_safe_redirect(admin_url('options-general.php?page=fs-developer-languages'));
	exit;
}, 1);

function fs_render_developer_languages(): void
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'fromscratch'));
	}

	$languages_saved = get_transient('fromscratch_languages_saved');
	if ($languages_saved !== false) {
		delete_transient('fromscratch_languages_saved');
	}

	$lang_data = get_option('fs_theme_languages', ['list' => [], 'default' => '', 'use_url_prefix' => true, 'prefix_default' => false, 'no_translation' => 'disabled']);
	$lang_list = isset($lang_data['list']) && is_array($lang_data['list']) ? $lang_data['list'] : [];
	$lang_default = isset($lang_data['default']) ? (string) $lang_data['default'] : '';
	$lang_use_url_prefix = isset($lang_data['use_url_prefix']) ? (bool) $lang_data['use_url_prefix'] : true;
	$lang_prefix_default = !empty($lang_data['prefix_default']);
	$lang_no_translation = isset($lang_data['no_translation']) && in_array($lang_data['no_translation'], ['hide', 'disabled', 'home'], true) ? $lang_data['no_translation'] : 'disabled';
	if ($lang_default === '' && !empty($lang_list)) {
		$lang_default = $lang_list[0]['id'] ?? '';
	}
	?>
	<div class="wrap">
		<h1><?= esc_html(__('Developer settings', 'fromscratch')) ?></h1>
		<?php if ($languages_saved !== false) : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?= esc_html(__('Settings saved.', 'fromscratch')) ?></strong></p>
			</div>
		<?php endif; ?>

		<?php fs_developer_settings_render_nav(); ?>

		<h2 class="title"><?= esc_html__('Languages', 'fromscratch') ?></h2>
		<p class="description"><?= esc_html__('Configure the languages available for your content and manage how they are used across the site.', 'fromscratch') ?></p>
		<form method="post" action="" class="page-settings-form" id="fs-languages-form">
			<?php settings_fields(FS_THEME_OPTION_GROUP_LANGUAGES); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?= esc_html__('Default language', 'fromscratch') ?></th>
					<td>
						<?php if (!empty($lang_list)) : ?>
							<select name="fs_theme_languages[default]" id="fs_theme_languages_default" class="regular-text">
								<?php foreach ($lang_list as $l) : ?>
									<option value="<?= esc_attr($l['id']) ?>" <?= selected($lang_default, $l['id'], false) ?>><?= esc_html($l['nameEnglish'] !== '' ? $l['nameEnglish'] : $l['id']) ?></option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input type="hidden" name="fs_theme_languages[default]" value="">
							<p class="description" style="margin: 0; color: #a7aaad; font-style: italic;"><?= esc_html__('Add at least one language in the list below to set a default language.', 'fromscratch') ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?= esc_html__('URL prefix', 'fromscratch') ?></th>
					<td>
						<input type="hidden" name="fs_theme_languages[use_url_prefix]" value="0">
						<label><input type="checkbox" name="fs_theme_languages[use_url_prefix]" id="fs_use_url_prefix" value="1" <?= checked($lang_use_url_prefix, true, false) ?>> <?= esc_html__('Use language prefix in URL', 'fromscratch') ?></label>
						<p class="description fs-indent-checkbox"><?= esc_html__('Adds a language prefix to URLs (e.g. /de/ueber-uns).', 'fromscratch') ?></p>
						<div id="fs-prefix-default-wrap" class="fs-url-prefix-sub" style="margin-top: 12px; <?= $lang_use_url_prefix ? '' : 'display:none;' ?>">
							<input type="hidden" name="fs_theme_languages[prefix_default]" value="0">
							<label><input type="checkbox" name="fs_theme_languages[prefix_default]" id="fs_prefix_default" value="1" <?= checked($lang_prefix_default, true, false) ?>> <?= esc_html__('Prefix default language in URL', 'fromscratch') ?></label>
							<p class="description fs-indent-checkbox"><?= esc_html__('Controls whether the default language also uses a URL prefix.', 'fromscratch') ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?= esc_html__('Language switcher', 'fromscratch') ?></th>
					<td>
						<div style="margin-bottom: 24px;">
							<div style="display: flex; align-items: center; gap: 8px">
								<input type="text" id="fs-language-toggler-shortcode" readonly class="regular-text code fs-code-small" value="[fs_language_switcher]" />
								<button type="button" class="button" data-fs-copy-from-source="fs-language-toggler-shortcode" data-fs-copy-feedback-text="<?= esc_attr__('Copied', 'fromscratch') ?>"><?= esc_html__('Copy', 'fromscratch') ?></button>
							</div>
							<p class="description"><?= esc_html__('To display a language switcher in your theme, use the shortcode [fs_language_switcher].', 'fromscratch') ?></p>
						</div>
						<select name="fs_theme_languages[no_translation]" id="fs_no_translation" class="regular-text">
							<option value="hide" <?= selected($lang_no_translation, 'hide', false) ?>><?= esc_html__('Language will not be shown in language toggler', 'fromscratch') ?></option>
							<option value="disabled" <?= selected($lang_no_translation, 'disabled', false) ?>><?= esc_html__('Language link is disabled', 'fromscratch') ?></option>
							<option value="home" <?= selected($lang_no_translation, 'home', false) ?>><?= esc_html__('Language link goes to language homepage (or site home)', 'fromscratch') ?></option>
						</select>
						<p class="description"><?= esc_html__('Defines how the language switcher behaves when the current page is not available in another language.', 'fromscratch') ?></p>
					</td>
				</tr>
			</table>
			<h3 class="title" style="margin-top: 24px;"><?= esc_html__('Available languages', 'fromscratch') ?></h3>
			<p class="description"><?= esc_html__('Add and manage the languages available for your site’s content.', 'fromscratch') ?></p>
			<p class="description"><?= esc_html__('Language codes follow ISO-639-1 (e.g. en, de, fr).', 'fromscratch') ?></p>
			<table class="widefat striped" id="fs-languages-table" style="width: auto; margin-top: 16px;">
				<thead>
					<tr>
						<th style="width: 100px;"><?= esc_html__('Code', 'fromscratch') ?></th>
						<th><?= esc_html__('Name', 'fromscratch') ?></th>
						<th><?= esc_html__('Native name', 'fromscratch') ?></th>
						<th style="width: 80px;"></th>
					</tr>
				</thead>
				<tbody id="fs-languages-tbody">
					<?php foreach ($lang_list as $i => $l) : ?>
						<tr class="fs-language-row">
							<td><input type="text" name="fs_theme_languages[list][<?= (int) $i ?>][id]" value="<?= esc_attr($l['id']) ?>" class="small-text" placeholder="en" maxlength="2" required pattern="[a-z]{2}" /></td>
							<td><input type="text" name="fs_theme_languages[list][<?= (int) $i ?>][nameEnglish]" value="<?= esc_attr($l['nameEnglish']) ?>" class="regular-text" required style="width: 140px;"></td>
							<td><input type="text" name="fs_theme_languages[list][<?= (int) $i ?>][nameOriginalLanguage]" value="<?= esc_attr($l['nameOriginalLanguage']) ?>" class="regular-text" required style="width: 140px;"></td>
							<td><button type="button" class="button button-small fs-remove-language" aria-label="<?= esc_attr__('Remove', 'fromscratch') ?>"><?= esc_html__('Remove', 'fromscratch') ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top: 12px;">
				<button type="button" class="button" id="fs-add-language"><?= esc_html__('Add language', 'fromscratch') ?></button>
			</p>
			<script>
			(function() {
				var form = document.getElementById('fs-languages-form');
				var tbody = document.getElementById('fs-languages-tbody');
				var addBtn = document.getElementById('fs-add-language');
				var usePrefix = document.getElementById('fs_use_url_prefix');
				var prefixWrap = document.getElementById('fs-prefix-default-wrap');
				var prefixDefault = document.getElementById('fs_prefix_default');
				function togglePrefixDefault() {
					var on = usePrefix && usePrefix.checked;
					if (prefixWrap) prefixWrap.style.display = on ? '' : 'none';
					if (prefixDefault) prefixDefault.disabled = !on;
				}
				if (usePrefix) usePrefix.addEventListener('change', togglePrefixDefault);
				togglePrefixDefault();

				if (!form || !tbody || !addBtn) return;
				var rowIndex = <?= (int) count($lang_list) ?>;
				addBtn.addEventListener('click', function() {
					var tr = document.createElement('tr');
					tr.className = 'fs-language-row';
					tr.innerHTML = '<td><input type="text" name="fs_theme_languages[list][' + rowIndex + '][id]" value="" class="small-text" placeholder="en" maxlength="2" required pattern="[a-z]{2}"></td>' +
						'<td><input type="text" name="fs_theme_languages[list][' + rowIndex + '][nameEnglish]" value="" class="regular-text" required style="width: 140px;"></td>' +
						'<td><input type="text" name="fs_theme_languages[list][' + rowIndex + '][nameOriginalLanguage]" value="" class="regular-text" required style="width: 140px;"></td>' +
						'<td><button type="button" class="button button-small fs-remove-language" aria-label="<?= esc_js(__('Remove', 'fromscratch')) ?>"><?= esc_js(__('Remove', 'fromscratch')) ?></button></td>';
					tbody.appendChild(tr);
					rowIndex++;
				});
				tbody.addEventListener('click', function(e) {
					if (e.target.classList.contains('fs-remove-language')) {
						e.target.closest('tr').remove();
					}
				});
			})();
			</script>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
