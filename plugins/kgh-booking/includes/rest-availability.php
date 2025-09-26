<?php
if (!defined('ABSPATH')) exit;

// REST: Availability (days, day slots) and Quote

function kgh_rest_err($code, $message, $status=400) {
  return new WP_REST_Response(['ok'=>false,'code'=>$code,'message'=>$message], $status);
}

function kgh_rest_get_tour($id) {
  $id = (int)$id; if ($id<=0) return null;
  $p = get_post($id);
  if (!$p || $p->post_type !== 'tour') return null;
  return $p;
}

add_action('rest_api_init', function(){
  register_rest_route('kgh/v1', '/availability', [
    'methods' => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $req){
      $tour = (int)$req->get_param('tour');
      if ($tour<=0) return kgh_rest_err('BAD_REQUEST', "Missing 'tour'.", 400);
      if (!kgh_rest_get_tour($tour)) return kgh_rest_err('NOT_FOUND', 'Tour not found.', 404);
      $days = (int)($req->get_param('days') ?: 90);
      if ($days<1) $days=1; if ($days>90) $days=90;
      if (!function_exists('kgh_avail_days')) return kgh_rest_err('SERVER', 'Availability engine missing.', 500);
      $dates = kgh_avail_days($tour, $days);
      return new WP_REST_Response([
        'tour'  => $tour,
        'days'  => $days,
        'dates' => array_values($dates),
      ], 200);
    }
  ]);

  register_rest_route('kgh/v1', '/availability/day', [
    'methods' => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $req){
      $tour = (int)$req->get_param('tour');
      $date = (string)$req->get_param('date');
      if ($tour<=0) return kgh_rest_err('BAD_REQUEST', "Missing 'tour'.", 400);
      if (!kgh_rest_get_tour($tour)) return kgh_rest_err('NOT_FOUND', 'Tour not found.', 404);
      if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return kgh_rest_err('BAD_REQUEST', "Missing 'date' (YYYY-MM-DD).", 400);
      if (!function_exists('kgh_avail_day_slots')) return kgh_rest_err('SERVER', 'Availability engine missing.', 500);
      $slots = kgh_avail_day_slots($tour, $date);
      // Enrich slots with stable time formats derived from KST ISO
      $slots = array_map(function($s){
        if (!is_array($s)) return $s;
        if (!empty($s['slot_start_iso'])) {
          $ts = strtotime($s['slot_start_iso']); // ISO includes +09:00 (KST)
          $s['time_24'] = date_i18n('H:i', $ts);
          $s['time_12'] = date_i18n('h:i A', $ts);
          if (empty($s['time'])) $s['time'] = $s['time_24']; // legacy alias
        } else {
          if (empty($s['time'])) $s['time'] = '';
          if (empty($s['time_24'])) $s['time_24'] = $s['time'];
          if (empty($s['time_12'])) $s['time_12'] = $s['time'] ? $s['time'] : '';
        }
        return $s;
      }, (array)$slots);
      return new WP_REST_Response([
        'tour'  => $tour,
        'date'  => $date,
        'slots' => array_values($slots),
      ], 200);
    }
  ]);

  register_rest_route('kgh/v1', '/quote', [
    'methods' => 'POST',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $req){
      $tour = (int)$req->get_param('tour_id');
      $slot = (string)$req->get_param('slot_start_iso');
      $qty  = (int)$req->get_param('qty');
      if ($tour<=0 || !$slot || $qty<=0) return kgh_rest_err('BAD_REQUEST', 'Missing or invalid fields (tour_id, slot_start_iso, qty).', 400);
      if (!kgh_rest_get_tour($tour)) return kgh_rest_err('NOT_FOUND', 'Tour not found.', 404);
      if (!function_exists('kgh_avail_quote')) return kgh_rest_err('SERVER', 'Availability engine missing.', 500);
      $res = kgh_avail_quote($tour, $slot, $qty);
      if (is_wp_error($res)) {
        $code = $res->get_error_code();
        $msg  = $res->get_error_message();
        return kgh_rest_err($code ?: 'UNPROCESSABLE', $msg ?: 'Unprocessable', 422);
      }
      return new WP_REST_Response(array_merge($res,[
        'tour_id'        => $tour,
        'slot_start_iso' => $slot,
      ]), 200);
    }
  ]);
});
