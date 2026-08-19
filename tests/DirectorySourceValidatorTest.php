<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use LBDistrictScouts\DistrictWordpressPlugin\Sync\DirectorySourceValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DirectorySourceValidatorTest extends TestCase {
    public function test_accepts_valid_directory(): void {
        $validator = new DirectorySourceValidator();
        $validator->validate(
            [ [ 'id' => 'team-1', 'team_name' => 'Team', 'slug' => 'team', 'team_parent_id' => null ] ],
            [ [ 'id' => 'role-1', 'team_id' => 'team-1', 'name' => 'Role', 'slug' => 'role' ] ]
        );
        $this->assertTrue( true );
    }

    public function test_rejects_slug_collision_across_team_and_role(): void {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'Slug "shared" is duplicated' );
        ( new DirectorySourceValidator() )->validate(
            [ [ 'id' => 'team-1', 'team_name' => 'Team', 'slug' => 'shared', 'team_parent_id' => null ] ],
            [ [ 'id' => 'role-1', 'team_id' => 'team-1', 'name' => 'Role', 'slug' => 'shared' ] ]
        );
    }

    public function test_rejects_missing_role_owner(): void {
        $this->expectException( RuntimeException::class );
        ( new DirectorySourceValidator() )->validate( [], [ [ 'id' => 'role-1', 'team_id' => 'missing', 'name' => 'Role', 'slug' => 'role' ] ] );
    }

    public function test_rejects_team_parent_cycle(): void {
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'cycle' );
        ( new DirectorySourceValidator() )->validate( [
            [ 'id' => 'a', 'team_name' => 'A', 'slug' => 'a', 'team_parent_id' => 'b' ],
            [ 'id' => 'b', 'team_name' => 'B', 'slug' => 'b', 'team_parent_id' => 'a' ],
        ], [] );
    }
}
