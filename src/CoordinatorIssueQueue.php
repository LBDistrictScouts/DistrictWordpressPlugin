<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use Aws\Sqs\SqsClient;

class CoordinatorIssueQueue {
    public const HOOK = 'district_coordinator_issue_queue_poll';
    public const QUEUE_URL_OPTION = 'district_coordinator_issue_sqs_queue_url';
    public const REGION_OPTION = 'district_coordinator_issue_aws_region';
    public const LAST_POLL_OPTION = 'district_coordinator_issue_queue_last_poll';
    public const LAST_RESULT_OPTION = 'district_coordinator_issue_queue_last_result';

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

        $poll = array(
            'received'  => 0,
            'processed' => 0,
            'failed'    => 0,
            'error'     => '',
            'errors'    => array(),
        );
        update_option( self::LAST_POLL_OPTION, gmdate( 'c' ), false );

        try {
            $client_config = array( 'version' => 'latest', 'region' => $region );
            $profile = defined( 'DISTRICT_COORDINATOR_ISSUE_AWS_PROFILE' ) ? (string) constant( 'DISTRICT_COORDINATOR_ISSUE_AWS_PROFILE' ) : '';
            if ( '' !== $profile ) {
                $client_config['profile'] = $profile;
            }
            $client = new SqsClient( $client_config );
            $result = $client->receiveMessage( array( 'QueueUrl' => $queue_url, 'MaxNumberOfMessages' => 10, 'WaitTimeSeconds' => 0, 'VisibilityTimeout' => 120 ) );
            $messages = $result->get( 'Messages' ) ?? array();
            $poll['received'] = count( $messages );
            $validator = new CoordinatorIssueMessageValidator();
            $ingester = new CoordinatorIssueIngester();
            foreach ( $messages as $message ) {
                try {
                    $ingester->ingest( $validator->validate( (string) ( $message['Body'] ?? '' ) ) );
                    $client->deleteMessage( array( 'QueueUrl' => $queue_url, 'ReceiptHandle' => $message['ReceiptHandle'] ) );
                    ++$poll['processed'];
                } catch ( \Throwable $error ) {
                    ++$poll['failed'];
                    $poll['error'] = $error->getMessage();
                    $poll['errors'][] = array(
                        'message_id' => (string) ( $message['MessageId'] ?? 'Unknown' ),
                        'type'       => $error instanceof CoordinatorIssueValidationException ? 'Schema validation' : 'Ingestion',
                        'message'    => $error->getMessage(),
                        'payload'    => (string) ( $message['Body'] ?? '' ),
                    );
                    error_log( 'Coordinator Issue SQS ingestion failed: ' . $error->getMessage() );
                }
            }
        } catch ( \Throwable $error ) {
            $poll['error'] = $error->getMessage();
            update_option( self::LAST_RESULT_OPTION, $poll, false );
            throw $error;
        }

        update_option( self::LAST_RESULT_OPTION, $poll, false );
    }
}
