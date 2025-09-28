<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin clickability guard for Tours editor.
 */
function kgh_components_is_tour_editor_screen(?string $hook = null): bool
{
    if (!function_exists('get_current_screen')) {
        return false;
    }

    $screen = get_current_screen();
    if (!$screen) {
        return false;
    }

    $allowed_types = ['tour', 'kgh_tour'];
    if (!in_array($screen->post_type, $allowed_types, true)) {
        return false;
    }

    if ($hook !== null && !in_array($hook, ['post.php', 'post-new.php'], true)) {
        return false;
    }

    return true;
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (!kgh_components_is_tour_editor_screen($hook)) {
        return;
    }

    $style_handles = [
        'kgh-booking',
        'kgh-theme-booking',
        'kgh-booking-ui',
        'kgh-main',
        'kgh-style',
        'kgh-google-fonts',
    ];

    foreach ($style_handles as $handle) {
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
    }

    $script_handles = [
        'kgh-booking',
        'kgh-theme-booking',
        'kgh-main-js',
    ];

    foreach ($script_handles as $handle) {
        wp_dequeue_script($handle);
        wp_deregister_script($handle);
    }
}, 20);

add_action('admin_head', function () {
    if (!kgh_components_is_tour_editor_screen()) {
        return;
    }

    echo '<style id="kgh-admin-clickability-fix">'
        . '.scf-field *, .scf-image, .scf-media, .scf-gallery, .scf-field button, .scf-field a,'
        . ' .wp-editor-wrap, .components-button, .button, .media-modal, .media-frame {'
        . ' pointer-events: auto !important;'
        . ' }'
        . ' #kgh-overlay, .kgh-overlay, .booking-overlay, .screen-blocker, .modal-backdrop {'
        . ' display: none !important; pointer-events: none !important;'
        . ' }'
        . ' #wpbody-content, #poststuff, .meta-box-sortables, .postbox, .inside {'
        . ' position: relative; z-index: 1;'
        . ' }'
        . '</style>';
});

add_action('admin_print_footer_scripts', function () {
    if (!kgh_components_is_tour_editor_screen()) {
        return;
    }
    ?>
    <script id="kgh-admin-clickability-script">
    (function(){
        var root = document.getElementById('poststuff') || document.body;
        if (!root) { return; }

        root.querySelectorAll('.scf-field input[disabled], .scf-field textarea[disabled], .scf-field button[disabled]').forEach(function(el){
            el.removeAttribute('disabled');
        });

        root.querySelectorAll('.scf-field [readonly]').forEach(function(el){
            el.removeAttribute('readonly');
        });

        document.body.classList.remove('kgh-no-click');
    })();
    </script>
    <?php
});
