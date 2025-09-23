<?php
if ( ! defined('ABSPATH') ) exit;

// --- CAPACITY MODEL ---------------------------------------------------------
// Booked_total = Booked_site (somme des bookings WP) + Booked_manual (champ meta admin)
// Left = max(0, Total - Booked_total)
// ---------------------------------------------------------------------------

/**
 * Bookings "site" (CPT booking/kgh_booking) → somme du meta qty pour un tour_date donné.
 * Supporte les anciennes clés meta sans underscore et les nouvelles avec underscore.
 */
function kgh_capacity_booked_site_qty( int $tour_date_id ): int {
    $t_key = 'kgh_booked_site_' . $tour_date_id;
    $cached = get_transient($t_key);
    if ($cached !== false) return (int) $cached;

    $q = new WP_Query([
        'post_type'      => ['booking','kgh_booking'],
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'kgh_tour_date_id',
                'value'   => $tour_date_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => '_kgh_tour_date_id',
                'value'   => $tour_date_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
        'no_found_rows'  => true,
    ]);

    $booked = 0;
    if ($q->have_posts()) {
        foreach ($q->posts as $bid) {
            $qty = get_post_meta($bid, 'kgh_qty', true);
            if ($qty === '' || $qty === null) {
                $qty = get_post_meta($bid, '_kgh_qty', true);
            }
            $qty = (int) $qty;
            $booked += max(1, $qty ?: 1);
        }
    }
    set_transient($t_key, $booked, 5 * MINUTE_IN_SECONDS);
    return $booked;
}

/**
 * Bookings "manuel" (réservations externes TripAdvisor, téléphone, etc.).
 * Historique : on réutilise l'ancien meta 'kgh_capacity_reserved' si présent,
 * sinon le nouveau 'kgh_booked_manual'. Les deux sont supportés.
 */
function kgh_capacity_booked_manual( int $tour_date_id ): int {
    // nouveau champ (préféré)
    $v = get_post_meta($tour_date_id, '_kgh_capacity_ext', true);
    if ($v === '' || $v === null) {
        // support legacy nouvel essai
        $v = get_post_meta($tour_date_id, '_kgh_booked_manual', true);
    }
    if ($v === '' || $v === null) {
        // fallback legacy sans underscore
        $v = get_post_meta($tour_date_id, 'kgh_booked_manual', true);
    }
    if ($v === '' || $v === null) {
        // rétrocompat : certains projets avaient 'kgh_capacity_reserved'
        $v = get_post_meta($tour_date_id, 'kgh_capacity_reserved', true);
    }
    $n = (int) $v;
    return max(0, $n);
}

/** Total paramétré dans le CPT tour_date (supporte _kgh_capacity_total et kgh_capacity_total). */
function kgh_capacity_total( int $tour_date_id ): int {
    $v = get_post_meta($tour_date_id, '_kgh_capacity_total', true);
    if ($v === '' || $v === null) {
        $v = get_post_meta($tour_date_id, 'kgh_capacity_total', true);
    }
    return max(0, (int) $v);
}

/** Booked total = site + manuel. */
function kgh_capacity_booked_total( int $tour_date_id ): int {
    return max(0, kgh_capacity_booked_site_qty($tour_date_id) + kgh_capacity_booked_manual($tour_date_id));
}

/** Restant = total - booked_total (jamais négatif). */
function kgh_capacity_left( int $tour_date_id ): int {
    return max(0, kgh_capacity_total($tour_date_id) - kgh_capacity_booked_total($tour_date_id));
}

/** Invalidation caches quand un booking change. */
function kgh_capacity_invalidate_cache( int $tour_date_id ): void {
    delete_transient('kgh_booked_site_' . $tour_date_id);
}

// Alias simple demandé : kgh_capacity_invalidate()
if (!function_exists('kgh_capacity_invalidate')) {
    function kgh_capacity_invalidate( int $tour_date_id ): void {
        kgh_capacity_invalidate_cache($tour_date_id);
    }
}

// Invalider quand on crée/edite/supprime un booking (site) — supporte 2 slugs.
add_action('save_post_booking', function($post_id, $post, $update){
    $td = (int) get_post_meta($post_id, '_kgh_tour_date_id', true);
    if (!$td) $td = (int) get_post_meta($post_id, 'kgh_tour_date_id', true);
    if ($td) kgh_capacity_invalidate_cache($td);
}, 10, 3);
add_action('save_post_kgh_booking', function($post_id, $post, $update){
    $td = (int) get_post_meta($post_id, '_kgh_tour_date_id', true);
    if (!$td) $td = (int) get_post_meta($post_id, 'kgh_tour_date_id', true);
    if ($td) kgh_capacity_invalidate_cache($td);
}, 10, 3);

add_action('before_delete_post', function($post_id){
    $pt = get_post_type($post_id);
    if ($pt !== 'booking' && $pt !== 'kgh_booking') return;
    $td = (int) get_post_meta($post_id, '_kgh_tour_date_id', true);
    if (!$td) $td = (int) get_post_meta($post_id, 'kgh_tour_date_id', true);
    if ($td) kgh_capacity_invalidate_cache($td);
});
