<?php
/**
 * Template Name: About Us
 * Description: Static About page matching the mockup (no logos).
 */
if (!defined('ABSPATH')) exit;

get_header();
?>

<main class="bg-white">

  <!-- HERO -->
  <section class="bg-kgh-bg">
    <div class="kgh-container text-center py-14 md:py-20">
      <h1 class="kgh-h1 mb-6">About Us</h1>

      <p class="kgh-subtle max-w-3xl mx-auto leading-relaxed">
        We are a team of <strong>passionate experts</strong>, both <strong>Korean and
        international</strong>, working closely with <strong>Masters artisans</strong>
        dedicated to sharing the best of <strong>Korea’s Wellness</strong> and
        <strong> Culinary traditions</strong>.
      </p>

      <!-- (logos intentionally removed as requested) -->
    </div>
  </section>

  <!-- OUR MISSION -->
  <section class="kgh-container py-12 md:py-16">
    <h2 class="font-serif text-2xl md:text-3xl text-black mb-8">Our Mission</h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
      <!-- Card 1 -->
      <article class="rounded-md border-2 border-[#CAC8C8] bg-[#EBEBEB] p-6">
        <div class="mb-4">
          <span class="inline-grid place-items-center w-10 h-10 rounded-full border-2 border-kgh-grey">
            <?php echo kgh_icon('icon-check-validate'); ?>
          </span>
        </div>
        <h3 class="font-serif text-xl text-black leading-tight mb-2">
          Pass down<br>our Ancestors’s traditions
        </h3>
        <p class="kgh-subtle text-sm leading-relaxed">
          In Korea, traditions are carefully passed down through generations, preserved by renowned
          Masters. Experiencing them allows you to connect with the country’s rich cultural legacy
          through the guidance of these expert artisans.
        </p>
      </article>

      <!-- Card 2 -->
      <article class="rounded-md border-2 border-[#CAC8C8] bg-[#EBEBEB] p-6">
        <div class="mb-4">
            <span class="inline-grid place-items-center w-10 h-10 rounded-full border-2 border-kgh-grey">
                <?php echo kgh_icon('icon-check-validate'); ?>
            </span>
        </div>
        <h3 class="font-serif text-xl text-black leading-tight mb-2">
          Promote Korean Wellness
        </h3>
        <p class="kgh-subtle text-sm leading-relaxed">
          Wellness practices are deeply rooted in tradition. By sharing these rituals, we aim to
          promote Korean wellness, helping visitors experience balance, health, and harmony in a
          truly authentic way.
        </p>
      </article>

      <!-- Card 3 -->
      <article class="rounded-md border-2 border-[#CAC8C8] bg-[#EBEBEB] p-6">
        <div class="mb-4">
            <span class="inline-grid place-items-center w-10 h-10 rounded-full border-2 border-kgh-grey">
                <?php echo kgh_icon('icon-check-validate'); ?>
            </span>
        </div>
        <h3 class="font-serif text-xl text-black leading-tight mb-2">
          Give you<br>the best of K&ndash;Cuisine
        </h3>
        <p class="kgh-subtle text-sm leading-relaxed">
          We strive to bring you the best of the K-Cuisine, guided by craft Masters and food experts.
          Through our expertise, you can discover the depth, flavor, and energy of Korean cuisine.
        </p>
      </article>
    </div>
  </section>

  <!-- MEET OUR TEAM (from CPT: guide) -->
    <section class="kgh-container py-12 md:py-16">
    <h2 class="font-serif text-2xl md:text-3xl text-black mb-10">Meet Our Team</h2>

    <?php
    // Query all published guides
    $q = new WP_Query([
        'post_type'      => 'guide',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => ['menu_order' => 'ASC', 'title' => 'ASC'],
        'order'          => 'ASC',
    ]);


    // Petit util pour résoudre l'image (prend celui du theme si dispo)
    $resolve_img = function($raw){
        if (function_exists('kgh_resolve_image')) {
        $img = kgh_resolve_image($raw, 'thumbnail', 'thumbnail');
        return $img['url'] ?: '';
        }
        if (is_numeric($raw)) return wp_get_attachment_image_url((int)$raw, 'thumbnail') ?: '';
        if (is_array($raw)) {
        if (isset($raw['id'])) return wp_get_attachment_image_url((int)$raw['id'], 'thumbnail') ?: '';
        if (isset($raw[0]) && is_numeric($raw[0])) return wp_get_attachment_image_url((int)$raw[0], 'thumbnail') ?: '';
        if (!empty($raw['url'])) return (string)$raw['url'];
        }
        if (is_string($raw) && preg_match('~^https?://~', $raw)) return $raw;
        return '';
    };
    ?>

    <?php if ($q->have_posts()): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-14">
        <?php while ($q->have_posts()): $q->the_post(); ?>
            <?php
            $gid   = get_the_ID();
            $name  = get_the_title($gid);
            $sub   = function_exists('SCF') ? SCF::get('subtitle',       $gid) : get_post_meta($gid, 'subtitle', true);
            $desc  = function_exists('SCF') ? SCF::get('description',    $gid) : get_post_meta($gid, 'description', true);
            $lang  = function_exists('SCF') ? SCF::get('languages',      $gid) : get_post_meta($gid, 'languages', true);
            $photo = function_exists('SCF') ? SCF::get('profil_picture', $gid) : get_post_meta($gid, 'profil_picture', true);
            $img   = $resolve_img($photo);

            // Labels (repeat field "label" → array de textes)
            $labels_raw = function_exists('SCF') ? SCF::get('label', $gid) : get_post_meta($gid, 'label', false);
            $labels = [];
            if (is_array($labels_raw)) {
                foreach ($labels_raw as $row) {
                $val = is_array($row) && isset($row['label']) ? trim((string)$row['label']) : trim((string)$row);
                if ($val !== '') $labels[] = $val;
                }
            } elseif (is_string($labels_raw) && trim($labels_raw) !== '') {
                $labels[] = trim($labels_raw);
            }
            $labels = array_values(array_unique($labels));
            ?>

            <article class="rounded-md bg-white">
                <div class="grid grid-cols-[92px_1fr] gap-5 items-start">
                    <!-- Avatar slab (carré arrondi) -->
                    <div class="w-[92px] h-[124px] rounded-md bg-[#F2EDEA] grid place-items-center overflow-hidden">
                    <?php if ($img): ?>
                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($name); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="kgh-ico w-6 h-6 text-kgh-red">
                        <?php echo function_exists('kgh_icon') ? kgh_icon('icon-user') : '👤'; ?>
                        </span>
                    <?php endif; ?>
                    </div>

                    <!-- Titre + sous-titre + badges -->
                    <div class="min-w-0">
                    <h3 class="font-serif leading-tight text-black">
                        <?php echo esc_html($name); ?>
                    </h3>

                    <?php if (!empty($sub)): ?>
                        <div class="text-sm text-[#2F3B46] mt-1">
                        <?php echo esc_html($sub); ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php if (!empty($lang)): ?>
                        <span class="kgh-badge !bg-gray-100">Languages: <?php echo esc_html($lang); ?></span>
                        <?php endif; ?>
                        <?php foreach ($labels as $lab): ?>
                        <span class="kgh-badge !bg-gray-100"><?php echo esc_html($lab); ?></span>
                        <?php endforeach; ?>
                    </div>
                    </div>
                </div>

                <?php if (!empty($desc)): ?>
                    <p class="kgh-subtle text-sm leading-relaxed mt-4 md:mt-5">
                    <?php echo esc_html($desc); ?>
                    </p>
                <?php endif; ?>
            </article>

        <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php else: ?>
        <p class="kgh-subtle">Team info coming soon.</p>
    <?php endif; ?>
    </section>

    <!-- TESTIMONIALS -->
    <section class="bg-white py-12 md:py-20">
    <div class="kgh-container">
        <h2 class="font-serif text-2xl md:text-3xl text-black mb-6 md:mb-10">Testimonials</h2>

        <?php
        // Témoignages (CPT "testimonial")
        $tq = new WP_Query([
        'post_type'      => 'testimonial',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
        'order'          => 'ASC',
        ]);
        ?>

        <?php if ($tq->have_posts()): ?>
        <div class="kgh-testi-wrap">
          <div class="kgh-testi" data-kgh-testi>
            <div class="kgh-testi-viewport">
                <div class="kgh-testi-track">
                <?php while ($tq->have_posts()): $tq->the_post();
                    $tid     = get_the_ID();
                    $author  = get_the_title() ?: 'Anonymous';
                    $quote   = get_the_content(null, false) ?: '';
                ?>
                <?php
                    $country = function_exists('SCF') ? trim((string) SCF::get('t_author_country', $tid))
                                                    : trim((string) get_post_meta($tid, 't_author_country', true));
                    $tour    = function_exists('SCF') ? trim((string) SCF::get('t_tour_name', $tid))
                                                    : trim((string) get_post_meta($tid, 't_tour_name', true));
                    $metaInline = trim(implode(' · ', array_filter([$country ? "From $country" : '', $tour])));
                ?>

                <article class="kgh-testi-card">
                <div class="kgh-testi-body">
                    <?php if ($quote): ?>
                    <div class="prose max-w-none prose-p:my-2 text-sm">
                      <?php echo wpautop( wp_kses_post( $quote ) ); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <footer class="kgh-testi-footer">
                    <div class="min-w-0">
                        <div class="font-semibold text-sm leading-tight">
                            <?php echo esc_html($author); ?>
                            <?php if ($metaInline): ?>
                                <span class="text-sm font-normal text-gray-700"> · <?php echo esc_html($metaInline); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </footer>
                </article>

                <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </div>
          </div>
          <div class="kgh-testi-controls">
            <button type="button" class="kgh-carousel-btn kgh-ts-prev" data-kgh-ts-prev aria-label="Previous">‹</button>
            <button type="button" class="kgh-carousel-btn kgh-ts-next" data-kgh-ts-next aria-label="Next">›</button>
          </div>
        </div>

        <?php else: ?>
        <p class="kgh-subtle">Reviews coming soon.</p>
        <?php endif; ?>
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
      <div class="grid md:grid-cols-[auto_1fr_auto] items-center gap-6 md:gap-16">
        <!-- Title -->
        <h2 class="font-serif text-2xl md:text-3xl font-bold text-black md:pt-2 mx-14 text-center">FAQ:</h2>

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
              Tours run rain or shine! Don't forget your umbrella and enjoy Korea's rainy mood!
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
              Yes—private or custom routes available. Tell us interests, budget, and timing, we'll craft a tailored itinerary.
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

  <!-- Contact -->
  <section id="kgh-contact" class="mt-12 md:mt-16 scroll-mt-24">
    <?php
      get_template_part('template-parts/section', 'contact', [
        'title'    => 'Contact us',
        'services' => [
          'Private tour inquiry',
          'Group booking',
          'Custom request',
        ],
        // 'portrait_id' => 0, // optionnel : fixe un portrait spécifique
      ]);
    ?>
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

<?php get_footer(); ?>
