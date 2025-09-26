<?php
if (!defined('ABSPATH')) exit;

define('KGH_AVAIL_ADMIN_SLUG', 'kgh-availability');

add_action('admin_menu', 'kgh_admin_availability_menu');
function kgh_admin_availability_menu() {
  add_submenu_page(
    'edit.php?post_type=tour',
    __('Availability', 'kgh-booking'),
    __('Availability', 'kgh-booking'),
    'manage_options',
    KGH_AVAIL_ADMIN_SLUG,
    'kgh_admin_availability_page'
  );
}

function kgh_admin_availability_page() {
  if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'kgh-booking'));
  }
  echo '<div class="wrap kgh-availability-admin">';
  echo '<h1>'.esc_html__('Availability', 'kgh-booking').'</h1>';
  echo '<p>'.esc_html__('Manage per-slot exceptions (closed, overrides, external bookings) without creating schedule posts.', 'kgh-booking').'</p>';
  echo '<div id="kgh-availability-app"></div>';
  echo '</div>';
}

add_action('admin_enqueue_scripts', 'kgh_admin_availability_assets');
function kgh_admin_availability_assets($hook) {
  if ($hook !== 'tour_page_'.KGH_AVAIL_ADMIN_SLUG) return;

  $version = defined('KGH_BOOKING_VERSION') ? KGH_BOOKING_VERSION : '0.1.0';

  wp_enqueue_style(
    'kgh-admin-availability',
    KGH_BOOKING_URL . 'assets/css/admin-availability.css',
    [],
    $version
  );

  wp_enqueue_script(
    'kgh-admin-availability',
    KGH_BOOKING_URL . 'assets/js/admin-availability.js',
    ['wp-i18n'],
    $version,
    true
  );

  $tours = get_posts([
    'post_type'      => 'tour',
    'post_status'    => 'publish',
    'numberposts'    => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
  ]);
  $tour_data = [];
  foreach ($tours as $tid) {
    $tour_data[] = [
      'id'    => (int)$tid,
      'title' => get_the_title($tid),
    ];
  }

  wp_localize_script('kgh-admin-availability', 'KGHAvailabilityAdmin', [
    'nonce'      => wp_create_nonce('wp_rest'),
    'restBase'   => esc_url_raw( rest_url('kgh/v1') ),
    'tours'      => $tour_data,
    'i18n'       => [
      'selectTour'   => __('Select a tour', 'kgh-booking'),
      'load'         => __('Load', 'kgh-booking'),
      'allTimesKst'  => __('All times KST', 'kgh-booking'),
      'leftLabel'    => __('Left', 'kgh-booking'),
      'statusOpen'   => __('Open', 'kgh-booking'),
      'statusClosed' => __('Closed', 'kgh-booking'),
      'statusCutoff' => __('Cutoff', 'kgh-booking'),
      'statusSoldOut'=> __('Sold out', 'kgh-booking'),
      'save'         => __('Save', 'kgh-booking'),
      'clear'        => __('Clear', 'kgh-booking'),
      'saved'        => __('Saved', 'kgh-booking'),
      'error'        => __('Error', 'kgh-booking'),
    ],
  ]);
}

// REST endpoints for admin availability
add_action('rest_api_init', function(){
  register_rest_route('kgh/v1', '/admin/availability', [
    [
      'methods'  => 'GET',
      'permission_callback' => 'kgh_admin_availability_capability',
      'callback' => 'kgh_admin_availability_get',
      'args'     => [
        'tour_id' => ['required'=>true, 'type'=>'integer'],
        'start'   => ['required'=>true],
        'end'     => ['required'=>true],
      ],
    ],
    [
      'methods'  => 'POST',
      'permission_callback' => 'kgh_admin_availability_capability',
      'callback' => 'kgh_admin_availability_save',
    ],
  ]);
});

function kgh_admin_availability_capability() {
  return current_user_can('manage_options');
}

function kgh_admin_availability_get(WP_REST_Request $req) {
  $tour_id = (int)$req->get_param('tour_id');
  $start   = sanitize_text_field($req->get_param('start'));
  $end     = sanitize_text_field($req->get_param('end'));

  if ($tour_id <= 0 || get_post_type($tour_id) !== 'tour') {
    return new WP_REST_Response(['error'=>'invalid_tour'], 400);
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    return new WP_REST_Response(['error'=>'invalid_range'], 400);
  }

  $schedule = function_exists('kgh_avail_load_schedule') ? kgh_avail_load_schedule($tour_id) : null;
  if (!$schedule || empty($schedule['time_slots'])) {
    return new WP_REST_Response(['error'=>'no_schedule'], 400);
  }

  $times = array_values(array_map('trim', $schedule['time_slots']));
  sort($times);

  $kst = new DateTimeZone('Asia/Seoul');
  try {
    $start_dt = new DateTimeImmutable($start.' 00:00:00', $kst);
    $end_dt   = new DateTimeImmutable($end.' 00:00:00', $kst);
  } catch (Exception $e) {
    return new WP_REST_Response(['error'=>'invalid_range'], 400);
  }
  if ($end_dt < $start_dt) {
    return new WP_REST_Response(['error'=>'invalid_range'], 400);
  }

  // Limit to max 120 days
  $max_days = 120;
  $interval = $start_dt->diff($end_dt)->days;
  if ($interval > $max_days) {
    $end_dt = $start_dt->add(new DateInterval('P'.$max_days.'D'));
  }

  // Exceptions for range
  $range_start_utc = $start_dt->setTime(0,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
  $range_end_utc   = $end_dt->add(new DateInterval('P1D'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
  $exceptions = function_exists('kgh_ex_list_for_range') ? kgh_ex_list_for_range($tour_id, $range_start_utc, $range_end_utc) : [];
  $ex_map = [];
  if ($exceptions) {
    foreach ($exceptions as $ex) {
      $dt_utc = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ex['slot_start_utc'], new DateTimeZone('UTC'));
      if (!$dt_utc) continue;
      $iso_kst = $dt_utc->setTimezone($kst)->format('Y-m-d\TH:i:sP');
      $ex_map[$iso_kst][$ex['type']] = $ex;
    }
  }

  $days = [];
  $current = $start_dt;
  while ($current <= $end_dt) {
    $ymd = $current->format('Y-m-d');
    $slots = function_exists('kgh_avail_day_slots') ? kgh_avail_day_slots($tour_id, $ymd) : [];
    $slot_map = [];
    foreach ((array)$slots as $slot) {
      if (!empty($slot['slot_start_iso'])) {
        $slot_map[$slot['slot_start_iso']] = $slot;
      }
    }

    $row_slots = [];
    foreach ($times as $time) {
      $slot_iso = $ymd.'T'.$time.':00+09:00';
      $slot_data = isset($slot_map[$slot_iso]) ? $slot_map[$slot_iso] : [];
      $row_slots[$time] = kgh_admin_availability_build_cell($tour_id, $slot_iso, $slot_data, $ex_map[$slot_iso] ?? []);
    }

    $days[] = [
      'date'  => $ymd,
      'day_label' => $current->format('D'),
      'slots' => $row_slots,
    ];

    $current = $current->add(new DateInterval('P1D'));
  }

  return new WP_REST_Response([
    'tour_id'    => $tour_id,
    'tour_title' => get_the_title($tour_id),
    'start'      => $start_dt->format('Y-m-d'),
    'end'        => $end_dt->format('Y-m-d'),
    'time_slots' => $times,
    'meta'       => [ 'time_slots' => $times ],
    'days'       => $days,
  ], 200);
}

function kgh_admin_availability_save(WP_REST_Request $req) {
  $params = $req->get_json_params();
  $action = isset($params['action']) ? sanitize_key($params['action']) : 'save';
  $tour_id = isset($params['tour_id']) ? (int)$params['tour_id'] : 0;
  $date    = isset($params['date']) ? sanitize_text_field($params['date']) : '';
  $time    = isset($params['time']) ? sanitize_text_field($params['time']) : '';

  if ($tour_id <= 0 || get_post_type($tour_id) !== 'tour') {
    return new WP_REST_Response(['error'=>'invalid_tour'], 400);
  }
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
    return new WP_REST_Response(['error'=>'invalid_slot'], 400);
  }
  $slot_iso = $date.'T'.$time.':00+09:00';

  $existing = function_exists('kgh_ex_for_slot') ? kgh_ex_for_slot($tour_id, $slot_iso) : [];
  $existing_by_type = [];
  foreach ($existing as $row) {
    $existing_by_type[$row['type']] = $row;
  }

  if ($action === 'clear') {
    foreach ($existing as $row) {
      kgh_ex_delete((int)$row['id']);
    }
  } else {
    $override_existing = $existing_by_type['override'] ?? null;
    $override_cap_current   = isset($override_existing['cap_override']) ? (int)$override_existing['cap_override'] : null;
    $override_price_current = isset($override_existing['price_usd_override']) ? (int)$override_existing['price_usd_override'] : null;
    $override_lang_current  = !empty($override_existing['lang_override']) ? $override_existing['lang_override'] : null;

    // Closed toggle (only if key provided)
    if (array_key_exists('closed', $params)) {
      $closed = (bool)$params['closed'];
      if ($closed) {
        $res = kgh_ex_add([
          'tour_id' => $tour_id,
          'slot_start_utc_iso' => $slot_iso,
          'type' => 'closed',
        ]);
        if (is_wp_error($res)) return new WP_REST_Response(['error'=>$res->get_error_code(),'message'=>$res->get_error_message()], 400);
      } elseif (!empty($existing_by_type['closed'])) {
        kgh_ex_delete((int)$existing_by_type['closed']['id']);
        error_log('[KGH availability] closed cleared for tour '.$tour_id.' slot '.$slot_iso);
      }
    }

    // Overrides (partial)
    $override_cap_provided = array_key_exists('override_cap', $params);
    $override_price_provided = array_key_exists('override_price_usd', $params) || array_key_exists('override_price', $params);
    $override_lang_provided = array_key_exists('override_lang', $params);
    if ($override_cap_provided) {
      $val = $params['override_cap'];
      if ($val === '' || $val === null) {
        if ($override_cap_current !== null) error_log('[KGH availability] override cap cleared for tour '.$tour_id.' slot '.$slot_iso);
        $override_cap_current = null;
      } else {
        $override_cap_current = max(0, min(50, (int)$val));
      }
    }
    if ($override_price_provided) {
      $val = $params['override_price_usd'] ?? $params['override_price'];
      if ($val === '' || $val === null) {
        if ($override_price_current !== null) error_log('[KGH availability] override price cleared for tour '.$tour_id.' slot '.$slot_iso);
        $override_price_current = null;
      } else {
        $override_price_current = max(0, (int)$val);
      }
    }
    if ($override_lang_provided) {
      $val = $params['override_lang'];
      if ($val === '' || $val === null) {
        if ($override_lang_current !== null) error_log('[KGH availability] override language cleared for tour '.$tour_id.' slot '.$slot_iso);
        $override_lang_current = null;
      } else {
        $val = strtoupper(sanitize_text_field($val));
        if (!in_array($val, ['EN','FR','KO'], true)) {
          return new WP_REST_Response(['error'=>'invalid_lang','message'=>__('Language must be EN, FR or KO', 'kgh-booking')], 400);
        }
        $override_lang_current = $val;
      }
    }
    if ($override_cap_provided || $override_price_provided || $override_lang_provided) {
      $has_override_values = ($override_cap_current !== null) || ($override_price_current !== null) || ($override_lang_current !== null);
      if ($has_override_values) {
        $res = kgh_ex_add([
          'tour_id' => $tour_id,
          'slot_start_utc_iso' => $slot_iso,
          'type' => 'override',
          'cap_override' => $override_cap_current,
          'price_usd_override' => $override_price_current,
          'lang_override' => $override_lang_current,
        ]);
        if (is_wp_error($res)) return new WP_REST_Response(['error'=>$res->get_error_code(),'message'=>$res->get_error_message()], 400);
      } elseif (!empty($existing_by_type['override'])) {
        kgh_ex_delete((int)$existing_by_type['override']['id']);
        error_log('[KGH availability] override removed for tour '.$tour_id.' slot '.$slot_iso);
      }
    }

    // External booked (partial)
    if (array_key_exists('external_booked', $params) || array_key_exists('external_qty', $params)) {
      $val = $params['external_booked'] ?? $params['external_qty'];
      $qty = ($val === '' || $val === null) ? 0 : max(0, (int)$val);
      if ($qty > 0) {
        $res = kgh_ex_add([
          'tour_id' => $tour_id,
          'slot_start_utc_iso' => $slot_iso,
          'type' => 'external_booked',
          'external_qty' => $qty,
        ]);
        if (is_wp_error($res)) return new WP_REST_Response(['error'=>$res->get_error_code(),'message'=>$res->get_error_message()], 400);
      } elseif (!empty($existing_by_type['external_booked'])) {
        kgh_ex_delete((int)$existing_by_type['external_booked']['id']);
        error_log('[KGH availability] external booked cleared for tour '.$tour_id.' slot '.$slot_iso);
      }
    }
  }

  if (function_exists('kgh_avail_invalidate_day_cache')) {
    kgh_avail_invalidate_day_cache($tour_id, $date);
  }

  $slots = function_exists('kgh_avail_day_slots') ? kgh_avail_day_slots($tour_id, $date) : [];
  $slot_data = [];
  foreach ((array)$slots as $s) {
    if (!empty($s['slot_start_iso']) && $s['slot_start_iso'] === $slot_iso) {
      $slot_data = $s;
      break;
    }
  }
  $cell = kgh_admin_availability_build_cell($tour_id, $slot_iso, $slot_data, function_exists('kgh_ex_for_slot') ? kgh_ex_for_slot($tour_id, $slot_iso) : []);

  return new WP_REST_Response([
    'date' => $date,
    'time' => $time,
    'cell' => $cell,
  ], 200);
}

function kgh_admin_availability_build_cell($tour_id, $slot_iso, $slot_data, $exceptions) {
  if (!is_array($exceptions)) {
    $exceptions = [];
  } elseif (!isset($exceptions['type'])) {
    // Possibly numeric array, normalize by type
    $normalized = [];
    foreach ($exceptions as $row) {
      if (is_array($row) && isset($row['type'])) {
        $normalized[$row['type']] = $row;
      }
    }
    $exceptions = $normalized;
  }

  $result = [
    'slot_start_iso' => $slot_iso,
    'status'   => 'open',
    'reason'   => $slot_data['reason'] ?? null,
    'sold_out' => (bool)($slot_data['sold_out'] ?? false),
    'left'     => isset($slot_data['left']) ? (int)$slot_data['left'] : null,
    'price_usd'=> isset($slot_data['price_usd']) ? (int)$slot_data['price_usd'] : null,
    'language' => $slot_data['language'] ?? '',
    'time'     => '',
    'time_24'  => '',
    'time_12'  => '',
    'closed'   => false,
    'override' => [
      'cap'        => null,
      'price_usd'  => null,
      'lang'       => null,
    ],
    'external_qty' => 0,
  ];

  $ts = $slot_iso ? strtotime($slot_iso) : false;
  if ($ts) {
    $kst = new DateTimeZone('Asia/Seoul');
    $result['time_24'] = wp_date('H:i', $ts, $kst);
    $result['time_12'] = wp_date('h:i A', $ts, $kst);
    $result['time']    = $result['time_24'];
  } else {
    if (empty($result['time']))    $result['time']    = $slot_data['time']    ?? '';
    if (empty($result['time_24'])) $result['time_24'] = $slot_data['time_24'] ?? $result['time'];
    if (empty($result['time_12'])) $result['time_12'] = $slot_data['time_12'] ?? ($result['time'] ? $result['time'] : '');
  }

  if (!empty($slot_data['reason']) && $slot_data['reason'] === 'cutoff') {
    $result['status'] = 'cutoff';
  } elseif (!empty($exceptions)) {
    foreach ($exceptions as $ex) {
      if (!is_array($ex)) continue;
      $type = $ex['type'] ?? '';
      if ($type === 'closed') {
        $result['status'] = 'closed';
        $result['closed'] = true;
        $result['left'] = 0;
      } elseif ($type === 'override') {
        if ($ex['cap_override'] !== null) $result['override']['cap'] = (int)$ex['cap_override'];
        if ($ex['price_usd_override'] !== null) $result['override']['price_usd'] = (int)$ex['price_usd_override'];
        if ($ex['lang_override']) $result['override']['lang'] = $ex['lang_override'];
      } elseif ($type === 'external_booked') {
        if ($ex['external_qty'] !== null) $result['external_qty'] = (int)$ex['external_qty'];
      }
    }
  }

  if ($result['override']['price_usd'] !== null) {
    $result['price_usd'] = (int)$result['override']['price_usd'];
  } elseif (isset($slot_data['price_usd'])) {
    $result['price_usd'] = (int)$slot_data['price_usd'];
  }
  if (!empty($result['override']['lang'])) {
    $result['language'] = $result['override']['lang'];
  }

  if ($result['status'] === 'open') {
    if ($result['sold_out']) {
      $result['status'] = 'sold_out';
    }
  }

  return $result;
}
