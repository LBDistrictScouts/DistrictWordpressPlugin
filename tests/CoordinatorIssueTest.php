<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\CoordinatorIssue;

class CoordinatorIssueTest extends PluginTestCase {
    protected function tearDown(): void {
        $_POST = array();
        parent::tearDown();
    }

    private function mock_output(): void {
        Functions\when( '__' )->returnArg();
        Functions\when( 'esc_html__' )->returnArg();
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'selected' )->alias( static fn( $value, $expected ) => $value === $expected ? ' selected="selected"' : '' );
        Functions\when( 'date_i18n' )->alias( static fn( $format, $timestamp ) => date( $format, $timestamp ) );
    }

    public function test_registers_public_post_type_metadata_and_hooks(): void {
        Functions\when( '__' )->returnArg();
        Functions\expect( 'register_post_type' )->once()->with(
            CoordinatorIssue::POST_TYPE,
            \Mockery::on( static fn( $args ) => $args['show_in_rest'] && 'coordinator-issues' === $args['rewrite']['slug'] && in_array( 'custom-fields', $args['supports'], true ) )
        );
        $meta = array();
        Functions\when( 'register_post_meta' )->alias( static function ( $type, $key, $args ) use ( &$meta ) {
            $meta[ $key ] = $args['type'];
            return true;
        } );
        Functions\expect( 'add_action' )->times( 5 );

        ( new CoordinatorIssue() )->register();

        $this->assertSame( 'integer', $meta[ CoordinatorIssue::PDF_META ] );
        $this->assertSame( 'string', $meta[ CoordinatorIssue::DATE_META ] );
        $this->assertSame( 'integer', $meta[ CoordinatorIssue::YEAR_META ] );
        $this->assertSame( 'integer', $meta[ CoordinatorIssue::MONTH_META ] );
    }

    public function test_adds_high_priority_primary_meta_box(): void {
        Functions\when( '__' )->returnArg();
        Functions\expect( 'add_meta_box' )->once()->with(
            'coordinator_issue_details', \Mockery::type( 'string' ), \Mockery::type( 'array' ),
            CoordinatorIssue::POST_TYPE, 'normal', 'high'
        );
        ( new CoordinatorIssue() )->add_meta_box();
        $this->addToAssertionCount( 1 );
    }

    public function test_renders_pdf_and_editable_issue_fields(): void {
        $this->mock_output();
        $values = array(
            CoordinatorIssue::PDF_META => 8,
            CoordinatorIssue::DATE_META => '2026-08-29',
            CoordinatorIssue::YEAR_META => '2026',
            CoordinatorIssue::MONTH_META => 8,
        );
        Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => $values[ $key ] );
        Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://example.org/issue.pdf' );
        Functions\when( 'get_the_title' )->justReturn( 'August issue' );
        Functions\expect( 'wp_nonce_field' )->once();

        ob_start();
        ( new CoordinatorIssue() )->render_meta_box( new \WP_Post( array( 'ID' => 42 ) ) );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'August issue', $output );
        $this->assertStringContainsString( 'issue.pdf', $output );
        $this->assertStringContainsString( 'type="date"', $output );
        $this->assertStringContainsString( '2026-08-29', $output );
        $this->assertStringContainsString( 'coordinator_issue_year', $output );
        $this->assertStringContainsString( 'value="8" selected', $output );
    }

    public function test_saves_valid_pdf_date_year_and_month(): void {
        $_POST = array(
            'coordinator_issue_nonce' => 'valid',
            'coordinator_issue_pdf_id' => '8',
            'coordinator_issue_date' => '2026-08-29',
            'coordinator_issue_year' => '2026',
            'coordinator_issue_month' => '8',
        );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'wp_is_post_autosave' )->justReturn( false );
        Functions\when( 'wp_is_post_revision' )->justReturn( false );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );
        Functions\when( 'get_post_mime_type' )->justReturn( 'application/pdf' );
        $saved = array();
        Functions\expect( 'update_post_meta' )->times( 4 )->andReturnUsing(
            static function ( $id, $key, $value ) use ( &$saved ) { $saved[ $key ] = $value; return true; }
        );

        ( new CoordinatorIssue() )->save( 42, new \WP_Post( array( 'ID' => 42 ) ) );

        $this->assertSame( 8, $saved[ CoordinatorIssue::PDF_META ] );
        $this->assertSame( '2026-08-29', $saved[ CoordinatorIssue::DATE_META ] );
        $this->assertSame( 2026, $saved[ CoordinatorIssue::YEAR_META ] );
        $this->assertSame( 8, $saved[ CoordinatorIssue::MONTH_META ] );
    }

    public function test_empty_fields_remove_saved_metadata(): void {
        $_POST = array(
            'coordinator_issue_nonce' => 'valid', 'coordinator_issue_pdf_id' => '',
            'coordinator_issue_date' => '', 'coordinator_issue_year' => '', 'coordinator_issue_month' => '',
        );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'wp_is_post_autosave' )->justReturn( false );
        Functions\when( 'wp_is_post_revision' )->justReturn( false );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );
        Functions\expect( 'delete_post_meta' )->times( 4 );

        ( new CoordinatorIssue() )->save( 42, new \WP_Post( array( 'ID' => 42 ) ) );
        $this->addToAssertionCount( 1 );
    }

    public function test_save_requires_valid_nonce(): void {
        Functions\expect( 'update_post_meta' )->never();
        ( new CoordinatorIssue() )->save( 42, new \WP_Post( array( 'ID' => 42 ) ) );
        $this->addToAssertionCount( 1 );
    }

    public function test_loads_media_and_renders_pdf_selector_script_only_on_issue_screen(): void {
        Functions\when( '__' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'get_current_screen' )->justReturn( (object) array( 'post_type' => CoordinatorIssue::POST_TYPE ) );
        Functions\expect( 'wp_enqueue_media' )->once();
        $issue = new CoordinatorIssue();
        $issue->enqueue_media();
        ob_start();
        $issue->render_media_script();
        $script = ob_get_clean();
        $this->assertStringContainsString( "type: 'application/pdf'", $script );
        $this->assertStringContainsString( 'wp.media', $script );
    }
}
