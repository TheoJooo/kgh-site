<?php
/**
 * Theme header
 * Location: wp-content/themes/kgh-theme/header.php
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>   <!-- INDISPENSABLE -->
</head>
<body <?php body_class(); ?>>

<?php wp_body_open(); ?>

<?php
  // URLs utiles
  $tours_url = get_post_type_archive_link('tour') ?: home_url('/tours/');
  // états actifs simples
  $is_home  = is_front_page();
  $is_tours = is_post_type_archive('tour') || get_post_type() === 'tour';
?>

<!-- Skip link pour accessibilité -->
<!-- <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 bg-black text-white px-3 py-2 rounded">
  Skip to content
</a> -->

<header class="bg-white sticky top-0 z-40 border-b-2 border-kgh-red">
  <div class="kgh-container">
    <div class="flex items-center justify-between py-3">
      <!-- Logo -->
      <a href="<?php echo esc_url( home_url('/') ); ?>" class="inline-flex items-center gap-2 hover:opacity-90">
        <?php if ( function_exists('the_custom_logo') && has_custom_logo() ) {
          the_custom_logo();
        } else { ?>
          <img
            src="<?php echo esc_url( get_theme_file_uri('assets/img/logo-red.png') ); ?>"
            alt="<?php echo esc_attr( get_bloginfo('name') ); ?>"
            class="h-7 md:h-9 w-auto">
        <?php } ?>
      </a>

      <!-- Desktop nav -->
      <nav class="hidden md:flex items-center gap-10">
        <a href="<?php echo esc_url( home_url('/') ); ?>"
          class="inline-flex items-center text-[15px] font-semibold border-b-2
                  <?php echo $is_home
                    ? 'text-kgh-red border-kgh-red'
                    : 'text-gray-900 border-transparent hover:border-kgh-red hover:no-underline'; ?>">
          Home
        </a>

        <a href="<?php echo esc_url( $tours_url ); ?>"
          class="inline-flex items-center text-[15px] font-semibold border-b-2
                  <?php echo $is_tours
                    ? 'text-kgh-red border-kgh-red'
                    : 'text-gray-900 border-transparent hover:border-kgh-red hover:no-underline'; ?>">
          Our Tours
        </a>

        <!-- Désactivés (gris, non cliquables) -->
        <span class="text-[15px] font-semibold text-gray-400 cursor-not-allowed select-none" aria-disabled="true">About Us</span>
        <span class="text-[15px] font-semibold text-gray-400 cursor-not-allowed select-none" aria-disabled="true">Blog</span>
      </nav>

      <!-- CTA + burger -->
      <div class="flex items-center gap-4">
        <a href="<?php echo esc_url( $tours_url ); ?>"
           class="kgh-btn--ghost border-2 border-kgh-red text-kgh-red hover:bg-kgh-red hover:text-white hidden md:inline-block">
          Book a tour
        </a>

        <!-- Burger (mobile) -->
        <button id="kgh-burger" data-kgh-open
                class="md:hidden inline-flex h-10 w-10 items-center justify-center rounded hover:bg-black/5"
                aria-label="Open menu" aria-controls="kgh-mobile-overlay" aria-expanded="false">
          <!-- Icône "menu" -->
          <svg class="kgh-ico w-6 h-6 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" data-icon="menu">
            <line x1="3" y1="6"  x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>

          <!-- Icône "close" (cachée au départ) -->
          <svg class="kgh-ico w-6 h-6 text-black hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" data-icon="close">
            <line x1="18" y1="6"  x2="6"  y2="18"></line>
            <line x1="6"  y1="6"  x2="18" y2="18"></line>
          </svg>
        </button>

      </div>
    </div>
  </div>

  <!-- Mobile overlay menu -->
  <div id="kgh-mobile-overlay" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <!-- Backdrop -->
    <button class="absolute inset-0 bg-black/40" data-kgh-close aria-label="Close menu"></button>

    <!-- Panel -->
    <div class="absolute inset-y-0 left-0 right-0 bg-kgh-red text-white
                translate-y-[-6%] opacity-0 transition-all duration-200
                data-[open=true]:translate-y-0 data-[open=true]:opacity-100">
      <!-- Top bar overlay -->
      <div class="kgh-container py-4 flex items-center justify-between">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center">
          <img src="<?php echo esc_url( get_theme_file_uri('assets/img/logo-white.png') ); ?>"
               alt="<?php echo esc_attr(get_bloginfo('name')); ?>" class="h-7 w-auto">
        </a>
        <button class="inline-flex h-10 w-10 items-center justify-center rounded hover:bg-white/10"
                data-kgh-close aria-label="Close menu">
          <span class="block h-[2px] w-6 bg-white rotate-45 translate-y-[1px]"></span>
          <span class="block h-[2px] w-6 bg-white -rotate-45 -translate-y-[1px] -ml-6"></span>
        </button>
      </div>

      <!-- Links -->
      <nav class="kgh-container">
        <ul class="flex flex-col items-center gap-8 py-6 text-lg font-semibold">
          <li><a class="hover:opacity-90" href="<?php echo esc_url(home_url('/')); ?>">Home</a></li>
          <li><a class="hover:opacity-90" href="<?php echo esc_url($tours_url); ?>">Our Tours</a></li>
          <li><span class="text-white/50 cursor-not-allowed" aria-disabled="true">About Us</span></li>
          <li><span class="text-white/50 cursor-not-allowed" aria-disabled="true">Blog</span></li>
          <li><a class="hover:opacity-90" href="<?php echo esc_url( home_url('/contact/') ); ?>">Contact Us</a></li>
        </ul>

        <div class="px-4 pb-8">
          <a href="<?php echo esc_url($tours_url); ?>"
             class="block w-full text-center kgh-btn--primary bg-white text-kgh-red hover:brightness-95">
            Book a tour
          </a>
        </div>
      </nav>
    </div>
  </div>
</header>

<script>
/* === Mobile menu toggle (overlay) === */
document.addEventListener('DOMContentLoaded', () => {
  const openBtn  = document.querySelector('[data-kgh-open]');
  const overlay  = document.getElementById('kgh-mobile-overlay');
  if (!openBtn || !overlay) return;

  const body  = document.body;
  const panel = overlay.querySelector('[class*="bg-kgh-red"]');
  const closeEls = overlay.querySelectorAll('[data-kgh-close]');
  const icoMenu  = openBtn.querySelector('[data-icon="menu"]');
  const icoClose = openBtn.querySelector('[data-icon="close"]');

  function swapIcons(isOpen){
    if (!icoMenu || !icoClose) return;
    if (isOpen) {
      icoMenu.classList.add('hidden');
      icoClose.classList.remove('hidden');
      openBtn.setAttribute('aria-label', 'Close menu');
    } else {
      icoClose.classList.add('hidden');
      icoMenu.classList.remove('hidden');
      openBtn.setAttribute('aria-label', 'Open menu');
    }
  }

  function openMenu(){
    overlay.classList.remove('hidden');
    overlay.setAttribute('aria-hidden', 'false');
    panel && panel.setAttribute('data-open', 'true');
    body.style.overflow = 'hidden';
    swapIcons(true);
    openBtn.setAttribute('aria-expanded', 'true');
    const first = overlay.querySelector('a,button,[tabindex]:not([tabindex="-1"])');
    first && first.focus();
  }
  function closeMenu(){
    panel && panel.setAttribute('data-open', 'false');
    overlay.setAttribute('aria-hidden', 'true');
    body.style.overflow = '';
    setTimeout(()=> overlay.classList.add('hidden'), 180);
    swapIcons(false);
    openBtn.setAttribute('aria-expanded', 'false');
    openBtn.focus();
  }

  openBtn.addEventListener('click', () => {
    // si déjà ouvert, ferme ; sinon ouvre
    const isOpen = !overlay.classList.contains('hidden');
    isOpen ? closeMenu() : openMenu();
  });
  closeEls.forEach(el => el.addEventListener('click', closeMenu));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeMenu();
  });
});

</script>

