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

  wp_enqueue_style(
    'kgh-booking-ui',
    KGH_URI . '/assets/css/booking.css',
    ['kgh-main'],
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

// Success page: poll status endpoint and show recap
add_shortcode('kgh_checkout_success', function () {
  $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : (isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '');
  $strings = [
    'heading'        => __( 'Thank you! Your payment is processing…', 'kgh-booking' ),
    'processing'     => __( 'Processing your payment… this page will update automatically.', 'kgh-booking' ),
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
    <h2><?php echo esc_html( $strings['heading'] ); ?></h2>
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
  (function(){
    const orderId = <?php echo wp_json_encode( $order_id ); ?>;
    const strings = <?php echo wp_json_encode( $strings ); ?>;
    const alertBox = document.getElementById('kgh-succ-alert');
    const recap = document.getElementById('kgh-succ-recap');
    const titleEl = document.getElementById('kgh-succ-title');
    const whenEl = document.getElementById('kgh-succ-when');
    const guestsEl = document.getElementById('kgh-succ-guests');
    const totalEl = document.getElementById('kgh-succ-total');

    function formatTime(iso){
      const match = iso && iso.match(/T(\d{2}):(\d{2}):/);
      if (!match) return iso || '';
      let h = parseInt(match[1], 10);
      const minutes = match[2];
      const ampm = h >= 12 ? 'PM' : 'AM';
      h = ((h + 11) % 12) + 1;
      return `${h}:${minutes} ${ampm} KST`;
    }

    function dollars(cents){
      return '$' + ((cents || 0) / 100).toFixed(2);
    }

    async function poll(){
      if(!orderId){
        alertBox.innerHTML = `<div class="notice notice-error"><p>${strings.missingOrder}</p></div>`;
        return;
      }
      for(let i=0;i<5;i++){
        try {
          const resp = await fetch(`/wp-json/kgh/v1/paypal/status?order_id=${encodeURIComponent(orderId)}`);
          const data = await resp.json().catch(()=>({status:'not_found'}));
          if (data.status === 'paid') {
            alertBox.style.display = 'none';
            recap.style.display = 'block';
            titleEl.textContent = data.tour_title || strings.bookingTitle;
            whenEl.textContent = data.slot_start_iso ? `${data.slot_start_iso.substring(0,10)} · ${formatTime(data.slot_start_iso)}` : '';
            guestsEl.textContent = `${strings.guests} ${data.qty || 1}`;
            totalEl.textContent = `${strings.total} ${dollars(data.amount_usd)}`;
            return;
          }
        } catch(e){}
        await new Promise(r => setTimeout(r, 2000));
      }
      alertBox.innerHTML = `<div class="notice notice-warning"><p>${strings.stillProcessing}</p></div>`;
    }
    poll();
  })();
  </script>
  <?php return ob_get_clean();
});



// Shortcodes: [kgh_checkout_cancel]
add_shortcode('kgh_checkout_cancel', function () {
  $strings = [
    'heading'   => __( 'Payment cancelled', 'kgh-booking' ),
    'message'   => __( 'No charge has been made. You can try again from your tour page.', 'kgh-booking' ),
    'backHome'  => __( 'Back to Home', 'kgh-booking' ),
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
