<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Sync;

use RuntimeException;
use WP_Error;

class TeamRolePostRepository {
    public function find_all_synced(): array {
        return get_posts( [
            'post_type' => 'team_role',
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query' => [ [ 'key' => '_district_source_id', 'compare' => 'EXISTS' ] ],
        ] );
    }

    public function upsert( array $record, string $type, bool $dry_run = false ): string {
        $source_id = sanitize_text_field( (string) $record['id'] );
        $existing = $this->find_by_source( $type, $source_id );
        $title = 'team' === $type ? (string) $record['team_name'] : (string) $record['name'];
        $slug = sanitize_title( (string) $record['slug'] );
        $structural = [
            'post_type' => 'team_role',
            'post_title' => sanitize_text_field( $title ),
            'post_name' => $slug,
        ];

        if ( $existing ) {
            $changed = $existing->post_title !== $structural['post_title'] || $existing->post_name !== $slug || $this->metadata_changed( $existing->ID, $record, $type );
            if ( ! $changed ) {
                return 'unchanged';
            }
            if ( ! $dry_run ) {
                $structural['ID'] = $existing->ID;
                $result = wp_update_post( $structural, true );
                $this->assert_write( $result );
                $this->write_meta( $existing->ID, $record, $type );
            }
            return 'updated';
        }

        if ( $dry_run ) {
            return 'created';
        }
        $structural['post_status'] = 'draft';
        if ( 'role' === $type && ! empty( $record['description'] ) ) {
            $structural['post_excerpt'] = sanitize_textarea_field( (string) $record['description'] );
        }
        $post_id = wp_insert_post( $structural, true );
        $this->assert_write( $post_id );
        $this->write_meta( (int) $post_id, $record, $type );
        return 'created';
    }

    public function deactivate_missing( array $source_keys, bool $dry_run = false ): int {
        $count = 0;
        foreach ( $this->find_all_synced() as $post ) {
            $key = get_post_meta( $post->ID, '_district_source_type', true ) . ':' . get_post_meta( $post->ID, '_district_source_id', true );
            if ( isset( $source_keys[ $key ] ) || 'draft' === $post->post_status ) {
                continue;
            }
            ++$count;
            if ( ! $dry_run ) {
                $result = wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'draft' ], true );
                $this->assert_write( $result );
                update_post_meta( $post->ID, '_district_deactivated_at', gmdate( 'c' ) );
                update_post_meta( $post->ID, '_district_deactivated_reason', 'missing_from_source' );
            }
        }
        return $count;
    }

    private function find_by_source( string $type, string $id ) {
        $posts = get_posts( [
            'post_type' => 'team_role', 'post_status' => 'any', 'numberposts' => 2,
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => '_district_source_type', 'value' => $type ],
                [ 'key' => '_district_source_id', 'value' => $id ],
            ],
        ] );
        if ( count( $posts ) > 1 ) {
            throw new RuntimeException( sprintf( 'Duplicate WordPress posts for %s %s.', $type, $id ) );
        }
        return $posts[0] ?? null;
    }

    private function metadata_changed( int $post_id, array $record, string $type ): bool {
        foreach ( $this->metadata( $record, $type ) as $key => $value ) {
            if ( (string) get_post_meta( $post_id, $key, true ) !== (string) $value ) {
                return true;
            }
        }
        return false;
    }

    private function write_meta( int $post_id, array $record, string $type ): void {
        foreach ( $this->metadata( $record, $type ) as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
        update_post_meta( $post_id, '_district_last_synced_at', gmdate( 'c' ) );
    }

    private function metadata( array $record, string $type ): array {
        $meta = [ '_district_source_id' => sanitize_text_field( (string) $record['id'] ), '_district_source_type' => $type ];
        if ( 'team' === $type ) {
            $meta['_district_parent_team_id'] = sanitize_text_field( (string) ( $record['team_parent_id'] ?? '' ) );
        } else {
            $meta['_district_owner_team_id'] = sanitize_text_field( (string) $record['team_id'] );
            $meta['_district_role_description'] = sanitize_textarea_field( (string) ( $record['description'] ?? '' ) );
            $meta['_district_currently_filled'] = ! empty( $record['currently_filled'] ) ? '1' : '0';
        }
        return $meta;
    }

    private function assert_write( $result ): void {
        if ( $result instanceof WP_Error || ! $result ) {
            $message = $result instanceof WP_Error ? $result->get_error_message() : 'Unknown WordPress write failure.';
            throw new RuntimeException( $message );
        }
    }
}
