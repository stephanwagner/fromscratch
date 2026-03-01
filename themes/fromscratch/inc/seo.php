<?php

defined('ABSPATH') || exit;

/**
 * SEO meta keys (post meta).
 * OG title/description are derived from title/description (with WordPress fallbacks).
 */
const FS_SEO_META_TITLE = '_fs_seo_title';
const FS_SEO_META_DESCRIPTION = '_fs_seo_description';
const FS_SEO_META_OG_IMAGE = '_fs_seo_og_image';
const FS_SEO_META_NOINDEX = '_fs_seo_noindex';

const FS_SEO_DESCRIPTION_TRIM_AT = 160;
const FS_SEO_DESCRIPTION_MAX_LENGTH = 155;
const FS_SEO_DESCRIPTION_MIN_LENGTH = 100;

/**
 * Trim plain text to max length at word boundary; only shortens when over threshold.
 *
 * @param string $text     Plain text (will be stripped of tags and normalized whitespace).
 * @param int    $max     Max length when trimming (default 155).
 * @param int    $trim_at Only trim if text is longer than this (default 160).
 * @return string
 */
function fs_seo_trim_description(string $text, int $max = FS_SEO_DESCRIPTION_MAX_LENGTH, int $trim_at = FS_SEO_DESCRIPTION_TRIM_AT): string
{
	$text = wp_strip_all_tags($text);
	$text = trim(preg_replace('/\s+/', ' ', $text));
	if (mb_strlen($text) <= $trim_at) {
		return $text;
	}
	$text = mb_substr($text, 0, $max);
	$text = preg_replace('/\s+\S*$/u', '', $text);
	return $text . '…';
}

/**
 * Post types that support the SEO panel and SEO meta.
 * Uses fs_theme_post_types() (post, page, theme CPTs); only includes types that support the editor.
 * Filter fs_seo_post_types to modify.
 *
 * @return array<string>
 */
function fs_seo_post_types(): array
{
	$types = fs_theme_post_types();
	$result = [];
	foreach ($types as $name) {
		if (post_type_supports($name, 'editor')) {
			$result[] = $name;
		}
	}
	return apply_filters('fs_seo_post_types', $result);
}

/**
 * Register SEO post meta for post and page (REST + block editor).
 *
 * @return void
 */
function fs_seo_register_meta(): void
{
	$post_types = fs_seo_post_types();
	$auth = function (bool $allowed, string $meta_key, int $post_id): bool {
		return current_user_can('edit_post', $post_id);
	};
	$string_args = [
		'type' => 'string',
		'single' => true,
		'show_in_rest' => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback' => $auth,
	];
	$int_args = [
		'type' => 'integer',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
	];
	$bool_args = [
		'type' => 'boolean',
		'single' => true,
		'show_in_rest' => true,
		'auth_callback' => $auth,
	];

	foreach ($post_types as $post_type) {
		register_post_meta($post_type, FS_SEO_META_TITLE, $string_args);
		register_post_meta($post_type, FS_SEO_META_DESCRIPTION, $string_args);
		register_post_meta($post_type, FS_SEO_META_OG_IMAGE, $int_args);
		register_post_meta($post_type, FS_SEO_META_NOINDEX, $bool_args);
	}
}
add_action('init', 'fs_seo_register_meta');

/**
 * Use SEO title in document title when set (single post/page).
 *
 * @param array<string, string> $title Parts (title, page, tagline).
 * @return array<string, string>
 */
function fs_seo_document_title(array $title): array
{
	if (!is_singular(fs_seo_post_types())) {
		return $title;
	}
	$post_id = get_queried_object_id();
	$seo_title = get_post_meta($post_id, FS_SEO_META_TITLE, true);
	if ($seo_title !== '') {
		$title['title'] = $seo_title;
	}
	return $title;
}
add_filter('document_title_parts', 'fs_seo_document_title');

/**
 * Output explicit index (or noindex) and follow in robots meta.
 * Honors WordPress “Discourage search engines” (blog_public); then per-page “No index” for singular post/page; else index, follow.
 *
 * @param array<string, bool|string> $robots Robots directives passed by wp_robots.
 * @return array<string, bool|string>
 */
function fs_seo_wp_robots(array $robots): array
{
	// WordPress “Discourage search engines from indexing this site” (Settings → Reading)
	if ((int) get_option('blog_public', 1) === 0) {
		$robots['noindex'] = true;
		$robots['follow'] = true;
		return $robots;
	}

	$robots['follow'] = true;
	if (is_singular(fs_seo_post_types())) {
		$post_id = get_queried_object_id();
		$noindex_meta = get_post_meta($post_id, FS_SEO_META_NOINDEX, true);
		$noindex = ($noindex_meta !== '' && $noindex_meta !== false) ? (bool) $noindex_meta : false;
		if ($noindex) {
			$robots['noindex'] = true;
		} else {
			$robots['index'] = true;
		}
	} else {
		$robots['index'] = true;
	}
	return $robots;
}
add_filter('wp_robots', 'fs_seo_wp_robots');

/**
 * Output SEO and Open Graph meta tags in head (single post/page).
 *
 * @return void
 */
function fs_seo_head_meta(): void
{
	if (!is_singular(fs_seo_post_types())) {
		return;
	}

	$post_id = get_queried_object_id();
	$post = get_post($post_id);
	if (!$post) {
		return;
	}

	$title = get_post_meta($post_id, FS_SEO_META_TITLE, true);
	if ($title === '') {
		$title = $post->post_title;
	}
	$description = get_post_meta($post_id, FS_SEO_META_DESCRIPTION, true);
	if ($description === '') {
		if (has_excerpt($post_id)) {
			$description = fs_seo_trim_description(get_the_excerpt($post_id));
		}

		if (empty($description) || mb_strlen($description) < FS_SEO_DESCRIPTION_MIN_LENGTH) {
			$description = fs_seo_trim_description($post->post_content);
		}
	}

	// OG title and description always use title/description (with fallbacks).
	$og_title = $title;
	$og_description = $description;
	$og_image_id = (int) get_post_meta($post_id, FS_SEO_META_OG_IMAGE, true);
	if ($og_image_id <= 0) {
		$og_image_id = (int) get_post_thumbnail_id($post_id);
	}
	$og_image_url = '';
	$og_image_width = 0;
	$og_image_height = 0;
	if ($og_image_id > 0) {
		$img = wp_get_attachment_image_src($og_image_id, 'full');
		if (!empty($img[0])) {
			$og_image_url = $img[0];
			$og_image_width = isset($img[1]) ? (int) $img[1] : 0;
			$og_image_height = isset($img[2]) ? (int) $img[2] : 0;
		}
	}

	$site_name = get_bloginfo('name');
	$url = get_permalink($post_id);

	if ($description !== '') {
		echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
	}
	echo '<meta property="og:type" content="article">' . "\n";
	if ($site_name !== '') {
		echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
	}
	echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
	if ($og_image_url !== '') {
		echo '<meta property="og:image" content="' . esc_url($og_image_url) . '">' . "\n";
		if ($og_image_width > 0) {
			echo '<meta property="og:image:width" content="' . (int) $og_image_width . '">' . "\n";
		}
		if ($og_image_height > 0) {
			echo '<meta property="og:image:height" content="' . (int) $og_image_height . '">' . "\n";
		}
	}
}
add_action('wp_head', 'fs_seo_head_meta');

/**
 * Pass translated SEO panel strings to the block editor script.
 *
 * @return void
 */
function fs_seo_editor_localize(): void
{
	wp_localize_script('fromscratch-editor', 'fromscratchSeo', [
		'postTypes' => fs_seo_post_types(),
		'panelTitle' => __('SEO', 'fromscratch'),
		'titleLabel' => __('Title', 'fromscratch'),
		'titleHelp' => __('Recommended length: up to 60 characters.', 'fromscratch'),
		'descriptionLabel' => __('Description', 'fromscratch'),
		'descriptionHelp' => __('Recommended length: up to 160 characters.', 'fromscratch'),
		'ogImageLabel' => __('Social Media Preview Image', 'fromscratch'),
		'ogImageHelp' => __('Best size: 1200 × 630 px. Fallback: featured image if set.', 'fromscratch'),
		'ogImageButton' => __('Select image', 'fromscratch'),
		'ogImageRemove' => __('Remove image', 'fromscratch'),
		'noindexLabel' => __('No index', 'fromscratch'),
		'noindexHelp' => __('Ask search engines not to index this page.', 'fromscratch'),
	]);
}
add_action('enqueue_block_editor_assets', 'fs_seo_editor_localize', 11);
