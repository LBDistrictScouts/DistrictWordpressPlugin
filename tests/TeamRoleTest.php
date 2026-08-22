<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\TeamRole;

/**
 * Tests for the Team Role custom post type.
 */
class TeamRoleTest extends PluginTestCase {

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
}
