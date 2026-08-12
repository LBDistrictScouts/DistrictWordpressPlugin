<?php

/**
 * Tests for plugin initialization.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin\Tests
 */

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Expect;
use Brain\Monkey\Functions;

/**
 * Tests for plugin initialization.
 *
 * Note: We test the plugin file itself rather than the Plugin class to avoid requiring WordPress to be fully loaded.
 */
class PluginInitializationTest extends PluginTestCase {




	/**
	 * Test that the main plugin file exists and is valid PHP.
	 *
	 * @return void
	 */
	public function test_main_plugin_file_exists(): void {
		$plugin_root = dirname( __DIR__ );
		$plugin_file = $plugin_root . '/district-wordpress-plugin.php';

		$this->assertFileExists( $plugin_file );
		$this->assertStringEndsWith( '.php', $plugin_file );
	}

	/**
	 * Test that the main plugin file has proper header comments.
	 *
	 * @return void
	 */
	public function test_plugin_file_has_proper_headers(): void {
		$plugin_root = dirname( __DIR__ );
		$plugin_file = $plugin_root . '/district-wordpress-plugin.php';

		$content = file_get_contents( $plugin_file );

		// Check for required plugin header comments.
		$this->assertStringContainsString( 'Plugin Name:', $content );
		$this->assertStringContainsString( 'Description:', $content );
		$this->assertStringContainsString( 'Version:', $content );
		$this->assertStringContainsString( 'Author:', $content );
		$this->assertStringContainsString( 'License:', $content );
	}

	/**
	 * Test that the plugin file loads the autoloader.
	 *
	 * @return void
	 */
	public function test_plugin_loads_autoloader(): void {
		$plugin_root = dirname( __DIR__ );
		$plugin_file = $plugin_root . '/district-wordpress-plugin.php';

		$content = file_get_contents( $plugin_file );

		// Check that the autoloader is loaded.
		$this->assertStringContainsString( 'vendor/autoload.php', $content );
	}

	/**
	 * Test that the plugin file checks for WPINC.
	 *
	 * @return void
	 */
	public function test_plugin_checks_wpinc(): void {
		$plugin_root = dirname( __DIR__ );
		$plugin_file = $plugin_root . '/district-wordpress-plugin.php';

		$content = file_get_contents( $plugin_file );

		// Check that the plugin verifies WordPress is loaded.
		$this->assertStringContainsString( 'WPINC', $content );
	}
}
