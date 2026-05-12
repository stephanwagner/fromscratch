<?php

// Field post type

global $fs_selected_article_list_post_type;

add_filter('acf/load_field/name=post-type', function ($field) {

    $post_types = get_post_types([
        'public' => true,
    ], 'objects');

    unset($post_types['page']);
    unset($post_types['attachment']);

    $field['choices'] = [];

    foreach ($post_types as $post_type) {
        $field['choices'][$post_type->name] = $post_type->labels->name;
    }
    return $field;
});

add_filter('acf/prepare_field/name=post-type', function ($field) {

    global $fs_selected_article_list_post_type;

    $fs_selected_article_list_post_type = $field['value'];

    if (!$fs_selected_article_list_post_type && !empty($field['default_value'])) {
        $fs_selected_article_list_post_type = $field['default_value'];
    }

    if (!$fs_selected_article_list_post_type && sizeof($field['choices']) > 0) {
        $fs_selected_article_list_post_type = array_keys($field['choices'])[0];
    }

    return $field;
});


// Field post taxonomy

add_filter('acf/prepare_field/name=post-taxonomy', function ($field) {

    global $fs_selected_article_list_post_type;

    if (!$fs_selected_article_list_post_type) {
        return $field;
    }

    $cpt = fs_config_cpt($fs_selected_article_list_post_type);

    if (!$cpt || empty($cpt['taxonomies'])) {
        return $field;
    }

    $taxonomy = array_key_first($cpt['taxonomies']);

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    $field['choices'] = [];

    foreach ($terms as $term) {
        $field['choices'][$term->term_id] = $term->name;
    }

    return $field;
});
