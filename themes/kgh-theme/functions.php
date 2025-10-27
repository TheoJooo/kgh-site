<?php
/**
 * KGH Theme functions
 */

if (!defined('KGH_VERSION')) {
  define('KGH_VERSION', '0.1.1');
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

  // === Flatpickr (pour le calendrier) ===
  wp_enqueue_style(
    'flatpickr',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    [],
    '4.6.13'
  );
  wp_enqueue_script(
    'flatpickr',
    'https://cdn.jsdelivr.net/npm/flatpickr',
    [],
    '4.6.13',
    true
  );

  // assets/css/main.css
  wp_enqueue_style(
    'kgh-main',
    KGH_URI . '/assets/css/main.css',
    ['kgh-style'],
    KGH_VERSION
  );

  // assets/css/booking.css (UNIQUEMENT si booking activé)
  if (apply_filters('kgh_booking_enabled', false)) {
    wp_enqueue_style(
      'kgh-booking-ui',
      KGH_URI . '/assets/css/booking.css',
      ['kgh-main', 'flatpickr'],
      KGH_VERSION
    );
  }

  // assets/js/main.js
  wp_enqueue_script(
    'kgh-main-js',
    KGH_URI . '/assets/js/main.js',
    [],
    KGH_VERSION,
    true
  );

  // assets/css/app.css (généré par Tailwind)
  wp_enqueue_style(
    'kgh-app',
    KGH_URI . '/assets/css/app.css',
    ['kgh-style'],
    KGH_VERSION
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

// Success page: poll status endpoint and show recap
add_shortcode('kgh_checkout_success', function () {
  $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : (isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '');
  $strings = [
    'headingProcessing' => __( 'Thank you! Your payment is processing…', 'kgh-booking' ),
    'headingApproved'   => __( 'Thank you! Your payment was approved.', 'kgh-booking' ),
    'processing'        => __( 'Processing your payment… this page will update automatically.', 'kgh-booking' ),
    'missingOrder'   => __( 'Missing order reference.', 'kgh-booking' ),
    'stillProcessing'=> __( 'We are still finalizing your payment. If this persists, please contact us.', 'kgh-booking' ),
    'bookingTitle'   => __( 'Your booking', 'kgh-booking' ),
    'guests'         => __( 'Guests:', 'kgh-booking' ),
    'total'          => __( 'Total:', 'kgh-booking' ),
    'paid'           => __( 'Paid', 'kgh-booking' ),
    'backHome'       => __( 'Back to Home', 'kgh-booking' ),
  ];
  ob_start(); ?>
  <div class="kgh-outcome">
    <h2 id="kgh-succ-heading"><?php echo esc_html( $strings['headingProcessing'] ); ?></h2>
    <div id="kgh-succ-alert" class="kgh-status" role="status" aria-live="polite">
      <div class="notice notice-info"><p><?php echo esc_html( $strings['processing'] ); ?></p></div>
    </div>
    <div id="kgh-succ-recap" class="kgh-success-card" style="display:none;">
      <div id="kgh-succ-title" style="font-weight:700;margin-bottom:4px;">—</div>
      <div id="kgh-succ-when" style="opacity:.9;">—</div>
      <div id="kgh-succ-guests" style="opacity:.9;margin-top:6px;">—</div>
      <div id="kgh-succ-total" style="margin-top:8px;font-weight:700;">—</div>
      <div id="kgh-succ-status" style="margin-top:8px;color:#1a7a3a;"><?php echo esc_html( $strings['paid'] ); ?></div>
      <div style="margin-top:12px;"><a class="kgh-inline-link" href="<?php echo esc_url( home_url('/') ); ?>"><?php echo esc_html( $strings['backHome'] ); ?></a></div>
    </div>
  </div>
  <script>
    (async function(){
      const orderId = <?php echo wp_json_encode( $order_id ); ?>;
      const strings = <?php echo wp_json_encode( $strings ); ?>;
      const alertBox = document.getElementById('kgh-succ-alert');
      const headingEl = document.getElementById('kgh-succ-heading');
      const recap = document.getElementById('kgh-succ-recap');
      const titleEl = document.getElementById('kgh-succ-title');
      const whenEl = document.getElementById('kgh-succ-when');
      const guestsEl = document.getElementById('kgh-succ-guests');
      const totalEl = document.getElementById('kgh-succ-total');

      if (orderId) {
        try {
          await fetch('/wp-json/kgh/v1/paypal/capture', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ order_id: orderId })
          });
        } catch(e){}
      }
      function formatTime(iso){
        const m = iso && iso.match(/T(\d{2}):(\d{2}):/);
        if (!m) return iso || '';
        let h = parseInt(m[1], 10);
        const minutes = m[2];
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = ((h + 11) % 12) + 1;
        return `${h}:${minutes} ${ampm} KST`;
      }
      function dollars(cents){ return '$' + ((cents || 0) / 100).toFixed(2); }

      // Backoff exponentiel: 0s, 3s, 6s, 12s, 24s (5 requêtes max)
      const waits = [0, 3000, 6000, 12000, 24000];

      async function fetchStatus(){
        const resp = await fetch(`/wp-json/kgh/v1/paypal/status?order_id=${encodeURIComponent(orderId)}`, { cache: 'no-store' });
        return resp.json().catch(()=>({status:'not_found'}));
      }

      function showPaid(data, label='Paid'){
        alertBox.style.display = 'none';
        if (headingEl && strings.headingApproved) headingEl.textContent = strings.headingApproved;
        recap.style.display = 'block';
        titleEl.textContent = data.tour_title || strings.bookingTitle;
        if (data.slot_start_iso) whenEl.textContent = `${data.slot_start_iso.substring(0,10)} · ${formatTime(data.slot_start_iso)}`;
        if (data.qty)  guestsEl.textContent = `${strings.guests} ${data.qty}`;
        if (data.amount_usd != null) totalEl.textContent = `${strings.total} ${dollars(data.amount_usd)}`;
        const sEl = document.getElementById('kgh-succ-status');
        if (sEl) sEl.textContent = label;
      }

      async function run(){
        if(!orderId){
          alertBox.innerHTML = `<div class="notice notice-error"><p>${strings.missingOrder}</p></div>`;
          return;
        }

        for (let i=0;i<waits.length;i++){
          if (waits[i]) await new Promise(r=>setTimeout(r, waits[i]));
          try {
            const data = await fetchStatus();
            const s = String(data.status || '').toLowerCase();
            const isPaid = (s==='paid'||s==='completed'||s==='captured'||s==='succeeded'
                            || data.paypal_status==='COMPLETED' || data.order_status==='COMPLETED');
            if (isPaid) { showPaid(data, s==='approved' ? 'Approved' : 'Paid'); return; }
          } catch(_) { /* retry */ }
        }

        alertBox.innerHTML = `<div class="notice notice-warning"><p>${strings.stillProcessing}</p></div>`;
      }

      run();
    })();
  </script>

  <?php return ob_get_clean();
});



// Shortcodes: [kgh_checkout_cancel]
add_shortcode('kgh_checkout_cancel', function () {
  $strings = [
    'heading'   => __( 'Payment cancelled', 'kgh-booking' ),
    'message'   => __( 'No charge has been made. You can try again from your tour page.', 'kgh-booking' ),
    // 'backHome'  => __( 'Back to Home', 'kgh-booking' ),
  ];
  ob_start(); ?>
  <div class="kgh-outcome">
    <h2><?php echo esc_html( $strings['heading'] ); ?></h2>
    <p><?php echo esc_html( $strings['message'] ); ?></p>
    <p><a class="kgh-inline-link" href="<?php echo esc_url( home_url('/') ); ?>"><?php echo esc_html( $strings['backHome'] ); ?></a></p>
  </div>
  <script>
  (function(){
    const p = new URLSearchParams(location.search);
    const order = p.get('order_id') || p.get('token');
    const tour = p.get('kgh_tour') || p.get('tour');
    const date = p.get('kgh_date') || p.get('date');
    const time = p.get('kgh_time') || p.get('time');
    if (order && tour && date && time) {
      fetch('/wp-json/kgh/v1/paypal/cancel', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ order_id: order, tour_id: parseInt(tour,10), slot_start_iso: `${date}T${time}:00+09:00` })
      });
    }
  })();
  </script>
  <?php return ob_get_clean();
});


add_action('init', function() {
  if (isset($_GET['kghlog']) && $_GET['kghlog']==='ping') {
    error_log('[KGH] ping');
    wp_die('ping logged');
  }
});

// Ensure [kgh_checkout] shortcode exists (fallback registration)
if (!function_exists('kgh_render_checkout')) {
  function kgh_render_checkout() {
    // Minimal fallback root; main implementation already registered above.
    return '<div id="kgh-checkout-root"></div>';
  }
}
add_action('init', function () {
  if (!shortcode_exists('kgh_checkout')) {
    add_shortcode('kgh_checkout', 'kgh_render_checkout');
  }
});

// Neutralize WP interpreting 'date'/'time' query vars on the checkout page only
add_filter('request', function ($vars) {
  $is_checkout = (isset($vars['pagename']) && $vars['pagename'] === 'checkout');
  if (!$is_checkout && !empty($vars['page_id'])) {
    $p = get_post((int)$vars['page_id']);
    $is_checkout = $p && ($p->post_name === 'checkout');
  }
  if ($is_checkout) {
    unset($vars['date'], $vars['time']);
  }
  return $vars;
}, 9);

// Add noindex to checkout-related pages
function kgh_checkout_noindex() {
  if (!is_page()) return;
  global $post;
  if (!$post) return;
  $slugs = [$post->post_name];
  $ancestors = get_post_ancestors($post);
  if (!empty($ancestors)) {
    foreach ($ancestors as $ancestor_id) {
      $ancestor = get_post($ancestor_id);
      if ($ancestor) {
        $slugs[] = $ancestor->post_name;
      }
    }
  }
  if (in_array('checkout', $slugs, true)) {
    echo "\n<meta name=\"robots\" content=\"noindex, nofollow\">\n";
  }
}
add_action('wp_head', 'kgh_checkout_noindex', 1);

// === Checkout page shortcode: [kgh_checkout] ===
add_shortcode('kgh_checkout', function(){
  $tour  = isset($_GET['kgh_tour']) ? intval($_GET['kgh_tour']) : ( isset($_GET['tour']) ? intval($_GET['tour']) : 0 );
  $date  = isset($_GET['kgh_date']) ? sanitize_text_field($_GET['kgh_date']) : ( isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '' );
  $time  = isset($_GET['kgh_time']) ? sanitize_text_field($_GET['kgh_time']) : ( isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '' );
  $qty   = isset($_GET['kgh_qty'])  ? max(1,intval($_GET['kgh_qty'])) : ( isset($_GET['qty']) ? max(1,intval($_GET['qty'])) : 1 );

  $tour_title = $tour ? get_the_title($tour) : '';
  $strings = [
    'detailsHeading' => __( 'Your details', 'kgh-booking' ),
    'firstName'      => __( 'First name', 'kgh-booking' ),
    'lastName'       => __( 'Last name', 'kgh-booking' ),
    'email'          => __( 'Email', 'kgh-booking' ),
    'phone'          => __( 'Phone (optional)', 'kgh-booking' ),
    'summary'        => __( 'Summary', 'kgh-booking' ),
    'guests'         => __( 'Guests:', 'kgh-booking' ),
    'total'          => __( 'Total:', 'kgh-booking' ),
    'processing'     => __( 'Processing…', 'kgh-booking' ),
    'backToTour'     => __( 'Back to tour page', 'kgh-booking' ),
    'payCta'         => __( 'Pay with PayPal', 'kgh-booking' ),
    'required'       => __( 'Required', 'kgh-booking' ),
    'emailRequired'  => __( 'Valid email required', 'kgh-booking' ),
    'invalidParams'  => __( 'Missing or invalid parameters. Please go back to the tour page.', 'kgh-booking' ),
    'unavailable'    => __( 'This time is no longer available. Please choose another.', 'kgh-booking' ),
    'networkError'   => __( 'Network error, please retry.', 'kgh-booking' ),
    'orderError'     => __( 'We could not start the payment. Please try again.', 'kgh-booking' ),
    'holdConflict'   => __( 'This time was just taken. Please choose another slot.', 'kgh-booking' ),
    'loading'        => __( 'Loading…', 'kgh-booking' ),
  ];

  ob_start(); ?>
  <div class="kgh-checkout">
    <div id="kgh-co-error" class="kgh-status" role="alert"></div>
    <div class="kgh-checkout-grid">
      <form id="kgh-co-form" class="kgh-checkout-form" novalidate>
        <h2><?php echo esc_html( $strings['detailsHeading'] ); ?></h2>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
          <label for="kgh-first">
            <span><?php echo esc_html( $strings['firstName'] ); ?></span>
            <input id="kgh-first" type="text" required>
            <small id="kgh-first-err"></small>
          </label>
          <label for="kgh-last">
            <span><?php echo esc_html( $strings['lastName'] ); ?></span>
            <input id="kgh-last" type="text" required>
            <small id="kgh-last-err"></small>
          </label>
        </div>
        <label for="kgh-email">
          <span><?php echo esc_html( $strings['email'] ); ?></span>
          <input id="kgh-email" type="email" required>
          <small id="kgh-email-err"></small>
        </label>
        <label for="kgh-phone">
          <span><?php echo esc_html( $strings['phone'] ); ?></span>
          <input id="kgh-phone" type="tel">
        </label>
        <button id="kgh-pay" type="button" class="kgh-button-primary" disabled><?php echo esc_html( $strings['payCta'] ); ?></button>
        <div id="kgh-co-loading" class="kgh-status" style="display:none;"><?php echo esc_html( $strings['processing'] ); ?></div>
        <div><a id="kgh-back" class="kgh-inline-link" href="<?php echo esc_url( get_permalink($tour) ?: home_url('/') ); ?>"><?php echo esc_html( $strings['backToTour'] ); ?></a></div>
      </form>

      <aside id="kgh-co-recap" class="kgh-checkout-summary" aria-live="polite">
        <h3><?php echo esc_html( $strings['summary'] ); ?></h3>
        <div><strong id="kgh-recap-title"><?php echo esc_html( $strings['loading'] ); ?></strong></div>
        <div id="kgh-recap-when" style="opacity:.9;">—</div>
        <div id="kgh-recap-guests" style="opacity:.9;">—</div>
        <div id="kgh-recap-total" style="margin-top:10px;font-weight:700;">—</div>
      </aside>
    </div>
  </div>

  <script>
  (function(){
    const params = new URLSearchParams(window.location.search);
    const strings = <?php echo wp_json_encode( $strings ); ?>;
    const tourId = parseInt((params.get('kgh_tour') || params.get('tour') || '0'), 10);
    const date = (params.get('kgh_date') || params.get('date') || '').trim();
    const time = (params.get('kgh_time') || params.get('time') || '').trim();
    const qty  = Math.max(1, parseInt((params.get('kgh_qty') || params.get('qty') || '1'), 10));
    const slotIso = `${date}T${time}:00+09:00`;

    const elErr = document.getElementById('kgh-co-error');
    const elPay = document.getElementById('kgh-pay');
    const elLoad= document.getElementById('kgh-co-loading');
    const elFirst=document.getElementById('kgh-first');
    const elLast =document.getElementById('kgh-last');
    const elEmail=document.getElementById('kgh-email');
    const elPhone=document.getElementById('kgh-phone');
    const recapTitle=document.getElementById('kgh-recap-title');
    const recapWhen =document.getElementById('kgh-recap-when');
    const recapGuests=document.getElementById('kgh-recap-guests');
    const recapTotal =document.getElementById('kgh-recap-total');

    function showError(msg){ elErr.innerHTML = msg ? `<div class="notice notice-error"><p>${msg}</p></div>` : ''; }
    function clearError(){ showError(''); }
    function fmt12(iso){ const m = iso.match(/T(\d{2}):(\d{2}):/); if (!m) return iso || ''; let h = parseInt(m[1],10), mi=m[2]; const ampm = h>=12?'PM':'AM'; h=((h+11)%12)+1; return `${h}:${mi} ${ampm} KST`; }
    function dollars(cents){ return '$'+((cents||0)/100).toFixed(2); }
    function validParams(){ if(!(tourId>0)) return false; if(!/^\d{4}-\d{2}-\d{2}$/.test(date)) return false; if(!/^\d{2}:\d{2}$/.test(time)) return false; const mm=time.split(':')[1]; if(mm!=='00'&&mm!=='30')return false; return true; }
    function setPayEnabled(on){ elPay.disabled=!on; elPay.setAttribute('aria-disabled', on?'false':'true'); }
    async function fetchJSON(url,payload){ const res=await fetch(url,Object.assign({headers:{'Accept':'application/json','Content-Type':'application/json'}},payload||{})); const body=await res.json().catch(()=>({})); return{ok:res.ok,status:res.status,body}; }

    async function initialQuote(){ if(!validParams()){ showError(strings.invalidParams); setPayEnabled(false); return; } recapTitle.textContent = <?php echo wp_json_encode( $tour_title ?: __( 'Tour', 'kgh-booking' ) ); ?>; recapWhen.textContent = `${date} · ${fmt12(slotIso)}`; recapGuests.textContent = `${strings.guests} ${qty}`; const quote=await fetchJSON('/wp-json/kgh/v1/quote',{method:'POST',body:JSON.stringify({tour_id:tourId,slot_start_iso:slotIso,qty})}); if(!quote.ok){ showError(quote.body.message||strings.unavailable); setPayEnabled(false); return;} recapTotal.textContent=`${strings.total} ${dollars(quote.body.total_usd)}`; setPayEnabled(true); }

    function validateForm(){ let ok=true; const firstErr=document.getElementById('kgh-first-err'); const lastErr=document.getElementById('kgh-last-err'); const emailErr=document.getElementById('kgh-email-err'); if(!elFirst.value.trim()){ firstErr.textContent=strings.required; firstErr.style.display='block'; ok=false; } else { firstErr.style.display='none'; } if(!elLast.value.trim()){ lastErr.textContent=strings.required; lastErr.style.display='block'; ok=false; } else { lastErr.style.display='none'; } if(!elEmail.value.trim() || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(elEmail.value)){ emailErr.textContent=strings.emailRequired; emailErr.style.display='block'; ok=false; } else { emailErr.style.display='none'; } setPayEnabled(ok); return ok; }

    document.getElementById('kgh-co-form').addEventListener('input', ()=>{ if(!elErr.innerHTML) validateForm(); });

    elPay.addEventListener('click', async ()=>{
      clearError();
      if(!validateForm()) return;
      setPayEnabled(false);
      elLoad.style.display='block';
      const quote=await fetchJSON('/wp-json/kgh/v1/quote',{method:'POST',body:JSON.stringify({tour_id:tourId,slot_start_iso:slotIso,qty})});
      if(!quote.ok){ showError(quote.body.message||strings.unavailable); elLoad.style.display='none'; return; }
      const orderPayload={
        tour_id:tourId,
        slot_start_iso:slotIso,
        qty:qty,
        customer_email:elEmail.value.trim(),
        customer_first_name:elFirst.value.trim(),
        customer_last_name:elLast.value.trim(),
        customer_phone:elPhone.value.trim(),
      };
      const order=await fetchJSON('/wp-json/kgh/v1/paypal/order',{method:'POST',body:JSON.stringify(orderPayload)});
      if(!order.ok){ const msg=order.status===409?strings.holdConflict:(order.body.message||strings.orderError); showError(msg); elLoad.style.display='none'; setPayEnabled(true); return; }
      const approve=order.body && order.body.approve_url;
      if(approve){ window.location.href=approve; }
      else { showError(strings.orderError); elLoad.style.display='none'; setPayEnabled(true); }
    });

    initialQuote();
  })();
  </script>
  <?php return ob_get_clean();
});


// === Disable Gutenberg editors (classic editing) ===
add_filter('use_block_editor_for_post', '__return_false', 10); // posts + pages
add_filter('use_widgets_block_editor', '__return_false');      // widget editor

// === CPT "tour" (fallback quand les plugins sont désactivés) ===
add_action('init', function () {
  // Si un plugin (ex: kgh-booking) a déjà enregistré "tour", on ne fait rien.
  if ( post_type_exists('tour') ) return;

  register_post_type('tour', [
    'label'         => 'Tours',
    'labels'        => [
      'name'               => 'Tours',
      'singular_name'      => 'Tour',
      'add_new'            => 'Add New',
      'add_new_item'       => 'Add New Tour',
      'edit_item'          => 'Edit Tour',
      'new_item'           => 'New Tour',
      'view_item'          => 'View Tour',
      'search_items'       => 'Search Tours',
      'not_found'          => 'No tours found',
      'not_found_in_trash' => 'No tours found in Trash',
    ],
    'public'        => true,
    'has_archive'   => 'tours',          // archive à /tours/
    'rewrite'       => ['slug' => 'tours'],
    'menu_position' => 5,
    'menu_icon'     => 'dashicons-location-alt',
    'supports'      => ['title','editor','excerpt','thumbnail'],
    'show_in_rest'  => false,            // Classic editor (on a désactivé Gutenberg)
    'supports' => ['title','excerpt','thumbnail'], // pas 'editor'
  ]);
});

add_filter('register_post_type_args', function($args, $post_type){
  if ($post_type === 'tour') {
    $args['public']             = true;
    $args['publicly_queryable'] = true;
    $args['has_archive']        = 'tours'; // true marche aussi, mais autant figer le slug
    $args['rewrite']            = ['slug' => 'tours', 'with_front' => false];
    $args['query_var']          = true;
  }
  return $args;
}, 20, 2);


// Inline un SVG depuis /assets/icons/*.svg
function kgh_icon($name){
  $path = get_theme_file_path('assets/icons/' . $name . '.svg');
  if (!file_exists($path)) return '';
  $svg = file_get_contents($path);
  // (optionnel) mini-sécurité : enlève les scripts éventuels
  $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
  return $svg;
};

// Désactiver l'éditeur (zone Add Media / Visual / Text) UNIQUEMENT pour la page avec le template "page-home.php"
add_action('load-post.php', 'kgh_disable_home_editor');
add_action('load-post-new.php', 'kgh_disable_home_editor');

function kgh_disable_home_editor() {
  $screen = get_current_screen();
  if (!$screen || $screen->post_type !== 'page') return;

  // ID de la page en édition
  $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
  if (!$post_id) return;

  // Si la page utilise bien le template Home → on retire l'éditeur
  if (get_page_template_slug($post_id) === 'page-home.php') {
    // 1) Retire le support "editor" pour le type "page" sur CET écran
    remove_post_type_support('page', 'editor');

    // 2) Par sécurité, retire la metabox classic editor si déjà ajoutée
    add_action('admin_menu', function () {
      remove_meta_box('postdivrich', 'page', 'normal');
    }, 999);
  }
}


/**
 * Resolve an SCF image (ID | array | URL) into url + srcset.
 */
function kgh_resolve_image($value, $size_url = 'large', $size_set = 'full') {
  $id = 0; $url = ''; $set = '';

  if (is_numeric($value)) {
    $id  = (int) $value;
  } elseif (is_array($value)) {
    // SCF can return an array; try common keys or first element
    if (isset($value['id']))        $id = (int) $value['id'];
    elseif (isset($value[0]))       $id = (int) $value[0];
    elseif (isset($value['url']))   $url = (string) $value['url'];
  } elseif (is_string($value) && preg_match('~^https?://~', $value)) {
    $url = $value;
  }

  if ($id) {
    $url = wp_get_attachment_image_url($id, $size_url) ?: $url;
    $set = wp_get_attachment_image_srcset($id, $size_set) ?: '';
  }

  return ['id'=>$id, 'url'=>$url, 'srcset'=>$set];
}

/**
 * Return desktop & mobile hero sources for the given page.
 * Fields: home_hero_desktop, home_hero_mobile (SCF). Fallback: featured image, then theme sample.
 */
function kgh_get_home_hero_sources($post_id) {
  $desk_val = function_exists('SCF') ? SCF::get('home_hero_desktop', $post_id) : 0;
  $mob_val  = function_exists('SCF') ? SCF::get('home_hero_mobile',  $post_id) : 0;

  // fallbacks
  $feat_id = get_post_thumbnail_id($post_id);
  if (!$desk_val) $desk_val = $feat_id ?: 0;
  if (!$mob_val)  $mob_val  = $feat_id ?: 0;

  $desk = kgh_resolve_image($desk_val);
  $mob  = kgh_resolve_image($mob_val);
  return [$desk, $mob];
}


// Badges sur l'image des cartes
function kgh_badge_icon($slug) {
  switch ($slug) {
    case 'spicy':        return kgh_icon('icon-zap');        // ⚡️ à remplacer par ton SVG
    case 'traditional':  return kgh_icon('icon-trad');       // remplace par ton SVG
    case 'night':        return kgh_icon('icon-moon');       // remplace par ton SVG
    default:             return '';                          // aucun icône
  }
}


// === CPT "guide" ===
add_action('init', function () {
  if ( post_type_exists('guide') ) return;

  register_post_type('guide', [
    'label'         => 'Guides',
    'labels'        => [
      'name'          => 'Guides',
      'singular_name' => 'Guide',
      'add_new_item'  => 'Add New Guide',
      'edit_item'     => 'Edit Guide',
      'view_item'     => 'View Guide',
      'search_items'  => 'Search Guides',
    ],
    'public'        => true,
    'has_archive'   => 'guides',
    'rewrite'       => ['slug' => 'guides'],
    'menu_position' => 6,
    'menu_icon'     => 'dashicons-book-alt',
    'show_in_rest'  => false, // laisse false si tu restes en Classic Editor
    'supports'      => ['title','excerpt','thumbnail','page-attributes'],
  ]);
});


// === CPT "testimonial" ===
add_action('init', function () {
  if ( post_type_exists('testimonial') ) return;

  register_post_type('testimonial', [
    'label'         => 'Testimonials',
    'labels'        => [
      'name'          => 'Testimonials',
      'singular_name' => 'Testimonial',
      'add_new_item'  => 'Add New Testimonial',
      'edit_item'     => 'Edit Testimonial',
      'view_item'     => 'View Testimonial',
      'search_items'  => 'Search Testimonials',
    ],
    'public'        => true,
    'has_archive'   => false,
    'rewrite'       => ['slug' => 'testimonials'],
    'menu_position' => 7,
    'menu_icon'     => 'dashicons-format-quote',
    'show_in_rest'  => false, // classic editor
    'supports'      => ['title','editor','thumbnail','page-attributes','excerpt'],
  ]);
});


// [kgh_testimonials count="6"]
add_shortcode('kgh_testimonials', function($atts){
  $atts = shortcode_atts([
    'count' => 6,        // nombre max à afficher
  ], $atts, 'kgh_testimonials');

  $q = new WP_Query([
    'post_type'      => 'testimonial',
    'post_status'    => 'publish',
    'posts_per_page' => intval($atts['count']),
    'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    'order'          => 'ASC',
  ]);

  ob_start(); ?>

  <section class="kgh-container my-10 md:my-16">
    <h2 class="font-serif text-3xl md:text-4xl text-black mb-6">Testimonials</h2>

    <?php if ($q->have_posts()): ?>
      <div class="relative">
        <!-- Flèche gauche -->
        <button type="button"
                class="hidden md:flex absolute -left-14 top-1/2 -translate-y-1/2 h-14 w-14 items-center justify-center border-2 border-kgh-grey rounded-sm hover:bg-kgh-redclayLight"
                data-kgh-ts-prev aria-label="Previous">
          <span class="text-2xl">‹</span>
        </button>

        <!-- Piste scrollable -->
        <div class="flex gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory pr-2"
             data-kgh-ts-track>
          <?php while ($q->have_posts()): $q->the_post();
            $country = function_exists('SCF') ? trim((string) SCF::get('t_author_country')) : '';
            $tour    = function_exists('SCF') ? trim((string) SCF::get('t_tour_name'))    : '';
            $name    = get_the_title();
            $text    = get_the_excerpt();
            if (!$text) $text = wp_trim_words( wp_strip_all_tags( get_the_content() ), 55 );

            // avatar: image à la une si dispo
            $avatar = has_post_thumbnail()
              ? get_the_post_thumbnail(null, 'thumbnail', ['class'=>'h-9 w-9 rounded-full object-cover'])
              : '';
          ?>
            <article class="min-w-[320px] md:min-w-[420px] grow snap-start rounded-sm border-2 border-[#CAC8C8] bg-white p-6">
              <p class="text-[15px] leading-relaxed text-gray-800 mb-6"><?php echo esc_html($text); ?></p>

              <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full overflow-hidden bg-kgh-porcelain grid place-items-center border border-kgh-red">
                  <?php
                    if ($avatar) {
                      echo $avatar;
                    } else {
                      echo function_exists('kgh_icon') ? kgh_icon('user') : '👤';
                    }
                  ?>
                </div>
                <div class="min-w-0">
                  <div class="font-semibold text-black"><?php echo esc_html($name); ?></div>
                  <div class="text-sm text-gray-600">
                    <?php
                      $bits = [];
                      if ($country !== '') $bits[] = esc_html($country);
                      if ($tour !== '')    $bits[] = esc_html($tour);
                      echo implode(' · ', $bits);
                    ?>
                  </div>
                </div>
              </div>
            </article>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <!-- Flèche droite -->
        <button type="button"
                class="hidden md:flex absolute -right-14 top-1/2 -translate-y-1/2 h-14 w-14 items-center justify-center border-2 border-kgh-grey rounded-sm hover:bg-kgh-redclayLight"
                data-kgh-ts-next aria-label="Next">
          <span class="text-2xl">›</span>
        </button>
      </div>
    <?php else: ?>
      <p class="kgh-subtle">No testimonials yet.</p>
    <?php endif; ?>
  </section>

  <script>
  // mini carrousel au scroll horizontal
  document.addEventListener('DOMContentLoaded', () => {
    const track = document.querySelector('[data-kgh-ts-track]');
    if (!track) return;
    const prev = document.querySelector('[data-kgh-ts-prev]');
    const next = document.querySelector('[data-kgh-ts-next]');
    const card = track.querySelector('article');
    const step = card ? (card.getBoundingClientRect().width + 24) : 380; // 24 ≈ gap

    function scrollBy(dx){ track.scrollBy({left: dx, behavior:'smooth'}); }
    prev && prev.addEventListener('click', () => scrollBy(-step));
    next && next.addEventListener('click', () => scrollBy(step));
  });
  </script>

  <?php return ob_get_clean();
});




// [kgh_contact title="Contact us" portrait_id="123" services="Private tour,Cooking class"]
add_shortcode('kgh_contact', function($atts){
  $atts = shortcode_atts([
    'title'       => 'Contact us',
    'portrait_id' => '',
    'services'    => '', // CSV
  ], $atts, 'kgh_contact');

  $args = [
    'title'       => $atts['title'],
    'portrait_id' => $atts['portrait_id'] ? (int) $atts['portrait_id'] : 0,
  ];
  if ($atts['services'] !== '') {
    $args['services'] = array_map('trim', explode(',', $atts['services']));
  }

  ob_start();
  get_template_part('template-parts/section', 'contact', $args);
  return ob_get_clean();
});


// Register Footer & Header menu
add_action('after_setup_theme', function () {
  register_nav_menus([
    'primary' => __('Header Menu', 'kgh'),
    'footer'  => __('Footer Menu', 'kgh'),
  ]);
});

// 2) Classes <li> générées par WP (pour hooker Tailwind plus facilement)
add_filter('nav_menu_css_class', function($classes, $item, $args) {
  if (isset($args->theme_location) && $args->theme_location === 'primary') {
    // on garde les classes WP (current-menu-item, current-menu-ancestor, etc.)
    $classes[] = 'kgh-nav-item';
  }
  return $classes;
}, 10, 3);

// 3) Classes <a> : base + hover + bordure, et état actif via .current-menu-*
add_filter('nav_menu_link_attributes', function($atts, $item, $args) {
  if (isset($args->theme_location) && $args->theme_location === 'primary') {
    // classes communes desktop et mobile; desktop aura un conteneur différent
    $base = 'inline-flex items-center font-semibold hover:no-underline';
    // on laisse la couleur/underline gérées par CSS utilitaire ci-dessous
    $atts['class'] = isset($atts['class']) ? $atts['class'] . ' ' . $base : $base;
  }
  return $atts;
}, 10, 3);


// 16:9 net pour vignettes/articles blog
add_action('after_setup_theme', function () {
  add_image_size('kgh-post', 1200, 675, true); 
});

// 120×120 dur recadré pour la liste
add_action('after_setup_theme', function () {
  add_image_size('kgh-post-thumb', 120, 120, true);
});

// Helper vignettes pour la liste d’articles
function kgh_post_list_thumb($post_id = 0){
  $post_id = $post_id ?: get_the_ID();

  if (has_post_thumbnail($post_id)) {
    return get_the_post_thumbnail(
      $post_id,
      'kgh-post-thumb',
      ['class'=>'w-full h-full object-cover','loading'=>'lazy','alt'=>esc_attr(get_the_title($post_id))]
    );
  }
  $html = get_post_field('post_content', $post_id);
  if ($html && preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $m)) {
    $src = esc_url($m[1]);
    return '<img src="'.$src.'" alt="'.esc_attr(get_the_title($post_id)).'" class="w-full h-full object-cover" loading="lazy">';
  }
  return '<div class="w-full h-full bg-gray-100 grid place-items-center text-xs text-gray-500">No image</div>';
}



// Handle contact form (logged-in + visitors)
add_action('admin_post_kgh_contact_send',    'kgh_handle_contact_form');
add_action('admin_post_nopriv_kgh_contact_send', 'kgh_handle_contact_form');

function kgh_handle_contact_form(){
  // CSRF
  if ( ! isset($_POST['kgh_contact_nonce']) || ! wp_verify_nonce($_POST['kgh_contact_nonce'], 'kgh_contact_send') ) {
    return kgh_contact_redirect(0);
  }
  
  $bcc = 'yunamisogo@gmail.com';

  // Anti-bot: le champ doit rester vide
  if ( ! empty($_POST['website']) ) {
    return kgh_contact_redirect(0);
  }

  // Collecte + nettoyage
  $name    = isset($_POST['name'])    ? trim( wp_strip_all_tags($_POST['name']) )   : '';
  $email   = isset($_POST['email'])   ? sanitize_email($_POST['email'])             : '';
  $service = isset($_POST['service']) ? sanitize_text_field($_POST['service'])      : '';
  $message = isset($_POST['message']) ? trim( wp_kses_post($_POST['message']) )     : '';

  if ($name==='' || !is_email($email) || $message==='') {
    return kgh_contact_redirect(0);
  }

  // Destinataire principal
  $to = 'yunamisogo@gmail.com';

  // Sujet + contenu
  $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $subj   = "[{$site}] New contact request";
  $serv_l = $service ? "Service: {$service}\n" : '';

  $body_text = "New message from {$site}\n\n"
             . "Name: {$name}\n"
             . "Email: {$email}\n"
             . $serv_l
             . "Message:\n{$message}\n";

  // Headers (plain text) + Reply-To = l’expéditeur
  $headers = [];
  $headers[] = 'Content-Type: text/plain; charset=UTF-8';
  $headers[] = 'Reply-To: '. $name .' <'. $email .'>';
  $headers[] = 'Bcc: ' . $bcc;


  $sent = wp_mail($to, $subj, $body_text, $headers);

  return kgh_contact_redirect( $sent ? 1 : 0 );
}

function kgh_contact_redirect($ok){
  $redirect = isset($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : home_url('/');
  // Nettoie l’URL et ajoute le flag
  $redirect = remove_query_arg(['sent'], $redirect);
  $redirect = add_query_arg(['sent' => $ok ? '1' : '0'], $redirect);
  wp_safe_redirect($redirect);
  exit;
}



// --- Booking feature flag (ON en local, OFF en prod) ---
add_filter('kgh_booking_enabled', function ($on) {
  $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
  $is_local = (defined('WP_DEBUG') && WP_DEBUG) || str_contains($host, 'localhost') || str_contains($host, '.local');
  return $is_local; // local => true, prod => false
}, 10, 1);

add_action('rest_api_init', function () {
  register_rest_route('kgh/v1', '/paypal/debug', [
    'methods'  => 'GET',
    'permission_callback' => '__return_true',
    'callback' => function(WP_REST_Request $req){
      $order = sanitize_text_field($req->get_param('order_id'));
      if (!$order) return new WP_REST_Response(['error'=>'no order_id'], 400);

      // Récupère tes creds (adapte à ton plugin: options, constants, etc.)
      $cid = get_option('kgh_pp_client_id');     // à ajuster
      $sec = get_option('kgh_pp_client_secret'); // à ajuster
      $base = 'https://api-m.sandbox.paypal.com';

      // 1) token OAuth
      $ch = curl_init("$base/v1/oauth2/token");
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $cid.':'.$sec,
        CURLOPT_RETURNTRANSFER => true,
      ]);
      $tok = json_decode(curl_exec($ch), true);
      curl_close($ch);
      if (empty($tok['access_token'])) return new WP_REST_Response(['error'=>'oauth_failed','raw'=>$tok], 500);

      $hdr = ['Authorization: Bearer '.$tok['access_token'],'Content-Type: application/json'];

      // 2) GET order
      $ch = curl_init("$base/v2/checkout/orders/$order");
      curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$hdr, CURLOPT_RETURNTRANSFER=>true]);
      $get = json_decode(curl_exec($ch), true);
      curl_close($ch);

      return new WP_REST_Response(['paypal_order'=>$get], 200);
    }
  ]);
});
