<?php
/** 404 template */
if (!defined('ABSPATH')) exit;

// renvoie bien un statut 404 au navigateur/SEO
status_header(404);
nocache_headers();

get_header();

// URLs utiles
$home_url  = home_url('/');
$tours_url = get_post_type_archive_link('tour') ?: home_url('/tours/');
?>
<main class="bg-kgh-bg">
  <section class="kgh-container text-center py-16 md:py-24">
    <!-- Titres -->
    <h1 class="kgh-h1 mb-2">Ooops!</h1>
    <h2 class="kgh-h1"><?php echo esc_html__('Page not found', 'kgh'); ?></h2>

    <!-- Texte -->
    <div class="kgh-subtle max-w-3xl mx-auto mt-6 space-y-1">
      <p>It seems you’ve wandered off the food trail.</p>
      <p>Even the best explorers get a little lost without a guide.</p>
      <p>Don’t worry! We’ll help you find your way back to the tastiest experiences.</p>
    </div>

    <!-- CTA -->
    <div class="mt-10 flex items-center justify-center gap-4">
      <a href="<?php echo esc_url($home_url); ?>"
         class="kgh-btn--primary">
        Return to Home
      </a>
      <a href="<?php echo esc_url($tours_url); ?>"
         class="kgh-btn--ghost border-[#131313] text-black hover:no-underline">
        Check Our Tours
      </a>
    </div>

    <!-- (optionnel) une petite recherche si tu veux -->
    <?php /* get_search_form(); */ ?>
  </section>
</main>
<?php get_footer(); ?>
