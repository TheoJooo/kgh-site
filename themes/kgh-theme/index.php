<?php
/**
 * KGH Theme — index.php minimal
 * Ce fichier s'affiche tant qu'on n'a pas encore créé de templates spécifiques.
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
  <style>
    /* petit hero de test pour valider la charte */
    .kgh-hero {
      padding: 56px 24px;
      background: var(--kgh-porcelain);
      border-bottom: 4px solid var(--kgh-red-clay);
      text-align: center;
    }
    .kgh-hero h1 {
      margin: 0 0 8px;
      font-family: var(--kgh-serif-en), var(--kgh-serif-kr);
      font-weight: 700;
      color: var(--kgh-red-clay);
      letter-spacing: 0.2px;
    }
    .kgh-hero p {
      margin: 0;
      font-family: var(--kgh-sans-en);
      color: var(--kgh-grey);
      opacity: 0.9;
    }
    .kgh-container {
      max-width: 1100px;
      margin: 40px auto;
      padding: 0 20px;
    }
    .kgh-card {
      background: #fff;
      border: 1px solid #eee;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.04);
    }
    .kgh-card h2 {
      margin: 0 0 10px;
      font-family: var(--kgh-serif-en), var(--kgh-serif-kr);
      color: var(--kgh-grey);
    }
  </style>
</head>
<body <?php body_class(); ?>>

  <section class="kgh-hero">
    <h1>Korean Gourmet Hunters — Thème activé</h1>
    <p>Charte chargée (Red Clay / Sesame Grey / Porcelain White + typos). Prochaine étape : templates tours.</p>
  </section>

  <main class="kgh-container" role="main">
    <?php if ( have_posts() ) : ?>
      <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class('kgh-card'); ?>>
          <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <div><?php the_excerpt(); ?></div>
        </article>
      <?php endwhile; ?>

      <div class="kgh-card">
        <div class="nav-links">
          <div class="nav-previous"><?php next_posts_link('&larr; Articles plus anciens'); ?></div>
          <div class="nav-next"><?php previous_posts_link('Articles plus récents &rarr;'); ?></div>
        </div>
      </div>

    <?php else : ?>
      <article class="kgh-card">
        <h2>Aucun contenu pour l’instant</h2>
        <p>Ajoutez une page ou un article pour tester l’affichage.</p>
      </article>
    <?php endif; ?>
  </main>

  <?php wp_footer(); ?>
</body>
</html>
