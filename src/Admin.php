<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use LBDistrictScouts\DistrictWordpressPlugin\Sync\DistrictTeamApiClient;

class Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function admin_menu() {
        add_options_page( 'District Plugin Settings', 'District Plugin', 'manage_options', 'district-wordpress-plugin', [ $this, 'settings_page' ] );
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

        add_settings_section(
            'district_team_sync',
            'District Team Synchronization',
            '__return_false',
            'district-wordpress-plugin'
        );

        add_settings_field(
            DistrictTeamApiClient::OPTION_NAME,
            'CakePHP API URL',
            [ $this, 'api_url_field' ],
            'district-wordpress-plugin',
            'district_team_sync'
        );
    }

    public function api_url_field() {
        $constant_override = defined( 'DISTRICT_TEAM_API_URL' );
        $value = $constant_override
            ? DISTRICT_TEAM_API_URL
            : get_option( DistrictTeamApiClient::OPTION_NAME, '' );

        printf(
            '<input type="url" class="regular-text code" name="%1$s" value="%2$s" placeholder="https://example.org/api" %3$s />',
            esc_attr( DistrictTeamApiClient::OPTION_NAME ),
            esc_attr( $value ),
            $constant_override ? 'disabled="disabled"' : ''
        );

        if ( $constant_override ) {
            echo '<p class="description">Configured by the <code>DISTRICT_TEAM_API_URL</code> constant. The WordPress setting is disabled.</p>';
        } else {
            echo '<p class="description">Base URL for the CakePHP directory API. Do not include <code>/teams</code> or <code>/roles</code>.</p>';
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
}
