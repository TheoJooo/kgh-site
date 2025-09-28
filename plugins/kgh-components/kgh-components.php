<?php
/**
 * Plugin Name: KGH Components
 * Description: Core components (CPT scaffolding, REST, admin helpers) for Korean Gourmet Hunters.
 * Version: 0.1.0
 * Author: KGH Team
 * Text Domain: kgh-components
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KGH_COMPONENTS_PATH', plugin_dir_path(__FILE__));
define('KGH_COMPONENTS_URL', plugin_dir_url(__FILE__));

if (!defined('KGH_INC')) {
    define('KGH_INC', KGH_COMPONENTS_PATH . 'includes/');
}

// Core helpers first
require_once KGH_INC . 'helpers.php';
require_once KGH_INC . 'public/badges.php';

// Data & registration
require_once KGH_INC . 'data/scf-register.php';
if (file_exists(KGH_INC . 'data/acf-fields.php')) {
    require_once KGH_INC . 'data/acf-fields.php';
}

// Domain hooks
require_once KGH_INC . 'cpt.php';
require_once KGH_INC . 'taxonomies.php';
require_once KGH_INC . 'polylang.php';

// Admin tooling
require_once KGH_INC . 'admin/notices.php';
require_once KGH_INC . 'admin/badge-columns.php';
require_once KGH_INC . 'admin/tour-editor-fixes.php';
if (file_exists(KGH_INC . 'admin/preview.php')) {
    require_once KGH_INC . 'admin/preview.php';
}

// REST API
if (file_exists(KGH_INC . 'rest/routes.php')) {
    require_once KGH_INC . 'rest/routes.php';
}

register_activation_hook(__FILE__, function () {
    if (function_exists('kgh_components_register_post_types')) {
        kgh_components_register_post_types();
    }

    if (function_exists('kgh_components_register_taxonomy')) {
        kgh_components_register_taxonomy();
    }

    flush_rewrite_rules();
});
