<?php
/**
 * Team Role custom post type.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Registers the Team Role custom post type.
 */
class TeamRole {

    /**
     * WordPress post type key.
     */
    public const POST_TYPE = 'team_role';

    /**
     * Register the post type and its public synchronization metadata.
     *
     * @return void
     */
    public function register(): void {
        $labels = array(
            'name'                  => __( 'Team Roles', 'district-wordpress-plugin' ),
            'singular_name'         => __( 'Team Role', 'district-wordpress-plugin' ),
            'menu_name'             => __( 'Team Roles', 'district-wordpress-plugin' ),
            'name_admin_bar'        => __( 'Team Role', 'district-wordpress-plugin' ),
            'add_new'               => __( 'Add New', 'district-wordpress-plugin' ),
            'add_new_item'          => __( 'Add New Team Role', 'district-wordpress-plugin' ),
            'new_item'              => __( 'New Team Role', 'district-wordpress-plugin' ),
            'edit_item'             => __( 'Edit Team Role', 'district-wordpress-plugin' ),
            'view_item'             => __( 'View Team Role', 'district-wordpress-plugin' ),
            'all_items'             => __( 'All Team Roles', 'district-wordpress-plugin' ),
            'search_items'          => __( 'Search Team Roles', 'district-wordpress-plugin' ),
            'not_found'             => __( 'No team roles found.', 'district-wordpress-plugin' ),
            'not_found_in_trash'    => __( 'No team roles found in Trash.', 'district-wordpress-plugin' ),
            'featured_image'        => __( 'Team Role Image', 'district-wordpress-plugin' ),
            'set_featured_image'    => __( 'Set team role image', 'district-wordpress-plugin' ),
            'remove_featured_image' => __( 'Remove team role image', 'district-wordpress-plugin' ),
            'use_featured_image'    => __( 'Use as team role image', 'district-wordpress-plugin' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'has_archive'        => true,
            'rewrite'            => array( 'slug' => 'team-roles' ),
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
            'menu_icon'          => 'dashicons-groups',
            'hierarchical'       => false,
            'publicly_queryable' => true,
        );

        register_post_type( self::POST_TYPE, $args );
        $this->register_meta();
    }

    /**
     * Register public-safe synchronization metadata for REST responses.
     *
     * @return void
     */
    private function register_meta(): void {
        $string_fields = array(
            '_district_source_id',
            '_district_source_type',
            '_district_parent_team_id',
            '_district_owner_team_id',
            '_district_role_description',
        );

        foreach ( $string_fields as $field ) {
            register_post_meta(
                self::POST_TYPE,
                $field,
                array(
                    'type'         => 'string',
                    'single'       => true,
                    'show_in_rest' => true,
                )
            );
        }

        register_post_meta(
            self::POST_TYPE,
            '_district_currently_filled',
            array(
                'type'         => 'boolean',
                'single'       => true,
                'show_in_rest' => true,
            )
        );
    }
}
