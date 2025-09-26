<?php
if (!defined('ABSPATH')) exit;

// Availability engine (virtual slots using schedule + exceptions + bookings + holds)

// ----- Holds stub (Étape 5 will implement real holds) -----
function kgh_hold_key(int $tour_id, string $slot_start_iso_kst): string {
  $hash = md5($slot_start_iso_kst);
  return 'kgh_hold_' . $tour_id . '_' . $hash;
}

function kgh_avail_invalidate_day_cache(int $tour_id, string $ymd_kst): void {
  delete_transient('kgh_av_day_' . $tour_id . '_' . $ymd_kst);
}

function kgh_active_holds_qty(int $tour_id, string $slot_start_iso_kst): int {
  $key = kgh_hold_key($tour_id, $slot_start_iso_kst);
  $data = get_transient($key);
  if (!$data || !is_array($data)) return 0;
  $now = time();
  $entries = array_filter((array)($data['entries'] ?? []), function($e) use ($now){
    return isset($e['exp_ts']) && (int)$e['exp_ts'] > $now;
  });
  if (empty($entries)) { delete_transient($key); return 0; }
  $total = 0;
  foreach ($entries as $e) { $total += max(0, (int)($e['qty'] ?? 0)); }
  // refresh transient with purged list; set TTL from soonest expiry (or 600 default)
  $exp_min = min(array_map(fn($e)=> (int)$e['exp_ts'], $entries));
  $ttl = max(1, $exp_min - $now);
  set_transient($key, ['entries'=>array_values($entries), 'total'=>$total], $ttl);
  return $total;
}

function kgh_add_hold(int $tour_id, string $slot_start_iso_kst, string $hold_id, int $qty, int $ttl_sec = 600) {
  $qty = max(1, (int)$qty);
  $res = kgh_avail_quote($tour_id, $slot_start_iso_kst, $qty);
  if (is_wp_error($res)) return $res; // propagate domain errors (422)

  $key = kgh_hold_key($tour_id, $slot_start_iso_kst);
  $lock_key = $key . '_lock';
  // light lock using wp_cache_add (non-persistent, but fine for V1)
  $got_lock = false;
  for ($i=0; $i<10; $i++) {
    if (wp_cache_add($lock_key, 1, '', 2)) { $got_lock = true; break; }
    usleep(100000); // 100ms
  }
  if (!$got_lock) return new WP_Error('BUSY', __('Please retry in a moment.', 'kgh-booking'), ['status'=>409]);

  try {
    $now = time();
    $data = get_transient($key);
    $entries = [];
    if (is_array($data) && !empty($data['entries'])) {
      foreach ($data['entries'] as $e) {
        if ((int)($e['exp_ts'] ?? 0) > $now) $entries[] = $e;
      }
    }
    // idempotent refresh
    $found = false;
    foreach ($entries as &$e) {
      if (($e['id'] ?? '') === $hold_id) {
        $e['exp_ts'] = $now + $ttl_sec; // refresh only
        $found = true; break;
      }
    }
    unset($e);

    if (!$found) {
      // race check: ensure left_before >= qty
      if ((int)$res['left_before'] < $qty) {
        return new WP_Error('NOT_ENOUGH_LEFT', __('Not enough seats left', 'kgh-booking'), ['status'=>422]);
      }
      $entries[] = [ 'id' => $hold_id, 'qty' => $qty, 'exp_ts' => $now + $ttl_sec ];
    }

    $total = 0; foreach ($entries as $e) { $total += max(0,(int)$e['qty']); }
    set_transient($key, ['entries'=>$entries,'total'=>$total], $ttl_sec);
  } finally {
    wp_cache_delete($lock_key);
  }

  // Invalidate day cache
  kgh_avail_invalidate_day_cache($tour_id, substr($slot_start_iso_kst, 0, 10));

  return [ 'ok'=>true, 'hold_id'=>$hold_id, 'left_after_hold' => max(0, ((int)$res['left_before']) - $qty) ];
}

function kgh_remove_hold(int $tour_id, string $slot_start_iso_kst, string $hold_id) {
  $key = kgh_hold_key($tour_id, $slot_start_iso_kst);
  $data = get_transient($key);
  if (!$data || !is_array($data) || empty($data['entries'])) {
    // Invalidate day cache regardless
    kgh_avail_invalidate_day_cache($tour_id, substr($slot_start_iso_kst, 0, 10));
    return true;
  }
  $now = time(); $changed = false; $entries = [];
  foreach ($data['entries'] as $e) {
    if ((int)($e['exp_ts'] ?? 0) <= $now) { $changed = true; continue; }
    if (($e['id'] ?? '') === $hold_id) { $changed = true; continue; }
    $entries[] = $e;
  }
  if (!$changed) return true;
  if (empty($entries)) { delete_transient($key); }
  else {
    $total = 0; foreach ($entries as $e) { $total += max(0,(int)$e['qty']); }
    // set TTL to min remaining
    $exp_min = min(array_map(fn($e)=> (int)$e['exp_ts'], $entries));
    $ttl = max(1, $exp_min - time());
    set_transient($key, ['entries'=>$entries,'total'=>$total], $ttl);
  }
  // Invalidate day cache
  kgh_avail_invalidate_day_cache($tour_id, substr($slot_start_iso_kst, 0, 10));
  return true;
}

// ----- Sum bookings (site) for a slot -----
// Criteria: CPT 'booking', meta _kgh_tour_id = tour_id, meta _kgh_slot_start_iso = slot_start_iso_kst, payment_status = paid if present
function kgh_sum_booked_qty(int $tour_id, string $slot_start_iso_kst): int {
  global $wpdb;
  $post_type = 'booking';
  $pm = $wpdb->postmeta; $p = $wpdb->posts;

  // With payment_status=paid if meta exists; otherwise sum all
  $sql = $wpdb->prepare(
    "SELECT SUM(CAST(qm.meta_value AS UNSIGNED)) AS qty
     FROM {$p} AS posts
     INNER JOIN {$pm} AS tm ON tm.post_id = posts.ID AND tm.meta_key = %s AND tm.meta_value = %d
     INNER JOIN {$pm} AS sm ON sm.post_id = posts.ID AND sm.meta_key = %s AND sm.meta_value = %s
     INNER JOIN {$pm} AS qm ON qm.post_id = posts.ID AND qm.meta_key = %s
     LEFT JOIN {$pm} AS pay ON pay.post_id = posts.ID AND pay.meta_key = %s
     WHERE posts.post_type = %s AND posts.post_status IN ('publish')
       AND (pay.meta_id IS NULL OR pay.meta_value = 'paid')",
    '_kgh_tour_id', $tour_id,
    '_kgh_slot_start_iso', $slot_start_iso_kst,
    '_kgh_qty',
    '_kgh_payment_status',
    $post_type
  );
  $val = $wpdb->get_var($sql);
  return (int) $val;
}

// ----- Helpers: schedule loader -----
function kgh_avail_load_schedule(int $tour_id): ?array {
  $raw = get_post_meta($tour_id, '_kgh_schedule', true);
  if (!$raw) return null;
  $d = json_decode($raw, true);
  if (!is_array($d)) return null;
  return $d;
}

// ----- Formatters/time -----
function kgh_kst_tz(): DateTimeZone { return new DateTimeZone('Asia/Seoul'); }

function kgh_avail_iso_kst(DateTimeImmutable $dt_kst): string {
  $kst = kgh_kst_tz();
  return $dt_kst->setTimezone($kst)->format('Y-m-d\TH:i:sP'); // +09:00
}

// ----- Slot computation for a day (KST) -----
function kgh_avail_day_slots(int $tour_id, string $ymd_kst): array {
  // Optional lightweight cache (60s)
  $ckey = 'kgh_av_day_'. $tour_id . '_' . $ymd_kst;
  $cached = get_transient($ckey);
  if ($cached !== false) return is_array($cached) ? $cached : [];

  $sched = kgh_avail_load_schedule($tour_id);
  if (!$sched) { set_transient($ckey, [], 30); return []; }

  $kst = kgh_kst_tz();
  try { $day = new DateTimeImmutable($ymd_kst.' 00:00:00', $kst); }
  catch (Exception $e) { set_transient($ckey, [], 30); return []; }

  $wd = (int)$day->format('w'); // 0..6 (Sun..Sat)
  $weekdays = isset($sched['weekdays']) && is_array($sched['weekdays']) ? array_map('intval', $sched['weekdays']) : [];
  if (!in_array($wd, $weekdays, true)) { set_transient($ckey, [], 30); return []; }

  $capacity = (int)($sched['capacity'] ?? 0);
  $price_usd = (int)($sched['price_usd'] ?? 0);
  $language = (string)($sched['language'] ?? 'EN');
  $cutoff_h = (int)($sched['cutoff_hours'] ?? 0);
  $slots = (array)($sched['time_slots'] ?? []);
  $now_kst = new DateTimeImmutable('now', $kst);

  $out = [];
  foreach ($slots as $t) {
    if (!preg_match('/^\d{2}:\d{2}$/', (string)$t)) continue;
    [$hh,$mm] = explode(':', $t, 2);
    $slot = $day->setTime((int)$hh, (int)$mm, 0);
    $slot_iso_kst = kgh_avail_iso_kst($slot);

    // Start with schedule values
    $eff_capacity = $capacity;
    $eff_price = $price_usd;
    $eff_lang = $language;
    $reason = null;

    // Cutoff first
    $cutoff_time = $now_kst->add(new DateInterval('PT'.max(0,$cutoff_h).'H'));
    $cutoff_hit = ($slot < $cutoff_time);

    // Exceptions (collect first, then apply priority rules)
    $external_sum = 0;
    $has_closed = false;
    $ov_cap = null; $ov_price = null; $ov_lang = null;
    $ex = function_exists('kgh_ex_for_slot') ? kgh_ex_for_slot($tour_id, $slot_iso_kst) : [];
    foreach ($ex as $row) {
      $t = $row['type'] ?? '';
      if ($t === 'closed') {
        $has_closed = true;
      } elseif ($t === 'override') {
        if (array_key_exists('cap_override', $row) && $row['cap_override'] !== null) $ov_cap = (int)$row['cap_override'];
        if (array_key_exists('price_usd_override', $row) && $row['price_usd_override'] !== null) $ov_price = (int)$row['price_usd_override'];
        if (!empty($row['lang_override'])) $ov_lang = (string)$row['lang_override'];
      } elseif ($t === 'external_booked') {
        $external_sum += max(0, (int)($row['external_qty'] ?? 0));
      }
    }

    // Priority: cutoff > closed > overrides/external
    if ($cutoff_hit) {
      $out[] = [
        'slot_start_iso' => $slot_iso_kst,
        'price_usd'      => $eff_price,
        'language'       => $eff_lang,
        'left'           => 0,
        'sold_out'       => true,
        'reason'         => 'cutoff',
      ];
      continue;
    }

    if ($has_closed) {
      $out[] = [
        'slot_start_iso' => $slot_iso_kst,
        'price_usd'      => $eff_price,
        'language'       => $eff_lang,
        'left'           => 0,
        'sold_out'       => true,
        'reason'         => 'closed',
      ];
      continue;
    }

    // Apply overrides if any
    if ($ov_cap !== null)   $eff_capacity = $ov_cap;
    if ($ov_price !== null) $eff_price = $ov_price;
    if ($ov_lang !== null)  $eff_lang = $ov_lang;

    // Site bookings + holds (only when not cutoff/closed)
    $site = kgh_sum_booked_qty($tour_id, $slot_iso_kst);
    $holds = kgh_active_holds_qty($tour_id, $slot_iso_kst);
    $left = max(0, $eff_capacity - ($site + $external_sum + $holds));
    $sold_out = ($left <= 0);

    $out[] = [
      'slot_start_iso' => $slot_iso_kst,
      'price_usd'      => $eff_price,
      'language'       => $eff_lang,
      'left'           => (int)$left,
      'sold_out'       => (bool)$sold_out,
      'reason'         => $sold_out ? null : null,
    ];
  }

  // sort by time
  usort($out, function($a,$b){ return strcmp($a['slot_start_iso'], $b['slot_start_iso']); });
  set_transient($ckey, $out, 60);
  return $out;
}

// ----- Days with any available slot -----
function kgh_avail_days(int $tour_id, int $days = 90): array {
  $days = max(1, min(90, $days));
  $kst = kgh_kst_tz();
  $today = new DateTimeImmutable('today', $kst);
  $out = [];
  for ($i=0; $i<$days; $i++) {
    $d = $today->add(new DateInterval('P'.$i.'D'));
    $ymd = $d->format('Y-m-d');
    $slots = kgh_avail_day_slots($tour_id, $ymd);
    foreach ($slots as $s) {
      if (!$s['sold_out']) { $out[] = $ymd; break; }
    }
  }
  return $out;
}

// ----- Quote for a specific slot -----
function kgh_avail_quote(int $tour_id, string $slot_start_iso_kst, int $qty) {
  $qty = max(1, (int)$qty);
  $kst = kgh_kst_tz();
  try { $dt = new DateTimeImmutable($slot_start_iso_kst, $kst); }
  catch (Exception $e) { return new WP_Error('INVALID_SLOT', __('Invalid slot datetime', 'kgh-booking'), ['status'=>422]); }
  $norm_iso = kgh_avail_iso_kst($dt); // normalized
  $ymd = $dt->format('Y-m-d');

  $slots = kgh_avail_day_slots($tour_id, $ymd);
  if (empty($slots)) return new WP_Error('NO_SCHEDULE', __('No schedule for this tour/day', 'kgh-booking'), ['status'=>422]);
  $found = null;
  foreach ($slots as $s) { if ($s['slot_start_iso'] === $norm_iso) { $found = $s; break; } }
  if (!$found) return new WP_Error('INVALID_SLOT', __('Slot does not match schedule', 'kgh-booking'), ['status'=>422]);

  if (!empty($found['reason']) && $found['reason']==='cutoff') return new WP_Error('CUTOFF', __('Cutoff reached for this slot', 'kgh-booking'), ['status'=>422]);
  if ($found['sold_out'] || (int)$found['left'] <= 0) return new WP_Error('SOLD_OUT', __('Slot is sold out', 'kgh-booking'), ['status'=>422]);
  if ((int)$found['left'] < $qty) return new WP_Error('NOT_ENOUGH_LEFT', __('Not enough seats left', 'kgh-booking'), ['status'=>422]);

  $unit = (int)$found['price_usd'];
  return [
    'ok'              => true,
    'unit_usd'        => $unit,
    'qty'             => $qty,
    'total_usd'       => $unit * $qty,
    'left_before'     => (int)$found['left'],
    'left_after_hold' => (int)$found['left'] - $qty,
  ];
}
