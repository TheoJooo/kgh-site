<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * POST /wp-json/kgh/v1/paypal/order
 * Body: { tour_date_id, qty, customer_email }
 * - Vérifie la dispo (capacity_total - capacity_booked)
 * - Crée un Order PayPal (intent CAPTURE, KRW) et renvoie l'URL d'approbation
 *
 * Chemin A (dev connecté) :
 *  - Pas de vérif nonce custom ici. WP valide le header X-WP-Nonce (wp_rest).
 */

add_action('rest_api_init', function() {
  register_rest_route('kgh/v1', '/paypal/order', [
    'methods'  => 'POST',
    'callback' => 'kghp_create_paypal_order',
    // Dev: tu es connecté → WP valide X-WP-Nonce automatiquement
    'permission_callback' => function() { return is_user_logged_in(); },
  ]);
});

function kghp_create_paypal_order(WP_REST_Request $req) {
  $tour_date_id   = intval($req->get_param('tour_date_id'));
  $qty            = max(1, intval($req->get_param('qty')));
  $customer_email = sanitize_email($req->get_param('customer_email'));

  if ($tour_date_id <= 0) {
    return new WP_Error('bad_request','tour_date_id required',['status'=>400]);
  }

  $tour_id     = intval(get_post_meta($tour_date_id, '_kgh_tour_id', true));
  $price_usd   = get_post_meta($tour_date_id, '_kgh_price_usd', true); // NEW
  $price_krw   = intval(get_post_meta($tour_date_id, '_kgh_price_krw', true));
  $cap_total   = intval(get_post_meta($tour_date_id, '_kgh_capacity_total', true));
  // Modèle calculé: site + externes (plus de _kgh_capacity_left)
  $site_booked = function_exists('kgh_capacity_booked_site_qty') ? kgh_capacity_booked_site_qty($tour_date_id) : 0;
  $ext_booked  = (int) get_post_meta($tour_date_id, '_kgh_capacity_ext', true);
  if (!$ext_booked) $ext_booked = (int) get_post_meta($tour_date_id, '_kgh_booked_manual', true); // fallback legacy
  $cap_booked  = max(0, $site_booked + $ext_booked);
  $date_start  = get_post_meta($tour_date_id, '_kgh_date_start', true);

  if ($tour_id <= 0) {
    return new WP_Error('bad_data','Tour missing',['status'=>409]);
  }

  // Devise maître PayPal = USD
  $currency = defined('KGH_PAYPAL_CURRENCY') ? KGH_PAYPAL_CURRENCY : 'USD';
  if ($currency !== 'USD') {
    // sécurité: on force USD tant que c'est le master
    $currency = 'USD';
  }

  // Validation prix USD
  $price_usd = floatval($price_usd);
  if ($price_usd <= 0) {
    return new WP_Error('no_usd_price','USD price missing on this date',['status'=>409]);
  }

  if ($cap_total < 0) $cap_total = 0;
  if ($cap_booked < 0) $cap_booked = 0;

  $available = max(0, $cap_total - $cap_booked);
  if ($qty > $available) {
    return new WP_Error('capacity','Not enough seats',['status'=>422]);
  }

  $tour_title   = get_the_title($tour_id) ?: 'Tour';
  $product_name = $tour_title . ($date_start ? (' — '.$date_start) : '');

  // ==== montant USD (2 décimales obligatoires pour PayPal) ====
  $amount_value = number_format($price_usd * $qty, 2, '.', ''); // "15.00"

  $custom_id = sprintf('tour:%d;date:%d;qty:%d;email:%s',
    $tour_id, $tour_date_id, $qty, $customer_email
  );
  $success_url = function_exists('home_url') ? home_url('/checkout/success/') : '/checkout/success/';
  $cancel_url  = function_exists('home_url') ? home_url('/checkout/cancel/')  : '/checkout/cancel/';

  $body = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
      'reference_id' => (string)$tour_date_id,
      'custom_id'    => substr($custom_id, 0, 127),
      'amount'       => [
        'currency_code' => $currency, // USD
        'value'         => (string)$amount_value,
      ],
      'description'  => $product_name,
    ]],
    'application_context' => [
      'brand_name'   => get_bloginfo('name'),
      'landing_page' => 'NO_PREFERENCE',
      'user_action'  => 'PAY_NOW',
      'return_url'   => $success_url,
      'cancel_url'   => $cancel_url,
    ],
  ];

  // Requête PayPal (via helper)
  $order = kghp_paypal_request('POST', '/v2/checkout/orders', $body);
  if (is_wp_error($order)) {
    error_log('[KGH] PayPal order error: '. print_r($order, true));
    return $order;
  }
  // Cherche le lien d’approbation
  $approve = '';
  foreach (($order['links'] ?? []) as $l) {
    if (($l['rel'] ?? '') === 'approve') { $approve = $l['href']; break; }
  }
  if (!$approve) {
    return new WP_Error('paypal_no_approve','No approve URL from PayPal',
      ['status'=>502,'raw'=>$order]
    );
  }

  return new WP_REST_Response([
    'id'          => $order['id'],
    'approve_url' => $approve,
  ], 201);
}


add_action('rest_api_init', function() {
  register_rest_route('kgh/v1', '/paypal/capture', [
    'methods'  => 'POST',
    'callback' => 'kghp_capture_paypal_order',
    'permission_callback' => '__return_true', // côté success page publique
  ]);
});

function kghp_capture_paypal_order( WP_REST_Request $req ) {
  $order_id = sanitize_text_field( $req->get_param('order_id') ?: $req->get_param('token') );
  if (!$order_id) {
    return new WP_Error('bad_request','order_id (token) required', ['status'=>400]);
  }

  // Appelle l’API PayPal pour CAPTURE
  $res = kghp_paypal_request('POST', '/v2/checkout/orders/' . urlencode($order_id) . '/capture', (object)[]);

  if (is_wp_error($res)) {
    error_log('[KGH] PayPal capture error for order '.$order_id.' => '. print_r($res, true));
    return $res;
  }

  // Log pour debug
  error_log('[KGH] PayPal order captured '.$order_id.' => '. substr(json_encode($res), 0, 500));

  // Renvoie un petit résumé utile au front
  return new WP_REST_Response([
    'ok'       => true,
    'order_id' => $order_id,
    'status'   => $res['status'] ?? 'COMPLETED',
  ], 200);
}
