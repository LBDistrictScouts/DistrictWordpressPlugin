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

// Minimal WordPress value objects used by type-hinted plugin methods.
if ( ! class_exists( 'WP_Post' ) ) {
    class WP_Post {
        public int $ID = 0;
        public string $post_status = 'draft';
        public string $post_content = '';
        public string $post_excerpt = '';
        public string $post_title = '';
        public string $post_name = '';

        public function __construct( array $properties = array() ) {
            foreach ( $properties as $property => $value ) {
                $this->{$property} = $value;
            }
        }
    }
}

if ( ! class_exists( 'WP_CLI' ) ) {
    class WP_CLI {
        public static array $logs = array();
        public static array $successes = array();
        public static array $errors = array();

        public static function log( string $message ): void {
            self::$logs[] = $message;
        }

        public static function success( string $message ): void {
            self::$successes[] = $message;
        }

        public static function error( string $message ): void {
            self::$errors[] = $message;
        }

        public static function add_command( string $name, object $command ): void {
        }
    }
}

// Load Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Load Brain Monkey
Brain\Monkey\setUp();

// Load source classes required by tests
require_once dirname(__FILE__) . '/../src/TeamRole.php';
require_once dirname(__FILE__) . '/../src/RewriteManager.php';

// Load test base class
require_once dirname(__FILE__) . '/PluginTestCase.php';
