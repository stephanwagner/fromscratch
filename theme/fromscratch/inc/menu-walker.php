<?php

defined('ABSPATH') || exit;

/**
 * Minimal, accessible, framework-agnostic nav walker.
 */
class FS_Walker_Nav_Menu extends Walker_Nav_Menu
{
	/** @var array<int, string> */
	private array $submenu_ids_by_depth = [];

	private function fs_menu_scope($args): string
	{
		$menu_id = '';
		if (is_object($args) && isset($args->menu_id) && is_scalar($args->menu_id)) {
			$menu_id = (string) $args->menu_id;
		}
		$menu_id = sanitize_html_class($menu_id);
		return $menu_id !== '' ? $menu_id : 'menu';
	}

	private function fs_build_submenu_id($args, int $item_id): string
	{
		$scope = $this->fs_menu_scope($args);
		if ($item_id > 0) {
			return 'sub-menu-' . $scope . '-' . $item_id;
		}
		return 'sub-menu-' . $scope . '-' . uniqid();
	}

	public function start_lvl(&$output, $depth = 0, $args = null): void
	{
		$indent = str_repeat("\t", $depth);
		$submenu_id = $this->submenu_ids_by_depth[$depth] ?? $this->fs_build_submenu_id($args, 0);
		$classes = ['sub-menu', 'menu-depth-' . ($depth + 1)];

		$output .= "\n" . $indent . '<ul id="' . esc_attr($submenu_id) . '" class="' . esc_attr(implode(' ', $classes)) . '">' . "\n";
	}

	public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
	{
		$indent = $depth ? str_repeat("\t", $depth) : '';

		$classes = empty($item->classes) ? [] : (array) $item->classes;
		$classes[] = 'menu-depth-' . $depth;
		$classes = apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth);
		$class_names = $classes ? ' class="' . esc_attr(implode(' ', $classes)) . '"' : '';

		$item_id = apply_filters('nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth);
		$item_id_attr = $item_id ? ' id="' . esc_attr($item_id) . '"' : '';

		$output .= $indent . '<li' . $item_id_attr . $class_names . '>';

		$atts = [];
		$atts['title']  = !empty($item->attr_title) ? $item->attr_title : '';
		$atts['target'] = !empty($item->target) ? $item->target : '';
		$atts['rel']    = !empty($item->xfn) ? $item->xfn : '';
		$atts['href']   = !empty($item->url) ? $item->url : '';
		$atts['class']  = 'menu-link';

		$atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);
		$attributes = '';
		foreach ($atts as $attr => $value) {
			if (!is_scalar($value) || $value === '') {
				continue;
			}
			$val = $attr === 'href' ? esc_url((string) $value) : esc_attr((string) $value);
			$attributes .= ' ' . $attr . '="' . $val . '"';
		}

		$title = apply_filters('the_title', $item->title, $item->ID);
		$title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);

		$item_output = '<a' . $attributes . '>';
		$item_output .= '<span class="menu-label">' . $title . '</span>';
		$item_output .= '</a>';

		$has_children = in_array('menu-item-has-children', $classes, true);
		unset($this->submenu_ids_by_depth[$depth]);
		if ($has_children) {
			$submenu_id = $this->fs_build_submenu_id($args, (int) $item->ID);
			$this->submenu_ids_by_depth[$depth] = $submenu_id;
			$toggle_label = sprintf(
				/* translators: %s is menu item label. */
				__('Toggle submenu for %s', 'fromscratch'),
				wp_strip_all_tags((string) $title)
			);
			$item_output .= '<button class="sub-menu-toggle" aria-expanded="false" aria-controls="' . esc_attr($submenu_id) . '" aria-label="' . esc_attr($toggle_label) . '" type="button"></button>';
		}

		$output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
	}
}

/**
 * Helper for rendering nav menus with the custom walker.
 *
 * @param array<string, mixed> $args
 */
function fs_nav_menu(array $args = []): void
{
	static $menu_instance = 0;
	$menu_instance++;

	$defaults = [
		'container' => 'nav',
		'fallback_cb' => 'wp_page_menu',
		'echo' => true,
	];
	$args = wp_parse_args($args, $defaults);
	$theme_location = isset($args['theme_location']) ? (string) $args['theme_location'] : '';
	if (empty($args['menu_id'])) {
		$menu_id_base = $theme_location !== '' ? ('menu-' . $theme_location) : 'menu';
		$menu_id_base = sanitize_html_class($menu_id_base);
		$args['menu_id'] = $menu_id_base . '-' . $menu_instance;
	}
	if ($theme_location !== '' && function_exists('has_nav_menu') && !has_nav_menu($theme_location)) {
		// No assigned menu for this location: show fallback navigation.
		unset($args['walker']);
		wp_nav_menu($args);
		return;
	}
	if (empty($args['walker']) && class_exists('FS_Walker_Nav_Menu')) {
		$args['walker'] = new FS_Walker_Nav_Menu();
	}
	wp_nav_menu($args);
}
