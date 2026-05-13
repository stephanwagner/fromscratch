<?php

defined('ABSPATH') || exit;

/**
 * Archive-style post card. Expects global $post (main loop, custom query after the_post(), or setup_postdata()).
 *
 * Optional (via fs_render_template $data): permalink, range, post_class_extra, heading (h3 for nested lists).
 */

$url = (isset($permalink) && is_string($permalink) && $permalink !== '') ? $permalink : get_permalink();
$range = (isset($range) && is_string($range)) ? $range : '';
$extra = (isset($post_class_extra) && is_string($post_class_extra)) ? trim($post_class_extra) : '';
$tag = (isset($heading) && $heading === 'h3') ? 'h3' : 'h2';

$classes = ['archive__item'];
if ($extra !== '') {
	foreach (preg_split('/\s+/', $extra, -1, PREG_SPLIT_NO_EMPTY) as $c) {
		$classes[] = $c;
	}
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class($classes); ?>>
	<a class="archive__thumb-link" href="<?php echo esc_url($url); ?>" tabindex="-1" aria-hidden="true">
		<?= fs_image_with_placeholder(get_post_thumbnail_id(), 'medium', ['class' => 'archive__thumb']); ?>
	</a>
	<div class="archive__body">
		<?php if ($range !== '') : ?>
			<p class="event-archive__range"><?php echo esc_html($range); ?></p>
		<?php endif; ?>
		<<?php echo esc_attr($tag); ?> class="archive__title">
			<a href="<?php echo esc_url($url); ?>"><?php the_title(); ?></a>
		</<?php echo esc_attr($tag); ?>>
		<div class="archive__excerpt"><?php the_excerpt(); ?></div>
		<p class="archive__more">
			<a class="archive__readmore" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Read more', 'fromscratch'); ?></a>
		</p>
	</div>
</article>
