<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

/**
 * Tests for plugin activation
 *
 * These are smoke tests to verify the activation file exists and is valid PHP
 */
class ActivationTest extends PluginTestCase
{
    /**
     * Test that the Activation class file exists
     */
    public function test_activation_file_exists(): void
    {
        $plugin_root = dirname(__DIR__);
        $activation_file = $plugin_root . '/src/Activation.php';

        $this->assertFileExists($activation_file);
        $this->assertStringEndsWith('.php', $activation_file);
    }

    /**
     * Test that the Activation file contains expected methods
     */
    public function test_activation_file_has_methods(): void
    {
        $plugin_root = dirname(__DIR__);
        $activation_file = $plugin_root . '/src/Activation.php';

        $content = file_get_contents($activation_file);

        // Check for activation hook methods
        $this->assertStringContainsString('public static function activate', $content);
        $this->assertStringContainsString('public static function deactivate', $content);
    }

    /**
     * Activation hooks are already permission-gated by WordPress and must also
     * work in non-interactive contexts such as WP-CLI deployments.
     */
    public function test_activation_does_not_require_logged_in_user(): void
    {
        $plugin_root = dirname(__DIR__);
        $activation_file = $plugin_root . '/src/Activation.php';

        $content = file_get_contents($activation_file);

        $this->assertStringNotContainsString('current_user_can', $content);
    }

    /**
     * Test that the Activation file flushes rewrite rules
     */
    public function test_activation_flushes_rewrite_rules(): void
    {
        $plugin_root = dirname(__DIR__);
        $activation_file = $plugin_root . '/src/Activation.php';

        $content = file_get_contents($activation_file);

        // Check that rewrite rules are flushed
        $this->assertStringContainsString('flush_rewrite_rules', $content);
    }
}
