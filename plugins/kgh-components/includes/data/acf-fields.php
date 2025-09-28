<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_kgh_tour',
        'title' => __('Tour Content', 'kgh-booking'),
        'fields' => [
            [
                'key' => 'kgh_tour_tab_header',
                'label' => __('Header', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_hero_image',
                'label' => __('Hero image', 'kgh-booking'),
                'name' => 'hero_image',
                'type' => 'image',
                'return_format' => 'id',
                'preview_size' => 'medium',
            ],
            [
                'key' => 'kgh_tour_title_overline',
                'label' => __('Title overline', 'kgh-booking'),
                'name' => 'title_overline',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_title_text',
                'label' => __('Title', 'kgh-booking'),
                'name' => 'title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_subtitle_badge',
                'label' => __('Subtitle badge', 'kgh-booking'),
                'name' => 'subtitle_badge',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_badges',
                'label' => __('Badges', 'kgh-booking'),
                'name' => 'badges',
                'type' => 'taxonomy',
                'taxonomy' => 'kgh_badge',
                'field_type' => 'multi_select',
                'add_term' => true,
                'return_format' => 'object',
            ],
            [
                'key' => 'kgh_tour_intro_richtext',
                'label' => __('Intro text', 'kgh-booking'),
                'name' => 'intro_richtext',
                'type' => 'wysiwyg',
                'tabs' => 'all',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],

            [
                'key' => 'kgh_tour_tab_meta',
                'label' => __('Meta', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_meta_location',
                'label' => __('Location text', 'kgh-booking'),
                'name' => 'location_text',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_meta_duration',
                'label' => __('Duration text', 'kgh-booking'),
                'name' => 'duration_text',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_meta_capacity',
                'label' => __('Capacity text', 'kgh-booking'),
                'name' => 'capacity_text',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_meta_languages',
                'label' => __('Languages text', 'kgh-booking'),
                'name' => 'languages_text',
                'type' => 'text',
            ],

            [
                'key' => 'kgh_tour_tab_selling_points',
                'label' => __('Selling Points', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_selling_points',
                'label' => __('Selling points', 'kgh-booking'),
                'name' => 'selling_points',
                'type' => 'repeater',
                'button_label' => __('Add selling point', 'kgh-booking'),
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_selling_icon',
                        'label' => __('Icon', 'kgh-booking'),
                        'name' => 'sp_icon',
                        'type' => 'image',
                        'return_format' => 'id',
                    ],
                    [
                        'key' => 'kgh_tour_selling_text',
                        'label' => __('Text', 'kgh-booking'),
                        'name' => 'sp_text',
                        'type' => 'text',
                    ],
                ],
            ],

            [
                'key' => 'kgh_tour_tab_discover',
                'label' => __('What you’ll discover', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_discover_items',
                'label' => __('Discover items', 'kgh-booking'),
                'name' => 'discover_items',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => __('Add item', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_discover_title',
                        'label' => __('Title', 'kgh-booking'),
                        'name' => 'item_title',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_discover_badges',
                        'label' => __('Badges', 'kgh-booking'),
                        'name' => 'item_badges',
                        'type' => 'taxonomy',
                        'taxonomy' => 'kgh_badge',
                        'field_type' => 'multi_select',
                        'return_format' => 'object',
                        'add_term' => true,
                    ],
                    [
                        'key' => 'kgh_tour_discover_desc',
                        'label' => __('Description', 'kgh-booking'),
                        'name' => 'item_description',
                        'type' => 'textarea',
                        'rows' => 3,
                    ],
                    [
                        'key' => 'kgh_tour_discover_note',
                        'label' => __('Note', 'kgh-booking'),
                        'name' => 'item_note',
                        'type' => 'text',
                    ],
                ],
            ],

            [
                'key' => 'kgh_tour_tab_dietary',
                'label' => __('Dietary Requirements', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_dietary_icon',
                'label' => __('Icon', 'kgh-booking'),
                'name' => 'dietary_icon',
                'type' => 'image',
                'return_format' => 'id',
            ],
            [
                'key' => 'kgh_tour_dietary_title',
                'label' => __('Title', 'kgh-booking'),
                'name' => 'dietary_title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_dietary_note',
                'label' => __('Note', 'kgh-booking'),
                'name' => 'dietary_note',
                'type' => 'textarea',
                'rows' => 2,
            ],

            [
                'key' => 'kgh_tour_tab_meeting',
                'label' => __('Meeting Point', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_meeting_title',
                'label' => __('Title', 'kgh-booking'),
                'name' => 'meeting_title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_meeting_station',
                'label' => __('Station name', 'kgh-booking'),
                'name' => 'meeting_station_name',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_meeting_description',
                'label' => __('Description', 'kgh-booking'),
                'name' => 'meeting_description',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'key' => 'kgh_tour_map_links',
                'label' => __('Map links', 'kgh-booking'),
                'name' => 'map_links',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => __('Add map link', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_map_label',
                        'label' => __('Label', 'kgh-booking'),
                        'name' => 'map_label',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_map_url',
                        'label' => __('URL', 'kgh-booking'),
                        'name' => 'map_url',
                        'type' => 'url',
                    ],
                ],
            ],
            [
                'key' => 'kgh_tour_meeting_end_note',
                'label' => __('End note', 'kgh-booking'),
                'name' => 'meeting_end_note',
                'type' => 'textarea',
                'rows' => 3,
            ],

            [
                'key' => 'kgh_tour_tab_howto',
                'label' => __('How to find us', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_howto_title',
                'label' => __('Title', 'kgh-booking'),
                'name' => 'howto_title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_howto_desc',
                'label' => __('Description', 'kgh-booking'),
                'name' => 'howto_description',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],

            [
                'key' => 'kgh_tour_tab_guide',
                'label' => __('Guide', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_guide_title',
                'label' => __('Guide title', 'kgh-booking'),
                'name' => 'guide_title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_guide_ref',
                'label' => __('Guide reference', 'kgh-booking'),
                'name' => 'guide_ref',
                'type' => 'post_object',
                'post_type' => ['kgh_guide'],
                'return_format' => 'object',
            ],
            [
                'key' => 'kgh_tour_guide_override_badges',
                'label' => __('Guide override badges', 'kgh-booking'),
                'name' => 'guide_override_badges',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => __('Add label', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_guide_override_badge_text',
                        'label' => __('Label', 'kgh-booking'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                ],
            ],

            [
                'key' => 'kgh_tour_tab_details',
                'label' => __('Tour Details', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_details_title',
                'label' => __('Details title', 'kgh-booking'),
                'name' => 'details_title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_details_included',
                'label' => __('Included', 'kgh-booking'),
                'name' => 'included',
                'type' => 'repeater',
                'button_label' => __('Add item', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_details_included_label',
                        'label' => __('Label', 'kgh-booking'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'key' => 'kgh_tour_details_excluded',
                'label' => __('Not included', 'kgh-booking'),
                'name' => 'excluded',
                'type' => 'repeater',
                'button_label' => __('Add item', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_details_excluded_label',
                        'label' => __('Label', 'kgh-booking'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'key' => 'kgh_tour_bottom_highlights',
                'label' => __('Bottom highlights', 'kgh-booking'),
                'name' => 'bottom_highlights',
                'type' => 'repeater',
                'button_label' => __('Add highlight', 'kgh-booking'),
                'layout' => 'block',
                'max' => 3,
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_highlight_icon',
                        'label' => __('Icon', 'kgh-booking'),
                        'name' => 'highlight_icon',
                        'type' => 'image',
                        'return_format' => 'id',
                    ],
                    [
                        'key' => 'kgh_tour_highlight_title',
                        'label' => __('Title', 'kgh-booking'),
                        'name' => 'highlight_title',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_highlight_subtitle',
                        'label' => __('Subtitle', 'kgh-booking'),
                        'name' => 'highlight_subtitle',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_highlight_note',
                        'label' => __('Note', 'kgh-booking'),
                        'name' => 'highlight_note',
                        'type' => 'text',
                    ],
                ],
            ],

            [
                'key' => 'kgh_tour_tab_reviews',
                'label' => __('Reviews', 'kgh-booking'),
                'type' => 'tab',
            ],
            [
                'key' => 'kgh_tour_reviews_title',
                'label' => __('Reviews title', 'kgh-booking'),
                'name' => 'reviews_title',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_tour_reviews',
                'label' => __('Reviews', 'kgh-booking'),
                'name' => 'reviews',
                'type' => 'repeater',
                'layout' => 'row',
                'button_label' => __('Add review', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_tour_review_author',
                        'label' => __('Author name', 'kgh-booking'),
                        'name' => 'author_name',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_review_country',
                        'label' => __('Country', 'kgh-booking'),
                        'name' => 'author_country',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_review_date',
                        'label' => __('Date text', 'kgh-booking'),
                        'name' => 'date_text',
                        'type' => 'text',
                    ],
                    [
                        'key' => 'kgh_tour_review_text',
                        'label' => __('Review text', 'kgh-booking'),
                        'name' => 'review_text',
                        'type' => 'textarea',
                        'rows' => 4,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'tour',
                ],
            ],
        ],
    ]);

    // Guides
    acf_add_local_field_group([
        'key' => 'group_kgh_guide',
        'title' => __('Guide Content', 'kgh-booking'),
        'fields' => [
            [
                'key' => 'kgh_guide_photo',
                'label' => __('Photo', 'kgh-booking'),
                'name' => 'photo',
                'type' => 'image',
                'return_format' => 'id',
            ],
            [
                'key' => 'kgh_guide_experience',
                'label' => __('Experience text', 'kgh-booking'),
                'name' => 'experience_text',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_guide_tags',
                'label' => __('Tags', 'kgh-booking'),
                'name' => 'tags',
                'type' => 'repeater',
                'button_label' => __('Add tag', 'kgh-booking'),
                'sub_fields' => [
                    [
                        'key' => 'kgh_guide_tag_label',
                        'label' => __('Label', 'kgh-booking'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'key' => 'kgh_guide_bio',
                'label' => __('Bio', 'kgh-booking'),
                'name' => 'bio',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'key' => 'kgh_guide_languages_text',
                'label' => __('Languages text', 'kgh-booking'),
                'name' => 'languages_text',
                'type' => 'text',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'kgh_guide',
                ],
            ],
        ],
    ]);

    // Badge meta
    acf_add_local_field_group([
        'key' => 'group_kgh_badge',
        'title' => __('Badge Meta', 'kgh-booking'),
        'fields' => [
            [
                'key' => 'kgh_badge_icon',
                'label' => __('Icon', 'kgh-booking'),
                'name' => 'icon',
                'type' => 'image',
                'return_format' => 'id',
            ],
            [
                'key' => 'kgh_badge_color',
                'label' => __('Color', 'kgh-booking'),
                'name' => 'color',
                'type' => 'color_picker',
            ],
            [
                'key' => 'kgh_badge_short_label',
                'label' => __('Short label', 'kgh-booking'),
                'name' => 'short_label',
                'type' => 'text',
            ],
            [
                'key' => 'kgh_badge_slug_tech',
                'label' => __('Technical slug', 'kgh-booking'),
                'name' => 'slug_tech_display',
                'type' => 'text',
                'instructions' => __('Auto-generated from slug.', 'kgh-booking'),
                'wrapper' => ['class' => 'acf-disabled'],
                'readonly' => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'kgh_badge',
                ],
            ],
        ],
    ]);
});
