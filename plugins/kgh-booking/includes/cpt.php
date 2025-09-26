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
