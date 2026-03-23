<?php

defined('ABSPATH') || exit;

/**
 * Register custom post types from config/cpt.php.
 * Registered CPTs are included in fs_theme_post_types() (theme-setup.php) and thus in SEO, post expirator, duplicate, etc.
 */

/**
 * Register all CPTs defined in config/cpt.php.
 *
 * @return void
 */
function fs_register_cpts(): void
{
	$cpts = fs_config_cpt('cpts');
	if (!is_array($cpts) || $cpts === []) {
		return;
	}

	$defaults = [
		'public'            => true,
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_in_rest'      => true,
		'supports'          => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
		'capability_type'   => 'post',
		'map_meta_cap'      => true,
		'has_archive'       => false,
		'rewrite'           => true,
		'query_var'         => true,
		'menu_position'     => 5,
	];

	foreach ($cpts as $post_type => $args) {
		if (!is_string($post_type) || $post_type === '' || !is_array($args)) {
			continue;
		}
		$args = array_merge($defaults, $args);
		// Convenience config: has_categories => true adds built-in category taxonomy.
		$has_categories = !empty($args['has_categories']);
		unset($args['has_categories']);
		if ($has_categories) {
			$taxonomies = isset($args['taxonomies']) && is_array($args['taxonomies']) ? $args['taxonomies'] : [];
			if (!in_array('category', $taxonomies, true)) {
				$taxonomies[] = 'category';
			}
			$args['taxonomies'] = $taxonomies;
		}
		// Block editor needs custom-fields support to expose/save post meta (e.g. SEO panel).
		if (isset($args['supports']) && is_array($args['supports']) && !in_array('custom-fields', $args['supports'], true)) {
			$args['supports'][] = 'custom-fields';
		}
		// Ensure labels exist and derive missing labels from configured name/singular_name.
		$provided_labels = isset($args['labels']) && is_array($args['labels']) ? $args['labels'] : [];
		$args['labels'] = array_merge(fs_cpt_default_labels($post_type, $provided_labels), $provided_labels);
		// Support inline SVG menu icons in config; allow "icon" alias; always ensure fallback icon.
		$menu_icon_value = $args['menu_icon'] ?? ($args['icon'] ?? null);
		unset($args['icon']);
		$args['menu_icon'] = fs_cpt_menu_icon($menu_icon_value);
		register_post_type($post_type, $args);
	}
}

/**
 * Resolve CPT menu icon (dashicon class, URL/data URI, or inline SVG).
 * Falls back to the default FromScratch SVG icon.
 *
 * @param mixed $icon Raw menu_icon value from config.
 */
function fs_cpt_menu_icon($icon): string
{
	if (is_string($icon) && $icon !== '') {
		$trimmed = trim($icon);
		// Already valid menu icon formats.
		if (strpos($trimmed, 'dashicons-') === 0 || strpos($trimmed, 'data:image/') === 0 || preg_match('#^https?://#i', $trimmed)) {
			return $trimmed;
		}
		// Inline SVG -> data URI.
		if (stripos($trimmed, '<svg') !== false) {
			return fs_cpt_svg_to_data_uri($trimmed);
		}
	}

	return fs_cpt_svg_to_data_uri('<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#000"><path d="M371.96-240h215.76q15.28 0 25.78-10.29 10.5-10.29 10.5-25.5t-10.34-25.71Q603.32-312 588.04-312H372.28q-15.28 0-25.78 10.29-10.5 10.29-10.5 25.5t10.34 25.71q10.34 10.5 25.62 10.5Zm0-144h215.76q15.28 0 25.78-10.29 10.5-10.29 10.5-25.5t-10.34-25.71Q603.32-456 588.04-456H372.28q-15.28 0-25.78 10.29-10.5 10.29-10.5 25.5t10.34 25.71q10.34 10.5 25.62 10.5ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h282q14 0 27.5 5t23.5 16l150 150q11 10 16 23.5t5 27.5v474q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-660q0 15.3 10.35 25.65Q548.7-624 564-624h132L528-792v132Z"/></svg>');
}

/**
 * Convert inline SVG markup to data URI accepted by register_post_type menu_icon.
 */
function fs_cpt_svg_to_data_uri(string $svg): string
{
	$svg = fs_cpt_svg_apply_fill($svg, '#9da2a7');
	return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Normalize inline SVG fill color for admin menu icon visibility.
 */
function fs_cpt_svg_apply_fill(string $svg, string $fill): string
{
	$svg = preg_replace('/\sfill="[^"]*"/i', ' fill="' . $fill . '"', $svg);
	if (is_string($svg) && stripos($svg, '<svg') !== false && stripos($svg, ' fill=') === false) {
		$svg = preg_replace('/<svg\b/i', '<svg fill="' . $fill . '"', $svg, 1);
	}
	return is_string($svg) ? $svg : '';
}

/**
 * Build admin CSS to force CPT menu icon background-image early (reduces flicker).
 */
function fs_cpt_admin_menu_icon_css(): string
{
	$cpts = fs_config_cpt('cpts');
	if (!is_array($cpts) || $cpts === []) {
		return '';
	}

	$css = '';
	foreach ($cpts as $post_type => $args) {
		if (!is_string($post_type) || $post_type === '' || !is_array($args)) {
			continue;
		}
		$icon_value = $args['menu_icon'] ?? ($args['icon'] ?? null);
		$icon = fs_cpt_menu_icon($icon_value);
		if (!is_string($icon) || $icon === '' || strpos($icon, 'dashicons-') === 0) {
			continue;
		}
		$post_type = sanitize_key($post_type);
		if ($post_type === '') {
			continue;
		}
		$icon_css = str_replace(['\\', '"'], ['\\\\', '\"'], $icon);
		$selector = '#adminmenu #menu-posts-' . $post_type . ' .wp-menu-image';
		$css .= $selector . '{background-image:url("' . $icon_css . '")!important;background-repeat:no-repeat!important;background-position:center!important;background-size:20px 20px!important;}';
		$css .= $selector . '::before{content:""!important;}';
	}

	return $css;
}

add_action('admin_head', function (): void {
	$css = fs_cpt_admin_menu_icon_css();
	if ($css === '') {
		return;
	}
	echo '<style id="fs-cpt-menu-icons">' . $css . '</style>';
}, 5);

/**
 * Build default labels from post type key (fallback when labels not provided).
 *
 * @param string $post_type Post type key (e.g. 'project').
 * @param array  $labels    Optional preconfigured labels (name/singular_name/menu_name).
 * @return array<string, string>
 */
function fs_cpt_default_labels(string $post_type, array $labels = []): array
{
	$name = isset($labels['singular_name']) && is_string($labels['singular_name']) && $labels['singular_name'] !== ''
		? $labels['singular_name']
		: ucfirst($post_type);
	$plural = isset($labels['name']) && is_string($labels['name']) && $labels['name'] !== ''
		? $labels['name']
		: $name . 's';
	$menu_name = (isset($labels['menu_name']) && is_string($labels['menu_name']) && $labels['menu_name'] !== '') ? $labels['menu_name'] : $plural;
	return [
		'name'                  => $plural,
		'singular_name'         => $name,
		'menu_name'             => $menu_name,
		'add_new'               => __('Add New', 'fromscratch'),
		'add_new_item'          => sprintf(__('Add New %s', 'fromscratch'), $name),
		'edit_item'             => sprintf(__('Edit %s', 'fromscratch'), $name),
		'new_item'              => sprintf(__('New %s', 'fromscratch'), $name),
		'view_item'             => sprintf(__('View %s', 'fromscratch'), $name),
		'view_items'            => sprintf(__('View %s', 'fromscratch'), $plural),
		'search_items'          => sprintf(__('Search %s', 'fromscratch'), $plural),
		'not_found'             => sprintf(__('No %s found.', 'fromscratch'), $plural),
		'not_found_in_trash'    => sprintf(__('No %s found in Trash.', 'fromscratch'), $plural),
		'all_items'             => sprintf(__('All %s', 'fromscratch'), $plural),
		'archives'              => sprintf(__('%s Archives', 'fromscratch'), $name),
		'attributes'            => sprintf(__('%s Attributes', 'fromscratch'), $name),
		'insert_into_item'      => sprintf(__('Insert into %s', 'fromscratch'), $name),
		'uploaded_to_this_item' => sprintf(__('Uploaded to this %s', 'fromscratch'), $name),
		'filter_items_list'     => sprintf(__('Filter %s list', 'fromscratch'), $plural),
		'items_list_navigation' => sprintf(__('%s list navigation', 'fromscratch'), $plural),
		'items_list'            => sprintf(__('%s list', 'fromscratch'), $plural),
	];
}

// Register after theme textdomain is loaded (init priority 1 in inc/language.php).
add_action('init', 'fs_register_cpts', 2);
