<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Sync;

use RuntimeException;

class SyncLock {
    private const OPTION = 'district_team_sync_lock';
    private const TTL = 900;

    public function acquire( bool $force = false ): void {
        $existing = get_option( self::OPTION );
        if ( ! $force && is_array( $existing ) && (int) ( $existing['expires'] ?? 0 ) > time() ) {
            throw new RuntimeException( 'A district team synchronization is already running.' );
        }
        update_option( self::OPTION, [ 'expires' => time() + self::TTL ], false );
    }

    public function release(): void {
        delete_option( self::OPTION );
    }
}
