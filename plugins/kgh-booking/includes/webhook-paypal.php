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
  // Récupère les headers PayPal (lowercased par WP)
  $h = [];
  foreach (['paypal-transmission-id','paypal-transmission-time','paypal-transmission-sig','paypal-cert-url','paypal-auth-algo'] as $k) {
    $h[$k] = $req->get_header($k);
  }

  $ok = kghp_verify_webhook($raw, $h);
  if (is_wp_error($ok)) return $ok;
  if (!$ok) return new WP_Error('bad_signature','Webhook signature invalid',['status'=>400]);

  $event = json_decode($raw, true);
  $type  = $event['event_type'] ?? '';

  if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
    $res = $event['resource'] ?? [];
    // Le custom_id de notre purchase unit est recopié sur la capture
    $custom = $res['custom_id'] ?? '';

    // Parse custom_id "tour:ID;date:ID;qty:N;email:foo"
    $tour_id = $tour_date_id = $qty = 0; $email = '';
    foreach (explode(';', $custom) as $pair) {
      if (str_contains($pair, ':')) {
        [$k,$v] = array_map('trim', explode(':', $pair, 2));
        if ($k==='tour')  $tour_id = intval($v);
        if ($k==='date')  $tour_date_id = intval($v);
        if ($k==='qty')   $qty = max(1, intval($v));
        if ($k==='email') $email = sanitize_email($v);
      }
    }

    // 1) Incrémenter la capacité (booked)
    $booked = intval(get_post_meta($tour_date_id, '_kgh_capacity_left', true));
    update_post_meta($tour_date_id, '_kgh_capacity_left', $booked + $qty);

    // 2) Créer un booking
    $amount_total = intval(($res['amount']['value'] ?? 0)); // KRW
    $currency     = strtoupper($res['amount']['currency_code'] ?? 'KRW');
    $booking_id = kgh_create_booking([
      'tour_id'        => $tour_id,
      'tour_date_id'   => $tour_date_id,
      'qty'            => $qty,
      'amount_total'   => $amount_total,
      'currency'       => strtolower($currency),
      'customer_email' => $email,
      'stripe_session' => $res['id'] ?? '', // on réutilise la même meta-clé pour simpli
      'payment_status' => 'paid',
    ]);

    // 3) Emails
    kgh_send_emails_after_payment($tour_id, $tour_date_id, $qty, $email);

    return new WP_REST_RESPONSE(['ok'=>true,'booking_id'=>$booking_id], 200);
  }

  // Ignorer le reste
  return new WP_REST_RESPONSE(['ok'=>true,'type'=>$type], 200);
}
