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

		$has_children = in_array('menu-item-has-children', $classes, true);
		unset($this->submenu_ids_by_depth[$depth]);

		$item_output = '';
		$submenu_id = '';
		if ($has_children) {
			$submenu_id = $this->fs_build_submenu_id($args, (int) $item->ID);
			$this->submenu_ids_by_depth[$depth] = $submenu_id;
			$item_output .= '<span class="menu-item__inner">';
		}

		$item_output .= '<a' . $attributes . '>';
		$item_output .= '<span class="menu-label">' . $title . '</span>';
		$item_output .= '</a>';

		if ($has_children) {
			$toggle_label = sprintf(
				/* translators: %s is menu item label. */
				__('Toggle submenu for %s', 'fromscratch'),
				wp_strip_all_tags((string) $title)
			);
			$item_output .= '<button class="sub-menu-toggle" aria-expanded="false" aria-controls="' . esc_attr($submenu_id) . '" aria-label="' . esc_attr($toggle_label) . '" type="button">';
			$item_output .= '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M466.54-375.23q-6.23-2.31-11.85-7.92L274.92-562.92q-8.3-8.31-8.5-20.89-.19-12.57 8.5-21.27 8.7-8.69 21.08-8.69 12.38 0 21.08 8.69L480-442.15l162.92-162.93q8.31-8.3 20.89-8.5 12.57-.19 21.27 8.5 8.69 8.7 8.69 21.08 0 12.38-8.69 21.08L505.31-383.15q-5.62 5.61-11.85 7.92-6.23 2.31-13.46 2.31t-13.46-2.31Z"/></svg>';
			$item_output .= '</button>';
			$item_output .= '</span>';
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
	if (!empty($args['aria_label']) && is_scalar($args['aria_label'])) {
		$args['container_aria_label'] = (string) $args['aria_label'];
	}
	unset($args['aria_label']);
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
