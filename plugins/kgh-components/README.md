# KGH Components

Minimal plugin that registers common building blocks for the Korean Gourmet Hunters site.

## Requirements
- WordPress 6.0+
- [Smart Custom Fields](https://wordpress.org/plugins/smart-custom-fields/)
- [Polylang](https://wordpress.org/plugins/polylang/)
- [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/) (optional, for mirrored field groups)

## Installation
1. Copy the `kgh-components` folder to `wp-content/plugins/`.
2. Activate “KGH Components” in the WordPress admin.
3. Ensure Smart Custom Fields and Polylang are active. The plugin will display admin notices if dependencies are missing.

## Structure
```
kgh-components/
├── kgh-components.php   # Main bootstrap
├── includes/
│   ├── helpers.php            # Shared getters (SCF/ACF/meta)
│   ├── cpt.php                # CPT + admin columns
│   ├── taxonomies.php         # kgh_badge registration + metabox
│   ├── polylang.php           # Language mappings
│   ├── public/badges.php      # Badge helpers
│   ├── admin/...               # Notices, preview UI
│   ├── data/...                # SCF field registration (+ optional ACF)
│   └── rest/routes.php        # JSON API
└── acf-json/                 # Reserved for local JSON field groups
```

The plugin currently provides dependency checks and bootstrap scaffolding. Future updates will register custom post types, taxonomies, REST endpoints, and ACF field groups.
