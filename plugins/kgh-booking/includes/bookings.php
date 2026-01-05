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
  $first_name   = sanitize_text_field($args['customer_first_name'] ?? '');
  $last_name    = sanitize_text_field($args['customer_last_name'] ?? '');
  $phone        = sanitize_text_field($args['customer_phone'] ?? '');
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
  if ($first_name !== '') update_post_meta($post_id, '_kgh_customer_first_name', $first_name);
  if ($last_name  !== '') update_post_meta($post_id, '_kgh_customer_last_name',  $last_name);
  if ($phone      !== '') update_post_meta($post_id, '_kgh_customer_phone',      $phone);
  if ($slot_iso) update_post_meta($post_id, '_kgh_slot_start_iso', $slot_iso);
  update_post_meta($post_id, '_kgh_paypal_capture', $pp_capture);
  if ($pp_order) update_post_meta($post_id, '_kgh_paypal_order_id', $pp_order);
  update_post_meta($post_id, '_kgh_payment_status', $status);

  $notify_owner = !array_key_exists('notify_owner', $args) || (bool) $args['notify_owner'];
  $notify_owner = (bool) apply_filters('kgh_booking_notify_owner', $notify_owner, $post_id, $args);
  if ($notify_owner) {
    kgh_booking_increment_unread_count();
    kgh_booking_notify_owner($post_id, $args);
  }

  return $post_id;
}

/**
 * Admin notifications helpers.
 */
function kgh_booking_get_unread_count(): int {
  $count = (int) get_option('kgh_booking_unread_count', 0);
  return max(0, $count);
}

function kgh_booking_increment_unread_count(): void {
  $count = kgh_booking_get_unread_count();
  update_option('kgh_booking_unread_count', $count + 1, false);
}

function kgh_booking_reset_unread_count(): void {
  update_option('kgh_booking_unread_count', 0, false);
}

function kgh_booking_format_slot(string $slot_iso): string {
  if ($slot_iso && preg_match('/^(\\d{4}-\\d{2}-\\d{2})T(\\d{2}):(\\d{2}):\\d{2}\\+09:00$/', $slot_iso, $m)) {
    return sprintf('%s %s:%s KST', $m[1], $m[2], $m[3]);
  }
  return $slot_iso ?: '—';
}

function kgh_booking_notify_owner(int $booking_id, array $args): void {
  $to = defined('KGH_BOOKING_NOTIFY_EMAIL') ? KGH_BOOKING_NOTIFY_EMAIL : get_option('admin_email');
  $to = apply_filters('kgh_booking_notify_email', $to, $booking_id, $args);
  if (!$to) return;

  $tour_id = (int) ($args['tour_id'] ?? 0);
  $tour_name = $tour_id ? get_the_title($tour_id) : '';
  $qty = (int) ($args['qty'] ?? 1);
  $slot_iso = (string) ($args['slot_start_iso'] ?? '');
  $amount_total = (string) ($args['amount_total'] ?? '');
  $currency = strtoupper((string) ($args['currency'] ?? 'USD'));
  $first_name = sanitize_text_field($args['customer_first_name'] ?? '');
  $last_name = sanitize_text_field($args['customer_last_name'] ?? '');
  $customer = trim($first_name . ' ' . $last_name);
  $email = sanitize_email($args['customer_email'] ?? '');
  $phone = sanitize_text_field($args['customer_phone'] ?? '');

  $subject_tour = $tour_name ?: __('Tour', 'kgh-booking');
  $subject = sprintf(__('New booking · %s', 'kgh-booking'), $subject_tour);

  $lines = [];
  $lines[] = __('New booking received:', 'kgh-booking');
  $lines[] = '';
  $lines[] = sprintf(__('Tour: %s', 'kgh-booking'), $tour_name ?: '—');
  $lines[] = sprintf(__('When: %s', 'kgh-booking'), kgh_booking_format_slot($slot_iso));
  $lines[] = sprintf(__('Qty: %d', 'kgh-booking'), $qty);
  $lines[] = sprintf(__('Customer: %s', 'kgh-booking'), $customer ?: '—');
  $lines[] = sprintf(__('Email: %s', 'kgh-booking'), $email ?: '—');
  $lines[] = sprintf(__('Phone: %s', 'kgh-booking'), $phone ?: '—');
  if ($amount_total !== '') {
    $lines[] = sprintf(__('Amount: %s %s', 'kgh-booking'), $amount_total, $currency);
  }
  $edit_link = get_edit_post_link($booking_id, '');
  if ($edit_link) {
    $lines[] = '';
    $lines[] = sprintf(__('Admin link: %s', 'kgh-booking'), $edit_link);
  }

  $headers = ['Content-Type: text/plain; charset=UTF-8'];
  wp_mail($to, $subject, implode("\n", $lines), $headers);
}

add_action('admin_menu', function () {
  if (!current_user_can('edit_posts')) return;
  $count = kgh_booking_get_unread_count();
  if ($count <= 0) return;

  $badge = '<span class="update-plugins count-' . esc_attr($count) . '"><span class="plugin-count">' . esc_html(number_format_i18n($count)) . '</span></span>';

  global $submenu, $menu;
  $parent = 'edit.php?post_type=tour';
  if (empty($submenu[$parent])) return;

  foreach ($submenu[$parent] as &$item) {
    if ($item[2] === 'edit.php?post_type=booking') {
      $item[0] .= ' ' . $badge;
      break;
    }
  }

  foreach ($menu as &$item) {
    if ($item[2] === $parent) {
      $item[0] .= ' ' . $badge;
      break;
    }
  }
});

add_action('current_screen', function ($screen) {
  if (!$screen || empty($screen->id)) return;
  if ($screen->id === 'edit-booking' || $screen->id === 'booking') {
    kgh_booking_reset_unread_count();
  }
});

/**
 * Email notifications after payment (webhook).
 */
function kgh_send_emails_after_payment($tour_id, $tour_date_id, $qty, $email) {
  // Booking already created and notified in kgh_create_booking().
}
