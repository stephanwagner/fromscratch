<?php
function fs_breadcrumbs(array $args = []): string
{
    $defaults = [
        'home_label' => __('Home', 'fromscratch'),
        'home_url'   => home_url('/'),
        'separator'  => '›'
    ];

    $args = wp_parse_args($args, $defaults);

    // `separator` may contain HTML (sanitized). Pass `separator_html` for the full fragment without the default wrapper.
    if (! array_key_exists('separator_html', $args)) {
        $args['separator_html'] = '<span class="fs-breadcrumbs__separator">'
            . esc_html($args['separator'])
            . '</span>';
    }

    if (is_front_page()) {
        return '';
    }

    if (is_404()) {
        return '';
    }

    $items = [];

    // Home
    $items[] = [
        'label' => $args['home_label'],
        'url'   => $args['home_url'],
    ];

    // Pages (hierarchical)
    if (is_page()) {
        global $post;

        $parents = array_reverse(get_post_ancestors($post));
        foreach ($parents as $parent_id) {
            $items[] = [
                'label' => get_the_title($parent_id),
                'url'   => get_permalink($parent_id),
            ];
        }

        $items[] = [
            'label' => get_the_title(),
            'url'   => null,
        ];
    }

    // Posts
    elseif (is_single()) {
        $post_type = get_post_type();

        // Blog page (for posts)
        if ($post_type === 'post') {
            $blog_id = get_option('page_for_posts');

            if ($blog_id) {
                $items[] = [
                    'label' => get_the_title($blog_id),
                    'url'   => get_permalink($blog_id),
                ];
            }
        }

        // CPT archive
        elseif ($post_type !== 'page') {
            $obj = get_post_type_object($post_type);

            if ($obj && ! empty($obj->has_archive)) {
                $items[] = [
                    'label' => $obj->labels->name,
                    'url'   => get_post_type_archive_link($post_type),
                ];
            }
        }

        $items[] = [
            'label' => get_the_title(),
            'url'   => null,
        ];
    }

    // Blog posts index (static front page + separate posts page)
    elseif (is_home()) {
        $blog_id = (int) get_option('page_for_posts');

        if ($blog_id) {
            $items[] = [
                'label' => get_the_title($blog_id),
                'url'   => null,
            ];
        }
    }

    // Search results
    elseif (is_search()) {
        $query = get_search_query();
        $items[] = [
            'label' => $query !== ''
                ? sprintf(
                    /* translators: %s: Search query. */
                    __('Search results for "%s"', 'fromscratch'),
                    $query
                )
                : __('Search results', 'fromscratch'),
            'url' => null,
        ];
    }

    // Archive
    elseif (is_archive()) {
        $items[] = [
            'label' => get_the_archive_title(),
            'url'   => null,
        ];
    }

    // Unsupported context would only output "Home" — omit rather than mislead.
    if (count($items) < 2) {
        return '';
    }

    // Build HTML
    $nav_label = esc_attr__('Breadcrumb', 'fromscratch');

    $html = '<nav class="fs-breadcrumbs__container" aria-label="' . $nav_label . '">';
    $html .= '<ol class="fs-breadcrumbs__list">';

    $last_index = count($items) - 1;

    foreach ($items as $index => $item) {
        $html .= '<li class="fs-breadcrumbs__item">';

        if ($item['url']) {
            $html .= '<a href="' . esc_url($item['url']) . '">'
                . esc_html($item['label']) . '</a>';
        } else {
            $current = ($index === $last_index) ? ' aria-current="page"' : '';
            $html .= '<span class="fs-breadcrumbs__item-label"' . $current . '>' . esc_html($item['label']) . '</span>';
        }

        if ($index < $last_index) {
            $html .= '<span class="fs-breadcrumbs__separator" aria-hidden="true">'
                . $args['separator_html']
                . '</span>';
        }

        $html .= '</li>';
    }

    $html .= '</ol></nav>';

    return $html;
}
