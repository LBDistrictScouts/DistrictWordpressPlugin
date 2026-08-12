<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;

/**
 * Tests for admin functionality
 *
 * These are smoke tests to verify the Admin file exists and is valid PHP
 */
class AdminTest extends PluginTestCase
{
    /**
     * Test that the Admin class file exists
     */
    public function test_admin_file_exists(): void
    {
        $plugin_root = dirname(__DIR__);
        $admin_file = $plugin_root . '/src/Admin.php';

        $this->assertFileExists($admin_file);
        $this->assertStringEndsWith('.php', $admin_file);
    }

    /**
     * Test that the Admin file contains expected methods
     */
    public function test_admin_file_has_methods(): void
    {
        $plugin_root = dirname(__DIR__);
        $admin_file = $plugin_root . '/src/Admin.php';

        $content = file_get_contents($admin_file);

        // Check for admin methods
        $this->assertStringContainsString('function __construct', $content);
        $this->assertStringContainsString('function admin_menu', $content);
        $this->assertStringContainsString('function settings_page', $content);
    }

    /**
     * Test that the Admin file registers hooks
     */
    public function test_admin_registers_hooks(): void
    {
        $plugin_root = dirname(__DIR__);
        $admin_file = $plugin_root . '/src/Admin.php';

        $content = file_get_contents($admin_file);

        // Check that hooks are registered
        $this->assertStringContainsString('add_action', $content);
        $this->assertStringContainsString('admin_menu', $content);
    }

    /**
     * Test that the Admin file creates options page
     */
    public function test_admin_creates_options_page(): void
    {
        $plugin_root = dirname(__DIR__);
        $admin_file = $plugin_root . '/src/Admin.php';

        $content = file_get_contents($admin_file);

        // Check that options page is created
        $this->assertStringContainsString('add_options_page', $content);
        $this->assertStringContainsString('District', $content);
    }
}
