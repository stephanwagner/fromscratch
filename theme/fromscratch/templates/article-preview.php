<?php

defined('ABSPATH') || exit;

/**
 * Archive-style post card. Expects global $post (main loop, custom query after the_post(), or setup_postdata()).
 *
 * Optional (via fs_render_template $data): id, url, classes, title_tag.
 */

$id = isset($id) ? $id : get_the_ID();
$url = (isset($url) && is_string($url) && $url !== '') ? $url : get_permalink();
// $range = (isset($range) && is_string($range)) ? $range : ''; // TODO
$classes = (isset($classes) && is_string($classes)) ? trim($classes) : '';
$title_tag = (isset($title_tag) && in_array($title_tag, ['div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) ? $title_tag : 'h4';

$post_classes = ['article-preview__container'];
if ($classes !== '') {
	foreach (preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY) as $c) {
		$post_classes[] = $c;
	}
}
?>

<article id="post-<?= $id ?>" <?php post_class($post_classes); ?>>
	<<?= is_admin() ? 'div' : 'a href="' . esc_url($url) . '"' ?> class="article-preview__link read-more-link-trigger">
		<div class="article-preview__image-container">
			<?= fs_image_with_placeholder(get_post_thumbnail_id(), 'medium', ['class' => 'article-preview__image']); ?>
		</div>
		<div class="article-preview__content">
			<?php /* if ($range !== '') : ?>
				<p class="event-archive__range"><?php echo esc_html($range); ?></p>
			<?php endif; */ ?>
			<<?= esc_attr($title_tag) ?> class="article-preview__title">
				<?php the_title(); ?>
			</<?= esc_attr($title_tag) ?>>
			<div class="article-preview__excerpt"><?php the_excerpt(); ?></div>
			<div class="article-preview__read-more-container">
				<?php fs_render_template('read-more', ['class' => 'article-preview__read-more-link']); ?>
			</div>
		</div>
	</<?= is_admin() ? 'div' : 'a' ?>>
</article>
