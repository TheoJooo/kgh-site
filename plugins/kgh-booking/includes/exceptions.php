<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

function kgh_ex_table_name(): string {
  global $wpdb;
  return $wpdb->prefix . 'kgh_slot_exceptions';
}

function kgh_ex_install_table() {
  global $wpdb;
  $table = kgh_ex_table_name();
  $charset_collate = $wpdb->get_charset_collate();
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $sql = "CREATE TABLE {$table} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    tour_id BIGINT(20) UNSIGNED NOT NULL,
    slot_start_utc DATETIME NOT NULL,
    type ENUM('closed','override','external_booked') NOT NULL,
    cap_override INT NULL,
    price_usd_override INT NULL,
    lang_override VARCHAR(8) NULL,
    external_qty INT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    KEY tour_slot (tour_id, slot_start_utc),
    KEY type_idx (type)
  ) {$charset_collate};";
  dbDelta($sql);
}

// ---- Time helpers ----
function kgh_ex_dt_from_iso(string $iso): ?DateTimeImmutable {
  $iso = trim($iso);
  if ($iso === '') return null;
  try {
    // DateTimeImmutable parses offsets like Z, +09:00 etc.
    return new DateTimeImmutable($iso);
  } catch (Exception $e) {
    return null;
  }
}

function kgh_ex_utc_string(DateTimeImmutable $dt): string {
  return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function kgh_ex_align_check(DateTimeImmutable $dt): bool {
  $m = (int)$dt->format('i');
  $s = (int)$dt->format('s');
  return ($s === 0) && ($m === 0 || $m === 30);
}

function kgh_ex_in_window_kst(DateTimeImmutable $dt): bool {
  // Check window [now-12h, now+90d] in KST
  $kst = new DateTimeZone('Asia/Seoul');
  $slot_kst = $dt->setTimezone($kst);
  $now_kst = new DateTimeImmutable('now', $kst);
  $min = $now_kst->sub(new DateInterval('PT12H'));
  $max = $now_kst->add(new DateInterval('P90D'));
  return ($slot_kst >= $min && $slot_kst <= $max);
}

// ---- CRUD helpers ----
/**
 * Add or update an exception row.
 * @param array $data
 * @return array|WP_Error normalized row (with id) or error
 */
function kgh_ex_add(array $data) {
  global $wpdb;
  $table = kgh_ex_table_name();

  $tour_id = isset($data['tour_id']) ? (int)$data['tour_id'] : 0;
  if ($tour_id <= 0) return new WP_Error('ex_tour', __('Missing or invalid tour_id', 'kgh-booking'));

  $type = isset($data['type']) ? trim((string)$data['type']) : '';
  $allowed_types = ['closed','override','external_booked'];
  if (!in_array($type, $allowed_types, true)) return new WP_Error('ex_type', __('Invalid type', 'kgh-booking'));

  $slot_iso = (string)($data['slot_start_utc_iso'] ?? '');
  $dt = kgh_ex_dt_from_iso($slot_iso);
  if (!$dt) return new WP_Error('ex_time', __('Invalid slot_start ISO', 'kgh-booking'));
  if (!kgh_ex_align_check($dt)) return new WP_Error('ex_time_align', __('Slot must align to :00 or :30', 'kgh-booking'));
  if (!kgh_ex_in_window_kst($dt)) return new WP_Error('ex_time_window', __('Slot must be within allowed window', 'kgh-booking'));
  $slot_utc = kgh_ex_utc_string($dt);

  $cap_override = isset($data['cap_override']) ? (int)$data['cap_override'] : null;
  $price_override = isset($data['price_usd_override']) ? (int)$data['price_usd_override'] : null;
  $lang_override = isset($data['lang_override']) ? strtoupper(preg_replace('/[^A-Za-z]/','', (string)$data['lang_override'])) : null;
  $external_qty = isset($data['external_qty']) ? (int)$data['external_qty'] : null;
  $notes = isset($data['notes']) ? substr(sanitize_text_field((string)$data['notes']), 0, 255) : null;

  // Type-specific rules
  if ($type === 'closed') {
    $cap_override = 0; $price_override = null; $lang_override = null; $external_qty = null;
  } elseif ($type === 'override') {
    $has_any = ($cap_override !== null) || ($price_override !== null) || ($lang_override !== null);
    if (!$has_any) return new WP_Error('ex_override_empty', __('Override requires at least one field', 'kgh-booking'));
    if ($cap_override !== null && ($cap_override < 0 || $cap_override > 50)) return new WP_Error('ex_cap', __('Capacity override must be between 0 and 50', 'kgh-booking'));
    if ($price_override !== null && $price_override < 0) return new WP_Error('ex_price', __('Price override must be >= 0', 'kgh-booking'));
    if ($lang_override !== null && !in_array($lang_override, ['EN','FR','KO'], true)) return new WP_Error('ex_lang', __('Language must be EN, FR or KO', 'kgh-booking'));
    $external_qty = null; // not applicable
  } elseif ($type === 'external_booked') {
    if ($external_qty === null || $external_qty < 1 || $external_qty > 50) return new WP_Error('ex_external', __('External qty must be between 1 and 50', 'kgh-booking'));
    $cap_override = null; $price_override = null; $lang_override = null;
  }

  // Upsert: find existing
  $existing_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$table} WHERE tour_id=%d AND slot_start_utc=%s AND type=%s LIMIT 1",
    $tour_id, $slot_utc, $type
  ));

  $now_utc = gmdate('Y-m-d H:i:s');
  $row = [
    'tour_id'            => $tour_id,
    'slot_start_utc'     => $slot_utc,
    'type'               => $type,
    'cap_override'       => $cap_override,
    'price_usd_override' => $price_override,
    'lang_override'      => $lang_override,
    'external_qty'       => $external_qty,
    'notes'              => $notes,
    'updated_at'         => $now_utc,
  ];

  $formats = ['%d','%s','%s','%d','%d','%s','%d','%s','%s'];
  // Convert nulls to NULL (wpdb->update/insert handles via format = null)
  $fmt = [
    'tour_id' => '%d', 'slot_start_utc'=>'%s','type'=>'%s',
    'cap_override'=> ($cap_override===null? null : '%d'),
    'price_usd_override'=> ($price_override===null? null : '%d'),
    'lang_override'=> ($lang_override===null? null : '%s'),
    'external_qty'=> ($external_qty===null? null : '%d'),
    'notes'=> ($notes===null? null : '%s'),
    'updated_at'=>'%s',
  ];

  if ($existing_id) {
    $ok = $wpdb->update($table, $row, ['id' => (int)$existing_id], array_values($fmt), ['%d']);
    if ($ok === false) return new WP_Error('ex_db_update', __('DB update failed', 'kgh-booking'));
    $id = (int)$existing_id;
  } else {
    $row['created_at'] = $now_utc;
    $fmt['created_at'] = '%s';
    $ok = $wpdb->insert($table, $row, array_values($fmt));
    if ($ok === false) return new WP_Error('ex_db_insert', __('DB insert failed', 'kgh-booking'));
    $id = (int)$wpdb->insert_id;
  }

  // Return normalized row
  return [
    'id'                 => $id,
    'tour_id'            => $tour_id,
    'slot_start_utc'     => $slot_utc,
    'type'               => $type,
    'cap_override'       => $cap_override,
    'price_usd_override' => $price_override,
    'lang_override'      => $lang_override,
    'external_qty'       => $external_qty,
    'notes'              => $notes,
    'created_at'         => $existing_id ? null : $now_utc,
    'updated_at'         => $now_utc,
  ];
}

function kgh_ex_delete(int $id) {
  global $wpdb; $table = kgh_ex_table_name();
  if ($id <= 0) return new WP_Error('ex_id', __('Invalid id', 'kgh-booking'));
  $ok = $wpdb->delete($table, ['id'=>$id], ['%d']);
  if ($ok === false) return new WP_Error('ex_db_delete', __('DB delete failed', 'kgh-booking'));
  return (bool)$ok;
}

function kgh_ex_list_for_range(int $tour_id, string $from_iso_utc, string $to_iso_utc): array {
  global $wpdb; $table = kgh_ex_table_name();
  $from = kgh_ex_dt_from_iso($from_iso_utc); $to = kgh_ex_dt_from_iso($to_iso_utc);
  if (!$from || !$to) return [];
  $from_s = kgh_ex_utc_string($from); $to_s = kgh_ex_utc_string($to);
  if ($from_s >= $to_s) return [];
  $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE tour_id=%d AND slot_start_utc >= %s AND slot_start_utc < %s ORDER BY slot_start_utc ASC, FIELD(type,'closed','override','external_booked')", $tour_id, $from_s, $to_s);
  return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

function kgh_ex_for_slot(int $tour_id, string $slot_start_iso_kst): array {
  global $wpdb; $table = kgh_ex_table_name();
  $kst = kgh_ex_dt_from_iso($slot_start_iso_kst);
  if (!$kst) return [];
  // normalize to exact minute and UTC string
  $utc = kgh_ex_utc_string($kst);
  $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE tour_id=%d AND slot_start_utc=%s ORDER BY FIELD(type,'closed','override','external_booked')", $tour_id, $utc);
  return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

