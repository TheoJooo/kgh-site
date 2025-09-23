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

// Charger fichiers inclus
require_once KGH_BOOKING_PATH . 'includes/cpt.php';
require_once KGH_BOOKING_PATH . 'includes/metaboxes.php';

require_once KGH_BOOKING_PATH . 'includes/paypal-http.php';
// require_once KGH_BOOKING_PATH . 'includes/paypal.php';        // << NOUVEAU
require_once KGH_BOOKING_PATH . 'includes/rest-paypal.php';   // << NOUVEAU (create order)
require_once KGH_BOOKING_PATH . 'includes/webhook-paypal.php';// << NOUVEAU (webhook)

