<?php

/**
 * Public archive listings for custom post types (`has_archive` in config).
 * Not used for a default blog posts index; build listings with pages/blocks instead.
 */

defined('ABSPATH') || exit;

get_header();

$archive_heading = fs_archive_heading();

$archive_type = fs_archive_cpt_type();
?>

<div class="content__wrapper">
	<div class="content__container container">

		<?= fs_breadcrumbs() ?>

		<div class="content__content">

			<?php
			$archive_post_type = fs_archive_current_post_type();
			$archive_filter_taxonomy = $archive_post_type !== '' ? fs_cpt_filter_taxonomy($archive_post_type) : '';
			$archive_filter_term_id = $archive_filter_taxonomy !== ''
				? fs_article_list_filter_term_id_from_request($archive_filter_taxonomy)
				: 0;
			$archive_pagination_args = $archive_filter_taxonomy !== ''
				? ['add_args' => fs_article_list_active_filter_query_args($archive_filter_taxonomy, $archive_filter_term_id)]
				: [];

			$has_category_filter = fs_archive_has_category_filter($archive_post_type);
			?>

			<div class="article-list__wrapper -content-margin-m">
			<h1 class="article-list__title<?= $has_category_filter ? ' -has-category-filter' : '' ?>">
				<span class="article-list__title-text"><?= wp_kses_post($archive_heading) ?></span>

				<?php
				if ($has_category_filter) {
					fs_render_template('article-list-filter', [
						'taxonomy'         => $archive_filter_taxonomy,
						'selected_term_id' => $archive_filter_term_id,
						'form_action'      => $archive_post_type !== '' ? (string) get_post_type_archive_link($archive_post_type) : '',
						'filter_context'   => 'archive',
					]);
				}
				?>
			</h1>

			<?php if (have_posts()) { ?>

				<div class="article-list__container">
					<div class="article-list__items -design-<?= esc_attr(fs_archive_design()) ?>">
						<?php
						while (have_posts()) {
							the_post();
							fs_render_template('article-preview');
						}
						?>
					</div>
				</div>

				<?php
				fs_render_template('pagination', [
					'pagination_args' => $archive_pagination_args,
				]);
				?>
			<?php } else { ?>
				<div class="article-list__empty"><?= esc_html(fs_cpt_text('empty')) ?></div>
			<?php } ?>
			</div>
		</div>

	</div>
</div>

<?php get_footer(); ?>