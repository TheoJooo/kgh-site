<?php
if (!defined('ABSPATH')) {
    exit;
}

function kgh_components_add_preview_metabox()
{
    add_meta_box(
        'kgh_components_json_preview',
        __('JSON Preview', 'kgh-components'),
        'kgh_components_render_preview_metabox',
        ['tour', 'kgh_guide'],
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'kgh_components_add_preview_metabox');

function kgh_components_render_preview_metabox($post)
{
    $data = null;
    if ($post->post_type === 'tour') {
        $data = kgh_components_prepare_tour($post->ID, true);
    } elseif ($post->post_type === 'kgh_guide') {
        $data = kgh_components_prepare_guide($post->ID);
    }
    if (!$data) {
        echo '<p>'.esc_html__('No data available.', 'kgh-components').'</p>';
        return;
    }
    echo '<p>'.esc_html__('Copy the JSON below to preview the API output for this entry.', 'kgh-components').'</p>';
    echo '<textarea readonly style="width:100%;min-height:200px;font-family:monospace;">'.esc_textarea(wp_json_encode($data, JSON_PRETTY_PRINT)).'</textarea>';
}
