<?php

defined('ABSPATH') || exit;

/**
 * Convert uploaded JPEG/PNG image sizes to WebP when Developer → Features: Convert images to WebP is enabled.
 * By default the original full-size file is kept; all other sizes are converted to WebP only.
 * Option webp_convert_original (config) can also convert the original to WebP.
 * JPEG → lossy WebP (quality from config). PNG → lossless WebP when possible (Imagick), else high-quality (GD).
 */

/**
 * Whether the server can generate WebP (GD or Imagick with WebP support).
 *
 * @return bool
 */
function fs_webp_supported(): bool
{
	if (extension_loaded('imagick')) {
		$formats = \Imagick::queryFormats('WEBP');
		if (!empty($formats)) {
			return true;
		}
	}
	if (extension_loaded('gd') && function_exists('imagewebp')) {
		return true;
	}
	return false;
}

/**
 * Get lossy WebP quality from config (1–100).
 *
 * @return int
 */
function fs_webp_quality(): int
{
	$q = function_exists('fs_config') ? fs_config('webp_quality') : null;
	$q = is_numeric($q) ? (int) $q : 82;
	return max(1, min(100, $q));
}

/**
 * Whether to convert the original (full-size) image to WebP as well.
 * Set in Developer → Features when "Convert images to WebP" is enabled.
 *
 * @return bool
 */
function fs_webp_convert_original(): bool
{
	return function_exists('fs_theme_feature_enabled') && fs_theme_feature_enabled('webp_convert_original');
}

/**
 * Convert a single image file to WebP. Original is deleted on success.
 *
 * @param string $source_path Full path to JPEG or PNG file.
 * @param string $mime_type   image/jpeg or image/png.
 * @param bool   $lossless    Use lossless (PNG); otherwise lossy with fs_webp_quality().
 * @return bool True if WebP was created and source was removed.
 */
function fs_webp_convert_file(string $source_path, string $mime_type, bool $lossless): bool
{
	if (!file_exists($source_path) || !is_readable($source_path)) {
		return false;
	}
	$dir = dirname($source_path);
	$base = pathinfo($source_path, PATHINFO_FILENAME);
	$webp_path = $dir . '/' . $base . '.webp';

	$quality = $lossless ? 100 : fs_webp_quality();
	$ok = false;

	if (extension_loaded('imagick')) {
		try {
			$im = new \Imagick($source_path);
			$im->setImageFormat('webp');
			if ($lossless) {
				$im->setOption('webp:lossless', 'true');
			} else {
				$im->setCompressionQuality($quality);
			}
			$im->writeImage($webp_path);
			$im->clear();
			$im->destroy();
			$ok = file_exists($webp_path);
		} catch (\Exception $e) {
			$ok = false;
		}
	}

	if (!$ok && extension_loaded('gd') && function_exists('imagewebp')) {
		if ($mime_type === 'image/jpeg') {
			$im = @imagecreatefromjpeg($source_path);
		} elseif ($mime_type === 'image/png') {
			$im = @imagecreatefrompng($source_path);
			if ($im) {
				imagealphablending($im, false);
				imagesavealpha($im, true);
			}
		} else {
			$im = false;
		}
		if ($im !== false) {
			$ok = @imagewebp($im, $webp_path, $quality);
			imagedestroy($im);
			if ($ok && file_exists($webp_path)) {
				// Preserve PNG alpha in WebP (GD may not set transparency by default)
				// No extra step needed for GD 2.0+ imagewebp with alpha
			}
		}
	}

	if ($ok && file_exists($webp_path) && is_writable($dir)) {
		@unlink($source_path);
		return true;
	}
	return false;
}

/**
 * Convert all non-full sizes of an attachment to WebP and update metadata.
 *
 * @param array $metadata       Attachment metadata from wp_generate_attachment_metadata.
 * @param int   $attachment_id  Attachment post ID.
 * @return array Modified metadata (sizes point to .webp files).
 */
function fs_webp_convert_attachment_sizes(array $metadata, int $attachment_id): array
{
	if (!function_exists('fs_theme_feature_enabled') || !fs_theme_feature_enabled('webp')) {
		return $metadata;
	}
	if (!fs_webp_supported()) {
		return $metadata;
	}

	$file = get_attached_file($attachment_id);
	if (!$file || !file_exists($file)) {
		return $metadata;
	}

	$mime = get_post_mime_type($attachment_id);
	if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
		return $metadata;
	}

	$upload_dir = dirname($file);
	$lossless_original = ($mime === 'image/png');

	if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
		foreach ($metadata['sizes'] as $size_name => $size_data) {
			if (!isset($size_data['file'], $size_data['mime-type'])) {
				continue;
			}
			$size_mime = $size_data['mime-type'];
			if ($size_mime !== 'image/jpeg' && $size_mime !== 'image/png') {
				continue;
			}
			$source_path = $upload_dir . '/' . $size_data['file'];
			$lossless = ($size_mime === 'image/png');
			if (!fs_webp_convert_file($source_path, $size_mime, $lossless)) {
				continue;
			}
			$base = pathinfo($size_data['file'], PATHINFO_FILENAME);
			$webp_file = $base . '.webp';
			$metadata['sizes'][$size_name]['file'] = $webp_file;
			$metadata['sizes'][$size_name]['mime-type'] = 'image/webp';
		}
	}

	// Optionally convert the original (full-size) to WebP.
	if (fs_webp_convert_original() && file_exists($file)) {
		if (fs_webp_convert_file($file, $mime, $lossless_original)) {
			$new_relative = dirname($metadata['file']) . '/' . pathinfo($metadata['file'], PATHINFO_FILENAME) . '.webp';
			$metadata['file'] = $new_relative;
			$new_full = $upload_dir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.webp';
			update_attached_file($attachment_id, $new_full);
			wp_update_post([
				'ID' => $attachment_id,
				'post_mime_type' => 'image/webp',
			]);
		}
	}

	return $metadata;
}

add_filter('wp_generate_attachment_metadata', 'fs_webp_convert_attachment_sizes', 20, 2);
