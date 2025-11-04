<?php
/**
 * Template: Single Tour
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $post;
$tour_id = get_the_ID();

get_header();

/** —————————————————— Feature flag réservation —————————————————— */
$booking_enabled = (bool) apply_filters('kgh_booking_enabled', true);
// $booking_enabled = false; 

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
$archive_url = get_post_type_archive_link('tour') ?: home_url('/tours/');

// SCF metas (déjà utilisées ailleurs)
$tour_id   = get_the_ID();
$subtitle = function_exists('SCF')
  ? SCF::get('subtitle', $tour_id)
  : get_post_meta($tour_id, 'subtitle', true);
$duration  = trim((string) get_post_meta($tour_id, 'duration', true));
$capacity  = trim((string) get_post_meta($tour_id, 'capacity', true));
$languages = trim((string) get_post_meta($tour_id, 'languages', true));

// Determine if tour has a configured price in schedule (main or alternate)
$has_price = false;
$sched_raw = get_post_meta($tour_id, '_kgh_schedule', true);
$from_price_cents = 0;
if (is_string($sched_raw) && $sched_raw !== '') {
  $sched = json_decode($sched_raw, true);
  if (is_array($sched)) {
    $main_price = isset($sched['price_usd']) ? (int)$sched['price_usd'] : 0;
    $alt_days   = isset($sched['alt_weekdays']) && is_array($sched['alt_weekdays']) ? $sched['alt_weekdays'] : [];
    $alt_price  = array_key_exists('alt_price_usd', $sched) ? $sched['alt_price_usd'] : null; // may be null
    $has_price = ($main_price > 0) || (!empty($alt_days) && $alt_price !== null && (int)$alt_price > 0);
    $cands = [];
    if ($main_price > 0) $cands[] = $main_price;
    if (!empty($alt_days) && $alt_price !== null && (int)$alt_price > 0) $cands[] = (int)$alt_price;
    if (!empty($cands)) $from_price_cents = min($cands);
  }
}

// If no price from schedule, enable booking UI when there are actual upcoming slots (one-off or scheduled)
if (!$has_price && function_exists('kgh_avail_days')) {
  $upcoming = kgh_avail_days($tour_id, 120);
  if (!empty($upcoming)) {
    $has_price = true;
  }
}

// badges (taxonomie)
$badges = get_the_terms($tour_id, 'kgh_badge');
if (is_wp_error($badges)) $badges = [];

// helper durée (minutes => h/m)
if (!function_exists('kgh_fmt_duration')) {
  function kgh_fmt_duration($raw){
    if ($raw === '') return '';
    if (ctype_digit($raw)) { $m=(int)$raw; $h=floor($m/60); $r=$m%60; return ($h?"{$h}h":'').($r?" {$r}m":(!$h?"{$m}m":'')); }
    return $raw;
  }
}
?>

<main class="kgh-container px-6 py-5 md:pb-5 lg:px-10">
  <!-- Two-column layout wrapper (desktop) -->
  <div class="kgh-tour-layout">
    <div class="kgh-tour-main">
      <div id="kgh-booking-root"
      data-tour-id="<?php echo (int) $tour_id; ?>"
      hidden></div>
      <!-- Back to Our Tours -->
      <nav class="mb-5 md:mb-6">
        <a href="<?php echo esc_url($archive_url); ?>" class="inline-flex items-center gap-2 text-sm hover:opacity-80">
          <span aria-hidden="true">←</span><span><?php echo esc_html__('Back to Our Tours', 'kgh-theme'); ?></span>
        </a>
      </nav>

  <!-- Image 16:9 -->
  <figure id="kgh-hero-figure" class="relative aspect-[16/9] overflow-hidden rounded-sm border-2 border-[#131313] bg-white">
    <?php if (has_post_thumbnail()): ?>
      <?php the_post_thumbnail('large', ['class'=>'absolute inset-0 w-full h-full object-cover','loading'=>'eager','fetchpriority'=>'high']); ?>
    <?php else: ?>
      <div class="absolute inset-0 grid place-items-center text-gray-500"><?php echo esc_html__('No image yet', 'kgh-theme'); ?></div>
    <?php endif; ?>
  </figure>

      <?php
      $tag = function_exists('SCF') ? SCF::get('tag', $tour_id) : get_post_meta($tour_id, 'tag', true);
      $tag = is_string($tag) ? trim($tag) : '';
      ?>
      <header class="mt-8 md:mt-10 flex flex-col md:flex-row items-start md:items-center md:justify-between gap-4">
        <h1 class="kgh-h1"><?php the_title(); ?></h1>
        <?php if ($tag !== ''): ?>
          <div class="kgh-stamp self-start md:self-auto"><?php echo esc_html($tag); ?></div>
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
          <span class="kgh-badge kgh-badge--light border border-kgh-grey">
            <span class="kgh-badge-ico" aria-hidden="true"><?php echo kgh_icon('icon-map-pin'); ?></span>
            <span><?php echo esc_html($area); ?></span>
          </span>
        <?php endif; ?>

        <?php if (!empty($badges)) : ?>
          <?php foreach ($badges as $it): ?>
            <span class="kgh-badge kgh-badge--dark !bg-kgh-grey border-2 border-kgh-grey ">
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
              <span><?php echo esc_html($capacity); ?></span>
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

      <?php
      // Optional gallery images (SCF repeatable image or meta list of IDs)
      $gallery_raw = function_exists('SCF') ? SCF::get('gallery_images', $tour_id) : get_post_meta($tour_id, 'gallery_images', false);
      $gallery_ids = [];
      if (is_array($gallery_raw)) {
        foreach ($gallery_raw as $g) {
          if (is_numeric($g)) { $gallery_ids[] = (int)$g; }
          elseif (is_array($g) && isset($g['id'])) { $gallery_ids[] = (int)$g['id']; }
          elseif (is_array($g) && isset($g[0]) && is_numeric($g[0])) { $gallery_ids[] = (int)$g[0]; }
        }
      } elseif (is_numeric($gallery_raw)) {
        $gallery_ids[] = (int)$gallery_raw;
      }
      $gallery_urls = [];
      foreach (array_values(array_unique($gallery_ids)) as $aid) {
        $url_large = wp_get_attachment_image_url($aid, 'kgh-gal-large')
          ?: wp_get_attachment_image_url($aid, '2048x2048')
          ?: wp_get_attachment_image_url($aid, 'full');
        if ($url_large) { $gallery_urls[] = ['id' => (int)$aid, 'large' => $url_large]; }
      }
      ?>
      <?php if (!empty($gallery_urls)): ?>
        <?php $count_gal = count($gallery_urls); $show_more = ($count_gal > 4); ?>
        <div class="kgh-gal-grid">
          <?php
            $max = $show_more ? 3 : min(4, $count_gal);
            for ($i=0; $i<$max; $i++): $row = $gallery_urls[$i];
          ?>
            <button type="button" class="kgh-gal-thumb" data-kgh-gal-index="<?php echo (int)$i; ?>">
              <?php echo wp_get_attachment_image(
                $row['id'],
                'kgh-gal-thumb',
                false,
                [
                  'loading' => 'lazy',
                  'class'   => 'absolute inset-0 w-full h-full object-cover',
                  'sizes'   => '(min-width:1024px) 300px, (min-width:640px) 33vw, 50vw',
                ]
              ); ?>
            </button>
          <?php endfor; ?>
          <?php if ($show_more): $row = $gallery_urls[3]; $more = $count_gal - 3; ?>
            <button type="button" class="kgh-gal-thumb" data-kgh-gal-index="3">
              <?php echo wp_get_attachment_image(
                $row['id'],
                'kgh-gal-thumb',
                false,
                [
                  'loading' => 'lazy',
                  'class'   => 'absolute inset-0 w-full h-full object-cover',
                  'sizes'   => '(min-width:1024px) 300px, (min-width:640px) 33vw, 50vw',
                ]
              ); ?>
              <span class="kgh-gal-more">+<?php echo (int)$more; ?> <?php echo esc_html__('more', 'kgh-theme'); ?></span>
            </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Advantages / Guarantees -->
      <section class="mt-8 md:mt-10">
        <div class="rounded-lg bg-white px-6 py-6 md:py-8">
          <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-8 items-center text-center">
            <!-- 1 -->
            <li class="flex flex-col items-center gap-2">
              <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
              </span>
              <span class="text-sm md:text-base"><?php echo esc_html__('Free 48h Cancellation', 'kgh-theme'); ?></span>
            </li>
            <!-- 2 -->
            <li class="flex flex-col items-center gap-2">
              <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
              </span>
              <span class="text-sm md:text-base"><?php echo esc_html__('All diets available', 'kgh-theme'); ?></span>
            </li>
            <!-- 3 -->
            <li class="flex flex-col items-center gap-2">
              <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
              </span>
              <span class="text-sm md:text-base"><?php echo esc_html__('All tastings included', 'kgh-theme'); ?></span>
            </li>
            <!-- 4 -->
            <li class="flex flex-col items-center gap-2">
              <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : ''; ?>
              </span>
              <span class="text-sm md:text-base"><?php echo esc_html__('No tourist traps', 'kgh-theme'); ?></span>
            </li>
          </ul>
        </div>
      </section>
          

      


        <?php
          // What you'll discover – DATA (version metas séparées)
          $discover_items = [];
          $post_id = $tour_id;

          // 1) lecture préférée : nos 3 clés step_*
          $titles = (array) get_post_meta($post_id, 'step_title', false);     // false => toutes les valeurs
          $texts  = (array) get_post_meta($post_id, 'step_text', false);
          $locs   = (array) get_post_meta($post_id, 'step_location', false);

          // 2) fallback si tu reviens aux anciens noms
          if (!$titles && !$texts && !$locs) {
            $titles = (array) get_post_meta($post_id, 'title', false);
            $texts  = (array) get_post_meta($post_id, 'text', false);
            $locs   = (array) get_post_meta($post_id, 'location', false);
          }
          // 3) autre fallback (au cas où)
          if (!$titles && !$texts && !$locs) {
            $titles = (array) get_post_meta($post_id, 'discover_title', false);
            $texts  = (array) get_post_meta($post_id, 'discover_text', false);
            $locs   = (array) get_post_meta($post_id, 'discover_location', false);
          }

          // 4) recomposition par index
          $max = max(count($titles), count($texts), count($locs));
          for ($i = 0; $i < $max; $i++) {
            $title = trim((string) ($titles[$i] ?? ''));
            $text  = trim((string) ($texts[$i]  ?? ''));
            $loc   = trim((string) ($locs[$i]   ?? ''));
            if ($title !== '' || $text !== '' || $loc !== '') {
              $discover_items[] = ['title'=>$title, 'text'=>$text, 'loc'=>$loc];
            }
          }
          ?>


          <?php if (!empty($discover_items)): ?>
        <section class="mt-8 md:mt-12">
          <div class="rounded-lg bg-white p-6 md:p-8">
            <h3 class="text-lg font-semibold text-black mb-6"><?php echo esc_html__('What you’ll discover in this tour', 'kgh-theme'); ?></h3>
            <ul class="space-y-8">
              <?php foreach ($discover_items as $it): ?>
                <li>
                  <div class="pl-4 border-l-2 border-black/90">
                    <?php if ($it['title'] !== ''): ?>
                      <p class="font-semibold text-black mb-1"><?php echo esc_html($it['title']); ?></p>
                    <?php endif; ?>
                    <?php if ($it['text'] !== ''): ?>
                      <p class="text-gray-800"><?php echo esc_html($it['text']); ?></p>
                    <?php endif; ?>
                    <?php if ($it['loc'] !== ''): ?>
                      <p class="mt-3 flex items-center gap-2 text-gray-800">
                        <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-map-pin'); ?></span>
                        <span><?php echo esc_html($it['loc']); ?></span>
                      </p>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="mt-8 rounded-md bg-gray-100 p-4 md:p-5">
              <div class="flex items-center gap-5">
                <span class="kgh-ico w-5 h-5 text-kgh-grey" aria-hidden="true"><?php echo kgh_icon('icon-alert-octagon'); ?></span>
                <div class="min-w-0">
                  <div class="text-xs font-semibold text-black"><?php echo esc_html__('Dietary Requirements', 'kgh-theme'); ?></div>
                  <p class="text-xs text-gray-700"><?php echo esc_html__('Please inform us of any allergies or dietary restrictions when booking.', 'kgh-theme'); ?></p>
                  <p class="text-xs text-gray-700"><?php echo esc_html__('We can accommodate vegetarian, halal, and gluten-free needs.', 'kgh-theme'); ?></p>
                </div>
              </div>
            </div>
          </div>
        </section>
        <?php endif; ?>




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
                <h3 class="text-lg font-semibold text-black mb-4"><?php echo esc_html__('Meeting Point', 'kgh-theme'); ?></h3>
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
                        <?php esc_html_e('Open in Google Maps', 'kgh-theme'); ?>
                      </a>
                    <?php endif; ?>
                    <?php if ($mt_naver): ?>
                      <a class="<?php echo esc_attr($btn_base); ?>" href="<?php echo esc_url($mt_naver); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Open in Naver Maps', 'kgh-theme'); ?>
                      </a>
                    <?php endif; ?>
                    <?php if ($mt_kakao): ?>
                      <a class="<?php echo esc_attr($btn_base); ?>" href="<?php echo esc_url($mt_kakao); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Open in Kakao Maps', 'kgh-theme'); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>

            <?php if ($end_title || $end_details): ?>
              <section>
                <h3 class="text-lg font-semibold text-black mb-4"><?php echo esc_html__('Ending Point', 'kgh-theme'); ?></h3>
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
                <article class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-0">
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
                        <span class="kgh-badge !bg-gray-100"><?php echo esc_html($lab); ?></span>
                      <?php endforeach; ?>
                    </div> 
                  </div>
                  <?php if (!empty($g_desc)): ?>
                      <div class="col-span-2 kgh-subtle text-[15px] md:text-base leading-relaxed">
                        <?php echo esc_html($g_desc); ?>
                      </div>
                    <?php endif; ?>
                </article>

              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <!-- Tour Details (static for now) -->
        <section class="mt-8 md:mt-12">
          <div class="rounded-lg bg-white p-6 md:p-8">
            <h3 class="text-lg font-semibold text-black mb-6"><?php echo esc_html__('Tour Details', 'kgh-theme'); ?></h3>

            <!-- 2 columns: included / not included -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-10 mb-8 mx-3">
              <!-- What's included -->
              <div>
                <h4 class="font-medium text-black mb-3"><?php echo esc_html__('What’s included', 'kgh-theme'); ?></h4>
                <ul class="space-y-3">
                  <?php
                    // simple helper to print a checked line with your green icon
                    $kgh_check = function($text){
                      echo '<li class="flex items-start gap-2">'
                        .   '<span class="inline-block w-5 text-center text-gray-500">✓</span>'
                        .   '<span class="text-black">'. esc_html($text) .'</span>'
                        . '</li>';
                    };
                    $kgh_check( esc_html__('Transportation fees', 'kgh-theme') );
                    $kgh_check( esc_html__('Expert Guide', 'kgh-theme') );
                    $kgh_check( esc_html__('Cultural Insights', 'kgh-theme') );
                    $kgh_check( esc_html__('Market navigation', 'kgh-theme') );
                  ?>
                </ul>
              </div>

              <!-- What's not included -->
              <div>
                <h4 class="font-medium text-black mb-3"><?php echo esc_html__('What’s not included', 'kgh-theme'); ?></h4>
                <ul class="space-y-3">
                  <?php
                    // cross lines (typographic ×)
                    $kgh_cross = function($text){
                      echo '<li class="flex items-start gap-2">'
                        .   '<span class="inline-block w-5 text-center text-gray-500 translate-y-[1px]">&times;</span>'
                        .   '<span class="text-black font-normal">'. esc_html($text) .'</span>'
                        . '</li>';
                    };
                    $kgh_cross( esc_html__('Hotel pickup', 'kgh-theme') );
                    $kgh_cross( esc_html__('Additional drinks', 'kgh-theme') );
                    $kgh_cross( esc_html__('Souvenirs', 'kgh-theme') );
                  ?>
                </ul>
              </div>
            </div>

            <!-- 3 guarantee blocks -->
            <div class="rounded-2xl border border-gray-300 bg-white px-3 py-6 md:py-7">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-y-6 md:gap-y-0 md:divide-x md:divide-gray-200">
                <!-- Photo service -->
                <div class="flex flex-col items-center text-center px-2">
                  <span class="kgh-ico w-5 h-5 text-[#3B7D3B] mb-2" aria-hidden="true">
                    <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
                  </span>
                  <div class="text-sm font-semibold text-black"><?php echo esc_html__('Photo Service', 'kgh-theme'); ?></div>
                  <div class="text-sm text-gray-700"><?php echo esc_html__('Ask your guide to take pictures', 'kgh-theme'); ?></div>
                </div>

                <!-- Hygiene -->
                <div class="flex flex-col items-center text-center px-2">
                  <span class="kgh-ico w-5 h-5 text-[#3B7D3B] mb-2" aria-hidden="true">
                    <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
                  </span>
                  <div class="text-sm font-semibold text-black"><?php echo esc_html__('Hygienes Standards', 'kgh-theme'); ?></div>
                  <div class="text-sm text-gray-700"><?php echo esc_html__('All venues inspected', 'kgh-theme'); ?></div>
                </div>


                <!-- All tastes included -->
                <div class="flex flex-col items-center text-center px-2">
                  <span class="kgh-ico w-5 h-5 text-[#3B7D3B] mb-2" aria-hidden="true">
                    <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
                  </span>
                  <div class="text-sm font-semibold text-black"><?php echo esc_html__('All tastes included', 'kgh-theme'); ?></div>
                  <div class="text-sm text-gray-700"><?php echo esc_html__('Choose what you want to eat', 'kgh-theme'); ?></div>
                </div>
              </div>
            </div>
          </div>
        </section>

        </div> <!-- /.kgh-tour-main (left column end) -->

    <?php if ($booking_enabled && $has_price): ?>
    <aside class="kgh-tour-aside">
      <section class="rounded-lg bg-white p-6 md:p-8">
      <h3 class="text-lg font-semibold text-black mb-6"><?php echo esc_html__('Check Availabilities & Prices', 'kgh-theme'); ?></h3>
      <?php
        // Optional SCF note displayed under the availability controls
        $avail_note = function_exists('SCF')
          ? SCF::get('availability_note', $tour_id)
          : get_post_meta($tour_id, 'availability_note', true);
        $avail_note = is_string($avail_note) ? trim($avail_note) : '';
      ?>

      <?php if ($from_price_cents > 0): ?>
        <p class="text-base text-black"><?php echo esc_html__('from', 'kgh-theme'); ?> <strong><?php echo '$'.number_format($from_price_cents/100, 2); ?></strong> <?php echo esc_html__('per person', 'kgh-theme'); ?></p>
      <?php endif; ?>
      <?php if ($avail_note !== ''): ?>
        <p class="mt-1 text-base text-gray-700"><?php echo esc_html($avail_note); ?></p>
      <?php endif; ?>
      <hr class="kgh-separator">

      <!-- Date -->
      <label class="block mb-4">
        <div class="flex items-center gap-2 text-sm font-semibold text-black mb-1">
          <span class="kgh-ico w-4 h-4" aria-hidden="true"><?php echo kgh_icon('icon-calendar'); ?></span>
          <span><?php echo esc_html__('Date', 'kgh-theme'); ?></span>
        </div>
        <input id="kgh-date" type="text" class="kgh-input w-full" placeholder="<?php echo esc_attr__('YYYY-MM-DD', 'kgh-theme'); ?>" readonly>
      </label>

      <!-- Time -->
      <label class="block mb-4">
        <div class="flex items-center gap-2 text-sm font-semibold text-black mb-1">
          <span class="kgh-ico w-4 h-4" aria-hidden="true"><?php echo kgh_icon('icon-clock'); ?></span>
          <span><?php echo esc_html__('Starting Time', 'kgh-theme'); ?></span>
        </div>
        <select id="kgh-time" class="kgh-input w-full"></select>
      </label>

      <!-- Guests -->
      <label class="block mb-4">
        <div class="flex items-center gap-2 text-sm font-semibold text-black mb-1">
          <span class="kgh-ico w-4 h-4" aria-hidden="true"><?php echo kgh_icon('icon-users'); ?></span>
          <span><?php echo esc_html__('Guests', 'kgh-theme'); ?></span>
        </div>
        <select id="kgh-guests" class="kgh-input w-full">
          <option value="1">1</option>
        </select>
      </label>

      <hr class="kgh-separator">
      <div class="flex items-center justify-between text-base mb-2">
        <span><?php echo esc_html__('Summary', 'kgh-theme'); ?></span>
        <span id="kgh-summary"></span>
      </div>
      <hr class="kgh-separator" style="margin-top:8px;margin-bottom:8px">
      <div class="flex items-center justify-between text-base font-semibold text-black mb-4">
        <span><?php echo esc_html__('Total amount', 'kgh-theme'); ?></span>
        <span id="kgh-total">$0.00</span>
      </div>

      <button id="kgh-cta" class="kgh-btn--primary w-full"><?php echo esc_html__('Book this tour', 'kgh-theme'); ?></button>

      <p id="kgh-no-slots" class="mt-3 text-sm text-gray-700" style="display:none">
        <?php echo esc_html__('No availability for this date', 'kgh-theme'); ?>
      </p>
      <p id="kgh-booking-error" class="mt-3 text-sm text-red-700" style="display:none"></p>
    </section>
    </aside>
    <?php endif; ?>

    <!-- CTA: Contact us to book -->
    <?php if (!$booking_enabled || !$has_price): ?>
    <aside class="kgh-tour-aside">
      <section class="rounded-lg bg-white p-6 md:p-8">
      <div class="flex flex-col max-w-[400px]">
        <div class="flex flex-row items-center">
          <span class="mr-4"><?php echo esc_html__('to book this tour :', 'kgh-theme'); ?></span>
          <a href="#kgh-contact" class="kgh-btn--primary text-center">
            <?php esc_html_e('Contact us', 'kgh-theme'); ?>
          </a>
        </div>
      </div>
      </section>
    </aside>
    <?php endif; ?>

  </div> <!-- /.kgh-tour-layout -->


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

<?php if (!empty($gallery_urls)): ?>
<!-- Lightweight lightbox for tour gallery -->
  <div id="kgh-lightbox" class="kgh-lightbox" hidden>
    <button type="button" class="kgh-lightbox-close" aria-label="Close">×</button>
    <button type="button" class="kgh-lightbox-prev" aria-label="Previous">‹</button>
    <img id="kgh-lightbox-img" src="" alt="">
    <button type="button" class="kgh-lightbox-next" aria-label="Next">›</button>
    <script>
      (function(){
        const gal = <?php echo wp_json_encode($gallery_urls); ?>;
        if (!Array.isArray(gal) || gal.length===0) return;
        const box = document.getElementById('kgh-lightbox');
        const img = document.getElementById('kgh-lightbox-img');
        const btnPrev = box.querySelector('.kgh-lightbox-prev');
        const btnNext = box.querySelector('.kgh-lightbox-next');
        const btnClose= box.querySelector('.kgh-lightbox-close');
        const hero = document.getElementById('kgh-hero-figure');
        let cur = 0;
        function show(i){ cur = (i+gal.length)%gal.length; img.src = gal[cur].large; box.hidden=false; box.classList.add('open'); document.body.style.overflow='hidden'; }
        function close(){ box.classList.remove('open'); box.hidden=true; img.src=''; document.body.style.overflow=''; }
        document.querySelectorAll('.kgh-gal-thumb').forEach(btn=>{
          btn.addEventListener('click', ()=>{ const idx=parseInt(btn.getAttribute('data-kgh-gal-index')||'0',10)||0; show(idx); });
        });
        if (hero) { hero.classList.add('kgh-hero-clickable'); hero.addEventListener('click', ()=>show(0)); }
        btnPrev.addEventListener('click', ()=>show(cur-1));
        btnNext.addEventListener('click', ()=>show(cur+1));
        btnClose.addEventListener('click', close);
        box.addEventListener('click', (e)=>{ if(e.target===box) close(); });
        window.addEventListener('keydown', (e)=>{ if(box.hidden) return; if(e.key==='Escape') close(); if(e.key==='ArrowLeft') show(cur-1); if(e.key==='ArrowRight') show(cur+1); });
      })();
    </script>
  </div>
<?php endif; ?>






<?php if ($booking_enabled): ?>
<!-- ————— Script réservation (TON CODE D’ORIGINE, uniquement si booking ON) -->
<script>
(function(){
  const messages = <?php echo wp_json_encode( $ui_strings ); ?>;
  <?php
    $lang_slug = function_exists('pll_current_language') ? (string) pll_current_language('slug') : '';
    $checkout_page = get_page_by_path('checkout');
    $checkout_id = $checkout_page ? (int) $checkout_page->ID : 0;
    if ($checkout_id && function_exists('pll_get_post')) {
      $mapped = (int) pll_get_post($checkout_id);
      if ($mapped) { $checkout_id = $mapped; }
    }
    // Fallback: find page containing [kgh_checkout] in current language
    if (!$checkout_id) {
      $args = [
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'suppress_filters'=> false,
      ];
      if ($lang_slug && taxonomy_exists('language')) {
        $args['tax_query'] = [[
          'taxonomy' => 'language',
          'field'    => 'slug',
          'terms'    => $lang_slug,
        ]];
      }
      foreach (get_posts($args) as $p) {
        if (has_shortcode($p->post_content, 'kgh_checkout')) { $checkout_id = (int) $p->ID; break; }
      }
    }
    $checkout_url = $checkout_id ? get_permalink($checkout_id) : home_url('/checkout/');
  ?>
  const checkoutBase = <?php echo wp_json_encode( $checkout_url ); ?>;
  const i18nQty = <?php echo wp_json_encode([
    'guest'  => __('guest', 'kgh-theme'),
    'guests' => __('guests', 'kgh-theme'),
  ]); ?>;
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
  const elSummary= document.getElementById('kgh-summary');
  const elTotal  = document.getElementById('kgh-total');

  let slots = [];
  let selectedSlot = null;

  let fpDate = null;

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
  function dollars(cents){ return '$' + ((cents||0)/100).toFixed(2); }
  function updateQuoteUI(){
    if (!selectedSlot) { if(elSummary) elSummary.textContent=''; if(elTotal) elTotal.textContent='$0.00'; return; }
    const qty = Math.max(1, parseInt(elGuests.value||'1',10));
    const unit = parseInt(selectedSlot.price_usd||0,10);
    const total = unit * qty;
    if (elSummary) {
      const label = `${qty} ${qty>1 ? i18nQty.guests : i18nQty.guest}`;
      elSummary.textContent = `${label} × ${dollars(unit)}`;
    }
    if (elTotal) elTotal.textContent = dollars(total);
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
      let dateOpts = apiDates.map(d => ({ value: d, disabled: false }));

      // On garde ta logique d’injection (cutoff aujourd’hui + preDate)
      dateOpts = await maybeInjectDate(todayKST(), dateOpts);
      dateOpts = await maybeInjectDate(preDate, dateOpts);

      // Séparation des dates activées/désactivées
      const enabled = dateOpts.filter(o => !o.disabled).map(o => o.value);
      const disabled = dateOpts.filter(o =>  o.disabled).map(d => d.value);

      if (!enabled.length && !disabled.length) {
        document.getElementById('kgh-date').value = messages.noAvailability;
        setDisabled(elTime, true);
        setDisabled(elGuests, true);
        setDisabled(elCTA, true);
        elNo.style.display = 'block';
        return;
      }

      // Date cible par défaut
      let target = preDate && enabled.includes(preDate) ? preDate : (enabled[0] || null);

      // (Ré)initialiser Flatpickr
      if (fpDate) fpDate.destroy();
      fpDate = flatpickr('#kgh-date', {
        dateFormat: 'Y-m-d',
        defaultDate: target || undefined,
        enable: enabled.length ? enabled : undefined,
        disable: disabled.map(d => ({ from: d, to: d })),
        disableMobile: true,              // 👈 important pour garder le skin
        onChange(selectedDates, dateStr) {
          if (dateStr) loadSlotsForDate(dateStr);
        }
      });

      // Charger les créneaux de la date par défaut
      if (target) {
        await loadSlotsForDate(target);
      } else {
        // tout est désactivé => bloquer
        setDisabled(elTime, true);
        setDisabled(elGuests, true);
        setDisabled(elCTA, true);
        elNo.style.display = 'block';
      }

    } catch (e) {
      document.getElementById('kgh-date').value = messages.networkError;
    }
  }


  // function renderDateOptions(opts) {
  //   elDate.innerHTML = '';
  //   opts.sort((a,b) => a.value.localeCompare(b.value));
  //   opts.forEach(o => {
  //     const opt = document.createElement('option');
  //     opt.value = o.value;
  //     opt.textContent = o.label;
  //     if (o.disabled) {
  //       opt.disabled = true;
  //       opt.setAttribute('aria-disabled', 'true');
  //     }
  //     elDate.appendChild(opt);
  //   });
  // }

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
        updateQuoteUI();
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
      updateQuoteUI();
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
    updateQuoteUI();
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

  // elDate.addEventListener('change', () => {
  //   if (elDate.value) { loadSlotsForDate(elDate.value); }
  // });
  elTime.addEventListener('change', onTimeChange);
  elGuests.addEventListener('change', updateQuoteUI);
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
    const url = new URL(checkoutBase, window.location.origin);
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
