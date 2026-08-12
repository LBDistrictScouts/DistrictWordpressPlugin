<?php

/**
 * Test bootstrap.
 *
 * Sets up the testing environment for the District WordPress Plugin.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin\Tests
 */

// Define WordPress constants that the plugin needs.
define( 'ABSPATH', __DIR__ . '/../' );
define( 'WP_PLUGIN_DIR', __DIR__ . '/../plugins' );
define( 'WPINC', true );

// Load Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Load Brain Monkey.
Brain\Monkey\setUp();

// Load test base class.
require_once __DIR__ . '/class-plugintestcase.php';
