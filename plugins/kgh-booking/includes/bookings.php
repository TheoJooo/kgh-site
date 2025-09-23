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
  $amount_total = (string)($args['amount_total'] ?? '0.00'); // en USD pour PayPal
  $currency     = strtolower($args['currency'] ?? 'usd');
  $email        = sanitize_email($args['customer_email'] ?? '');
  $pp_capture   = sanitize_text_field($args['paypal_capture_id'] ?? '');
  $status       = sanitize_text_field($args['payment_status'] ?? 'paid');

  $title = sprintf('Booking — tour %d / date %d — %s x%d', $tour_id, $tour_date_id, $email ?: 'no-email', $qty);

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
  update_post_meta($post_id, '_kgh_currency',       $currency);
  update_post_meta($post_id, '_kgh_customer_email', $email);
  update_post_meta($post_id, '_kgh_paypal_capture', $pp_capture);
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
