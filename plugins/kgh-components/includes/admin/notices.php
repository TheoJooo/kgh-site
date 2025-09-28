<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_notices', function () {
    if (!current_user_can('activate_plugins')) {
        return;
    }

    if (!class_exists('SCF')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('KGH Components requires Smart Custom Fields to be installed and activated.', 'kgh-components') . '</p></div>';
    }

    if (!defined('POLYLANG_VERSION')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('KGH Components requires Polylang to be installed and activated.', 'kgh-components') . '</p></div>';
    }
});
