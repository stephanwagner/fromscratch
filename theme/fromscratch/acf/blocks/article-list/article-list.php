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
if ($hasLimit && $limitType == 'pagination') {
    $postsPerPage = $limit;
}

// Get articles
$query = new WP_Query([
    'post_type' => $postType,
    'posts_per_page' => $postsPerPage,
    'orderby' => $sortBy,
    'order' => $sortDirection,
]);

// Posts
$posts = $query->posts;

// Total pages
$totalPosts = $query->found_posts;
$totalPages = $query->max_num_pages;

// TODO
// - no articles list message
// - fallback image

/*

// TODO maybe use this?

<?php else : ?>
    <div class="archive__list">
        <?php
        while (have_posts()) {
            the_post();
            get_template_part('template-parts/content', 'archive');
        }
        ?>
    </div>
<?php endif; ?>

<nav class="archive__pagination" aria-label="<?php esc_attr_e('Posts pagination', 'fromscratch'); ?>">
    <?php
    the_posts_pagination([
        'mid_size'  => 2,
        'prev_text' => __('Previous', 'fromscratch'),
        'next_text' => __('Next', 'fromscratch'),
    ]);
    ?>
</nav>
*/

?>

<div
    class="article-list__wrapper"
>
    <div class="article-list__container">
        <div class="article-list__items">
            <?php
            foreach ($posts as $post) {
                $postThumbnail = get_the_post_thumbnail($post->ID, 'small');
                ?>
                <a href="<?= get_the_permalink($post->ID); ?>" class="article-list__item">
                    <div class="article-list__image">
                        <?= $postThumbnail ?>
                    </div>
                    <div class="article-list__content">
                        <h3 class="article-list__title"><?= $post->post_title ?></h3>
                        <div class="article-list__excerpt"><?= get_the_excerpt($post->ID) ?></div>
                    </div>
                </a>
                <?php
            }
            ?>
        </div>
        <?php /*if ($hasLimit && $limitType == 'pagination' && $totalPages > 1) { ?>
            <div class="article-list__pagination">
                <?php echo paginate_links([
                    'total' => $totalPages,
                    'current' => get_query_var('paged'),
                    'format' => 'page/%#%/',
                    'prev_text' => __('Previous', 'fromscratch'),
                    'next_text' => __('Next', 'fromscratch'),
                ]); ?>
            </div>
        <?php } */ ?>
    </div>
</div>
