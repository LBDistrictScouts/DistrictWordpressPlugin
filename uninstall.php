<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Cleanup plugin options, custom tables, etc.
// delete_option( 'districtwp_version' );
delete_option( 'district_coordinator_issue_sqs_queue_url' );
delete_option( 'district_coordinator_issue_aws_region' );
wp_clear_scheduled_hook( 'district_coordinator_issue_queue_poll' );
