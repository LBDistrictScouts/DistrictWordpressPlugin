<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

class Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
    }

    public function admin_menu() {
        add_options_page( 'District Plugin Settings', 'District Plugin', 'manage_options', 'district-wordpress-plugin', [ $this, 'settings_page' ] );
    }

    public function settings_page() {
        echo '<div class="wrap"><h1>District WordPress Plugin</h1><p>Settings go here.</p></div>';
    }
}
