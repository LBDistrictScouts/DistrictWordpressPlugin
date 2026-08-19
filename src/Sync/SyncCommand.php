<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Sync;

class SyncCommand {
    public function __invoke( array $args, array $assoc_args ): void {
        $dry_run = isset( $assoc_args['dry-run'] );
        $force = isset( $assoc_args['force'] );
        $sync = new DirectorySynchronizer( new DistrictTeamApiClient(), new DirectorySourceValidator(), new TeamRolePostRepository(), new SyncLock() );

        try {
            $result = $sync->sync( $dry_run, $force );
            foreach ( $result as $key => $value ) {
                \WP_CLI::log( sprintf( '%s: %s', $key, $value ) );
            }
            \WP_CLI::success( $dry_run ? 'District team dry run completed.' : 'District team synchronization completed.' );
        } catch ( \Throwable $error ) {
            \WP_CLI::error( $error->getMessage() );
        }
    }
}
