<?php

/**
 * Core plugin bootstrap.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Singleton bootstrap for the plugin.
 */
class Plugin {



	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Plugin main file path.
	 */
	private function __construct( $file ) {
		$this->file = $file;
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Gets the singleton instance.
	 *
	 * @param string $file Plugin main file path.
	 * @return self
	 */
	public static function get_instance( $file ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
		}
		return self::$instance;
	}

	/**
	 * Defines plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		if ( ! defined( 'DISTRICTWP_VERSION' ) ) {
			define( 'DISTRICTWP_VERSION', '0.1.0' );
		}
		define( 'DISTRICTWP_FILE', $this->file );
		define( 'DISTRICTWP_PATH', plugin_dir_path( DISTRICTWP_FILE ) );
		define( 'DISTRICTWP_URL', plugin_dir_url( DISTRICTWP_FILE ) );
	}

	/**
	 * Loads required includes.
	 *
	 * @return void
	 */
	private function includes() {
		if ( is_admin() ) {
			require_once DISTRICTWP_PATH . 'src/class-admin.php';
		}
		require_once DISTRICTWP_PATH . 'src/class-activation.php';
	}

	/**
	 * Registers the plugin hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		register_activation_hook( DISTRICTWP_FILE, array( 'LBDistrictScouts\\DistrictWordpressPlugin\\Activation', 'activate' ) );
		register_deactivation_hook( DISTRICTWP_FILE, array( 'LBDistrictScouts\\DistrictWordpressPlugin\\Activation', 'deactivate' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'district-wordpress-plugin', false, dirname( plugin_basename( DISTRICTWP_FILE ) ) . '/languages' );
	}

	/**
	 * Runs the plugin.
	 *
	 * @return void
	 */
	public function run() {
		if ( is_admin() ) {
			new Admin();
		}
	}
}
