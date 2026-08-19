<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Sync;

use RuntimeException;

class DirectorySynchronizer {
    public function __construct(
        private DistrictTeamApiClient $client,
        private DirectorySourceValidator $validator,
        private TeamRolePostRepository $repository,
        private SyncLock $lock
    ) {}

    public function sync( bool $dry_run = false, bool $force = false ): array {
        $this->lock->acquire( $force );
        try {
            $teams = $this->client->fetch_teams();
            $roles = $this->client->fetch_roles();
            $this->validator->validate( $teams, $roles );

            if ( empty( $teams ) && empty( $roles ) && ! $force ) {
                throw new RuntimeException( 'Source directory unexpectedly returned no teams or roles; refusing to deactivate posts without --force.' );
            }

            usort( $teams, static fn( $a, $b ) => (int) ( $a['tree_left'] ?? 0 ) <=> (int) ( $b['tree_left'] ?? 0 ) );
            $result = [ 'teams_received' => count( $teams ), 'roles_received' => count( $roles ), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'deactivated' => 0 ];
            $keys = [];

            foreach ( [ 'team' => $teams, 'role' => $roles ] as $type => $records ) {
                foreach ( $records as $record ) {
                    $keys[ $type . ':' . $record['id'] ] = true;
                    $outcome = $this->repository->upsert( $record, $type, $dry_run );
                    ++$result[ $outcome ];
                }
            }

            $result['deactivated'] = $this->repository->deactivate_missing( $keys, $dry_run );
            if ( ! $dry_run ) {
                update_option( 'district_team_last_sync_result', $result, false );
                update_option( 'district_team_last_successful_sync', gmdate( 'c' ), false );
            }
            return $result;
        } finally {
            $this->lock->release();
        }
    }
}
