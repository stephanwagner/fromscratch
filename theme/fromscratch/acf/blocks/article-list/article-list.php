<?php

// Block name
$blockName = 'article-list';

// Class names
$classNames = ['fs-wp-block'];

// ID for specific styling
$classNames[] = $block['id'];

// Add class provided via class_field in WP Backend
if (!empty($block['className'])) {
	$classNames[] = $block['className'];
}

// Add wrapper class
$classNames[] = $blockName . '__wrapper';

// Fields
$postType = get_field('post-type');
$postTaxonomy = get_field('post-taxonomy');
$hasCategoryFilters = get_field('has-category-filters');
$hasLimit = get_field('has-limit');
$limitType = get_field('limit-type');
$limit = get_field('limit');
$sortBy = get_field('sort-by');
$sortDirection = get_field('sort-direction');

// CPT Config
$cpt = fs_config_cpt($postType);

// Posts per page
$postsPerPage = -1;
$paged = 1;
if ($hasLimit && $limitType === 'pagination') {
	$postsPerPage = (int) $limit;
	$paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
}

// Get articles
$query = new WP_Query([
	'post_type'      => $postType,
	'posts_per_page' => $postsPerPage,
	'orderby'        => $sortBy,
	'order'          => $sortDirection,
	'paged'          => $paged,
]);

// Posts
$posts = $query->posts;

// TODO
// - no articles list message
// - fallback image

?>

<div
    class="article-list__wrapper"
>
    <div class="article-list__container">
        <div class="article-list__items archive__list">
            <?php
            foreach ($posts as $post) {
                $GLOBALS['post'] = $post;
                setup_postdata($post);
                fs_render_template('post-preview.php', [
                    'heading' => 'h3',
                ]);
            }
            wp_reset_postdata();
            ?>
        </div>
        <?php
        if ($hasLimit && $limitType === 'pagination' && $query->max_num_pages > 1) {
            fs_render_pagination_for_query($query, [
                'aria_label' => __('Articles pagination', 'fromscratch'),
                'nav_class'  => 'article-list__pagination archive__pagination',
            ]);
        }
        ?>
    </div>
</div>
