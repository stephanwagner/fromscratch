<?php

defined('ABSPATH') || exit;

$aria_label = isset($aria_label) && is_string($aria_label) ? $aria_label : __('Posts pagination', 'fromscratch');
$nav_class = isset($nav_class) && is_string($nav_class) ? $nav_class : 'archive__pagination';

// Secondary query: pass `query` as a WP_Query instance (e.g. blocks).
if (isset($query) && $query instanceof \WP_Query) {
	$total = max(1, (int) $query->max_num_pages);
	if ($total <= 1) {
		return;
	}

	$user = isset($paginate_links_args) && is_array($paginate_links_args) ? $paginate_links_args : [];
	$args = fs_paginate_links_defaults_for_query($query, $user);

	$links = paginate_links($args);
	if (!is_string($links) || $links === '') {
		return;
	}
	?>
<nav class="<?php echo esc_attr($nav_class); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from core paginate_links().
	echo $links;
	?>
</nav>
	<?php
	return;
}

// Main query (archives, blog index, etc.).
global $wp_query;
$max_pages = ($wp_query instanceof \WP_Query) ? (int) $wp_query->max_num_pages : 1;
if ($max_pages <= 1) {
	return;
}

$pagination_args = isset($pagination_args) && is_array($pagination_args) ? $pagination_args : [
	'mid_size'  => 2,
	'prev_text' => __('Previous', 'fromscratch'),
	'next_text' => __('Next', 'fromscratch'),
];
?>
<nav class="<?php echo esc_attr($nav_class); ?>" aria-label="<?php echo esc_attr($aria_label); ?>">
	<?php the_posts_pagination($pagination_args); ?>
</nav>
