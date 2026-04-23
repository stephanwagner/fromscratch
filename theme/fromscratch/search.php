<?php get_header(); ?>

<div class="content__wrapper">
	<div class="content__container container">

		<h1>Search</h1>

		<?php
		$s = trim(get_search_query());

		if (!$s || !get_search_query()) {
		?>
			<p>Please enter a search term</p>
			<?php
		} else {
			$args = array(
				's' => $s
			);

			$the_query = new WP_Query($args);

			if ($the_query->have_posts()) {

				_e('<div class="search__amount">' . ($the_query->post_count) . ' ' . ($the_query->post_count == 1 ? 'result' : 'results') . ' for search term "' . get_query_var('s') . '"</div>');
				_e('<div class="search-result__wrapper">');

				while ($the_query->have_posts()) {
					$the_query->the_post();
			?>
					<div class="search-result__container">
						<div class="search-result__link">
							<a href="<?php
										if (strpos(get_the_permalink(), '?') > 0) {
											echo get_the_permalink() . '&query=' . $s;
										} else {
											echo get_the_permalink() . '?query=' . $s;
										}
										?>"><?php the_title(); ?></a>
						</div>
						<div class="search-result__excerpt">
							<?php the_excerpt(); ?>
						</div>
					</div>
				<?php
				}

				_e('</div>');
			} else {
				?>
				<p>No results found for "<?= $s ?>"</p>
		<?php
			}
		}
		?>
	</div>
</div>

<?php get_footer(); ?>
