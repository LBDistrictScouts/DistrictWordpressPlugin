<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\TeamRolePostRepository;
use RuntimeException;

class TeamRolePostRepositoryTest extends PluginTestCase {
    public function test_stores_current_role_schema_fields(): void {
        $metadata = [];

        Functions\when( 'get_posts' )->justReturn( [] );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_textarea_field' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'wp_insert_post' )->justReturn( 42 );
        Functions\when( 'update_post_meta' )->alias(
            static function ( $post_id, $key, $value ) use ( &$metadata ): bool {
                $metadata[ $key ] = $value;

                return true;
            }
        );

        $result = ( new TeamRolePostRepository() )->upsert(
            [
                'id'               => 'role-1',
                'team_id'          => 'team-1',
                'name'             => 'District Lead Volunteer',
                'slug'             => 'district-lead-volunteer',
                'description'      => '',
                'currently_filled' => false,
                'is_lead'          => true,
            ],
            'role'
        );

        $this->assertSame( 'created', $result );
        $this->assertFalse( $metadata['_district_currently_filled'] );
        $this->assertTrue( $metadata['_district_is_lead'] );
        $this->assertSame( 'team-1', $metadata['_district_owner_team_id'] );
    }

    public function test_moves_missing_synced_post_to_trash(): void {
        $post = (object) array(
            'ID'          => 42,
            'post_status' => 'publish',
        );
        $metadata = array();

        Functions\when( 'get_posts' )->justReturn( array( $post ) );
        Functions\when( 'get_post_meta' )->alias(
            static function ( $post_id, $key ) {
                return '_district_source_type' === $key ? 'role' : 'role-1';
            }
        );
        Functions\expect( 'wp_trash_post' )
            ->once()
            ->with( 42 )
            ->andReturn( $post );
        Functions\when( 'update_post_meta' )->alias(
            static function ( $post_id, $key, $value ) use ( &$metadata ): bool {
                $metadata[ $key ] = $value;
                return true;
            }
        );

        $count = ( new TeamRolePostRepository() )->deactivate_missing( array() );

        $this->assertSame( 1, $count );
        $this->assertSame( 'missing_from_source', $metadata['_district_deactivated_reason'] );
        $this->assertArrayHasKey( '_district_deactivated_at', $metadata );
    }

    public function test_ignores_already_trashed_synced_post(): void {
        $post = (object) array(
            'ID'          => 42,
            'post_status' => 'trash',
        );

        Functions\when( 'get_posts' )->justReturn( array( $post ) );
        Functions\expect( 'wp_trash_post' )->never();
        Functions\expect( 'update_post_meta' )->never();

        $count = ( new TeamRolePostRepository() )->deactivate_missing( array() );

        $this->assertSame( 0, $count );
    }

    public function test_updates_existing_post_and_synchronization_metadata(): void {
        $existing = new \WP_Post( array( 'ID' => 42, 'post_title' => 'Old title', 'post_name' => 'old-title' ) );
        Functions\when( 'get_posts' )->justReturn( array( $existing ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_textarea_field' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\expect( 'wp_update_post' )->once()->andReturn( 42 );
        Functions\expect( 'update_post_meta' )->times( 7 )->andReturn( true );

        $result = ( new TeamRolePostRepository() )->upsert(
            array(
                'id' => 'role-1', 'team_id' => 'team-1', 'name' => 'New title', 'slug' => 'new-title',
                'description' => 'Description', 'currently_filled' => true, 'is_lead' => false,
            ),
            'role'
        );

        $this->assertSame( 'updated', $result );
    }

    public function test_returns_unchanged_when_structure_and_metadata_match(): void {
        $existing = new \WP_Post( array( 'ID' => 42, 'post_title' => 'Role', 'post_name' => 'role' ) );
        $record = array(
            'id' => 'role-1', 'team_id' => 'team-1', 'name' => 'Role', 'slug' => 'role',
            'description' => '', 'currently_filled' => false, 'is_lead' => true,
        );
        $meta = array(
            '_district_source_id' => 'role-1', '_district_source_type' => 'role',
            '_district_owner_team_id' => 'team-1', '_district_role_description' => '',
            '_district_currently_filled' => false, '_district_is_lead' => true,
        );
        Functions\when( 'get_posts' )->justReturn( array( $existing ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_textarea_field' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => $meta[ $key ] );
        Functions\expect( 'wp_update_post' )->never();

        $this->assertSame( 'unchanged', ( new TeamRolePostRepository() )->upsert( $record, 'role' ) );
    }

    public function test_creates_team_parent_metadata_and_supports_dry_run(): void {
        Functions\when( 'get_posts' )->justReturn( array() );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\expect( 'wp_insert_post' )->never();
        $record = array( 'id' => 'team-1', 'team_name' => 'Team', 'slug' => 'team', 'team_parent_id' => 'parent' );
        $this->assertSame( 'created', ( new TeamRolePostRepository() )->upsert( $record, 'team', true ) );
    }

    public function test_duplicate_source_identity_throws(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_posts' )->justReturn( array( new \WP_Post(), new \WP_Post() ) );
        $this->expectException( RuntimeException::class );
        ( new TeamRolePostRepository() )->upsert( array( 'id' => 'duplicate' ), 'role' );
    }

    public function test_deactivation_dry_run_counts_only_missing_posts(): void {
        $present = new \WP_Post( array( 'ID' => 1, 'post_status' => 'publish' ) );
        $missing = new \WP_Post( array( 'ID' => 2, 'post_status' => 'draft' ) );
        Functions\when( 'get_posts' )->justReturn( array( $present, $missing ) );
        Functions\when( 'get_post_meta' )->alias(
            static fn( $id, $key ) => '_district_source_type' === $key ? 'role' : 'role-' . $id
        );
        Functions\expect( 'wp_trash_post' )->never();
        $this->assertSame( 1, ( new TeamRolePostRepository() )->deactivate_missing( array( 'role:role-1' => true ), true ) );
    }
}
