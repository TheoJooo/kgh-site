<?php
/**
 * Template: Single Tour
 * Affiche le tour + ses sessions (CPT: tour_date) avec bouton Reserve (PayPal).
 */
if ( ! defined('ABSPATH') ) exit;

get_header();

// ID du tour courant
$tour_id = get_the_ID();

/**
 * Récupère les sessions liées, futures en premier.
 * On garde publish/draft/pending pour que la proprio puisse prévisualiser.
 */
$sessions = get_posts([
  'post_type'      => 'tour_date',
  'posts_per_page' => -1,
  'meta_key'       => '_kgh_date_start',
  'orderby'        => 'meta_value',
  'order'          => 'ASC',
  'meta_query'     => [
    'relation' => 'AND',
    [
      'key'     => '_kgh_tour_id',
      'value'   => $tour_id,
      'compare' => '=',
    ],
    [
      'key'     => '_kgh_date_start',
      'value'   => current_time('mysql'),
      'compare' => '>=',
      'type'    => 'DATETIME',
    ],
  ],
  'post_status'    => ['publish','draft','pending'],
]);

/** Helpers d'affichage */
function kgh_fmt_price_krw($v){ return number_format_i18n( (int)$v ) . ' KRW'; }
function kgh_fmt_price_usd($v){ return '$' . number_format((float)$v, 2); }
function kgh_fmt_dt($mysql){
  if(!$mysql) return '—';
  $ts = strtotime($mysql);
  return date_i18n('M j, Y · H:i', $ts);
}
?>
<main class="kgh-container" style="max-width:980px;margin:60px auto;padding:0 20px;">
  <article class="kgh-tour">
    <header style="margin-bottom:24px;">
      <h1 class="kgh-title" style="font-family:'Merriweather',serif;color:#661E11;"><?php the_title(); ?></h1>
      <div class="kgh-excerpt" style="color:#373737;opacity:.9;font-family:'Noto Sans',system-ui;"><?php the_excerpt(); ?></div>
    </header>

    <section class="kgh-sessions">
      <h2 style="font-family:'Merriweather',serif;margin:24px 0 12px;">Upcoming sessions</h2>

      <?php if (empty($sessions)) : ?>
        <p style="font-family:'Noto Sans',system-ui;">No upcoming dates yet.</p>
      <?php else: ?>
        <ul class="kgh-session-list" style="list-style:none;padding:0;display:grid;gap:16px;">
          <?php foreach ($sessions as $s) :
            $sid        = $s->ID;
            $price_krw  = (int) get_post_meta($sid, '_kgh_price_krw', true);
            $price_usd  = get_post_meta($sid, '_kgh_price_usd', true); // peut être string
            $cap_total  = (int) get_post_meta($sid, '_kgh_capacity_total', true);
            $booked     = (int) get_post_meta($sid, '_kgh_capacity_left', true); // "booked"
            $start      = get_post_meta($sid, '_kgh_date_start', true);
            $lang       = get_post_meta($sid, '_kgh_language', true) ?: 'EN';
            $available  = max(0, $cap_total - $booked);
            $is_full    = ($available === 0);

            // Libellé prix: USD maître si présent, sinon fallback KRW. (Affiche les deux si disponibles)
            if ($price_usd !== '' && (float)$price_usd > 0) {
              $price_label = kgh_fmt_price_usd($price_usd) . ' USD';
              if ($price_krw > 0) {
                $price_label .= ' · ' . kgh_fmt_price_krw($price_krw);
              }
            } else {
              $price_label = kgh_fmt_price_krw($price_krw);
            }
          ?>
            <li class="kgh-session-card" style="background:#FDFAFA;border:1px solid #eee;border-radius:16px;padding:16px;display:flex;justify-content:space-between;gap:16px;align-items:center;">
              <div class="kgh-session-info" style="flex:1 1 auto;">
                <div style="font-family:'Noto Serif KR','Noto Serif',serif;color:#373737;">
                  <strong><?php echo esc_html( kgh_fmt_dt($start) ); ?></strong>
                  <span style="margin-left:8px;opacity:.8;">· Lang: <?php echo esc_html($lang); ?></span>
                </div>

                <div style="margin-top:6px;font-family:'Noto Sans',system-ui;color:#373737;">
                  Price: <strong><?php echo esc_html($price_label); ?></strong>
                  <span style="margin-left:12px;">Booked / Total: <strong><?php echo (int)$booked . ' / ' . (int)$cap_total; ?></strong></span>
                  <span style="margin-left:12px;">Available: <strong><?php echo (int)$available; ?></strong></span>
                </div>
              </div>

              <div class="kgh-session-actions" style="display:flex;align-items:center;gap:10px;">
                <label style="font-family:'Noto Sans',system-ui;font-size:14px;">
                  Qty
                  <select class="kgh-qty" data-session="<?php echo esc_attr($sid); ?>" style="margin-left:6px;padding:6px 8px;border-radius:10px;border:1px solid #ddd;">
                    <?php
                      $maxQty = min(6, max(1,$available)); // limite UX
                      for($i=1;$i<=$maxQty;$i++) echo '<option value="'.$i.'">'.$i.'</option>';
                    ?>
                  </select>
                </label>

                <input type="email"
                       class="kgh-email"
                       data-session="<?php echo esc_attr($sid); ?>"
                       placeholder="your@email.com"
                       style="padding:8px 10px;border-radius:10px;border:1px solid #ddd;min-width:210px;font-family:'Noto Sans',system-ui;">

                <?php if ($is_full): ?>
                  <button class="kgh-btn" disabled
                          style="background:#ccc;color:#fff;border:none;border-radius:999px;padding:10px 18px;font-weight:600;cursor:not-allowed;">
                    Sold out
                  </button>
                <?php else: ?>
                  <button class="kgh-btn kgh-reserve"
                          data-session="<?php echo esc_attr($sid); ?>"
                          style="background:#661E11;color:#FDFAFA;border:none;border-radius:999px;padding:10px 18px;font-weight:600;cursor:pointer;">
                    Reserve
                  </button>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="kgh-content" style="margin-top:36px;">
      <div style="font-family:'Noto Sans',system-ui;color:#373737;"><?php the_content(); ?></div>
    </section>
  </article>
</main>

<script>
// Clic "Reserve" -> appelle kghStartPayPal (défini dans assets/js/main.js)
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.kgh-reserve');
  if (!btn) return;
  const sid = parseInt(btn.dataset.session, 10);
  const wrap = btn.closest('.kgh-session-card');
  const qtyEl = wrap.querySelector('.kgh-qty[data-session="'+sid+'"]');
  const emailEl = wrap.querySelector('.kgh-email[data-session="'+sid+'"]');
  const qty = parseInt((qtyEl && qtyEl.value) ? qtyEl.value : '1', 10);
  const email = (emailEl && emailEl.value ? emailEl.value : '').trim();

  btn.disabled = true; const old = btn.textContent; btn.textContent = 'Redirecting…';
  try {
    await kghStartPayPal({ tourDateId: sid, qty, email });
  } catch (err) {
    alert(err.message || 'Payment error');
    btn.disabled = false; btn.textContent = old;
  }
});
</script>

<?php get_footer(); ?>
