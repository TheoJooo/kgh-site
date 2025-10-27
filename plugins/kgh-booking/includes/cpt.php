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

// Admin columns for Bookings
add_filter('manage_edit-booking_columns', function($cols){
  $new = [];
  $new['cb']       = $cols['cb'];
  $new['title']    = __('Booking', 'kgh-booking');
  $new['tour']     = __('Tour', 'kgh-booking');
  $new['when']     = __('Date/Time (KST)', 'kgh-booking');
  $new['qty']      = __('Qty', 'kgh-booking');
  $new['customer'] = __('Customer', 'kgh-booking');
  $new['contact']  = __('Email / Phone', 'kgh-booking');
  $new['total']    = __('Total (USD)', 'kgh-booking');
  $new['date']     = $cols['date'];
  return $new;
});

add_action('manage_booking_posts_custom_column', function($col, $post_id){
  switch ($col) {
    case 'tour':
      $tid = (int) get_post_meta($post_id, '_kgh_tour_id', true);
      echo $tid ? esc_html(get_the_title($tid)) : '—';
      break;
    case 'when':
      $iso = (string) get_post_meta($post_id, '_kgh_slot_start_iso', true);
      if ($iso && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2}):/',$iso,$m)){
        $h = (int)$m[2]; $ampm = $h>=12?'PM':'AM'; $h=((($h+11)%12)+1);
        printf('%s · %02d:%s %s KST', esc_html($m[1]), $h, esc_html($m[3]), $ampm);
      } else {
        echo $iso ? esc_html($iso) : '—';
      }
      break;
    case 'qty':
      echo (int) get_post_meta($post_id, '_kgh_qty', true);
      break;
    case 'customer':
      $fn = get_post_meta($post_id, '_kgh_customer_first_name', true);
      $ln = get_post_meta($post_id, '_kgh_customer_last_name', true);
      $name = trim($fn.' '.$ln);
      if (!$name) $name = '—';
      echo esc_html($name);
      break;
    case 'contact':
      $em = get_post_meta($post_id, '_kgh_customer_email', true);
      $ph = get_post_meta($post_id, '_kgh_customer_phone', true);
      $out = [];
      if ($em) $out[] = '<a href="mailto:'.esc_attr($em).'">'.esc_html($em).'</a>';
      if ($ph) $out[] = '<a href="tel:'.esc_attr(preg_replace('~\s+~','', $ph)).'">'.esc_html($ph).'</a>';
      echo $out ? implode('<br>', $out) : '—';
      break;
    case 'total':
      $cents = (int) get_post_meta($post_id, '_kgh_amount_usd', true);
      printf('$%0.2f', $cents/100);
      break;
  }
}, 10, 2);

add_filter('manage_edit-booking_sortable_columns', function($cols){
  $cols['qty'] = 'qty';
  $cols['total'] = 'total';
  return $cols;
});

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
