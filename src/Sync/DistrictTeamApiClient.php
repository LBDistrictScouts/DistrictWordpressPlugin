<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Sync;

use RuntimeException;

class DistrictTeamApiClient {
    public const OPTION_NAME = 'district_team_api_url';

    private string $base_url;

    public function __construct( ?string $base_url = null ) {
        if ( null !== $base_url ) {
            $configured = $base_url;
        } elseif ( defined( 'DISTRICT_TEAM_API_URL' ) ) {
            $configured = DISTRICT_TEAM_API_URL;
        } else {
            $configured = (string) get_option( self::OPTION_NAME, '' );
        }

        $this->base_url = untrailingslashit( esc_url_raw( $configured ) );
    }

    public function fetch_teams(): array {
        return $this->fetch_collection( 'teams' );
    }

    public function fetch_roles(): array {
        return $this->fetch_collection( 'roles' );
    }

    private function fetch_collection( string $resource ): array {
        if ( '' === $this->base_url ) {
            throw new RuntimeException( 'District Team API URL is not configured.' );
        }

        $records = [];
        $page = 1;
        do {
            $url = add_query_arg( [ 'limit' => 100, 'page' => $page ], $this->api_url( $resource ) );
            $response = wp_remote_get( $url, [
                'headers' => [ 'Accept' => 'application/json' ],
                'timeout' => 15,
                'redirection' => 3,
            ] );
            if ( is_wp_error( $response ) ) {
                throw new RuntimeException( sprintf( 'Failed to fetch %s: %s', $resource, $response->get_error_message() ) );
            }
            $status = wp_remote_retrieve_response_code( $response );
            if ( $status < 200 || $status >= 300 ) {
                throw new RuntimeException( sprintf( 'Failed to fetch %s: HTTP %d.', $resource, $status ) );
            }
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $body ) || ! isset( $body['data'], $body['pagination'] ) || ! is_array( $body['data'] ) ) {
                throw new RuntimeException( sprintf( 'Malformed %s API response.', $resource ) );
            }
            $records = array_merge( $records, $body['data'] );
            $page_count = max( 1, (int) ( $body['pagination']['page_count'] ?? 0 ) );
            ++$page;
        } while ( $page <= $page_count );

        return $records;
    }

    private function api_url( string $resource ): string {
        $api_base = preg_match( '#/api$#i', $this->base_url ) ? $this->base_url : $this->base_url . '/api';

        return $api_base . '/' . $resource;
    }
}
