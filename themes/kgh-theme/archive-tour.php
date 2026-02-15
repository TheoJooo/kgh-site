<?php
/** Archive: Tours (CPT 'tour') – grouped carousels */
if (!defined('ABSPATH')) exit;
get_header();
?>

<main id="main">

  <?php
  /* ============================ CONFIG ============================
     Adapte simplement les libellés de zones / tags ici.
     - Les groupes "area" filtrent sur le champ SCF `area_label` (LIKE).
     - "seasonal" et "cooking" cherchent d'abord un tag SCF `badge_tags`
       (ex: seasonal, season, cooking, cooking-class), sinon fallback sur titre/contenu.
  ================================================================== */
  $GROUPS = [
    [
      'key'       => 'seoul_around',                   
      'title'     => __('In Seoul & Nearby', 'kgh-theme'),
      'subtitle'  => __('All our tours available in Seoul and accessible from Seoul!', 'kgh-theme'),
      'area_like' => ['Seoul', 'Gyeonggi', 'Incheon'],
      'limit'     => 12,
    ],
    [
      'key'       => 'busan_south',
      'title'     => __('Busan & Southern Korea', 'kgh-theme'),
      'subtitle'  => __('These tours are outside of Seoul, we will help you organize your trip and guide you in the countryside of South Korea!', 'kgh-theme'),
      'area_like' => ['Busan', 'Southern Korea', 'Gyeongsang', 'Jeolla', 'Jeju', 'Gyeongju'],
      'limit'     => 12,
    ],
    [
      'key'       => 'seasonal',
      'title'     => __('Seasonal Tours', 'kgh-theme'),
      'subtitle'  => __('These tours are only available during their seasons!', 'kgh-theme'),
      'seasonal'  => true,
      'limit'     => 12,
    ],
    [
      'key'       => 'cooking',
      'title'     => __('Cooking Classes', 'kgh-theme'),
      'subtitle'  => __('Participate to one of our cooking class!', 'kgh-theme'),
      'cooking_class'   => true,
      'limit'     => 12,
    ],
  ];

    // Helper: query par groupe
    function kgh_query_group(array $g){
    $limit = isset($g['limit']) ? max(1, (int)$g['limit']) : 12;
    $args = [
        'post_type'      => 'tour',
        'posts_per_page' => -1, // tri custom en PHP, puis découpe au bon nombre
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ];

    // Filtre par zone (LIKE sur area_label)
    if (!empty($g['area_like'])) {
        $areas   = (array) $g['area_like'];
        $meta_or = [];
        foreach ($areas as $a) {
        $meta_or[] = [
            'key'     => 'area_label',
            'value'   => $a,
            'compare' => 'LIKE',
        ];
        }
        $args['meta_query'][] = array_merge(['relation' => 'OR'], $meta_or);
    }

    // SCF booléens (stockés en '1' / '0')
    if (!empty($g['seasonal'])) {
        $args['meta_query'][] = [
        'key'     => 'seasonal_tour',   // nom exact de ton champ SCF
        'value'   => '1',
        'compare' => '=',
        ];
    }

    if (!empty($g['cooking_class'])) {
        $args['meta_query'][] = [
        'key'     => 'cooking_class',   // nom exact de ton champ SCF
        'value'   => '1',
        'compare' => '=',
        ];
    }

    if (!empty($args['meta_query']) && !isset($args['meta_query']['relation'])) {
        $args['meta_query']['relation'] = 'AND';
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()) {
      return $q;
    }

    $group_key = isset($g['key']) ? sanitize_key((string)$g['key']) : '';
    $date_score = static function($post){
      $raw = (!empty($post->post_date_gmt) && $post->post_date_gmt !== '0000-00-00 00:00:00')
        ? $post->post_date_gmt
        : $post->post_date;
      $ts = strtotime((string)$raw);
      return $ts ?: 0;
    };

    usort($q->posts, function($a, $b) use ($group_key, $date_score){
      $a_custom = function_exists('kgh_get_tour_group_order_value') ? kgh_get_tour_group_order_value($a->ID, $group_key) : null;
      $b_custom = function_exists('kgh_get_tour_group_order_value') ? kgh_get_tour_group_order_value($b->ID, $group_key) : null;
      $a_has_custom = ($a_custom !== null);
      $b_has_custom = ($b_custom !== null);

      if ($a_has_custom && $b_has_custom && $a_custom !== $b_custom) {
        return $a_custom <=> $b_custom;
      }
      if ($a_has_custom !== $b_has_custom) {
        return $a_has_custom ? -1 : 1;
      }

      $a_date = $date_score($a);
      $b_date = $date_score($b);
      if ($a_date !== $b_date) {
        return $b_date <=> $a_date;
      }

      return ((int)$a->ID) <=> ((int)$b->ID);
    });

    if (count($q->posts) > $limit) {
      $q->posts = array_slice($q->posts, 0, $limit);
    }
    $q->post_count = count($q->posts);
    $q->found_posts = $q->post_count;
    $q->max_num_pages = 1;

    return $q;
  }

  // Helper: icône
  function kgh_icon_or($name){ return function_exists('kgh_icon') ? kgh_icon($name) : ''; }

  // Helper: carte tour (même rendu que la Home)
  function kgh_render_tour_card($post_id){
    ob_start();
    ?>
    <article class="kgh-card">
      <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="block">
        <div class="kgh-card-media">

          <?php if (has_post_thumbnail($post_id)) {
            echo get_the_post_thumbnail($post_id, 'large', ['class'=>'absolute inset-0 h-full w-full object-cover']);
          } ?>

          <?php
          $area = function_exists('SCF')
            ? SCF::get('area_label', $post_id)
            : get_post_meta($post_id, 'area_label', true);

          // badges via SCF (array variadique)
          if (function_exists('SCF')) {
            $badge_raw = (array) SCF::get('badge_tags', $post_id);
          } else {
            $badge_raw = (array) get_post_meta($post_id, 'badge_tags', false);
          }
          $badges = [];
          if (!empty($badge_raw)) {
            foreach ($badge_raw as $k => $v) {
              $slug = is_int($k) ? trim((string)$v) : trim((string)$k);
              if ($slug === '') continue;
              $label = function_exists('kgh_badge_label') ? kgh_badge_label($slug) : ucwords(str_replace('-', ' ', $slug));
              if ($label === '') continue;
              $badges[] = ['slug'=>$slug, 'label'=>$label];
            }
          }
          $badges = array_slice($badges, 0, 3);
          ?>

          <?php if (!empty($area)) : ?>
            <div class="kgh-label-tl">
              <span class="kgh-badge kgh-badge--light">
                <span class="kgh-badge-ico" aria-hidden="true"><?php echo kgh_icon_or('icon-map-pin'); ?></span>
                <span><?php echo esc_html($area); ?></span>
              </span>
            </div>
          <?php endif; ?>

          <?php if (!empty($badges)) : ?>
            <div class="kgh-label-bl">
              <?php foreach ($badges as $it): ?>
                <span class="kgh-badge kgh-badge--dark">
                  <?php $ico = function_exists('kgh_badge_icon') ? kgh_badge_icon($it['slug']) : ''; ?>
                  <?php if ($ico): ?><span class="kgh-badge-ico" aria-hidden="true"><?php echo $ico; ?></span><?php endif; ?>
                  <span><?php echo esc_html($it['label']); ?></span>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </a>
      <?php
        $tag = function_exists('SCF') ? SCF::get('tag', $post_id) : get_post_meta($post_id, 'tag', true);
        $tag = is_string($tag) ? trim($tag) : '';
        ?>

        <?php if ($tag !== ''): ?>
        <div class="kgh-tagbar w-full text-center py-1 absolute top-[172px] sm:top-[184px] lg:top-48">
            <?php echo esc_html($tag); ?>
        </div>
      <?php endif; ?>

      <div class="kgh-card-body">
        <h3 class="kgh-card-title"><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
        <?php $short = wp_trim_words( wp_strip_all_tags( get_the_excerpt($post_id) ), 22, '' ); ?>
        <p class="kgh-card-ex-inline">
          <?php echo esc_html($short); ?>&nbsp;
          <?php /* translators: %s: tour title */ ?>
          <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="kgh-card-more" aria-label="<?php echo esc_attr( sprintf(__('%s – View more', 'kgh-theme'), get_the_title($post_id)) ); ?>">
            <?php esc_html_e('View more', 'kgh-theme'); ?>
          </a>
        </p>
      </div>

      <?php
      $price_cents   = get_post_meta($post_id, 'price_usd_cents', true);
      $price_cents   = is_numeric($price_cents) ? (int)$price_cents : 0;
      $price_dollars = $price_cents > 0 ? number_format($price_cents / 100, 0) : null;
      $duration  = function_exists('SCF') ? SCF::get('duration',  $post_id) : get_post_meta($post_id, 'duration',  true);
      $capacity  = function_exists('SCF') ? SCF::get('capacity',  $post_id) : get_post_meta($post_id, 'capacity',  true);
      $languages = function_exists('SCF') ? SCF::get('languages', $post_id) : get_post_meta($post_id, 'languages', true);
      ?>
      <div class="kgh-meta kgh-meta-row">
        <?php if ($duration): ?>
            <span class="kgh-meta-item flex items-center gap-1 whitespace-normal h-auto">
                <span class="kgh-ico mt-[2px]" aria-hidden="true"><?php echo kgh_icon_or('icon-clock'); ?></span>
                <span class="leading-tight">
                <?php
                    // autorise seulement <br>
                    echo wp_kses( wp_specialchars_decode((string)$duration, ENT_QUOTES), ['br'=>[]] );
                ?>
                </span>
            </span>
        <?php endif; ?>
        <?php if ($capacity): ?>
          <span class="kgh-meta-item"><span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon_or('icon-users'); ?></span><span><?php echo esc_html($capacity); ?></span></span>
        <?php endif; ?>
        <?php if ($languages): ?>
          <span class="kgh-meta-item"><span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon_or('icon-globe'); ?></span><span class="truncate max-w-[9rem] sm:max-w-[12rem]"><?php echo esc_html($languages); ?></span></span>
        <?php endif; ?>
      </div>

      <div class="kgh-footer">
        <span class="kgh-price">
          <?php echo $price_dollars ? esc_html__('from', 'kgh-theme') . ' $' . esc_html($price_dollars) : esc_html__('Price on request', 'kgh-theme'); ?>
        </span>
        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="kgh-card-cta kgh-btn--tertiary">
          <?php esc_html_e('View Details', 'kgh-theme'); ?> <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon_or('icon-right'); ?></span>
        </a>
      </div>
    </article>
    <?php
    return ob_get_clean();
  }
  ?>

  <!-- HERO -->
  <section class="bg-kgh-bg">
    <div class="kgh-container py-12 md:py-16 text-center">
      <h1 class="kgh-h1 text-black mb-4"><?php echo esc_html__('Our tours', 'kgh-theme'); ?></h1>
      <p class="kgh-subtle max-w-3xl mx-auto mb-10 md:mb-12">
        <?php echo esc_html__('Across Seoul and in the whole country – crafted for authentic Korean experiences.', 'kgh-theme'); ?>
      </p>

      <!-- Stats (4) -->
      <ul class="flex flex-wrap justify-center gap-4 md:gap-6">
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center text-left gap-4 rounded-2xl bg-[#EBEBEB] border-[#E1E1E1] px-3 py-4 ">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true"><?php echo kgh_icon_or('icon-smiley'); ?></span>
            </span>
            <div><div class="text-2xl font-semibold text-black leading-tight">3000+</div><div class="text-gray-700 text-xs"><?php echo esc_html__('Happy Gourmet Hunters', 'kgh-theme'); ?></div></div>
          </div>
        </li>
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center text-left gap-4 rounded-2xl bg-[#EBEBEB] border-[#E1E1E1] px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true"><?php echo kgh_icon_or('icon-compass'); ?></span>
            </span>
            <div><div class="text-2xl font-semibold text-black leading-tight">2000+</div><div class="text-gray-700 text-xs"><?php echo esc_html__('Tours since 2010', 'kgh-theme'); ?></div></div>
          </div>
        </li>
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center text-left gap-4 rounded-2xl bg-[#EBEBEB] border-[#E1E1E1] px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true"><?php echo kgh_icon_or('icon-home'); ?></span>
            </span>
            <div><div class="text-2xl font-semibold text-black leading-tight">1.8K+</div><div class="text-gray-700 text-xs"><?php echo esc_html__('Addresses and Hidden Gems', 'kgh-theme'); ?></div></div>
          </div>
        </li>
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center text-left gap-4 rounded-2xl bg-[#EBEBEB] border-[#E1E1E1] px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true"><?php echo kgh_icon_or('icon-smiley'); ?></span>
            </span>
            <div><div class="text-2xl font-semibold text-black leading-tight">6</div><div class="text-gray-700 text-xs"><?php echo esc_html__('Amazing Guides', 'kgh-theme'); ?></div></div>
          </div>
        </li>
      </ul>
    </div>
  </section>

  <!-- SECTIONS / CARROUSELS -->
  <?php foreach ($GROUPS as $g): $q = kgh_query_group($g); if ($q->have_posts()): 
        $cid = 'kgh-carousel-'.$g['key']; ?>
    <section class="bg-white py-12 md:py-16">
      <div class="kgh-container">
        <header class="mb-6 md:mb-8 flex items-end justify-between gap-4">
          <div>
            <h2 class="kgh-h1 text-black mb-2"><?php echo esc_html( __($g['title'], 'kgh-theme') ); ?></h2>
            <p class="kgh-subtle"><?php echo esc_html( __($g['subtitle'], 'kgh-theme') ); ?></p>
          </div>

          <!-- Controls -->
          <div class="hidden md:flex items-center gap-3">
            <button type="button" class="kgh-carousel-btn" data-kgh-prev="<?php echo esc_attr($cid); ?>" aria-label="<?php echo esc_attr__('Previous', 'kgh-theme'); ?>">‹</button>
            <button type="button" class="kgh-carousel-btn" data-kgh-next="<?php echo esc_attr($cid); ?>" aria-label="<?php echo esc_attr__('Next', 'kgh-theme'); ?>">›</button>
          </div>
        </header>

        <div class="relative">
          <div id="<?php echo esc_attr($cid); ?>"
               class="flex gap-6 md:gap-8 overflow-x-auto overflow-y-hidden no-scrollbar snap-x snap-mandatory scroll-smooth px-1"
               data-kgh-carousel>
            <?php while ($q->have_posts()): $q->the_post(); echo kgh_render_tour_card(get_the_ID()); endwhile; wp_reset_postdata(); ?>
          </div>

          <!-- Fades -->
          <span class="kgh-fade absolute left-0 top-0 h-full w-8 bg-gradient-to-r from-white to-transparent opacity-0"></span>
          <span class="kgh-fade absolute right-0 top-0 h-full w-8 bg-gradient-to-l from-white to-transparent opacity-100"></span>
        </div>

        <!-- Controls mobile -->
        <div class="mt-6 flex md:hidden items-center justify-center gap-3">
          <button type="button" class="kgh-carousel-btn" data-kgh-prev="<?php echo esc_attr($cid); ?>" aria-label="<?php echo esc_attr__('Previous', 'kgh-theme'); ?>">‹</button>
          <button type="button" class="kgh-carousel-btn" data-kgh-next="<?php echo esc_attr($cid); ?>" aria-label="<?php echo esc_attr__('Next', 'kgh-theme'); ?>">›</button>
        </div>
      </div>
    </section>
  <?php endif; endforeach; ?>


  <!-- Home • Value props row -->
  <section class="bg-white pt-20 pb-32">
    <div class="kgh-container">
      <!-- Headline centered -->
      <p class="text-center text-lg max-w-3xl mx-auto mb-8 md:mb-12">
        <?php echo wp_kses_post( __('Authentic Korean food tours in Seoul:</br>hidden gems, small groups, and the stories behind every bite.', 'kgh-theme') ); ?>
      </p>

      <!-- Pills: 1x4 desktop, stacked mobile -->
      <ul class="flex flex-wrap justify-center gap-4 md:gap-6">
        <!-- 1 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm"><?php echo esc_html__('Free 48h Cancellation', 'kgh-theme'); ?></span>
          </div>
        </li>

        <!-- 2 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm"><?php echo esc_html__('All Diets Available', 'kgh-theme'); ?></span>
          </div>
        </li>

        <!-- 3 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm"><?php echo esc_html__('No Tourist Traps', 'kgh-theme'); ?></span>
          </div>
        </li>

        <!-- 4 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm"><?php echo esc_html__('All Tastings Included', 'kgh-theme'); ?></span>
          </div>
        </li>
      </ul>

    </div>
  </section>

  <!-- Private Tour CTA -->
  <section class="bg-kgh-bg pt-44 pb-40">
    <div class="kgh-container">
      <div class="relative mx-auto max-w-3xl">
        <?php $blob = get_theme_file_uri('assets/img/blob-map-korea.png'); ?>

        <!-- Tache en fond -->
        <!-- <img
          src="<?php echo esc_url($blob); ?>"
          alt="korea-map"
          aria-hidden="true"
          class="pointer-events-none select-none absolute left-1/2 top-1/2 z-0 w-[520px] max-w-sm -translate-x-1/2 -translate-y-1/2 opacity-90 md:w-[576px] md:max-w-xl"> -->

        <!-- Contenu au-dessus -->
        <div class="relative z-10 text-center">
          <p class="kgh-subtle text-black mb-6"><?php echo esc_html__('Can’t find what you’re looking for?', 'kgh-theme'); ?></p>
          <h2 class="kgh-h1 text-black mb-5"><?php echo esc_html__('Private tour', 'kgh-theme'); ?></h2>
          <p class="kgh-subtle mb-10"><?php echo esc_html__('We can create a fully customized experience just for you!', 'kgh-theme'); ?></p>

          <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="<?php echo esc_url( home_url('/contact/') ); ?>"
              class="kgh-btn--quaternary hover:no-underline"><?php esc_html_e('Contact Us', 'kgh-theme'); ?></a>
            <a href="<?php echo esc_url( home_url('/contact/') ); ?>"
              class="kgh-btn--primary hover:no-underline"><?php esc_html_e('Request Custom Tour', 'kgh-theme'); ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Home • FAQ -->
  <section class="bg-white py-20 md:py-36">
    <div class="kgh-container">
      <div class="grid md:grid-cols-[auto_1fr_auto] items-center gap-6 md:gap-16">
        <!-- Title -->
        <h2 class="font-serif text-2xl md:text-3xl font-bold text-black md:pt-2 mx-14 text-center"><?php echo esc_html__('FAQ:', 'kgh-theme'); ?></h2>

        <!-- Accordion -->
        <div id="kgh-faq" class="w-full max-w-3xl mx-auto md:mx-0">
          <!-- Item -->
          <div class="border-t border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7"><?php echo esc_html__('Can the weather cancel a tour?', 'kgh-theme'); ?></span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              <?php echo esc_html__('Tours run rain or shine! Don\'t forget your umbrella and enjoy Korea\'s rainy mood!', 'kgh-theme'); ?>
            </div>
          </div>

          <!-- Item -->
          <div class="border-t border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7"><?php echo esc_html__('Can we customize or book a private tour?', 'kgh-theme'); ?></span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              <?php echo esc_html__('Yes—private or custom routes available. Tell us interests, budget, and timing, we\'ll craft a tailored itinerary.', 'kgh-theme'); ?>
            </div>
          </div>

          <!-- Item -->
          <div class="border-t border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7"><?php echo esc_html__('Do you offer options for dietary restrictions or allergies?', 'kgh-theme'); ?></span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              <?php echo esc_html__('Absolutely. We accommodate vegetarians, vegans, halal, gluten-free, and allergies. Just inform us early when booking.', 'kgh-theme'); ?>
            </div>
          </div>

          <!-- Item -->
          <div class="border-t border-b border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7"><?php echo esc_html__('What kind of places and food will we visit?', 'kgh-theme'); ?></span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              <?php echo esc_html__('Hidden local markets, family-run eateries, street stalls; regional specialties, seasonal dishes, and classic Korean favorites.', 'kgh-theme'); ?>
            </div>
          </div>
        </div>

        <!-- CTA -->
        <div class="md:sticky md:top-20">
          <a href="<?php echo esc_url( home_url('/contact/') ); ?>"
            class="kgh-btn--quaternary hover:no-underline whitespace-nowrap">
            <?php esc_html_e('Personal Request', 'kgh-theme'); ?>
          </a>
        </div>
      </div>
    </div>
  </section>

  <script>
  (function(){
    const root = document.getElementById('kgh-faq');
    if (!root) return;
    const items = Array.from(root.querySelectorAll('[data-faq-toggle]'));

    function closeAll(exceptBtn){
      items.forEach(btn => {
        if (btn === exceptBtn) return;
        btn.setAttribute('aria-expanded', 'false');
        const ico = btn.querySelector('.kgh-ico');
        if (ico) ico.style.transform = 'rotate(0deg)';
        const panel = btn.nextElementSibling;
        if (panel) panel.classList.add('hidden');
      });
    }

    items.forEach(btn => {
      btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        if (expanded) {
          btn.setAttribute('aria-expanded', 'false');
          const ico = btn.querySelector('.kgh-ico'); if (ico) ico.style.transform = 'rotate(0deg)';
          const panel = btn.nextElementSibling; if (panel) panel.classList.add('hidden');
        } else {
          closeAll(btn);
          btn.setAttribute('aria-expanded', 'true');
          const ico = btn.querySelector('.kgh-ico'); if (ico) ico.style.transform = 'rotate(180deg)';
          const panel = btn.nextElementSibling; if (panel) panel.classList.remove('hidden');
        }
      });
    });
  })();
  </script>




</main>

<script>
(function(){
  // Initialise tous les carrousels présents
  const carousels = document.querySelectorAll('[data-kgh-carousel]');
  carousels.forEach(scroller => {
    const id = scroller.id;
    const wrap = scroller.parentElement; // relatif (pour fades)
    const btnPrev = document.querySelector('[data-kgh-prev="'+id+'"]');
    const btnNext = document.querySelector('[data-kgh-next="'+id+'"]');
    const fadeL  = wrap.querySelector('.kgh-fade:first-of-type');
    const fadeR  = wrap.querySelector('.kgh-fade:last-of-type');

    function cardStep(){
      const first = scroller.children[0];
      if (!first) return 320;
      const rect = first.getBoundingClientRect();
      const gap = parseInt(getComputedStyle(scroller).gap || '0', 10) || 0;
      return Math.round(rect.width + gap);
    }
    function scrollByCards(n){ scroller.scrollBy({ left: n * cardStep(), behavior: 'smooth' }); }
    function setDisabled(btn, disabled){
      if (!btn) return;
      btn.disabled = !!disabled;
      btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }
    function setFade(el, visible){ if (el) el.style.opacity = visible ? '1' : '0'; }

    function update(){
      const max = Math.max(0, scroller.scrollWidth - scroller.clientWidth - 1);
      const atStart = scroller.scrollLeft <= 1;
      const atEnd   = scroller.scrollLeft >= max;
      setDisabled(btnPrev, atStart);
      setDisabled(btnNext, atEnd);
      setFade(fadeL, !atStart);
      setFade(fadeR, !atEnd);
    }

    if (btnPrev) btnPrev.addEventListener('click', () => scrollByCards(-1));
    if (btnNext) btnNext.addEventListener('click', () => scrollByCards(+1));
    scroller.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', () => requestAnimationFrame(update));
    window.addEventListener('load', update);
    requestAnimationFrame(update);
  });
})();
</script>

<?php get_footer();
