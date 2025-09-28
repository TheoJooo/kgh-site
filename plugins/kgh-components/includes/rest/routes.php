<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('kgh/v1', '/tours', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_tours',
        'permission_callback' => '__return_true',
        'args' => [
            'page'     => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 10],
            'lang'     => ['type' => 'string'],
        ],
    ]);

    register_rest_route('kgh/v1', '/tours/(?P<id>[\d]+)', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_tour',
        'permission_callback' => '__return_true',
        'args' => [
            'lang' => ['type' => 'string'],
        ],
    ]);

    register_rest_route('kgh/v1', '/tours/(?P<slug>[a-z0-9\-]+)', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_tour',
        'permission_callback' => '__return_true',
        'args' => [
            'lang' => ['type' => 'string'],
        ],
    ]);

    register_rest_route('kgh/v1', '/guides', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_guides',
        'permission_callback' => '__return_true',
        'args' => [
            'page'     => ['type' => 'integer', 'default' => 1],
            'per_page' => ['type' => 'integer', 'default' => 10],
            'lang'     => ['type' => 'string'],
        ],
    ]);

    register_rest_route('kgh/v1', '/guides/(?P<id>[\d]+)', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_guide',
        'permission_callback' => '__return_true',
        'args' => [
            'lang' => ['type' => 'string'],
        ],
    ]);

    register_rest_route('kgh/v1', '/guides/(?P<slug>[a-z0-9\-]+)', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_guide',
        'permission_callback' => '__return_true',
        'args' => [
            'lang' => ['type' => 'string'],
        ],
    ]);

    register_rest_route('kgh/v1', '/badges', [
        'methods'  => 'GET',
        'callback' => 'kgh_components_rest_get_badges',
        'permission_callback' => '__return_true',
        'args' => [
            'lang' => ['type' => 'string'],
        ],
    ]);
});

function kgh_components_switch_language($lang)
{
    if ($lang && function_exists('pll_switch_language')) {
        pll_switch_language($lang);
    }
}

function kgh_components_rest_get_tours(WP_REST_Request $request)
{
    kgh_components_switch_language($request->get_param('lang'));

    $page = max(1, (int)$request->get_param('page'));
    $per_page = (int)$request->get_param('per_page');
    if ($per_page <= 0 || $per_page > 50) {
        $per_page = 10;
    }

    $query = new WP_Query([
        'post_type'      => 'tour',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ]);

    $items = [];
    foreach ($query->posts as $post) {
        $items[] = kgh_components_prepare_tour($post->ID, false);
    }

    return new WP_REST_Response([
        'data' => $items,
        'meta' => [
            'total'       => (int)$query->found_posts,
            'total_pages' => (int)$query->max_num_pages,
            'page'        => $page,
        ],
    ], 200);
}

function kgh_components_rest_get_tour(WP_REST_Request $request)
{
    kgh_components_switch_language($request->get_param('lang'));

    $identifier = $request->get_param('id') ?: $request->get_param('slug');
    $post = null;
    if (is_numeric($identifier)) {
        $post = get_post((int)$identifier);
    } else {
        $post = get_page_by_path(sanitize_title($identifier), OBJECT, 'tour');
    }
    if (!$post || $post->post_type !== 'tour') {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }

    return new WP_REST_Response(kgh_components_prepare_tour($post->ID, true), 200);
}

function kgh_components_rest_get_guides(WP_REST_Request $request)
{
    kgh_components_switch_language($request->get_param('lang'));
    $page = max(1, (int)$request->get_param('page'));
    $per_page = (int)$request->get_param('per_page');
    if ($per_page <= 0 || $per_page > 50) {
        $per_page = 10;
    }

    $query = new WP_Query([
        'post_type'      => 'kgh_guide',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ]);

    $items = [];
    foreach ($query->posts as $post) {
        $items[] = kgh_components_prepare_guide($post->ID);
    }

    return new WP_REST_Response([
        'data' => $items,
        'meta' => [
            'total'       => (int)$query->found_posts,
            'total_pages' => (int)$query->max_num_pages,
            'page'        => $page,
        ],
    ], 200);
}

function kgh_components_rest_get_guide(WP_REST_Request $request)
{
    kgh_components_switch_language($request->get_param('lang'));
    $identifier = $request->get_param('id') ?: $request->get_param('slug');
    $post = null;
    if (is_numeric($identifier)) {
        $post = get_post((int)$identifier);
    } else {
        $post = get_page_by_path(sanitize_title($identifier), OBJECT, 'kgh_guide');
    }
    if (!$post || $post->post_type !== 'kgh_guide') {
        return new WP_REST_Response(['error' => 'not_found'], 404);
    }
    return new WP_REST_Response(kgh_components_prepare_guide($post->ID), 200);
}

function kgh_components_rest_get_badges(WP_REST_Request $request)
{
    kgh_components_switch_language($request->get_param('lang'));
    $terms = get_terms([
        'taxonomy'   => 'kgh_badge',
        'hide_empty' => false,
    ]);
    $data = [];
    foreach ($terms as $term) {
        $data[] = kgh_components_format_badge_term($term);
    }
    return new WP_REST_Response(['data' => array_values(array_filter($data))], 200);
}

function kgh_components_prepare_tour($post_id, $full = true)
{
    $header = [
        'hero_image'     => kgh_components_format_image(kgh_components_get_field('hero_image', $post_id)),
        'title_overline' => kgh_components_get_field('title_overline', $post_id),
        'title'          => kgh_components_get_field('title', $post_id) ?: get_the_title($post_id),
        'subtitle_badge' => kgh_components_get_field('subtitle_badge', $post_id),
        'intro_richtext' => kgh_components_get_field('intro_richtext', $post_id),
    ];

    $badge_terms = kgh_components_get_field('badges', $post_id);
    if (!$badge_terms) {
        $badge_terms = wp_get_post_terms($post_id, 'kgh_badge');
    }
    $badges = [];
    if ($badge_terms && is_array($badge_terms)) {
        foreach ($badge_terms as $term) {
            $formatted = kgh_components_format_badge_term($term);
            if ($formatted) {
                $badges[] = $formatted;
            }
        }
    }

    $meta = [
        'location_text'  => kgh_components_get_field('location_text', $post_id),
        'duration_text'  => kgh_components_get_field('duration_text', $post_id),
        'capacity_text'  => kgh_components_get_field('capacity_text', $post_id),
        'languages_text' => kgh_components_get_field('languages_text', $post_id),
    ];

    $selling = [];
    $selling_rows = kgh_components_get_field('selling_points', $post_id);
    if ($selling_rows) {
        foreach ($selling_rows as $row) {
            $selling[] = [
                'icon' => kgh_components_format_image($row['sp_icon'] ?? null),
                'text' => $row['sp_text'] ?? '',
            ];
        }
    }

    $discover = [];
    $discover_rows = kgh_components_get_field('discover_items', $post_id);
    if ($discover_rows) {
        foreach ($discover_rows as $row) {
            $item_badges = [];
            if (!empty($row['item_badges']) && is_array($row['item_badges'])) {
                foreach ($row['item_badges'] as $badge_term) {
                    $formatted = kgh_components_format_badge_term($badge_term);
                    if ($formatted) {
                        $item_badges[] = $formatted;
                    }
                }
            }
            $discover[] = [
                'title'       => $row['item_title'] ?? '',
                'badges'      => array_values(array_filter($item_badges)),
                'description' => $row['item_description'] ?? '',
                'note'        => $row['item_note'] ?? '',
            ];
        }
    }

    $dietary = [
        'icon'  => kgh_components_format_image(kgh_components_get_field('dietary_icon', $post_id)),
        'title' => kgh_components_get_field('dietary_title', $post_id),
        'note'  => kgh_components_get_field('dietary_note', $post_id),
    ];

    $map_links = [];
    $map_rows = kgh_components_get_field('map_links', $post_id);
    if ($map_rows) {
        foreach ($map_rows as $row) {
            $map_links[] = [
                'label' => $row['map_label'] ?? '',
                'url'   => $row['map_url'] ?? '',
            ];
        }
    }

    $meeting = [
        'title'              => kgh_components_get_field('meeting_title', $post_id),
        'station_name'       => kgh_components_get_field('meeting_station_name', $post_id),
        'description'        => kgh_components_get_field('meeting_description', $post_id),
        'map_links'          => array_values(array_filter($map_links, fn($item) => !empty($item['url']))),
        'ending_note'        => kgh_components_get_field('meeting_end_note', $post_id),
    ];

    $how_to = [
        'title'       => kgh_components_get_field('howto_title', $post_id),
        'description' => kgh_components_get_field('howto_description', $post_id),
    ];

    $guide_block = null;
    $guide_override_badges = [];
    $guide_ref = kgh_components_get_field('guide_ref', $post_id);
    if ($guide_ref && is_array($guide_ref)) {
        $guide_id = $guide_ref['ID'] ?? $guide_ref['id'] ?? null;
        if ($guide_id) {
            $guide_block = kgh_components_prepare_guide($guide_id);
        }
    }
    $override_rows = kgh_components_get_field('guide_override_badges', $post_id);
    if ($override_rows) {
        foreach ($override_rows as $row) {
            if (!empty($row['label'])) {
                $guide_override_badges[] = $row['label'];
            }
        }
    }

    $details_included = [];
    $included_rows = kgh_components_get_field('included', $post_id);
    if ($included_rows) {
        foreach ($included_rows as $row) {
            if (!empty($row['label'])) {
                $details_included[] = $row['label'];
            }
        }
    }
    $details_excluded = [];
    $excluded_rows = kgh_components_get_field('excluded', $post_id);
    if ($excluded_rows) {
        foreach ($excluded_rows as $row) {
            if (!empty($row['label'])) {
                $details_excluded[] = $row['label'];
            }
        }
    }
    $bottom_highlights = [];
    $highlight_rows = kgh_components_get_field('bottom_highlights', $post_id);
    if ($highlight_rows) {
        foreach ($highlight_rows as $row) {
            $bottom_highlights[] = [
                'icon'      => kgh_components_format_image($row['highlight_icon'] ?? null),
                'title'     => $row['highlight_title'] ?? '',
                'subtitle'  => $row['highlight_subtitle'] ?? '',
                'note'      => $row['highlight_note'] ?? '',
            ];
        }
    }

    $reviews_data = [];
    $reviews_rows = kgh_components_get_field('reviews', $post_id);
    if ($reviews_rows) {
        foreach ($reviews_rows as $row) {
            $reviews_data[] = [
                'author_name'    => $row['author_name'] ?? '',
                'author_country' => $row['author_country'] ?? '',
                'date_text'      => $row['date_text'] ?? '',
                'review_text'    => $row['review_text'] ?? '',
            ];
        }
    }

    $data = [
        'id'        => (int)$post_id,
        'slug'      => get_post_field('post_name', $post_id),
        'title'     => get_the_title($post_id),
        'locale'    => function_exists('pll_get_post_language') ? pll_get_post_language($post_id) : get_locale(),
        'excerpt'   => get_the_excerpt($post_id),
        'header'    => $header,
        'meta'      => $meta,
        'badges'    => array_values(array_filter($badges)),
        'selling_points' => $selling,
        'discover'       => $discover,
        'dietary'        => $dietary,
        'meeting'        => $meeting,
        'how_to_find_us' => $how_to,
        'guide' => [
            'title'            => kgh_components_get_field('guide_title', $post_id),
            'guide'            => $guide_block,
            'override_badges'  => $guide_override_badges,
        ],
        'details' => [
            'title'    => kgh_components_get_field('details_title', $post_id),
            'included' => $details_included,
            'excluded' => $details_excluded,
        ],
        'bottom_highlights' => $bottom_highlights,
        'reviews' => [
            'title' => kgh_components_get_field('reviews_title', $post_id),
            'items' => $reviews_data,
        ],
    ];

    if ($full) {
        $data['content'] = apply_filters('the_content', get_post_field('post_content', $post_id));
    }

    return $data;
}

function kgh_components_prepare_guide($post_id)
{
    if (!$post_id) {
        return null;
    }
    $tags = [];
    $tag_rows = kgh_components_get_field('tags', $post_id);
    if ($tag_rows) {
        foreach ($tag_rows as $row) {
            if (!empty($row['label'])) {
                $tags[] = $row['label'];
            }
        }
    }
    return [
        'id'             => (int)$post_id,
        'slug'           => get_post_field('post_name', $post_id),
        'name'           => get_the_title($post_id),
        'photo'          => kgh_components_format_image(kgh_components_get_field('photo', $post_id) ?: get_post_thumbnail_id($post_id)),
        'experience_text'=> kgh_components_get_field('experience_text', $post_id),
        'tags'           => $tags,
        'bio'            => kgh_components_get_field('bio', $post_id),
        'languages_text' => kgh_components_get_field('languages_text', $post_id),
        'locale'         => function_exists('pll_get_post_language') ? pll_get_post_language($post_id) : get_locale(),
    ];
}
