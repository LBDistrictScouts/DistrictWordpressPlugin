<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\RewriteManager;

/**
 * Tests for rewrite schema upgrades.
 */
class RewriteManagerTest extends PluginTestCase {

    /**
     * An up-to-date installation must not flush rewrite rules on every request.
     *
     * @return void
     */
    public function test_does_not_flush_when_version_is_current(): void {
        Functions\expect( 'get_option' )
            ->once()
            ->with( RewriteManager::OPTION_NAME )
            ->andReturn( RewriteManager::VERSION );

        Functions\expect( 'flush_rewrite_rules' )->never();
        Functions\expect( 'update_option' )->never();

        RewriteManager::maybe_flush();

        $this->assertSame( '1', RewriteManager::VERSION );
    }

    /**
     * An upgrade must flush once and persist the new schema version.
     *
     * @return void
     */
    public function test_flushes_and_updates_when_version_is_stale(): void {
        Functions\expect( 'get_option' )
            ->once()
            ->with( RewriteManager::OPTION_NAME )
            ->andReturn( '0' );

        Functions\expect( 'flush_rewrite_rules' )->once();
        Functions\expect( 'update_option' )
            ->once()
            ->with( RewriteManager::OPTION_NAME, RewriteManager::VERSION );

        RewriteManager::maybe_flush();

        $this->assertSame( 'districtwp_rewrite_version', RewriteManager::OPTION_NAME );
    }
}
