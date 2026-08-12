<?php

/**
 * Activation hooks for the plugin.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Handles activation and deactivation tasks.
 */
class Activation {



	/**
	 * Activates the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		flush_rewrite_rules();
	}

	/**
	 * Deactivates the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Cleanup tasks on deactivation.
		flush_rewrite_rules();
	}
}
