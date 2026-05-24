<?php

defined('ABSPATH') || exit;

get_header();

$s = trim((string) get_search_query());
?>

<div class="content__wrapper">
	<div class="content__container container">

		<?php echo fs_breadcrumbs(); ?>

		<div class="content__content">

			<h1><?= esc_html(__('Search results', 'fromscratch')) ?></h1>

			<div class="search__page-form">
				<?php get_search_form(); ?>
			</div>

			<?php if ($s === '' || !get_search_query()) : ?>
				<div class="search__empty"><?php esc_html_e('Please enter a search term.', 'fromscratch'); ?></div>
			<?php else : ?>
				<?php
				$the_query = new \WP_Query([
					's' => $s,
				]);
				?>
				<?php if ($the_query->have_posts()) : ?>
					<div class="search__amount">
						<?php
						$count = (int) $the_query->post_count;
						echo esc_html(
							sprintf(
								/* translators: 1: number of results, 2: search term */
								_n(
									'%1$d result for "%2$s"',
									'%1$d results for "%2$s"',
									$count,
									'fromscratch'
								),
								$count,
								$s
							)
						);
						?>
					</div>
					<div class="archive__list">
						<?php
						while ($the_query->have_posts()) {
							$the_query->the_post();
							fs_render_template('article-preview');
						}
						wp_reset_postdata();
						?>
					</div>
				<?php else : ?>
					<div class="search__empty"><?php echo esc_html(sprintf(__('No results found for "%s".', 'fromscratch'), $s)); ?></div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

	</div>
</div>

<?php get_footer(); ?>
