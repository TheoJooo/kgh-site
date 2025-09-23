// KGH main.js — prêt pour GSAP/animations futures
document.addEventListener('DOMContentLoaded', () => {
  // Test visuel : légère entrée en fondu des cartes
  const cards = document.querySelectorAll('.kgh-card');
  cards.forEach((el, i) => {
    el.style.opacity = 0;
    el.style.transform = 'translateY(8px)';
    requestAnimationFrame(() => {
      setTimeout(() => {
        el.style.transition = 'opacity .35s ease, transform .35s ease';
        el.style.opacity = 1;
        el.style.transform = 'translateY(0)';
      }, 80 * i);
    });
  });
});

// PayPal: crée un Order via REST WordPress puis redirige sur PayPal (approve)
async function kghStartPayPal({ tourDateId, qty, email }) {
  const resp = await fetch('/wp-json/kgh/v1/paypal/order', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': (window.KGHBooking && window.KGHBooking.restNonce) || ''
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      tour_date_id: tourDateId,
      qty: qty,
      customer_email: email || ''
    })
  });
  const data = await resp.json();
  if (!resp.ok) { console.error(data); throw new Error(data.message || 'PayPal error'); }
  window.location.href = data.approve_url;
}

