<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

class Activation {
    public static function activate() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        // Register rewrite-producing content types before persisting rewrite rules.
        $team_role = new TeamRole();
        $team_role->register();

        flush_rewrite_rules();
    }

    public static function deactivate() {
        // Cleanup tasks on deactivation.
        flush_rewrite_rules();
    }
}
