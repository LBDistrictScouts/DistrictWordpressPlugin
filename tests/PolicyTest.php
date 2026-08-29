<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\Policy;

class PolicyTest extends PluginTestCase {

    public function test_registers_public_rest_enabled_post_type(): void {
        Functions\when( '__' )->returnArg();
        Functions\expect( 'register_post_type' )
            ->once()
            ->with(
                Policy::POST_TYPE,
                \Mockery::on(
                    function ( array $args ): bool {
                        $this->assertTrue( $args['public'] );
                        $this->assertTrue( $args['show_in_rest'] );
                        $this->assertTrue( $args['has_archive'] );
                        $this->assertSame( array( 'slug' => 'policies' ), $args['rewrite'] );
                        $this->assertContains( 'editor', $args['supports'] );
                        $this->assertSame( 'Policies', $args['labels']['name'] );
                        return true;
                    }
                )
            );

        ( new Policy() )->register();
    }
}
