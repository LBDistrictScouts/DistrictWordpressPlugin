<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Plugin;

class PluginBehaviorTest extends PluginTestCase {
    public function test_plugin_bootstrap_defines_paths_registers_hooks_and_loads_textdomain(): void {
        $plugin_file = dirname( __DIR__ ) . '/district-wordpress-plugin.php';

        Functions\when( 'plugin_dir_path' )->alias( static fn() => dirname( __DIR__ ) . '/' );
        Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.org/wp-content/plugins/district-wordpress-plugin/' );
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\expect( 'add_action' )->times( 3 );

        $plugin = Plugin::get_instance( $plugin_file );

        $this->assertSame( $plugin_file, DISTRICTWP_FILE );
        $this->assertSame( dirname( __DIR__ ) . '/', DISTRICTWP_PATH );
        $this->assertSame( $plugin, Plugin::get_instance( 'ignored.php' ) );

        Functions\when( 'plugin_basename' )->justReturn( 'district-wordpress-plugin/district-wordpress-plugin.php' );
        Functions\expect( 'load_plugin_textdomain' )->once()->with(
            'district-wordpress-plugin', false, 'district-wordpress-plugin/languages'
        );
        $plugin->load_textdomain();
        $plugin->run();
    }
}
