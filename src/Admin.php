<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use LBDistrictScouts\DistrictWordpressPlugin\Sync\DirectorySourceValidator;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DirectorySynchronizer;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\DistrictTeamApiClient;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\SyncLock;
use LBDistrictScouts\DistrictWordpressPlugin\Sync\TeamRolePostRepository;

class Admin {
    private function format_monitor_time( $value ): string {
        if ( ! $value ) {
            return 'Never';
        }
        $timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
        return false === $timestamp ? (string) $value : wp_date( 'Y-m-d H:i:s T', $timestamp );
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_district_team_sync', [ $this, 'run_sync' ] );
        add_action( 'admin_post_district_coordinator_queue_poll', [ $this, 'run_coordinator_queue_poll' ] );
    }

    public function admin_menu() {
        add_options_page( 'District Plugin Settings', 'District Plugin', 'manage_options', 'district-wordpress-plugin', [ $this, 'settings_page' ] );
        add_management_page( 'District Team Sync', 'District Team Sync', 'manage_options', 'district-team-sync', [ $this, 'sync_page' ] );
        add_management_page( 'Coordinator Queue', 'Coordinator Queue', 'manage_options', 'district-coordinator-queue', [ $this, 'coordinator_queue_page' ] );
    }

    public function register_settings() {
        register_setting(
            'district_wordpress_plugin',
            DistrictTeamApiClient::OPTION_NAME,
            [
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            ]
        );
        register_setting( 'district_wordpress_plugin', CoordinatorIssueQueue::QUEUE_URL_OPTION, array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );
        register_setting( 'district_wordpress_plugin', CoordinatorIssueQueue::REGION_OPTION, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );

        add_settings_section( 'district_team_sync', 'District Team Synchronization', '__return_false', 'district-wordpress-plugin' );
        add_settings_field(
            DistrictTeamApiClient::OPTION_NAME,
            'CakePHP API URL',
            [ $this, 'api_url_field' ],
            'district-wordpress-plugin',
            'district_team_sync'
        );
        add_settings_section( 'district_coordinator_issue_queue', 'Coordinator Issue SQS ingestion', '__return_false', 'district-wordpress-plugin' );
        add_settings_field( CoordinatorIssueQueue::QUEUE_URL_OPTION, 'SQS Queue URL', array( $this, 'coordinator_queue_url_field' ), 'district-wordpress-plugin', 'district_coordinator_issue_queue' );
        add_settings_field( CoordinatorIssueQueue::REGION_OPTION, 'AWS Region', array( $this, 'coordinator_region_field' ), 'district-wordpress-plugin', 'district_coordinator_issue_queue' );
    }

    public function coordinator_queue_url_field(): void {
        $overridden = defined( 'DISTRICT_COORDINATOR_ISSUE_SQS_QUEUE_URL' );
        $value = $overridden ? DISTRICT_COORDINATOR_ISSUE_SQS_QUEUE_URL : get_option( CoordinatorIssueQueue::QUEUE_URL_OPTION, '' );
        printf( '<input type="url" class="regular-text code" name="%1$s" value="%2$s" %3$s>', esc_attr( CoordinatorIssueQueue::QUEUE_URL_OPTION ), esc_attr( (string) $value ), $overridden ? 'disabled="disabled"' : '' );
        echo '<p class="description">AWS credentials use the SDK default credential chain. The queue should have a dead-letter queue for repeatedly invalid messages.</p>';
    }

    public function coordinator_region_field(): void {
        $overridden = defined( 'DISTRICT_COORDINATOR_ISSUE_AWS_REGION' );
        $value = $overridden ? DISTRICT_COORDINATOR_ISSUE_AWS_REGION : get_option( CoordinatorIssueQueue::REGION_OPTION, '' );
        printf( '<input type="text" class="regular-text code" name="%1$s" value="%2$s" placeholder="eu-west-2" %3$s>', esc_attr( CoordinatorIssueQueue::REGION_OPTION ), esc_attr( (string) $value ), $overridden ? 'disabled="disabled"' : '' );
        echo '<p class="description">Message schema: <code>schema/coordinator-issue-message.schema.json</code>.</p>';
    }

    public function api_url_field() {
        $constant_override = defined( 'DISTRICT_TEAM_API_URL' );
        $value = $constant_override ? DISTRICT_TEAM_API_URL : get_option( DistrictTeamApiClient::OPTION_NAME, '' );

        printf(
            '<input type="url" class="regular-text code" name="%1$s" value="%2$s" placeholder="https://example.org/api" %3$s />',
            esc_attr( DistrictTeamApiClient::OPTION_NAME ),
            esc_attr( $value ),
            $constant_override ? 'disabled="disabled"' : ''
        );

        if ( $constant_override ) {
            echo '<p class="description">Configured by the <code>DISTRICT_TEAM_API_URL</code> constant. The WordPress setting is disabled.</p>';
        } else {
            echo '<p class="description">Base URL for the CakePHP directory service. The plugin uses its <code>/api/teams</code> and <code>/api/roles</code> endpoints.</p>';
        }
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        echo '<div class="wrap"><h1>District WordPress Plugin</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'district_wordpress_plugin' );
        do_settings_sections( 'district-wordpress-plugin' );
        submit_button();
        echo '</form></div>';
    }

    public function sync_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $configured_url = defined( 'DISTRICT_TEAM_API_URL' )
            ? DISTRICT_TEAM_API_URL
            : get_option( DistrictTeamApiClient::OPTION_NAME, '' );
        $last_sync = get_option( 'district_team_last_successful_sync', '' );
        $last_result = get_option( 'district_team_last_sync_result', [] );
        $lock = get_option( SyncLock::OPTION_NAME );
        $running = is_array( $lock ) && (int) ( $lock['expires'] ?? 0 ) > time();
        $notice = isset( $_GET['district_sync_notice'] ) ? sanitize_key( wp_unslash( $_GET['district_sync_notice'] ) ) : '';
        $dry_run_result = get_transient( 'district_team_sync_dry_run_' . get_current_user_id() );

        echo '<div class="wrap"><h1>District Team Sync</h1>';

        if ( 'success' === $notice ) {
            echo '<div class="notice notice-success"><p>District team synchronization completed successfully.</p></div>';
        } elseif ( 'dry-run' === $notice ) {
            echo '<div class="notice notice-info"><p>Dry run completed. No WordPress content was changed.</p></div>';
        } elseif ( 'error' === $notice ) {
            $error = get_transient( 'district_team_sync_error_' . get_current_user_id() );
            echo '<div class="notice notice-error"><p><strong>Synchronization failed:</strong> ' . esc_html( (string) $error ) . '</p></div>';
            delete_transient( 'district_team_sync_error_' . get_current_user_id() );
        }

        echo '<h2>Status</h2><table class="widefat striped" style="max-width:900px"><tbody>';
        echo '<tr><th style="width:240px">CakePHP API</th><td><code>' . esc_html( $configured_url ?: 'Not configured' ) . '</code></td></tr>';
        echo '<tr><th>Configuration source</th><td>' . ( defined( 'DISTRICT_TEAM_API_URL' ) ? '<code>DISTRICT_TEAM_API_URL</code> constant' : 'WordPress setting' ) . '</td></tr>';
        echo '<tr><th>Sync status</th><td>' . ( $running ? '<strong>Running / locked</strong>' : 'Idle' ) . '</td></tr>';
        echo '<tr><th>Last successful sync</th><td>' . esc_html( $this->format_monitor_time( $last_sync ) ) . '</td></tr>';
        echo '</tbody></table>';

        $display_result = is_array( $dry_run_result ) ? $dry_run_result : $last_result;
        if ( is_array( $display_result ) && ! empty( $display_result ) ) {
            echo '<h2>' . ( is_array( $dry_run_result ) ? 'Dry Run Result' : 'Last Sync Result' ) . '</h2>';
            echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Teams received</th><th>Roles received</th><th>Created</th><th>Updated</th><th>Unchanged</th><th>Deactivated</th></tr></thead><tbody><tr>';
            foreach ( [ 'teams_received', 'roles_received', 'created', 'updated', 'unchanged', 'deactivated' ] as $key ) {
                echo '<td>' . esc_html( (string) ( $display_result[ $key ] ?? 0 ) ) . '</td>';
            }
            echo '</tr></tbody></table>';
        }
        delete_transient( 'district_team_sync_dry_run_' . get_current_user_id() );

        echo '<h2>Run Sync</h2>';
        if ( ! $configured_url ) {
            echo '<p>Configure the CakePHP API URL under <a href="' . esc_url( admin_url( 'options-general.php?page=district-wordpress-plugin' ) ) . '">Settings → District Plugin</a> before synchronizing.</p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="district_team_sync" />';
        wp_nonce_field( 'district_team_sync' );
        echo '<p><label><input type="checkbox" name="dry_run" value="1" /> Dry run — fetch and reconcile without changing WordPress content</label></p>';
        $button_attributes = ( ! $configured_url || $running ) ? [ 'disabled' => 'disabled' ] : [];
        submit_button( 'Run Sync Now', 'primary', 'submit', false, $button_attributes );
        echo '</form>';
        echo '<p class="description">The sync runs synchronously. The page returns with reconciliation counts when complete; it does not display a percentage because the source API does not expose reliable progress information.</p>';
        echo '</div>';
    }

    public function coordinator_queue_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $queue_url = defined( 'DISTRICT_COORDINATOR_ISSUE_SQS_QUEUE_URL' ) ? DISTRICT_COORDINATOR_ISSUE_SQS_QUEUE_URL : get_option( CoordinatorIssueQueue::QUEUE_URL_OPTION, '' );
        $region = defined( 'DISTRICT_COORDINATOR_ISSUE_AWS_REGION' ) ? DISTRICT_COORDINATOR_ISSUE_AWS_REGION : get_option( CoordinatorIssueQueue::REGION_OPTION, '' );
        $last_poll = get_option( CoordinatorIssueQueue::LAST_POLL_OPTION, '' );
        $last_result = get_option( CoordinatorIssueQueue::LAST_RESULT_OPTION, array() );
        $next_poll = wp_next_scheduled( CoordinatorIssueQueue::HOOK );
        $notice = isset( $_GET['district_queue_notice'] ) ? sanitize_key( wp_unslash( $_GET['district_queue_notice'] ) ) : '';

        echo '<div class="wrap"><h1>Coordinator Queue</h1>';
        if ( 'success' === $notice ) {
            echo '<div class="notice notice-success"><p>Coordinator queue poll completed.</p></div>';
        } elseif ( 'error' === $notice ) {
            $error = get_transient( 'district_coordinator_queue_error_' . get_current_user_id() );
            echo '<div class="notice notice-error"><p><strong>Queue poll failed:</strong> ' . esc_html( (string) $error ) . '</p></div>';
            delete_transient( 'district_coordinator_queue_error_' . get_current_user_id() );
        }

        echo '<h2>Status</h2><table class="widefat striped" style="max-width:900px"><tbody>';
        echo '<tr><th style="width:240px">SQS queue</th><td><code>' . esc_html( $queue_url ?: 'Not configured' ) . '</code></td></tr>';
        echo '<tr><th>AWS region</th><td><code>' . esc_html( $region ?: 'Not configured' ) . '</code></td></tr>';
        echo '<tr><th>AWS credential profile</th><td><code>' . esc_html( defined( 'DISTRICT_COORDINATOR_ISSUE_AWS_PROFILE' ) ? (string) constant( 'DISTRICT_COORDINATOR_ISSUE_AWS_PROFILE' ) : 'Default credential chain' ) . '</code></td></tr>';
        echo '<tr><th>Schedule</th><td>Every five minutes</td></tr>';
        echo '<tr><th>Next scheduled poll</th><td>' . esc_html( $next_poll ? $this->format_monitor_time( $next_poll ) : 'Not scheduled' ) . '</td></tr>';
        echo '<tr><th>Last poll started</th><td>' . esc_html( $this->format_monitor_time( $last_poll ) ) . '</td></tr>';
        echo '</tbody></table>';

        if ( is_array( $last_result ) && ! empty( $last_result ) ) {
            echo '<h2>Last Poll Result</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>Received</th><th>Processed</th><th>Failed</th><th>Last error</th></tr></thead><tbody><tr>';
            foreach ( array( 'received', 'processed', 'failed' ) as $key ) {
                echo '<td>' . esc_html( (string) ( $last_result[ $key ] ?? 0 ) ) . '</td>';
            }
            echo '<td>' . esc_html( (string) ( $last_result['error'] ?? '' ) ?: 'None' ) . '</td></tr></tbody></table>';
        }

        echo '<h2>Poll Queue</h2>';
        if ( ! $queue_url || ! $region ) {
            echo '<p>Configure the SQS queue URL and AWS region under <a href="' . esc_url( admin_url( 'options-general.php?page=district-wordpress-plugin' ) ) . '">Settings → District Plugin</a> before polling.</p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="district_coordinator_queue_poll">';
        wp_nonce_field( 'district_coordinator_queue_poll' );
        submit_button( 'Poll Queue Now', 'primary', 'submit', false, ( ! $queue_url || ! $region ) ? array( 'disabled' => 'disabled' ) : array() );
        echo '</form><p class="description">Each poll receives up to 10 messages. Successfully ingested messages are deleted; failed messages remain available for retry or dead-letter handling.</p></div>';
    }

    public function run_coordinator_queue_poll(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to poll this queue.', 'district-wordpress-plugin' ) );
        }
        check_admin_referer( 'district_coordinator_queue_poll' );
        $redirect = admin_url( 'tools.php?page=district-coordinator-queue' );
        try {
            ( new CoordinatorIssueQueue() )->consume();
            $redirect = add_query_arg( 'district_queue_notice', 'success', $redirect );
        } catch ( \Throwable $error ) {
            set_transient( 'district_coordinator_queue_error_' . get_current_user_id(), $error->getMessage(), 300 );
            $redirect = add_query_arg( 'district_queue_notice', 'error', $redirect );
        }
        wp_safe_redirect( $redirect );
    }

    public function run_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to run this synchronization.', 'district-wordpress-plugin' ) );
        }
        check_admin_referer( 'district_team_sync' );

        $dry_run = ! empty( $_POST['dry_run'] );
        $redirect = admin_url( 'tools.php?page=district-team-sync' );

        try {
            $sync = new DirectorySynchronizer(
                new DistrictTeamApiClient(),
                new DirectorySourceValidator(),
                new TeamRolePostRepository(),
                new SyncLock()
            );
            $result = $sync->sync( $dry_run, false );
            if ( $dry_run ) {
                set_transient( 'district_team_sync_dry_run_' . get_current_user_id(), $result, 300 );
            }
            $redirect = add_query_arg( 'district_sync_notice', $dry_run ? 'dry-run' : 'success', $redirect );
        } catch ( \Throwable $error ) {
            set_transient( 'district_team_sync_error_' . get_current_user_id(), $error->getMessage(), 300 );
            $redirect = add_query_arg( 'district_sync_notice', 'error', $redirect );
        }

        wp_safe_redirect( $redirect );
        return;
    }
}
