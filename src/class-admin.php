<?php

/**
 * Admin functionality for the plugin.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Registers admin menu and settings page.
 */
class Admin {



	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Adds the admin menu entry.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page( 'District Plugin Settings', 'District Plugin', 'manage_options', 'district-wordpress-plugin', array( $this, 'settings_page' ) );
	}

	/**
	 * Renders the plugin settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		echo '<div class="wrap"><h1>District WordPress Plugin</h1><p>Settings go here.</p></div>';
	}
}
