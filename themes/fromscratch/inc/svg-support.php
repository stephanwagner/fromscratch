<?php
/**
 * Safe SVG support for WordPress (all users).
 * Allows SVG uploads + sanitizes on upload.
 */

defined('ABSPATH') || exit;

/**
 * Allow SVG MIME type
 */
add_filter('upload_mimes', function ($mimes) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';
	return $mimes;
});

/**
 * Fix SVG preview in Media Library
 */
add_action('admin_head', function () {
	echo '<style>
		.media-icon img[src$=".svg"],
		img[src$=".svg"].attachment-post-thumbnail {
			width: 100% !important;
			height: auto !important;
		}
	</style>';
});

/**
 * Sanitize SVG on upload (DOM-based)
 */
add_filter('wp_handle_upload_prefilter', function ($file) {

	if (
		!isset($file['type'], $file['tmp_name']) ||
		$file['type'] !== 'image/svg+xml'
	) {
		return $file;
	}

	$svg = file_get_contents($file['tmp_name']);
	if (!$svg) {
		return $file;
	}

	$sanitized = fs_svg_sanitize($svg);

	if ($sanitized === '') {
		$file['error'] = __('Invalid or unsafe SVG file.', 'essential');
		return $file;
	}

	file_put_contents($file['tmp_name'], $sanitized);

	return $file;
});

/**
 * 4️⃣ DOM-based SVG sanitizer
 */
function fs_svg_sanitize(string $svg): string
{
	libxml_use_internal_errors(true);

	// Remove XML/DOCTYPE/comments first
	$svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
	$svg = preg_replace('/<!DOCTYPE.*?>/i', '', $svg);
	$svg = preg_replace('/<!--.*?-->/s', '', $svg);

	$dom = new DOMDocument();
	if (!$dom->loadXML($svg, LIBXML_NONET | LIBXML_NOENT | LIBXML_COMPACT)) {
		return '';
	}

	$svgEl = $dom->documentElement;
	if (!$svgEl || strtolower($svgEl->tagName) !== 'svg') {
		return '';
	}

	// Allowed tags
	$allowed_tags = [
		'svg', 'g', 'path', 'rect', 'circle', 'ellipse',
		'line', 'polyline', 'polygon',
		'defs', 'lineargradient', 'radialgradient', 'stop',
		'title'
	];

	// Allowed attributes
	$allowed_attrs = [
		'xmlns', 'viewbox', 'width', 'height',
		'fill', 'stroke', 'stroke-width', 'fill-rule',
		'transform', 'class', 'id',
		'cx', 'cy', 'r', 'rx', 'ry',
		'x', 'y', 'x1', 'y1', 'x2', 'y2',
		'points',
		'gradientunits', 'offset',
		'stop-color', 'stop-opacity',
		'aria-hidden', 'role', 'focusable',
	];

	$xpath = new DOMXPath($dom);

	// Remove forbidden elements
	foreach ($xpath->query('//*') as $node) {
		if (!in_array(strtolower($node->nodeName), $allowed_tags, true)) {
			$node->parentNode->removeChild($node);
			continue;
		}

		// Remove forbidden attributes
		if ($node->hasAttributes()) {
			foreach (iterator_to_array($node->attributes) as $attr) {
				$name  = strtolower($attr->name);
				$value = trim($attr->value);

				// Disallow event handlers
				if (strpos($name, 'on') === 0) {
					$node->removeAttributeNode($attr);
					continue;
				}

				// Disallow href/xlink entirely
				if ($name === 'href' || $name === 'xlink:href') {
					$node->removeAttributeNode($attr);
					continue;
				}

				// Disallow external / data URLs
				if (preg_match('/url\s*\(\s*(?!#)[^)]+\)/i', $value)) {
					$node->removeAttributeNode($attr);
					continue;
				}

				if (!in_array($name, $allowed_attrs, true)) {
					$node->removeAttributeNode($attr);
				}
			}
		}
	}

	return $dom->saveXML($dom->documentElement);
}
