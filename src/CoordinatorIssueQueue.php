<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use Aws\Sqs\SqsClient;

class CoordinatorIssueQueue {
    public const HOOK = 'district_coordinator_issue_queue_poll';
    public const QUEUE_URL_OPTION = 'district_coordinator_issue_sqs_queue_url';
    public const REGION_OPTION = 'district_coordinator_issue_aws_region';

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, 'five_minutes', self::HOOK );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::HOOK );
    }

    public static function add_interval( array $schedules ): array {
        $schedules['five_minutes'] = array( 'interval' => 300, 'display' => __( 'Every five minutes', 'district-wordpress-plugin' ) );
        return $schedules;
    }

    public function consume(): void {
        $queue_url = defined( 'DISTRICT_COORDINATOR_ISSUE_SQS_QUEUE_URL' ) ? DISTRICT_COORDINATOR_ISSUE_SQS_QUEUE_URL : get_option( self::QUEUE_URL_OPTION, '' );
        $region = defined( 'DISTRICT_COORDINATOR_ISSUE_AWS_REGION' ) ? DISTRICT_COORDINATOR_ISSUE_AWS_REGION : get_option( self::REGION_OPTION, '' );
        if ( ! $queue_url || ! $region ) {
            return;
        }
        $client = new SqsClient( array( 'version' => 'latest', 'region' => $region ) );
        $result = $client->receiveMessage( array( 'QueueUrl' => $queue_url, 'MaxNumberOfMessages' => 10, 'WaitTimeSeconds' => 0, 'VisibilityTimeout' => 120 ) );
        $validator = new CoordinatorIssueMessageValidator();
        $ingester = new CoordinatorIssueIngester();
        foreach ( $result->get( 'Messages' ) ?? array() as $message ) {
            try {
                $ingester->ingest( $validator->validate( (string) ( $message['Body'] ?? '' ) ) );
                $client->deleteMessage( array( 'QueueUrl' => $queue_url, 'ReceiptHandle' => $message['ReceiptHandle'] ) );
            } catch ( \Throwable $error ) {
                error_log( 'Coordinator Issue SQS ingestion failed: ' . $error->getMessage() );
            }
        }
    }
}
