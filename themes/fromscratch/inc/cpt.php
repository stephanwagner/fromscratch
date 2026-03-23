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
		$has_order = !empty($args['has_order']);
		unset($args['has_order']);
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
		if ($has_order && isset($args['supports']) && is_array($args['supports']) && !in_array('page-attributes', $args['supports'], true)) {
			$args['supports'][] = 'page-attributes';
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
 * Return ordered CPT map from config.
 *
 * @return array<string, bool>
 */
function fs_cpt_ordered_map(): array
{
	$cpts = fs_config_cpt('cpts');
	if (!is_array($cpts) || $cpts === []) {
		return [];
	}
	$ordered = [];
	foreach ($cpts as $post_type => $args) {
		if (!is_string($post_type) || $post_type === '' || !is_array($args)) {
			continue;
		}
		if (!empty($args['has_order'])) {
			$ordered[sanitize_key($post_type)] = true;
		}
	}
	return $ordered;
}

function fs_cpt_is_ordered(string $post_type): bool
{
	$map = fs_cpt_ordered_map();
	return isset($map[$post_type]) && $map[$post_type] === true;
}

/**
 * Admin list: default sort by menu_order for ordered CPTs.
 */
add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}
	$post_type = $query->get('post_type');
	if (!is_string($post_type) || $post_type === '' || !fs_cpt_is_ordered($post_type)) {
		return;
	}
	$orderby = (string) $query->get('orderby');
	if ($orderby !== '' && $orderby !== 'menu_order') {
		return;
	}
	$order = strtoupper((string) $query->get('order')) === 'DESC' ? 'DESC' : 'ASC';
	$query->set('orderby', ['menu_order' => $order, 'date' => 'DESC']);
	$query->set('order', $order);
}, 20);

/**
 * Ordered CPTs: when menu_order is left at 0, append to end (max + 1).
 */
add_action('save_post', function (int $post_id, \WP_Post $post): void {
	static $running = [];
	if (isset($running[$post_id])) {
		return;
	}
	if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
		return;
	}
	if (!fs_cpt_is_ordered($post->post_type)) {
		return;
	}
	if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
		return;
	}
	if ((int) $post->menu_order !== 0) {
		return;
	}

	$max_ids = get_posts([
		'post_type'      => $post->post_type,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'menu_order',
		'order'          => 'DESC',
		'exclude'        => [$post_id],
	]);
	$max = isset($max_ids[0]) ? (int) $max_ids[0] : 0;
	$max_order = $max > 0 ? (int) get_post_field('menu_order', $max) : 0;

	$running[$post_id] = true;
	wp_update_post([
		'ID'         => $post_id,
		'menu_order' => $max_order + 1,
	]);
	unset($running[$post_id]);
}, 20, 2);

/**
 * Add reorder column for ordered CPTs.
 */
add_filter('manage_posts_columns', function (array $columns): array {
	$post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : 'post';
	if (!fs_cpt_is_ordered($post_type)) {
		return $columns;
	}
	$out = [];
	foreach ($columns as $key => $label) {
		$out[$key] = $label;
		if ($key === 'title') {
			$out['fs_cpt_order'] = __('Order', 'fromscratch');
			$out['fs_cpt_reorder'] = __('Reorder', 'fromscratch');
		}
	}
	if (!isset($out['fs_cpt_order'])) {
		$out['fs_cpt_order'] = __('Order', 'fromscratch');
	}
	if (!isset($out['fs_cpt_reorder'])) {
		$out['fs_cpt_reorder'] = __('Reorder', 'fromscratch');
	}
	return $out;
}, 20);

/**
 * Make "Order" column header clickable/sortable for ordered CPTs.
 */
add_action('init', function (): void {
	$ordered = fs_cpt_ordered_map();
	if ($ordered === []) {
		return;
	}
	foreach (array_keys($ordered) as $post_type) {
		add_filter('manage_edit-' . $post_type . '_sortable_columns', function (array $columns): array {
			$columns['fs_cpt_order'] = 'menu_order';
			return $columns;
		});
	}
}, 20);

/**
 * Default list-table sort UI for ordered CPTs: show Order as active (not Date).
 */
add_filter('request', function (array $vars): array {
	if (!is_admin()) {
		return $vars;
	}
	$post_type = isset($vars['post_type']) ? sanitize_key((string) $vars['post_type']) : '';
	if ($post_type === '' || !fs_cpt_is_ordered($post_type)) {
		return $vars;
	}
	if (!empty($vars['orderby'])) {
		return $vars;
	}
	$vars['orderby'] = 'menu_order';
	$vars['order'] = 'asc';
	return $vars;
}, 20);

/**
 * Ensure ordered CPT list URL includes menu_order so the UI shows "Order" as active sort.
 */
add_action('admin_init', function (): void {
	global $pagenow;
	if ($pagenow !== 'edit.php') {
		return;
	}
	// Let reorder action requests pass through untouched.
	if (!empty($_GET['fs_cpt_reorder']) || !empty($_GET['post_id'])) {
		return;
	}
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
		return;
	}
	$post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
	if ($post_type === '' || !fs_cpt_is_ordered($post_type)) {
		return;
	}
	if (!empty($_GET['orderby'])) {
		return;
	}
	$url = add_query_arg([
		'post_type' => $post_type,
		'orderby'   => 'menu_order',
		'order'     => 'asc',
	], admin_url('edit.php'));
	wp_safe_redirect($url);
	exit;
}, 15);

add_action('manage_posts_custom_column', function (string $column, int $post_id): void {
	if ($column === 'fs_cpt_order') {
		$order = (int) get_post_field('menu_order', $post_id);
		echo esc_html((string) $order);
		return;
	}
	if ($column !== 'fs_cpt_reorder') {
		return;
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || !fs_cpt_is_ordered($post->post_type) || !current_user_can('edit_post', $post_id)) {
		return;
	}
	$base = admin_url('edit.php?post_type=' . rawurlencode($post->post_type));
	$mk = function (string $dir, string $label) use ($base, $post_id): string {
		$url = add_query_arg([
			'fs_cpt_reorder' => $dir,
			'post_id'        => $post_id,
		], $base);
		$url = wp_nonce_url($url, 'fs_cpt_reorder_' . $dir . '_' . $post_id);
		return '<a class="button button-small" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
	};
	echo '<div style="display:flex;gap:4px;flex-wrap:wrap">';
	echo $mk('top', __('Top', 'fromscratch')) . $mk('up', __('Up', 'fromscratch')) . $mk('down', __('Down', 'fromscratch')) . $mk('bottom', __('Bottom', 'fromscratch'));
	echo '</div>';
}, 20, 2);

/**
 * Handle reorder actions from list table.
 */
add_action('admin_init', function (): void {
	if (!is_admin() || empty($_GET['fs_cpt_reorder']) || empty($_GET['post_id'])) {
		return;
	}
	$dir = sanitize_key((string) wp_unslash($_GET['fs_cpt_reorder']));
	$post_id = (int) $_GET['post_id'];
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || !fs_cpt_is_ordered($post->post_type) || !current_user_can('edit_post', $post_id)) {
		return;
	}
	$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) wp_unslash($_GET['_wpnonce'])) : '';
	if (!wp_verify_nonce($nonce, 'fs_cpt_reorder_' . $dir . '_' . $post_id)) {
		return;
	}
	$ordered_ids = get_posts([
		'post_type'      => $post->post_type,
		'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'ASC'],
	]);
	$ordered_ids = array_values(array_map('intval', is_array($ordered_ids) ? $ordered_ids : []));
	$idx = array_search($post_id, $ordered_ids, true);
	if ($idx === false) {
		return;
	}
	if ($dir === 'up' && $idx > 0) {
		$tmp = $ordered_ids[$idx - 1];
		$ordered_ids[$idx - 1] = $ordered_ids[$idx];
		$ordered_ids[$idx] = $tmp;
	} elseif ($dir === 'down' && $idx < count($ordered_ids) - 1) {
		$tmp = $ordered_ids[$idx + 1];
		$ordered_ids[$idx + 1] = $ordered_ids[$idx];
		$ordered_ids[$idx] = $tmp;
	} elseif ($dir === 'top' && $idx > 0) {
		unset($ordered_ids[$idx]);
		array_unshift($ordered_ids, $post_id);
		$ordered_ids = array_values($ordered_ids);
	} elseif ($dir === 'bottom' && $idx < count($ordered_ids) - 1) {
		unset($ordered_ids[$idx]);
		$ordered_ids[] = $post_id;
		$ordered_ids = array_values($ordered_ids);
	}
	foreach ($ordered_ids as $menu_order => $id) {
		wp_update_post([
			'ID'         => (int) $id,
			// Start at 1 so save_post "append when 0" logic doesn't override reordered top item.
			'menu_order' => (int) $menu_order + 1,
		]);
	}
	wp_safe_redirect(admin_url('edit.php?post_type=' . rawurlencode($post->post_type)));
	exit;
});

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
