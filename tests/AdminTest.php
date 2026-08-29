<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Admin;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DistrictTeamApiClient;

/**
 * Tests for admin functionality
 *
 * These are smoke tests to verify the Admin file exists and is valid PHP
 */
class AdminTest extends PluginTestCase
{
    private function mock_output_functions(): void {
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'admin_url' )->alias( static fn( $path ) => 'wp-admin/' . $path );
    }
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

    public function test_constructor_menu_and_settings_register_wordpress_hooks(): void {
        Functions\expect( 'add_action' )->times( 3 );
        $admin = new Admin();

        Functions\expect( 'add_options_page' )->once();
        Functions\expect( 'add_management_page' )->once();
        $admin->admin_menu();

        Functions\expect( 'register_setting' )->once()->with(
            'district_wordpress_plugin',
            DistrictTeamApiClient::OPTION_NAME,
            \Mockery::type( 'array' )
        );
        Functions\expect( 'register_setting' )->twice()->with( 'district_wordpress_plugin', \Mockery::type( 'string' ), \Mockery::type( 'array' ) );
        Functions\expect( 'add_settings_section' )->twice();
        Functions\expect( 'add_settings_field' )->times( 3 );
        $admin->register_settings();
        $this->addToAssertionCount( 1 );
    }

    public function test_renders_api_field_and_settings_page(): void {
        $this->mock_output_functions();
        Functions\when( 'get_option' )->justReturn( 'https://example.org' );
        $admin = new Admin();

        ob_start();
        $admin->api_url_field();
        $field = ob_get_clean();
        $this->assertStringContainsString( 'https://example.org', $field );
        $this->assertStringNotContainsString( 'disabled="disabled"', $field );

        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\expect( 'settings_fields' )->once();
        Functions\expect( 'do_settings_sections' )->once();
        Functions\expect( 'submit_button' )->once();
        ob_start();
        $admin->settings_page();
        $page = ob_get_clean();
        $this->assertStringContainsString( '<form', $page );
    }

    public function test_settings_page_is_hidden_without_permission(): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        ob_start();
        ( new Admin() )->settings_page();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_renders_sync_status_results_and_success_notice(): void {
        $this->mock_output_functions();
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_current_user_id' )->justReturn( 7 );
        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'get_option' )->alias(
            static function ( $key, $default = null ) {
                $values = array(
                    DistrictTeamApiClient::OPTION_NAME       => 'https://example.org',
                    'district_team_last_successful_sync'     => '2026-08-29T08:00:00Z',
                    'district_team_last_sync_result'         => array( 'created' => 3, 'deactivated' => 1 ),
                    'district_team_sync_lock'                => array( 'expires' => 0 ),
                );
                return $values[ $key ] ?? $default;
            }
        );
        Functions\when( 'get_transient' )->justReturn( array( 'teams_received' => 2, 'created' => 2 ) );
        Functions\expect( 'delete_transient' )->once();
        Functions\expect( 'wp_nonce_field' )->once();
        Functions\expect( 'submit_button' )->once();
        $_GET['district_sync_notice'] = 'success';

        ob_start();
        ( new Admin() )->sync_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'synchronization completed successfully', $output );
        $this->assertStringContainsString( 'Dry Run Result', $output );
        $this->assertStringContainsString( '2026-08-29T08:00:00Z', $output );
    }

    public function test_run_sync_reports_configuration_error_and_redirects(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\expect( 'check_admin_referer' )->once()->with( 'district_team_sync' );
        Functions\when( 'get_option' )->justReturn( '' );
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'untrailingslashit' )->returnArg();
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( 'get_current_user_id' )->justReturn( 7 );
        Functions\expect( 'set_transient' )->once()->with(
            'district_team_sync_error_7',
            'District Team API URL is not configured.',
            300
        );
        Functions\when( 'admin_url' )->alias( static fn( $path ) => 'wp-admin/' . $path );
        Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '&' . $key . '=' . $value );
        Functions\expect( 'wp_safe_redirect' )->once()->with( \Mockery::on( static fn( $url ) => str_contains( $url, 'district_sync_notice=error' ) ) );

        ( new Admin() )->run_sync();
        $this->addToAssertionCount( 1 );
    }

    public function test_run_sync_dry_run_stores_result_and_redirects(): void {
        $_POST['dry_run'] = '1';
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'check_admin_referer' )->justReturn( true );
        Functions\when( 'get_option' )->alias(
            static fn( $key, $default = null ) => DistrictTeamApiClient::OPTION_NAME === $key ? 'https://example.org' : $default
        );
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'untrailingslashit' )->returnArg();
        Functions\when( 'add_query_arg' )->alias(
            static fn( $args, $url = null ) => is_array( $args ) ? $url . '?' . http_build_query( $args ) : $url . '&' . $args . '=dry-run'
        );
        Functions\when( 'wp_remote_get' )->alias(
            static function ( $url ) {
                $data = str_contains( $url, '/teams?' )
                    ? array( array( 'id' => 'team-1', 'team_name' => 'Team', 'slug' => 'team', 'team_parent_id' => null ) )
                    : array();
                return array( 'code' => 200, 'body' => json_encode( array( 'data' => $data, 'pagination' => array( 'page_count' => 1 ) ) ) );
            }
        );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $response ) => $response['code'] );
        Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $response ) => $response['body'] );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'get_posts' )->justReturn( array() );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( 'get_current_user_id' )->justReturn( 7 );
        Functions\expect( 'set_transient' )->once()->with( 'district_team_sync_dry_run_7', \Mockery::type( 'array' ), 300 );
        Functions\when( 'admin_url' )->alias( static fn( $path ) => 'wp-admin/' . $path );
        Functions\expect( 'wp_safe_redirect' )->once()->with( \Mockery::on( static fn( $url ) => str_contains( $url, 'district_sync_notice=dry-run' ) ) );

        ( new Admin() )->run_sync();
        $this->addToAssertionCount( 1 );
    }

    protected function tearDown(): void {
        $_GET = array();
        $_POST = array();
        parent::tearDown();
    }
}
