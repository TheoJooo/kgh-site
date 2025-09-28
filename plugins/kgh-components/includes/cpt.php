<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kgh_components_register_post_types')) {
    function kgh_components_register_post_types()
    {
        register_post_type('kgh_guide', [
            'labels' => [
                'name'          => __('Guides', 'kgh-components'),
                'singular_name' => __('Guide', 'kgh-components'),
                'add_new_item'  => __('Add New Guide', 'kgh-components'),
                'edit_item'     => __('Edit Guide', 'kgh-components'),
                'new_item'      => __('New Guide', 'kgh-components'),
                'view_item'     => __('View Guide', 'kgh-components'),
            ],
            'public'       => true,
            'show_ui'      => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports'     => ['title', 'thumbnail', 'excerpt'],
            'menu_icon'    => 'dashicons-id',
            'has_archive'  => false,
            'rewrite'      => ['slug' => 'guides', 'with_front' => false],
        ]);
    }
    add_action('init', 'kgh_components_register_post_types', 0);
}

// Admin columns for Tour
add_filter('manage_edit-tour_columns', function (array $columns) {
    $insert_after = ['title', 'author'];
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if (in_array($key, $insert_after, true)) {
            $new_columns['kgh_thumb']    = __('Thumbnail', 'kgh-components');
            $new_columns['kgh_language'] = __('Language', 'kgh-components');
            $new_columns['kgh_badges']   = __('Badges', 'kgh-components');
            $new_columns['kgh_guide']    = __('Guide', 'kgh-components');
        }
    }
    return $new_columns;
}, 10, 1);

add_action('manage_tour_posts_custom_column', function ($column, $post_id) {
    if ($column === 'kgh_thumb') {
        if (has_post_thumbnail($post_id)) {
            echo get_the_post_thumbnail($post_id, [60, 60]);
        } else {
            echo '—';
        }
        return;
    }

    if ($column === 'kgh_language') {
        if (function_exists('pll_get_post_language')) {
            $slug = pll_get_post_language($post_id);
            echo esc_html(strtoupper((string) $slug));
        } else {
            echo '—';
        }
        return;
    }

    if ($column === 'kgh_badges') {
        $badges = kgh_components_get_badges_for_post($post_id);
        if ($badges) {
            $names = wp_list_pluck($badges, 'short_label');
            echo esc_html(implode(', ', array_filter($names)));
        } else {
            echo '—';
        }
        return;
    }

    if ($column === 'kgh_guide') {
        $guide = kgh_components_get_field('guide_ref', $post_id);
        if (is_array($guide)) {
            $guide_id = $guide['ID'] ?? $guide['id'] ?? null;
            if ($guide_id) {
                $title = get_the_title($guide_id);
                $url   = get_edit_post_link($guide_id);
                if ($url && $title) {
                    printf('<a href="%s">%s</a>', esc_url($url), esc_html($title));
                    return;
                }
            }
        }
        echo '—';
    }
}, 10, 2);

// Admin columns for Guide
add_filter('manage_edit-kgh_guide_columns', function (array $columns) {
    $updated = [];
    foreach ($columns as $key => $label) {
        if ($key === 'title') {
            $updated['kgh_photo'] = __('Photo', 'kgh-components');
        }
        $updated[$key] = $label;
    }
    if (!isset($updated['kgh_language'])) {
        $updated['kgh_language'] = __('Language', 'kgh-components');
    }
    return $updated;
}, 10, 1);

add_action('manage_kgh_guide_posts_custom_column', function ($column, $post_id) {
    if ($column === 'kgh_photo') {
        if (has_post_thumbnail($post_id)) {
            echo get_the_post_thumbnail($post_id, [60, 60]);
        } else {
            echo '—';
        }
        return;
    }

    if ($column === 'kgh_language') {
        if (function_exists('pll_get_post_language')) {
            $slug = pll_get_post_language($post_id);
            echo esc_html(strtoupper((string) $slug));
        } else {
            echo '—';
        }
    }
}, 10, 2);
