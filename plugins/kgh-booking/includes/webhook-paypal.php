<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * POST /wp-json/kgh/v1/paypal/webhook
 * - Vérifie la signature via /v1/notifications/verify-webhook-signature
 * - Sur PAYMENT.CAPTURE.COMPLETED :
 *     lit resource.custom_id → met à jour capacité (booked), crée Booking, envoie e-mails.
 */
add_action('rest_api_init', function() {
  register_rest_route('kgh/v1', '/paypal/webhook', [
    'methods'  => 'POST',
    'callback' => 'kghp_webhook_handle',
    'permission_callback' => '__return_true',
  ]);
});

function kghp_verify_webhook($body, $headers) {
  // Docs: https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post
  $payload = [
    'transmission_id'    => $headers['paypal-transmission-id']    ?? '',
    'transmission_time'  => $headers['paypal-transmission-time']  ?? '',
    'cert_url'           => $headers['paypal-cert-url']           ?? '',
    'auth_algo'          => $headers['paypal-auth-algo']          ?? '',
    'transmission_sig'   => $headers['paypal-transmission-sig']   ?? '',
    'webhook_id'         => KGH_PAYPAL_WEBHOOK_ID,
    'webhook_event'      => json_decode($body, true),
  ];
  $resp = kghp_paypal_request('POST', '/v1/notifications/verify-webhook-signature', $payload);
  if (is_wp_error($resp)) return $resp;
  return (isset($resp['verification_status']) && $resp['verification_status'] === 'SUCCESS');
}

function kghp_webhook_handle(WP_REST_Request $req) {
  $raw = $req->get_body();

  // DEBUG: journaliser le payload et headers (utile pour local/testing)
  error_log('[KGH webhook] RAW payload: ' . substr($raw, 0, 4000));
  // log headers utiles
  $h = [];
  foreach (['paypal-transmission-id','paypal-transmission-time','paypal-transmission-sig','paypal-cert-url','paypal-auth-algo'] as $k) {
    $h[$k] = $req->get_header($k);
  }
  error_log('[KGH webhook] HEADERS: ' . json_encode($h));

  $ok = kghp_verify_webhook($raw, $h);
  error_log('[KGH webhook] verify result: ' . (is_wp_error($ok) ? 'ERROR:'.print_r($ok,true) : ($ok ? 'SUCCESS' : 'FAIL')));
  if (is_wp_error($ok)) return $ok;
  if (!$ok) return new WP_Error('bad_signature','Webhook signature invalid',['status'=>400]);

  $event = json_decode($raw, true);
  $type  = $event['event_type'] ?? '';

  // Quand l'ORDER est approuvé, on capture et on traite comme payé
  if ($type === 'CHECKOUT.ORDER.APPROVED') {
    $res = $event['resource'] ?? [];
    $order_id = $res['id'] ?? '';
    if (!$order_id) {
      error_log('[KGH webhook] APPROVED sans order_id');
      return new WP_REST_Response(['ok'=>false,'reason'=>'no order id'], 400);
    }

    // 1) CAPTURE de l'order (idempotent côté PayPal)
    $cap = kghp_paypal_request('POST', '/v2/checkout/orders/' . urlencode($order_id) . '/capture', (object)[]);
    if (is_wp_error($cap)) {
      error_log('[KGH webhook] capture error: '. print_r($cap, true));
      return $cap; // 4xx/5xx -> PayPal retentera
    }
    error_log('[KGH webhook] capture OK for order '.$order_id.' status='.($cap['status']??''));

    // 2) Récupère la capture principale (payments.captures[0])
    $pu = $cap['purchase_units'][0] ?? [];
    $cap0 = ($pu['payments']['captures'][0] ?? []);
    $capture_id   = $cap0['id'] ?? '';
    $amount_value = (string)($cap0['amount']['value'] ?? '0.00');
    $currency     = strtoupper($cap0['amount']['currency_code'] ?? 'USD');

    // 3) Retrouver notre custom_id posé à la création d'order
    $custom = $pu['custom_id'] ?? ($res['purchase_units'][0]['custom_id'] ?? '');

    // Parse "tour:ID;date:ID;qty:N;email:foo"
    $tour_id = $tour_date_id = $qty = 0; $email = ''; $slot_iso = '';
    foreach (explode(';', (string)$custom) as $pair) {
      if (strpos($pair, ':') !== false) {
        list($k,$v) = array_map('trim', explode(':', $pair, 2));
        if ($k==='tour')  $tour_id = (int)$v;
        if ($k==='date')  $tour_date_id = (int)$v;
        if ($k==='qty')   $qty = max(1, (int)$v);
        if ($k==='email') $email = sanitize_email($v);
        if ($k==='slot')  $slot_iso = $v;
      }
    }

    // 4) Anti-doublon sur la capture
    if ($capture_id) {
      $existing = get_posts([
        'post_type'   => 'booking',
        'post_status' => 'any',
        'numberposts' => 1,
        'meta_key'    => '_kgh_paypal_capture',
        'meta_value'  => $capture_id,
        'fields'      => 'ids',
      ]);
      if (!empty($existing)) {
        error_log('[KGH webhook] Duplicate capture '.$capture_id.' ignored');
        if (function_exists('kgh_capacity_invalidate')) {
          kgh_capacity_invalidate((int)$tour_date_id);
        } elseif (function_exists('kgh_capacity_invalidate_cache')) {
          kgh_capacity_invalidate_cache((int)$tour_date_id);
        }
        return new WP_REST_Response(['ok'=>true,'duplicate'=>true], 200);
      }
    }

    // 5) Crée le booking
    if (function_exists('kgh_create_booking')) {
      $amount_cents = (int) round(floatval($amount_value) * 100);
      $booking_id = kgh_create_booking([
        'tour_id'          => $tour_id,
        'tour_date_id'     => $tour_date_id,
        'qty'              => $qty,
        'amount_total'     => $amount_value,
        'amount_usd_cents' => $amount_cents,
        'currency'         => strtolower($currency),
        'customer_email'   => $email,
        'slot_start_iso'   => $slot_iso,
        'paypal_capture_id'=> $capture_id,
        'paypal_order_id'  => $order_id,
        'payment_status'   => 'paid',
      ]);
      // Compat alias for capture meta key
      if (!is_wp_error($booking_id) && $capture_id) {
        update_post_meta($booking_id, '_kgh_paypal_capture_id', $capture_id);
      }
      if (function_exists('kgh_capacity_invalidate')) {
        kgh_capacity_invalidate((int)$tour_date_id);
      } elseif (function_exists('kgh_capacity_invalidate_cache')) {
        kgh_capacity_invalidate_cache((int)$tour_date_id);
      }
      if (function_exists('kgh_avail_invalidate_day_cache') && $slot_iso) {
        kgh_avail_invalidate_day_cache((int)$tour_id, substr($slot_iso,0,10));
      }
      if (function_exists('kgh_send_emails_after_payment')) {
        kgh_send_emails_after_payment($tour_id, $tour_date_id, $qty, $email);
      }
      error_log('[KGH webhook] booking created #'. (is_wp_error($booking_id)? 'ERR' : $booking_id) .' for capture '.$capture_id);
    } else {
      error_log('[KGH webhook] WARNING: kgh_create_booking() absent');
    }

    // Remove hold if any
    if (function_exists('kgh_remove_hold') && $slot_iso) {
      kgh_remove_hold((int)$tour_id, $slot_iso, $order_id);
    }

    // 7) Répond 200 pour que PayPal arrête de retenter
    return new WP_REST_Response(['ok'=>true,'captured'=>true,'order'=>$order_id,'capture'=>$capture_id], 200);
  }

  if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
    $res = $event['resource'] ?? [];
    $custom = $res['custom_id'] ?? '';

    // Debug utile
    error_log('[KGH] WEBHOOK capture completed raw='.substr($raw,0,500));

    // Parse custom_id "tour:ID;date:ID;qty:N;email:foo"
    $tour_id = $tour_date_id = $qty = 0; $email = ''; $slot_iso = '';
    foreach (explode(';', $custom) as $pair) {
      if (str_contains($pair, ':')) {
        [$k,$v] = array_map('trim', explode(':', $pair, 2));
        if ($k==='tour')  $tour_id = intval($v);
        if ($k==='date')  $tour_date_id = intval($v);
        if ($k==='qty')   $qty = max(1, intval($v));
        if ($k==='email') $email = sanitize_email($v);
        if ($k==='slot')  $slot_iso = $v;
      }
    }

    // 1) Créer un booking
    // PayPal envoie amount.value en décimal (USD ici), ex: "170.00"
    $amount_value = (string)($res['amount']['value'] ?? '0.00');
    $currency     = strtolower($res['amount']['currency_code'] ?? 'USD');
    $amount_cents = (int) round(floatval($amount_value) * 100);

    // Évite de traiter deux fois la même capture
    $capture_id = sanitize_text_field($res['id'] ?? '');
    if ($capture_id) {
      $existing = get_posts([
        'post_type'   => 'booking',
        'post_status' => 'any',
        'numberposts' => 1,
        'meta_key'    => '_kgh_paypal_capture',
        'meta_value'  => $capture_id,
        'fields'      => 'ids',
      ]);
      if (!empty($existing)) {
        error_log('[KGH] Duplicate capture '.$capture_id.' ignored');
        if (function_exists('kgh_capacity_invalidate')) {
          kgh_capacity_invalidate((int)$tour_date_id);
        } elseif (function_exists('kgh_capacity_invalidate_cache')) {
          kgh_capacity_invalidate_cache((int)$tour_date_id);
        }
        return new WP_REST_Response(['ok'=>true,'duplicate'=>true], 200);
      }
    }


    $booking_id = kgh_create_booking([
      'tour_id'          => $tour_id,
      'tour_date_id'     => $tour_date_id,
      'qty'              => $qty,
      'amount_total'     => $amount_value, // USD, décimal
      'amount_usd_cents' => $amount_cents,
      'currency'         => $currency,
      'customer_email'   => $email,
      'slot_start_iso'   => $slot_iso,
      'paypal_capture_id'=> $capture_id,
      'payment_status'   => 'paid',
    ]);
    // Compat alias for capture meta key
    if (!is_wp_error($booking_id) && $capture_id) {
      update_post_meta($booking_id, '_kgh_paypal_capture_id', $capture_id);
    }
    if (function_exists('kgh_capacity_invalidate')) {
      kgh_capacity_invalidate((int)$tour_date_id);
    } elseif (function_exists('kgh_capacity_invalidate_cache')) {
      kgh_capacity_invalidate_cache((int)$tour_date_id);
    }

    error_log('[KGH] Booking created id='. (is_wp_error($booking_id)? 'ERR' : $booking_id) .' for tour_date='.$tour_date_id.' qty='.$qty.' amount='.$amount_value.' '.$currency);
    if (function_exists('kgh_avail_invalidate_day_cache') && $slot_iso) {
      kgh_avail_invalidate_day_cache((int)$tour_id, substr($slot_iso,0,10));
    }

    // Remove hold if any (try to find order_id from related_ids)
    if (function_exists('kgh_remove_hold') && $slot_iso) {
      $order_id = $res['supplementary_data']['related_ids']['order_id'] ?? '';
      if ($order_id) kgh_remove_hold((int)$tour_id, $slot_iso, $order_id);
    }

    // 3) Emails (stub pour l’instant)
    kgh_send_emails_after_payment($tour_id, $tour_date_id, $qty, $email);

    return new WP_REST_Response(['ok'=>true,'booking_id'=>$booking_id], 200);
  }


  // Ignorer le reste
  return new WP_REST_RESPONSE(['ok'=>true,'type'=>$type], 200);
}
