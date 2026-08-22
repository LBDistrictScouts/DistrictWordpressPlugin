<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\TeamRolePostRepository;

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
}
