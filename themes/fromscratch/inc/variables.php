<?php

/**
 * Variables
 */
function theme_settings_page()
{
	global $fs_config_variables;
?>
	<div class="wrap">
		
		<h1><?= $fs_config_variables['title_page'] ?></h1>

		<form method="post" action="options.php" class="page-settings-form">
			<div class="settings-page-tab-wrapper">
				<div class="settings-page-tab-container">
					<div class="settings-page-tab">
						Allgemein
					</div>
					<div class="settings-page-tab">
						Texte
					</div>
					<div class="settings-page-tab">
						Design
					</div>
				</div>
			</div>
		
			<div style="margin: 24px 0 32px;">
				<h1>Design</h1>

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
		
			<div style="margin: 24px 0 32px;">
				<?php
				if (sizeof($fs_config_variables['languages']) > 1) {
					foreach ($fs_config_variables['languages'] as $language) {
						echo '<div onclick="changeSettingsPageLanguage(\'' . $language['id'] . '\')" class="settings-page-language-button settings-page-language-button-' . $language['id'] . ' button' . ($language['id'] == 'de' ? ' button-primary' : '') . '" style="margin-right: 8px">' . $language['nameEnglish'] . '</div>';
					}
				}
				?>
				<script>
					function changeSettingsPageLanguage(id) {
						jQuery('.settings-page-language-button').removeClass('button-primary');
						jQuery('.settings-page-language-button-' + id).addClass('button-primary');
						jQuery('.page-settings-language-container').parents('tr').css({
							display: 'none'
						});
						jQuery('.page-settings-language-container-' + id).parents('tr').css({
							display: 'table-row'
						});
					}

					jQuery(function() {
						changeSettingsPageLanguage('de');
						jQuery('.page-settings-form').css({
							display: 'block'
						});
					});
				</script>
				<style>
					input.settings-page-textfield,
					textarea.settings-page-textfield {
						border-radius: 2px;
						border-color: #ccc;
						color: #373737;
						padding: 1px 8px;
					}

					textarea.settings-page-textfield {
						resize: vertical;
					}

					.page-settings-form .form-table .widefat th {
						padding: 8px 10px;
					}

					.page-settings-form {
						display: none;
					}

					.page-settings-form h2 {
						padding-top: 20px;
						position: relative;
					}

					.page-settings-form h2::after {
						content: '';
						display: block;
						position: absolute;
						height: 2px;
						top: -1px;
						left: 0;
						width: 100%;
						max-width: 620px;
						background: #d0d0d0;
					}

					.page-settings-description {
						color: #aaa;
						font-size: 12px;
						padding: 4px 0 0 4px;
					}
				</style>
			</div>
			<?php
			foreach ($fs_config_variables['variables']['sections'] as $section) {
				settings_fields('section');
				do_settings_sections('theme_variables_' . $section['id']);
			}
			submit_button();
			?>
		</form>
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
	global $fs_config_variables;

	foreach ($fs_config_variables['variables']['sections'] as $section) {
		add_settings_section('section', $section['title'], null, 'theme_variables_' . $section['id']);

		foreach ($section['variables'] as $variable) {
			$variableId = 'theme_variables_' . $section['id'] . '_' . $variable['id'];

			if (!empty($variable['translate'])) {
				foreach ($fs_config_variables['languages'] as $language) {
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
	global $fs_config_variables;

	add_options_page(
		$fs_config_variables['title_page'],
		$fs_config_variables['title_menu'],
		'manage_options',
		'custom-theme-settings',
		'theme_settings_page'
	);
}
add_action('admin_menu', 'add_custom_info_menu_item');
