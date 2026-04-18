<?php

defined('ABSPATH') || exit;

get_header();

$archive_heading = get_the_archive_title();
if (is_post_type_archive()) {
	$pto = get_queried_object();
	if ($pto instanceof \WP_Post_Type && isset($pto->labels->name)) {
		$archive_heading = $pto->labels->name;
	}
}
?>

<div class="content__wrapper">
	<div class="content__container container">

		<header class="archive__header">
			<h1 class="archive__heading"><?php echo wp_kses_post($archive_heading); ?></h1>
		</header>

		<?php if (have_posts()) : ?>
			<div class="archive__list">
				<?php
				while (have_posts()) {
					the_post();
				?>
					<article id="post-<?php the_ID(); ?>" <?php post_class('archive__item'); ?>>
						<?php if (has_post_thumbnail()) : ?>
							<a class="archive__thumb-link" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
								<?php the_post_thumbnail('small', ['class' => 'archive__thumb', 'loading' => 'lazy']); ?>
							</a>
						<?php endif; ?>
						<div class="archive__body">
							<h2 class="archive__title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h2>
							<div class="archive__excerpt"><?php the_excerpt(); ?></div>
							<p class="archive__more">
								<a class="archive__readmore" href="<?php the_permalink(); ?>"><?php esc_html_e('Read more', 'fromscratch'); ?></a>
							</p>
						</div>
					</article>
				<?php
				}
				?>
			</div>

			<nav class="archive__pagination" aria-label="<?php esc_attr_e('Posts pagination', 'fromscratch'); ?>">
				<?php
				the_posts_pagination([
					'mid_size'  => 2,
					'prev_text' => __('Previous', 'fromscratch'),
					'next_text' => __('Next', 'fromscratch'),
				]);
				?>
			</nav>
		<?php else : ?>
			<p class="archive__empty"><?php esc_html_e('No posts found.', 'fromscratch'); ?></p>
		<?php endif; ?>

	</div>
</div>

<?php get_footer(); ?>