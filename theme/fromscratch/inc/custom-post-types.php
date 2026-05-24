<?php

defined('ABSPATH') || exit;

/**
 * Register custom post types from config/content-types/*.php.
 * Registered CPTs are included in fs_theme_post_types() (theme-setup.php) and thus in SEO, post expirator, duplicate, etc.
 */

/**
 * Map content-type config to register_post_type() arguments (strips theme-only keys).
 *
 * @param array<string, mixed> $cfg
 * @return array<string, mixed>
 */
function fs_content_type_wp_register_args(array $cfg): array
{
	$archive = fs_content_type_archive_from_cfg($cfg);
	$query = fs_content_type_query_from_cfg($cfg);
	$admin = fs_content_type_admin_from_cfg($cfg);

	$args = [];
	foreach (['public', 'hierarchical', 'labels', 'supports', 'show_ui', 'show_in_menu', 'show_in_rest', 'capability_type', 'map_meta_cap', 'rewrite', 'query_var'] as $key) {
		if (array_key_exists($key, $cfg)) {
			$args[$key] = $cfg[$key];
		}
	}

	$args['has_archive'] = !empty($archive['enabled']);
	if (!empty($archive['slug']) && is_string($archive['slug'])) {
		$args['url'] = $archive['slug'];
	}

	$args['menu_position'] = isset($admin['menu_position']) ? (int) $admin['menu_position'] : 5;
	if (!empty($admin['menu_icon'])) {
		$args['menu_icon'] = $admin['menu_icon'];
	}

	$args['_fs_has_menu_order'] = !empty($query['menu_order']);
	$args['_fs_orderby'] = isset($query['orderby']) && is_string($query['orderby']) ? $query['orderby'] : '';
	$args['_fs_order'] = isset($query['order']) && is_string($query['order']) ? $query['order'] : '';

	return $args;
}

/**
 * @param array<string, mixed> $cfg
 * @return array<string, mixed>
 */
function fs_content_type_archive_from_cfg(array $cfg): array
{
	if (isset($cfg['archive']) && is_array($cfg['archive'])) {
		return $cfg['archive'];
	}

	return [
		'enabled' => !empty($cfg['has_archive']),
		'slug' => isset($cfg['url']) && is_string($cfg['url']) ? $cfg['url'] : '',
		'design' => isset($cfg['archive_design']) ? (string) $cfg['archive_design'] : 'list',
		'texts' => isset($cfg['texts']) && is_array($cfg['texts']) ? $cfg['texts'] : [],
	];
}

/**
 * @param array<string, mixed> $cfg
 * @return array<string, mixed>
 */
function fs_content_type_query_from_cfg(array $cfg): array
{
	if (isset($cfg['query']) && is_array($cfg['query'])) {
		return $cfg['query'];
	}

	return [
		'orderby' => isset($cfg['orderby']) && is_string($cfg['orderby']) ? $cfg['orderby'] : '',
		'order' => isset($cfg['order']) && is_string($cfg['order']) ? $cfg['order'] : '',
		'menu_order' => !empty($cfg['has_order']),
	];
}

/**
 * @param array<string, mixed> $cfg
 * @return array<string, mixed>
 */
function fs_content_type_admin_from_cfg(array $cfg): array
{
	if (isset($cfg['admin']) && is_array($cfg['admin'])) {
		return $cfg['admin'];
	}

	return [
		'menu_icon' => $cfg['menu_icon'] ?? null,
		'menu_position' => $cfg['menu_position'] ?? 5,
		'page_title_toggle' => !empty($cfg['has_page_title_toggle']),
	];
}

/**
 * Whether config enables a public archive for built-in posts (`config/content-types/post.php`).
 */
function fs_post_type_has_config_archive(?string $post_type = null): bool
{
	if ($post_type === null || $post_type === '') {
		$post_type = 'post';
	}
	if ($post_type !== 'post') {
		return false;
	}
	if (function_exists('fs_theme_feature_enabled') && !fs_theme_feature_enabled('blogs')) {
		return false;
	}

	$archive = fs_content_type_archive('post');

	return !empty($archive['enabled']);
}

/**
 * Archive slug from `config/content-types/post.php` (`archive.slug`).
 */
function fs_post_archive_slug(): string
{
	if (!fs_post_type_has_config_archive('post')) {
		return '';
	}

	$archive = fs_content_type_archive('post');
	$slug = isset($archive['slug']) && is_string($archive['slug']) ? sanitize_title($archive['slug']) : '';

	return $slug !== '' ? $slug : 'blog';
}

/**
 * Apply `config/content-types/post.php` archive settings to the built-in `post` type.
 *
 * Core registers `post` with `rewrite => false`, so archive rewrites are added separately.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function fs_post_register_args_from_config(array $args, string $post_type): array
{
	if ($post_type !== 'post') {
		return $args;
	}
	if (function_exists('fs_theme_feature_enabled') && !fs_theme_feature_enabled('blogs')) {
		$args['has_archive'] = false;

		return $args;
	}

	$cfg = fs_config_cpt('post');
	if (!is_array($cfg)) {
		return $args;
	}

	$archive = fs_content_type_archive_from_cfg($cfg);
	if (empty($archive['enabled'])) {
		$args['has_archive'] = false;

		return $args;
	}

	$slug = fs_post_archive_slug();
	$args['has_archive'] = $slug !== '' ? $slug : true;

	return $args;
}

add_filter('register_post_type_args', 'fs_post_register_args_from_config', 10, 2);

/**
 * Built-in `post` keeps `rewrite => false`; register archive URL rules explicitly.
 */
function fs_post_archive_register_rewrites(): void
{
	$slug = fs_post_archive_slug();
	if ($slug === '') {
		return;
	}

	global $wp_rewrite;

	add_rewrite_rule("{$slug}/?$", 'index.php?post_type=post', 'top');
	add_rewrite_rule("{$slug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$", 'index.php?post_type=post&paged=$matches[1]', 'top');
}

add_action('init', 'fs_post_archive_register_rewrites', 20);

/**
 * Core `get_post_type_archive_link( 'post' )` ignores `has_archive` and uses Posts page / home.
 */
add_filter('post_type_archive_link', function (string $link, string $post_type): string {
	if ($post_type !== 'post') {
		return $link;
	}

	$slug = fs_post_archive_slug();
	if ($slug === '') {
		return $link;
	}

	return home_url(user_trailingslashit($slug, 'post_type_archive'));
}, 10, 2);

/**
 * Flush rewrite rules when the post archive slug/enabled state changes.
 */
function fs_post_archive_maybe_flush_rewrites(): void
{
	if (!fs_post_type_has_config_archive()) {
		delete_option('fs_post_archive_rewrite_sig');

		return;
	}

	$archive = fs_content_type_archive('post');
	$sig = md5(wp_json_encode([
		'enabled' => !empty($archive['enabled']),
		'slug' => isset($archive['slug']) ? (string) $archive['slug'] : '',
	]));
	if (get_option('fs_post_archive_rewrite_sig') === $sig) {
		return;
	}

	flush_rewrite_rules(true);
	update_option('fs_post_archive_rewrite_sig', $sig, false);
}

add_action('init', 'fs_post_archive_maybe_flush_rewrites', 99);

/**
 * Merge configured labels with generated defaults (same shape as register_post_type() labels).
 *
 * @param array<string, string> $provided_labels
 * @return array<string, string>
 */
function fs_cpt_merge_labels(string $post_type, array $provided_labels = []): array
{
	return array_merge(fs_cpt_default_labels($post_type, $provided_labels), $provided_labels);
}

/**
 * Translate plain English label strings from content-type config.
 * Must run before {@see fs_cpt_merge_labels()} so derived labels (e.g. “All %s”) use translated names.
 *
 * @param array<string, string> $strings
 * @return array<string, string>
 */
function fs_cpt_translate_config_label_strings(array $strings): array
{
	foreach ($strings as $key => $value) {
		if (!is_string($key) || !is_string($value) || $value === '') {
			continue;
		}
		$strings[$key] = __($value, 'fromscratch');
	}

	return $strings;
}

/**
 * Replace labels on a registered post type object.
 *
 * @param array<string, string> $labels
 */
function fs_apply_post_type_labels(string $post_type, array $labels): void
{
	$obj = get_post_type_object($post_type);
	if (!$obj instanceof \WP_Post_Type) {
		return;
	}

	foreach ($labels as $key => $value) {
		if (!is_string($key) || !is_string($value)) {
			continue;
		}
		$obj->labels->$key = $value;
	}

	if (isset($labels['name']) && is_string($labels['name'])) {
		$obj->label = $labels['name'];
	}
}

/**
 * Apply `config/content-types/post.php` labels to the built-in `post` type.
 *
 * Core registers `post` before the text domain loads; labels are applied on init instead.
 */
function fs_post_apply_labels_from_config(): void
{
	if (function_exists('fs_theme_feature_enabled') && !fs_theme_feature_enabled('blogs')) {
		return;
	}

	$cfg = fs_config_cpt('post');
	if (!is_array($cfg) || !isset($cfg['labels']) || !is_array($cfg['labels'])) {
		return;
	}

	$provided_labels = array_filter(
		$cfg['labels'],
		static fn($value, $key) => is_string($key) && is_string($value) && $value !== '',
		ARRAY_FILTER_USE_BOTH
	);
	if ($provided_labels === []) {
		return;
	}

	$provided_labels = fs_cpt_translate_config_label_strings($provided_labels);
	$labels = fs_cpt_merge_labels('post', $provided_labels);
	fs_apply_post_type_labels('post', $labels);
}

add_action('init', 'fs_post_apply_labels_from_config', 10);

/**
 * Register all enabled CPTs from config/content-types/.
 *
 * @return void
 */
function fs_register_cpts(): void
{
	$cpts = fs_config_cpt('all');
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

	foreach ($cpts as $post_type => $cfg) {
		if (!is_string($post_type) || $post_type === '' || !is_array($cfg)) {
			continue;
		}
		$args = array_merge($defaults, fs_content_type_wp_register_args($cfg));
		// Convenience: `url` => 'projects' sets rewrite slug (same as rewrite => ['slug' => 'projects']).
		if (isset($args['url'])) {
			$url_raw = $args['url'];
			unset($args['url']);
			if (is_string($url_raw) && $url_raw !== '') {
				$url_slug = sanitize_title($url_raw);
				$rewrite = $args['rewrite'] ?? true;
				if ($rewrite !== false) {
					if ($rewrite === true) {
						$args['rewrite'] = ['slug' => $url_slug];
					} elseif (is_array($rewrite) && !isset($rewrite['slug'])) {
						$args['rewrite'] = array_merge($rewrite, ['slug' => $url_slug]);
					}
				}
			}
		}
		$has_order = !empty($args['_fs_has_menu_order']);
		unset($args['_fs_has_menu_order'], $args['_fs_orderby'], $args['_fs_order']);
		// Taxonomies are attached separately so config can define/register custom taxonomies too.
		unset($args['taxonomies']);
		// Block editor needs custom-fields support to expose/save post meta (e.g. SEO panel).
		if (isset($args['supports']) && is_array($args['supports']) && !in_array('custom-fields', $args['supports'], true)) {
			$args['supports'][] = 'custom-fields';
		}
		if ($has_order && isset($args['supports']) && is_array($args['supports']) && !in_array('page-attributes', $args['supports'], true)) {
			$args['supports'][] = 'page-attributes';
		}
		// Ensure labels exist and derive missing labels from configured name/singular_name.
		$provided_labels = isset($args['labels']) && is_array($args['labels']) ? $args['labels'] : [];
		$provided_labels = fs_cpt_translate_config_label_strings($provided_labels);
		$args['labels'] = fs_cpt_merge_labels($post_type, $provided_labels);
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
 * Collect taxonomy config for built-in posts and configured CPTs.
 *
 * Supports:
 * - `wp_categories => true` (shared core category taxonomy)
 * - `taxonomies => ['category']` (attach existing taxonomy)
 * - `taxonomies => ['project_category' => ['label' => 'Projekt-Kategorien']]` (register custom taxonomy)
 *
 * @return array<string, array{args: ?array, object_types: string[]}>
 */
function fs_cpt_taxonomy_map(): array
{
	$map = [];
	$sources = [];

	$post_cfg = fs_config_cpt('post');
	if (is_array($post_cfg)) {
		$sources['post'] = $post_cfg;
	}

	$cpts = fs_config_cpt('all');
	if (is_array($cpts)) {
		foreach ($cpts as $post_type => $args) {
			if (is_string($post_type) && $post_type !== '' && is_array($args)) {
				$sources[$post_type] = $args;
			}
		}
	}

	foreach ($sources as $object_type => $cfg) {
		$taxonomies = [];

		if (fs_content_type_uses_wp_categories($cfg)) {
			$taxonomies[] = 'category';
		}
		if (isset($cfg['taxonomies']) && is_array($cfg['taxonomies'])) {
			$taxonomies = array_merge($taxonomies, $cfg['taxonomies']);
		}

		foreach ($taxonomies as $key => $value) {
			$slug = '';
			$args = null;

			if (is_int($key) && is_string($value)) {
				$slug = sanitize_key($value);
			} elseif (is_string($key) && $key !== '' && is_array($value)) {
				$slug = sanitize_key($key);
				$args = $value;
			}

			if ($slug === '') {
				continue;
			}

			if (!isset($map[$slug])) {
				$map[$slug] = [
					'args' => null,
					'object_types' => [],
				];
			}
			if (is_array($args) && $map[$slug]['args'] === null) {
				$map[$slug]['args'] = $args;
			}
			$map[$slug]['object_types'][] = $object_type;
		}
	}

	foreach ($map as $slug => $entry) {
		$map[$slug]['object_types'] = array_values(array_unique(array_filter($entry['object_types'], 'is_string')));
	}

	return $map;
}

/**
 * Build fallback labels for custom taxonomies declared in config.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param string $label Plural label.
 * @param string $singular_label Singular label.
 * @return array<string, string>
 */
function fs_cpt_default_taxonomy_labels(string $taxonomy, string $label, string $singular_label): array
{
	if ($label === '') {
		$label = ucwords(str_replace(['-', '_'], ' ', $taxonomy));
	}
	if ($singular_label === '') {
		$singular_label = $label;
	}

	return [
		'name' => $label,
		'menu_name' => $label,
		'singular_name' => $singular_label,
		'search_items' => __('Search categories', 'fromscratch'),
		'all_items' => __('All categories', 'fromscratch'),
		'parent_item' => __('Parent category', 'fromscratch'),
		'parent_item_colon' => __('Parent category:', 'fromscratch'),
		'edit_item' => __('Edit category', 'fromscratch'),
		'view_item' => __('View categories', 'fromscratch'),
		'update_item' => __('Update category', 'fromscratch'),
		'add_new_item' => __('Add new category', 'fromscratch'),
		'new_item_name' => __('New category name', 'fromscratch'),
	];
}

/**
 * Normalize custom taxonomy args from config.
 *
 * @param string $taxonomy Taxonomy slug.
 * @param array<string, mixed> $args Raw config args.
 * @return array<string, mixed>
 */
function fs_cpt_normalize_taxonomy_args(string $taxonomy, array $args): array
{
	$defaults = [
		'public' => true,
		'show_ui' => true,
		'show_admin_column' => true,
		'show_in_rest' => true,
		'hierarchical' => true,
		'rewrite' => true,
		'query_var' => true,
	];

	$label = isset($args['label']) && is_string($args['label']) ? trim($args['label']) : '';
	$singular_label = isset($args['singular_label']) && is_string($args['singular_label']) ? trim($args['singular_label']) : '';
	unset($args['label'], $args['singular_label']);

	if ($label !== '') {
		$label = __($label, 'fromscratch');
	}
	if ($singular_label !== '') {
		$singular_label = __($singular_label, 'fromscratch');
	}

	if (isset($args['url'])) {
		$url_raw = $args['url'];
		unset($args['url']);
		if (is_string($url_raw) && $url_raw !== '') {
			$url_slug = sanitize_title($url_raw);
			$rewrite = $args['rewrite'] ?? true;
			if ($rewrite !== false) {
				if ($rewrite === true) {
					$args['rewrite'] = ['slug' => $url_slug];
				} elseif (is_array($rewrite) && !isset($rewrite['slug'])) {
					$args['rewrite'] = array_merge($rewrite, ['slug' => $url_slug]);
				}
			}
		}
	}

	$provided_labels = isset($args['labels']) && is_array($args['labels']) ? $args['labels'] : [];
	$provided_labels = fs_cpt_translate_config_label_strings($provided_labels);
	$args = array_merge($defaults, $args);
	$args['labels'] = array_merge(
		fs_cpt_default_taxonomy_labels($taxonomy, $label, $singular_label),
		$provided_labels
	);

	return $args;
}

/**
 * Register/attach taxonomies declared in config for posts and CPTs.
 */
function fs_register_cpt_taxonomies(): void
{
	$taxonomies = fs_cpt_taxonomy_map();
	if ($taxonomies === []) {
		return;
	}

	foreach ($taxonomies as $taxonomy => $entry) {
		$object_types = array_values(array_filter(
			$entry['object_types'],
			static fn(string $object_type): bool => post_type_exists($object_type)
		));
		if ($object_types === []) {
			continue;
		}

		if (taxonomy_exists($taxonomy)) {
			foreach ($object_types as $object_type) {
				register_taxonomy_for_object_type($taxonomy, $object_type);
			}
			continue;
		}

		register_taxonomy(
			$taxonomy,
			$object_types,
			fs_cpt_normalize_taxonomy_args($taxonomy, is_array($entry['args']) ? $entry['args'] : [])
		);
	}
}
add_action('init', 'fs_register_cpt_taxonomies', 11);

/**
 * Built-in posts use the shared core `category` taxonomy by default.
 * Allow config/content-types/post.php to opt out via `wp_categories => false`
 * so posts can use a dedicated taxonomy instead.
 */
add_action('init', function (): void {
	$post_cfg = fs_config_cpt('post');
	if (!is_array($post_cfg)) {
		return;
	}
	if (!fs_content_type_uses_wp_categories($post_cfg)) {
		unregister_taxonomy_for_object_type('category', 'post');
	}
}, 12);

/**
 * Convert inline SVG markup to data URI accepted by register_post_type menu_icon.
 */
function fs_cpt_svg_to_data_uri(string $svg): string
{
	$svg = fs_cpt_svg_apply_fill($svg, '#f3f1f1');
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
	$cpts = fs_config_cpt('all');
	if (!is_array($cpts) || $cpts === []) {
		return '';
	}

	$css = '';
	foreach ($cpts as $post_type => $args) {
		if (!is_string($post_type) || $post_type === '' || !is_array($args)) {
			continue;
		}
		$admin = fs_content_type_admin($post_type);
		$icon_value = $admin['menu_icon'] ?? null;
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
	$cpts = fs_config_cpt('all');
	if (!is_array($cpts) || $cpts === []) {
		return [];
	}
	$ordered = [];
	foreach ($cpts as $post_type => $args) {
		if (!is_string($post_type) || $post_type === '' || !is_array($args)) {
			continue;
		}
		$query = fs_content_type_query($post_type);
		if (!empty($query['menu_order'])) {
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
 * Frontend CPT archives: order from config (orderby / order).
 * Defaults: date DESC; if has_order then menu_order ASC (override with order DESC).
 *
 * @param \WP_Query $query Main query.
 */
function fs_cpt_pre_get_posts_order(\WP_Query $query): void
{
	if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive()) {
		return;
	}
	$pt = $query->get('post_type');
	if (is_array($pt)) {
		$pt = (string) reset($pt);
	}
	if (!is_string($pt) || $pt === '') {
		return;
	}
	if (fs_cpt_type($pt) === 'event') {
		return;
	}
	if (!is_array(fs_config_cpt($pt))) {
		return;
	}
	if ($pt === 'post' && !fs_post_type_has_config_archive('post')) {
		return;
	}
	$query_config = fs_content_type_query($pt);
	$has_order = !empty($query_config['menu_order']);

	$raw_orderby = isset($query_config['orderby']) && is_string($query_config['orderby'])
		? strtolower(trim($query_config['orderby']))
		: '';
	if ($raw_orderby === 'publish_date' || $raw_orderby === 'published') {
		$raw_orderby = 'date';
	}
	$allowed = ['date', 'title', 'menu_order'];
	if ($raw_orderby === '' || !in_array($raw_orderby, $allowed, true)) {
		$raw_orderby = $has_order ? 'menu_order' : 'date';
	}

	$raw_order = isset($query_config['order']) && is_string($query_config['order'])
		? strtoupper(trim($query_config['order']))
		: '';
	if ($raw_order !== 'ASC' && $raw_order !== 'DESC') {
		if ($raw_orderby === 'menu_order') {
			$raw_order = 'ASC';
		} elseif ($raw_orderby === 'date') {
			$raw_order = 'DESC';
		} else {
			$raw_order = 'ASC';
		}
	}

	if ($raw_orderby === 'menu_order') {
		$query->set('orderby', ['menu_order' => $raw_order, 'date' => 'DESC']);
		return;
	}
	if ($raw_orderby === 'title') {
		$query->set('orderby', 'title');
		$query->set('order', $raw_order);
		return;
	}
	$query->set('orderby', 'date');
	$query->set('order', $raw_order);
}

add_action('pre_get_posts', 'fs_cpt_pre_get_posts_order', 15);

/**
 * Resolve current post type in admin contexts (including Quick Edit AJAX).
 */
function fs_cpt_current_admin_post_type(): string
{
	if (isset($_REQUEST['post_type'])) {
		$pt = sanitize_key((string) wp_unslash($_REQUEST['post_type']));
		if ($pt !== '') {
			return $pt;
		}
	}
	if (isset($_REQUEST['post_ID'])) {
		$post_id = (int) $_REQUEST['post_ID'];
		$pt = $post_id > 0 ? get_post_type($post_id) : '';
		if (is_string($pt) && $pt !== '') {
			return sanitize_key($pt);
		}
	}
	global $typenow;
	if (is_string($typenow) && $typenow !== '') {
		return sanitize_key($typenow);
	}
	if (function_exists('get_current_screen')) {
		$screen = get_current_screen();
		if ($screen && isset($screen->post_type) && is_string($screen->post_type) && $screen->post_type !== '') {
			return sanitize_key($screen->post_type);
		}
	}

	return '';
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
	if (!empty($running['bulk'])) {
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
		$desired_order = (int) $post->menu_order;
		$duplicates = get_posts([
			'post_type'      => $post->post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => ['menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'],
			'order'          => 'ASC',
			'exclude'        => [$post_id],
		]);
		$has_collision = false;
		foreach ((array) $duplicates as $duplicate_id) {
			if ((int) get_post_field('menu_order', (int) $duplicate_id) === $desired_order) {
				$has_collision = true;
				break;
			}
		}
		if (!$has_collision) {
			return;
		}

		$to_shift = get_posts([
			'post_type'      => $post->post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => ['menu_order' => 'DESC', 'date' => 'DESC', 'ID' => 'DESC'],
			'order'          => 'DESC',
			'exclude'        => [$post_id],
		]);

		$running['bulk'] = true;
		foreach ((array) $to_shift as $shift_id) {
			$shift_id = (int) $shift_id;
			$current_order = (int) get_post_field('menu_order', $shift_id);
			if ($current_order < $desired_order) {
				continue;
			}
			$running[$shift_id] = true;
			wp_update_post([
				'ID'         => $shift_id,
				'menu_order' => $current_order + 1,
			]);
			unset($running[$shift_id]);
		}
		unset($running['bulk']);
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
 * Insert "Order" column on admin list tables for ordered CPTs.
 *
 * {@see manage_posts_columns} only runs for the built-in `post` type. CPT screens use
 * `manage_{$post_type}_posts_columns` (e.g. manage_project_posts_columns).
 *
 * @param array<string, string> $columns List table columns.
 * @return array<string, string>
 */
function fs_cpt_admin_list_insert_order_column(array $columns): array
{
	$post_type = fs_cpt_current_admin_post_type();
	if ($post_type === '') {
		$post_type = 'post';
	}
	if (!fs_cpt_is_ordered($post_type)) {
		return $columns;
	}
	$reorder_label = __('Order', 'fromscratch');
	$out = [];
	$inserted = false;
	foreach ($columns as $key => $label) {
		$out[$key] = $label;
		// Place order controls after category columns (or near date fallback below).
		if ($key === 'categories' || strncmp($key, 'taxonomy-', 9) === 0) {
			$out['fs_cpt_reorder'] = $reorder_label;
			$inserted = true;
		}
	}
	if (!$inserted) {
		$with_fallback = [];
		foreach ($out as $key => $label) {
			if ($key === 'date') {
				$with_fallback['fs_cpt_reorder'] = $reorder_label;
				$inserted = true;
			}
			$with_fallback[$key] = $label;
		}
		$out = $with_fallback;
	}
	if (!$inserted) {
		$out['fs_cpt_reorder'] = $reorder_label;
	}
	return $out;
}

/**
 * Render Order column cells (move up/down/top/bottom controls).
 *
 * {@see manage_posts_custom_column} only runs for `post`. CPTs need
 * `manage_{$post_type}_posts_custom_column`.
 */
function fs_cpt_admin_list_render_order_column(string $column, int $post_id): void
{
	if ($column !== 'fs_cpt_reorder') {
		return;
	}
	$post = get_post($post_id);
	if (!$post instanceof \WP_Post || !fs_cpt_is_ordered($post->post_type) || !current_user_can('edit_post', $post_id)) {
		return;
	}
	static $ordered_cache = [];
	if (!isset($ordered_cache[$post->post_type])) {
		$ids = get_posts([
			'post_type'      => $post->post_type,
			'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC', 'ID' => 'ASC'],
		]);
		$ordered_cache[$post->post_type] = array_values(array_map('intval', is_array($ids) ? $ids : []));
	}
	$ordered_ids = $ordered_cache[$post->post_type];
	$idx = array_search($post_id, $ordered_ids, true);
	$is_first = $idx === 0;
	$is_last = $idx === count($ordered_ids) - 1;

	$base = admin_url('edit.php?post_type=' . rawurlencode($post->post_type));
	$mk = function (string $dir, string $label, string $icon_class, bool $disabled = false) use ($base, $post_id): string {
		if ($disabled) {
			return '<span class="fs-cpt-reorder-menu__action is-disabled" aria-disabled="true"><span class="dashicons ' . esc_attr($icon_class) . '" aria-hidden="true"></span><span>' . esc_html($label) . '</span></span>';
		}
		$url = add_query_arg([
			'fs_cpt_reorder' => $dir,
			'post_id'        => $post_id,
		], $base);
		$url = wp_nonce_url($url, 'fs_cpt_reorder_' . $dir . '_' . $post_id);
		return '<a class="fs-cpt-reorder-menu__action" href="' . esc_url($url) . '"><span class="dashicons ' . esc_attr($icon_class) . '" aria-hidden="true"></span><span>' . esc_html($label) . '</span></a>';
	};
	$order = (int) get_post_field('menu_order', $post_id);
	echo '<div class="fs-cpt-reorder-menu"><span class="fs-cpt-reorder-menu__order">' . esc_html((string) $order) . '</span>';
	echo '<button type="button" class="button button-small button-icon fs-cpt-reorder-menu__toggle" aria-expanded="false" aria-label="' . esc_attr__('Reorder', 'fromscratch') . '" title="' . esc_attr__('Reorder', 'fromscratch') . '"><svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="currentColor"><path d="M334.5-442.35Q324-452.7 324-468v-258l-80 80q-11 11-25.67 11-14.66 0-25.33-11-11-10.67-11-25.33Q182-686 193-697l142-142q5.4-5 11.7-7.5 6.3-2.5 13.5-2.5t13.5 2.5Q380-844 385-839l142 142q11 11 11 25t-11 25.48Q516-636 501.5-636T476-647l-80-79v258q0 15.3-10.29 25.65Q375.42-432 360.21-432t-25.71-10.35ZM586.3-113.5Q580-116 575-121L433-263q-11-10.91-10.5-25.45.5-14.55 11.5-26.03Q445-325 459.5-325t25.5 11l79 80v-258q0-15.3 10.29-25.65Q584.58-528 599.79-528t25.71 10.35Q636-507.3 636-492v258l80-80q11-11 25.67-11 14.66 0 25.33 11 11 10.67 11 25.33Q778-274 767-263L625-121q-5.4 5-11.7 7.5-6.3 2.5-13.5 2.5t-13.5-2.5Z"/></svg></button>';
	echo '<div class="fs-cpt-reorder-menu__popover" hidden>';
	echo $mk('up', __('Move up', 'fromscratch'), 'dashicons-arrow-up-alt2', $is_first);
	echo $mk('down', __('Move down', 'fromscratch'), 'dashicons-arrow-down-alt2', $is_last);
	echo $mk('top', __('Move to top', 'fromscratch'), 'dashicons-upload', $is_first);
	echo $mk('bottom', __('Move to bottom', 'fromscratch'), 'dashicons-download', $is_last);
	echo '</div>';
	echo '</div>';
}

/**
 * Register list-table hooks per ordered CPT (`manage_posts_*` only applies to built-in Posts).
 */
add_action('init', function (): void {
	$ordered = fs_cpt_ordered_map();
	if ($ordered === []) {
		return;
	}
	foreach (array_keys($ordered) as $post_type) {
		add_filter('manage_' . $post_type . '_posts_columns', 'fs_cpt_admin_list_insert_order_column', 20);
		add_action('manage_' . $post_type . '_posts_custom_column', 'fs_cpt_admin_list_render_order_column', 20, 2);
		add_filter('manage_edit-' . $post_type . '_sortable_columns', function (array $columns): array {
			$columns['fs_cpt_reorder'] = 'menu_order';
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

add_action('admin_head', function (): void {
	global $pagenow;
	if ($pagenow !== 'edit.php') {
		return;
	}
	$post_type = fs_cpt_current_admin_post_type();
	if ($post_type === '' || !fs_cpt_is_ordered($post_type)) {
		return;
	}
	// TODO in scss
	echo '<style>
	.column-fs_cpt_reorder{width:115px; text-align: right;}
	th.column-fs_cpt_reorder a {display: flex; align-items: center; justify-content: flex-end;}
	td.fs_cpt_reorder.column-fs_cpt_reorder{white-space:nowrap;}
	.fs-cpt-reorder-menu{position:relative;display:inline-flex;align-items:center;gap:8px}
	.fs-cpt-reorder-menu__order{display:inline-block;min-width:18px;text-align:right;font-variant-numeric:tabular-nums;}
	.fs-cpt-reorder-menu__toggle .dashicons{font-size:16px;line-height:18px;width:16px;height:16px}
	.fs-cpt-reorder-menu__popover{position:absolute;right:100%;top:0;z-index:1000;display:flex;gap:4px;flex-direction:column;padding:6px;margin-left:6px;min-width:170px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.15)}
	.fs-cpt-reorder-menu__popover[hidden]{display:none}
	.fs-cpt-reorder-menu__action{display:inline-flex;align-items:center;gap:6px;padding:4px 6px;border-radius:3px;color:#2271b1;text-decoration:none}
	.fs-cpt-reorder-menu__action:hover{background:#f0f6fc;color:#135e96}
	.fs-cpt-reorder-menu__action .dashicons{font-size:14px;width:14px;height:14px;line-height:14px}
	.fs-cpt-reorder-menu__action.is-disabled{color:#8c8f94;cursor:not-allowed;background:transparent}
	</style>';
});

add_action('admin_footer', function (): void {
	global $pagenow;
	if ($pagenow !== 'edit.php') {
		return;
	}
	$post_type = fs_cpt_current_admin_post_type();
	if ($post_type === '' || !fs_cpt_is_ordered($post_type)) {
		return;
	}
	$has_explicit_orderby = !empty($_GET['orderby']);
	$default_dir = strtoupper((string) ($_GET['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
	
	// TODO in ja
	echo '<script>
	(function(){
		var defaultOrderActive = ' . ($has_explicit_orderby ? 'false' : 'true') . ';
		var defaultOrderDir = "' . esc_js($default_dir) . '";
		if(defaultOrderActive){
			var orderTh=document.querySelector("th.column-fs_cpt_reorder");
			var orderLink=orderTh?orderTh.querySelector("a"):null;
			if(orderTh){
				orderTh.classList.remove("sortable","asc","desc");
				orderTh.classList.add(defaultOrderDir==="DESC"?"desc":"asc","sorted");
				orderTh.setAttribute("aria-sort",defaultOrderDir==="DESC"?"descending":"ascending");
			}
			if(orderLink){
				orderLink.setAttribute("aria-current","true");
			}
			var dateTh=document.querySelector("th.column-date");
			if(dateTh){
				dateTh.classList.remove("sorted","asc","desc");
				if(!dateTh.classList.contains("sortable")){dateTh.classList.add("sortable");}
				dateTh.removeAttribute("aria-sort");
			}
		}
		function positionPopover(menu,pop){
			var btn=menu.querySelector(".fs-cpt-reorder-menu__toggle");
			if(!btn||!pop) return;
			var rect=btn.getBoundingClientRect();
			var margin=8;
			var popW=pop.offsetWidth||170;
			var popH=pop.offsetHeight||140;
			var left=rect.left-popW-margin;
			if(left<margin){left=margin;}
			var top=rect.top;
			if(top+popH>window.innerHeight-margin){
				top=rect.bottom-popH;
			}
			if(top<margin){top=margin;}
			pop.style.position="fixed";
			pop.style.left=left+"px";
			pop.style.top=top+"px";
			pop.style.right="auto";
		}
		function closeAll(exceptMenu){
			document.querySelectorAll(".fs-cpt-reorder-menu").forEach(function(menu){
				if(exceptMenu && menu===exceptMenu) return;
				var pop=menu.querySelector(".fs-cpt-reorder-menu__popover");
				var btn=menu.querySelector(".fs-cpt-reorder-menu__toggle");
				if(pop){
					pop.hidden=true;
					pop.style.position="";
					pop.style.left="";
					pop.style.top="";
					pop.style.right="";
				}
				if(btn){btn.setAttribute("aria-expanded","false");}
			});
		}
		document.addEventListener("click",function(e){
			var btn=e.target.closest(".fs-cpt-reorder-menu__toggle");
			if(btn){
				var menu=btn.closest(".fs-cpt-reorder-menu");
				var pop=menu?menu.querySelector(".fs-cpt-reorder-menu__popover"):null;
				if(!menu||!pop) return;
				var willOpen=pop.hidden;
				closeAll(menu);
				pop.hidden=!willOpen;
				btn.setAttribute("aria-expanded",willOpen?"true":"false");
				if(willOpen){positionPopover(menu,pop);}
				return;
			}
			if(!e.target.closest(".fs-cpt-reorder-menu")){
				closeAll(null);
			}
		});
		window.addEventListener("resize",function(){
			document.querySelectorAll(".fs-cpt-reorder-menu").forEach(function(menu){
				var pop=menu.querySelector(".fs-cpt-reorder-menu__popover");
				if(pop && !pop.hidden){positionPopover(menu,pop);}
			});
		});
		document.addEventListener("keydown",function(e){
			if(e.key==="Escape"){closeAll(null);}
		});
	})();
	</script>';
});

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

/**
 * Post type for the current archive listing (CPT archives and post taxonomy archives only).
 * Does not cover the blog posts index (`is_home()`); FromScratch does not ship a default posts listing.
 */
function fs_archive_current_post_type(): string
{
	if (is_post_type_archive()) {
		$pto = get_query_var('post_type');
		if (is_array($pto)) {
			return (string) ($pto[0] ?? 'post');
		}

		return is_string($pto) && $pto !== '' ? $pto : 'post';
	}

	if (is_category() || is_tag() || is_author() || is_date()) {
		return 'post';
	}

	return '';
}

/**
 * Archive layout from config: `grid` | `list` (default `list`).
 */
function fs_archive_design(?string $post_type = null): string
{
	if ($post_type === null || $post_type === '') {
		$post_type = fs_archive_current_post_type();
	}

	$archive = fs_content_type_archive($post_type);
	$design = isset($archive['design']) && is_string($archive['design']) ? $archive['design'] : 'list';

	return in_array($design, ['grid', 'list'], true) ? $design : 'list';
}

/**
 * Theme CPT kind from config (`type`). Default `default`; use `event` for event behaviour.
 */
function fs_cpt_type(?string $post_type = null): string
{
	if ($post_type === null || $post_type === '') {
		$post_type = function_exists('get_post_type') ? (string) get_post_type() : '';
	}
	if ($post_type === '') {
		return 'default';
	}

	$cfg = fs_config_cpt($post_type);
	if (!is_array($cfg)) {
		return 'default';
	}

	$type = isset($cfg['type']) && is_string($cfg['type']) ? strtolower(trim($cfg['type'])) : 'default';

	return in_array($type, ['default', 'event'], true) ? $type : 'default';
}

/**
 * CPT slugs in config with the given `type`.
 *
 * @return string[]
 */
function fs_cpt_slugs_by_type(string $type): array
{
	$type = strtolower(trim($type));
	$cpts = fs_config_cpt('all');
	if (!is_array($cpts)) {
		return [];
	}

	$slugs = [];
	foreach ($cpts as $slug => $cfg) {
		if (!is_string($slug) || $slug === '' || !is_array($cfg)) {
			continue;
		}
		if (fs_cpt_type($slug) === $type) {
			$slugs[] = $slug;
		}
	}

	return $slugs;
}

/**
 * Event-specific options from config (`event_options`). Reserved for future archive/editor behaviour.
 *
 * @return array<string, mixed>
 */
function fs_cpt_event_options(?string $post_type = null): array
{
	if ($post_type === null || $post_type === '') {
		$post_type = fs_archive_current_post_type();
	}
	if ($post_type === '' || fs_cpt_type($post_type) !== 'event') {
		return [];
	}

	$cfg = fs_config_cpt($post_type);
	if (!is_array($cfg) || !isset($cfg['event_options']) || !is_array($cfg['event_options'])) {
		return [];
	}

	return $cfg['event_options'];
}

/**
 * Theme copy from config (`texts` array). Falls back to defaults when not set.
 */
function fs_cpt_text(string $key, ?string $post_type = null): string
{
	$aliases = [
		'archive_empty' => 'empty',
	];
	$key = $aliases[$key] ?? $key;

	$defaults = [
		'empty' => 'No posts found.',
		'heading' => '',
	];

	if ($post_type === null || $post_type === '') {
		$post_type = fs_archive_current_post_type();
	}

	if ($post_type === '') {
		$default = $defaults[$key] ?? '';

		return $default !== '' ? __($default, 'fromscratch') : '';
	}

	$archive = fs_content_type_archive($post_type);
	$texts = isset($archive['texts']) && is_array($archive['texts']) ? $archive['texts'] : [];
	if (isset($texts[$key]) && is_string($texts[$key]) && $texts[$key] !== '') {
		return __($texts[$key], 'fromscratch');
	}

	$default = $defaults[$key] ?? '';

	return $default !== '' ? __($default, 'fromscratch') : '';
}

/**
 * Public archive label (breadcrumbs, archive &lt;h1&gt;, etc.).
 * Uses `archive.texts.heading` when set, else the post type plural label (translated at output).
 */
function fs_cpt_archive_label(string $post_type): string
{
	if ($post_type === '') {
		return '';
	}

	$heading = fs_cpt_text('heading', $post_type);
	if ($heading !== '') {
		return $heading;
	}

	$obj = get_post_type_object($post_type);
	if ($obj instanceof \WP_Post_Type && isset($obj->labels->name) && is_string($obj->labels->name) && $obj->labels->name !== '') {
		return __($obj->labels->name, 'fromscratch');
	}

	return '';
}

/**
 * Archive &lt;h1&gt; from `archive.texts.heading`, else post type label / WP archive title.
 */
function fs_archive_heading(): string
{
	$post_type = fs_archive_current_post_type();
	if ($post_type !== '') {
		$heading = fs_cpt_archive_label($post_type);
		if ($heading !== '') {
			return $heading;
		}
	}

	if (is_post_type_archive()) {
		$pto = get_queried_object();
		if ($pto instanceof \WP_Post_Type && isset($pto->labels->name)) {
			return (string) $pto->labels->name;
		}
	}

	return (string) get_the_archive_title();
}

/**
 * `type` for the current archive listing (from {@see fs_archive_current_post_type()}).
 */
function fs_archive_cpt_type(): string
{
	$post_type = fs_archive_current_post_type();

	return $post_type !== '' ? fs_cpt_type($post_type) : 'default';
}

/**
 * Whether the main query is a CPT archive with `type` => `event`.
 */
function fs_is_event_archive(): bool
{
	return is_post_type_archive() && fs_archive_cpt_type() === 'event';
}

// Register after theme textdomain is loaded (init priority 1 in inc/language.php).
add_action('init', 'fs_register_cpts', 2);
