<?php
/** Reusable contact section
 * Args (facultatifs) :
 * - title         string
 * - portrait_id   int   (ID media WP)
 * - services      array (libellés du select)
 */
if (!defined('ABSPATH')) exit;

$defaults = [
  'title'       => __('Contact us', 'kgh-theme'),
  'portrait_id' => function_exists('SCF') ? (int) SCF::get('contact_portrait', get_the_ID()) : 0,
  'services'    => [],
];
$args = wp_parse_args($args ?? [], $defaults);

/* Services :
 * 1/ si passés en $args -> priorité
 * 2/ sinon SCF texte "contact_services" (liste séparée par virgule)
 * 3/ sinon valeurs par défaut
 */
$services = $args['services'];
if (empty($services)) {
  $csv = function_exists('SCF') ? trim((string) SCF::get('contact_services', get_the_ID())) : '';
  if ($csv) $services = array_map('trim', explode(',', $csv));
  if (!$services) $services = [
    __('Private tour', 'kgh-theme'),
    __('Fixed tour', 'kgh-theme'),
    __('Cooking class', 'kgh-theme'),
    __('Corporate', 'kgh-theme'),
    __('Other', 'kgh-theme'),
  ];
}

$portrait_url = $args['portrait_id']
  ? wp_get_attachment_image_url($args['portrait_id'], 'large')
  : get_theme_file_uri('assets/img/contact-fallback.png');

$action  = esc_url( admin_url('admin-post.php') );
$success = isset($_GET['sent']) && $_GET['sent'] === '1';
?>

<section class="bg-kgh-bg py-12 md:py-20">
  <div class="kgh-container">
    <!-- 1 col mobile; dès md: 2 colonnes flex qui s'étirent à la même hauteur -->
    <div class="gap-12 md:gap-20 md:flex md:items-stretch">

      <!-- COLONNE IMAGE : même hauteur que le formulaire -->
        <figure
            class="mb-8 w-1/2 md:mb-0 md:self-stretch rounded-xs bg-[#EFECEA] overflow-hidden
            mx-auto md:mx-0 md:flex md:items-center md:justify-center">
        <?php if ($portrait_url): ?>
            <img
                src="<?php echo esc_url($portrait_url); ?>"
                alt=""
                class="block h-full w-full object-cover object-center" />
        <?php endif; ?>
      </figure>

      <!-- COLONNE FORMULAIRE -->
      <div class="w-full md:w-1/2 md:self-stretch">
        <h1 class="kgh-h1 text-black mb-6"><?php echo esc_html( $args['title'] ); ?></h1>

        <?php if (!empty($success)): ?>
          <p class="mb-6 text-green-700"><?php echo esc_html__("Thanks! Your message was sent. We’ll get back to you shortly.", 'kgh-theme'); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="kgh-contact-form space-y-4" novalidate>
          <?php wp_nonce_field('kgh_contact_send','kgh_contact_nonce'); ?>
          <input type="hidden" name="action" value="kgh_contact_send">
          <input type="hidden" name="redirect_to" value="<?php echo esc_url( add_query_arg(null,null) ); ?>">

          <div style="position:absolute;left:-10000px;top:auto;height:0;overflow:hidden;">
            <label><?php echo esc_html__('Leave this field empty', 'kgh-theme'); ?></label>
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </div>

          <div><label class="kgh-label"><?php echo esc_html__('Name', 'kgh-theme'); ?></label><input type="text" name="name" required class="kgh-field"></div>
          <div><label class="kgh-label"><?php echo esc_html__('Email', 'kgh-theme'); ?></label><input type="email" name="email" required class="kgh-field"></div>
          <div>
            <label class="kgh-label"><?php echo esc_html__('Service', 'kgh-theme'); ?></label>
            <select name="service" class="kgh-field">
              <option value=""><?php echo esc_html__('Select', 'kgh-theme'); ?></option>
              <option value="private_tour"><?php echo esc_html__('Private Tour', 'kgh-theme'); ?></option>
              <option value="fixed_tour"><?php echo esc_html__('Fixed Tour', 'kgh-theme'); ?></option>
              <option value="cooking_class"><?php echo esc_html__('Cooking Class', 'kgh-theme'); ?></option>
              <option value="corporate"><?php echo esc_html__('Corporate', 'kgh-theme'); ?></option>
              <option value="other"><?php echo esc_html__('Other', 'kgh-theme'); ?></option>
            </select>
          </div>
          <div><label class="kgh-label"><?php echo esc_html__('Message', 'kgh-theme'); ?></label><textarea name="message" rows="6" required class="kgh-field"></textarea></div>

          <div class="pt-2 flex justify-end">
            <button type="submit" class="kgh-btn--primary"><?php echo esc_html__('Send', 'kgh-theme'); ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
