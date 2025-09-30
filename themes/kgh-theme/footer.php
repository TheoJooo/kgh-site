<?php
  if ( ! defined('ABSPATH') ) exit;
  $year = (string) date('Y');

  // Assets
  $logo_white = get_theme_file_uri('assets/img/logo-white.png');

  // Fallback links si aucun menu "footer"
  $privacy_url = function_exists('get_privacy_policy_url') && get_privacy_policy_url()
    ? get_privacy_policy_url()
    : home_url('/privacy-policy/');

  $terms_page   = get_page_by_path('terms') ?: get_page_by_path('terms-conditions') ?: get_page_by_path('terms-and-conditions');
  $terms_url    = $terms_page ? get_permalink($terms_page->ID) : home_url('/terms-and-conditions/');

  $cookies_page = get_page_by_path('cookie-policy') ?: get_page_by_path('cookies');
  $cookies_url  = $cookies_page ? get_permalink($cookies_page->ID) : home_url('/cookie-policy/');

  $contact_page = get_page_by_path('contact') ?: get_page_by_path('contact-us');
  $contact_url  = $contact_page ? get_permalink($contact_page->ID) : home_url('/contact/');
?>

<footer class="bg-[#2B2B2B] text-white">
  <div class="kgh-container py-8 md:pt-24 md:pb-12">
    <div class="grid gap-6 md:grid-cols-2 md:items-start">
      <!-- Left: Logo + copyright -->
      <div class="flex flex-col gap-3">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center gap-3">
          <?php if ($logo_white): ?>
            <img src="<?php echo esc_url($logo_white); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="h-8 w-auto" loading="lazy" decoding="async">
          <?php else: ?>
            <span class="font-serif text-xl font-bold"><?php bloginfo('name'); ?></span>
          <?php endif; ?>
        </a>
        <p class="text-xs text-white/80">
          Copyright <?php echo esc_html($year); ?> Korean Gourmet Hunters.
        </p>
      </div>

      <!-- Right: Links -->
      <nav class="md:justify-self-end md:self-end">
        <?php
        if (has_nav_menu('footer')) {
          wp_nav_menu([
            'theme_location' => 'footer',
            'container'      => false,
            'menu_class'     => 'flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80',
            'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
            'link_before'    => '',
            'link_after'     => '',
            'fallback_cb'    => false,
          ]);
        } else {
          // Fallback simple si aucun menu n'est affecté
          ?>
          <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-white/80">
            <li><a class="hover:text-white hover:underline underline-offset-4" href="<?php echo esc_url($privacy_url); ?>">Privacy Policy</a></li>
            <li class="text-kgh-grey">Terms &amp; Conditions</li>
            <li class="text-kgh-grey">Cookie Policy</li>
            <!-- <li><a class="hover:text-white hover:underline underline-offset-4" href="<?php echo esc_url($terms_url); ?>">Terms &amp; Conditions</a></li>
            <li><a class="hover:text-white hover:underline underline-offset-4" href="<?php echo esc_url($cookies_url); ?>">Cookie Policy</a></li> -->
            <li><a class="hover:text-white hover:underline underline-offset-4" href="<?php echo esc_url($contact_url); ?>">Contact</a></li>
          </ul>
          <?php
        }
        ?>
      </nav>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
