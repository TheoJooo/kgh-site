<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('manage_edit-kgh_badge_columns', function (array $columns) {
    $updated = [];

    foreach ($columns as $key => $label) {
        if ($key === 'name') {
            $updated['kgh_icon'] = __('Icon', 'kgh-components');
        }

        $updated[$key] = $label;
    }

    if (!isset($updated['kgh_icon'])) {
        $updated['kgh_icon'] = __('Icon', 'kgh-components');
    }

    return $updated;
});

add_filter('manage_kgh_badge_custom_column', function ($content, $column, $term_id) {
    if ($column !== 'kgh_icon') {
        return $content;
    }

    $icon_id = kgh_components_get_term_field('kgh_badge', $term_id, 'icon');
    $short_label = kgh_components_get_term_field('kgh_badge', $term_id, 'short_label');

    $thumb = '';
    if ($icon_id) {
        $thumb = wp_get_attachment_image(
            (int) $icon_id,
            [24, 24],
            false,
            [
                'style' => 'width:24px;height:24px;object-fit:cover;border-radius:4px;margin-right:6px;vertical-align:middle;',
                'alt'   => '',
            ]
        );
    }

    if ($short_label === null) {
        $term = get_term($term_id, 'kgh_badge');
        if ($term && !is_wp_error($term)) {
            $short_label = $term->name;
        } else {
            $short_label = '';
        }
    }

    return $thumb . esc_html($short_label);
}, 10, 3);
