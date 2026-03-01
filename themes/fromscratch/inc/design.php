<?php

defined('ABSPATH') || exit;

/**
 * Design variables: overridable in Settings → Theme → Design.
 * Values come from config/theme.php design.sections; overrides are stored in fromscratch_design option.
 */

/**
 * Sanitize a string for use as CSS custom property value.
 *
 * @param string $value Raw value.
 * @return string
 */
function fs_sanitize_css_custom_property_value(string $value): string
{
	$value = preg_replace('/[^\w\s#.,()%\-\/_\\:;"\']/', '', $value);
	$value = str_replace(["\r", "\n", "\t", '<', '>'], '', $value);
	return substr($value, 0, 500);
}

/**
 * Get all design variables as a flat list from config.
 *
 * @return array<int, array{id: string, title: string, default: string, type: string}>
 */
function fs_get_design_variables_list(): array
{
	$sections = fs_config('design.sections');
	if (!is_array($sections)) {
		return [];
	}
	$list = [];
	foreach ($sections as $section) {
		$variables = [];

		if (!empty($section['from']) && $section['from'] === 'theme_colors') {
			$theme_colors = fs_config('theme_colors');
			if (is_array($theme_colors)) {
				foreach ($theme_colors as $tc) {
					if (!empty($tc['slug']) && isset($tc['color'])) {
						$variables[] = [
							'id' => 'color-' . (string) $tc['slug'],
							'title' => isset($tc['name']) ? (string) $tc['name'] : (string) $tc['slug'],
							'default' => (string) $tc['color'],
							'type' => 'color',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_gradients') {
			$theme_gradients = fs_config('theme_gradients');
			if (is_array($theme_gradients)) {
				foreach ($theme_gradients as $tg) {
					if (!empty($tg['slug']) && isset($tg['gradient'])) {
						$variables[] = [
							'id' => 'gradient-' . (string) $tg['slug'],
							'title' => isset($tg['name']) ? (string) $tg['name'] : (string) $tg['slug'],
							'default' => (string) $tg['gradient'],
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_font_sizes') {
			$theme_font_sizes = fs_config('theme_font_sizes');
			if (is_array($theme_font_sizes)) {
				foreach ($theme_font_sizes as $tfs) {
					if (!empty($tfs['slug']) && isset($tfs['size'])) {
						$variables[] = [
							'id' => 'font-size-' . (string) $tfs['slug'],
							'title' => isset($tfs['name']) ? (string) $tfs['name'] : (string) $tfs['slug'],
							'default' => (string) $tfs['size'] . 'px',
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['variables']) && is_array($section['variables'])) {
			foreach ($section['variables'] as $v) {
				if (!empty($v['id']) && isset($v['default'])) {
					$variables[] = [
						'id' => (string) $v['id'],
						'title' => isset($v['title']) ? (string) $v['title'] : $v['id'],
						'default' => (string) $v['default'],
						'type' => isset($v['type']) && in_array($v['type'], ['color', 'text'], true) ? $v['type'] : 'text',
					];
				}
			}
		}

		foreach ($variables as $v) {
			$list[] = $v;
		}
	}
	return $list;
}

/**
 * Get design sections with variables resolved (for Design tab UI).
 *
 * @return array<string, array{title: string, variables: array}>
 */
function fs_get_design_sections_resolved(): array
{
	$sections = fs_config('design.sections');
	if (!is_array($sections)) {
		return [];
	}
	$resolved = [];
	foreach ($sections as $section_id => $section) {
		$variables = [];

		if (!empty($section['from']) && $section['from'] === 'theme_colors') {
			$theme_colors = fs_config('theme_colors');
			if (is_array($theme_colors)) {
				foreach ($theme_colors as $tc) {
					if (!empty($tc['slug']) && isset($tc['color'])) {
						$variables[] = [
							'id' => 'color-' . (string) $tc['slug'],
							'title' => isset($tc['name']) ? (string) $tc['name'] : (string) $tc['slug'],
							'default' => (string) $tc['color'],
							'type' => 'color',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_gradients') {
			$theme_gradients = fs_config('theme_gradients');
			if (is_array($theme_gradients)) {
				foreach ($theme_gradients as $tg) {
					if (!empty($tg['slug']) && isset($tg['gradient'])) {
						$variables[] = [
							'id' => 'gradient-' . (string) $tg['slug'],
							'title' => isset($tg['name']) ? (string) $tg['name'] : (string) $tg['slug'],
							'default' => (string) $tg['gradient'],
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['from']) && $section['from'] === 'theme_font_sizes') {
			$theme_font_sizes = fs_config('theme_font_sizes');
			if (is_array($theme_font_sizes)) {
				foreach ($theme_font_sizes as $tfs) {
					if (!empty($tfs['slug']) && isset($tfs['size'])) {
						$variables[] = [
							'id' => 'font-size-' . (string) $tfs['slug'],
							'title' => isset($tfs['name']) ? (string) $tfs['name'] : (string) $tfs['slug'],
							'default' => (string) $tfs['size'] . 'px',
							'type' => 'text',
						];
					}
				}
			}
		}

		if (!empty($section['variables']) && is_array($section['variables'])) {
			foreach ($section['variables'] as $v) {
				if (!empty($v['id']) && isset($v['default'])) {
					$variables[] = [
						'id' => (string) $v['id'],
						'title' => isset($v['title']) ? (string) $v['title'] : $v['id'],
						'default' => (string) $v['default'],
						'type' => isset($v['type']) && in_array($v['type'], ['color', 'text'], true) ? $v['type'] : 'text',
					];
				}
			}
		}

		if ($variables !== []) {
			$resolved[$section_id] = [
				'title' => isset($section['title']) ? (string) $section['title'] : $section_id,
				'variables' => $variables,
			];
		}
	}
	return $resolved;
}

/**
 * Get override value for a design variable. Empty when using default.
 *
 * @param string $id Variable id.
 * @return string
 */
function fs_design_variable_override(string $id): string
{
	$saved = get_option('fromscratch_design', []);
	if (is_array($saved) && array_key_exists($id, $saved) && $saved[$id] !== '') {
		return (string) $saved[$id];
	}
	return '';
}

/**
 * Get effective value for a design variable (override or default).
 *
 * @param string $id Variable id.
 * @return string
 */
function fs_design_variable_value(string $id): string
{
	$override = fs_design_variable_override($id);
	if ($override !== '') {
		return $override;
	}
	foreach (fs_get_design_variables_list() as $v) {
		if ($v['id'] === $id) {
			return $v['default'];
		}
	}
	return '';
}

/**
 * Sanitize design variables on save.
 *
 * @param array|mixed $input Posted values.
 * @return array<string, string>
 */
function fs_sanitize_design_variables($input): array
{
	$vars = fs_get_design_variables_list();
	$by_id = [];
	foreach ($vars as $v) {
		$by_id[$v['id']] = $v;
	}
	$result = [];
	$input = is_array($input) ? $input : [];
	foreach ($by_id as $id => $def) {
		$val = isset($input[$id]) ? $input[$id] : '';
		$val = is_string($val) ? trim($val) : '';
		if ($val === '') {
			continue;
		}
		$result[$id] = sanitize_text_field($val);
	}
	return $result;
}

/**
 * Output :root { --var: value; } for design variables.
 */
function fs_output_design_css(): void
{
	$vars = fs_get_design_variables_list();
	if ($vars === []) {
		return;
	}
	$lines = [];
	foreach ($vars as $v) {
		$value = fs_design_variable_value($v['id']);
		$value = fs_sanitize_css_custom_property_value($value);
		$lines[] = '  --' . $v['id'] . ': ' . $value . ';';
	}
	if ($lines === []) {
		return;
	}
	echo "\n<style id=\"fromscratch-design-vars\">\n:root {\n" . implode("\n", $lines) . "\n}\n</style>\n";
}

/**
 * Output custom CSS from Settings → Theme → CSS. Printed after design variables so custom CSS can override or use var(--*).
 */
function fs_output_custom_css(): void
{
	$css = get_option('fromscratch_custom_css', '');
	if ($css === '') {
		return;
	}
	$css = wp_strip_all_tags($css);
	$css = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $css);
	$css = str_ireplace('</style>', '', $css);
	if ($css === '') {
		return;
	}
	echo "\n<style id=\"fromscratch-custom-css\">\n" . $css . "\n</style>\n";
}
