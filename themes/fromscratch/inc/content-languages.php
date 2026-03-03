<?php

defined('ABSPATH') || exit;

/**
 * Content languages: taxonomy fs_language, translation groups (post meta fs_translation_group),
 * and side panel to link translations or create a copy in another language.
 * Loaded only when Languages feature is enabled (Settings → Developer → Features).
 */

const FS_LANGUAGE_TAXONOMY = 'fs_language';
const FS_TRANSLATION_GROUP_META = 'fs_translation_group';

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
 * Sync fs_language terms from the configured language list (slug = id, name = nameEnglish).
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
		$name = isset($lang['nameEnglish']) && $lang['nameEnglish'] !== '' ? $lang['nameEnglish'] : $slug;
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
		$group_id = (int) get_post_meta($post_id, FS_TRANSLATION_GROUP_META, true);
		if ($group_id <= 0) {
			$group_id = $post_id;
		}
		$linked_raw = fs_language_get_linked_translations($post_id, $group_id);
		foreach ($linked_raw as $slug => $other_id) {
			$linked[$slug] = [
				'postId'  => $other_id,
				'editLink' => get_edit_post_link($other_id, 'raw'),
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

	wp_localize_script('fromscratch-editor', 'fromscratchLanguages', [
		'postTypes'             => fs_language_post_types(),
		'panelTitle'            => __('Language & translations', 'fromscratch'),
		'languages'             => $languages,
		'slugToTermId'          => $slug_to_term_id,
		'linked'                => $linked,
		'createTranslationUrls' => $create_translation_urls,
		'thisContentIsIn'       => __('This content is in', 'fromscratch'),
		'translations'          => __('Translations', 'fromscratch'),
		'current'               => __('current', 'fromscratch'),
		'linkedLabel'           => __('linked', 'fromscratch'),
		'createTranslation'     => __('Create translation', 'fromscratch'),
		'selectLanguage'        => __('— Select language —', 'fromscratch'),
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
	$terms = wp_get_object_terms($post_id, FS_LANGUAGE_TAXONOMY);
	if (is_wp_error($terms) || empty($terms)) {
		return null;
	}
	return $terms[0];
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
	$out = [];

	$query = new \WP_Query([
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'post__not_in'   => [$post_id],
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			[
				'key'     => FS_TRANSLATION_GROUP_META,
				'value'   => (string) $group_id,
				'compare' => '=',
			],
		],
	]);

	foreach ($query->posts as $other_id) {
		$term = fs_language_get_post_language((int) $other_id);
		if ($term) {
			$out[$term->slug] = (int) $other_id;
		}
	}

	return $out;
}

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

	$group = (int) get_post_meta($post_id, FS_TRANSLATION_GROUP_META, true);
	if ($group <= 0) {
		update_post_meta($post_id, FS_TRANSLATION_GROUP_META, $post_id);
	}
}, 10, 1);

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

	$group_id = (int) get_post_meta($post_id, FS_TRANSLATION_GROUP_META, true);
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
