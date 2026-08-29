<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DirectorySourceValidator;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DirectorySynchronizer;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DistrictTeamApiClient;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\SyncCommand;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\SyncLock;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\TeamRolePostRepository;
use Mockery;
use RuntimeException;

class SyncWorkflowTest extends PluginTestCase {
    public function test_synchronizer_reconciles_records_and_persists_result(): void {
        $client = Mockery::mock( DistrictTeamApiClient::class );
        $validator = Mockery::mock( DirectorySourceValidator::class );
        $repository = Mockery::mock( TeamRolePostRepository::class );
        $lock = Mockery::mock( SyncLock::class );
        $teams = array(
            array( 'id' => 'child', 'tree_left' => 2 ),
            array( 'id' => 'parent', 'tree_left' => 1 ),
        );
        $roles = array( array( 'id' => 'role-1' ) );

        $lock->shouldReceive( 'acquire' )->once()->with( false );
        $lock->shouldReceive( 'release' )->once();
        $client->shouldReceive( 'fetch_teams' )->once()->andReturn( $teams );
        $client->shouldReceive( 'fetch_roles' )->once()->andReturn( $roles );
        $validator->shouldReceive( 'validate' )->once()->with( $teams, $roles );
        $repository->shouldReceive( 'upsert' )->once()->with( $teams[1], 'team', false )->andReturn( 'created' );
        $repository->shouldReceive( 'upsert' )->once()->with( $teams[0], 'team', false )->andReturn( 'updated' );
        $repository->shouldReceive( 'upsert' )->once()->with( $roles[0], 'role', false )->andReturn( 'unchanged' );
        $repository->shouldReceive( 'deactivate_missing' )->once()->andReturn( 2 );
        Functions\expect( 'update_option' )->twice()->andReturn( true );

        $result = ( new DirectorySynchronizer( $client, $validator, $repository, $lock ) )->sync();

        $this->assertSame( 1, $result['created'] );
        $this->assertSame( 1, $result['updated'] );
        $this->assertSame( 1, $result['unchanged'] );
        $this->assertSame( 2, $result['deactivated'] );
    }

    public function test_dry_run_does_not_persist_and_always_releases_lock(): void {
        $client = Mockery::mock( DistrictTeamApiClient::class );
        $validator = Mockery::mock( DirectorySourceValidator::class );
        $repository = Mockery::mock( TeamRolePostRepository::class );
        $lock = Mockery::mock( SyncLock::class );
        $lock->shouldReceive( 'acquire' )->once()->with( false );
        $lock->shouldReceive( 'release' )->once();
        $client->shouldReceive( 'fetch_teams' )->andReturn( array( array( 'id' => 'team-1' ) ) );
        $client->shouldReceive( 'fetch_roles' )->andReturn( array() );
        $validator->shouldReceive( 'validate' )->once();
        $repository->shouldReceive( 'upsert' )->once()->with( Mockery::type( 'array' ), 'team', true )->andReturn( 'unchanged' );
        $repository->shouldReceive( 'deactivate_missing' )->once()->with( array( 'team:team-1' => true ), true )->andReturn( 0 );
        Functions\expect( 'update_option' )->never();

        ( new DirectorySynchronizer( $client, $validator, $repository, $lock ) )->sync( true );
        $this->addToAssertionCount( 1 );
    }

    public function test_empty_source_is_rejected_unless_forced(): void {
        $client = Mockery::mock( DistrictTeamApiClient::class );
        $validator = Mockery::mock( DirectorySourceValidator::class );
        $repository = Mockery::mock( TeamRolePostRepository::class );
        $lock = Mockery::mock( SyncLock::class );
        $lock->shouldReceive( 'acquire' )->once();
        $lock->shouldReceive( 'release' )->once();
        $client->shouldReceive( 'fetch_teams' )->andReturn( array() );
        $client->shouldReceive( 'fetch_roles' )->andReturn( array() );
        $validator->shouldReceive( 'validate' )->once();

        $this->expectException( RuntimeException::class );
        ( new DirectorySynchronizer( $client, $validator, $repository, $lock ) )->sync();
    }

    public function test_sync_lock_rejects_live_lock_and_supports_force(): void {
        Functions\when( 'get_option' )->justReturn( array( 'expires' => time() + 60 ) );
        Functions\expect( 'update_option' )->never();
        $this->expectException( RuntimeException::class );
        ( new SyncLock() )->acquire();
    }

    public function test_sync_lock_force_and_release(): void {
        Functions\when( 'get_option' )->justReturn( array( 'expires' => time() + 60 ) );
        Functions\expect( 'update_option' )->once();
        Functions\expect( 'delete_option' )->once()->with( SyncLock::OPTION_NAME );
        $lock = new SyncLock();
        $lock->acquire( true );
        $lock->release();
        $this->addToAssertionCount( 1 );
    }

    public function test_cli_command_reports_configuration_error(): void {
        \WP_CLI::$errors = array();
        Functions\when( 'get_option' )->justReturn( '' );
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'untrailingslashit' )->returnArg();
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );

        ( new SyncCommand() )( array(), array() );

        $this->assertSame( 'District Team API URL is not configured.', \WP_CLI::$errors[0] );
    }
}
