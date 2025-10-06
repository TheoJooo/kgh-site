<?php
/** Blog index (Posts page) — matches the mockup */
if (!defined('ABSPATH')) exit;

get_header();

// Page des articles + SCF (titre/intro éditables)
$posts_page_id = (int) get_option('page_for_posts');

$hero_title = 'Korean Food, Culture & Travel';
$hero_intro = '';

if ($posts_page_id) {
  // 1) base = titre de la page (ex: "Blog")
  $hero_title = get_the_title($posts_page_id) ?: $hero_title;

  // 2) SCF
  if (function_exists('SCF')) {
    $scf_title = trim((string) SCF::get('blog_hero_title', $posts_page_id));
    $scf_intro = trim((string) SCF::get('blog_hero_intro', $posts_page_id));
    if ($scf_title !== '') $hero_title = $scf_title;
    if ($scf_intro !== '') $hero_intro = wp_kses_post( wpautop($scf_intro) );
  }

  // 3) Fallback meta direct (au cas où SCF::get() ne renvoie rien)
  if ($hero_title === '' || $hero_title === 'Blog' || $hero_title === 'Korean Food, Culture & Travel') {
    $meta_title = trim((string) get_post_meta($posts_page_id, 'blog_hero_title', true));
    if ($meta_title !== '') $hero_title = $meta_title;
  }
  if ($hero_intro === '') {
    $meta_intro = trim((string) get_post_meta($posts_page_id, 'blog_hero_intro', true));
    if ($meta_intro !== '') $hero_intro = wp_kses_post( wpautop($meta_intro) );
  }
}

// URL archive “tours” pour le bouton gauche
$tours_url = get_post_type_archive_link('tour') ?: home_url('/tours/');
?>


<main class="bg-white">
  <!-- Hero -->
  <section class="flex flex-col items-center bg-kgh-bg py-14 md:py-20 px-14 md:px-20 text-center">
    <h1 class="kgh-h1"><?php echo esc_html($hero_title); ?></h1>
    <?php if ($hero_intro): ?>
      <div class="kgh-subtle mt-6 max-w-xl"><?php echo $hero_intro; ?></div>
    <?php else: ?>
      <p class="kgh-subtle mt-6 max-w-xl">
        Discover Korean food, culture, and travel with stories from markets to hidden eateries.
        Tips and guides to help you taste and explore Seoul.
      </p>
    <?php endif; ?>
  </section>

  <section class="kgh-container py-12 md:py-20">
        <!-- Section title -->
        <h2 class="font-serif text-2xl md:text-3xl text-black mb-6">Articles</h2>

        <?php if (have_posts()): ?>
        <!-- Grid 2 colonnes -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
            <?php while (have_posts()): the_post(); ?>
            <article class="rounded-sm border-2 border-[#CAC8C8] bg-white hover:bg-kgh-redclayLight hover:border-kgh-sesameDark hover:shadow-[4px_2px_4px_rgba(0,0,0,0.25)]">
                <a href="<?php the_permalink(); ?>" class="block p-5 hover:no-underline">
                    <div class="grid grid-cols-[96px_1fr] md:grid-cols-[112px_1fr] gap-4 md:gap-5 items-start">
                    <div class="relative w-[96px] md:w-[112px] aspect-square rounded-sm overflow-hidden bg-gray-100 border-2 border-kgh-grey">
                        <?php echo kgh_post_list_thumb(); ?>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-serif text-xl text-black leading-tight mb-1"><?php the_title(); ?></h3>
                        <div class="text-xs text-gray-600 mb-2"><?php echo get_the_date(); ?></div>
                        <p class="text-gray-700 text-sm leading-relaxed line-clamp-3">
                        <?php echo esc_html( get_the_excerpt() ); ?>
                        </p>
                    </div>
                    </div>
                </a>
            </article>
            <?php endwhile; ?>
        </div>

        <!-- Footer CTA row -->
        <div class="mt-10 md:mt-12 flex items-center justify-between">
            <a href="<?php echo esc_url($tours_url); ?>" class="kgh-btn--ghost">
                Check our tours
            </a>

            <?php if (get_previous_posts_link() || get_next_posts_link()): ?>
            <nav class="kgh-pagination" aria-label="Blog pagination">
                <?php
                echo paginate_links([
                    'mid_size'  => 1,
                    'prev_text' => '← Previous page',
                    'next_text' => 'Next page →',
                    'type'      => 'list', // outputs <ul class="page-numbers">…</ul>
                ]);
                ?>
            </nav>
            <?php else: ?>
            <span class="text-sm text-gray-500">More articles coming soon</span>
            <?php endif; ?>

        </div>


    <?php else: ?>
        <p class="kgh-subtle">No posts yet.</p>
        <div class="mt-8">
        <a href="<?php echo esc_url($tours_url); ?>" class="kgh-btn--ghost">Check our tours</a>
        </div>
    <?php endif; ?>
  </section>
  
</main>

<?php get_footer(); ?>
