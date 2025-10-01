<?php
/**
 * Template: Single Tour
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $post;
$tour_id = get_the_ID();

get_header();

/** —————————————————— Feature flag réservation —————————————————— */
$booking_enabled = (bool) apply_filters('kgh_booking_enabled', false);

/** —————————————————— Strings UI (réservation) —————————————————— */
$ui_strings = [
  'loading'          => __( 'Loading…', 'kgh-booking' ),
  'selectDateFirst'  => __( 'Select a date first', 'kgh-booking' ),
  'noAvailability'   => __( 'No availability for this date', 'kgh-booking' ),
  'cutoff'           => __( 'Cutoff', 'kgh-booking' ),
  'soldOut'          => __( 'Sold out', 'kgh-booking' ),
  'onlyLeft'         => __( '— only %s left', 'kgh-booking' ),
  'unavailable'      => __( 'This time is no longer available. Please choose another.', 'kgh-booking' ),
  'networkError'     => __( 'Network error, please retry.', 'kgh-booking' ),
  'invalidParams'    => __( 'Missing or invalid parameters. Please go back to the tour page.', 'kgh-booking' ),
  'reserve'          => __( 'Reserve', 'kgh-booking' ),
];
?>

<?php
// archive URL
$archive_url = get_post_type_archive_link('tours') ?: home_url('/tours/');

// SCF metas (déjà utilisées ailleurs)
$tour_id   = get_the_ID();
$subtitle = function_exists('SCF')
  ? SCF::get('subtitle', $tour_id)
  : get_post_meta($tour_id, 'subtitle', true);
$duration  = trim((string) get_post_meta($tour_id, 'duration', true));
$capacity  = trim((string) get_post_meta($tour_id, 'capacity', true));
$languages = trim((string) get_post_meta($tour_id, 'languages', true));

// badges (taxonomie)
$badges = get_the_terms($tour_id, 'kgh_badge');
if (is_wp_error($badges)) $badges = [];

// ruban “Most Popular Choice” (bool SCF)
$is_popular = (bool) get_post_meta($tour_id, 'is_most_popular', true);

// helper durée (minutes => h/m)
if (!function_exists('kgh_fmt_duration')) {
  function kgh_fmt_duration($raw){
    if ($raw === '') return '';
    if (ctype_digit($raw)) { $m=(int)$raw; $h=floor($m/60); $r=$m%60; return ($h?"{$h}h":'').($r?" {$r}m":(!$h?"{$m}m":'')); }
    return $raw;
  }
}
?>

<main class="kgh-container py-10 md:py-16">
  <!-- Back to Our Tours -->
  <nav class="mb-5 md:mb-6">
    <a href="<?php echo esc_url($archive_url); ?>" class="inline-flex items-center gap-2 text-sm hover:opacity-80">
      <span aria-hidden="true">←</span><span>Back to Our Tours</span>
    </a>
  </nav>

  <!-- Image 16:9 -->
  <figure class="relative aspect-[16/9] overflow-hidden rounded-sm border-2 border-[#131313] shadow-[4px_2px_4px_rgba(0,0,0,0.25)] bg-white">
    <?php if (has_post_thumbnail()): ?>
      <?php the_post_thumbnail('large', ['class'=>'absolute inset-0 w-full h-full object-cover','loading'=>'eager','fetchpriority'=>'high']); ?>
    <?php else: ?>
      <div class="absolute inset-0 grid place-items-center text-gray-500">No image yet</div>
    <?php endif; ?>
  </figure>

  <!-- Titre + ruban -->
  <header class="mt-8 md:mt-10 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
    <h1 class="kgh-h1"><?php the_title(); ?></h1>
    <?php if ($is_popular): ?>
      <div class="inline-block px-3 py-1 text-sm font-semibold rounded-sm border-2 border-dashed border-[#131313] bg-white">
        Most Popular Choice
      </div>
    <?php endif; ?>
  </header>

  <?php
  // --- SCF same as home ---
  $area = function_exists('SCF')
    ? SCF::get('area_label', $tour_id)
    : get_post_meta($tour_id, 'area_label', true);

  /** Always get badges as an array (same normalisation as home) */
  if (function_exists('SCF')) {
    $badge_raw = (array) SCF::get('badge_tags', $tour_id);
  } else {
    // IMPORTANT: third arg = false => array of all values
    $badge_raw = (array) get_post_meta($tour_id, 'badge_tags', false);
  }
  $badges = [];
  if (!empty($badge_raw)) {
    foreach ($badge_raw as $k => $v) {
      if (is_int($k)) {                // ['spicy','traditional']
        $slug  = trim((string) $v);
        $label = ucwords(str_replace('-', ' ', $slug));
      } else {                         // ['spicy' => 'Spicy'] or ['spicy' => 1]
        $slug  = trim((string) $k);
        $label = (is_string($v) && $v !== '') ? $v : ucwords(str_replace('-', ' ', $slug));
      }
      if ($slug !== '') $badges[] = ['slug'=>$slug,'label'=>$label];
    }
  }
  // limit like home if needed
  $badges = array_slice($badges, 0, 3);
  ?>

  <!-- ROW: area + badges (exact same look as cards) -->
  <div class="mt-3 flex flex-wrap items-center gap-2">
    <?php if (!empty($area)) : ?>
      <span class="kgh-badge kgh-badge--light">
        <span class="kgh-badge-ico" aria-hidden="true"><?php echo kgh_icon('icon-map-pin'); ?></span>
        <span><?php echo esc_html($area); ?></span>
      </span>
    <?php endif; ?>

    <?php if (!empty($badges)) : ?>
      <?php foreach ($badges as $it): ?>
        <span class="kgh-badge kgh-badge--dark">
          <?php $ico = function_exists('kgh_badge_icon') ? kgh_badge_icon($it['slug']) : ''; ?>
          <?php if ($ico): ?>
            <span class="kgh-badge-ico" aria-hidden="true"><?php echo $ico; ?></span>
          <?php endif; ?>
          <span><?php echo esc_html($it['label']); ?></span>
        </span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Subtitle (SCF) -->
  <?php if (!empty($subtitle)): ?>
    <p class="mt-6 kgh-subtle">
      <?php echo esc_html($subtitle); ?>
    </p>
  <?php endif; ?>

  <!-- Méta (icônes via kgh_icon(), pas d’emoji) -->
  <section class="mt-6">
    <div class="flex flex-wrap gap-2">
      <?php if (!empty($duration)): ?>
        <span class="kgh-meta-item" title="<?php echo esc_attr($duration); ?>">
          <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-clock'); ?></span>
          <span><?php echo esc_html(kgh_fmt_duration($duration)); ?></span>
        </span>
      <?php endif; ?>

      <?php if (!empty($capacity)): ?>
        <span class="kgh-meta-item" title="<?php echo esc_attr($capacity); ?>">
          <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-users'); ?></span>
          <span><?php echo esc_html__('Max', 'kgh'); ?> <?php echo esc_html($capacity); ?></span>
        </span>
      <?php endif; ?>

      <?php if (!empty($languages)): ?>
        <span class="kgh-meta-item" title="<?php echo esc_attr($languages); ?>">
          <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-globe'); ?></span>
          <span class="truncate max-w-[9rem] sm:max-w-[12rem]"><?php echo esc_html($languages); ?></span>
        </span>
      <?php endif; ?>
    </div>
  </section>

  <!-- CTA: Contact us to book -->
  <div class="flex flex-col mt-6 max-w-[400px]">
    <div class="flex flex-row items-center">
      <span class="mr-4">to book this tour :</span>
      <a href="#kgh-contact" class="kgh-btn--primary text-center">
        Contact us
      </a>
    </div>
  </div>

  <!-- Advantages / Guarantees -->
  <section class="mt-8 md:mt-10">
    <div class="rounded-lg bg-white px-6 py-6 md:py-8">
      <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-8 items-center text-center">
        <!-- 1 -->
        <li class="flex flex-col items-center gap-2">
          <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
            <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
          </span>
          <span class="text-sm md:text-base">Free 24h Cancellation</span>
        </li>
        <!-- 2 -->
        <li class="flex flex-col items-center gap-2">
          <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
            <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
          </span>
          <span class="text-sm md:text-base">All diets available</span>
        </li>
        <!-- 3 -->
        <li class="flex flex-col items-center gap-2">
          <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
            <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
          </span>
          <span class="text-sm md:text-base">All tasting included</span>
        </li>
        <!-- 4 -->
        <li class="flex flex-col items-center gap-2">
          <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
            <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
          </span>
          <span class="text-sm md:text-base">No tourist traps</span>
        </li>
      </ul>
    </div>
  </section>

  <!-- Meeting / Ending Points -->
  <section class="mt-8 md:mt-12">
    <div class="rounded-lg bg-white p-6 md:p-8">

      <?php
      // SCF: read once with fallback to meta
      $scf_get = function($key) use ($tour_id) {
        if (function_exists('SCF')) return SCF::get($key, $tour_id);
        return get_post_meta($tour_id, $key, true);
      };

      $mt_title   = trim((string) $scf_get('meeting_point_title'));
      $mt_details = trim((string) $scf_get('meeting_point_details'));
      $mt_gmaps   = trim((string) $scf_get('exact_location_link_google_maps'));
      $mt_naver   = trim((string) $scf_get('exact_location_link_naver_maps'));
      $mt_kakao   = trim((string) $scf_get('exact_location_link_kakao_map'));

      $end_title   = trim((string) $scf_get('ending_point_title'));
      $end_details = trim((string) $scf_get('ending_point_details'));

      // util bouton ghost noir
      $btn_base = 'kgh-btn--ghost border-[#131313] text-black hover:no-underline';
      ?>

      

      <?php if ($mt_title || $mt_details): ?>
        <section class="mb-10 md:mb-12">
          <h3 class="text-lg font-semibold text-black mb-4">Meeting Point</h3>
          <div class="pl-4 border-l-2 border-black/90">
            <?php if ($mt_title): ?>
              <p class="font-semibold mb-1"><?php echo esc_html($mt_title); ?></p>
            <?php endif; ?>
            <?php if ($mt_details): ?>
              <p class="text-gray-800"><?php echo esc_html($mt_details); ?></p>
            <?php endif; ?>
          </div>

          <?php if ($mt_gmaps || $mt_naver || $mt_kakao): ?>
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
              <?php if ($mt_gmaps): ?>
                <a class="<?php echo esc_attr($btn_base); ?>" href="<?php echo esc_url($mt_gmaps); ?>" target="_blank" rel="noopener">
                  Open in Google Maps
                </a>
              <?php endif; ?>
              <?php if ($mt_naver): ?>
                <a class="<?php echo esc_attr($btn_base); ?>" href="<?php echo esc_url($mt_naver); ?>" target="_blank" rel="noopener">
                  Open in Naver Maps
                </a>
              <?php endif; ?>
              <?php if ($mt_kakao): ?>
                <a class="<?php echo esc_attr($btn_base); ?>" href="<?php echo esc_url($mt_kakao); ?>" target="_blank" rel="noopener">
                  Open in Kakao Maps
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($end_title || $end_details): ?>
        <section>
          <h3 class="text-lg font-semibold text-black mb-4">Ending Point</h3>
          <div class="pl-4 border-l-2 border-black/90">
            <?php if ($end_title): ?>
              <p class="font-semibold mb-1"><?php echo esc_html($end_title); ?></p>
            <?php endif; ?>
            <?php if ($end_details): ?>
              <p class="text-gray-800"><?php echo esc_html($end_details); ?></p>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

    </div>
  </section>

  <!-- Your Guide(s) -->
  <?php
  // 1) Récupère la relation SCF: tour_guides (IDs / objets / arrays)
  $raw_guides = function_exists('SCF') ? SCF::get('tour_guides', $tour_id) : get_post_meta($tour_id, 'tour_guides', false);
  $guide_ids  = [];

  // Normalisation -> [int,int,...]
  $push_id = function($v) use (&$guide_ids) {
    if ($v === null) return;
    if (is_numeric($v))                    { $guide_ids[] = (int)$v; return; }
    if ($v instanceof WP_Post)             { $guide_ids[] = (int)$v->ID; return; }
    if (is_array($v)) {
      if (isset($v['id']))                 { $guide_ids[] = (int)$v['id']; return; }
      if (isset($v['ID']))                 { $guide_ids[] = (int)$v['ID']; return; }
      if (isset($v['post_id']))            { $guide_ids[] = (int)$v['post_id']; return; }
      if (isset($v['object_id']))          { $guide_ids[] = (int)$v['object_id']; return; }
      if (isset($v['post']) && $v['post'] instanceof WP_Post) { $guide_ids[] = (int)$v['post']->ID; return; }
      if (isset($v[0])) {
        if (is_array($v[0]) && isset($v[0]['id'])) { $guide_ids[] = (int)$v[0]['id']; return; }
        if (is_numeric($v[0]))                     { $guide_ids[] = (int)$v[0]; return; }
      }
    }
  };
  if (is_array($raw_guides)) { foreach ($raw_guides as $g) { $push_id($g); } } else { $push_id($raw_guides); }

  // uniques + publiés
  $guide_ids = array_values(array_unique(array_filter($guide_ids, function($id){
    return $id && get_post_status($id) === 'publish';
  })));

  // util: resolve image
  $kgh_resolve_img = function($raw, $size = 'thumbnail'){
    if (is_numeric($raw))                    return wp_get_attachment_image_url((int)$raw, $size) ?: '';
    if ($raw instanceof WP_Post)             return wp_get_attachment_image_url((int)$raw->ID, $size) ?: '';
    if (is_array($raw)) {
      $pid = isset($raw['id']) ? (int)$raw['id'] : (isset($raw[0]) && is_numeric($raw[0]) ? (int)$raw[0] : 0);
      if ($pid) return wp_get_attachment_image_url($pid, $size) ?: '';
      if (!empty($raw['url'])) return (string)$raw['url'];
    }
    if (is_string($raw) && preg_match('~^https?://~', $raw)) return $raw;
    return '';
  };

  if (!empty($guide_ids)):
    $title_guides = count($guide_ids) > 1 ? 'Your Guides' : 'Your Guide';
  ?>
  <section class="mt-8 md:mt-12">
    <div class="rounded-lg bg-white p-6 md:p-8">
      <h3 class="text-lg font-semibold text-black mb-6"><?php echo esc_html($title_guides); ?></h3>

      <div class="grid grid-cols-1 gap-8">
        <?php foreach ($guide_ids as $gid): ?>
          <?php
            $g_title   = get_the_title($gid);
            $g_sub     = function_exists('SCF') ? SCF::get('subtitle',        $gid) : get_post_meta($gid, 'subtitle', true);
            $g_desc    = function_exists('SCF') ? SCF::get('description',     $gid) : get_post_meta($gid, 'description', true);
            $g_lang    = function_exists('SCF') ? SCF::get('languages',       $gid) : get_post_meta($gid, 'languages', true); // 1 chip
            $g_pic_raw = function_exists('SCF') ? SCF::get('profil_picture',  $gid) : get_post_meta($gid, 'profil_picture', true);
            $g_pic_url = $kgh_resolve_img($g_pic_raw, 'thumbnail');

            // Labels (repeat "Label") => array de strings
            $g_labels_raw = function_exists('SCF') ? SCF::get('label', $gid) : get_post_meta($gid, 'label', false);
            $labels = [];
            if (is_array($g_labels_raw)) {
              foreach ($g_labels_raw as $row) {
                $val = is_array($row) && isset($row['label']) ? trim((string)$row['label']) : trim((string)$row);
                if ($val !== '') $labels[] = $val;
              }
            } elseif (is_string($g_labels_raw) && trim($g_labels_raw) !== '') {
              $labels[] = trim($g_labels_raw);
            }
            $labels = array_values(array_unique($labels));
          ?>

          <!-- Carte guide -->
          <article class="grid grid-cols-[auto_1fr] gap-4">
            <!-- avatar -->
            <div class="w-11 h-11 rounded-full bg-[#F2EDEA] grid place-items-center overflow-hidden">
              <?php if ($g_pic_url): ?>
                <img src="<?php echo esc_url($g_pic_url); ?>" alt="<?php echo esc_attr($g_title); ?>" class="w-full h-full object-cover">
              <?php else: ?>
                <span class="kgh-ico w-11 h-11 text-kgh-red" aria-hidden="true">
                  <?php echo function_exists('kgh_icon') ? kgh_icon('icon-user') : ''; ?>
                </span>
              <?php endif; ?>
            </div>

            <!-- infos -->
            <div class="min-w-0">
              <div class="font-semibold text-black"><?php echo esc_html($g_title); ?></div>
              <?php if (!empty($g_sub)): ?>
                <div class="text-xs text-gray-700 mb-3"><?php echo esc_html($g_sub); ?></div>
              <?php endif; ?>

              <div class="flex flex-wrap gap-2 mb-4">
                <?php if (!empty($g_lang)): ?>
                  <span class="kgh-badge !bg-gray-100"><?php echo esc_html('Languages: ' . $g_lang); ?></span>
                <?php endif; ?>
                <?php foreach ($labels as $lab): ?>
                  <span class="kgh-badge kgh-badge--light !bg-gray-100"><?php echo esc_html($lab); ?></span>
                <?php endforeach; ?>
              </div>

              <?php if (!empty($g_desc)): ?>
                <div class="kgh-subtle text-[15px] md:text-base leading-relaxed">
                  <?php echo esc_html($g_desc); ?>
                </div>
              <?php endif; ?>
            </div>
          </article>

        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Tour Details (static for now) -->
  <section class="mt-8 md:mt-12">
    <div class="rounded-lg bg-white p-6 md:p-8">
      <h3 class="text-lg font-semibold text-black mb-6">Tour Details</h3>

      <!-- 2 columns: included / not included -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-10 mb-8 mx-3">
        <!-- What's included -->
        <div>
          <h4 class="font-medium text-black mb-3">What’s included</h4>
          <ul class="space-y-3">
            <?php
              // simple helper to print a checked line with your green icon
              $kgh_check = function($text){
                echo '<li class="flex items-start gap-2">'
                  .   '<span class="inline-block w-5 text-center text-gray-500">✓</span>'
                  .   '<span class="text-black">'. esc_html($text) .'</span>'
                  . '</li>';
              };
              $kgh_check('Transportation fees');
              $kgh_check('Expert Guide');
              $kgh_check('Cultural Insights');
              $kgh_check('Market navigation');
            ?>
          </ul>
        </div>

        <!-- What's not included -->
        <div>
          <h4 class="font-medium text-black mb-3">What’s not included</h4>
          <ul class="space-y-3">
            <?php
              // cross lines (typographic ×)
              $kgh_cross = function($text){
                echo '<li class="flex items-start gap-2">'
                  .   '<span class="inline-block w-5 text-center text-gray-500 translate-y-[1px]">&times;</span>'
                  .   '<span class="text-black font-normal">'. esc_html($text) .'</span>'
                  . '</li>';
              };
              $kgh_cross('Hotel pickup');
              $kgh_cross('Additional drinks');
              $kgh_cross('Souvenirs');
            ?>
          </ul>
        </div>
      </div>

      <!-- 3 guarantee blocks -->
      <div class="rounded-2xl border border-gray-300 bg-white px-5 py-6 md:px-8 md:py-7">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-y-6 md:gap-y-0 md:divide-x md:divide-gray-200">
          <!-- Hygiene -->
          <div class="flex flex-col items-center text-center px-2">
            <span class="kgh-ico w-7 h-7 text-[#3B7D3B] mb-2" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <div class="font-semibold text-black">Hygienes Standards</div>
            <div class="text-sm text-gray-700">All venues inspected</div>
          </div>

          <!-- Photo service -->
          <div class="flex flex-col items-center text-center px-2">
            <span class="kgh-ico w-7 h-7 text-[#3B7D3B] mb-2" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <div class="font-semibold text-black">Photo Service</div>
            <div class="text-sm text-gray-700">Ask your guide to take pictures</div>
          </div>

          <!-- All tastes included -->
          <div class="flex flex-col items-center text-center px-2">
            <span class="kgh-ico w-7 h-7 text-[#3B7D3B] mb-2" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <div class="font-semibold text-black">All tastes included</div>
            <div class="text-sm text-gray-700">Choose what you want to eat</div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Contact section -->
  <section id="kgh-contact" class="mt-12 md:mt-16 scroll-mt-24">
    <?php
      // On passe un titre + une liste simple de services dont le nom du tour pour aider le staff
      get_template_part('template-parts/section', 'contact', [
        'title'    => 'Contact us to book this tour',
        'services' => [
          'Private tour inquiry',
          'Group booking',
          'Question about: ' . get_the_title($tour_id),
        ],
        // 'portrait_id' => 0, // (optionnel) force un portrait spécifique si besoin
      ]);
    ?>
  </section>

</main>
























<?php if ($booking_enabled): ?>
<!-- ————— Script réservation (TON CODE D’ORIGINE, uniquement si booking ON) -->
<script>
(function(){
  const messages = <?php echo wp_json_encode( $ui_strings ); ?>;
  const tourId = <?php echo (int) $tour_id; ?>;
  const qs = new URLSearchParams(window.location.search);
  const preDate = qs.get('kgh_date') || qs.get('date') || '';
  const preTime = qs.get('kgh_time') || qs.get('time') || '';
  const preQty  = Math.max(1, parseInt(qs.get('kgh_qty') || qs.get('qty') || '1', 10));

  const elDate   = document.getElementById('kgh-date');
  const elTime   = document.getElementById('kgh-time');
  const elGuests = document.getElementById('kgh-guests');
  const elCTA    = document.getElementById('kgh-cta');
  const elErr    = document.getElementById('kgh-booking-error');
  const elNo     = document.getElementById('kgh-no-slots');

  let slots = [];
  let selectedSlot = null;

  function setDisabled(el, disabled) {
    if (!el) return;
    el.disabled = !!disabled;
    el.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function showError(msg) {
    if (!msg) {
      elErr.style.display = 'none';
      elErr.textContent = '';
      return;
    }
    elErr.textContent = msg;
    elErr.style.display = 'block';
  }

  function isoToKstHm(iso) {
    const match = iso.match(/T(\d{2}):(\d{2}):\d{2}\+09:00$/);
    if (!match) return iso;
    return `${match[1]}:${match[2]}`;
  }

  function fmt12hm(hhmm) {
    const [hStr, mStr] = hhmm.split(':');
    let h = parseInt(hStr, 10);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = ((h + 11) % 12) + 1;
    return `${h}:${mStr} ${ampm}`;
  }

  function labelForSlot(slot, fmtTime) {
    if (!slot) return fmtTime;
    let label = fmtTime;
    if (slot.reason === 'cutoff') label += ` (${messages.cutoff})`;
    else if (slot.sold_out) label += ` (${messages.soldOut})`;
    else if (Number(slot.left) <= 4) label += ' ' + messages.onlyLeft.replace('%s', slot.left);
    return label;
  }

  function isSlotDisabled(slot) {
    return !!(slot && (slot.sold_out || slot.reason === 'cutoff' || slot.reason === 'closed'));
  }

  async function loadDays(){
    try {
      const res = await fetch(`/wp-json/kgh/v1/availability?tour=${tourId}&days=90`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('http');
      const data = await res.json();
      const apiDates = Array.isArray(data.dates) ? data.dates : [];
      let dateOpts = apiDates.map(d => ({ value: d, label: d, disabled: false }));

      dateOpts = await maybeInjectDate(todayKST(), dateOpts);
      dateOpts = await maybeInjectDate(preDate, dateOpts);

      if (!dateOpts.length) {
        elDate.innerHTML = `<option value="">${messages.noAvailability}</option>`;
        setDisabled(elDate, true);
        setDisabled(elTime, true);
        setDisabled(elGuests, true);
        elNo.style.display = 'block';
        return;
      }

      renderDateOptions(dateOpts);
      let target = preDate && dateOpts.some(o => o.value === preDate) ? preDate : null;
      if (!target) {
        const firstOpen = dateOpts.find(o => !o.disabled);
        target = firstOpen ? firstOpen.value : dateOpts[0].value;
      }
      elDate.value = target;
      await loadSlotsForDate(target);
    } catch (e) {
      elDate.innerHTML = `<option value="">${messages.networkError}</option>`;
    }
  }

  function renderDateOptions(opts) {
    elDate.innerHTML = '';
    opts.sort((a,b) => a.value.localeCompare(b.value));
    opts.forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.label;
      if (o.disabled) {
        opt.disabled = true;
        opt.setAttribute('aria-disabled', 'true');
      }
      elDate.appendChild(opt);
    });
  }

  async function maybeInjectDate(ymd, opts) {
    if (!ymd || opts.some(o => o.value === ymd)) return opts;
    try {
      const res = await fetch(`/wp-json/kgh/v1/availability/day?tour=${tourId}&date=${encodeURIComponent(ymd)}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) return opts;
      const data = await res.json();
      const slotsArr = Array.isArray(data.slots) ? data.slots : [];
      const hasCutoff = slotsArr.length > 0 && slotsArr.every(s => s.sold_out) && slotsArr.some(s => s.reason === 'cutoff');
      if (hasCutoff) {
        opts.push({ value: ymd, label: `${ymd} (${messages.cutoff})`, disabled: true });
      }
    } catch (e) {
      // ignore
    }
    return opts;
  }

  async function loadSlotsForDate(ymd) {
    showError('');
    selectedSlot = null;
    elNo.style.display = 'none';
    setDisabled(elTime, true);
    setDisabled(elGuests, true);
    setDisabled(elCTA, true);
    elTime.innerHTML = `<option value="">${messages.loading}</option>`;
    try {
      const res = await fetch(`/wp-json/kgh/v1/availability/day?tour=${tourId}&date=${encodeURIComponent(ymd)}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('http');
      const data = await res.json();
      slots = data.slots || [];
      elTime.innerHTML = '';
      if (!slots.length) {
        elTime.innerHTML = `<option value="">${messages.noAvailability}</option>`;
        elNo.style.display = 'block';
        return;
      }
      slots.forEach(slot => {
        const hm = isoToKstHm(slot.slot_start_iso);
        const label = labelForSlot(slot, fmt12hm(hm));
        const disabled = isSlotDisabled(slot);
        const opt = document.createElement('option');
        opt.value = slot.slot_start_iso;
        opt.textContent = label;
        opt.disabled = disabled;
        opt.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        elTime.appendChild(opt);
      });
      let chosen = slots.find(s => !isSlotDisabled(s));
      if (preTime) {
        const want = slots.find(s => !isSlotDisabled(s) && isoToKstHm(s.slot_start_iso) === preTime);
        if (want) chosen = want;
      }
      if (chosen) {
        elTime.value = chosen.slot_start_iso;
        onTimeChange();
      } else {
        elNo.style.display = 'block';
        elGuests.innerHTML = '<option value="1">1</option>';
      }
    } catch (e) {
      elTime.innerHTML = `<option value="">${messages.networkError}</option>`;
    } finally {
      setDisabled(elTime, false);
    }
  }

  function onTimeChange() {
    showError('');
    const iso = elTime.value;
    selectedSlot = slots.find(s => s.slot_start_iso === iso) || null;
    if (!selectedSlot || isSlotDisabled(selectedSlot)) {
      setDisabled(elGuests, true);
      setDisabled(elCTA, true);
      elGuests.innerHTML = '<option value="1">1</option>';
      return;
    }
    const max = Math.min(50, Math.max(1, parseInt(selectedSlot.left, 10)));
    elGuests.innerHTML = '';
    for (let i = 1; i <= max; i++) {
      const opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = String(i);
      elGuests.appendChild(opt);
    }
    let want = preQty > 0 ? preQty : 1;
    if (want > max) want = max;
    if (want < 1) want = 1;
    elGuests.value = String(want);
    setDisabled(elGuests, false);
    setDisabled(elCTA, false);
  }

  function todayKST() {
    const now = new Date();
    const utc = now.getTime() + now.getTimezoneOffset() * 60000;
    const kst = new Date(utc + 9 * 3600000);
    const y = kst.getUTCFullYear();
    const m = String(kst.getUTCMonth() + 1).padStart(2, '0');
    const d = String(kst.getUTCDate(), 10).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  elDate.addEventListener('change', () => {
    if (elDate.value) { loadSlotsForDate(elDate.value); }
  });
  elTime.addEventListener('change', onTimeChange);
  elCTA.addEventListener('click', async () => {
    if (!selectedSlot) return;
    const hm = isoToKstHm(selectedSlot.slot_start_iso);
    const qty = parseInt(elGuests.value || '1', 10);
    try {
      const resp = await fetch('/wp-json/kgh/v1/quote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ tour_id: tourId, slot_start_iso: selectedSlot.slot_start_iso, qty })
      });
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({ message: messages.unavailable }));
        if (resp.status === 422) {
          showError(err.message || messages.unavailable);
          await loadSlotsForDate(selectedSlot.slot_start_iso.substring(0,10));
          return;
        }
      }
    } catch (e) {
      showError(messages.networkError);
      return;
    }
    const url = new URL('/checkout/', window.location.origin);
    url.searchParams.set('kgh_tour', String(tourId));
    url.searchParams.set('kgh_date', selectedSlot.slot_start_iso.substring(0, 10));
    url.searchParams.set('kgh_time', hm);
    url.searchParams.set('kgh_qty', String(qty));
    window.location.href = url.toString();
  });

  loadDays();
})();
</script>
<?php endif; ?>

<?php get_footer(); ?>
