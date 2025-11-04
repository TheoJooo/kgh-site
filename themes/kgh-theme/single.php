<?php
/** Single post */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main class="kgh-container py-10 md:py-16 md:px-44">
  <?php if (have_posts()): while (have_posts()): the_post(); ?>
    <nav class="mb-6">
      <a href="<?php echo esc_url( get_permalink( get_option('page_for_posts') ) ); ?>" class="inline-flex items-center gap-2 text-sm hover:opacity-80">
        <span aria-hidden="true">←</span><span>Back to Blog</span>
      </a>
    </nav>

    <article>
      <header class="mb-6">
        <h1 class="kgh-h1"><?php the_title(); ?></h1>
        <div class="kgh-subtle mt-2 text-sm"><?php echo get_the_date(); ?> · <?php the_category(', '); ?></div>
      </header>

      <?php if (has_post_thumbnail()): ?>
        <figure class="relative aspect-[16/9] overflow-hidden rounded-sm border-2 border-[#131313] shadow-[4px_2px_4px_rgba(0,0,0,0.25)] bg-white mb-8">
          <?php the_post_thumbnail('kgh-post', ['class'=>'absolute inset-0 w-full h-full object-cover']); ?>
        </figure>
      <?php endif; ?>

      <div class="prose max-w-none prose-headings:font-serif prose-a:underline prose-img:rounded-sm prose-img:border-2 prose-img:border-[#131313]">
        <?php the_content(); ?>
      </div>

      <?php the_tags('<p class="mt-8 text-sm text-gray-700">Tags: ', ', ', '</p>'); ?>
    </article>

    <!-- Lire d'autres articles -->
    <section class="mt-12 md:mt-16">
    <h2 class="font-serif text-2xl md:text-3xl text-black mb-6"><?php echo esc_html__('Read more articles', 'kgh-theme'); ?></h2>

    <?php
    $current_id = get_the_ID();
    $need = 2;
    $picked = [];         // posts (WP_Post) collectés
    $exclude_ids = [$current_id];

    // 1) Même(s) catégorie(s)
    $cats = wp_get_post_categories($current_id);
    if (!empty($cats)) {
        $q1 = new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => $need,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'category__in'        => $cats,
        'post__not_in'        => $exclude_ids,
        ]);
        if ($q1->have_posts()) {
        foreach ($q1->posts as $p) { $picked[] = $p; $exclude_ids[] = $p->ID; }
        }
        wp_reset_postdata();
    }

    // 2) Compléter avec les plus récents (toutes catégories)
    if (count($picked) < $need) {
        $q2 = new WP_Query([
        'post_type'           => 'post',
        'posts_per_page'      => $need - count($picked),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'post__not_in'        => $exclude_ids,
        ]);
        if ($q2->have_posts()) {
        foreach ($q2->posts as $p) { $picked[] = $p; }
        }
        wp_reset_postdata();
    }

    if (!empty($picked)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
        <?php foreach ($picked as $post): setup_postdata($post); ?>
            <article class="rounded-sm border-2 border-[#CAC8C8] bg-white hover:bg-kgh-redclayLight hover:border-kgh-sesameDark hover:shadow-[4px_2px_4px_rgba(0,0,0,0.25)]">
            <a href="<?php the_permalink(); ?>" class="block p-5 hover:no-underline">
                <div class="grid grid-cols-[96px_1fr] gap-4 items-start">
                <div class="relative w-[96px] aspect-square rounded-sm overflow-hidden bg-gray-100 border-2 border-kgh-grey">
                    <?php if (function_exists('kgh_post_list_thumb')) { echo kgh_post_list_thumb();
                    } else { has_post_thumbnail() ? the_post_thumbnail('thumbnail', ['class'=>'w-full h-full object-cover','loading'=>'lazy']) : print '<div class="w-full h-full grid place-items-center text-xs text-gray-500">No image</div>'; } ?>
                </div>
                <div class="min-w-0">
                    <h3 class="font-serif text-lg text-black leading-tight mb-1"><?php the_title(); ?></h3>
                    <div class="text-xs text-gray-600 mb-2"><?php echo get_the_date(); ?></div>
                    <p class="text-gray-700 text-sm leading-relaxed line-clamp-3"><?php echo esc_html(get_the_excerpt()); ?></p>
                </div>
                </div>
            </a>
            </article>
        <?php endforeach; wp_reset_postdata(); ?>
        </div>
    <?php else: ?>
        <p class="kgh-subtle">No other articles yet.</p>
    <?php endif; ?>

    <!-- CTA vers les tours -->
    <div class="mt-8">
        <a href="<?php echo esc_url( get_post_type_archive_link('tour') ?: home_url('/tours/') ); ?>" class="kgh-btn--ghost">
        Check our tours
        </a>
    </div>
    </section>



  <?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>
