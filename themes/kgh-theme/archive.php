<?php
/** Generic archives (category, tag, author, date) */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="kgh-container py-10 md:py-16">
  <header class="mb-8 md:mb-10">
    <h1 class="kgh-h1"><?php the_archive_title(); ?></h1>
    <?php if ( get_the_archive_description() ): ?>
      <p class="kgh-subtle mt-3"><?php the_archive_description(); ?></p>
    <?php endif; ?>
  </header>

  <?php if (have_posts()): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
      <?php while (have_posts()): the_post(); ?>
        <article class="rounded-sm overflow-hidden border-2 border-[#131313] shadow-[4px_2px_4px_rgba(0,0,0,0.25)] bg-white flex flex-col">
          <a href="<?php the_permalink(); ?>" class="block relative aspect-[16/9] bg-gray-100">
            <?php if (has_post_thumbnail()): ?>
              <?php the_post_thumbnail('kgh-post', ['class'=>'absolute inset-0 w-full h-full object-cover']); ?>
            <?php endif; ?>
          </a>
          <div class="px-6 py-5 grow">
            <h2 class="font-serif text-xl text-black leading-tight mb-2">
              <a href="<?php the_permalink(); ?>" class="hover:underline underline-offset-2"><?php the_title(); ?></a>
            </h2>
            <div class="text-xs text-gray-600 mb-3"><?php echo get_the_date(); ?></div>
            <p class="text-gray-700 text-sm leading-relaxed line-clamp-3"><?php echo get_the_excerpt(); ?></p>
          </div>
          <div class="px-6 pb-6">
            <a href="<?php the_permalink(); ?>" class="kgh-btn--ghost"><?php esc_html_e('Read more', 'kgh-theme'); ?></a>
          </div>
        </article>
      <?php endwhile; ?>
    </div>

    <nav class="mt-10 flex items-center justify-between" aria-label="<?php echo esc_attr__('Blog pagination', 'kgh-theme'); ?>">
      <div><?php previous_posts_link( __('← Newer', 'kgh-theme') ); ?></div>
      <div><?php next_posts_link( __('Older →', 'kgh-theme') ); ?></div>
    </nav>
  <?php else: ?>
    <p><?php esc_html_e('No posts found.', 'kgh-theme'); ?></p>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
