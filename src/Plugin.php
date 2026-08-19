<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

class Plugin {
    private static $instance = null;
    private $file;

    private function __construct( $file ) {
        $this->file = $file;
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    public static function get_instance( $file ) {
        if ( null === self::$instance ) {
            self::$instance = new self( $file );
        }
        return self::$instance;
    }

    private function define_constants() {
        if ( ! defined( 'DISTRICTWP_VERSION' ) ) {
            define( 'DISTRICTWP_VERSION', '0.1.0' );
        }
        define( 'DISTRICTWP_FILE', $this->file );
        define( 'DISTRICTWP_PATH', plugin_dir_path( DISTRICTWP_FILE ) );
        define( 'DISTRICTWP_URL', plugin_dir_url( DISTRICTWP_FILE ) );
    }

    private function includes() {
        if ( is_admin() ) {
            require_once DISTRICTWP_PATH . 'src/Admin.php';
        }
        require_once DISTRICTWP_PATH . 'src/TeamRole.php';
    }

    private function init_hooks() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ new TeamRole(), 'register' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'district-wordpress-plugin', false, dirname( plugin_basename( DISTRICTWP_FILE ) ) . '/languages' );
    }

    public function run() {
        if ( is_admin() ) {
            new Admin();
        }
    }
}
