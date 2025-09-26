<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Crée un Booking (CPT) et stocke les métas principales.
 * @param array $args
 * @return int|WP_Error post_id du booking
 */
function kgh_create_booking(array $args) {
  $tour_id      = intval($args['tour_id'] ?? 0);
  $tour_date_id = intval($args['tour_date_id'] ?? 0);
  $qty          = max(1, intval($args['qty'] ?? 1));
  $amount_total = (string)($args['amount_total'] ?? '0.00'); // USD decimal string
  $amount_usd_cents = isset($args['amount_usd_cents']) ? intval($args['amount_usd_cents']) : (int) round(floatval($amount_total) * 100);
  $currency     = strtolower($args['currency'] ?? 'usd');
  $email        = sanitize_email($args['customer_email'] ?? '');
  $slot_iso     = sanitize_text_field($args['slot_start_iso'] ?? '');
  $pp_capture   = sanitize_text_field($args['paypal_capture_id'] ?? '');
  $pp_order     = sanitize_text_field($args['paypal_order_id'] ?? '');
  $status       = sanitize_text_field($args['payment_status'] ?? 'paid');

  // Title: "{Tour Name} · {YYYY-MM-DD HH:MM KST} · {qty} pax" if slot is known
  $title = 'Booking';
  $tour_name = $tour_id ? get_the_title($tour_id) : '';
  if ($slot_iso && $tour_name) {
    // Extract HH:MM from ISO; keep date
    if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):\d{2}\+09:00$/', $slot_iso, $m)) {
      $title = sprintf('%s · %s %s KST · %d pax', $tour_name, $m[1], $m[2], $qty);
    } else {
      $title = sprintf('%s · %s · %d pax', $tour_name, $slot_iso, $qty);
    }
  } else {
    $title = sprintf('Booking — tour %d — %s x%d', $tour_id, $email ?: 'no-email', $qty);
  }

  $post_id = wp_insert_post([
    'post_type'   => 'booking',
    'post_status' => 'publish',
    'post_title'  => $title,
  ], true);

  if (is_wp_error($post_id)) return $post_id;

  update_post_meta($post_id, '_kgh_tour_id',        $tour_id);
  update_post_meta($post_id, '_kgh_tour_date_id',   $tour_date_id);
  update_post_meta($post_id, '_kgh_qty',            $qty);
  update_post_meta($post_id, '_kgh_amount_total',   $amount_total);
  update_post_meta($post_id, '_kgh_amount_usd',     $amount_usd_cents);
  update_post_meta($post_id, '_kgh_currency',       $currency);
  update_post_meta($post_id, '_kgh_customer_email', $email);
  if ($slot_iso) update_post_meta($post_id, '_kgh_slot_start_iso', $slot_iso);
  update_post_meta($post_id, '_kgh_paypal_capture', $pp_capture);
  if ($pp_order) update_post_meta($post_id, '_kgh_paypal_order_id', $pp_order);
  update_post_meta($post_id, '_kgh_payment_status', $status);

  return $post_id;
}

/**
 * Stub email — on branchera les vrais emails dans l’étape suivante.
 */
function kgh_send_emails_after_payment($tour_id, $tour_date_id, $qty, $email) {
  // Pour debug V1 : log seulement
  error_log('[KGH] Email placeholders — tour='.$tour_id.' date='.$tour_date_id.' qty='.$qty.' to='.$email);
}
