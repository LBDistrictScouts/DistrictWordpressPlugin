<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Sync;

use RuntimeException;

class DirectorySourceValidator {
    public function validate( array $teams, array $roles ): void {
        $team_ids = [];
        $slugs = [];

        foreach ( $teams as $team ) {
            $id = (string) ( $team['id'] ?? '' );
            $slug = (string) ( $team['slug'] ?? '' );
            if ( '' === $id || '' === $slug ) {
                throw new RuntimeException( 'Every team must have an id and slug.' );
            }
            $this->claim_slug( $slug, 'team', $id, $slugs );
            $team_ids[ $id ] = $team;
        }

        foreach ( $roles as $role ) {
            $id = (string) ( $role['id'] ?? '' );
            $slug = (string) ( $role['slug'] ?? '' );
            $team_id = (string) ( $role['team_id'] ?? '' );
            if ( '' === $id || '' === $slug || '' === $team_id ) {
                throw new RuntimeException( 'Every role must have an id, slug, and team_id.' );
            }
            $this->claim_slug( $slug, 'role', $id, $slugs );
            if ( ! isset( $team_ids[ $team_id ] ) ) {
                throw new RuntimeException( sprintf( 'Role %s refers to missing team %s.', $id, $team_id ) );
            }
        }

        foreach ( $teams as $team ) {
            $parent = $team['team_parent_id'] ?? null;
            if ( null !== $parent && '' !== $parent && ! isset( $team_ids[ (string) $parent ] ) ) {
                throw new RuntimeException( sprintf( 'Team %s refers to missing parent %s.', $team['id'], $parent ) );
            }
        }

        $this->assert_no_cycles( $team_ids );
    }

    private function claim_slug( string $slug, string $type, string $id, array &$slugs ): void {
        if ( isset( $slugs[ $slug ] ) ) {
            throw new RuntimeException( sprintf( 'Slug "%s" is duplicated by %s %s and %s.', $slug, $type, $id, $slugs[ $slug ] ) );
        }
        $slugs[ $slug ] = $type . ' ' . $id;
    }

    private function assert_no_cycles( array $teams ): void {
        foreach ( $teams as $id => $team ) {
            $seen = [];
            $cursor = $id;
            while ( isset( $teams[ $cursor ] ) ) {
                if ( isset( $seen[ $cursor ] ) ) {
                    throw new RuntimeException( sprintf( 'Team hierarchy contains a cycle involving %s.', $cursor ) );
                }
                $seen[ $cursor ] = true;
                $parent = $teams[ $cursor ]['team_parent_id'] ?? null;
                if ( null === $parent || '' === $parent ) {
                    break;
                }
                $cursor = (string) $parent;
            }
        }
    }
}
