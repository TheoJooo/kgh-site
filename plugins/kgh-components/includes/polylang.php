<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('pll_get_post_types', function ($types, $is_settings) {
    $types['tour'] = 'tour';
    $types['kgh_guide'] = 'kgh_guide';
    return $types;
}, 10, 2);

add_filter('pll_get_taxonomies', function ($taxonomies, $is_settings) {
    $taxonomies['kgh_badge'] = 'kgh_badge';
    return $taxonomies;
}, 10, 2);
