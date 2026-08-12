<?php

/**
 * Tests for plugin activation.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin\Tests
 */

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;

/**
 * Smoke tests for activation behavior.
 */
class ActivationTest extends PluginTestCase {




	/**
	 * Test that the Activation class file exists.
	 *
	 * @return void
	 */
	public function test_activation_file_exists(): void {
		$plugin_root     = dirname( __DIR__ );
		$activation_file = $plugin_root . '/src/class-activation.php';

		$this->assertFileExists( $activation_file );
		$this->assertStringEndsWith( '.php', $activation_file );
	}

	/**
	 * Test that the Activation file contains expected methods.
	 *
	 * @return void
	 */
	public function test_activation_file_has_methods(): void {
		$plugin_root     = dirname( __DIR__ );
		$activation_file = $plugin_root . '/src/class-activation.php';

		$content = file_get_contents( $activation_file );

		// Check for activation hook methods.
		$this->assertStringContainsString( 'public static function activate', $content );
		$this->assertStringContainsString( 'public static function deactivate', $content );
	}

	/**
	 * Test that the Activation file handles permissions check.
	 *
	 * @return void
	 */
	public function test_activation_checks_permissions(): void {
		$plugin_root     = dirname( __DIR__ );
		$activation_file = $plugin_root . '/src/class-activation.php';

		$content = file_get_contents( $activation_file );

		// Check that activation checks user permissions.
		$this->assertStringContainsString( 'current_user_can', $content );
		$this->assertStringContainsString( 'activate_plugins', $content );
	}

	/**
	 * Test that the Activation file flushes rewrite rules.
	 *
	 * @return void
	 */
	public function test_activation_flushes_rewrite_rules(): void {
		$plugin_root     = dirname( __DIR__ );
		$activation_file = $plugin_root . '/src/class-activation.php';

		$content = file_get_contents( $activation_file );

		// Check that rewrite rules are flushed.
		$this->assertStringContainsString( 'flush_rewrite_rules', $content );
	}
}
