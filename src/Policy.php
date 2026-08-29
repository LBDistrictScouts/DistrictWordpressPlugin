<?php
/**
 * Policy custom post type.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Registers the Policy custom post type.
 */
class Policy {

    public const POST_TYPE = 'policy';

    /**
     * Register the post type.
     *
     * @return void
     */
    public function register(): void {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels'             => array(
                    'name'                  => __( 'Policies', 'district-wordpress-plugin' ),
                    'singular_name'         => __( 'Policy', 'district-wordpress-plugin' ),
                    'menu_name'             => __( 'Policies', 'district-wordpress-plugin' ),
                    'name_admin_bar'        => __( 'Policy', 'district-wordpress-plugin' ),
                    'add_new'               => __( 'Add New', 'district-wordpress-plugin' ),
                    'add_new_item'          => __( 'Add New Policy', 'district-wordpress-plugin' ),
                    'new_item'              => __( 'New Policy', 'district-wordpress-plugin' ),
                    'edit_item'             => __( 'Edit Policy', 'district-wordpress-plugin' ),
                    'view_item'             => __( 'View Policy', 'district-wordpress-plugin' ),
                    'all_items'             => __( 'All Policies', 'district-wordpress-plugin' ),
                    'search_items'          => __( 'Search Policies', 'district-wordpress-plugin' ),
                    'not_found'             => __( 'No policies found.', 'district-wordpress-plugin' ),
                    'not_found_in_trash'    => __( 'No policies found in the bin.', 'district-wordpress-plugin' ),
                    'featured_image'        => __( 'Policy Image', 'district-wordpress-plugin' ),
                    'set_featured_image'    => __( 'Set policy image', 'district-wordpress-plugin' ),
                    'remove_featured_image' => __( 'Remove policy image', 'district-wordpress-plugin' ),
                    'use_featured_image'    => __( 'Use as policy image', 'district-wordpress-plugin' ),
                ),
                'public'             => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_rest'       => true,
                'has_archive'        => true,
                'rewrite'            => array( 'slug' => 'policies' ),
                'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
                'menu_icon'          => 'dashicons-media-text',
                'hierarchical'       => false,
                'publicly_queryable' => true,
            )
        );
    }
}
