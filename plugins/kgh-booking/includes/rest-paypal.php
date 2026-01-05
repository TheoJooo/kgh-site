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
    'permission_callback' => '__return_true', // public endpoint
  ]);
});

function kghp_create_paypal_order(WP_REST_Request $req) {
  $tour_id        = (int)$req->get_param('tour_id');
  $slot_start_iso = (string)$req->get_param('slot_start_iso');
  $qty            = max(1, (int)$req->get_param('qty'));
  $customer_email = sanitize_email($req->get_param('customer_email'));
  $first_name     = sanitize_text_field($req->get_param('customer_first_name'));
  $last_name      = sanitize_text_field($req->get_param('customer_last_name'));
  $phone          = sanitize_text_field($req->get_param('customer_phone'));
  $lang_param     = sanitize_text_field($req->get_param('lang'));

  // If a desired language is provided, switch context for URL generation
  if ($lang_param && function_exists('pll_switch_language')) {
    pll_switch_language($lang_param);
  }

  if ($tour_id <= 0 || !$slot_start_iso) {
    return new WP_Error('BAD_REQUEST', __('tour_id and slot_start_iso required', 'kgh-booking'), ['status'=>400]);
  }
  if (!get_post($tour_id) || get_post_type($tour_id)!=='tour') {
    return new WP_Error('NOT_FOUND', __('Tour not found', 'kgh-booking'), ['status'=>404]);
  }

  if (!function_exists('kgh_avail_quote')) {
    return new WP_Error('SERVER', __('Availability engine missing', 'kgh-booking'), ['status'=>500]);
  }
  $quote = kgh_avail_quote($tour_id, $slot_start_iso, $qty);
  if (is_wp_error($quote)) {
    $code = $quote->get_error_code();
    $msg  = $quote->get_error_message();
    return new WP_Error($code ?: 'UNPROCESSABLE', $msg ?: __('Unprocessable', 'kgh-booking'), ['status'=>422]);
  }

  // Devise PayPal = USD
  $currency = defined('KGH_PAYPAL_CURRENCY') ? KGH_PAYPAL_CURRENCY : 'USD';
  if ($currency !== 'USD') $currency = 'USD';

  $tour_title   = get_the_title($tour_id) ?: 'Tour';
  $product_name = $tour_title . ' — ' . $slot_start_iso;

  // ==== montant USD (2 décimales obligatoires pour PayPal) ====
  $amount_value = number_format(($quote['unit_usd'] / 100) * $qty, 2, '.', '');

  // Pass useful details via custom_id (<=127 chars)
  // Keep it parseable and short; strip semicolons/colons to avoid breaking parser
  $norm = function($s, $len){ $s = preg_replace('~[;:]~','-', (string)$s); return substr($s, 0, $len); };
  $custom_id = sprintf('tour:%d;slot:%s;qty:%d;email:%s;fn:%s;ln:%s;ph:%s',
    $tour_id,
    substr($slot_start_iso,0,32),
    $qty,
    $norm($customer_email,40),
    $norm($first_name,20),
    $norm($last_name,20),
    $norm($phone,20)
  );
  // Build success/cancel URLs in the current language (Polylang-aware)
  if (function_exists('get_page_by_path')) {
    $lang = function_exists('pll_current_language') ? (string) pll_current_language('slug') : '';

    // Helper: resolve page by shortcode in current language
    $resolve_by_shortcode = function($shortcode, $lang) {
      $args = [
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'suppress_filters'=> false,
        'orderby'        => 'ID',
        'order'          => 'ASC',
      ];
      // Filter by Polylang language taxonomy if available
      if ($lang && taxonomy_exists('language')) {
        $args['tax_query'] = [[
          'taxonomy' => 'language',
          'field'    => 'slug',
          'terms'    => $lang,
        ]];
      }
      $pages = get_posts($args);
      foreach ($pages as $p) {
        if (has_shortcode($p->post_content, $shortcode)) return (int) $p->ID;
      }
      return 0;
    };

    $succ_id = 0; $canc_id = 0;
    $succ_page = get_page_by_path('checkout/success');
    if ($succ_page) $succ_id = (int) $succ_page->ID;
    $canc_page = get_page_by_path('checkout/cancel');
    if ($canc_page) $canc_id = (int) $canc_page->ID;

    if (function_exists('pll_get_post')) {
      if ($succ_id) { $mapped = (int) pll_get_post($succ_id); if ($mapped) $succ_id = $mapped; }
      if ($canc_id) { $mapped = (int) pll_get_post($canc_id); if ($mapped) $canc_id = $mapped; }
    }

    // Fallback: search by shortcode in current language
    if (!$succ_id) $succ_id = $resolve_by_shortcode('kgh_checkout_success', $lang);
    if (!$canc_id) $canc_id = $resolve_by_shortcode('kgh_checkout_cancel',  $lang);

    $success_url = $succ_id ? get_permalink($succ_id) : home_url('/checkout/success/');
    $cancel_url  = $canc_id ? get_permalink($canc_id) : home_url('/checkout/cancel/');
  } else {
    $success_url = function_exists('home_url') ? home_url('/checkout/success/') : '/checkout/success/';
    $cancel_url  = function_exists('home_url') ? home_url('/checkout/cancel/')  : '/checkout/cancel/';
  }

  // Enforce current language on return URLs (Polylang supports ?lang=xx)
  if (function_exists('pll_current_language')) {
    $lang = (string) pll_current_language('slug');
    if ($lang) {
      $success_url = add_query_arg('lang', $lang, $success_url);
      $cancel_url  = add_query_arg('lang', $lang,  $cancel_url);
    }
  }

  $body = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
      'reference_id' => (string)$tour_id,
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
    return new WP_Error('paypal_no_approve', __('No approve URL from PayPal', 'kgh-booking'),
      ['status'=>502,'raw'=>$order]
    );
  }

  // Pose hold (10 min). If conflict occurred since quote => conflict
  if (!function_exists('kgh_add_hold')) {
    return new WP_Error('SERVER', __('Hold engine missing', 'kgh-booking'), ['status'=>500]);
  }
  $hold = kgh_add_hold($tour_id, $slot_start_iso, (string)$order['id'], $qty, 600);
  if (is_wp_error($hold)) {
    // Optionally attempt cancel order here (not implemented). Return conflict
    return new WP_Error('HOLD_CONFLICT', __('Slot just sold out. Please pick another time.', 'kgh-booking'), ['status'=>409]);
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
  register_rest_route('kgh/v1', '/paypal/cancel', [
    'methods'  => 'POST',
    'callback' => 'kghp_cancel_paypal_order',
    'permission_callback' => '__return_true',
  ]);
  register_rest_route('kgh/v1', '/paypal/status', [
    'methods'  => 'GET',
    'callback' => 'kghp_paypal_status',
    'permission_callback' => '__return_true',
  ]);
});

function kghp_capture_paypal_order( WP_REST_Request $req ) {
  $order_id = sanitize_text_field( $req->get_param('order_id') ?: $req->get_param('token') );
  if (!$order_id) {
    return new WP_Error('bad_request', __('order_id (token) required', 'kgh-booking'), ['status'=>400]);
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

function kghp_cancel_paypal_order( WP_REST_Request $req ) {
  $tour_id = (int)$req->get_param('tour_id');
  $slot    = (string)$req->get_param('slot_start_iso');
  $order_id= (string)$req->get_param('order_id');
  if ($tour_id<=0 || !$slot || !$order_id) return new WP_Error('BAD_REQUEST', __('tour_id, slot_start_iso, order_id required', 'kgh-booking'), ['status'=>400]);
  if (!get_post($tour_id) || get_post_type($tour_id)!=='tour') return new WP_Error('NOT_FOUND', __('Tour not found', 'kgh-booking'), ['status'=>404]);
  if (function_exists('kgh_remove_hold')) {
    kgh_remove_hold($tour_id, $slot, $order_id);
  }
  return new WP_REST_Response(['ok'=>true], 200);
}

function kghp_paypal_status( WP_REST_Request $req ) {
  $order_id = sanitize_text_field($req->get_param('order_id'));
  if (!$order_id) return new WP_REST_Response(['status'=>'not_found'], 200);
  // try to find booking by _kgh_paypal_order_id, else via capture for robustness
  $ids = get_posts([
    'post_type'   => 'booking',
    'post_status' => 'any',
    'numberposts' => 1,
    'fields'      => 'ids',
    'meta_query'  => [[ 'key'=>'_kgh_paypal_order_id','value'=>$order_id,'compare'=>'=' ]],
  ]);
  if (empty($ids)) {
    // Fallback: query PayPal for this order to get the capture id, then map to our booking
    $order = kghp_paypal_request('GET', '/v2/checkout/orders/' . urlencode($order_id));
    if (!is_wp_error($order)) {
      $pu = $order['purchase_units'][0] ?? [];
      $captures = $pu['payments']['captures'] ?? [];
      $cap = is_array($captures) && !empty($captures) ? $captures[0] : [];
      $capture_id = isset($cap['id']) ? (string)$cap['id'] : '';
      if ($capture_id !== '') {
        $ids_by_cap = get_posts([
          'post_type'   => 'booking',
          'post_status' => 'any',
          'numberposts' => 1,
          'fields'      => 'ids',
          'meta_query'  => [[ 'key'=>'_kgh_paypal_capture','value'=>$capture_id,'compare'=>'=' ]],
        ]);
        if (empty($ids_by_cap)) {
          // also try alias meta key used as compatibility
          $ids_by_cap = get_posts([
            'post_type'   => 'booking',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
            'meta_query'  => [[ 'key'=>'_kgh_paypal_capture_id','value'=>$capture_id,'compare'=>'=' ]],
          ]);
        }
        if (!empty($ids_by_cap)) {
          $ids = $ids_by_cap;
          // Cache linkage for future status lookups
          update_post_meta((int)$ids[0], '_kgh_paypal_order_id', $order_id);
        }
      }
    }
    if (empty($ids)) {
      return new WP_REST_Response(['status'=>'processing'], 200);
    }
  }
  $bid = (int)$ids[0];
  $tour_id = (int) get_post_meta($bid, '_kgh_tour_id', true);
  $slot    = (string) get_post_meta($bid, '_kgh_slot_start_iso', true);
  $qty     = (int) get_post_meta($bid, '_kgh_qty', true);
  $amount  = (int) get_post_meta($bid, '_kgh_amount_usd', true);
  $curr    = (string) get_post_meta($bid, '_kgh_currency', true) ?: 'usd';
  return new WP_REST_Response([
    'status'        => 'paid',
    'booking_id'    => $bid,
    'tour_id'       => $tour_id,
    'tour_title'    => $tour_id ? get_the_title($tour_id) : '',
    'slot_start_iso'=> $slot,
    'qty'           => $qty,
    'amount_usd'    => $amount,
    'currency'      => $curr,
  ], 200);
}
