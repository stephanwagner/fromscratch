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
					get_template_part('template-parts/content', 'archive');
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