<?php
/**
 * Template: Single Tour
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

global $post;
$tour_id = get_the_ID();

get_header();

$ui_strings = [
  'loading'          => __( 'Loading…', 'kgh-booking' ),
  'selectDateFirst'  => __( 'Select a date first', 'kgh-booking' ),
  'noAvailability'   => __( 'No availability for this date', 'kgh-booking' ),
  'cutoff'           => __( 'Cutoff', 'kgh-booking' ),
  'soldOut'          => __( 'Sold out', 'kgh-booking' ),
  'onlyLeft'         => __( '— only %s left', 'kgh-booking' ),
  'unavailable'      => __( 'This time is no longer available. Please choose another.', 'kgh-booking' ),
  'networkError'     => __( 'Network error, please retry.', 'kgh-booking' ),
  'invalidParams'    => __( 'Missing or invalid parameters. Please go back to the tour page.', 'kgh-booking' ),
  'reserve'          => __( 'Reserve', 'kgh-booking' ),
];
?>

<main class="kgh-booking-container">
  <article class="kgh-tour">
    <header class="kgh-booking-header">
      <h1 class="kgh-booking-title"><?php the_title(); ?></h1>
      <div class="kgh-booking-excerpt"><?php the_excerpt(); ?></div>
    </header>

    <section class="kgh-reserve-block" aria-labelledby="kgh-reserve-heading">
      <h2 id="kgh-reserve-heading" class="kgh-reserve-title"><?php esc_html_e( 'Reserve', 'kgh-booking' ); ?></h2>
      <div id="kgh-booking-error" class="kgh-reserve-alert" role="alert"></div>

      <div class="kgh-reserve-grid">
        <label for="kgh-date">
          <span><?php esc_html_e( 'Date', 'kgh-booking' ); ?></span>
          <select id="kgh-date">
            <option value=""><?php esc_html_e( 'Loading…', 'kgh-booking' ); ?></option>
          </select>
        </label>

        <label for="kgh-time">
          <span><?php esc_html_e( 'Time', 'kgh-booking' ); ?> <span style="opacity:.7;">(<?php esc_html_e( 'KST', 'kgh-booking' ); ?>)</span></span>
          <select id="kgh-time" disabled aria-disabled="true">
            <option value=""><?php esc_html_e( 'Select a date first', 'kgh-booking' ); ?></option>
          </select>
        </label>

        <label for="kgh-guests">
          <span><?php esc_html_e( 'Guests', 'kgh-booking' ); ?></span>
          <select id="kgh-guests" disabled aria-disabled="true"></select>
        </label>

        <button id="kgh-cta" class="kgh-reserve-button" disabled><?php echo esc_html( $ui_strings['reserve'] ); ?></button>
      </div>

      <div id="kgh-no-slots" class="kgh-reserve-message"><?php esc_html_e( 'No availability for this date', 'kgh-booking' ); ?></div>
    </section>

    <section class="kgh-tour-content">
      <div><?php the_content(); ?></div>
    </section>
  </article>
</main>

<script>
(function(){
  const messages = <?php echo wp_json_encode( $ui_strings ); ?>;
  const tourId = <?php echo (int) $tour_id; ?>;
  const qs = new URLSearchParams(window.location.search);
  const preDate = qs.get('kgh_date') || qs.get('date') || '';
  const preTime = qs.get('kgh_time') || qs.get('time') || '';
  const preQty  = Math.max(1, parseInt(qs.get('kgh_qty') || qs.get('qty') || '1', 10));

  const elDate   = document.getElementById('kgh-date');
  const elTime   = document.getElementById('kgh-time');
  const elGuests = document.getElementById('kgh-guests');
  const elCTA    = document.getElementById('kgh-cta');
  const elErr    = document.getElementById('kgh-booking-error');
  const elNo     = document.getElementById('kgh-no-slots');

  let slots = [];
  let selectedSlot = null;

  function setDisabled(el, disabled) {
    if (!el) return;
    el.disabled = !!disabled;
    el.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function showError(msg) {
    if (!msg) {
      elErr.style.display = 'none';
      elErr.textContent = '';
      return;
    }
    elErr.textContent = msg;
    elErr.style.display = 'block';
  }

  function isoToKstHm(iso) {
    const match = iso.match(/T(\d{2}):(\d{2}):\d{2}\+09:00$/);
    if (!match) return iso;
    return `${match[1]}:${match[2]}`;
  }

  function fmt12hm(hhmm) {
    const [hStr, mStr] = hhmm.split(':');
    let h = parseInt(hStr, 10);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = ((h + 11) % 12) + 1;
    return `${h}:${mStr} ${ampm}`;
  }

  function labelForSlot(slot, fmtTime) {
    if (!slot) return fmtTime;
    let label = fmtTime;
    if (slot.reason === 'cutoff') label += ` (${messages.cutoff})`;
    else if (slot.sold_out) label += ` (${messages.soldOut})`;
    else if (Number(slot.left) <= 4) label += ' ' + messages.onlyLeft.replace('%s', slot.left);
    return label;
  }

  function isSlotDisabled(slot) {
    return !!(slot && (slot.sold_out || slot.reason === 'cutoff' || slot.reason === 'closed'));
  }

  async function loadDays(){
    try {
      const res = await fetch(`/wp-json/kgh/v1/availability?tour=${tourId}&days=90`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('http');
      const data = await res.json();
      const apiDates = Array.isArray(data.dates) ? data.dates : [];
      let dateOpts = apiDates.map(d => ({ value: d, label: d, disabled: false }));

      dateOpts = await maybeInjectDate(todayKST(), dateOpts);
      dateOpts = await maybeInjectDate(preDate, dateOpts);

      if (!dateOpts.length) {
        elDate.innerHTML = `<option value="">${messages.noAvailability}</option>`;
        setDisabled(elDate, true);
        setDisabled(elTime, true);
        setDisabled(elGuests, true);
        elNo.style.display = 'block';
        return;
      }

      renderDateOptions(dateOpts);
      let target = preDate && dateOpts.some(o => o.value === preDate) ? preDate : null;
      if (!target) {
        const firstOpen = dateOpts.find(o => !o.disabled);
        target = firstOpen ? firstOpen.value : dateOpts[0].value;
      }
      elDate.value = target;
      await loadSlotsForDate(target);
    } catch (e) {
      elDate.innerHTML = `<option value="">${messages.networkError}</option>`;
    }
  }

  function renderDateOptions(opts) {
    elDate.innerHTML = '';
    opts.sort((a,b) => a.value.localeCompare(b.value));
    opts.forEach(o => {
      const opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.label;
      if (o.disabled) {
        opt.disabled = true;
        opt.setAttribute('aria-disabled', 'true');
      }
      elDate.appendChild(opt);
    });
  }

  async function maybeInjectDate(ymd, opts) {
    if (!ymd || opts.some(o => o.value === ymd)) return opts;
    try {
      const res = await fetch(`/wp-json/kgh/v1/availability/day?tour=${tourId}&date=${encodeURIComponent(ymd)}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) return opts;
      const data = await res.json();
      const slotsArr = Array.isArray(data.slots) ? data.slots : [];
      const hasCutoff = slotsArr.length > 0 && slotsArr.every(s => s.sold_out) && slotsArr.some(s => s.reason === 'cutoff');
      if (hasCutoff) {
        opts.push({ value: ymd, label: `${ymd} (${messages.cutoff})`, disabled: true });
      }
    } catch (e) {
      // ignore
    }
    return opts;
  }

  async function loadSlotsForDate(ymd) {
    showError('');
    selectedSlot = null;
    elNo.style.display = 'none';
    setDisabled(elTime, true);
    setDisabled(elGuests, true);
    setDisabled(elCTA, true);
    elTime.innerHTML = `<option value="">${messages.loading}</option>`;
    try {
      const res = await fetch(`/wp-json/kgh/v1/availability/day?tour=${tourId}&date=${encodeURIComponent(ymd)}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('http');
      const data = await res.json();
      slots = data.slots || [];
      elTime.innerHTML = '';
      if (!slots.length) {
        elTime.innerHTML = `<option value="">${messages.noAvailability}</option>`;
        elNo.style.display = 'block';
        return;
      }
      slots.forEach(slot => {
        const hm = isoToKstHm(slot.slot_start_iso);
        const label = labelForSlot(slot, fmt12hm(hm));
        const disabled = isSlotDisabled(slot);
        const opt = document.createElement('option');
        opt.value = slot.slot_start_iso;
        opt.textContent = label;
        opt.disabled = disabled;
        opt.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        elTime.appendChild(opt);
      });
      let chosen = slots.find(s => !isSlotDisabled(s));
      if (preTime) {
        const want = slots.find(s => !isSlotDisabled(s) && isoToKstHm(s.slot_start_iso) === preTime);
        if (want) chosen = want;
      }
      if (chosen) {
        elTime.value = chosen.slot_start_iso;
        onTimeChange();
      } else {
        elNo.style.display = 'block';
        elGuests.innerHTML = '<option value="1">1</option>';
      }
    } catch (e) {
      elTime.innerHTML = `<option value="">${messages.networkError}</option>`;
    } finally {
      setDisabled(elTime, false);
    }
  }

  function onTimeChange() {
    showError('');
    const iso = elTime.value;
    selectedSlot = slots.find(s => s.slot_start_iso === iso) || null;
    if (!selectedSlot || isSlotDisabled(selectedSlot)) {
      setDisabled(elGuests, true);
      setDisabled(elCTA, true);
      elGuests.innerHTML = '<option value="1">1</option>';
      return;
    }
    const max = Math.min(50, Math.max(1, parseInt(selectedSlot.left, 10)));
    elGuests.innerHTML = '';
    for (let i = 1; i <= max; i++) {
      const opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = String(i);
      elGuests.appendChild(opt);
    }
    let want = preQty > 0 ? preQty : 1;
    if (want > max) want = max;
    if (want < 1) want = 1;
    elGuests.value = String(want);
    setDisabled(elGuests, false);
    setDisabled(elCTA, false);
  }

  function todayKST() {
    const now = new Date();
    const utc = now.getTime() + now.getTimezoneOffset() * 60000;
    const kst = new Date(utc + 9 * 3600000);
    const y = kst.getUTCFullYear();
    const m = String(kst.getUTCMonth() + 1).padStart(2, '0');
    const d = String(kst.getUTCDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  elDate.addEventListener('change', () => {
    if (elDate.value) {
      loadSlotsForDate(elDate.value);
    }
  });
  elTime.addEventListener('change', onTimeChange);
  elCTA.addEventListener('click', async () => {
    if (!selectedSlot) return;
    const hm = isoToKstHm(selectedSlot.slot_start_iso);
    const qty = parseInt(elGuests.value || '1', 10);
    try {
      const resp = await fetch('/wp-json/kgh/v1/quote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ tour_id: tourId, slot_start_iso: selectedSlot.slot_start_iso, qty })
      });
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({ message: messages.unavailable }));
        if (resp.status === 422) {
          showError(err.message || messages.unavailable);
          await loadSlotsForDate(selectedSlot.slot_start_iso.substring(0,10));
          return;
        }
      }
    } catch (e) {
      showError(messages.networkError);
      return;
    }
    const url = new URL('/checkout/', window.location.origin);
    url.searchParams.set('kgh_tour', String(tourId));
    url.searchParams.set('kgh_date', selectedSlot.slot_start_iso.substring(0, 10));
    url.searchParams.set('kgh_time', hm);
    url.searchParams.set('kgh_qty', String(qty));
    window.location.href = url.toString();
  });

  loadDays();
})();
</script>

<?php get_footer(); ?>
