<?php
if (!defined('ABSPATH')) exit;

// ====== KGH Tour Schedule (recurring rule as JSON) ======

// Defaults
function kgh_schedule_defaults(): array {
  return [
    'weekdays'     => [0,3,4,5,6],
    'time_slots'   => ['12:00','17:30'],
    'capacity'     => 12,
    'price_usd'    => 11500,
    'duration_min' => 180,
    'language'     => 'EN',
    'cutoff_hours' => 12,
    // Optional alternate rule: different weekdays + time slots + price
    'alt_weekdays'   => [],
    'alt_time_slots' => [],
    'alt_price_usd'  => null,
  ];
}

function kgh_is_rest_read_request(): bool {
  return (defined('REST_REQUEST') && REST_REQUEST && strtoupper(($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET');
}

// Validation + normalization; returns array or WP_Error
function kgh_schedule_validate(array $in) {
  $def = kgh_schedule_defaults();

  // weekdays
  $w = array_values(array_unique(array_map('intval', $in['weekdays'] ?? $def['weekdays'])));
  $w = array_values(array_filter($w, fn($d) => $d>=0 && $d<=6));
  sort($w);
  if (empty($w)) return new WP_Error('schedule_weekdays', __('Select at least one weekday.', 'kgh-booking'));

  // time_slots
  $ts_raw = $in['time_slots'] ?? $def['time_slots'];
  if (is_string($ts_raw)) {
    $ts_raw = array_filter(array_map('trim', explode(',', $ts_raw)));
  }
  $ts = [];
  foreach ((array)$ts_raw as $t) {
    if (!preg_match('/^\d{2}:\d{2}$/', $t)) {
      return new WP_Error('schedule_time', sprintf(__('Invalid time %s. Use HH:MM.', 'kgh-booking'), $t));
    }
    [$hh,$mm] = explode(':', $t, 2);
    $hh = (int)$hh; $mm = (int)$mm;
    if ($hh<0 || $hh>23 || !in_array($mm, [0,15,30,45], true)) {
      return new WP_Error('schedule_time', sprintf(__('Invalid time %s. Minutes must be 00, 15, 30 or 45.', 'kgh-booking'), $t));
    }
    $ts[] = sprintf('%02d:%02d', $hh, $mm);
  }
  $ts = array_values(array_unique($ts));
  if (empty($ts)) return new WP_Error('schedule_time_empty', __('Provide at least one time slot.', 'kgh-booking'));

  // capacity
  $cap = (int)($in['capacity'] ?? $def['capacity']);
  if ($cap < 1 || $cap > 50) return new WP_Error('schedule_capacity', __('Capacity must be between 1 and 50.', 'kgh-booking'));

  // price_usd (cents)
  $price = (int)($in['price_usd'] ?? $def['price_usd']);
  if ($price < 0) return new WP_Error('schedule_price', __('Price (USD cents) must be >= 0.', 'kgh-booking'));

  // duration (allow any positive number of minutes)
  $dur = (int)($in['duration_min'] ?? $def['duration_min']);
  if ($dur < 1) return new WP_Error('schedule_duration', __('Duration must be a positive number of minutes.', 'kgh-booking'));

  // language
  $lang = strtoupper(trim((string)($in['language'] ?? $def['language'])));
  if (!in_array($lang, ['EN','FR','KO'], true)) {
    return new WP_Error('schedule_language', __('Language must be EN, FR or KO.', 'kgh-booking'));
  }

  // cutoff
  $cut = (int)($in['cutoff_hours'] ?? $def['cutoff_hours']);
  if ($cut < 0 || $cut > 72) return new WP_Error('schedule_cutoff', __('Cutoff must be between 0 and 72 hours.', 'kgh-booking'));

  // --- Optional alternate rule ---
  $aw = array_values(array_unique(array_map('intval', $in['alt_weekdays'] ?? $def['alt_weekdays'])));
  $aw = array_values(array_filter($aw, fn($d) => $d>=0 && $d<=6));
  sort($aw);

  $ats_raw = $in['alt_time_slots'] ?? $def['alt_time_slots'];
  if (is_string($ats_raw)) {
    $ats_raw = array_filter(array_map('trim', explode(',', $ats_raw)));
  }
  $ats = [];
  foreach ((array)$ats_raw as $t) {
    if (!preg_match('/^\d{2}:\d{2}$/', $t)) {
      return new WP_Error('schedule_time_alt', sprintf(__('Invalid alternate time %s. Use HH:MM.', 'kgh-booking'), $t));
    }
    [$hh,$mm] = explode(':', $t, 2);
    $hh = (int)$hh; $mm = (int)$mm;
    if ($hh<0 || $hh>23 || !in_array($mm, [0,15,30,45], true)) {
      return new WP_Error('schedule_time_alt', sprintf(__('Invalid alternate time %s. Minutes must be 00, 15, 30 or 45.', 'kgh-booking'), $t));
    }
    $ats[] = sprintf('%02d:%02d', $hh, $mm);
  }
  $ats = array_values(array_unique($ats));

  $aprice = $in['alt_price_usd'] ?? $def['alt_price_usd'];
  if ($aprice !== null) { $aprice = (int)$aprice; if ($aprice < 0) return new WP_Error('schedule_alt_price', __('Alt price must be >= 0.', 'kgh-booking')); }

  // If alternate weekdays are set, require at least one time slot; price defaults to main if not provided
  if (!empty($aw) && empty($ats)) {
    return new WP_Error('schedule_alt_time_empty', __('Provide at least one alternate time slot.', 'kgh-booking'));
  }

  return [
    'weekdays'     => $w,
    'time_slots'   => $ts,
    'capacity'     => $cap,
    'price_usd'    => $price,
    'duration_min' => $dur,
    'language'     => $lang,
    'cutoff_hours' => $cut,
    'alt_weekdays'   => $aw,
    'alt_time_slots' => $ats,
    'alt_price_usd'  => $aprice,
  ];
}

// Sanitize for REST updates: accept JSON string or array, validate, return JSON string or WP_Error
function kgh_schedule_sanitize($value, $meta_key, $object_type) {
  if ($meta_key !== '_kgh_schedule') return $value;
  $arr = null;
  if (is_string($value) && $value !== '') {
    $arr = json_decode($value, true);
    if (!is_array($arr)) return new WP_Error('schedule_json', __('Invalid JSON for schedule.', 'kgh-booking'));
  } elseif (is_array($value)) {
    $arr = $value;
  } else {
    $arr = [];
  }
  $norm = kgh_schedule_validate($arr);
  if (is_wp_error($norm)) return $norm;
  return wp_json_encode($norm, JSON_UNESCAPED_SLASHES);
}

// Register meta with REST exposure
add_action('init', function() {
  register_post_meta('tour', '_kgh_schedule', [
    'type'              => 'string',
    'single'            => true,
    'show_in_rest'      => true,
    'sanitize_callback' => 'kgh_schedule_sanitize',
    'auth_callback'     => function($allowed, $meta_key, $post_id, $user_id, $cap, $caps) {
      if (kgh_is_rest_read_request()) return true; // public read
      $uid = $user_id ?: get_current_user_id();
      return user_can($uid, 'administrator');
    },
  ]);
});

// Admin metabox
add_action('add_meta_boxes', function(){
  add_meta_box(
    'kgh_tour_schedule',
    __('KGH · Schedule', 'kgh-booking'),
    'kgh_render_tour_schedule_metabox',
    'tour',
    'normal',
    'high'
  );
});

function kgh_render_tour_schedule_metabox($post){
  if (!current_user_can('administrator')) {
    echo '<p>'.esc_html__('Only administrators can edit the schedule.', 'kgh-booking').'</p>';
    return;
  }
  wp_nonce_field('kgh_save_tour_schedule','kgh_tour_schedule_nonce');
  $raw = get_post_meta($post->ID, '_kgh_schedule', true);
  $has_data = (is_string($raw) && $raw !== '');
  if ($has_data) {
    $dec = json_decode($raw, true);
    $data = is_array($dec) ? array_merge(kgh_schedule_defaults(), $dec) : kgh_schedule_defaults();
  } else {
    $data = null; // render blank for tours without a schedule
  }

  $weekdays = $data ? (array)$data['weekdays'] : [];
  $time_slots = $data ? implode(',', (array)$data['time_slots']) : '';
  $capacity = $data ? (string)(int)$data['capacity'] : '';
  $price_usd = $data ? (string)(int)$data['price_usd'] : '';
  $duration = $data ? (string)(int)$data['duration_min'] : '';
  $language = $data ? (string)$data['language'] : 'EN';
  $cutoff = $data ? (string)(int)$data['cutoff_hours'] : '';
  $alt_weekdays = $data ? (array)($data['alt_weekdays'] ?? []) : [];
  $alt_time_slots = $data ? implode(',', (array)($data['alt_time_slots'] ?? [])) : '';
  $alt_price_usd = ($data && isset($data['alt_price_usd']) && $data['alt_price_usd'] !== null) ? (string)(int)$data['alt_price_usd'] : '';

  echo '<div style="display:grid;gap:12px;max-width:740px;font-family:system-ui;">';

  // Weekdays
  echo '<div><label><strong>'.esc_html__('Weekdays','kgh-booking').'</strong></label><br/>';
  $labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  for ($d=0; $d<=6; $d++) {
    $id = 'kgh_sched_day_'.$d;
    printf('<label for="%1$s" style="margin-right:10px;"><input type="checkbox" id="%1$s" name="kgh_schedule_weekdays[]" value="%2$d" %3$s> %4$s</label>',
      esc_attr($id), $d, checked(in_array($d,$weekdays,true), true, false), esc_html($labels[$d])
    );
  }
  echo '</div>';

  // Time slots
  echo '<div><label><strong>'.esc_html__('Time slots','kgh-booking').'</strong></label><br/>';
  printf('<input type="text" name="kgh_schedule_time_slots" value="%s" placeholder="%s" style="width:360px;">',
    esc_attr($time_slots), esc_attr(__('ex: 12:00,17:30','kgh-booking'))
  );
  echo '</div>';

  // Capacity
  echo '<div><label><strong>'.esc_html__('Capacity','kgh-booking').'</strong></label><br/>';
  printf('<input type="number" name="kgh_schedule_capacity" value="%s" min="1" max="50" step="1" style="width:120px;" placeholder="12">', esc_attr($capacity));
  echo '</div>';

  // Price USD (cents)
  echo '<div><label><strong>'.esc_html__('Price USD (cents)','kgh-booking').'</strong></label><br/>';
  printf('<input type="number" name="kgh_schedule_price_usd" value="%s" min="0" step="1" style="width:160px;" placeholder="11500">', esc_attr($price_usd));
  echo '<div style="opacity:.7">'.esc_html__('in cents (11500 = $115.00)','kgh-booking').'</div></div>';

  // Duration
  echo '<div><label><strong>'.esc_html__('Duration (minutes)','kgh-booking').'</strong></label><br/>';
  printf('<input type="number" name="kgh_schedule_duration_min" value="%s" min="1" step="1" style="width:140px;" placeholder="180">', esc_attr($duration));
  echo '</div>';

  // Language
  echo '<div><label><strong>'.esc_html__('Language','kgh-booking').'</strong></label><br/>';
  echo '<select name="kgh_schedule_language">';
  foreach (['EN','FR','KO'] as $opt) {
    printf('<option value="%1$s" %2$s>%1$s</option>', esc_attr($opt), selected($language, $opt, false));
  }
  echo '</select></div>';

  // Cutoff hours
  echo '<div><label><strong>'.esc_html__('Cutoff (hours)','kgh-booking').'</strong></label><br/>';
  printf('<input type="number" name="kgh_schedule_cutoff_hours" value="%s" min="0" max="72" step="1" style="width:120px;" placeholder="12">', esc_attr($cutoff));
  echo '</div>';

  // --- Alternate rule ---
  echo '<hr style="margin:12px 0;opacity:.25">';
  echo '<div><strong>'.esc_html__('Alternate rule (optional)','kgh-booking').'</strong><div style="opacity:.8;margin-bottom:6px">'.esc_html__('Use different weekdays/slots at a different price.', 'kgh-booking').'</div>';
  // alt weekdays
  echo '<div style="margin-top:6px"><label><strong>'.esc_html__('Alt Weekdays','kgh-booking').'</strong></label><br/>';
  $labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  for ($d=0; $d<=6; $d++) {
    $id = 'kgh_sched_alt_day_'.$d;
    printf('<label for="%1$s" style="margin-right:10px;"><input type="checkbox" id="%1$s" name="kgh_schedule_alt_weekdays[]" value="%2$d" %3$s> %4$s</label>',
      esc_attr($id), $d, checked(in_array($d,$alt_weekdays,true), true, false), esc_html($labels[$d])
    );
  }
  echo '</div>';

  // alt slots
  echo '<div><label><strong>'.esc_html__('Alt Time slots','kgh-booking').'</strong></label><br/>';
  printf('<input type="text" name="kgh_schedule_alt_time_slots" value="%s" placeholder="%s" style="width:360px;">',
    esc_attr($alt_time_slots), esc_attr(__('ex: 12:00,17:30','kgh-booking'))
  );
  echo '</div>';

  // alt price
  echo '<div><label><strong>'.esc_html__('Alt Price USD (cents)','kgh-booking').'</strong></label><br/>';
  printf('<input type="number" name="kgh_schedule_alt_price_usd" value="%s" min="0" step="1" style="width:160px;">', esc_attr($alt_price_usd));
  echo '<div style="opacity:.7">'.esc_html__('leave empty to reuse main price','kgh-booking').'</div></div>';

  echo '</div>';
}

// Admin save handler
add_action('save_post_tour', function($post_id, $post){
  if (!current_user_can('administrator')) return;
  if (!isset($_POST['kgh_tour_schedule_nonce']) || !wp_verify_nonce($_POST['kgh_tour_schedule_nonce'], 'kgh_save_tour_schedule')) return;

  // collect
  $in = [];
  $in['weekdays']     = isset($_POST['kgh_schedule_weekdays']) ? (array) $_POST['kgh_schedule_weekdays'] : [];
  $in['time_slots']   = (string)($_POST['kgh_schedule_time_slots'] ?? '');
  $in['capacity']     = (int)($_POST['kgh_schedule_capacity'] ?? 0);
  $in['price_usd']    = (int)($_POST['kgh_schedule_price_usd'] ?? 0);
  $in['duration_min'] = (int)($_POST['kgh_schedule_duration_min'] ?? 0);
  $in['language']     = (string)($_POST['kgh_schedule_language'] ?? '');
  $in['cutoff_hours'] = (int)($_POST['kgh_schedule_cutoff_hours'] ?? 0);
  // alt rule
  $in['alt_weekdays']   = isset($_POST['kgh_schedule_alt_weekdays']) ? (array) $_POST['kgh_schedule_alt_weekdays'] : [];
  $in['alt_time_slots'] = (string)($_POST['kgh_schedule_alt_time_slots'] ?? '');
  $in['alt_price_usd']  = ($_POST['kgh_schedule_alt_price_usd'] === '' ? null : (int)($_POST['kgh_schedule_alt_price_usd'] ?? 0));

  // If both weekdays and time slots are empty, treat as "no schedule": clear meta and exit.
  if (empty($in['weekdays']) && trim($in['time_slots']) === '') {
    delete_post_meta($post_id, '_kgh_schedule');
    set_transient('kgh_schedule_updated', __('Schedule cleared.', 'kgh-booking'), 30);
    return;
  }

  $norm = kgh_schedule_validate($in);
  if (is_wp_error($norm)) {
    set_transient('kgh_schedule_error', $norm->get_error_message(), 30);
    return; // refuse: ne sauvegarde pas le méta
  }
  $json = wp_json_encode($norm, JSON_UNESCAPED_SLASHES);
  update_post_meta($post_id, '_kgh_schedule', $json);
  set_transient('kgh_schedule_updated', __('Schedule saved.', 'kgh-booking'), 30);
}, 10, 2);

// Admin notices
add_action('admin_notices', function(){
  if ($msg = get_transient('kgh_schedule_error')) {
    echo '<div class="notice notice-error"><p>'.esc_html($msg).'</p></div>';
    delete_transient('kgh_schedule_error');
  }
  if ($ok = get_transient('kgh_schedule_updated')) {
    echo '<div class="notice notice-success"><p>'.esc_html($ok).'</p></div>';
    delete_transient('kgh_schedule_updated');
  }
});

// Admin list columns for Tours: Weekdays, Time slots, Capacity, Price (USD)
add_filter('manage_tour_posts_columns', function($cols){
  $date = $cols['date'] ?? null; unset($cols['date']);
  $new = [];
  foreach ($cols as $k=>$v) {
    $new[$k] = $v;
    if ($k === 'title') {
      $new['kgh_sched_days'] = __('Weekdays','kgh-booking');
      $new['kgh_sched_slots'] = __('Time slots','kgh-booking');
      $new['kgh_sched_cap'] = __('Capacity','kgh-booking');
      $new['kgh_sched_price'] = __('Price (USD)','kgh-booking');
    }
  }
  if ($date) $new['date'] = $date;
  return $new;
});

add_action('manage_tour_posts_custom_column', function($col, $post_id){
  if (!in_array($col, ['kgh_sched_days','kgh_sched_slots','kgh_sched_cap','kgh_sched_price'], true)) return;
  $raw = get_post_meta($post_id, '_kgh_schedule', true);
  if (!$raw) { echo '—'; return; }
  $d = json_decode($raw, true);
  if (!is_array($d)) { echo '—'; return; }
  switch ($col) {
    case 'kgh_sched_days':
      $labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      $wd = isset($d['weekdays']) && is_array($d['weekdays']) ? array_map('intval',$d['weekdays']) : [];
      $names = array_map(function($i) use ($labels){ return $labels[$i] ?? (string)$i; }, $wd);
      echo esc_html(implode(',', $names));
      break;
    case 'kgh_sched_slots':
      $ts = isset($d['time_slots']) && is_array($d['time_slots']) ? $d['time_slots'] : [];
      echo esc_html(implode(',', $ts));
      break;
    case 'kgh_sched_cap':
      echo isset($d['capacity']) ? (int)$d['capacity'] : '—';
      break;
    case 'kgh_sched_price':
      $cents = isset($d['price_usd']) ? (int)$d['price_usd'] : null;
      if ($cents === null) { echo '—'; break; }
      $dollars = number_format($cents / 100, 2);
      echo '$'.$dollars;
      break;
  }
}, 10, 2);
