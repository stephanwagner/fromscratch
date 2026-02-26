<?php

defined('ABSPATH') || exit;

/**
 * Theme settings: handle "Bump" asset version
 */
add_action('load-settings_page_custom-theme-settings', function () {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (empty($_GET['fromscratch_bump']) || empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fromscratch_bump_asset_version')) {
		return;
	}
	$current = get_option('fromscratch_asset_version', '1');
	$next = is_numeric($current) ? (string) ((int) $current + 1) : '2';
	update_option('fromscratch_asset_version', $next);
	wp_safe_redirect(admin_url('options-general.php?page=custom-theme-settings'));
	exit;
});

/**
 * Register asset version option (saved with main form)
 */
add_action('admin_init', function () {
	register_setting('section', 'fromscratch_asset_version', [
		'type' => 'string',
		'default' => '1',
		'sanitize_callback' => 'sanitize_text_field',
	]);
}, 5);

/**
 * Variables
 */
function theme_settings_page()
{
	$asset_version = get_option('fromscratch_asset_version', '1');
	$bump_url = wp_nonce_url(
		add_query_arg('fromscratch_bump', '1', admin_url('options-general.php?page=custom-theme-settings')),
		'fromscratch_bump_asset_version'
	);
?>
	<div class="wrap">
		<h1><?= esc_html(fs_config_variables('title_page')) ?></h1>

		<form method="post" action="options.php" class="page-settings-form" id="fromscratch-settings-form">
			<?php settings_fields('section'); ?>

			<nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
				<button type="button" class="nav-tab nav-tab-active" data-fromscratch-tab="general" role="tab" aria-selected="true"><?= esc_html(fs_t('SETTINGS_TAB_GENERAL')) ?></button>
				<button type="button" class="nav-tab" data-fromscratch-tab="texte" role="tab"><?= esc_html(fs_t('SETTINGS_TAB_TEXTE')) ?></button>
				<button type="button" class="nav-tab" data-fromscratch-tab="design" role="tab"><?= esc_html(fs_t('SETTINGS_TAB_DESIGN')) ?></button>
			</nav>

			<div id="fromscratch-panel-general" class="fromscratch-settings-panel" role="tabpanel">
				<h2><?= esc_html(fs_t('SETTINGS_TAB_GENERAL')) ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fromscratch_asset_version"><?= esc_html(fs_t('SETTINGS_ASSET_VERSION')) ?></label>
						</th>
						<td>
							<input type="text" name="fromscratch_asset_version" id="fromscratch_asset_version" value="<?= esc_attr($asset_version) ?>" class="small-text" style="width: 5em;">
							<p class="description"><?= esc_html(fs_t('SETTINGS_ASSET_VERSION_DESCRIPTION')) ?></p>
							<p style="margin-top: 8px;">
								<a href="<?= esc_url($bump_url) ?>" class="button"><?= esc_html(fs_t('SETTINGS_BUMP_VERSION')) ?></a>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div id="fromscratch-panel-texte" class="fromscratch-settings-panel" role="tabpanel" hidden>
				<h2><?= esc_html(fs_t('SETTINGS_TAB_TEXTE')) ?></h2>
				<?php
				foreach (fs_config_variables('variables.sections') as $section) {
					do_settings_sections('theme_variables_' . $section['id']);
				}
				?>
			</div>

			<div id="fromscratch-panel-design" class="fromscratch-settings-panel" role="tabpanel" hidden>
				<h2><?= esc_html(fs_t('SETTINGS_TAB_DESIGN')) ?></h2>
				<table class="widefat striped">
                <thead>
                  <tr>
				  	<th style="padding: 8px 10px; line-height: 1.4em; font-weight: bold"><?= fs_t('Variable') ?></th>
                    <th style="padding: 8px 10px; line-height: 1.4em; font-weight: bold"><?= fs_t('Titel') ?></th>
                    <th style="padding: 8px 10px; line-height: 1.4em; font-weight: bold"><?= fs_t('Wert') ?></th>
                    <th style="padding: 8px 10px; line-height: 1.4em; font-weight: bold"></th>
                  </tr>
                </thead>
                <tbody>

                  <tr>
                    <td><code>--color-gray-200</code></td>
                    <td>
                      <input
                        type="text"
                        name="pages[homepage][title]"
                        value="Grau 200"
                        class="regular-text" style="width: 180px">
                    </td>
                    <td>
                      <input
                        type="text"
                        name="pages[homepage][slug]"
                        value="#444444"
                        class="code" style="width: 180px">
                    </td>
					<td>
						<div class="button">
							<span class="dashicons dashicons-trash"></span>
						</div>
					</td>
                  </tr>
				</table>
			</div>

			<p class="submit"><?php submit_button(); ?></p>
		</form>

		<style>
			.fromscratch-settings-panel { margin-top: 16px; }
			.fromscratch-settings-panel h2 { padding-top: 20px; position: relative; }
			.fromscratch-settings-panel h2::after {
				content: ''; display: block; position: absolute; height: 2px; top: -1px; left: 0;
				width: 100%; max-width: 620px; background: #d0d0d0;
			}
			input.settings-page-textfield, textarea.settings-page-textfield {
				border-radius: 2px; border-color: #ccc; color: #373737; padding: 1px 8px;
			}
			textarea.settings-page-textfield { resize: vertical; }
			.page-settings-form .form-table .widefat th { padding: 8px 10px; }
			.page-settings-description { color: #aaa; font-size: 12px; padding: 4px 0 0 4px; }
		</style>
		<script>
			(function() {
				var form = document.getElementById('fromscratch-settings-form');
				if (!form) return;
				var tabs = form.querySelectorAll('[data-fromscratch-tab]');
				var panels = form.querySelectorAll('.fromscratch-settings-panel');
				tabs.forEach(function(tab) {
					tab.addEventListener('click', function() {
						var id = this.getAttribute('data-fromscratch-tab');
						tabs.forEach(function(t) { t.classList.toggle('nav-tab-active', t === tab); t.setAttribute('aria-selected', t === tab); });
						panels.forEach(function(p) {
							var show = p.id === 'fromscratch-panel-' + id;
							p.hidden = !show;
						});
					});
				});
			})();
		</script>
	</div>
<?php
}

function display_custom_info_field($variable, $variableId, $languageId = null)
{
	if ($languageId) {
		echo '<div class="page-settings-language-container page-settings-language-container-' . $languageId . '">';
	}

	switch ($variable['type']) {
		case 'textfield':
			echo '<input class="settings-page-textfield" type="text" name="' . $variableId . '" value="' . get_option($variableId) . '" style="width: ' . $variable['width'] . 'px">';
			echo '<div style="color: #999; font-size: 12px; margin: 4px 0 0 4px; font-family: monospace;">' . $variableId . '</div>';
			break;
		case 'textarea':
			echo '<textarea class="settings-page-textfield" name="' . $variableId . '" rows="' . $variable['rows'] . '" style="width: ' . $variable['width'] . 'px">' . get_option($variableId) . '</textarea>';
			echo '<div style="color: #999; font-size: 12px; margin: 4px 0 0 4px; font-family: monospace;">' . $variableId . '</div>';
			break;
	}

	if (!empty($variable['description'])) {
		echo '<div class="page-settings-description">' . $variable['description'] . '</div>';
	}

	if ($languageId) {
		echo '</div>';
	}
}

function display_custom_info_fields()
{
	foreach (fs_config_variables('variables.sections') as $section) {
		add_settings_section('section', $section['title'], null, 'theme_variables_' . $section['id']);

		foreach ($section['variables'] as $variable) {
			$variableId = 'theme_variables_' . $section['id'] . '_' . $variable['id'];

			if (!empty($variable['translate'])) {
				foreach (fs_config_variables('languages') as $language) {
					$variableIdLang = $variableId . '_' . $language['id'];
					add_settings_field($variableIdLang, $variable['title'], function () use ($variable, $variableIdLang, $language) {
						display_custom_info_field($variable, $variableIdLang, $language['id']);
					}, 'theme_variables_' . $section['id'], 'section');
					register_setting('section', $variableIdLang);
				}
			} else {
				add_settings_field($variableId, $variable['title'], function () use ($variable, $variableId) {
					display_custom_info_field($variable, $variableId);
				}, 'theme_variables_' . $section['id'], 'section');
				register_setting('section', $variableId);
			}
		}
	}
}
add_action('admin_init', 'display_custom_info_fields');

function add_custom_info_menu_item()
{
	add_options_page(
		fs_config_variables('title_page'),
		fs_config_variables('title_menu'),
		'manage_options',
		'custom-theme-settings',
		'theme_settings_page'
	);
}
add_action('admin_menu', 'add_custom_info_menu_item');
