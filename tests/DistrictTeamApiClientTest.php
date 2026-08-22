<?php
namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DistrictTeamApiClient;

class DistrictTeamApiClientTest extends PluginTestCase {
    public function test_fetches_all_pages_from_api_prefix(): void {
        $urls = [];

        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'untrailingslashit' )->alias( static fn( $url ) => rtrim( $url, '/' ) );
        Functions\when( 'add_query_arg' )->alias(
            static fn( $args, $url ) => $url . '?' . http_build_query( $args )
        );
        Functions\when( 'wp_remote_get' )->alias(
            static function ( $url ) use ( &$urls ) {
                $urls[] = $url;
                $page = count( $urls );

                return [
                    'response' => [ 'code' => 200 ],
                    'body'     => wp_json_encode(
                        [
                            'data'       => [ [ 'id' => 'team-' . $page ] ],
                            'pagination' => [ 'page' => $page, 'page_count' => 2 ],
                        ]
                    ),
                ];
            }
        );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $response ) => $response['response']['code'] );
        Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $response ) => $response['body'] );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $records = ( new DistrictTeamApiClient( 'https://district-team-test.lbdscouts.org.uk/' ) )->fetch_teams();

        $this->assertSame( [ [ 'id' => 'team-1' ], [ 'id' => 'team-2' ] ], $records );
        $this->assertSame(
            [
                'https://district-team-test.lbdscouts.org.uk/api/teams?limit=100&page=1',
                'https://district-team-test.lbdscouts.org.uk/api/teams?limit=100&page=2',
            ],
            $urls
        );
    }

    public function test_does_not_duplicate_configured_api_prefix(): void {
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'untrailingslashit' )->alias( static fn( $url ) => rtrim( $url, '/' ) );
        Functions\when( 'add_query_arg' )->alias(
            static function ( $args, $url ) {
                self::assertSame( 'https://example.org/api/roles', $url );

                return $url . '?' . http_build_query( $args );
            }
        );
        Functions\when( 'wp_remote_get' )->justReturn(
            [
                'response' => [ 'code' => 200 ],
                'body'     => '{"data":[],"pagination":{"page_count":1}}',
            ]
        );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $response ) => $response['response']['code'] );
        Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $response ) => $response['body'] );

        $this->assertSame( [], ( new DistrictTeamApiClient( 'https://example.org/api' ) )->fetch_roles() );
    }
}
