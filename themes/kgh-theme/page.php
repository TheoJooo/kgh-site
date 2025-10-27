<?php
/** Template: Page */
get_header();
?>
<main id="primary" class="site-main" style="max-width:980px;margin:40px auto;padding:0 16px;">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <?php
        // Hide the page H1 on checkout-related pages (checkout/success/cancel)
        $hide_title = false;
        if (is_page()) {
          $p = get_post();
          if ($p) {
            $slugs = [$p->post_name];
            $ancestors = get_post_ancestors($p);
            if (!empty($ancestors)) {
              foreach ($ancestors as $aid) { $an = get_post($aid); if ($an) { $slugs[] = $an->post_name; } }
            }
            if (in_array('checkout', $slugs, true)) {
              $hide_title = true;
            }
            $content = (string) $p->post_content;
            if (function_exists('has_shortcode')) {
              if (has_shortcode($content, 'kgh_checkout_success') || has_shortcode($content, 'kgh_checkout_cancel') || has_shortcode($content, 'kgh_checkout')) {
                $hide_title = true;
              }
            }
          }
        }
      ?>
      <header class="entry-header">
        <?php if (!$hide_title) the_title('<h1 class="entry-title">', '</h1>'); ?>
      </header>
      <div class="entry-content">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; else: ?>
    <p><?php esc_html_e('No content.', 'kgh-booking'); ?></p>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
