<?php
if (!defined('ABSPATH')) {
    exit;
}


if (!function_exists('kgh_components_format_badge_term')) {
    function kgh_components_format_badge_term($term)
    {
        if (!$term) {
            return null;
        }
        if (is_numeric($term)) {
            $term = get_term((int) $term, 'kgh_badge');
        } elseif (is_array($term) && isset($term['term_id'])) {
            $term = get_term((int) $term['term_id'], 'kgh_badge');
        }
        if (!$term || is_wp_error($term)) {
            return null;
        }

        $icon      = kgh_components_get_term_field('kgh_badge', $term->term_id, 'icon');
        $color     = kgh_components_get_term_field('kgh_badge', $term->term_id, 'color');
        $short     = kgh_components_get_term_field('kgh_badge', $term->term_id, 'short_label');
        $slug_tech = get_term_meta($term->term_id, 'slug_tech', true);

        return [
            'id'         => (int) $term->term_id,
            'slug'       => $term->slug,
            'slug_tech'  => $slug_tech ?: $term->slug,
            'name'       => $term->name,
            'icon'       => kgh_components_format_image($icon),
            'color'      => $color ?: null,
            'short_label'=> $short !== null ? (string) $short : $term->name,
        ];
    }
}


if (!function_exists('kgh_components_get_badges_for_post')) {
    /**
     * Return enriched badge data for a post (icon + labels).
     */
    function kgh_components_get_badges_for_post($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return [];
        }

        $terms = get_the_terms($post_id, 'kgh_badge');
        if (empty($terms) || is_wp_error($terms)) {
            return [];
        }

        $badges = [];
        foreach ($terms as $term) {
            $icon_id = kgh_components_get_term_field('kgh_badge', $term->term_id, 'icon');
            $short_label = kgh_components_get_term_field('kgh_badge', $term->term_id, 'short_label');
            $icon_url = null;
            if ($icon_id) {
                $icon_url = wp_get_attachment_image_url((int) $icon_id, 'thumbnail');
            }

            $badges[] = [
                'id'          => (int) $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'short_label' => $short_label !== null ? (string) $short_label : $term->name,
                'icon_id'     => $icon_id ? (int) $icon_id : null,
                'icon_url'    => $icon_url ?: null,
            ];
        }

        return $badges;
    }
}

if (!function_exists('kgh_get_enriched_badges_for_post')) {
    /**
     * @deprecated Use kgh_components_get_badges_for_post().
     */
    function kgh_get_enriched_badges_for_post($post_id)
    {
        return kgh_components_get_badges_for_post($post_id);
    }
}
