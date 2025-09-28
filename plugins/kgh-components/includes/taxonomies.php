<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kgh_components_register_taxonomy')) {
    function kgh_components_register_taxonomy()
    {
        register_taxonomy('kgh_badge', ['tour'], [
            'labels' => [
                'name'          => __('Badges', 'kgh-components'),
                'singular_name' => __('Badge', 'kgh-components'),
            ],
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'rewrite'           => [
                'slug'       => 'badge',
                'with_front' => false,
            ],
            'show_admin_column' => true,
            'meta_box_cb'       => false,
        ]);
    }
    add_action('init', 'kgh_components_register_taxonomy', 0);
}

if (!function_exists('kgh_components_render_badges_box')) {
    function kgh_components_render_badges_box($post, $box = null)
    {
        $taxonomy = isset($box['args']['taxonomy']) ? $box['args']['taxonomy'] : 'kgh_badge';
        $post_id  = (int) $post->ID;

        $lang = null;
        if (function_exists('pll_get_post_language')) {
            $lang = pll_get_post_language($post_id, 'slug');
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'lang'       => $lang ?: 'all',
        ]);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        $assigned = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        $assigned = is_wp_error($assigned) ? [] : $assigned;

        echo '<div class="kgh-badges-list" style="display:grid;gap:6px;">';

        foreach ($terms as $term) {
            $icon_id     = kgh_components_get_term_field('kgh_badge', $term->term_id, 'icon');
            $short_label = kgh_components_get_term_field('kgh_badge', $term->term_id, 'short_label');

            $icon_markup = '';
            if ($icon_id) {
                $icon_markup = wp_get_attachment_image(
                    (int) $icon_id,
                    [20, 20],
                    false,
                    [
                        'style' => 'width:20px;height:20px;object-fit:cover;border-radius:3px;margin-right:6px;vertical-align:middle;',
                        'alt'   => '',
                    ]
                );
            }

            $label       = $short_label !== null ? (string) $short_label : $term->name;
            $is_selected = in_array($term->term_id, $assigned, true);

            echo '<label style="display:flex;align-items:center;padding:6px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;">';
            printf(
                '<input type="checkbox" name="tax_input[%1$s][]" value="%2$d" %3$s style="margin-right:8px;">',
                esc_attr($taxonomy),
                (int) $term->term_id,
                checked($is_selected, true, false)
            );
            echo $icon_markup ? $icon_markup : '';
            echo esc_html($label);
            echo '<span style="color:#768;margin-left:8px;">(' . esc_html($term->slug) . ')</span>';
            echo '</label>';
        }

        echo '<input type="hidden" name="tax_input[' . esc_attr($taxonomy) . '][]" value="0">';
        echo '<p class="howto" style="margin-top:6px;">' . esc_html__("Manage badges (icon + label) under Tours -> Badges.", 'kgh-components') . '</p>';
        echo '</div>';
    }
}

add_action('add_meta_boxes_tour', function () {
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    remove_meta_box('tagsdiv-kgh_badge', 'tour', 'side');

    add_meta_box(
        'kgh_badges_box',
        __('Badges', 'kgh-components'),
        'kgh_components_render_badges_box',
        'tour',
        'side',
        'default',
        ['taxonomy' => 'kgh_badge']
    );
});

add_action('created_kgh_badge', 'kgh_components_update_badge_slug_meta', 10, 2);
add_action('edited_kgh_badge', 'kgh_components_update_badge_slug_meta', 10, 2);
if (!function_exists('kgh_components_update_badge_slug_meta')) {
    function kgh_components_update_badge_slug_meta($term_id, $tt_id)
    {
        $term = get_term($term_id, 'kgh_badge');
        if ($term && !is_wp_error($term)) {
            update_term_meta($term_id, 'slug_tech', sanitize_title($term->slug));
        }
    }
}
