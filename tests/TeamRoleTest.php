<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\TeamRole;

/**
 * Tests for the Team Role custom post type.
 */
class TeamRoleTest extends PluginTestCase {

    protected function tearDown(): void {
        $_POST = array();
        $_GET  = array();
        parent::tearDown();
    }

    private function mock_output_functions(): void {
        Functions\when( '__' )->returnArg();
        Functions\when( 'esc_html__' )->returnArg();
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'wp_kses_post' )->returnArg();
    }

    /**
     * Test that TeamRole registers the expected post type and REST meta support.
     *
     * @return void
     */
    public function test_registers_team_role_post_type(): void {
        Functions\when( '__' )->returnArg();

        Functions\expect( 'register_post_type' )
            ->once()
            ->with(
                TeamRole::POST_TYPE,
                \Mockery::on(
                    static function ( array $args ): bool {
                        return true === $args['public']
                            && true === $args['show_in_rest']
                            && true === $args['has_archive']
                            && array( 'slug' => 'team-roles' ) === $args['rewrite']
                            && in_array( 'title', $args['supports'], true )
                            && in_array( 'editor', $args['supports'], true )
                            && in_array( 'custom-fields', $args['supports'], true );
                    }
                )
            );

        $registered_meta = array();
        Functions\when( 'register_post_meta' )->alias(
            static function ( string $post_type, string $meta_key, array $args ) use ( &$registered_meta ): bool {
                $registered_meta[ $meta_key ] = array(
                    'post_type' => $post_type,
                    'args'      => $args,
                );
                return true;
            }
        );

        $team_role = new TeamRole();
        $team_role->register();

        $expected = array(
            '_district_source_id'          => 'string',
            '_district_source_type'        => 'string',
            '_district_parent_team_id'     => 'string',
            '_district_owner_team_id'      => 'string',
            '_district_role_description'   => 'string',
            '_district_currently_filled'   => 'boolean',
            '_district_is_lead'            => 'boolean',
        );

        foreach ( $expected as $key => $type ) {
            $this->assertArrayHasKey( $key, $registered_meta );
            $this->assertSame( TeamRole::POST_TYPE, $registered_meta[ $key ]['post_type'] );
            $this->assertTrue( $registered_meta[ $key ]['args']['show_in_rest'] );
            $this->assertTrue( $registered_meta[ $key ]['args']['single'] );
            $this->assertSame( $type, $registered_meta[ $key ]['args']['type'] );
        }
    }

    /**
     * Test the post type key remains stable.
     *
     * @return void
     */
    public function test_post_type_key(): void {
        $this->assertSame( 'team_role', TeamRole::POST_TYPE );
    }

    public function test_adds_both_editor_meta_boxes(): void {
        Functions\when( '__' )->returnArg();
        $ids = array();
        Functions\expect( 'add_meta_box' )->twice()->andReturnUsing(
            static function ( $id ) use ( &$ids ): void {
                $ids[] = $id;
            }
        );

        ( new TeamRole() )->add_meta_boxes();

        $this->assertSame( array( 'district_team_role_metadata', 'district_team_role_reassignment' ), $ids );
    }

    public function test_reassignment_box_is_closed_without_duplicate_class(): void {
        $this->assertSame( array( 'side', 'closed' ), ( new TeamRole() )->close_reassignment_meta_box( array( 'side', 'closed' ) ) );
    }

    public function test_reassignment_styles_only_render_on_team_role_screen(): void {
        Functions\when( 'get_current_screen' )->justReturn( false );
        ob_start();
        ( new TeamRole() )->style_reassignment_meta_box();
        $this->assertSame( '', ob_get_clean() );

        \Brain\Monkey\tearDown();
        \Brain\Monkey\setUp();
        Functions\when( 'get_current_screen' )->justReturn( (object) array( 'post_type' => TeamRole::POST_TYPE ) );
        ob_start();
        ( new TeamRole() )->style_reassignment_meta_box();
        $output = ob_get_clean();
        $this->assertStringContainsString( '.handle-order-higher', $output );
        $this->assertStringContainsString( '.handle-order-lower', $output );
    }

    public function test_renders_read_only_synchronization_metadata(): void {
        $this->mock_output_functions();
        $values = array(
            '_district_source_id'         => 'role-1',
            '_district_source_type'       => 'role',
            '_district_role_description'  => "Line one\nLine two",
            '_district_currently_filled'  => true,
            '_district_is_lead'           => false,
        );
        Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => $values[ $key ] ?? '' );

        ob_start();
        ( new TeamRole() )->render_metadata_meta_box( new \WP_Post( array( 'ID' => 42 ) ) );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'role-1', $output );
        $this->assertStringContainsString( "Line one<br />\nLine two", $output );
        $this->assertStringContainsString( '>Yes<', $output );
        $this->assertStringContainsString( '>No<', $output );
        $this->assertStringContainsString( '&mdash;', $output );
        $this->assertStringNotContainsString( '<input', $output );
    }

    public function test_renders_guarded_reassignment_control(): void {
        $this->mock_output_functions();
        Functions\when( 'get_post_meta' )->alias(
            static fn( $id, $key ) => '_district_source_id' === $key ? 'old-id' : 'role'
        );
        Functions\expect( 'wp_nonce_field' )->once()->with( 'district_reassign_source_42', 'district_reassign_source_nonce' );

        ob_start();
        ( new TeamRole() )->render_reassignment_meta_box( new \WP_Post( array( 'ID' => 42 ) ) );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'role:old-id', $output );
        $this->assertStringContainsString( 'district_replacement_source_id', $output );
        $this->assertStringContainsString( 'district_migrate_to_existing', $output );
    }

    public function test_directly_reassigns_an_unused_source_id(): void {
        $_POST = array(
            'district_reassign_source_nonce' => 'valid',
            'district_replacement_source_id' => 'new-id',
        );
        $this->mock_save_guards();
        Functions\when( 'get_post_meta' )->alias(
            static fn( $id, $key ) => '_district_source_id' === $key ? 'old-id' : 'role'
        );
        Functions\when( 'get_posts' )->justReturn( array() );
        Functions\expect( 'update_post_meta' )->once()->with( 42, '_district_source_id', 'new-id' );
        Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '&' . $key . '=' . $value );

        $role = new TeamRole();
        $role->save_reassignment( 42, new \WP_Post( array( 'ID' => 42 ) ) );

        $this->assertStringContainsString( 'district_reassignment=success', $role->add_reassignment_notice( 'post.php?post=42' ) );
    }

    public function test_rejects_duplicate_without_explicit_migration(): void {
        $_POST = array(
            'district_reassign_source_nonce' => 'valid',
            'district_replacement_source_id' => 'new-id',
        );
        $this->mock_save_guards();
        Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => '_district_source_id' === $key ? 'old-id' : 'role' );
        Functions\when( 'get_posts' )->justReturn( array( 99 ) );
        Functions\expect( 'update_post_meta' )->never();
        Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '&' . $key . '=' . $value );

        $role = new TeamRole();
        $role->save_reassignment( 42, new \WP_Post( array( 'ID' => 42 ) ) );

        $this->assertStringContainsString( 'district_reassignment=duplicate', $role->add_reassignment_notice( 'post.php?post=42' ) );
    }

    public function test_migrates_authored_content_to_existing_replacement(): void {
        $_POST = array(
            'district_reassign_source_nonce' => 'valid',
            'district_replacement_source_id' => 'new-id',
            'district_migrate_to_existing'   => '1',
        );
        $this->mock_save_guards();
        Functions\when( 'get_posts' )->justReturn( array( 99 ) );
        Functions\when( 'get_post_meta' )->alias(
            static function ( $id, $key = null ) {
                if ( null === $key ) {
                    return array(
                        '_thumbnail_id'       => array( '7' ),
                        'local_field'         => array( 'value' ),
                        '_district_source_id' => array( 'old-id' ),
                        '_edit_lock'          => array( 'ignored' ),
                    );
                }
                return '_district_source_id' === $key ? 'old-id' : 'role';
            }
        );
        Functions\when( 'maybe_unserialize' )->returnArg();
        Functions\expect( 'delete_post_meta' )->twice();
        Functions\expect( 'add_post_meta' )->twice();
        Functions\expect( 'update_post_meta' )->twice();
        $updates = array();
        Functions\when( 'wp_update_post' )->alias(
            static function ( $data ) use ( &$updates ) {
                $updates[] = $data;
                return $data['ID'];
            }
        );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'get_edit_post_link' )->justReturn( 'post.php?post=99' );
        Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '&' . $key . '=' . $value );

        $source = new \WP_Post( array( 'ID' => 42, 'post_content' => 'Body', 'post_excerpt' => 'Excerpt' ) );
        $role = new TeamRole();
        $role->save_reassignment( 42, $source );

        $this->assertSame( array( 'ID' => 99, 'post_content' => 'Body', 'post_excerpt' => 'Excerpt' ), $updates[0] );
        $this->assertSame( array( 'ID' => 42, 'post_status' => 'draft' ), $updates[1] );
        $this->assertSame( 'post.php?post=99&district_reassignment=migrated', $role->add_reassignment_notice( 'ignored' ) );
    }

    public function test_save_guards_and_status_notices(): void {
        $role = new TeamRole();
        $role->save_reassignment( 42, new \WP_Post( array( 'ID' => 42 ) ) );
        $this->assertSame( 'unchanged', $role->add_reassignment_notice( 'unchanged' ) );

        $this->mock_output_functions();
        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        $_GET['district_reassignment'] = 'migrated';
        ob_start();
        $role->render_reassignment_notice();
        $this->assertStringContainsString( 'notice-success', ob_get_clean() );

        $_GET['district_reassignment'] = 'unknown';
        ob_start();
        $role->render_reassignment_notice();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_reports_missing_and_unchanged_source_identity(): void {
        Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '?' . $value );

        $_POST = array(
            'district_reassign_source_nonce' => 'valid',
            'district_replacement_source_id' => 'new-id',
        );
        $this->mock_save_guards();
        Functions\when( 'get_post_meta' )->justReturn( '' );
        $role = new TeamRole();
        $role->save_reassignment( 42, new \WP_Post( array( 'ID' => 42 ) ) );
        $this->assertSame( 'url?missing_source', $role->add_reassignment_notice( 'url' ) );

        \Brain\Monkey\tearDown();
        \Brain\Monkey\setUp();
        $this->mock_save_guards();
        Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => '_district_source_id' === $key ? 'same-id' : 'role' );
        Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '?' . $value );
        $_POST['district_replacement_source_id'] = 'same-id';
        $role = new TeamRole();
        $role->save_reassignment( 42, new \WP_Post( array( 'ID' => 42 ) ) );
        $this->assertSame( 'url?unchanged', $role->add_reassignment_notice( 'url' ) );
    }

    private function mock_save_guards(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'wp_is_post_autosave' )->justReturn( false );
        Functions\when( 'wp_is_post_revision' )->justReturn( false );
        Functions\when( 'current_user_can' )->justReturn( true );
    }
}
