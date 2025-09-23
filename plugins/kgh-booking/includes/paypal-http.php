
<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Helpers HTTP pour PayPal REST (OAuth2 + requêtes API)
 * Requiert dans wp-config.php :
 *   define('KGH_PAYPAL_CLIENT_ID', '...sandbox-or-live-client-id...');
 *   define('KGH_PAYPAL_SECRET',    '...sandbox-or-live-secret...');
 *   // Sandbox par défaut
 *   define('KGH_PAYPAL_BASE',     'https://api-m.sandbox.paypal.com');
 */

function kghp_paypal_base() {
  return defined('KGH_PAYPAL_BASE') ? rtrim(KGH_PAYPAL_BASE, '/') : 'https://api-m.sandbox.paypal.com';
}

/** Récupère et met en cache (transient) l'access_token OAuth2 PayPal */
function kghp_paypal_get_access_token() {
  $cached = get_transient('kghp_paypal_access_token');
  if ($cached) return $cached;

  if (!defined('KGH_PAYPAL_CLIENT_ID') || !defined('KGH_PAYPAL_SECRET')) {
    return new WP_Error('paypal_config', 'PayPal CLIENT_ID/SECRET non définis.');
  }

  $url  = kghp_paypal_base() . '/v1/oauth2/token';
  $args = [
    'method'  => 'POST',
    'headers' => [
      'Authorization' => 'Basic ' . base64_encode(KGH_PAYPAL_CLIENT_ID . ':' . KGH_PAYPAL_SECRET),
      'Content-Type'  => 'application/x-www-form-urlencoded',
    ],
    'body'    => 'grant_type=client_credentials',
    'timeout' => 20,
  ];
  $res  = wp_remote_request($url, $args);
  if (is_wp_error($res)) return $res;

  $code = wp_remote_retrieve_response_code($res);
  $body = json_decode(wp_remote_retrieve_body($res), true);
  if ($code < 200 || $code >= 300 || empty($body['access_token'])) {
    error_log('[KGH] OAuth token FAIL code='.$code.' body='.print_r($body, true));
    return new WP_Error('paypal_oauth_failed', 'OAuth2 token error', ['status'=>$code, 'response'=>$body]);
  }

  $ttl = max(60, intval($body['expires_in'] ?? 3000) - 60);
  set_transient('kghp_paypal_access_token', $body['access_token'], $ttl);

  return $body['access_token'];
}

/** Effectue une requête PayPal avec Bearer token */
function kghp_paypal_request($method, $path, $json_body = null) {
  $token = kghp_paypal_get_access_token();
  if (is_wp_error($token)) return $token;

  $url  = kghp_paypal_base() . $path;
  $args = [
    'method'  => strtoupper($method),
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Content-Type'  => 'application/json',
    ],
    'timeout' => 20,
  ];
  if ($json_body !== null && $method !== 'GET') {
    $args['body'] = wp_json_encode($json_body);
  }

  $res  = wp_remote_request($url, $args);
  if (is_wp_error($res)) return $res;

  $code = wp_remote_retrieve_response_code($res);
  $body = json_decode(wp_remote_retrieve_body($res), true);

  if ($code < 200 || $code >= 300) {
    error_log('[KGH] PayPal HTTP FAIL method='.$method.' path='.$path.' code='.$code.' body='.print_r($body, true));
    return new WP_Error('paypal_http_error', 'PayPal API error', ['status'=>$code, 'response'=>$body]);
  }
  return $body;
}
