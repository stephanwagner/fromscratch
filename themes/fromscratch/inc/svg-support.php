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
		.attachment-preview.subtype-svg\+xml img {
			width: calc(100% - 16px) !important;
			height: calc(100% - 16px) !important;
		}
	</style>';
});

/**
 * Sanitize SVG on upload (DOM-based)
 */
add_filter('wp_handle_upload_prefilter', function ($file) {
	global $fs_config;

	if (!isset($fs_config['svg_max_size'])) {
		$max_size = $fs_config['svg_max_size'];
	} else {
		$max_size = 2;
	}

	if (
		!isset($file['type'], $file['tmp_name']) ||
		$file['type'] !== 'image/svg+xml'
	) {
		return $file;
	}

	$size = isset($file['size']) ? (int) $file['size'] : 0;
	if ($size <= 0 || $size / 1024 / 1024 > $max_size) {
		$max_size_formatted = $max_size . ' MB';
		$size_formatted = number_format($size / 1024 / 1024, 2) . ' MB';
		$file['error'] = fs_t('SVG_FILE_MUST_BE_UNDER_MAX_SIZE', ['MAX_SIZE' => $max_size_formatted, 'SIZE' => $size_formatted]);
		return $file;
	}

	$svg = file_get_contents($file['tmp_name']);
	if (!$svg) {
		$file['error'] = fs_t('SVG_FILE_NOT_FOUND');
		return $file;
	}

	$sanitized = fs_svg_sanitize($svg);

	if ($sanitized === '') {
		$file['error'] = fs_t('SVG_INVALID_OR_UNSAFE');
		return $file;
	}

	file_put_contents($file['tmp_name'], $sanitized);

	return $file;
});

/**
 * DOM-based SVG sanitizer
 */
function fs_svg_sanitize(string $svg): string
{
	libxml_use_internal_errors(true);

	// Remove XML/DOCTYPE/comments first
	$svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
	$svg = preg_replace('/<!DOCTYPE.*?>/i', '', $svg);
	$svg = preg_replace('/<!--.*?-->/s', '', $svg);

	$dom = new DOMDocument();
	if (!$dom->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT)) {
		return '';
	}

	$svgEl = $dom->documentElement;
	if (!$svgEl || strtolower($svgEl->tagName) !== 'svg') {
		return '';
	}

	// Allowed tags (safe subset: no script, foreignObject, image, use)
	$allowed_tags = [
		// Structure
		'svg',
		'g',
		'defs',
		'symbol',
		// Shapes & path
		'path',
		'rect',
		'circle',
		'ellipse',
		'line',
		'polyline',
		'polygon',
		// Text
		'text',
		'tspan',
		'textpath',
		// Clipping & masking
		'clippath',
		'mask',
		'pattern',
		'marker',
		// Gradients
		'lineargradient',
		'radialgradient',
		'stop',
		// A11y & metadata
		'title',
		'desc',
		// Removed
		// 'switch', // Increases complexity and can cause memory issues
		// 'metadata', // Can get heavy and cause memory issues
		// 'animate', // Rarely needed, can cause memory issues
		// 'animatetransform', // Rarely needed, can cause memory issues
		// 'animatemotion', // Rarely needed, can cause memory issues
		// 'set', // Rarely needed, can cause memory issues
	];

	// Allowed attributes (lowercase; safe SVG presentation + geometry)
	$allowed_attrs = [
		// Structure & identity
		'xmlns',
		'viewbox',
		'width',
		'height',
		'preserveaspectratio',
		'class',
		'id',
		// Path & shape geometry
		'd',
		'points',
		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'x',
		'y',
		'x1',
		'y1',
		'x2',
		'y2',
		// Fill & stroke
		'fill',
		'stroke',
		'fill-rule',
		'fill-opacity',
		'stroke-opacity',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-dasharray',
		'stroke-dashoffset',
		'opacity',
		// Transforms
		'transform',
		'gradienttransform',
		// Gradients
		'gradientunits',
		'spreadmethod',
		'offset',
		'stop-color',
		'stop-opacity',
		'fx',
		'fy',
		// Clipping & masking
		'clip-path',
		'clip-rule',
		'clippathunits',
		'mask',
		'maskunits',
		'maskcontentunits',
		'patternunits',
		'patterncontentunits',
		'patterntransform',
		'markerunits',
		'markerwidth',
		'markerheight',
		'refx',
		'refy',
		'orient',
		'marker-start',
		'marker-mid',
		'marker-end',
		// Text
		'font-size',
		'font-family',
		'font-weight',
		'text-anchor',
		'dx',
		'dy',
		// SMIL animation
		'attributeName',
		'attributeType',
		'begin',
		'dur',
		'end',
		'repeatCount',
		'from',
		'to',
		'values',
		'keyTimes',
		'keySplines',
		'calcMode',
		'type',
		'additive',
		'accumulate',
		'restart',
		'by',
		// A11y & misc
		'aria-hidden',
		'aria-label',
		'role',
		'focusable',
		'xml:space',
	];

	$xpath = new DOMXPath($dom);

	// Remove forbidden elements
	foreach ($xpath->query('//*') as $node) {
		if (!($node instanceof DOMElement)) {
			continue;
		}

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
					$node->removeAttribute($attr->name);
					continue;
				}

				// Disallow href/xlink entirely
				if ($name === 'href' || $name === 'xlink:href') {
					$node->removeAttribute($attr->name);
					continue;
				}

				// Disallow external / data URLs
				if (preg_match('/url\s*\(\s*(?!#)[^)]+\)/i', $value)) {
					$node->removeAttribute($attr->name);
					continue;
				}

				if (!in_array($name, $allowed_attrs, true)) {
					$node->removeAttribute($attr->name);
				}
			}
		}
	}

	return $dom->saveXML($dom->documentElement);
}
