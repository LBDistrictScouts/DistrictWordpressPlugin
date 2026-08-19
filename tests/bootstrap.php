<?php

/**
 * Test Bootstrap
 *
 * Sets up the testing environment for the District WordPress Plugin
 */

// Define WordPress constants that the plugin needs
define('ABSPATH', dirname(__FILE__) . '/../');
define('WP_PLUGIN_DIR', dirname(__FILE__) . '/../plugins');
define('WPINC', true);

// Load Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Load Brain Monkey
Brain\Monkey\setUp();

// Load source classes required by tests
require_once dirname(__FILE__) . '/../src/TeamRole.php';
require_once dirname(__FILE__) . '/../src/RewriteManager.php';

// Load test base class
require_once dirname(__FILE__) . '/PluginTestCase.php';
