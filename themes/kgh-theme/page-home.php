<?php
/* Template Name: Home */
get_header(); ?>

<main class="kgh-home">
  <!-- HERO (2 cols desktop, stacked mobile) -->
  <section class="bg-kgh-bg">
    <div class="kgh-container py-10 md:py-24">
      <div class="grid md:grid-cols-10 md:items-center gap-8 md:gap-12">
        <div class="text-center md:text-left md:col-span-4">
          <p class="kgh-overline mb-3">Discover Korea’s Culinary</br>Essence and Timeless Wellness</p>
          <h1 class="kgh-h1 my-8">Chasing the Korea’s</br>Authentic Flavors!</h1>
          <p class="kgh-subtle mb-8 max-w-[36ch] md:max-w-none mx-auto md:mx-0">Your journey into Korean food and</br>gastronomic culture starts here!</p>
          <div class="flex flex-wrap items-center gap-3 justify-center md:justify-start">
            <a href="<?php echo esc_url( home_url('/contact/') ); ?>" class="kgh-btn--primary hover:no-underline">Book a tour</a>
            <a href="<?php echo esc_url( home_url('/contact/') ); ?>" class="kgh-btn--ghost hover:no-underline">Contact Us</a>
          </div>
          <div class="kgh-google-review justify-center md:justify-start">
            <span class="kgh-google-stars">★★★★★</span>
            <span>on Google</span>
          </div>
        </div>
        <div class="order-first md:order-none md:col-span-6">
          <figure class="relative aspect-[4/3] md:aspect-[5/4] border-[#661e11] border-2 overflow-hidden ring-1 ring-black/10 shadow-lg">
            <?php
            $home_id = get_the_ID();

            // Lis d'abord SCF, sinon la meta brute (au cas où)
            $desk_raw = function_exists('SCF') ? SCF::get('home_hero_desktop', $home_id) : '';
            $mob_raw  = function_exists('SCF') ? SCF::get('home_hero_mobile',  $home_id) : '';

            if (!$desk_raw) $desk_raw = get_post_meta($home_id, 'home_hero_desktop', true);
            if (!$mob_raw)  $mob_raw  = get_post_meta($home_id, 'home_hero_mobile',  true);

            // Resolver (ID | array | URL)
            $resolve = function($val){
              $id=0; $url=''; $set='';
              if (is_numeric($val)) { $id=(int)$val; }
              elseif (is_array($val)) {
                if (isset($val['id'])) $id=(int)$val['id'];
                elseif (isset($val[0])) $id=(int)$val[0];
                elseif (!empty($val['url'])) $url=(string)$val['url'];
              } elseif (is_string($val) && preg_match('~^https?://~',$val)) {
                $url=$val;
              }
              if ($id) { $url = wp_get_attachment_image_url($id, 'large') ?: $url;
                        $set = wp_get_attachment_image_srcset($id, 'full')  ?: ''; }
              return ['url'=>$url,'srcset'=>$set];
            };

            $desk = $resolve($desk_raw);
            $mob  = $resolve($mob_raw);

            // Fallbacks (featured, puis asset)
            $feat_id = get_post_thumbnail_id($home_id);
            if ((!$desk['url'] && !$desk['srcset']) && $feat_id) {
              $desk = [
                'url'    => wp_get_attachment_image_url($feat_id, 'large'),
                'srcset' => wp_get_attachment_image_srcset($feat_id, 'full'),
              ];
            }
            if ((!$mob['url'] && !$mob['srcset']) && $feat_id) {
              $mob = [
                'url'    => wp_get_attachment_image_url($feat_id, 'large'),
                'srcset' => wp_get_attachment_image_srcset($feat_id, 'full'),
              ];
            }

            $fallback = get_theme_file_uri('assets/img/hero-sample.jpg');
            $alt = get_the_title().' hero';

            ?>
            <picture>
              <?php if (!empty($desk['srcset'])): ?>
                <source media="(min-width: 768px)"
                        srcset="<?php echo esc_attr($desk['srcset']); ?>"
                        sizes="(min-width:1280px) 600px, (min-width:768px) 50vw">
              <?php endif; ?>
              <?php if (!empty($mob['srcset'])): ?>
                <source media="(max-width: 767px)"
                        srcset="<?php echo esc_attr($mob['srcset']); ?>"
                        sizes="100vw">
              <?php endif; ?>
              <img
                src="<?php echo esc_url($mob['url'] ?: $desk['url'] ?: $fallback); ?>"
                alt="<?php echo esc_attr($alt); ?>"
                class="absolute inset-0 h-full w-full object-cover"
                loading="eager" fetchpriority="high" decoding="async">
            </picture>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- Logos row -->
  <!-- <section class="bg-white py-8">
    <div class="kgh-container">
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-6 items-center justify-items-center">
        <div class="opacity-70">Logo 1</div>
        <div class="opacity-70">Logo 2</div>
        <div class="opacity-70">Logo 3</div>
        <div class="opacity-70">Logo 4</div>
        <div class="opacity-70">Logo 5</div>
      </div>
    </div>
  </section> -->

  <!-- Our Tours (home section) -->
  <section class="bg-white py-12 md:py-20">
    <div class="kgh-container">
      <header class="mb-6 md:mb-10">
        <h2 class="kgh-h1 text-black mb-2">Our tours</h2>
        <p class="kgh-subtle">Korean Food & Cultural Walking Tours</p>
      </header>

      <?php if ( post_type_exists('tour') ) : ?>
        <?php
          // 1) Récupère les tours sélectionnés sur la page d'accueil
          $home_id = get_the_ID(); // safe: au cas où la variable d'en haut ne serait plus dispo

          $raw = function_exists('SCF')
            ? SCF::get('home_featured_tours', $home_id)
            : get_post_meta($home_id, 'home_featured_tours', false);

          // 2) Normalisation -> [int,int,...]
          $sel_ids = [];
          if (is_array($raw)) {
            foreach ($raw as $it) {
              if (is_numeric($it)) {
                $sel_ids[] = (int)$it;
              } elseif ($it instanceof WP_Post) {
                $sel_ids[] = (int)$it->ID;
              } elseif (is_array($it) && isset($it['id'])) {
                $sel_ids[] = (int)$it['id'];
              }
            }
          } elseif (is_numeric($raw)) {
            $sel_ids[] = (int)$raw;
          }

          // Garde uniquement les publiés et uniques
          $sel_ids = array_values(array_unique(array_filter($sel_ids, function($id){
            return $id && get_post_status($id) === 'publish';
          })));

          // 3) Construis la requête
          if (!empty($sel_ids)) {
            // Utilise l'ordre défini dans SCF (drag & drop)
            $q = new WP_Query([
              'post_type'      => 'tour',
              'post__in'       => $sel_ids,
              'orderby'        => 'post__in',
              'posts_per_page' => count($sel_ids),
              'no_found_rows'  => true,
            ]);
          } else {
            // Fallback : derniers tours
            $q = new WP_Query([
              'post_type'      => 'tour',
              'posts_per_page' => 6,
              'post_status'    => 'publish',
              'orderby'        => 'date',
              'order'          => 'DESC',
              'no_found_rows'  => true,
            ]);
          }
        ?>

        <?php if ( $q->have_posts() ) : ?>
          <div class="relative">
            <!-- Fade gauche -->
            <!-- <span id="kgh-fade-left"
            class="kgh-fade absolute left-0 top-0 h-full w-8 bg-gradient-to-r from-white to-transparent opacity-0 md:block"></span> -->

            <div id="home-tours"
                class="flex gap-6 md:gap-8 overflow-x-auto overflow-y-hidden no-scrollbar snap-x snap-mandatory scroll-smooth px-1">
              <?php while ( $q->have_posts() ) : $q->the_post(); ?>
                <article class="kgh-card">
                  <!-- image -->
                  <a href="<?php the_permalink(); ?>" class="block">
                    <div class="kgh-card-media">
                      <?php if ( has_post_thumbnail() ) {
                        the_post_thumbnail('large', ['class'=>'absolute inset-0 h-full w-full object-cover']);
                      } ?>

                      <?php
                      $area = function_exists('SCF')
                        ? SCF::get('area_label', get_the_ID())
                        : get_post_meta(get_the_ID(), 'area_label', true);

                      /** Always get badges as an array */
                      if (function_exists('SCF')) {
                        $badge_raw = (array) SCF::get('badge_tags', get_the_ID());
                      } else {
                        // IMPORTANT: third arg = false => return all meta values as array
                        $badge_raw = (array) get_post_meta(get_the_ID(), 'badge_tags', false);
                      }

                      /** Normalise to [{slug,label}, ...] */
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
                          if ($slug !== '') {
                            $badges[] = ['slug' => $slug, 'label' => $label];
                          }
                        }
                      }
                      // limit to 3 if you want
                      $badges = array_slice($badges, 0, 3);
                      ?>

                      <?php if (!empty($area)) : ?>
                        <div class="kgh-label-tl">
                          <span class="kgh-badge kgh-badge--light">
                            <span class="kgh-badge-ico" aria-hidden="true"><?php echo kgh_icon('icon-map-pin'); ?></span>
                            <span><?php echo esc_html($area); ?></span>
                          </span>
                        </div>
                      <?php endif; ?>

                      <?php if (!empty($badges)) : ?>
                        <div class="kgh-label-bl">
                          <?php foreach ($badges as $it): ?>
                            <span class="kgh-badge kgh-badge--dark">
                              <?php $ico = kgh_badge_icon($it['slug']); if ($ico): ?>
                                <span class="kgh-badge-ico" aria-hidden="true"><?php echo $ico; ?></span>
                              <?php endif; ?>
                              <span><?php echo esc_html($it['label']); ?></span>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </a>

                  <!-- body -->
                  <div class="kgh-card-body">
                    <h3 class="kgh-card-title">
                      <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    <?php
                      // Extrait court contrôlé côté PHP pour que le lien soit toujours visible
                      $raw = get_the_excerpt();
                      // 22 mots → ajuste à 20–26 selon ta hauteur de carte
                      $short = wp_trim_words( wp_strip_all_tags( $raw ), 22, '' );
                    ?>
                    <p class="kgh-card-ex-inline">
                      <?php echo esc_html( $short ); ?>&nbsp;
                      <a href="<?php the_permalink(); ?>" class="kgh-card-more" aria-label="<?php echo esc_attr( get_the_title() . ' – View more' ); ?>">
                        View more
                      </a>
                    </p>
                  </div>

                  <!-- footer (price + chips) -->
                  <?php
                  $price_cents   = get_post_meta(get_the_ID(), 'price_usd_cents', true);
                  $price_cents   = is_numeric($price_cents) ? (int)$price_cents : 0;
                  $price_dollars = $price_cents > 0 ? number_format($price_cents / 100, 0) : null;
                  $duration  = function_exists('SCF') ? SCF::get('duration',  get_the_ID()) : get_post_meta(get_the_ID(), 'duration', true);
                  $capacity  = function_exists('SCF') ? SCF::get('capacity',  get_the_ID()) : get_post_meta(get_the_ID(), 'capacity', true);
                  $languages = function_exists('SCF') ? SCF::get('languages', get_the_ID()) : get_post_meta(get_the_ID(), 'languages', true);
                  ?>
                  <div class="kgh-meta kgh-meta-row">
                    <?php if ($duration): ?>
                      <span class="kgh-meta-item" title="<?php echo esc_attr($duration); ?>">
                        <span class="kgh-ico" aria-hidden="true">
                          <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-clock'); ?></span>
                        </span>
                        <span><?php echo esc_html($duration); ?></span>
                      </span>
                    <?php endif; ?>
                    <?php if ($capacity): ?>
                      <span class="kgh-meta-item" title="<?php echo esc_attr($capacity); ?>">
                        <span class="kgh-ico" aria-hidden="true">
                          <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-users'); ?></span>
                        </span>
                        <span><?php echo esc_html($capacity); ?></span>
                      </span>
                    <?php endif; ?>
                    <?php if ($languages): ?>
                      <span class="kgh-meta-item" title="<?php echo esc_attr($languages); ?>">
                        <span class="kgh-ico" aria-hidden="true">
                          <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-globe'); ?></span>
                        </span>
                        <span class="truncate max-w-[9rem] sm:max-w-[12rem]"><?php echo esc_html($languages); ?></span>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="kgh-footer">
                    <span class="kgh-price">
                      <?php echo $price_dollars ? esc_html__('from', 'kgh') . ' $' . esc_html($price_dollars) : esc_html__('Price on request', 'kgh'); ?>
                    </span>
                    <a href="<?php the_permalink(); ?>" class="kgh-card-cta kgh-btn--tertiary">View Details
                        <span class="kgh-ico" aria-hidden="true"><?php echo kgh_icon('icon-right'); ?></span>
                    </a>
                  </div>

                </article>
              <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <!-- Fade droit -->
            <span id="kgh-fade-right"
                  class="kgh-fade absolute right-0 top-0 h-full w-8 bg-gradient-to-l from-white to-transparent opacity-100 md:block"></span>
          </div>

          <div class="mt-8 flex items-center justify-between">
            <a href="<?php echo esc_url( home_url('/tours/') ); ?>" class="kgh-btn--quaternary hover:no-underline">
              View all tours
            </a>

            <div class="flex items-center gap-4">
              <button type="button" id="home-tours-prev" aria-label="Previous" class="kgh-btn--quaternary">
                ‹
              </button>
              <button type="button" id="home-tours-next" aria-label="Next" class="kgh-btn--quaternary">
                ›
              </button>
            </div>
          </div>
          <?php else: ?>
            <p class="text-gray-600">No tours yet.</p>
          <?php endif; ?>
        <?php else: ?>
        <p class="text-gray-600">Tours content type is not active right now.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- Home • Value props row -->
  <section class="bg-white pt-20 pb-32">
    <div class="kgh-container">
      <!-- Headline centered -->
      <p class="text-center text-lg max-w-3xl mx-auto mb-8 md:mb-12">
        Authentic Korean food tours in Seoul:</br>hidden gems, small groups, and the stories behind every bite.
      </p>

      <!-- Pills: 1x4 desktop, stacked mobile -->
      <ul class="flex flex-wrap justify-center gap-4 md:gap-6">
        <!-- 1 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm">Free 48h Cancellation</span>
          </div>
        </li>

        <!-- 2 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm">All Diets Available</span>
          </div>
        </li>

        <!-- 3 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm">No Tourist Traps</span>
          </div>
        </li>

        <!-- 4 -->
        <li class="w-full max-w-[300px] md:max-w-[225px]">
          <div class="flex items-center gap-3 md:justify-center md:text-center rounded-2xl bg-[#FFF7F5] px-5 py-4">
            <span class="kgh-ico w-5 h-5 text-[#3B7D3B]" aria-hidden="true">
              <?php echo function_exists('kgh_icon') ? kgh_icon('icon-check-validate') : '✓'; ?>
            </span>
            <span class="text-black text-sm">All Tastings Included</span>
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
        <img
          src="<?php echo esc_url($blob); ?>"
          alt=""
          aria-hidden="true"
          class="pointer-events-none select-none absolute left-1/2 top-1/2 z-0 w-[520px] max-w-sm -translate-x-1/2 -translate-y-1/2 opacity-90 md:w-[576px] md:max-w-xl">

        <!-- Contenu au-dessus -->
        <div class="relative z-10 text-center">
          <p class="kgh-subtle text-black mb-6">Can’t find what you’re looking for?</p>
          <h2 class="kgh-h1 text-black mb-5">Private tour</h2>
          <p class="kgh-subtle mb-10">We can create a fully customized experience just for you!</p>

          <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="<?php echo esc_url( home_url('/contact/') ); ?>"
              class="kgh-btn--quaternary hover:no-underline">Contact Us</a>
            <a href="<?php echo esc_url( home_url('/contact/') ); ?>"
              class="kgh-btn--primary hover:no-underline">Request Custom Tour</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Home • Stats (4 cards) -->
  <section class="bg-white py-20 md:py-32">
    <div class="kgh-container">
      <ul class="flex flex-wrap justify-center gap-4 md:gap-6">
        <!-- 1 -->
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center gap-4 rounded-2xl bg-gray-100 px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-smiley') : ''; ?>
              </span>
            </span>
            <div class="min-w-0">
              <div class="text-2xl font-semibold text-black leading-tight">3000+</div>
              <div class="text-gray-700 text-sm">Happy Gourmet Hunters</div>
            </div>
          </div>
        </li>

        <!-- 2 -->
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center gap-4 rounded-2xl bg-gray-100 px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-compass') : ''; ?>
              </span>
            </span>
            <div class="min-w-0">
              <div class="text-2xl font-semibold text-black leading-tight">2000+</div>
              <div class="text-gray-700 text-sm">Tours since 2010</div>
            </div>
          </div>
        </li>

        <!-- 3 -->
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center gap-4 rounded-2xl bg-gray-100 px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-home') : ''; ?>
              </span>
            </span>
            <div class="min-w-0">
              <div class="text-2xl font-semibold text-black leading-tight">1.8K+</div>
              <div class="text-gray-700 text-sm">Addresses and Gems</div>
            </div>
          </div>
        </li>

        <!-- 4 -->
        <li class="w-full max-w-[300px] md:max-w-[260px]">
          <div class="flex items-center gap-4 rounded-2xl bg-gray-100 px-3 py-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white">
              <span class="kgh-ico w-6 h-6 text-black" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-smiley') : ''; ?>
              </span>
            </span>
            <div class="min-w-0">
              <div class="text-2xl font-semibold text-black leading-tight">6</div>
              <div class="text-gray-700 text-sm">Amazing Guides</div>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </section>

  <!-- Home • FAQ -->
  <section class="bg-white py-20 md:py-36">
    <div class="kgh-container">
      <div class="grid md:grid-cols-[auto_1fr_auto] items-start gap-6 md:gap-16">
        <!-- Title -->
        <h2 class="font-serif text-2xl md:text-3xl font-bold text-black md:pt-2 mx-14">FAQ:</h2>

        <!-- Accordion -->
        <div id="kgh-faq" class="w-full max-w-3xl mx-auto md:mx-0">
          <!-- Item -->
          <div class="border-t border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7">Can the weather cancel a tour?</span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              Tours run rain or shine; severe weather may reschedule. We'll contact you early with options or refunds.
            </div>
          </div>

          <!-- Item -->
          <div class="border-t border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7">Can we customize or book a private tour?</span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              Yes—private or custom routes available. Tell us interests, budget, and timing; we'll craft a tailored itinerary.
            </div>
          </div>

          <!-- Item -->
          <div class="border-t border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7">Is it suitable for dietary restrictions or allergies?</span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              Absolutely. We accommodate vegetarians, vegans, halal, gluten-free, and allergies. Just inform us early when booking.
            </div>
          </div>

          <!-- Item -->
          <div class="border-t border-b border-black/40">
            <button type="button" data-faq-toggle
              class="w-full flex items-center justify-between gap-4 py-3 md:py-4 text-left text-[15px] md:text-base leading-snug"
              aria-expanded="false">
              <span class="pr-7">What kind of places and food will we visit?</span>
              <span class="kgh-ico w-4 h-4 shrink-0 transition-transform" aria-hidden="true">
                <?php echo function_exists('kgh_icon') ? kgh_icon('icon-chevron-down') : '˅'; ?>
              </span>
            </button>
            <div data-faq-panel class="hidden pb-4 text-gray-700 text-sm md:text-base">
              Hidden local markets, family-run eateries, street stalls; regional specialties, seasonal dishes, and classic Korean favorites.
            </div>
          </div>
        </div>

        <!-- CTA -->
        <div class="md:sticky md:top-20">
          <a href="<?php echo esc_url( home_url('/contact/') ); ?>"
            class="kgh-btn--quaternary hover:no-underline whitespace-nowrap">
            Personal Request
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
  const scroller = document.getElementById('home-tours');
  const prev = document.getElementById('home-tours-prev');
  const next = document.getElementById('home-tours-next');
  const fadeL = document.getElementById('kgh-fade-left');
  const fadeR = document.getElementById('kgh-fade-right');
  if (!scroller) return;

  function cardStep(){
    const first = scroller.children[0];
    if (!first) return 320;
    const rect = first.getBoundingClientRect();
    const gap = parseInt(getComputedStyle(scroller).gap || '0', 10) || 0;
    return Math.round(rect.width + gap);
  }
  function scrollByCards(n){ scroller.scrollBy({ left: n * cardStep(), behavior: 'smooth' }); }

  if (prev && next){
    prev.addEventListener('click', () => scrollByCards(-1));
    next.addEventListener('click', () => scrollByCards(+1));
  }

  function setDisabled(btn, disabled){
    if (!btn) return;
    btn.disabled = !!disabled;
    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }
  function setFade(el, visible){
    if (!el) return;
    el.style.opacity = visible ? '1' : '0';
  }

  function updateArrows(){
    const max = Math.max(0, scroller.scrollWidth - scroller.clientWidth - 1);
    const atStart = scroller.scrollLeft <= 1;      // tolérance 1px
    const atEnd   = scroller.scrollLeft >= max;

    // boutons (si présents)
    setDisabled(prev, atStart);
    setDisabled(next, atEnd);

    // fades
    setFade(fadeL, !atStart);
    setFade(fadeR, !atEnd);
  }

  // s’assurer d’un état correct aux différents moments
  scroller.addEventListener('scroll', updateArrows, { passive: true });
  window.addEventListener('resize', () => requestAnimationFrame(updateArrows));
  window.addEventListener('load', updateArrows);
  requestAnimationFrame(updateArrows);
})();

</script>


<?php get_footer();
