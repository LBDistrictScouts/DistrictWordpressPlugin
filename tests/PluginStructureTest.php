<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

class PluginStructureTest extends PluginTestCase
{
    /**
     * Test that essential plugin files exist
     */
    public function test_essential_files_exist(): void
    {
        $plugin_root = dirname(__DIR__);

        $this->assertFileExists($plugin_root . '/district-wordpress-plugin.php');
        $this->assertFileExists($plugin_root . '/composer.json');
        $this->assertFileExists($plugin_root . '/vendor/autoload.php');
    }

    /**
     * Test that essential source files exist
     */
    public function test_essential_source_files_exist(): void
    {
        $plugin_root = dirname(__DIR__);

        $this->assertFileExists($plugin_root . '/src/Plugin.php');
        $this->assertFileExists($plugin_root . '/src/Activation.php');
        $this->assertFileExists($plugin_root . '/src/Admin.php');
    }

    /**
     * Test that composer.json is valid JSON
     */
    public function test_composer_json_is_valid(): void
    {
        $plugin_root = dirname(__DIR__);
        $composer_file = $plugin_root . '/composer.json';

        $this->assertFileExists($composer_file);

        $content = file_get_contents($composer_file);
        $decoded = json_decode($content, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('description', $decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertEquals('wordpress-plugin', $decoded['type']);
    }

    /**
     * Test that the plugin has proper PHP version requirement
     */
    public function test_php_version_requirement(): void
    {
        $plugin_root = dirname(__DIR__);
        $composer_file = $plugin_root . '/composer.json';

        $content = file_get_contents($composer_file);
        $decoded = json_decode($content, true);

        $this->assertArrayHasKey('require', $decoded);
        $this->assertArrayHasKey('php', $decoded['require']);
        $this->assertStringContainsString('>=8.1', $decoded['require']['php']);
    }
}
