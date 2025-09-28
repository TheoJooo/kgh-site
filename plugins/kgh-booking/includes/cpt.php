<?php
/**
 * Registers Custom Post Types: tour and tour_date (English labels)
 */
if ( ! defined('ABSPATH') ) exit;

// CPT: Tour
function kgh_register_cpt_tour() {
  $labels = [
    'name'                  => 'Tours',
    'singular_name'         => 'Tour',
    'menu_name'             => 'Tours',
    'name_admin_bar'        => 'Tour',
    'add_new'               => 'Add New',
    'add_new_item'          => 'Add New Tour',
    'edit_item'             => 'Edit Tour',
    'new_item'              => 'New Tour',
    'view_item'             => 'View Tour',
    'view_items'            => 'View Tours',
    'search_items'          => 'Search Tours',
    'not_found'             => 'No tours found',
    'not_found_in_trash'    => 'No tours found in Trash',
    'all_items'             => 'All Tours',
    'archives'              => 'Tour Archives',
  ];

  $args = [
    'labels'               => $labels,
    'public'               => true,
    'show_in_rest'         => true, // Gutenberg + REST
    // No content editor; keep title/thumbnail/excerpt as meta-boxes
    'supports'             => ['title','thumbnail','excerpt'],
    'menu_icon'            => 'dashicons-palmtree',
    'has_archive'          => true,
    'rewrite'              => ['slug' => 'tours'],
  ];

  register_post_type('tour', $args);
}
add_action('init', 'kgh_register_cpt_tour');

// Disable block editor (Gutenberg) for Tour → classic editor with meta-boxes
add_filter('use_block_editor_for_post_type', function($use, $post_type){
  if ($post_type === 'tour') return false;
  return $use;
}, 10, 2);

// CPT: Booking (minimal pour V1)
function kgh_register_cpt_booking() {
  $labels = [
    'name'          => 'Bookings',
    'singular_name' => 'Booking',
    'menu_name'     => 'Bookings',
    'add_new_item'  => 'Add New Booking',
    'edit_item'     => 'Edit Booking',
  ];
  $args = [
    'labels'       => $labels,
    'public'       => false,
    'show_ui'      => true,
    'show_in_menu' => 'edit.php?post_type=tour', // sous "Tours"
    'supports'     => ['title'],
  ];
  register_post_type('booking', $args);
}
add_action('init', 'kgh_register_cpt_booking');

// Admin columns for Bookings list
function kgh_booking_admin_columns(array $columns): array {
  $updated = [];
  foreach ($columns as $key => $label) {
    $updated[$key] = $label;
    if ($key === 'title') {
      $updated['kgh_email'] = 'Email';
      $updated['kgh_qty']   = 'Pax';
      $updated['kgh_when']  = 'Date/Time (KST)';
      $updated['kgh_name']  = 'Name';
      $updated['kgh_phone'] = 'Phone';
    }
  }
  return $updated;
}
add_filter('manage_booking_posts_columns', 'kgh_booking_admin_columns');

function kgh_booking_render_admin_columns(string $column, int $post_id): void {
  if ($column === 'kgh_email') {
    $value = get_post_meta($post_id, '_kgh_customer_email', true);
    echo esc_html($value !== '' ? (string) $value : '—');
    return;
  }

  if ($column === 'kgh_qty') {
    echo esc_html((string) intval(get_post_meta($post_id, '_kgh_qty', true)));
    return;
  }

  if ($column === 'kgh_when') {
    $iso = (string) get_post_meta($post_id, '_kgh_slot_start_iso', true);
    if ($iso && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $iso, $matches)) {
      echo esc_html($matches[1] . ' · ' . $matches[2] . ' KST');
    } else {
      echo esc_html('—');
    }
    return;
  }

  if ($column === 'kgh_name') {
    $first = (string) get_post_meta($post_id, '_kgh_customer_first', true);
    $last  = (string) get_post_meta($post_id, '_kgh_customer_last', true);
    $name  = trim($first . ' ' . $last);
    echo esc_html($name !== '' ? $name : '—');
    return;
  }

  if ($column === 'kgh_phone') {
    $phone = (string) get_post_meta($post_id, '_kgh_customer_phone', true);
    echo esc_html($phone !== '' ? $phone : '—');
  }
}
add_action('manage_booking_posts_custom_column', 'kgh_booking_render_admin_columns', 10, 2);

function kgh_booking_sortable_columns(array $columns): array {
  $columns['kgh_when'] = 'kgh_when';
  return $columns;
}
add_filter('manage_edit-booking_sortable_columns', 'kgh_booking_sortable_columns');

function kgh_booking_handle_column_orderby(WP_Query $query): void {
  if (!is_admin() || !$query->is_main_query()) return;
  if ($query->get('post_type') !== 'booking') return;
  if ($query->get('orderby') === 'kgh_when') {
    $query->set('meta_key', '_kgh_slot_start_iso');
    $query->set('orderby', 'meta_value');
    if (!$query->get('order')) {
      $query->set('order', 'DESC');
    }
  }
}
add_action('pre_get_posts', 'kgh_booking_handle_column_orderby');

// CPT: Tour Date
function kgh_register_cpt_tour_date() {
  $labels = [
    'name'                  => 'Tour Dates',
    'singular_name'         => 'Tour Date',
    'menu_name'             => 'Tour Dates',
    'add_new'               => 'Add New',
    'add_new_item'          => 'Add New Tour Date',
    'edit_item'             => 'Edit Tour Date',
    'new_item'              => 'New Tour Date',
    'view_item'             => 'View Tour Date',
    'view_items'            => 'View Tour Dates',
    'search_items'          => 'Search Tour Dates',
    'not_found'             => 'No tour dates found',
    'not_found_in_trash'    => 'No tour dates found in Trash',
    'all_items'             => 'All Tour Dates',
  ];

  $args = [
    'labels'               => $labels,
    'public'               => true,
    // show as a submenu under Tours
    'show_in_menu'         => 'edit.php?post_type=tour',
    'show_in_rest'         => true,
    'supports'             => ['title'],
    'menu_icon'            => 'dashicons-calendar-alt',
    'rewrite'              => ['slug' => 'tour-dates'],
  ];

  register_post_type('tour_date', $args);
}
add_action('init', 'kgh_register_cpt_tour_date');
