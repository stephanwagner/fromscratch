<?php get_header(); ?>

<div class="content__wrapper">
	<div class="content__container container">

		<?php
		if (have_posts()) {
			while (have_posts()) {
				the_post();
				if (fs_page_should_show_title((int) get_the_ID())) {
					echo '<h1>' . esc_html(get_the_title()) . '</h1>';
				}
				the_content();
			}
		}
		?>

	</div>
</div>

<?php get_footer(); ?>
