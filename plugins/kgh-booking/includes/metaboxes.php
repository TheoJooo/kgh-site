<?php
/**
 * Metaboxes & Admin UI for tour_date
 * - Linked Tour select
 * - Session details (price KRW + USD, capacity, date/time, language)
 * - Admin columns + filter
 */
if ( ! defined('ABSPATH') ) exit;

/* ---------------------------------------------
 * 0) Helpers
 * -------------------------------------------*/
function kghb_sanitize_datetime_local($val) {
  $val = trim((string)$val);
  if (!$val) return '';
  $val = str_replace('T', ' ', $val);
  if ( preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $val) ) return $val . ':00';
  if ( preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $val) ) return $val;
  return '';
}
function kghb_format_datetime_for_input($mysql){
  if (!$mysql) return '';
  $mysql = substr($mysql, 0, 16);
  return str_replace(' ', 'T', $mysql);
}

/* ---------------------------------------------
 * 1) Register metas (REST-visible)
 * -------------------------------------------*/
add_action('init', function() {
  $fields = [
    '_kgh_tour_id'        => 'integer',
    '_kgh_price_krw'      => 'integer', // KRW entiers
    '_kgh_price_usd'      => 'number',  // USD décimal (2 décimales)
    '_kgh_capacity_total' => 'integer',
    '_kgh_capacity_ext'   => 'integer',
    '_kgh_capacity_left'  => 'integer', // = booked
    '_kgh_date_start'     => 'string',
    '_kgh_date_end'       => 'string',
    '_kgh_language'       => 'string',
  ];
  foreach ($fields as $key => $type) {
    register_post_meta('tour_date', $key, [
      'type'         => $type,
      'single'       => true,
      'show_in_rest' => true,
      'auth_callback'=> fn() => current_user_can('edit_posts'),
    ]);
  }
});

/* ---------------------------------------------
 * 2) Metabox: Linked Tour
 * -------------------------------------------*/
add_action('add_meta_boxes_tour_date', function () {
  static $ran = false;
  if ($ran) {
    error_log('KGH metabox REG CALLED TWICE: ' . __FILE__ . '::tour_date');
    return;
  }
  $ran = true;

  add_meta_box(
    'kgh_tour_date_link',
    __('Linked Tour', 'kgh-booking'),
    'kgh_render_tour_link_metabox',
    'tour_date',
    'side',
    'high'
  );

  add_meta_box(
    'kgh_tour_date_details',
    __('Session details', 'kgh-booking'),
    'kgh_render_tour_date_details_metabox',
    'tour_date',
    'normal',
    'high'
  );
});
function kgh_render_tour_link_metabox($post) {
  wp_nonce_field('kgh_save_tour_date_link', 'kgh_tour_date_link_nonce');

  $current_tour_id = (int) get_post_meta($post->ID, '_kgh_tour_id', true);
  $tours = get_posts([
    'post_type'      => 'tour',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'post_status'    => ['publish','draft','pending'],
    'fields'         => 'ids',
  ]);

  echo '<select id="kgh_tour_parent" name="kgh_tour_parent" style="width:100%;">';
  echo '<option value="">' . esc_html__('-- Select a Tour --', 'kgh-booking') . '</option>';
  foreach ($tours as $tour_id) {
    printf('<option value="%d"%s>%s</option>',
      $tour_id,
      selected($current_tour_id, $tour_id, false),
      esc_html(get_the_title($tour_id))
    );
  }
  echo '</select>';
}

/* ---------------------------------------------
 * 3) Metabox: Session details
 * -------------------------------------------*/
function kgh_render_tour_date_details_metabox($post) {
  wp_nonce_field('kgh_save_tour_date_details', 'kgh_tour_date_details_nonce');

  $price_krw = (int) get_post_meta($post->ID, '_kgh_price_krw', true);
  $price_usd = get_post_meta($post->ID, '_kgh_price_usd', true);
  $cap_total = (int) get_post_meta($post->ID, '_kgh_capacity_total', true);
  $cap_left  = get_post_meta($post->ID, '_kgh_capacity_left', true);
  $cap_left  = ($cap_left === '') ? '' : (int)$cap_left;
  $date_start= kghb_format_datetime_for_input(get_post_meta($post->ID, '_kgh_date_start', true));
  $date_end  = kghb_format_datetime_for_input(get_post_meta($post->ID, '_kgh_date_end', true));
  $language  = get_post_meta($post->ID, '_kgh_language', true) ?: 'EN';
  ?>

  <p><label><strong><?php _e('Price (KRW)','kgh-booking'); ?></strong></label><br>
    <input type="number" name="kgh_price_krw" min="0" step="1"
           value="<?php echo esc_attr($price_krw); ?>" placeholder="95000" style="width:140px"></p>

  <p><label><strong><?php _e('Price (USD)','kgh-booking'); ?></strong></label><br>
    <input type="number" name="kgh_price_usd" min="0" step="0.01"
           value="<?php echo esc_attr($price_usd); ?>" placeholder="85.00" style="width:140px">
    <span class="description"><?php esc_html_e('Used for PayPal (USD master).', 'kgh-booking'); ?></span>
  </p>

  <p><label><strong><?php _e('Capacity total','kgh-booking'); ?></strong></label><br>
    <input type="number" name="kgh_capacity_total" min="1" step="1"
           value="<?php echo esc_attr($cap_total); ?>" placeholder="16" style="width:120px"></p>

  <p><label><strong><?php _e('External bookings (manual)','kgh-booking'); ?></strong></label><br>
    <input type="number" name="kgh_capacity_ext" min="0" step="1"
           value="<?php echo esc_attr( get_post_meta($post->ID, '_kgh_capacity_ext', true) ); ?>"
           placeholder="TripAdvisor / WhatsApp / phone, etc." style="width:160px">
  </p>

  <?php
  $cap_total = (int) get_post_meta($post->ID, '_kgh_capacity_total', true);
  $cap_ext   = (int) get_post_meta($post->ID, '_kgh_capacity_ext', true); // nouveau champ “External bookings”
  // UI ne lit plus jamais _kgh_capacity_left — fallback site=0 si helper absent
  $cap_site  = function_exists('kgh_capacity_booked_site_qty') ? kgh_capacity_booked_site_qty($post->ID) : 0;
  $cap_booked= max(0, $cap_site + $cap_ext);
  $cap_left  = max(0, $cap_total - $cap_booked);
  ?>
  <p style="margin-top:10px; padding:10px 12px; background:#f6f7f7; border:1px solid #ddd; border-radius:8px;">
    <strong><?php _e('Live capacity (read-only):','kgh-booking'); ?></strong><br>
    <?php printf(
      __('Site %1$d / Ext %2$d · Booked %3$d · Left %4$d · Total %5$d','kgh-booking'),
      (int)$cap_site, (int)$cap_ext, (int)$cap_booked, (int)$cap_left, (int)$cap_total
    ); ?>
  </p>

  

  <p><label><strong><?php _e('Start datetime','kgh-booking'); ?></strong></label><br>
    <input type="datetime-local" name="kgh_date_start" value="<?php echo esc_attr($date_start); ?>"></p>

  <p><label><strong><?php _e('End datetime','kgh-booking'); ?></strong></label><br>
    <input type="datetime-local" name="kgh_date_end" value="<?php echo esc_attr($date_end); ?>"></p>

  <p><label><strong><?php _e('Language','kgh-booking'); ?></strong></label><br>
    <select name="kgh_language">
      <?php foreach(['EN','KR','FR'] as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php selected($language,$opt); ?>><?php echo $opt; ?></option>
      <?php endforeach; ?>
    </select></p>

  <?php
}

/* ---------------------------------------------
 * 4) Save handler
 * -------------------------------------------*/
add_action('save_post', function($post_id) {
  if (get_post_type($post_id) !== 'tour_date') return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  // Linked Tour
  if (isset($_POST['kgh_tour_date_link_nonce']) && wp_verify_nonce($_POST['kgh_tour_date_link_nonce'], 'kgh_save_tour_date_link')) {
    $tour_id = isset($_POST['kgh_tour_parent']) ? intval($_POST['kgh_tour_parent']) : 0;
    if ($tour_id > 0 && get_post_type($tour_id) === 'tour') update_post_meta($post_id,'_kgh_tour_id',$tour_id);
    else delete_post_meta($post_id,'_kgh_tour_id');
  }

  // Session details
  if (!isset($_POST['kgh_tour_date_details_nonce']) || !wp_verify_nonce($_POST['kgh_tour_date_details_nonce'], 'kgh_save_tour_date_details')) return;

  // Prix KRW (int)
  $price_krw = isset($_POST['kgh_price_krw']) ? max(0, intval($_POST['kgh_price_krw'])) : 0;
  update_post_meta($post_id,'_kgh_price_krw',$price_krw);

  // Prix USD (float à 2 décimales)
  if (isset($_POST['kgh_price_usd']) && $_POST['kgh_price_usd'] !== '') {
    $usd = round((float)$_POST['kgh_price_usd'], 2);
    if ($usd > 0) update_post_meta($post_id,'_kgh_price_usd', $usd);
    else delete_post_meta($post_id,'_kgh_price_usd');
  } else {
    delete_post_meta($post_id,'_kgh_price_usd');
  }

  $cap_total = isset($_POST['kgh_capacity_total']) ? max(1,intval($_POST['kgh_capacity_total'])) : 1;
  update_post_meta($post_id,'_kgh_capacity_total',$cap_total);

  // External manual bookings (nouveau champ)
  $cap_ext = isset($_POST['kgh_capacity_ext']) ? max(0, intval($_POST['kgh_capacity_ext'])) : 0;
  update_post_meta($post_id, '_kgh_capacity_ext', $cap_ext);

  // Nettoyage hérité: on supprime l'ancien compteur pour éviter toute réapparition
  delete_post_meta($post_id, '_kgh_capacity_left');

  $date_start= isset($_POST['kgh_date_start']) ? kghb_sanitize_datetime_local($_POST['kgh_date_start']) : '';
  $date_end  = isset($_POST['kgh_date_end']) ? kghb_sanitize_datetime_local($_POST['kgh_date_end']) : '';
  if ($date_start) update_post_meta($post_id,'_kgh_date_start',$date_start); else delete_post_meta($post_id,'_kgh_date_start');
  if ($date_end)   update_post_meta($post_id,'_kgh_date_end',$date_end);   else delete_post_meta($post_id,'_kgh_date_end');

  $lang = in_array($_POST['kgh_language'] ?? 'EN',['EN','KR','FR'], true) ? $_POST['kgh_language'] : 'EN';
  update_post_meta($post_id,'_kgh_language',$lang);
});

/* ---------------------------------------------
 * 5) Admin columns + filter
 * -------------------------------------------*/
add_filter('manage_tour_date_posts_columns', function($cols){
  $date=$cols['date']??null; unset($cols['date']);
  $cols['kgh_tour']='Tour';
  $cols['kgh_price']='Price';
  $cols['kgh_start']='Start';
  $cols['kgh_cap']='Booked / Total';
  $cols['kgh_lang']='Lang';
  if($date) $cols['date']=$date;
  return $cols;
});
add_action('manage_tour_date_posts_custom_column', function($col,$id){
  switch($col){
    case 'kgh_tour':
      $tid=get_post_meta($id,'_kgh_tour_id',true);
      echo $tid?esc_html(get_the_title($tid)):'—';
      break;

    case 'kgh_price':
      $usd = get_post_meta($id,'_kgh_price_usd',true);
      $krw = (int) get_post_meta($id,'_kgh_price_krw',true);
      if ($usd !== '' && $usd > 0) {
        echo '$'.number_format((float)$usd, 2).' USD';
        if ($krw > 0) echo ' <span style="opacity:.7">· '.number_format_i18n($krw).' KRW</span>';
      } else {
        echo number_format_i18n($krw).' KRW';
      }
      break;

    case 'kgh_start':
      echo esc_html(get_post_meta($id,'_kgh_date_start',true));
      break;

    case 'kgh_cap':
      // Toujours basé sur le modèle calculé; aucun fallback sur _kgh_capacity_left
      $site  = function_exists('kgh_capacity_booked_site_qty') ? kgh_capacity_booked_site_qty($id) : 0;
      $ext   = (int) get_post_meta($id, '_kgh_capacity_ext', true);
      if (!$ext) $ext = (int) get_post_meta($id, '_kgh_booked_manual', true); // fallback legacy
      $total = (int) get_post_meta($id, '_kgh_capacity_total', true);
      $booked= $site + $ext;
      $left  = max(0, $total - $booked);
      echo esc_html("Booked {$booked} (site {$site} / ext {$ext}) · Left {$left} · Total {$total}");
      break;

    case 'kgh_lang':
      echo esc_html(get_post_meta($id,'_kgh_language',true));
      break;
  }
},10,2);

add_action('current_screen',function($screen){
  if($screen && $screen->id==='edit-tour_date'){
    add_action('restrict_manage_posts','kghb_render_tour_filter',9);
    add_action('pre_get_posts','kghb_apply_tour_filter');
  }
});
function kghb_render_tour_filter(){
  $tours=get_posts(['post_type'=>'tour','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','post_status'=>['publish','draft','pending'],'fields'=>'ids']);
  if(empty($tours)) return;
  $sel=intval($_GET['kgh_tour_filter']??0);
  echo '<select name="kgh_tour_filter" id="kgh_tour_filter" class="postform"><option value="">All Tours</option>';
  foreach($tours as $tid){printf('<option value="%d"%s>%s</option>',$tid,selected($sel,$tid,false),esc_html(get_the_title($tid)));}
  echo '</select>';
}
function kghb_apply_tour_filter($q){
  if(!is_admin()||!$q->is_main_query()||$q->get('post_type')!=='tour_date')return;
  if(!empty($_GET['kgh_tour_filter'])) {
    $q->set('meta_query',[
      ['key'=>'_kgh_tour_id','value'=>intval($_GET['kgh_tour_filter']),'compare'=>'=']
    ]);
  }
}
