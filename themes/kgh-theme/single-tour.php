<?php
/**
 * Template: Single Tour
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

// Helpers (no side effects if SCF missing)
if (!function_exists('kgh_scf_get')) {
  function kgh_scf_get($key, $post_id = null, $default = null) {
    $post_id = $post_id ? $post_id : get_the_ID();
    if (empty($key)) return $default;
    // SCF first
    if (class_exists('SCF')) {
      try {
        $v = SCF::get($key, $post_id);
        if ($v !== null && $v !== '' && $v !== []) return $v;
      } catch (Throwable $e) {
        // fall-through
      }
    }
    // Fallback to post meta
    $meta = get_post_meta($post_id, $key, true);
    if ($meta !== '' && $meta !== null) return $meta;
    return $default;
  }
}

if (!function_exists('kgh_norm_list')) {
  function kgh_norm_list($raw) {
    // Accept array of objects/arrays/strings or textarea (string with newlines)
    $out = [];
    if (is_string($raw)) {
      $lines = preg_split('/\r\n|\r|\n/', $raw);
      foreach ($lines as $ln) {
        $ln = trim((string)$ln);
        if ($ln !== '') $out[] = $ln;
      }
      return $out;
    }
    if (is_array($raw)) {
      foreach ($raw as $item) {
        if (is_string($item)) {
          $val = trim($item);
          if ($val !== '') $out[] = $val;
          continue;
        }
        if (is_array($item)) {
          $candidate = '';
          foreach (['text', 'label', 'value', 'content', 'title'] as $key) {
            if (isset($item[$key])) {
              $candidate = (string) $item[$key];
              break;
            }
          }
          if ($candidate === '' && isset($item[0])) {
            $candidate = (string) $item[0];
          }
          $candidate = trim($candidate);
          if ($candidate !== '') $out[] = $candidate;
          continue;
        }
        if (is_object($item)) {
          $candidate = '';
          foreach (['text', 'label', 'value', 'content', 'title'] as $key) {
            if (isset($item->{$key})) {
              $candidate = (string) $item->{$key};
              break;
            }
          }
          $candidate = trim($candidate);
          if ($candidate !== '') $out[] = $candidate;
          continue;
        }
      }
      return $out;
    }
    return $out;
  }
}

?>
<!-- KGH DEBUG: single-tour.php loaded -->
<?php

// Debug helpers (disable after validation)
// $all_meta = get_post_meta( get_the_ID(), '', true );
// echo '<!-- KGH META SAMPLE: ' . esc_html( wp_json_encode( array_slice( $all_meta, 0, 10, true ) ) ) . ' -->';
// if ( class_exists( 'SCF' ) ) {
//   $scf_payload = SCF::gets( get_the_ID() );
//   echo '<!-- KGH SCF SNAPSHOT: ' . esc_html( wp_json_encode( $scf_payload ) ) . ' -->';
// }

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
    <?php echo '<!-- single-tour.php ACTIVE -->'; ?>
    <?php
    $pid = get_the_ID();

    if (class_exists('SCF')) {
      $overline   = SCF::get('title_overline', $pid);
      $title_cus  = SCF::get('title', $pid);
      $subtitle   = SCF::get('subtitle_badge', $pid);
      $intro_html = SCF::get('intro_richtext', $pid);

      $location_t = SCF::get('location_text', $pid);
      $duration_t = SCF::get('duration_text', $pid);
      $capacity_t = SCF::get('capacity_text', $pid);
    } else {
      $overline   = get_post_meta($pid, 'title_overline', true);
      $title_cus  = get_post_meta($pid, 'title', true);
      $subtitle   = get_post_meta($pid, 'subtitle_badge', true);
      $intro_html = get_post_meta($pid, 'intro_richtext', true);

      $location_t = get_post_meta($pid, 'location_text', true);
      $duration_t = get_post_meta($pid, 'duration_text', true);
      $capacity_t = get_post_meta($pid, 'capacity_text', true);
    }

    $price_cents = (int) get_post_meta($pid, 'price_usd_cents', true);
    if ($price_cents <= 0) {
      $price_cents = (int) get_post_meta($pid, 'price_cents', true);
    }

    $price_t = '';
    if ($price_cents > 0) {
      $price_t = '$' . number_format($price_cents / 100, 2);
    } else {
      $price_float = (float) get_post_meta($pid, '_kgh_price_usd', true);
      if ($price_float > 0) {
        $price_t = '$' . number_format($price_float, 2);
      }
    }
    ?>

    <section class="kgh-tour-hero">
      <?php if (!empty($overline)) : ?>
        <p class="kgh-overline"><?php echo esc_html($overline); ?></p>
      <?php endif; ?>

      <h1 class="kgh-hero-title"><?php echo esc_html($title_cus ?: get_the_title($pid)); ?></h1>

      <?php if (!empty($subtitle)) : ?>
        <p class="kgh-subtitle"><?php echo esc_html($subtitle); ?></p>
      <?php endif; ?>

      <?php if (!empty($intro_html)) : ?>
        <div class="kgh-hero-intro"><?php echo wp_kses_post($intro_html); ?></div>
      <?php endif; ?>

      <?php if ($location_t || $duration_t || $capacity_t || $price_t) : ?>
        <ul class="kgh-hero-facts">
          <?php if (!empty($location_t)) : ?><li><?php echo esc_html($location_t); ?></li><?php endif; ?>
          <?php if (!empty($duration_t)) : ?><li><?php echo esc_html($duration_t); ?></li><?php endif; ?>
          <?php if (!empty($capacity_t)) : ?><li><?php echo esc_html($capacity_t); ?></li><?php endif; ?>
          <?php if (!empty($price_t)) :    ?><li><?php echo esc_html($price_t); ?></li><?php endif; ?>
        </ul>
      <?php endif; ?>

      <?php
      $badges = wp_get_object_terms($pid, 'kgh_badge', ['hide_empty' => false]);
      if (!is_wp_error($badges) && $badges) {
        $badge_svg_allowed = [
          'svg' => [
            'xmlns' => true,
            'xmlns:xlink' => true,
            'viewBox' => true,
            'width' => true,
            'height' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'aria-hidden' => true,
            'role' => true,
            'focusable' => true,
            'class' => true,
            'style' => true,
          ],
          'g' => [
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
            'transform' => true,
          ],
          'path' => [
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
            'fill-rule' => true,
            'clip-rule' => true,
          ],
          'circle' => [
            'cx' => true,
            'cy' => true,
            'r' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
          ],
          'rect' => [
            'x' => true,
            'y' => true,
            'width' => true,
            'height' => true,
            'rx' => true,
            'ry' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
          ],
          'line' => [
            'x1' => true,
            'y1' => true,
            'x2' => true,
            'y2' => true,
            'stroke' => true,
            'stroke-width' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
          ],
          'polyline' => [
            'points' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
          ],
          'polygon' => [
            'points' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
          ],
          'title' => [],
          'defs' => [],
          'clipPath' => [ 'id' => true ],
          'linearGradient' => [
            'id' => true,
            'gradientUnits' => true,
            'x1' => true,
            'y1' => true,
            'x2' => true,
            'y2' => true,
          ],
          'stop' => [
            'offset' => true,
            'stop-color' => true,
            'stop-opacity' => true,
          ],
          'use' => [
            'href' => true,
            'xlink:href' => true,
          ],
          'ellipse' => [
            'cx' => true,
            'cy' => true,
            'rx' => true,
            'ry' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'class' => true,
            'style' => true,
            'opacity' => true,
          ],
        ];
        echo '<div class="kgh-badges">';
        foreach ($badges as $t) {
          $short = get_term_meta($t->term_id, 'short_label', true);
          $short = $short ? $short : $t->name;
          $icon  = get_term_meta($t->term_id, 'icon_svg', true);
          echo '<span class="kgh-badge">';
          if (!empty($icon)) {
            echo '<span class="kgh-badge-ico">' . wp_kses($icon, $badge_svg_allowed) . '</span>';
          }
          echo '<span class="kgh-badge-label">' . esc_html($short) . '</span>';
          echo '</span>';
        }
        echo '</div>';
      }
      ?>
    </section>

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

    <?php
    // =============================
    // Section 2/3 blocks
    // =============================

    // A. Highlights
    $highlights = kgh_scf_get('highlights', $pid, []);
    if (is_array($highlights) && count($highlights)) {
      $items = [];
      $highlight_svg_allowed = [
        'svg'   => ['class' => true, 'width' => true, 'height' => true, 'viewBox' => true, 'fill' => true, 'stroke' => true, 'xmlns' => true, 'aria-hidden' => true, 'role' => true, 'focusable' => true],
        'path'  => ['d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true],
        'g'     => ['fill' => true, 'stroke' => true, 'stroke-width' => true],
        'rect'  => ['x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true],
        'circle'=> ['cx' => true, 'cy' => true, 'r' => true, 'fill' => true],
        'title' => [],
      ];
      foreach ($highlights as $row) {
        $text = '';
        $icon_svg_raw = '';
        $icon_img_raw = '';
        if (is_array($row)) {
          $text = isset($row['text']) ? (string) $row['text'] : (isset($row['label']) ? (string) $row['label'] : (isset($row['title']) ? (string) $row['title'] : ''));
          $icon_svg_raw = isset($row['icon_svg']) ? (string) $row['icon_svg'] : '';
          foreach (['icon', 'icon_img', 'image', 'img'] as $img_key) {
            if (isset($row[$img_key])) {
              $icon_img_raw = $row[$img_key];
              break;
            }
          }
        } elseif (is_object($row)) {
          $text = isset($row->text) ? (string) $row->text : (isset($row->label) ? (string) $row->label : (isset($row->title) ? (string) $row->title : ''));
          $icon_svg_raw = isset($row->icon_svg) ? (string) $row->icon_svg : '';
          foreach (['icon', 'icon_img', 'image', 'img'] as $img_key) {
            if (isset($row->{$img_key})) {
              $icon_img_raw = $row->{$img_key};
              break;
            }
          }
        } else {
          $text = (string) $row;
        }

        $text = trim($text);
        if ($text === '') {
          continue;
        }

        $icon_html = '';
        if (!empty($icon_svg_raw)) {
          $icon_html = wp_kses($icon_svg_raw, $highlight_svg_allowed);
        } elseif (!empty($icon_img_raw)) {
          if (is_numeric($icon_img_raw)) {
            $img_id = (int) $icon_img_raw;
            $icon_html = wp_get_attachment_image(
              $img_id,
              'thumbnail',
              false,
              [
                'class'    => 'kgh-highlight-img',
                'loading'  => 'lazy',
                'decoding' => 'async',
                'alt'      => '',
              ]
            );
          } elseif (is_array($icon_img_raw)) {
            $img_id = 0;
            if (isset($icon_img_raw['id']) && is_numeric($icon_img_raw['id'])) {
              $img_id = (int) $icon_img_raw['id'];
            } elseif (isset($icon_img_raw['attachment_id']) && is_numeric($icon_img_raw['attachment_id'])) {
              $img_id = (int) $icon_img_raw['attachment_id'];
            }
            if ($img_id) {
              $icon_html = wp_get_attachment_image(
                $img_id,
                'thumbnail',
                false,
                [
                  'class'    => 'kgh-highlight-img',
                  'loading'  => 'lazy',
                  'decoding' => 'async',
                  'alt'      => '',
                ]
              );
            } elseif (!empty($icon_img_raw['url']) && is_string($icon_img_raw['url'])) {
              $icon_html = '<img class="kgh-highlight-img" src="' . esc_url($icon_img_raw['url']) . '" alt="" loading="lazy" decoding="async">';
            }
          } elseif (is_object($icon_img_raw)) {
            $img_id = 0;
            if (isset($icon_img_raw->id) && is_numeric($icon_img_raw->id)) {
              $img_id = (int) $icon_img_raw->id;
            } elseif (isset($icon_img_raw->attachment_id) && is_numeric($icon_img_raw->attachment_id)) {
              $img_id = (int) $icon_img_raw->attachment_id;
            }
            if ($img_id) {
              $icon_html = wp_get_attachment_image(
                $img_id,
                'thumbnail',
                false,
                [
                  'class'    => 'kgh-highlight-img',
                  'loading'  => 'lazy',
                  'decoding' => 'async',
                  'alt'      => '',
                ]
              );
            } elseif (!empty($icon_img_raw->url) && is_string($icon_img_raw->url)) {
              $icon_html = '<img class="kgh-highlight-img" src="' . esc_url($icon_img_raw->url) . '" alt="" loading="lazy" decoding="async">';
            }
          } elseif (is_string($icon_img_raw)) {
            $icon_html = '<img class="kgh-highlight-img" src="' . esc_url(trim($icon_img_raw)) . '" alt="" loading="lazy" decoding="async">';
          }
        }

        $items[] = [
          'text'      => $text,
          'icon_html' => $icon_html,
        ];
      }
      if (count($items)) : ?>
        <section class="kgh-sec kgh-highlights">
          <h2 class="kgh-sec-title"><?php echo esc_html__( 'Highlights', 'kgh-booking' ); ?></h2>
          <ul class="kgh-highlights-grid" role="list">
            <?php foreach ($items as $it): ?>
              <li class="kgh-highlight" role="listitem">
                <?php if (!empty($it['icon_html'])): ?>
                  <span class="kgh-highlight-icon kgh-highlight-ico" aria-hidden="true"><?php echo $it['icon_html']; ?></span>
                <?php endif; ?>
                <span class="kgh-highlight-text"><?php echo esc_html($it['text']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; }

    // B. Itinerary
    $itinerary = kgh_scf_get('itinerary', $pid, []);
    if (is_array($itinerary) && count($itinerary)) {
      $steps = [];
      foreach ($itinerary as $row) {
        $time = $title_i = $desc_i = '';
        if (is_array($row)) {
          $time = isset($row['time']) ? trim((string)$row['time']) : '';
          $title_i = isset($row['title']) ? trim((string)$row['title']) : '';
          $desc_i = isset($row['desc']) ? trim((string)$row['desc']) : '';
        } else if (is_object($row)) {
          $time = isset($row->time) ? trim((string)$row->time) : '';
          $title_i = isset($row->title) ? trim((string)$row->title) : '';
          $desc_i = isset($row->desc) ? trim((string)$row->desc) : '';
        }
        if ($time !== '' || $title_i !== '' || $desc_i !== '') $steps[] = compact('time','title_i','desc_i');
      }
      if (count($steps)) : ?>
        <section class="kgh-sec kgh-itinerary">
          <h2 class="kgh-sec-title"><?php echo esc_html__( 'Itinerary', 'kgh-booking' ); ?></h2>
          <ol class="kgh-itinerary-list">
            <?php foreach ($steps as $st): ?>
              <li class="kgh-itinerary-item">
                <?php if ($st['time'] !== ''): ?>
                  <span class="kgh-itin-time" aria-label="<?php esc_attr_e('Time', 'kgh-booking'); ?>"><?php echo esc_html($st['time']); ?></span>
                <?php endif; ?>
                <div class="kgh-itin-body">
                  <?php if ($st['title_i'] !== ''): ?>
                    <h3 class="kgh-itin-title"><?php echo esc_html($st['title_i']); ?></h3>
                  <?php endif; ?>
                  <?php if ($st['desc_i'] !== ''): ?>
                    <div class="kgh-itin-desc"><?php echo wp_kses_post( wpautop( $st['desc_i'] ) ); ?></div>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </section>
      <?php endif; }

    // C. Includes / Not included
    $includes_raw = kgh_scf_get('includes', $pid, []);
    $excludes_raw = kgh_scf_get('excludes', $pid, []);
    $includes = kgh_norm_list($includes_raw);
    $excludes = kgh_norm_list($excludes_raw);
    if (!empty($includes) || !empty($excludes)) : ?>
      <section class="kgh-sec kgh-includes">
        <h2 class="kgh-sec-title"><?php echo esc_html__( 'Included / Not included', 'kgh-booking' ); ?></h2>
        <div class="kgh-includes-grid">
          <?php if (!empty($includes)) : ?>
            <div class="kgh-inc-col">
              <h3 class="kgh-inc-heading"><?php echo esc_html__( 'Included', 'kgh-booking' ); ?></h3>
              <ul class="kgh-inc-list" role="list">
                <?php foreach ($includes as $li): ?>
                  <li class="kgh-inc kgh-inc--ok"><?php echo esc_html($li); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (!empty($excludes)) : ?>
            <div class="kgh-inc-col">
              <h3 class="kgh-inc-heading"><?php echo esc_html__( 'Not included', 'kgh-booking' ); ?></h3>
              <ul class="kgh-inc-list" role="list">
                <?php foreach ($excludes as $li): ?>
                  <li class="kgh-inc kgh-inc--no"><?php echo esc_html($li); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php
    // D. Meeting point
    $mp_title = trim((string) kgh_scf_get('meeting_point_title', $pid, ''));
    $mp_addr  = trim((string) kgh_scf_get('meeting_point_address', $pid, ''));
    $mp_note  = trim((string) kgh_scf_get('meeting_point_note', $pid, ''));
    $mp_iframe = kgh_scf_get('meeting_point_map_iframe', $pid, '');
    $has_iframe = is_string($mp_iframe) && trim($mp_iframe) !== '';
    if ($mp_title !== '' || $mp_addr !== '' || $mp_note !== '' || $has_iframe) :
      $iframe_allowed = [
        'iframe' => [
          'src' => true, 'width' => true, 'height' => true, 'style' => true,
          'referrerpolicy' => true, 'allowfullscreen' => true, 'loading' => true
        ]
      ];
      ?>
      <section class="kgh-sec kgh-meeting">
        <h2 class="kgh-sec-title"><?php echo esc_html__( 'Meeting point', 'kgh-booking' ); ?></h2>
        <div class="kgh-meeting-grid">
          <?php if ($mp_title !== '' || $mp_addr !== '' || $mp_note !== ''): ?>
            <div class="kgh-meeting-info">
              <?php if ($mp_title !== ''): ?>
                <h3 class="kgh-meeting-title"><?php echo esc_html($mp_title); ?></h3>
              <?php endif; ?>
              <?php if ($mp_addr !== ''): ?>
                <p class="kgh-meeting-address"><?php echo esc_html($mp_addr); ?></p>
              <?php endif; ?>
              <?php if ($mp_note !== ''): ?>
                <p class="kgh-meeting-note"><?php echo esc_html($mp_note); ?></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($has_iframe): ?>
            <div class="kgh-meeting-map">
              <div class="kgh-iframe-16x9">
                <?php echo wp_kses( $mp_iframe, $iframe_allowed ); ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php
    // E. Gallery
    $gallery_raw = kgh_scf_get('gallery', $pid, []);
    $gallery_ids = [];
    if (is_string($gallery_raw)) {
      // Could be CSV or a single URL
      $str = trim($gallery_raw);
      if ($str !== '') {
        if (preg_match('/^https?:\/\//i', $str)) {
          $maybe = attachment_url_to_postid($str);
          if ($maybe) $gallery_ids[] = $maybe;
        } else {
          foreach (preg_split('/\s*,\s*/', $str) as $tok) {
            $id = absint($tok); if ($id) $gallery_ids[] = $id;
          }
        }
      }
    } elseif (is_array($gallery_raw)) {
      foreach ($gallery_raw as $g) {
        if (is_numeric($g)) { $id = absint($g); if ($id) $gallery_ids[] = $id; continue; }
        if (is_string($g)) {
          if (preg_match('/^https?:\/\//i', $g)) {
            $maybe = attachment_url_to_postid($g);
            if ($maybe) $gallery_ids[] = $maybe;
          } else { $id = absint($g); if ($id) $gallery_ids[] = $id; }
          continue;
        }
        if (is_array($g)) {
          foreach (['id', 'attachment_id', 'value', 'image'] as $key) {
            if (isset($g[$key]) && is_numeric($g[$key])) {
              $id = absint($g[$key]);
              if ($id) { $gallery_ids[] = $id; break; }
            }
          }
          if (isset($g['url']) && is_string($g['url'])) {
            $maybe = attachment_url_to_postid($g['url']);
            if ($maybe) $gallery_ids[] = $maybe;
          }
          continue;
        }
        if (is_object($g)) {
          foreach (['id', 'attachment_id', 'value', 'image'] as $key) {
            if (isset($g->{$key}) && is_numeric($g->{$key})) {
              $id = absint($g->{$key});
              if ($id) { $gallery_ids[] = $id; break; }
            }
          }
          if (isset($g->url) && is_string($g->url)) {
            $maybe = attachment_url_to_postid($g->url);
            if ($maybe) $gallery_ids[] = $maybe;
          }
        }
      }
    }
    $gallery_ids = array_values(array_unique(array_filter(array_map('absint', $gallery_ids))));
    if (!empty($gallery_ids)) : ?>
      <section class="kgh-sec kgh-gallery">
        <h2 class="kgh-sec-title"><?php echo esc_html__( 'Gallery', 'kgh-booking' ); ?></h2>
        <div class="kgh-gallery-grid">
          <?php foreach ($gallery_ids as $gid): ?>
            <figure class="kgh-gallery-item">
              <?php echo wp_get_attachment_image( $gid, 'large', false, [ 'loading' => 'lazy', 'class' => 'kgh-gallery-img' ] ); ?>
            </figure>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php
    // F. FAQ
    $faq = kgh_scf_get('faq', $pid, []);
    if (is_array($faq) && count($faq)) {
      $faqs = [];
      foreach ($faq as $row) {
        $q = $a = '';
        if (is_array($row)) {
          $q = isset($row['q']) ? trim((string)$row['q']) : '';
          $a = isset($row['a']) ? trim((string)$row['a']) : '';
        } elseif (is_object($row)) {
          $q = isset($row->q) ? trim((string)$row->q) : '';
          $a = isset($row->a) ? trim((string)$row->a) : '';
        }
        if ($q !== '' || $a !== '') $faqs[] = compact('q','a');
      }
      if (count($faqs)) : ?>
        <section class="kgh-sec kgh-faq">
          <h2 class="kgh-sec-title"><?php echo esc_html__( 'FAQ', 'kgh-booking' ); ?></h2>
          <div class="kgh-faq-list">
            <?php foreach ($faqs as $f): ?>
              <details class="kgh-faq-item">
                <summary class="kgh-faq-q"><?php echo esc_html($f['q']); ?></summary>
                <div class="kgh-faq-a"><?php echo wp_kses_post( wpautop( $f['a'] ) ); ?></div>
              </details>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; }
    ?>

    <?php /* QA checklist V1
    - Données complètes: toutes sections visibles, responsive OK
    - Données partielles: sections vides masquées
    - Gallery: IDs/URLs/CSV -> images lazy OK
    - Includes/Excludes: textarea multi-lignes -> puces OK
    - Meeting: iframe Google Maps responsive 16:9
    - Accessibilité: FAQ clavier; aria-label sur l'heure d'itinéraire
    - i18n: textdomain kgh-booking partout
    - Sécurité: wp_kses pour SVG/iframe; échappements
    - Non-régression: Reserve/Checkout flows inchangés; 0 erreur console
    - Lighthouse: CLS inchangé
    */ ?>
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
