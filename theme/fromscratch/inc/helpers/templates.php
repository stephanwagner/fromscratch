<?php

defined('ABSPATH') || exit;

/**
 * Load a PHP template from the theme `templates/` directory with scoped variables.
 *
 * @param string $template Relative path under `templates/`, e.g. `pagination.php` or `post-preview.php`.
 * @param array<string, mixed> $data Variables extracted into the template scope (EXTR_SKIP).
 */
function fs_render_template(string $template, array $data = []): void
{
	$template = str_replace("\0", '', $template);
	$template = ltrim(str_replace('\\', '/', $template), '/');
	if ($template === '' || str_contains($template, '..')) {
		return;
	}

	$base = trailingslashit(get_template_directory()) . 'templates/';
	$path = $base . $template;

	if (!is_file($path) || !is_readable($path)) {
		return;
	}

	$real_base = realpath($base);
	$real_file = realpath($path);
	if ($real_base === false || $real_file === false) {
		return;
	}

	$real_base = wp_normalize_path($real_base) . '/';
	$real_file = wp_normalize_path($real_file);
	if (!str_starts_with($real_file, $real_base)) {
		return;
	}

	extract($data, EXTR_SKIP);
	include $path;
}

/**
 * Default arguments for {@see paginate_links()} for a secondary {@see WP_Query}.
 * Uses the current request URL via {@see get_pagenum_link()} so links match the viewed page.
 *
 * Override `base`, `format`, or `current` when multiple paged lists share one URL (pass a unique query arg).
 *
 * @param array<string, mixed> $overrides Merged on top of defaults.
 * @return array<string, mixed>
 */
function fs_paginate_links_defaults_for_query(\WP_Query $query, array $overrides = []): array
{
	$total = max(1, (int) $query->max_num_pages);
	$paged_from_query = (int) $query->get('paged');
	$current = max(1, $paged_from_query, (int) get_query_var('paged'), (int) get_query_var('page'));
	if ($current > $total) {
		$current = $total;
	}

	$big = 999999999;
	$base = str_replace((string) $big, '%#%', esc_url(get_pagenum_link($big, false)));

	$defaults = [
		'base'      => $base,
		'format'    => '',
		'total'     => $total,
		'current'   => $current,
		'mid_size'  => 2,
		'end_size'  => 1,
		'prev_text' => __('Previous', 'fromscratch'),
		'next_text' => __('Next', 'fromscratch'),
		'type'      => 'list',
	];

	return array_merge($defaults, $overrides);
}

/**
 * Render `templates/pagination.php` for a custom {@see WP_Query} (same template as the main loop; pass `query`).
 *
 * Optional `$data` keys: `aria_label`, `nav_class`, `paginate_links_args` (merged into defaults).
 */
function fs_render_pagination_for_query(\WP_Query $query, array $data = []): void
{
	fs_render_template('pagination.php', array_merge(['query' => $query], $data));
}
