<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('kgh_components_get_field')) {
    function kgh_components_get_field($key, $post_id = null)
    {
        if (!$key) {
            return null;
        }
        if ($post_id === null) {
            $post_id = get_the_ID();
        }
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return null;
        }

        if (class_exists('SCF')) {
            try {
                $value = SCF::get($key, $post_id);
                if ($value !== null && $value !== '' && $value !== []) {
                    return $value;
                }
            } catch (Throwable $e) {
                // fall through to other providers
            }
        }

        if (function_exists('get_field')) {
            $value = get_field($key, $post_id);
            if ($value !== null && $value !== '' && $value !== []) {
                return $value;
            }
        }

        $meta = get_post_meta($post_id, $key, true);
        return ($meta === '' || $meta === null) ? null : $meta;
    }
}

if (!function_exists('kgh_components_get_term_field')) {
    function kgh_components_get_term_field($taxonomy, $term_id, $key)
    {
        $term_id = (int) $term_id;
        if ($term_id <= 0 || empty($taxonomy) || empty($key)) {
            return null;
        }

        if (class_exists('SCF')) {
            try {
                $value = SCF::get_term_meta($term_id, $taxonomy, $key);
                if (is_array($value)) {
                    $value = reset($value);
                }
                if ($value !== null && $value !== '') {
                    return $value;
                }
            } catch (Throwable $e) {
                // continue
            }
        }

        if (function_exists('get_field')) {
            $value = get_field($key, $taxonomy . '_' . $term_id);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        $meta = get_term_meta($term_id, $key, true);
        return ($meta === '' || $meta === null) ? null : $meta;
    }
}

if (!function_exists('kgh_components_format_image')) {
    function kgh_components_format_image($value)
    {
        if (!$value) {
            return null;
        }

        if (is_numeric($value)) {
            $url = wp_get_attachment_image_url((int) $value, 'full');
            return $url ?: null;
        }

        if (is_array($value)) {
            if (isset($value['ID']) && is_numeric($value['ID'])) {
                $url = wp_get_attachment_image_url((int) $value['ID'], 'full');
                if ($url) {
                    return $url;
                }
            }
            if (isset($value['url']) && is_string($value['url'])) {
                return $value['url'];
            }
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return null;
    }
}

if (!function_exists('kgh_components_format_repeater')) {
    function kgh_components_format_repeater($rows)
    {
        if (is_array($rows)) {
            return array_values($rows);
        }
        return [];
    }
}

if (!function_exists('kgh_components_make_iso_kst')) {
    function kgh_components_make_iso_kst(DateTimeImmutable $date)
    {
        return $date->setTimezone(new DateTimeZone('Asia/Seoul'))->format('Y-m-d\TH:i:sP');
    }
}
