<?php

defined('ABSPATH') || exit;

/**
 * Minimal, accessible, framework-agnostic nav walker.
 */
class FS_Walker_Nav_Menu extends Walker_Nav_Menu
{
	/** @var array<int, int> */
	private array $submenu_parent_ids = [];

	public function start_lvl(&$output, $depth = 0, $args = null): void
	{
		$indent = str_repeat("\t", $depth);
		$parent_id = isset($this->submenu_parent_ids[$depth]) ? (int) $this->submenu_parent_ids[$depth] : 0;
		$submenu_id = $parent_id > 0 ? 'sub-menu-' . $parent_id : 'sub-menu-' . uniqid();
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
		$this->submenu_parent_ids[$depth] = (int) $item->ID;
		if ($has_children) {
			$submenu_id = 'sub-menu-' . (int) $item->ID;
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
	$defaults = [
		'container' => 'nav',
		'fallback_cb' => 'wp_page_menu',
		'echo' => true,
	];
	$args = wp_parse_args($args, $defaults);
	$theme_location = isset($args['theme_location']) ? (string) $args['theme_location'] : '';
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
