<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('smart-cf-register-fields', 'kgh_components_register_scf_fields', 10, 4);

function kgh_components_register_scf_fields($settings, $type, $id, $meta_type)
{
    if (!class_exists('SCF')) {
        return $settings;
    }

    if ($meta_type === 'post' && $type === 'tour') {
        $settings[] = kgh_components_scf_tour_core();
        $settings[] = kgh_components_scf_tour_sections();
        $settings[] = kgh_components_scf_tour_details();
    } elseif ($meta_type === 'post' && $type === 'kgh_guide') {
        $settings[] = kgh_components_scf_guide();
    } elseif ($meta_type === 'term' && $type === 'kgh_badge') {
        $settings[] = kgh_components_scf_badge();
    }

    return $settings;
}

function kgh_components_scf_tour_core()
{
    $setting = SCF::add_setting('kgh_tour_core_1', 'KGH Tour — Core (1/3)');

    $setting->add_group('kgh_header', false, [
        [
            'name'  => 'hero_image',
            'label' => __('Hero Image', 'kgh-components'),
            'type'  => 'image',
        ],
        [
            'name'  => 'title_overline',
            'label' => __('Title Overline', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'title',
            'label' => __('Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'subtitle_badge',
            'label' => __('Subtitle Badge', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'intro_richtext',
            'label' => __('Intro Richtext', 'kgh-components'),
            'type'  => 'wysiwyg',
        ],
    ]);

    $setting->add_group('kgh_meta', false, [
        [
            'name'  => 'location_text',
            'label' => __('Location Text', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'duration_text',
            'label' => __('Duration Text', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'capacity_text',
            'label' => __('Capacity Text', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'languages_text',
            'label' => __('Languages Text', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('selling_points', true, [
        [
            'name'  => 'sp_icon',
            'label' => __('Icon', 'kgh-components'),
            'type'  => 'image',
        ],
        [
            'name'  => 'sp_text',
            'label' => __('Text', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    return $setting;
}

function kgh_components_scf_tour_sections()
{
    $setting = SCF::add_setting('kgh_tour_sections_2', 'KGH Tour — Sections (2/3)');

    $setting->add_group('discover_items', true, [
        [
            'name'  => 'item_title',
            'label' => __('Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'     => 'item_badges',
            'label'    => __('Badges', 'kgh-components'),
            'type'     => 'relation',
            'target'   => 'term',
            'taxonomy' => 'kgh_badge',
            'multiple' => true,
        ],
        [
            'name'  => 'item_description',
            'label' => __('Description', 'kgh-components'),
            'type'  => 'textarea',
        ],
        [
            'name'  => 'item_note',
            'label' => __('Note', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('dietary', false, [
        [
            'name'  => 'dietary_icon',
            'label' => __('Dietary Icon', 'kgh-components'),
            'type'  => 'image',
        ],
        [
            'name'  => 'dietary_title',
            'label' => __('Dietary Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'dietary_note',
            'label' => __('Dietary Note', 'kgh-components'),
            'type'  => 'textarea',
        ],
    ]);

    $setting->add_group('meeting', false, [
        [
            'name'  => 'meeting_title',
            'label' => __('Meeting Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'meeting_station_name',
            'label' => __('Meeting Station Name', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'meeting_description',
            'label' => __('Meeting Description', 'kgh-components'),
            'type'  => 'wysiwyg',
        ],
        [
            'name'  => 'meeting_end_note',
            'label' => __('Meeting End Note', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('map_links', true, [
        [
            'name'  => 'map_label',
            'label' => __('Label', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'map_url',
            'label' => __('URL', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('howto', false, [
        [
            'name'  => 'howto_title',
            'label' => __('Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'howto_description',
            'label' => __('Description', 'kgh-components'),
            'type'  => 'wysiwyg',
        ],
    ]);

    return $setting;
}

function kgh_components_scf_tour_details()
{
    $setting = SCF::add_setting('kgh_tour_details_3', 'KGH Tour — Details (3/3)');

    $setting->add_group('guide', false, [
        [
            'name'  => 'guide_title',
            'label' => __('Guide Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'      => 'guide_ref',
            'label'     => __('Guide Reference', 'kgh-components'),
            'type'      => 'relation',
            'target'    => 'post',
            'post-type' => 'kgh_guide',
            'multiple'  => false,
        ],
    ]);

    $setting->add_group('guide_override_badges', true, [
        [
            'name'  => 'label',
            'label' => __('Label', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('included', true, [
        [
            'name'  => 'label',
            'label' => __('Label', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('excluded', true, [
        [
            'name'  => 'label',
            'label' => __('Label', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('bottom_highlights', true, [
        [
            'name'  => 'highlight_icon',
            'label' => __('Icon', 'kgh-components'),
            'type'  => 'image',
        ],
        [
            'name'  => 'highlight_title',
            'label' => __('Title', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'highlight_subtitle',
            'label' => __('Subtitle', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'highlight_note',
            'label' => __('Note', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('reviews', true, [
        [
            'name'  => 'author_name',
            'label' => __('Author Name', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'author_country',
            'label' => __('Author Country', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'date_text',
            'label' => __('Date Text', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'review_text',
            'label' => __('Review Text', 'kgh-components'),
            'type'  => 'textarea',
        ],
    ]);

    return $setting;
}

function kgh_components_scf_guide()
{
    $setting = SCF::add_setting('kgh_guide_main', 'KGH Guide');

    $setting->add_group('guide_meta', false, [
        [
            'name'  => 'photo',
            'label' => __('Photo', 'kgh-components'),
            'type'  => 'image',
        ],
        [
            'name'  => 'name',
            'label' => __('Name', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'experience_text',
            'label' => __('Experience Text', 'kgh-components'),
            'type'  => 'text',
        ],
        [
            'name'  => 'bio',
            'label' => __('Bio', 'kgh-components'),
            'type'  => 'wysiwyg',
        ],
        [
            'name'  => 'languages_text',
            'label' => __('Languages Text', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    $setting->add_group('tags', true, [
        [
            'name'  => 'tag',
            'label' => __('Tag', 'kgh-components'),
            'type'  => 'text',
        ],
    ]);

    return $setting;
}

function kgh_components_scf_badge()
{
    $setting = SCF::add_setting('kgh_badge_meta', 'KGH Badge Meta');

    $setting->add_group('badge_meta', false, [
        [
            'name'        => 'icon',
            'label'       => __('Icon', 'kgh-components'),
            'type'        => 'image',
            'instruction' => __('Small square pictogram displayed with the badge.', 'kgh-components'),
        ],
        [
            'name'        => 'short_label',
            'label'       => __('Short Label', 'kgh-components'),
            'type'        => 'text',
            'instruction' => __('Compact text shown with the badge.', 'kgh-components'),
        ],
    ]);

    return $setting;
}
