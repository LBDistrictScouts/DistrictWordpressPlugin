<?php

namespace LBDistrictScouts\DistrictWordpressPlugin\Tests;

use Brain\Monkey\Functions;
use LBDistrictScouts\DistrictWordpressPlugin\CoordinatorIssueIngester;
use LBDistrictScouts\DistrictWordpressPlugin\CoordinatorIssueMessageValidator;
use LBDistrictScouts\DistrictWordpressPlugin\CoordinatorIssueQueue;
use RuntimeException;

class CoordinatorIssueQueueTest extends PluginTestCase {
    private function valid_message(): array {
        return array(
            'schema_version' => '1.0',
            'external_id' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Coordinator August 2026',
            'issue_date' => '2026-08-29',
            'issue_year' => 2026,
            'issue_month' => 8,
            'pdf_url' => 'https://cdn.example.org/coordinator-2026-08.pdf',
            'description' => 'News and updates for August.',
            'preview_image_url' => 'https://cdn.example.org/coordinator-2026-08.jpg',
        );
    }

    public function test_validates_schema_version_one_message(): void {
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        $message = ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $this->valid_message() ) );
        $this->assertSame( '550e8400-e29b-41d4-a716-446655440000', $message['external_id'] );
    }

    public function test_rejects_invalid_json_and_missing_properties(): void {
        $validator = new CoordinatorIssueMessageValidator();
        try {
            $validator->validate( '{' );
            $this->fail( 'Invalid JSON was accepted.' );
        } catch ( RuntimeException $error ) {
            $this->assertStringContainsString( 'valid JSON', $error->getMessage() );
        }
        $this->expectException( RuntimeException::class );
        $validator->validate( '{}' );
    }

    public function test_rejects_invalid_date_and_non_https_pdf(): void {
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        $message = $this->valid_message();
        $message['issue_date'] = '2026-02-30';
        try {
            ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $message ) );
            $this->fail( 'Invalid date was accepted.' );
        } catch ( RuntimeException $error ) {
            $this->assertStringContainsString( 'date', $error->getMessage() );
        }
        $message = $this->valid_message();
        $message['pdf_url'] = 'http://example.org/file.pdf';
        $this->expectExceptionMessage( 'HTTPS' );
        ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $message ) );
    }

    public function test_rejects_non_uuid_external_id(): void {
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        $message = $this->valid_message();
        $message['external_id'] = 'coordinator-2026-08';

        $this->expectExceptionMessage( 'external ID' );
        ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $message ) );
    }

    public function test_accepts_messages_without_optional_fields(): void {
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        $message = $this->valid_message();
        unset( $message['description'], $message['preview_image_url'] );

        $validated = ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $message ) );
        $this->assertArrayNotHasKey( 'description', $validated );
        $this->assertArrayNotHasKey( 'preview_image_url', $validated );
    }

    public function test_rejects_invalid_optional_fields(): void {
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        $message = $this->valid_message();
        $message['preview_image_url'] = 'http://example.org/preview.jpg';
        $this->expectExceptionMessage( 'preview image URL' );
        ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $message ) );
    }

    public function test_reports_all_schema_validation_errors(): void {
        Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
        $message = $this->valid_message();
        $message['title'] = '';
        $message['issue_month'] = 13;
        $message['pdf_url'] = 'http://example.org/file.pdf';

        try {
            ( new CoordinatorIssueMessageValidator() )->validate( json_encode( $message ) );
            $this->fail( 'Invalid message was accepted.' );
        } catch ( \LBDistrictScouts\DistrictWordpressPlugin\CoordinatorIssueValidationException $error ) {
            $this->assertCount( 3, $error->get_validation_errors() );
            $this->assertStringContainsString( 'title', $error->getMessage() );
            $this->assertStringContainsString( 'issue_month', $error->getMessage() );
            $this->assertStringContainsString( 'pdf_url', $error->getMessage() );
        }
    }

    public function test_registers_interval_schedules_and_unschedules_cron(): void {
        Functions\when( '__' )->returnArg();
        $schedules = CoordinatorIssueQueue::add_interval( array() );
        $this->assertSame( 300, $schedules['five_minutes']['interval'] );
        Functions\when( 'wp_next_scheduled' )->justReturn( false );
        Functions\expect( 'wp_schedule_event' )->once()->with( \Mockery::type( 'int' ), 'five_minutes', CoordinatorIssueQueue::HOOK );
        CoordinatorIssueQueue::schedule();
        Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( CoordinatorIssueQueue::HOOK );
        CoordinatorIssueQueue::unschedule();
    }

    public function test_consumer_returns_when_queue_is_not_configured(): void {
        Functions\when( 'get_option' )->justReturn( '' );
        ( new CoordinatorIssueQueue() )->consume();
        $this->addToAssertionCount( 1 );
    }

    public function test_existing_ingested_issue_is_published(): void {
        Functions\when( 'get_posts' )->justReturn( array( 42 ) );
        Functions\expect( 'wp_update_post' )->once()->with(
            array( 'ID' => 42, 'post_status' => 'publish' ),
            true
        )->andReturn( 42 );
        Functions\when( 'is_wp_error' )->justReturn( false );

        $post_id = ( new CoordinatorIssueIngester() )->ingest( array( 'external_id' => '550e8400-e29b-41d4-a716-446655440000' ) );

        $this->assertSame( 42, $post_id );
    }

    public function test_new_ingestion_publishes_only_after_metadata_is_written(): void {
        $source = file_get_contents( dirname( __DIR__ ) . '/src/CoordinatorIssueIngester.php' );
        $metadata_position = strpos( $source, 'update_post_meta' );
        $publish_position = strpos( $source, '$this->publish( (int) $post_id )' );

        $this->assertNotFalse( $metadata_position );
        $this->assertNotFalse( $publish_position );
        $this->assertGreaterThan( $metadata_position, $publish_position );
    }
}
