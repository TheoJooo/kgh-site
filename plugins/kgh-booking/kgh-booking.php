<?php
/**
 * Plugin Name: KGH Booking
 * Description: Gestion des tours, dates et réservations pour Korean Gourmet Hunters.
 * Version: 0.1.0
 * Author: Theo J.
 * Text Domain: kgh-booking
 */

if ( ! defined('ABSPATH') ) exit; // Sécurité

// Define constants
define('KGH_BOOKING_PATH', plugin_dir_path(__FILE__));
define('KGH_BOOKING_URL', plugin_dir_url(__FILE__));
if (!defined('KGH_BOOKING_VERSION')) {
  define('KGH_BOOKING_VERSION', '0.1.0');
}

// Charger fichiers inclus
require_once KGH_BOOKING_PATH . 'includes/cpt.php';
require_once KGH_BOOKING_PATH . 'includes/bookings.php'; // helpers booking + emails stub
require_once KGH_BOOKING_PATH . 'includes/utils.php';
require_once KGH_BOOKING_PATH . 'includes/exceptions.php';
require_once KGH_BOOKING_PATH . 'includes/availability.php';

require_once KGH_BOOKING_PATH . 'includes/metaboxes.php';
require_once KGH_BOOKING_PATH . 'includes/schedule.php';
require_once KGH_BOOKING_PATH . 'includes/paypal-http.php';
require_once KGH_BOOKING_PATH . 'includes/rest-paypal.php';   // << NOUVEAU (create order)
require_once KGH_BOOKING_PATH . 'includes/webhook-paypal.php';// << NOUVEAU (webhook)
require_once KGH_BOOKING_PATH . 'includes/rest-availability.php';
require_once KGH_BOOKING_PATH . 'includes/admin-availability.php';

// Install DB table for slot exceptions on plugin activation
register_activation_hook(__FILE__, 'kgh_ex_install_table');
