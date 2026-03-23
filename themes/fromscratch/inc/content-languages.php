<?php

defined('ABSPATH') || exit;

/**
 * Content languages: taxonomy fs_language, translation groups (post meta fs_translation_group),
 * and side panel to link translations or create a copy in another language.
 * Loaded only when Languages feature is enabled (Settings → Developer → Features).
 */

const FS_LANGUAGE_TAXONOMY = 'fs_language';
const FS_THEME_LANGUAGES_OPTION = 'fs_theme_languages';

/**
 * Flush rewrite rules when language options are updated so URL prefix rules stay in sync and 404s are avoided.
 */
add_action('update_option_' . FS_THEME_LANGUAGES_OPTION, function (): void {
	flush_rewrite_rules(false);
}, 10, 0);
const FS_TRANSLATION_GROUP_META = 'fs_translation_group';
/** Post meta key for language slug (synced from taxonomy for fast permalink lookups). */
const FS_LANGUAGE_META = '_fs_lang';

/**
 * Get the translation group for a post.
 *
 * @param int $post_id Post ID.
 * @return int Translation group ID.
 */
function fs_translation_group(int $post_id): int
{
	static $cache = [];

	if (isset($cache[$post_id])) {
		return $cache[$post_id];
	}

	$group = (int) get_post_meta($post_id, FS_TRANSLATION_GROUP_META, true);
	$cache[$post_id] = $group > 0 ? $group : $post_id;

	return $cache[$post_id];
}

/**
 * Get the language slug for a post.
 *
 * @param int $post_id Post ID.
 * @return string Language slug.
 */
function fs_post_language_slug(int $post_id): string
{
	static $cache = [];

	if (isset($cache[$post_id])) {
		return $cache[$post_id];
	}

	$lang = get_post_meta($post_id, FS_LANGUAGE_META, true);
	$cache[$post_id] = $lang ?: '';

	return $cache[$post_id];
}

/**
 * Get the translation group map for a post type.
 *
 * @param int $group_id Translation group ID.
 * @param string $post_type Post type.
 * @return array<string, int> Language slug => post ID.
 */
function fs_translation_group_map(int $group_id, string $post_type): array
{
	static $cache = [];

	$cache_key = $group_id . '|' . $post_type;
	if (isset($cache[$cache_key])) {
		return $cache[$cache_key];
	}

	global $wpdb;

	$sql = $wpdb->prepare(
		"
		SELECT p.ID, pm.meta_value AS lang
		FROM {$wpdb->posts} p
		JOIN {$wpdb->postmeta} g 
			ON g.post_id = p.ID 
			AND g.meta_key = %s
		LEFT JOIN {$wpdb->postmeta} pm 
			ON pm.post_id = p.ID 
			AND pm.meta_key = %s
		WHERE g.meta_value = %s
		AND p.post_type = %s
	",
		FS_TRANSLATION_GROUP_META,
		FS_LANGUAGE_META,
		(string)$group_id,
		$post_type
	);

	$rows = $wpdb->get_results($sql);

	$map = [];

	foreach ($rows as $row) {
		$lang = $row->lang ?: fs_get_default_language();
		$map[$lang] = (int)$row->ID;
	}

	return $cache[$cache_key] = $map;
}

/**
 * Post types that get the language taxonomy and translation panel.
 *
 * @return string[]
 */
function fs_language_post_types(): array
{
	$types = array_keys(get_post_types(['public' => true], 'names'));
	return array_values(array_unique(array_merge(['post', 'page'], $types)));
}

/**
 * Register taxonomy and sync terms from the language list.
 */
add_action('init', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}

	$languages = fs_get_content_languages();
	if (empty($languages)) {
		return;
	}

	register_taxonomy(FS_LANGUAGE_TAXONOMY, fs_language_post_types(), [
		'public'       => false,
		'show_ui'      => false,
		'show_in_rest' => true,
		'rewrite'      => false,
		'query_var'    => false,
		'labels'       => [
			'name' => __('Language', 'fromscratch'),
		],
		'hierarchical' => true,
	]);
}, 5);

/**
 * Sync fs_language terms from the configured language list (slug = id, name = label via fs_content_language_label).
 */
add_action('init', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	if (!taxonomy_exists(FS_LANGUAGE_TAXONOMY)) {
		return;
	}

	$languages = fs_get_content_languages();
	foreach ($languages as $lang) {
		$slug = isset($lang['id']) ? (string) $lang['id'] : '';
		$name = function_exists('fs_content_language_label') ? fs_content_language_label($lang, 'name') : $slug;
		if ($slug === '') {
			continue;
		}
		$term = get_term_by('slug', $slug, FS_LANGUAGE_TAXONOMY);
		if (!$term) {
			wp_insert_term($name, FS_LANGUAGE_TAXONOMY, ['slug' => $slug]);
		} else {
			wp_update_term($term->term_id, FS_LANGUAGE_TAXONOMY, ['name' => $name]);
		}
	}
}, 15);

/**
 * Add Language column to list tables (Posts, Pages, CPTs).
 */
add_filter('manage_posts_columns', 'fs_language_list_add_column');
add_filter('manage_pages_columns', 'fs_language_list_add_column');

function fs_language_list_add_column(array $columns): array
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $columns;
	}
	$new = [];
	foreach ($columns as $key => $label) {
		$new[$key] = $label;
		if ($key === 'title') {
			$new['fs_language'] = __('Language', 'fromscratch');
		}
	}
	if (!isset($new['fs_language'])) {
		$new['fs_language'] = __('Language', 'fromscratch');
	}
	return $new;
}

add_action('manage_posts_custom_column', 'fs_language_list_column_content', 10, 2);
add_action('manage_pages_custom_column', 'fs_language_list_column_content', 10, 2);

function fs_language_list_column_content(string $column, int $post_id): void
{
	if ($column !== 'fs_language') {
		return;
	}
	static $rendered = [];
	$key = $post_id . ':' . $column;
	if (isset($rendered[$key])) {
		return;
	}
	$rendered[$key] = true;
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	$term = fs_language_get_post_language($post_id);
	if ($term) {
		echo esc_html($term->name);
	} else {
		echo '<span aria-hidden="true">—</span>';
	}
}

/**
 * Add Language column to custom post type list tables.
 */
add_action('admin_init', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	$types = fs_language_post_types();
	foreach ($types as $type) {
		if ($type === 'post' || $type === 'page') {
			continue;
		}
		add_filter('manage_' . $type . '_posts_columns', 'fs_language_list_add_column');
		add_action('manage_' . $type . '_posts_custom_column', 'fs_language_list_column_content', 10, 2);
	}
}, 20);

/**
 * Add language filter row below the views (All | Published | Drafts | …): "All Languages | English | German".
 * WordPress passes an array of view id => html; we add one more entry so it appears as an extra segment (on its own line via CSS).
 */
add_filter('views_edit-post', 'fs_language_views_row', 10, 1);
add_filter('views_edit-page', 'fs_language_views_row', 10, 1);

function fs_language_views_row(array $views): array
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $views;
	}
	$screen = get_current_screen();
	$post_type = $screen && isset($screen->post_type) ? $screen->post_type : 'post';
	$html = fs_language_filter_links($post_type);
	if ($html !== '') {
		$views['fs_language'] = '<span class="fs-language-filters">' . $html . '</span>';
	}
	return $views;
}

add_action('admin_init', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	$types = fs_language_post_types();
	foreach ($types as $type) {
		if ($type === 'post' || $type === 'page') {
			continue;
		}
		add_filter('views_edit-' . $type, 'fs_language_views_row', 10, 1);
	}
}, 20);

/**
 * Build the language filter links: All Languages | English | German (plain links, no ul/li).
 *
 * @param string $post_type
 * @return string HTML fragment (links with | between)
 */
function fs_language_filter_links(string $post_type): string
{
	$languages = fs_get_content_languages();
	if (empty($languages)) {
		return '';
	}

	$base_args = ['post_type' => $post_type];
	if ($post_type === 'post') {
		unset($base_args['post_type']);
	}
	foreach ($_GET as $k => $v) {
		if (in_array($k, ['fs_language', 'paged'], true)) {
			continue;
		}
		if (is_string($v) && $v !== '') {
			$base_args[$k] = $v;
		}
	}
	$base_url = add_query_arg($base_args, admin_url('edit.php'));
	$current = isset($_GET['fs_language']) ? sanitize_text_field(wp_unslash($_GET['fs_language'])) : '';

	$links = [];
	$all_label = __('All languages', 'fromscratch');
	$links[] = '<a href="' . esc_url($base_url) . '" class="' . ($current === '' ? 'current' : '') . '">' . esc_html($all_label) . '</a>';

	foreach ($languages as $lang) {
		$slug = $lang['id'] ?? '';
		$name = function_exists('fs_content_language_label') ? fs_content_language_label($lang, 'name') : $slug;
		if ($slug === '') {
			continue;
		}
		$url = add_query_arg('fs_language', $slug, $base_url);
		$links[] = '<a href="' . esc_url($url) . '" class="' . ($current === $slug ? 'current' : '') . '">' . esc_html($name) . '</a>';
	}

	return implode(' | ', $links);
}

/**
 * Register query var so translation lookup queries can skip our menu/post filters (prevent recursion).
 */
add_filter('query_vars', function (array $vars): array {
	$vars[] = 'fs_translation_lookup';
	return $vars;
});

/**
 * Filter the list by fs_language when fs_language query var is set.
 */
add_action('pre_get_posts', function (\WP_Query $query): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	if (!is_admin()) {
		return;
	}
	$post_type = $query->get('post_type');
	$post_types = is_array($post_type) ? $post_type : ($post_type ? [$post_type] : []);
	$allowed = fs_language_post_types();
	$match = false;
	foreach ($post_types as $pt) {
		if (in_array($pt, $allowed, true)) {
			$match = true;
			break;
		}
	}
	if (!$match && $post_type && !is_array($post_type) && in_array($post_type, $allowed, true)) {
		$match = true;
	}
	if (!$match) {
		return;
	}
	$slug = isset($_GET['fs_language']) ? sanitize_text_field(wp_unslash($_GET['fs_language'])) : '';
	if ($slug === '') {
		return;
	}
	$term = get_term_by('slug', $slug, FS_LANGUAGE_TAXONOMY);
	if (!$term) {
		return;
	}
	$tax_query = (array) $query->get('tax_query');
	$tax_query[] = [
		'taxonomy' => FS_LANGUAGE_TAXONOMY,
		'field'    => 'term_id',
		'terms'    => [(int) $term->term_id],
	];
	$query->set('tax_query', $tax_query);
}, 10, 1);

/**
 * Order list table by translation group: default language first, then translations (indented in UI).
 */
add_filter('posts_clauses', function (array $clauses, \WP_Query $query): array {
	global $wpdb;
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $clauses;
	}
	if (!is_admin() || !function_exists('get_current_screen')) {
		return $clauses;
	}
	$screen = get_current_screen();
	if (!$screen || strpos($screen->id, 'edit-') !== 0) {
		return $clauses;
	}
	$post_type = $query->get('post_type');
	$allowed = fs_language_post_types();
	$ok = false;
	if (is_array($post_type)) {
		$ok = !empty(array_intersect($post_type, $allowed));
	} elseif (in_array($post_type, $allowed, true)) {
		$ok = true;
	}
	if (!$ok) {
		return $clauses;
	}
	$default_slug = fs_get_default_language();
	$default_term_id = 0;
	if ($default_slug !== '' && taxonomy_exists(FS_LANGUAGE_TAXONOMY)) {
		$t = get_term_by('slug', $default_slug, FS_LANGUAGE_TAXONOMY);
		if ($t) {
			$default_term_id = (int) $t->term_id;
		}
	}
	$meta_key = FS_TRANSLATION_GROUP_META;
	$join_alias = 'fs_lang_grp';
	$tr_alias = 'fs_lang_tr';
	$tt_alias = 'fs_lang_tt';
	$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS {$join_alias} ON {$join_alias}.post_id = {$wpdb->posts}.ID AND {$join_alias}.meta_key = '" . esc_sql($meta_key) . "' ";
	if ($default_term_id > 0) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS {$tr_alias} ON {$tr_alias}.object_id = {$wpdb->posts}.ID ";
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS {$tt_alias} ON {$tt_alias}.term_taxonomy_id = {$tr_alias}.term_taxonomy_id AND {$tt_alias}.taxonomy = '" . esc_sql(FS_LANGUAGE_TAXONOMY) . "' AND {$tt_alias}.term_id = " . $default_term_id . " ";
	}
	$group_order = " COALESCE(CAST({$join_alias}.meta_value AS UNSIGNED), {$wpdb->posts}.ID) ";
	$default_first = $default_term_id > 0
		? " (CASE WHEN {$tt_alias}.term_id IS NOT NULL THEN 0 ELSE 1 END) "
		: " 0 ";
	$existing = trim($clauses['orderby']);
	$clauses['orderby'] = $group_order . ' ASC, ' . $default_first . ' ASC' . ($existing !== '' ? ', ' . $existing : '');
	return $clauses;
}, 10, 2);

/**
 * Whether the post is the default-language item in its translation group (no indent in list).
 */
function fs_language_is_default_in_group(int $post_id): bool
{
	$group_id = fs_translation_group($post_id);
	if ($group_id <= 0) {
		return true;
	}
	$term = fs_language_get_post_language($post_id);
	$default_slug = fs_get_default_language();
	if ($default_slug === '') {
		return true;
	}
	return $term && $term->slug === $default_slug;
}

/**
 * Whether the list table row for this post should be indented (translation linked to default language).
 * Only true when the post is not the default in its group AND the group contains a default-language post.
 */
function fs_language_should_indent_in_list(int $post_id): bool
{
	if (fs_language_is_default_in_group($post_id)) {
		return false;
	}
	$group_id = fs_translation_group($post_id);
	if ($group_id <= 0) {
		return false;
	}
	$default_slug = fs_get_default_language();
	if ($default_slug === '') {
		return false;
	}
	$linked = fs_language_get_linked_translations($post_id, $group_id);
	$member_ids = array_values($linked);
	$member_ids[] = $post_id;
	foreach ($member_ids as $member_id) {
		$term = fs_language_get_post_language((int) $member_id);
		if ($term && $term->slug === $default_slug) {
			return true;
		}
	}
	return false;
}

/**
 * Add row class for translation rows that are linked to default language (indent via CSS).
 * Note: post_class filter can receive $class as string or array depending on context.
 */
add_filter('post_class', function ($classes, $class, $post_id) {
	if (!is_array($classes)) {
		return $classes;
	}
	$post_id = (int) $post_id;
	if ($post_id <= 0) {
		return $classes;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $classes;
	}
	if (!is_admin()) {
		return $classes;
	}
	$screen = get_current_screen();
	if (!$screen || strpos($screen->id, 'edit-') !== 0) {
		return $classes;
	}
	if (!in_array(get_post_type($post_id), fs_language_post_types(), true)) {
		return $classes;
	}
	if (fs_language_should_indent_in_list($post_id)) {
		$classes[] = 'fs-list-translation-row';
	}
	return $classes;
}, 10, 3);

/**
 * Pass language panel data to the block editor (same sidebar as SEO / Expirator).
 */
add_action('enqueue_block_editor_assets', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}

	$languages = fs_get_content_languages();
	if (empty($languages)) {
		return;
	}

	$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
	$slug_to_term_id = [];
	$linked = [];
	$create_translation_urls = [];

	if (taxonomy_exists(FS_LANGUAGE_TAXONOMY)) {
		$terms = get_terms(['taxonomy' => FS_LANGUAGE_TAXONOMY, 'hide_empty' => false]);
		foreach ($terms as $t) {
			$slug_to_term_id[$t->slug] = (int) $t->term_id;
		}
	}

	if ($post_id > 0) {
		$group_id = fs_translation_group($post_id);
		if ($group_id <= 0) {
			$group_id = $post_id;
		}

		$current_content = get_post_field('post_content', $post_id);
		$current_word_count = str_word_count(wp_strip_all_tags($current_content));

		$linked_raw = fs_language_get_linked_translations($post_id, $group_id);
		foreach ($linked_raw as $slug => $other_id) {
			$other_content = get_post_field('post_content', $other_id);
			$linked[$slug] = [
				'postId'    => $other_id,
				'editLink'  => get_edit_post_link($other_id, 'raw'),
				'wordCount' => str_word_count(wp_strip_all_tags($other_content)),
			];
		}
		foreach ($languages as $lang) {
			$id = $lang['id'] ?? '';
			if ($id === '' || isset($linked[$id])) {
				continue;
			}
			$create_translation_urls[$id] = wp_nonce_url(
				add_query_arg([
					'action'                => 'fs_create_translation',
					'fs_create_translation' => $id,
					'post_id'               => $post_id,
				], admin_url('admin-post.php')),
				'fs_create_translation_' . $post_id . '_' . $id
			);
		}
	}

	$default_lang = function_exists('fs_get_default_language') ? fs_get_default_language() : '';

	wp_localize_script('fromscratch-editor', 'fromscratchLanguages', [
		'postTypes'             => fs_language_post_types(),
		'panelTitle'            => __('Language', 'fromscratch'),
		'languages'             => $languages,
		'slugToTermId'          => $slug_to_term_id,
		'linked'                => $linked,
		'currentWordCount'      => $current_word_count ?? 0,
		'createTranslationUrls' => $create_translation_urls,
		'defaultLanguage'       => $default_lang,
		'thisContentIsIn'       => __('This content is in', 'fromscratch'),
		'translations'          => __('Translations', 'fromscratch'),
		'current'               => __('current', 'fromscratch'),
		'linkedLabel'           => __('linked', 'fromscratch'),
		'languageSetOnCreate'   => __('Language is set when the content is created and cannot be changed.', 'fromscratch'),
		'createTranslation'     => __('Add', 'fromscratch'),
		'assignLanguage'        => __('Assign', 'fromscratch'),
		'words'                 => __('words', 'fromscratch'),
		'word'                  => __('word', 'fromscratch'),
	]);
}, 11);

/**
 * Get the language term assigned to a post.
 *
 * @param int $post_id
 * @return \WP_Term|null
 */
function fs_language_get_post_language(int $post_id): ?\WP_Term
{
	static $cache = [];

	if (array_key_exists($post_id, $cache)) {
		return $cache[$post_id];
	}

	$terms = wp_get_object_terms($post_id, FS_LANGUAGE_TAXONOMY);

	if (is_wp_error($terms) || empty($terms)) {
		$cache[$post_id] = null;
	} else {
		$cache[$post_id] = $terms[0];
	}

	return $cache[$post_id];
}

/**
 * Get the post ID for a given language from the same translation group.
 * Returns the given post_id if it is already in that language, or a linked translation.
 *
 * @param int $post_id Any post in the translation group.
 * @param string $lang_slug Target language slug (e.g. 'en', 'de').
 * @return int|null Post ID for that language, or null if not found.
 */
function fs_language_get_translation(int $post_id, string $lang_slug): ?int
{
	if ($lang_slug === '') {
		return $post_id;
	}
	$group_id = fs_translation_group($post_id);
	if ($group_id <= 0) {
		$group_id = $post_id;
	}
	$linked = fs_language_get_linked_translations($post_id, $group_id);
	$term = fs_language_get_post_language($post_id);
	$current_slug = $term ? $term->slug : '';
	$linked[$current_slug] = $post_id;
	$default_lang = function_exists('fs_get_default_language') ? fs_get_default_language() : '';
	if ($current_slug === '' && $default_lang !== '') {
		$linked[$default_lang] = $post_id;
	}
	return isset($linked[$lang_slug]) ? (int) $linked[$lang_slug] : null;
}

/**
 * Get linked translation post IDs keyed by language slug (same translation group, other languages).
 *
 * @param int $post_id Current post ID.
 * @param int $group_id Translation group id (post meta fs_translation_group).
 * @return array<string, int> Language slug => post ID.
 */
function fs_language_get_linked_translations(int $post_id, int $group_id): array
{
	if ($group_id <= 0) {
		return [];
	}

	$post_type = get_post_type($post_id);

	$map = fs_translation_group_map($group_id, $post_type);

	foreach ($map as $slug => $id) {
		if ($id === $post_id) {
			unset($map[$slug]);
		}
	}

	return $map;
}

/**
 * Current language for this request: from queried object when singular, else from URL or default.
 *
 * @return string Language slug.
 */
function fs_language_current_request_lang(): string
{
	$default = function_exists('fs_get_default_language') ? fs_get_default_language() : '';
	if (is_singular()) {
		$post = get_queried_object();
		if ($post && isset($post->ID)) {
			$lang = get_post_meta($post->ID, FS_LANGUAGE_META, true);
			if ($lang !== '' && $lang !== false) {
				return $lang;
			}
			$term = fs_language_get_post_language($post->ID);
			if ($term) {
				return $term->slug;
			}
			// Post has no language stored; use URL prefix so /de/impressum/ is treated as DE (avoids default when viewing a standalone non-default page).
			if (function_exists('fs_use_language_url_prefix') && fs_use_language_url_prefix()) {
				$path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
				if ($path !== '') {
					$segments = explode('/', $path);
					$first = $segments[0] ?? '';
					$languages = fs_get_content_languages();
					foreach ($languages as $l) {
						if (isset($l['id']) && (string) $l['id'] === $first) {
							return $first;
						}
					}
				}
			}
			return $default;
		}
	}
	if (function_exists('fs_use_language_url_prefix') && fs_use_language_url_prefix()) {
		$path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
		if ($path !== '') {
			$segments = explode('/', $path);
			$first = $segments[0] ?? '';
			$languages = fs_get_content_languages();
			foreach ($languages as $l) {
				if (isset($l['id']) && (string) $l['id'] === $first) {
					return $first;
				}
			}
		}
	}
	return $default;
}

/**
 * URL for a language's homepage: prefixed home when use_url_prefix, else site home.
 *
 * @param string $lang_slug Language id/slug.
 * @return string
 */
function fs_language_home_url(string $lang_slug): string
{
	if ($lang_slug === '') {
		return home_url('/');
	}
	if (function_exists('fs_use_language_url_prefix') && fs_use_language_url_prefix()) {
		$default = function_exists('fs_get_default_language') ? fs_get_default_language() : '';
		$prefix_default = function_exists('fs_prefix_default_language') && fs_prefix_default_language();
		if ($lang_slug === $default && !$prefix_default) {
			return home_url('/');
		}
		return trailingslashit(home_url('/' . $lang_slug . '/'));
	}
	return home_url('/');
}

/**
 * Menu editor: list default-language pages and pages that have no linked page (standalone).
 * Exclude non-default pages that are linked to a default-language page (translations).
 */
add_action('pre_get_posts', function ($query): void {
	if ($query->get('fs_translation_lookup')) {
		return;
	}
	if (!is_admin()) {
		return;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'nav-menus') {
		return;
	}
	if ($query->get('post_type') !== 'page') {
		return;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	$default_lang = fs_get_default_language();
	if ($default_lang === '') {
		$query->set('suppress_filters', false);
		return;
	}

	$query->set('suppress_filters', false);

	static $exclude_ids = null;
	if ($exclude_ids === null) {
		$exclude_ids = [];
		$pages = get_posts([
			'post_type'      => 'page',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);
		foreach ($pages as $page_id) {
			$term = fs_language_get_post_language($page_id);
			$page_lang = $term ? $term->slug : $default_lang;
			if ($page_lang === $default_lang) {
				continue;
			}
			$group_id = (int) get_post_meta($page_id, FS_TRANSLATION_GROUP_META, true);
			if ($group_id <= 0) {
				$group_id = $page_id;
			}
			$linked = fs_language_get_linked_translations($page_id, $group_id);
			$linked[$page_lang] = $page_id;
			if (isset($linked[$default_lang])) {
				$exclude_ids[] = $page_id;
			}
		}
	}

	if (!empty($exclude_ids)) {
		$existing = $query->get('post__not_in');
		$existing = is_array($existing) ? $existing : [];
		$query->set('post__not_in', array_merge($existing, $exclude_ids));
	}
});

/**
 * Menu editor: do not append language in the_title (posts_results already sets post_title).
 * Prevents double "(EN) (EN)" when the Walker uses get_the_title() which returns the cached modified title.
 */
add_filter('the_title', function ($title, $post_id) {
	if (!is_admin()) {
		return $title;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'nav-menus') {
		return $title;
	}
	if (get_post_type($post_id) !== 'page') {
		return $title;
	}
	return $title;
}, 10, 2);

/**
 * Menu editor: also set language/linked info on post_title in query results (in case the UI uses post_title directly).
 */
add_filter('posts_results', function (array $posts, \WP_Query $query): array {
	if ($query->get('fs_translation_lookup')) {
		return $posts;
	}
	if (!is_admin() || $query->get('post_type') !== 'page') {
		return $posts;
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'nav-menus') {
		return $posts;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $posts;
	}

	foreach ($posts as $post) {
		if (!isset($post->ID) || $post->post_type !== 'page') {
			continue;
		}
		$term = fs_language_get_post_language($post->ID);
		if (!$term) {
			continue;
		}
		$page_lang = $term->slug;
		$post->post_title .= ' (' . strtoupper($page_lang) . ')';

		$group_id = (int) get_post_meta($post->ID, FS_TRANSLATION_GROUP_META, true);
		if ($group_id <= 0) {
			$group_id = $post->ID;
		}
		$linked = fs_language_get_linked_translations($post->ID, $group_id);
		$linked[$page_lang] = $post->ID;
		$other_langs = [];
		foreach ($linked as $lang => $id) {
			if ($lang !== $page_lang) {
				$other_langs[] = strtoupper($lang);
			}
		}
		if (!empty($other_langs)) {
			$post->post_title .= ' [' . implode(', ', $other_langs) . ']';
		}
	}
	return $posts;
}, 10, 2);


/**
 * Frontend menu only: page ID to use for URL/title (translation for current language, or default-language fallback).
 * Not used by the menu builder; see pre_get_posts/posts_results for backend.
 *
 * @param int $page_id Menu item's linked page ID.
 * @return int Post ID to use for URL/title.
 */
function fs_language_menu_page_for_current_lang(int $page_id): int
{
	if (!function_exists('fs_get_default_language') || !in_array('page', fs_language_post_types(), true)) {
		return $page_id;
	}
	$front_id = (int) get_option('page_on_front');

	if ($front_id > 0) {

		$post_group = (int) get_post_meta($page_id, FS_TRANSLATION_GROUP_META, true) ?: $page_id;
		$front_group = (int) get_post_meta($front_id, FS_TRANSLATION_GROUP_META, true) ?: $front_id;

		if ($post_group === $front_group) {
			return fs_language_get_translation($front_id, fs_language_current_request_lang()) ?: $front_id;
		}
	}

	$current = fs_language_current_request_lang();
	$default_lang = fs_get_default_language();
	$group_id = (int) get_post_meta($page_id, FS_TRANSLATION_GROUP_META, true);
	if ($group_id <= 0) {
		$group_id = $page_id;
	}
	$linked = fs_language_get_linked_translations($page_id, $group_id);
	$term = fs_language_get_post_language($page_id);
	$page_lang = $term ? $term->slug : '';
	$linked[$page_lang] = $page_id;
	if (isset($linked[$current])) {
		$translation_id = (int) $linked[$current];
		if (get_post_status($translation_id) === 'publish') {
			return $translation_id;
		}
	}
	// No translation for current language: use default-language page (fallback).
	return (int) ($linked[$default_lang] ?? $page_id);
}

/**
 * Frontend menu only: whether to show this page menu item in the current request language.
 * - Page in a group that has a default-language page: always show (with translation or fallback).
 * - Page not linked to default (standalone in one language): show only when current language matches.
 * Not used by the menu builder.
 *
 * @param int $page_id Menu item's linked page ID.
 * @return bool True if the item should appear in the menu.
 */
function fs_language_show_page_menu_item(int $page_id): bool
{
	if (!function_exists('fs_get_default_language') || !in_array('page', fs_language_post_types(), true)) {
		return true;
	}
	$default_lang = fs_get_default_language();
	$current_lang = fs_language_current_request_lang();
	$group_id = (int) get_post_meta($page_id, FS_TRANSLATION_GROUP_META, true);
	if ($group_id <= 0) {
		$group_id = $page_id;
	}
	$linked = fs_language_get_linked_translations($page_id, $group_id);
	$term = fs_language_get_post_language($page_id);
	$page_lang = $term ? $term->slug : '';
	$linked[$page_lang] = $page_id;
	$group_has_default = isset($linked[$default_lang]);
	if ($group_has_default) {
		return true;
	}
	return $current_lang === $page_lang;
}

// /**
//  * Restrict menu editor page list: only default-language pages, or pages not linked to a default-language page.
//  * Non-default pages that are in a group with a default-language page (translations) are hidden.
//  * Non-default pages not linked to default are shown with their language marked in the title.
//  */
// add_filter('get_pages', function (array $pages, array $parsed_args): array {
// 	if (!is_admin() || !function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
// 		return $pages;
// 	}
// 	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
// 	$on_nav_menus = $screen && $screen->id === 'nav-menus';
// 	$on_customizer = isset($GLOBALS['wp_customize']);
// 	if (!$on_nav_menus && !$on_customizer) {
// 		return $pages;
// 	}

// 	$default_lang = fs_get_default_language();
// 	$languages = fs_get_content_languages();
// 	$lang_names = [];
// 	foreach ($languages as $l) {
// 		$id = isset($l['id']) ? (string) $l['id'] : '';
// 		if ($id !== '') {
// 			$lang_names[$id] = isset($l['name']) && $l['name'] !== '' ? $l['name'] : $id;
// 		}
// 	}

// 	$filtered = [];
// 	foreach ($pages as $page) {
// 		if (!isset($page->ID) || get_post_type($page->ID) !== 'page') {
// 			$filtered[] = $page;
// 			continue;
// 		}
// 		$term = fs_language_get_post_language($page->ID);
// 		$page_lang = $term ? $term->slug : $default_lang;
// 		$is_default = ($page_lang === $default_lang);

// 		if ($is_default) {
// 			$filtered[] = $page;
// 			continue;
// 		}

// 		$group_id = (int) get_post_meta($page->ID, FS_TRANSLATION_GROUP_META, true);
// 		if ($group_id <= 0) {
// 			$group_id = $page->ID;
// 		}
// 		$linked = fs_language_get_linked_translations($page->ID, $group_id);
// 		$linked[$page_lang] = $page->ID;

// 		$group_has_default = false;
// 		foreach (array_keys($linked) as $slug) {
// 			if ($slug === $default_lang) {
// 				$group_has_default = true;
// 				break;
// 			}
// 		}
// 		if ($group_has_default) {
// 			continue;
// 		}

// 		$label = $lang_names[$page_lang] ?? $page_lang;
// 		$page = clone $page;
// 		$page->post_title = $page->post_title . ' (' . $label . ')';
// 		$filtered[] = $page;
// 	}

// 	return $filtered;
// }, 10, 2);

// /**
//  * Add a meta box on the menu editor screen explaining the page list (PHP-rendered).
//  */
// add_action('add_meta_boxes_nav-menus', function (): void {
// 	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
// 		return;
// 	}
// 	add_meta_box(
// 		'fs_language_menu_notice',
// 		__('Menu: pages by language', 'fromscratch'),
// 		function () {
// 			echo '<p style="margin:0; padding:0; font-size:13px;">';
// 			echo esc_html(__('Only default-language pages and pages not linked to the default language are listed. Unlinked pages in other languages are marked with their language. The menu shows the correct translation per language on the frontend.', 'fromscratch'));
// 			echo '</p>';
// 		},
// 		'nav-menus',
// 		'side',
// 		'high'
// 	);
// });

/**
 * Frontend menu display only (decoupled from menu builder).
 * - Page not linked to default language: show item only when current request language matches that page's language.
 * - Page linked to default (or default itself): always show; use translation for current language or default-language fallback for URL/title.
 * Backend menu builder uses pre_get_posts and posts_results on the nav-menus screen only.
 */
add_filter('wp_nav_menu_objects', function (array $items, stdClass $args): array {

	if (is_admin() || !fs_theme_feature_enabled('languages')) {
		return $items;
	}

	$current_lang = fs_language_current_request_lang();
	$default_lang = fs_get_default_language();

	// 1️⃣ collect groups used in menu
	$groups = [];

	foreach ($items as $item) {
		if ($item->object !== 'page') {
			continue;
		}

		$post_id = (int)$item->object_id;
		$group = (int)get_post_meta($post_id, FS_TRANSLATION_GROUP_META, true) ?: $post_id;

		$groups[$group] = $post_id;
	}

	// 2️⃣ preload translation maps
	$maps = [];

	foreach ($groups as $group => $post_id) {
		$post_type = get_post_type($post_id);
		$maps[$group] = fs_translation_group_map($group, $post_type);
	}

	// 3️⃣ resolve menu items
	foreach ($items as $key => $item) {

		if ($item->object !== 'page') {
			continue;
		}

		$page_id = (int)$item->object_id;
		$group = (int)get_post_meta($page_id, FS_TRANSLATION_GROUP_META, true) ?: $page_id;

		$map = $maps[$group] ?? [];

		// translation exists
		if (isset($map[$current_lang])) {
			$show_id = $map[$current_lang];
		}
		// fallback to default
		elseif (isset($map[$default_lang])) {
			$show_id = $map[$default_lang];
		} else {
			unset($items[$key]);
			continue;
		}

		$item->url   = get_permalink($show_id);
		$item->title = get_the_title($show_id);
	}

	return array_values($items);
}, 10, 2);

/**
 * Language toggler shortcode: list of language links with active class on current language.
 * When a language has no translation for the current page, behavior is controlled by Settings → Developer → Languages (no_translation).
 */
add_shortcode('fs_language_switcher', function (): string {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return '';
	}
	$languages = fs_get_content_languages();
	if (empty($languages)) {
		return '';
	}

	$current_lang = fs_language_current_request_lang();
	$behavior = function_exists('fs_language_no_translation_behavior') ? fs_language_no_translation_behavior() : 'disabled';

	$group_id = 0;
	$linked = [];
	if (is_singular()) {
		$post = get_queried_object();
		if ($post && isset($post->ID)) {
			$group_id = (int) get_post_meta($post->ID, FS_TRANSLATION_GROUP_META, true);
			if ($group_id <= 0) {
				$group_id = $post->ID;
			}
			$linked = fs_language_get_linked_translations($post->ID, $group_id);
			$post_lang = get_post_meta($post->ID, FS_LANGUAGE_META, true);
			if ($post_lang === '' || $post_lang === false) {
				$t = fs_language_get_post_language($post->ID);
				$post_lang = $t ? $t->slug : '';
			}
			$linked[$post_lang] = $post->ID;
		}
	}

	$items = [];
	foreach ($languages as $lang) {
		$id = isset($lang['id']) ? (string) $lang['id'] : '';
		if ($id === '') {
			continue;
		}
		$label = function_exists('fs_content_language_label') ? fs_content_language_label($lang, 'name') : $id;
		$is_active = ($id === $current_lang);

		$translation_id = isset($linked[$id]) ? (int) $linked[$id] : 0;
		$has_translation = $translation_id > 0 && get_post_status($translation_id) === 'publish';

		if (!$has_translation) {
			if ($behavior === 'hide') {
				continue;
			}
			if ($behavior === 'disabled') {
				$items[] = '<span class="fs-lang-item fs-lang-disabled' . ($is_active ? ' active' : '') . '" aria-current="' . ($is_active ? 'true' : 'false') . '">' . esc_html($label) . '</span>';
				continue;
			}
			$url = fs_language_home_url($id);
			$items[] = '<a class="fs-lang-item' . ($is_active ? ' active' : '') . '" href="' . esc_url($url) . '" aria-current="' . ($is_active ? 'true' : 'false') . '">' . esc_html($label) . '</a>';
			continue;
		}

		$front_id = (int) get_option('page_on_front');
		$group_id = (int) get_post_meta($translation_id, FS_TRANSLATION_GROUP_META, true) ?: $translation_id;
		$front_group = (int) get_post_meta($front_id, FS_TRANSLATION_GROUP_META, true) ?: $front_id;

		$is_home_translation = ($group_id === $front_group);

		$url = $is_home_translation
			? fs_language_home_url($id)
			: get_permalink($translation_id);

		$items[] = '<a class="fs-lang-item' . ($is_active ? ' active' : '') . '" href="' . esc_url($url) . '" aria-current="' . ($is_active ? 'true' : 'false') . '">' . esc_html($label) . '</a>';
	}

	if (empty($items)) {
		return '';
	}

	return '<ul class="fs-language-toggler"><li>' . implode('</li><li>', $items) . '</li></ul>';
});

/**
 * Ensure translation group is set when a post is saved (block editor saves language term via REST).
 */
add_action('save_post', function (int $post_id): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}
	if (!current_user_can('edit_post', $post_id)) {
		return;
	}
	if (!in_array(get_post_type($post_id), fs_language_post_types(), true)) {
		return;
	}

	$group = fs_translation_group($post_id);
	if ($group <= 0) {
		update_post_meta($post_id, FS_TRANSLATION_GROUP_META, $post_id);
	}

	$term = fs_language_get_post_language($post_id);
	if (!$term && function_exists('fs_get_default_language')) {
		$default_slug = fs_get_default_language();
		if ($default_slug !== '' && taxonomy_exists(FS_LANGUAGE_TAXONOMY)) {
			$default_term = get_term_by('slug', $default_slug, FS_LANGUAGE_TAXONOMY);
			if ($default_term && !is_wp_error($default_term)) {
				wp_set_object_terms($post_id, [(int) $default_term->term_id], FS_LANGUAGE_TAXONOMY);
				$term = $default_term;
			}
		}
	}
	// Sync language slug to meta so permalink filter can use get_post_meta() instead of wp_get_object_terms().
	$slug = $term ? $term->slug : '';
	update_post_meta($post_id, FS_LANGUAGE_META, $slug);
}, 10, 1);

/**
 * Language can only be set when creating content; lock it after first save.
 * Store current language before REST update, then revert any change in rest_after_insert.
 */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $result;
	}
	if (!taxonomy_exists(FS_LANGUAGE_TAXONOMY)) {
		return $result;
	}
	$route = $request->get_route();
	if (!is_string($route) || ($request->get_method() !== 'PUT' && $request->get_method() !== 'POST')) {
		return $result;
	}
	if (!preg_match('#^/wp/v2/(posts|pages|[\w-]+)/(?P<id>\d+)$#', $route, $m)) {
		return $result;
	}
	$post_id = (int) $m['id'];
	if ($post_id <= 0) {
		return $result;
	}
	$post = get_post($post_id);
	if (!$post || !in_array($post->post_type, fs_language_post_types(), true)) {
		return $result;
	}
	if ($post->post_status === 'auto-draft') {
		return $result;
	}
	$terms = wp_get_object_terms($post_id, FS_LANGUAGE_TAXONOMY);
	$term_ids = array_map('intval', wp_list_pluck($terms, 'term_id'));
	fs_language_rest_previous_terms($post_id, $term_ids);
	return $result;
}, 10, 3);

/**
 * @param int $post_id
 * @param array|null $term_ids When set, store these term IDs for this post (before REST update). When null, return stored value.
 * @return array|null Stored term IDs for this post, or null if we didn't store (not an update we're tracking).
 */
function fs_language_rest_previous_terms(int $post_id = 0, ?array $term_ids = null): ?array
{
	static $store = [];
	if ($term_ids !== null && $post_id > 0) {
		$store[$post_id] = $term_ids;
		return $term_ids;
	}
	if ($post_id > 0 && array_key_exists($post_id, $store)) {
		return $store[$post_id];
	}
	return null;
}

function fs_language_rest_revert_language($post, $request, $creating): void
{
	if ($creating) {
		return;
	}
	$previous = fs_language_rest_previous_terms($post->ID);
	if ($previous === null) {
		return;
	}
	$current = wp_get_object_terms($post->ID, FS_LANGUAGE_TAXONOMY);
	$current_ids = array_map('intval', wp_list_pluck($current, 'term_id'));
	sort($previous);
	sort($current_ids);
	if ($previous === $current_ids) {
		return;
	}
	wp_set_object_terms($post->ID, $previous, FS_LANGUAGE_TAXONOMY);
}

add_action('init', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	foreach (fs_language_post_types() as $type) {
		add_filter('rest_after_insert_' . $type, 'fs_language_rest_revert_language', 10, 3);
	}
}, 20);

/**
 * Get a GET parameter by key; also check amp;key (when URL had &amp; in HTML and was sent literally).
 *
 * @param string $key
 * @return string|null
 */
function fs_language_get_request_param(string $key): ?string
{
	if (isset($_GET[$key]) && is_string($_GET[$key])) {
		return $_GET[$key];
	}
	$amp_key = 'amp;' . $key;
	if (isset($_GET[$amp_key]) && is_string($_GET[$amp_key])) {
		return $_GET[$amp_key];
	}
	return null;
}

/**
 * Handle "Create translation" action: duplicate post, set language and group, redirect to edit new post.
 */
add_action('admin_post_fs_create_translation', function (): void {
	if (!current_user_can('edit_posts')) {
		wp_die(esc_html__('You do not have permission to do this.', 'fromscratch'));
	}

	$post_id = fs_language_get_request_param('post_id');
	$post_id = $post_id !== null ? (int) $post_id : 0;
	$lang_slug = fs_language_get_request_param('fs_create_translation');
	$lang_slug = $lang_slug !== null ? sanitize_text_field(wp_unslash($lang_slug)) : '';

	if ($post_id <= 0 || $lang_slug === '') {
		wp_die(esc_html__('Invalid request.', 'fromscratch'));
	}

	$nonce = fs_language_get_request_param('_wpnonce');
	$nonce = $nonce !== null ? $nonce : '';
	if (!wp_verify_nonce($nonce, 'fs_create_translation_' . $post_id . '_' . $lang_slug)) {
		wp_die(esc_html__('Security check failed.', 'fromscratch'));
	}

	$source = get_post($post_id, ARRAY_A);
	if (!$source || !in_array(get_post_type($post_id), fs_language_post_types(), true)) {
		wp_die(esc_html__('Invalid post.', 'fromscratch'));
	}

	$term = get_term_by('slug', $lang_slug, FS_LANGUAGE_TAXONOMY);
	if (!$term) {
		wp_die(esc_html__('Language not found.', 'fromscratch'));
	}

	$current_lang = fs_language_get_post_language($post_id);

	// If the page has no language assigned yet, just assign the selected language instead of creating a copy.
	if (!$current_lang) {
		wp_set_object_terms($post_id, (int) $term->term_id, FS_LANGUAGE_TAXONOMY);
		update_post_meta($post_id, FS_TRANSLATION_GROUP_META, $post_id);
		update_post_meta($post_id, FS_LANGUAGE_META, $lang_slug);
		wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
		exit;
	}

	$group_id = fs_translation_group($post_id);
	if ($group_id <= 0) {
		$group_id = $post_id;
	}

	$new_post = [
		'post_title'   => $source['post_title'],
		'post_content' => $source['post_content'],
		'post_excerpt' => $source['post_excerpt'],
		'post_status'  => 'draft',
		'post_type'    => $source['post_type'],
		'post_author'  => get_current_user_id(),
		'post_parent'  => (int) $source['post_parent'],
		'menu_order'   => (int) $source['menu_order'],
		'comment_status' => $source['comment_status'],
		'ping_status'  => $source['ping_status'],
	];

	$new_id = wp_insert_post($new_post, true);
	if (is_wp_error($new_id)) {
		wp_die(esc_html__('Could not create translation.', 'fromscratch'));
	}

	wp_set_object_terms($new_id, (int) $term->term_id, FS_LANGUAGE_TAXONOMY);
	update_post_meta($new_id, FS_TRANSLATION_GROUP_META, $group_id);
	update_post_meta($new_id, FS_LANGUAGE_META, $lang_slug);

	// Copy meta (optional: copy common meta like thumbnail, SEO, etc.)
	$meta_keys = ['_thumbnail_id', '_fs_seo_title', '_fs_seo_description'];
	foreach ($meta_keys as $key) {
		$val = get_post_meta($post_id, $key, true);
		if ($val !== '') {
			update_post_meta($new_id, $key, $val);
		}
	}

	wp_safe_redirect(admin_url('post.php?post=' . $new_id . '&action=edit'));
	exit;
});

/**
 * Language URL routing: rewrite rules and resolution for translated pages and default language.
 * Rules must be registered in admin too so that permalink flush (Settings → Permalinks → Save)
 * and Languages tab save include them in the stored rules.
 */
add_action('init', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	if (!function_exists('fs_use_language_url_prefix') || !fs_use_language_url_prefix()) {
		return;
	}
	$languages = fs_get_content_languages();
	if (empty($languages)) {
		return;
	}

	foreach ($languages as $lang) {
		$id = isset($lang['id']) ? (string) $lang['id'] : '';
		if ($id === '' || !preg_match('/^[a-z0-9_-]+$/i', $id)) {
			continue;
		}
		// Add rules for all languages so that /en/about/ is seen even when prefix_default is off;
		// we then redirect default-language prefixed URLs to the canonical URL without prefix.

		// Language-prefixed path: /en/about/ or /de/ueber-uns/ (at least one character after the slash).
		add_rewrite_rule(
			'^' . $id . '/(.+?)/?$',
			'index.php?fs_path=$matches[1]&fs_lang=' . $id,
			'top'
		);
		// Language root: /en/ or /de/
		add_rewrite_rule(
			'^' . $id . '/?$',
			'index.php?fs_lang=' . $id,
			'top'
		);
	}
}, 20);

add_filter('query_vars', function (array $vars): array {
	$vars[] = 'fs_lang';
	$vars[] = 'fs_path';
	return $vars;
});

/**
 * Resolve (lang, path) to the post to show. Path can be empty (language root = front page).
 *
 * @return array{id: int, post_type: string}|null
 */
function fs_language_resolve_request(string $lang, string $path): ?array
{
	$path = trim($path, '/');
	$default_lang = fs_get_default_language();

	if ($path === '') {

		$front_id = (int) get_option('page_on_front');

		if (!$front_id) {
			return null;
		}

		$translation = fs_language_get_translation($front_id, $lang);

		if ($translation) {
			return [
				'id' => $translation,
				'post_type' => 'page'
			];
		}

		return [
			'id' => $front_id,
			'post_type' => 'page'
		];
	}

	// Try page by path (supports hierarchical path).
	$page = get_page_by_path($path, OBJECT, 'page');
	if ($page && in_array($page->post_type, fs_language_post_types(), true)) {
		$group_id = (int) get_post_meta($page->ID, FS_TRANSLATION_GROUP_META, true);
		if ($group_id <= 0) {
			$group_id = $page->ID;
		}
		$linked = fs_language_get_linked_translations($page->ID, $group_id);
		$page_lang = fs_language_get_post_language($page->ID);
		$page_slug = $page_lang ? $page_lang->slug : '';
		$linked[$page_slug] = $page->ID;
		if ($page_slug === '' && $default_lang !== '') {
			$linked[$default_lang] = $page->ID;
		}
		if (isset($linked[$lang])) {
			$id = (int) $linked[$lang];
			$post = get_post($id);
			if ($post && $post->post_status === 'publish') {
				return ['id' => $id, 'post_type' => 'page'];
			}
		}
	}

	// Try post by slug.
	$posts = get_posts([
		'post_type'      => 'post',
		'name'           => $path,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	]);
	if (!empty($posts)) {
		$any_id = (int) $posts[0];
		$group_id = (int) get_post_meta($any_id, FS_TRANSLATION_GROUP_META, true);
		if ($group_id <= 0) {
			$group_id = $any_id;
		}
		$linked = fs_language_get_linked_translations($any_id, $group_id);
		$any_lang = fs_language_get_post_language($any_id);
		$any_slug = $any_lang ? $any_lang->slug : '';
		$linked[$any_slug] = $any_id;
		if ($any_slug === '' && $default_lang !== '') {
			$linked[$default_lang] = $any_id;
		}
		if (isset($linked[$lang])) {
			$id = (int) $linked[$lang];
			$p = get_post($id);
			if ($p && $p->post_status === 'publish') {
				return ['id' => $id, 'post_type' => 'post'];
			}
		}
	}

	return null;
}

/**
 * After WordPress parses the request, resolve language-prefixed URLs to the correct post.
 */
add_action('parse_request', function (\WP $wp): void {
	if (is_admin()) {
		return;
	}
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	if (!function_exists('fs_use_language_url_prefix') || !fs_use_language_url_prefix()) {
		return;
	}
	$lang = isset($wp->query_vars['fs_lang']) ? $wp->query_vars['fs_lang'] : '';
	$path = isset($wp->query_vars['fs_path']) ? $wp->query_vars['fs_path'] : '';

	if ($lang === '') {
		return;
	}

	$path = is_string($path) ? trim($path, '/') : '';
	$default_lang = fs_get_default_language();
	$prefix_default = function_exists('fs_prefix_default_language') && fs_prefix_default_language();

	// When prefix_default is off, redirect /en/about/ to /about/ (canonical URL without prefix).
	if (!$prefix_default && $lang === $default_lang) {
		$resolved = fs_language_resolve_request($lang, $path);
		if ($resolved) {
			$post_id = (int) $resolved['id'];
			$redirect_url = $path !== '' ? get_permalink($post_id) : home_url('/');
			if (is_string($redirect_url) && $redirect_url !== '') {
				wp_safe_redirect($redirect_url, 301);
				exit;
			}
		}
		// Not resolved (e.g. /en/nonexistent/): unset and let WP handle (404).
		unset($wp->query_vars['fs_lang'], $wp->query_vars['fs_path']);
		return;
	}

	$resolved = fs_language_resolve_request($lang, $path);

	if ($resolved) {
		$post_type = $resolved['post_type'];
		$post_id = (int) $resolved['id'];
		unset($wp->query_vars['fs_lang'], $wp->query_vars['fs_path'], $wp->query_vars['error']);
		$wp->query_vars['post_type'] = $post_type;
		if ($post_type === 'page') {
			$wp->query_vars['page_id'] = $post_id;
			$wp->query_vars['pagename'] = '';
		} else {
			$wp->query_vars['p'] = $post_id;
		}
		$wp->request = $path;
	} elseif ($path === '') {
		// Language root but no static front page: show blog/home.
		unset($wp->query_vars['fs_lang'], $wp->query_vars['fs_path'], $wp->query_vars['error']);
		$wp->request = '';
	} else {
		// Path not resolved: unset our vars and let WordPress handle (e.g. 404 or other handlers).
		unset($wp->query_vars['fs_lang'], $wp->query_vars['fs_path']);
	}
}, 10, 1);

/**
 * Add language prefix to permalinks when the current request or target is in a prefixed language.
 */
if (!is_admin()) {
	add_filter('post_link', 'fs_language_permalink', 10, 3);
	add_filter('page_link', 'fs_language_permalink', 10, 3);
}

function fs_language_permalink(string $url, $post_id, bool $sample): string
{
	$post_id = $post_id instanceof \WP_Post ? $post_id->ID : (int) $post_id;
	if ($post_id <= 0 || $sample) {
		return $url;
	}

	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return $url;
	}

	if (!function_exists('fs_use_language_url_prefix') || !fs_use_language_url_prefix()) {
		return $url;
	}

	$post_type = get_post_type($post_id);

	static $post_types = null;
	if ($post_types === null) {
		$post_types = fs_language_post_types();
	}

	if (!$post_type || !in_array($post_type, $post_types, true)) {
		return $url;
	}

	static $default_lang = null;
	static $prefix_default = null;

	if ($default_lang === null) {
		$default_lang = fs_get_default_language();
		$prefix_default = function_exists('fs_prefix_default_language') && fs_prefix_default_language();
	}

	$post_lang = fs_post_language_slug($post_id);

	if ($post_lang === '' || $post_lang === false) {
		$term = fs_language_get_post_language($post_id);
		$post_lang = $term ? $term->slug : $default_lang;
	}

	if ($post_lang === $default_lang && !$prefix_default) {
		return $url;
	}

	// Homepage special case
	$front_id = (int) get_option('page_on_front');
	if ($post_type === 'page' && $post_id === $front_id) {
		return fs_language_home_url($post_lang);
	}

	$path = trim(str_replace(home_url('/'), '', $url), '/');
	if ($path === '') {
		return $url;
	}

	static $home = null;
	if ($home === null) {
		$home = trailingslashit(home_url('/'));
	}

	return $home . $post_lang . '/' . $path;
}

/**
 * When language URL prefix is enabled, redirect singular content to its canonical prefixed URL
 * so both /about/ and /en/about/ do not coexist; the canonical version is enforced.
 */
add_action('template_redirect', function (): void {
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}
	if (!function_exists('fs_use_language_url_prefix') || !fs_use_language_url_prefix()) {
		return;
	}
	if (get_query_var('fs_lang')) {
		return;
	}
	if (is_admin() || is_preview() || is_feed()) {
		return;
	}
	if (!is_singular()) {
		return;
	}

	$post = get_queried_object();
	if (!$post || !isset($post->ID, $post->post_type, $post->post_name)) {
		return;
	}
	if (!in_array($post->post_type, fs_language_post_types(), true)) {
		return;
	}

	$lang = get_post_meta($post->ID, FS_LANGUAGE_META, true);
	if ($lang === '' || $lang === false) {
		$term = fs_language_get_post_language($post->ID);
		$lang = $term ? $term->slug : '';
	}
	$default = fs_get_default_language();
	$prefix_default = function_exists('fs_prefix_default_language') && fs_prefix_default_language();

	$current_path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

	// Never redirect the site root so the homepage is always reachable (avoids redirect loops when languages are just enabled).
	if ($current_path === '') {
		return;
	}

	// Skip canonical redirect when the post has no language assigned yet (e.g. right after enabling languages).
	if ($lang === '') {
		return;
	}

	$path = $post->post_type === 'page' ? get_page_uri($post->ID) : $post->post_name;
	$path = trim($path, '/');

	// TODO function that checks if any page is a frontpage
	$front_id = (int) get_option('page_on_front');
	$post_group  = (int) get_post_meta($post->ID, FS_TRANSLATION_GROUP_META, true) ?: $post->ID;
	$front_group = (int) get_post_meta($front_id, FS_TRANSLATION_GROUP_META, true) ?: $front_id;
	$is_homepage = ($post->post_type === 'page' && $post_group === $front_group);

	if ($is_homepage) {
		$canonical = $lang !== $default ? $lang : ($prefix_default ? $lang : '');
	} elseif ($path !== '') {
		$canonical = $lang !== $default ? $lang . '/' . $path : ($prefix_default ? $lang . '/' . $path : $path);
	} else {
		$canonical = $lang !== $default ? $lang : ($prefix_default ? $lang : '');
	}

	if ($current_path !== $canonical) {
		// Never redirect a language root (e.g. /de/) to / — the user is on a valid URL for that language.
		if ($canonical === '' && $current_path !== '') {
			$languages = fs_get_content_languages();
			foreach ($languages as $l) {
				$id = $l['id'] ?? '';
				if ($id !== '' && $current_path === $id) {
					return;
				}
			}
		}
		wp_safe_redirect(home_url('/' . $canonical . '/'), 301);
		exit;
	}
});

// Add hreflang links to head for posts that have linked translations
add_action('wp_head', function () {
	if (!is_singular()) {
		return;
	}

	$post_id = get_queried_object_id();
	$post_type = get_post_type($post_id);

	if (!in_array($post_type, fs_language_post_types(), true)) {
		return;
	}

	$group_id = fs_translation_group($post_id);
	if ($group_id <= 0) {
		$group_id = $post_id;
	}

	$linked = fs_language_get_linked_translations($post_id, $group_id);
	$current_term = fs_language_get_post_language($post_id);
	$default_lang = fs_get_default_language();
	$current_slug = $current_term ? $current_term->slug : $default_lang;
	$linked[$current_slug] = $post_id;
	if ($current_slug === '' && $default_lang !== '') {
		$linked[$default_lang] = $post_id;
	}
	if (empty($linked)) {
		return;
	}

	$front_id = (int) get_option('page_on_front', 0);
	$post_group = fs_translation_group($post_id) ?: $post_id;
	$front_group = $front_id > 0 ? ((int) get_post_meta($front_id, FS_TRANSLATION_GROUP_META, true) ?: $front_id) : 0;
	$is_homepage_group = ($front_id > 0 && $post_group === $front_group);

	foreach ($linked as $slug => $other_id) {
		$url = $is_homepage_group ? fs_language_home_url($slug) : get_permalink($other_id);
		echo '<link rel="alternate" hreflang="' . esc_attr($slug) . '" href="' . esc_url($url) . '">' . "\n";
	}

	$x_default_url = $is_homepage_group ? fs_language_home_url($default_lang) : (isset($linked[$default_lang]) ? get_permalink($linked[$default_lang]) : null);
	if ($x_default_url !== null) {
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($x_default_url) . '">' . "\n";
	}
}, 5);





// TODO TEST

// Shouldnt we rather when we save a page make sure that it is the frontpage?

add_action('init', function () {

	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('languages')) {
		return;
	}

	if (get_option('show_on_front') !== 'page') {
		return;
	}

	$front_id = (int) get_option('page_on_front');
	if (!$front_id) {
		return;
	}

	$default_lang = fs_get_default_language();
	if (!$default_lang) {
		return;
	}

	$term = fs_language_get_post_language($front_id);

	if ($term && $term->slug !== $default_lang) {

		$default_home = fs_language_get_translation($front_id, $default_lang);

		if ($default_home) {
			update_option('page_on_front', $default_home);
		}
	}
});
