<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\TeamRole;

/**
 * Tests for the Team Role custom post type.
 */
class TeamRoleTest extends PluginTestCase {

    /**
     * Test that TeamRole registers the expected post type.
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
                            && in_array( 'editor', $args['supports'], true );
                    }
                )
            );

        $team_role = new TeamRole();
        $team_role->register();

        $this->assertSame( 'team_role', TeamRole::POST_TYPE );
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
