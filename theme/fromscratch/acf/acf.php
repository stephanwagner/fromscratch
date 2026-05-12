<?php

// Import block filters
include __DIR__ . '/block-filters.php';

// Import blocks
$configBlocks = include __DIR__ . '/blocks.php';

// Register ACF blocks
function fs_acf_init_core()
{
	global $configBlocks;
	if (!empty($configBlocks) && function_exists('acf_register_block') && function_exists('acf_add_local_field_group')) {
		foreach ($configBlocks as $acfBlock) {
			acf_register_block([
				'name' => $acfBlock['name'],
				'title' => $acfBlock['title'],
				'description' => $acfBlock['description'] ?? '',
				'render_callback' => 'fs_acf_block_render_callback',
				'category' => 'design',
				'icon' => $acfBlock['icon'],
				'keywords' => $acfBlock['keywords'],
				'supports' => [
					'align' => !empty($acfBlock['supports']['align']) ? $acfBlock['supports']['align'] : false,
					'multiple' => isset($acfBlock['supports']['multiple']) ? $acfBlock['supports']['multiple'] : true,
				],
				'parent' => !empty($acfBlock['parent']) ? $acfBlock['parent'] : null,
				'api_version' => 3,
				'acf_block_version' => 3,
			]);
		}
	}
}
add_action('acf/init', 'fs_acf_init_core');

// Render callback for ACF blocks
function fs_acf_block_render_callback($block)
{
	$slug = str_replace('acf/', '', $block['name']);
	if (file_exists(get_theme_file_path("/acf/blocks/{$slug}/{$slug}.php"))) {
		include(get_theme_file_path("/acf/blocks/{$slug}/{$slug}.php"));
	}
}

// Customize WYSIWYG Toolbar
// https://www.advancedcustomfields.com/resources/customize-the-wysiwyg-toolbars
function fs_acf_toolbars($toolbars)
{
	$toolbars['Bold Italic Underline'] = [];
	$toolbars['Bold Italic Underline'][1] = ['bold', 'italic', 'underline'];
	$toolbars['Only Bold'] = [];
	$toolbars['Only Bold'][1] = ['bold'];
	return $toolbars;
}
add_filter('acf/fields/wysiwyg/toolbars', 'fs_acf_toolbars');

// Add ACF Options Page
function fs_acf_add_options_page()
{
	if (function_exists('acf_add_options_page')) {
		$args = array(
			'page_title' => 'Theme-Config',
			'capability' => 'edit_posts',
			'menu_slug' => 'theme-settings',
			'redirect' => false,
			'icon_url' => 'dashicons-admin-settings',
			'update_button' => 'Speichern'
		);
		acf_add_options_page($args);
	}
}
add_action('acf/init', 'fs_acf_add_options_page');

// Remove core blocks
add_filter('allowed_block_types_all', function ($allowed_blocks, $editor_context) {

    $blocked = [
        'core/accordion',
        'core/accordion-item',
        'core/accordion-heading',
        'core/accordion-panel',
    ];

    // If WP already passed all registered blocks as true
    if ($allowed_blocks === true) {
        $allowed_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $allowed_blocks = array_keys($allowed_blocks);
    }

    return array_values(array_diff($allowed_blocks, $blocked));
}, 10, 2);