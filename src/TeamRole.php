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
     * Result of a source reassignment attempted during the current request.
     *
     * @var string
     */
    private string $reassignment_notice = '';

    /**
     * Existing replacement post to open after a content migration.
     *
     * @var int
     */
    private int $reassignment_target_post_id = 0;

    /**
     * Prevent nested save hooks while a migration updates both posts.
     *
     * @var bool
     */
    private bool $is_migrating = false;

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

        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_reassignment' ), 10, 2 );
        add_filter( 'redirect_post_location', array( $this, 'add_reassignment_notice' ) );
        add_action( 'admin_notices', array( $this, 'render_reassignment_notice' ) );
        add_filter( 'postbox_classes_' . self::POST_TYPE . '_district_team_role_reassignment', array( $this, 'close_reassignment_meta_box' ) );
        add_action( 'admin_head-post.php', array( $this, 'style_reassignment_meta_box' ) );
        add_action( 'admin_head-post-new.php', array( $this, 'style_reassignment_meta_box' ) );
    }

    /**
     * Add synchronized metadata and reassignment panels to the editor.
     *
     * @return void
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'district_team_role_metadata',
            __( 'District synchronization data', 'district-wordpress-plugin' ),
            array( $this, 'render_metadata_meta_box' ),
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'district_team_role_reassignment',
            __( 'Reassign Source', 'district-wordpress-plugin' ),
            array( $this, 'render_reassignment_meta_box' ),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Make the reassignment panel collapsed by default.
     *
     * @param string[] $classes Meta box CSS classes.
     * @return string[]
     */
    public function close_reassignment_meta_box( array $classes ): array {
        $classes[] = 'closed';
        return array_unique( $classes );
    }

    /**
     * Hide ordering controls for the reassignment panel only.
     *
     * @return void
     */
    public function style_reassignment_meta_box(): void {
        $screen = get_current_screen();
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        echo '<style>#district_team_role_reassignment .handle-order-higher, #district_team_role_reassignment .handle-order-lower { display: none; }</style>';
    }

    /**
     * Render synchronized metadata as read-only values.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_metadata_meta_box( \WP_Post $post ): void {
        $fields = array(
            '_district_source_id'          => __( 'Source ID', 'district-wordpress-plugin' ),
            '_district_source_type'        => __( 'Source type', 'district-wordpress-plugin' ),
            '_district_parent_team_id'     => __( 'Parent team ID', 'district-wordpress-plugin' ),
            '_district_owner_team_id'      => __( 'Owner team ID', 'district-wordpress-plugin' ),
            '_district_role_description'   => __( 'Role description', 'district-wordpress-plugin' ),
            '_district_currently_filled'   => __( 'Currently filled', 'district-wordpress-plugin' ),
            '_district_is_lead'            => __( 'Lead role', 'district-wordpress-plugin' ),
            '_district_last_synced_at'     => __( 'Last synchronized', 'district-wordpress-plugin' ),
            '_district_deactivated_at'     => __( 'Deactivated at', 'district-wordpress-plugin' ),
            '_district_deactivated_reason' => __( 'Deactivation reason', 'district-wordpress-plugin' ),
        );
        $boolean_fields = array( '_district_currently_filled', '_district_is_lead' );

        echo '<table class="widefat striped"><tbody>';
        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, $key, true );
            if ( in_array( $key, $boolean_fields, true ) ) {
                $value = $value ? __( 'Yes', 'district-wordpress-plugin' ) : __( 'No', 'district-wordpress-plugin' );
            } elseif ( '' === (string) $value ) {
                $value = '&mdash;';
            } else {
                $value = nl2br( esc_html( (string) $value ) );
            }

            printf(
                '<tr><th scope="row" style="width: 30%%;">%1$s</th><td>%2$s</td></tr>',
                esc_html( $label ),
                wp_kses_post( (string) $value )
            );
        }
        echo '</tbody></table>';
    }

    /**
     * Render the guarded source reassignment control.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function render_reassignment_meta_box( \WP_Post $post ): void {
        $source_id   = (string) get_post_meta( $post->ID, '_district_source_id', true );
        $source_type = (string) get_post_meta( $post->ID, '_district_source_type', true );

        wp_nonce_field( 'district_reassign_source_' . $post->ID, 'district_reassign_source_nonce' );
        printf(
            '<p>%1$s <strong>%2$s:%3$s</strong></p>',
            esc_html__( 'Current source:', 'district-wordpress-plugin' ),
            esc_html( $source_type ),
            esc_html( $source_id )
        );
        echo '<p>' . esc_html__( 'Enter a replacement source ID and update this post to retain its existing WordPress content for that replacement.', 'district-wordpress-plugin' ) . '</p>';
        echo '<p><label for="district_replacement_source_id"><strong>' . esc_html__( 'Replacement source ID', 'district-wordpress-plugin' ) . '</strong></label></p>';
        echo '<p><input class="widefat" type="text" id="district_replacement_source_id" name="district_replacement_source_id" value="" autocomplete="off"></p>';
        echo '<p><label><input type="checkbox" name="district_migrate_to_existing" value="1"> ' . esc_html__( 'If the replacement already exists, move this post’s authored content to it and draft this post.', 'district-wordpress-plugin' ) . '</label></p>';
        echo '<p class="description">' . esc_html__( 'A direct reassignment retains this post. A migration copies its body, excerpt, featured image, and non-synchronization metadata to the existing replacement. Synchronized fields refresh on the next sync.', 'district-wordpress-plugin' ) . '</p>';
    }

    /**
     * Reassign a post to a replacement source identity.
     *
     * @param int      $post_id Current post ID.
     * @param \WP_Post $post    Current post.
     * @return void
     */
    public function save_reassignment( int $post_id, \WP_Post $post ): void {
        if ( $this->is_migrating ) {
            return;
        }

        if ( ! isset( $_POST['district_reassign_source_nonce'], $_POST['district_replacement_source_id'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['district_reassign_source_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'district_reassign_source_' . $post_id ) ) {
            return;
        }

        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $replacement_id = sanitize_text_field( wp_unslash( $_POST['district_replacement_source_id'] ) );
        if ( '' === $replacement_id ) {
            return;
        }

        $source_id   = (string) get_post_meta( $post_id, '_district_source_id', true );
        $source_type = (string) get_post_meta( $post_id, '_district_source_type', true );
        if ( '' === $source_type ) {
            $this->reassignment_notice = 'missing_source';
            return;
        }

        if ( $replacement_id === $source_id ) {
            $this->reassignment_notice = 'unchanged';
            return;
        }

        $duplicates = get_posts(
            array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'post__not_in'   => array( $post_id ),
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'   => '_district_source_type',
                        'value' => $source_type,
                    ),
                    array(
                        'key'   => '_district_source_id',
                        'value' => $replacement_id,
                    ),
                ),
            )
        );

        if ( ! empty( $duplicates ) ) {
            if ( isset( $_POST['district_migrate_to_existing'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['district_migrate_to_existing'] ) ) ) {
                if ( $this->migrate_authored_content( $post, (int) $duplicates[0] ) ) {
                    $this->reassignment_target_post_id = (int) $duplicates[0];
                    $this->reassignment_notice         = 'migrated';
                } else {
                    $this->reassignment_notice = 'migration_failed';
                }
                return;
            }

            $this->reassignment_notice = 'duplicate';
            return;
        }

        update_post_meta( $post_id, '_district_source_id', $replacement_id );
        $this->reassignment_notice = 'success';
    }

    /**
     * Carry the reassignment result across the normal post-save redirect.
     *
     * @param string $location Redirect URL.
     * @return string
     */
    public function add_reassignment_notice( string $location ): string {
        if ( '' === $this->reassignment_notice ) {
            return $location;
        }

        if ( 0 < $this->reassignment_target_post_id ) {
            $target_location = get_edit_post_link( $this->reassignment_target_post_id, 'raw' );
            if ( is_string( $target_location ) && '' !== $target_location ) {
                $location = $target_location;
            }
        }

        return add_query_arg( 'district_reassignment', $this->reassignment_notice, $location );
    }

    /**
     * Display the result of a source reassignment.
     *
     * @return void
     */
    public function render_reassignment_notice(): void {
        if ( ! isset( $_GET['district_reassignment'] ) ) {
            return;
        }

        $notice = sanitize_key( wp_unslash( $_GET['district_reassignment'] ) );
        $messages = array(
            'success'          => array( 'success', __( 'District source reassigned. Synchronized values will refresh during the next sync.', 'district-wordpress-plugin' ) ),
            'migrated'         => array( 'success', __( 'Authored content was moved to this replacement Team Role, and the old post was moved to draft.', 'district-wordpress-plugin' ) ),
            'migration_failed' => array( 'error', __( 'The authored content could not be moved to the replacement Team Role.', 'district-wordpress-plugin' ) ),
            'duplicate'        => array( 'error', __( 'Another Team Role already uses that source ID and type. Select the migration option to move the authored content to it.', 'district-wordpress-plugin' ) ),
            'missing_source'   => array( 'error', __( 'District source was not reassigned because this post has no source type.', 'district-wordpress-plugin' ) ),
            'unchanged'        => array( 'info', __( 'District source was not changed because the replacement ID matches the current ID.', 'district-wordpress-plugin' ) ),
        );

        if ( ! isset( $messages[ $notice ] ) ) {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr( $messages[ $notice ][0] ),
            esc_html( $messages[ $notice ][1] )
        );
    }

    /**
     * Copy locally authored content to an existing synchronized replacement.
     *
     * Synchronizer-owned metadata and WordPress editing bookkeeping stay with
     * their respective posts. Other metadata, including the featured image, is
     * copied so integrations attached to the authored content continue to work.
     *
     * @param \WP_Post $source Source post.
     * @param int      $target_id Replacement post ID.
     * @return bool Whether both post updates succeeded.
     */
    private function migrate_authored_content( \WP_Post $source, int $target_id ): bool {
        $this->is_migrating = true;
        $target_result = wp_update_post(
            array(
                'ID'           => $target_id,
                'post_content' => $source->post_content,
                'post_excerpt' => $source->post_excerpt,
            ),
            true
        );
        if ( is_wp_error( $target_result ) ) {
            $this->is_migrating = false;
            return false;
        }

        $excluded_meta = array( '_edit_lock', '_edit_last', '_wp_old_slug' );
        foreach ( get_post_meta( $source->ID ) as $key => $values ) {
            if ( 0 === strpos( $key, '_district_' ) || in_array( $key, $excluded_meta, true ) || 0 === strpos( $key, '_wp_trash_meta_' ) ) {
                continue;
            }

            delete_post_meta( $target_id, $key );
            foreach ( $values as $value ) {
                add_post_meta( $target_id, $key, maybe_unserialize( $value ) );
            }
        }

        $source_result = wp_update_post(
            array(
                'ID'          => $source->ID,
                'post_status' => 'draft',
            ),
            true
        );
        $this->is_migrating = false;
        if ( is_wp_error( $source_result ) ) {
            return false;
        }

        update_post_meta( $source->ID, '_district_deactivated_at', gmdate( 'c' ) );
        update_post_meta( $source->ID, '_district_deactivated_reason', 'replaced_in_wordpress' );
        return true;
    }

    /**
     * Register public-safe synchronization metadata for REST responses.
     *
     * These fields are readable through REST but not writable through REST; CakePHP
     * remains the source of truth, apart from guarded source reassignment in admin.
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
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'auth_callback'     => '__return_false',
                    'sanitize_callback' => 'sanitize_text_field',
                )
            );
        }

        foreach ( array( '_district_currently_filled', '_district_is_lead' ) as $field ) {
            register_post_meta(
                self::POST_TYPE,
                $field,
                array(
                    'type'          => 'boolean',
                    'single'        => true,
                    'show_in_rest'  => true,
                    'auth_callback' => '__return_false',
                )
            );
        }
    }
}
