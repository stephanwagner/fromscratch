<?php get_header(); ?>

<div class="content__wrapper">
	<div class="content__container container">

		<?php
		if (have_posts()) {
			while (have_posts()) {
				the_post();
				the_content();
			}
		}
		?>
	</div>
</div>

<?php get_footer(); ?>
