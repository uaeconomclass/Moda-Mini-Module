<?php
/**
 * Plugin Name: Moda Mini Module
 * Description: Custom tables, admin UI, REST API, and seeding tools for Moda stylist data.
 * Version: 1.0.0
 * Author: Moda
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MODA_PLUGIN_VERSION', '1.0.0');
define('MODA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MODA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MODA_PLUGIN_DIR . 'includes/class-moda-database.php';
require_once MODA_PLUGIN_DIR . 'includes/class-moda-stylist-repository.php';
require_once MODA_PLUGIN_DIR . 'includes/class-moda-rest-controller.php';
require_once MODA_PLUGIN_DIR . 'includes/class-moda-admin.php';
require_once MODA_PLUGIN_DIR . 'includes/class-moda-seeder.php';

register_activation_hook(__FILE__, array('Moda_Database', 'activate'));

add_action('plugins_loaded', static function () {
    $repository = new Moda_Stylist_Repository();

    $rest = new Moda_REST_Controller($repository);
    $rest->register();

    if (is_admin()) {
        $admin = new Moda_Admin($repository);
        $admin->register();
    }

    $seeder = new Moda_Seeder($repository);
    $seeder->register();
});

