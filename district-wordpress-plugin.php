<?php
/**
 * Plugin Name: District WordPress Plugin
 * Plugin URI: https://github.com/LBDistrictScouts/DistrictWordpressPlugin
 * Description: Custom Post Types for District CMS
 * Version: 0.1.0
 * Author: LBDistrictScouts
 * Author URI: https://github.com/LBDistrictScouts
 * Text Domain: district-wordpress-plugin
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Autoload (if using Composer)
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use LBDistrictScouts\DistrictWordpressPlugin\Plugin;

function district_wordpress_plugin_init() {
    $plugin = Plugin::get_instance( __FILE__ );
    $plugin->run();
}
add_action( 'plugins_loaded', 'district_wordpress_plugin_init' );
