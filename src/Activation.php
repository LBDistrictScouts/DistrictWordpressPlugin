<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

class Activation {
    public static function activate() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        // Example: add_option( 'districtwp_version', DISTRICTWP_VERSION );
        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Cleanup tasks on deactivation
        flush_rewrite_rules();
    }
}
