<?php
if ( ! defined('ABSPATH') ) exit;

function kghp_paypal_base() {
  return (KGH_PAYPAL_MODE === 'live')
    ? 'https://api.paypal.com'
    : 'https://api.sandbox.paypal.com';
}

function kghp_paypal_token() {
  $resp = wp_remote_post(kghp_paypal_base().'/v1/oauth2/token', [
    'headers' => [
      'Authorization' => 'Basic '. base64_encode(KGH_PAYPAL_CLIENT_ID.':'.KGH_PAYPAL_SECRET),
      'Content-Type'  => 'application/x-www-form-urlencoded',
    ],
    'body'    => [ 'grant_type' => 'client_credentials' ],
    'timeout' => 20,
  ]);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $data = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code >= 400 || empty($data['access_token'])) {
    return new WP_Error('paypal_auth', 'PayPal auth failed', ['status'=>$code, 'raw'=>$data]);
  }
  return $data['access_token'];
}

function kghp_paypal_request($method, $path, $body = null) {
  $token = kghp_paypal_token();
  if (is_wp_error($token)) return $token;

  $args = [
    'headers' => [
      'Authorization' => 'Bearer '.$token,
      'Content-Type'  => 'application/json',
      'PayPal-Request-Id' => wp_generate_uuid4(), // idempotency léger
    ],
    'timeout' => 20,
    'method'  => strtoupper($method),
  ];
  if ($body !== null) $args['body'] = wp_json_encode($body);

  $resp = wp_remote_request(kghp_paypal_base().$path, $args);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $data = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code >= 400) {
    $msg = $data['message'] ?? 'PayPal API error';
    return new WP_Error('paypal_api', $msg, ['status'=>$code, 'raw'=>$data]);
  }
  return $data;
}
