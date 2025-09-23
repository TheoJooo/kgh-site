<?php
/**
 * KGH Theme functions
 */

if (!defined('KGH_VERSION')) {
  define('KGH_VERSION', '0.1.0');
}

if (!defined('KGH_DIR')) {
  define('KGH_DIR', get_stylesheet_directory());
}

if (!defined('KGH_URI')) {
  define('KGH_URI', get_stylesheet_directory_uri());
}

/**
 * Enqueue styles & scripts
 */
function kgh_enqueue_assets() {
  // Google Fonts
  wp_enqueue_style(
    'kgh-google-fonts',
    'https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Noto+Sans:wght@300;400;700&family=Noto+Serif+KR:wght@300;400;700&display=swap',
    [],
    null
  );

  // style.css
  wp_enqueue_style(
    'kgh-style',
    get_stylesheet_uri(),
    ['kgh-google-fonts'],
    KGH_VERSION
  );

  // assets/css/main.css
  wp_enqueue_style(
    'kgh-main',
    KGH_URI . '/assets/css/main.css',
    ['kgh-style'],
    KGH_VERSION
  );

  // assets/js/main.js
  wp_enqueue_script(
    'kgh-main-js',
    KGH_URI . '/assets/js/main.js',
    [],
    KGH_VERSION,
    true
  );

  wp_localize_script('kgh-main-js', 'KGHBooking', [
    'restNonce' => wp_create_nonce('wp_rest'),
  ]);
}
add_action('wp_enqueue_scripts', 'kgh_enqueue_assets');


/**
 * Theme supports basiques
 */
function kgh_theme_setup() {
  add_theme_support('title-tag');        // <title> géré par WP
  add_theme_support('post-thumbnails');  // images à la une
  add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
}
add_action('after_setup_theme', 'kgh_theme_setup');

/**
 * (Optionnel) Menus – utile plus tard
 */
// function kgh_register_menus() {
//   register_nav_menus([
//     'primary' => __('Menu principal', 'kgh'),
//   ]);
// }
// add_action('after_setup_theme', 'kgh_register_menus');


// Shortcodes: [kgh_checkout_success] et [kgh_checkout_cancel]
add_shortcode('kgh_checkout_success', function () {
  $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
  ob_start(); ?>
  <div class="kgh-checkout kgh-success" style="max-width:700px;margin:40px auto;font-family:'Noto Sans',system-ui;">
    <h2 style="color:#661E11;margin:0 0 12px;">🎉 Thank you! Your payment was approved.</h2>
    <p>You’ll receive a confirmation email shortly.</p>
    <?php if ($token): ?><p style="opacity:.7">Ref: <?php echo esc_html($token); ?></p><?php endif; ?>
    <p><a href="<?php echo esc_url( home_url('/') ); ?>" style="color:#661E11;text-decoration:underline;">Back to Home</a></p>
  </div>
  <?php return ob_get_clean();
});

add_shortcode('kgh_checkout_cancel', function () {
  ob_start(); ?>
  <div class="kgh-checkout kgh-cancel" style="max-width:700px;margin:40px auto;font-family:'Noto Sans',system-ui;">
    <h2 style="color:#661E11;margin:0 0 12px;">Payment cancelled</h2>
    <p>No charge has been made. You can try again from your tour page.</p>
    <p><a href="<?php echo esc_url( home_url('/') ); ?>" style="color:#661E11;text-decoration:underline;">Back to Home</a></p>
  </div>
  <?php return ob_get_clean();
});
