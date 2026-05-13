<?php

defined('ABSPATH') || exit;

/**
 * Resolve an image URL with fallbacks: primary attachment → Theme settings fallback featured image
 * → `assets/img/placeholder.webp` when the file exists.
 *
 * @param int|string|null $image_id Attachment ID (or numeric string). Zero / empty skips straight to fallbacks.
 * @param string|int[] $size Registered image size or [w, h] for {@see wp_get_attachment_image_url()}.
 * @return array{url: string, attachment_id: int} `url` is escaped for HTML; `attachment_id` is the winning attachment (0 for static placeholder).
 */
function fs_image_with_placeholder_resolve($image_id, $size = 'large'): array
{
	$id = absint($image_id);
	if ($id > 0 && wp_attachment_is_image($id)) {
		$url = wp_get_attachment_image_url($id, $size);
		if (is_string($url) && $url !== '') {
			return [
				'url' => esc_url($url),
				'attachment_id' => $id
			];
		}
	}

	$fallback = (int) get_option('fromscratch_feature_image_fallback', 0);
	if ($fallback > 0 && wp_attachment_is_image($fallback)) {
		$url = wp_get_attachment_image_url($fallback, $size);
		if (is_string($url) && $url !== '') {
			return [
				'url' => esc_url($url),
				'attachment_id' => $fallback
			];
		}
	}

	$static = trailingslashit(get_template_directory()) . 'assets/img/placeholder.webp';
	if (is_file($static) && is_readable($static)) {

		$dimensions = @getimagesize($static) ?: [0, 0];

		return [
			'url' => fs_asset_url('/img/placeholder.webp'),
			'width'  => $dimensions[0],
			'height' => $dimensions[1],
			'attachment_id' => 0
		];
	}

	return ['url' => '', 'attachment_id' => 0];
}

/**
 * Image URL with placeholder fallbacks (see {@see fs_image_with_placeholder_resolve()}).
 *
 * @param int|string|null $image_id Primary attachment ID.
 * @param string|int[] $size Image size for attachment URLs.
 * @return string Escaped URL or empty string.
 */
function fs_image_with_placeholder_url($image_id, $size = 'large'): string
{
	return fs_image_with_placeholder_resolve($image_id, $size)['url'];
}

/**
 * `<img>` for the resolved image. Media Library results (featured image or theme “fallback featured image” option)
 * go through {@see fs_img()} (`srcset` / `sizes` / dimensions). The on-disk `assets/img/placeholder.webp` branch is
 * a plain `<img src="…">` only (small asset, no `srcset` unless you pass attrs).
 *
 * @param int|string|null $image_id Primary attachment ID.
 * @param string|int[] $size Image size for attachment URLs.
 * @param array<string, mixed> $attr Extra attributes (class, loading, sizes, alt, …). Do not set `src` for attachments.
 */
function fs_image_with_placeholder($image_id, $size = 'large', array $attr = []): string
{
	$resolved = fs_image_with_placeholder_resolve($image_id, $size);
	if ($resolved['url'] === '') {
		return '';
	}

	$attachment_id = $resolved['attachment_id'];

	if ($attachment_id > 0) {
		unset($attr['src']);
		return fs_img($attachment_id, $size, $attr);
	}

	if (!array_key_exists('alt', $attr) || $attr['alt'] === null || $attr['alt'] === '') {
		$attr['alt'] = '';
	}

	$defaults = [
		'loading'  => 'lazy',
		'decoding' => 'async',
	];

	foreach ($defaults as $k => $v) {
		if (!array_key_exists($k, $attr)) {
			$attr[$k] = $v;
		}
	}

	$attr['src'] = $resolved['url'];

	if (!empty($resolved['width']) && !empty($resolved['height'])) {
		$attr['width'] = $resolved['width'];
		$attr['height'] = $resolved['height'];
	}

	$html = '<img';
	foreach ($attr as $name => $value) {
		if ($value === null || $value === false) {
			continue;
		}
		if ($value === true) {
			$html .= ' ' . esc_attr((string) $name);
			continue;
		}
		$html .= ' ' . esc_attr((string) $name) . '="' . esc_attr((string) $value) . '"';
	}
	$html .= ' />';

	return $html;
}
