<?php
/**
 * Coordinator Issue custom post type.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Registers Coordinator Issues and their publication metadata.
 */
class CoordinatorIssue {

    public const POST_TYPE = 'coordinator_issue';
    public const PDF_META = '_coordinator_issue_pdf_id';
    public const DATE_META = '_coordinator_issue_date';
    public const YEAR_META = '_coordinator_issue_year';
    public const MONTH_META = '_coordinator_issue_month';
    public const EXTERNAL_ID_META = '_coordinator_issue_external_id';

    /**
     * Register the post type, metadata, and editor hooks.
     *
     * @return void
     */
    public function register(): void {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Coordinator Issues', 'district-wordpress-plugin' ),
                    'singular_name'      => __( 'Coordinator Issue', 'district-wordpress-plugin' ),
                    'menu_name'          => __( 'Coordinator Issues', 'district-wordpress-plugin' ),
                    'add_new_item'       => __( 'Add New Coordinator Issue', 'district-wordpress-plugin' ),
                    'edit_item'          => __( 'Edit Coordinator Issue', 'district-wordpress-plugin' ),
                    'new_item'           => __( 'New Coordinator Issue', 'district-wordpress-plugin' ),
                    'view_item'          => __( 'View Coordinator Issue', 'district-wordpress-plugin' ),
                    'all_items'          => __( 'All Coordinator Issues', 'district-wordpress-plugin' ),
                    'search_items'       => __( 'Search Coordinator Issues', 'district-wordpress-plugin' ),
                    'not_found'          => __( 'No Coordinator Issues found.', 'district-wordpress-plugin' ),
                    'not_found_in_trash' => __( 'No Coordinator Issues found in the bin.', 'district-wordpress-plugin' ),
                ),
                'public'             => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_rest'       => true,
                'has_archive'        => true,
                'rewrite'            => array( 'slug' => 'coordinator-issues' ),
                'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
                'menu_icon'          => 'dashicons-media-document',
                'publicly_queryable' => true,
            )
        );

        $this->register_meta();
        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media' ) );
        add_action( 'admin_footer-post.php', array( $this, 'render_media_script' ) );
        add_action( 'admin_footer-post-new.php', array( $this, 'render_media_script' ) );
    }

    /**
     * Add the primary issue-details panel.
     *
     * @return void
     */
    public function add_meta_box(): void {
        add_meta_box(
            'coordinator_issue_details',
            __( 'Coordinator Issue PDF and publication details', 'district-wordpress-plugin' ),
            array( $this, 'render_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the prominent PDF selector and editable issue fields.
     *
     * @param \WP_Post $post Current issue.
     * @return void
     */
    public function render_meta_box( \WP_Post $post ): void {
        $pdf_id = (int) get_post_meta( $post->ID, self::PDF_META, true );
        $pdf_url = $pdf_id ? (string) wp_get_attachment_url( $pdf_id ) : '';
        $pdf_name = $pdf_id ? (string) get_the_title( $pdf_id ) : '';
        $date = (string) get_post_meta( $post->ID, self::DATE_META, true );
        $year = (string) get_post_meta( $post->ID, self::YEAR_META, true );
        $month = (int) get_post_meta( $post->ID, self::MONTH_META, true );

        wp_nonce_field( 'save_coordinator_issue_' . $post->ID, 'coordinator_issue_nonce' );
        echo '<div class="coordinator-issue-pdf" style="padding:16px;border:2px solid #2271b1;background:#f6f7f7;margin-bottom:20px">';
        echo '<h3 style="margin-top:0">' . esc_html__( 'Issue PDF', 'district-wordpress-plugin' ) . '</h3>';
        echo '<input type="hidden" id="coordinator_issue_pdf_id" name="coordinator_issue_pdf_id" value="' . esc_attr( (string) $pdf_id ) . '">';
        echo '<p id="coordinator_issue_pdf_current">';
        if ( $pdf_url ) {
            echo '<strong>' . esc_html( $pdf_name ) . '</strong><br><a href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open current PDF', 'district-wordpress-plugin' ) . '</a>';
        } else {
            echo esc_html__( 'No PDF selected.', 'district-wordpress-plugin' );
        }
        echo '</p>';
        echo '<p><button type="button" class="button button-primary" id="coordinator_issue_choose_pdf">' . esc_html__( 'Choose or upload PDF', 'district-wordpress-plugin' ) . '</button> ';
        echo '<button type="button" class="button" id="coordinator_issue_remove_pdf"' . ( $pdf_id ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Remove PDF', 'district-wordpress-plugin' ) . '</button></p>';
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:16px">';
        echo '<p><label for="coordinator_issue_date"><strong>' . esc_html__( 'Issue date', 'district-wordpress-plugin' ) . '</strong></label><br>';
        echo '<input class="widefat" type="date" id="coordinator_issue_date" name="coordinator_issue_date" value="' . esc_attr( $date ) . '"></p>';
        echo '<p><label for="coordinator_issue_year"><strong>' . esc_html__( 'Issue year', 'district-wordpress-plugin' ) . '</strong></label><br>';
        echo '<input class="widefat" type="number" min="1900" max="9999" id="coordinator_issue_year" name="coordinator_issue_year" value="' . esc_attr( $year ) . '"></p>';
        echo '<p><label for="coordinator_issue_month"><strong>' . esc_html__( 'Issue month', 'district-wordpress-plugin' ) . '</strong></label><br>';
        echo '<select class="widefat" id="coordinator_issue_month" name="coordinator_issue_month"><option value="">' . esc_html__( 'Select month', 'district-wordpress-plugin' ) . '</option>';
        for ( $number = 1; $number <= 12; ++$number ) {
            printf( '<option value="%1$d"%2$s>%3$s</option>', $number, selected( $month, $number, false ), esc_html( date_i18n( 'F', mktime( 0, 0, 0, $number, 1 ) ) ) );
        }
        echo '</select></p></div>';
    }

    /**
     * Save issue metadata.
     *
     * @param int      $post_id Current post ID.
     * @param \WP_Post $post Current post.
     * @return void
     */
    public function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['coordinator_issue_nonce'] ) ) {
            return;
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['coordinator_issue_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'save_coordinator_issue_' . $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $pdf_id = isset( $_POST['coordinator_issue_pdf_id'] ) ? absint( wp_unslash( $_POST['coordinator_issue_pdf_id'] ) ) : 0;
        if ( $pdf_id && 'application/pdf' === get_post_mime_type( $pdf_id ) ) {
            update_post_meta( $post_id, self::PDF_META, $pdf_id );
        } elseif ( 0 === $pdf_id ) {
            delete_post_meta( $post_id, self::PDF_META );
        }

        $date = isset( $_POST['coordinator_issue_date'] ) ? sanitize_text_field( wp_unslash( $_POST['coordinator_issue_date'] ) ) : '';
        if ( $this->valid_date( $date ) ) {
            update_post_meta( $post_id, self::DATE_META, $date );
        } elseif ( '' === $date ) {
            delete_post_meta( $post_id, self::DATE_META );
        }

        $year = isset( $_POST['coordinator_issue_year'] ) ? absint( wp_unslash( $_POST['coordinator_issue_year'] ) ) : 0;
        if ( $year >= 1900 && $year <= 9999 ) {
            update_post_meta( $post_id, self::YEAR_META, $year );
        } elseif ( 0 === $year ) {
            delete_post_meta( $post_id, self::YEAR_META );
        }

        $month = isset( $_POST['coordinator_issue_month'] ) ? absint( wp_unslash( $_POST['coordinator_issue_month'] ) ) : 0;
        if ( $month >= 1 && $month <= 12 ) {
            update_post_meta( $post_id, self::MONTH_META, $month );
        } elseif ( 0 === $month ) {
            delete_post_meta( $post_id, self::MONTH_META );
        }
    }

    /**
     * Load the WordPress media library on Coordinator Issue editors.
     *
     * @return void
     */
    public function enqueue_media(): void {
        $screen = get_current_screen();
        if ( $screen && self::POST_TYPE === $screen->post_type ) {
            wp_enqueue_media();
        }
    }

    /**
     * Render the PDF-only media-selector behavior.
     *
     * @return void
     */
    public function render_media_script(): void {
        $screen = get_current_screen();
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }
        ?>
        <script>
        jQuery(function ($) {
            let frame;
            $('#coordinator_issue_choose_pdf').on('click', function () {
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: <?php echo wp_json_encode( __( 'Choose issue PDF', 'district-wordpress-plugin' ) ); ?>, button: { text: <?php echo wp_json_encode( __( 'Use this PDF', 'district-wordpress-plugin' ) ); ?> }, library: { type: 'application/pdf' }, multiple: false });
                frame.on('select', function () {
                    const file = frame.state().get('selection').first().toJSON();
                    $('#coordinator_issue_pdf_id').val(file.id);
                    $('#coordinator_issue_pdf_current').html($('<strong>').text(file.title)).append('<br>').append($('<a>', { href: file.url, target: '_blank', rel: 'noopener', text: <?php echo wp_json_encode( __( 'Open selected PDF', 'district-wordpress-plugin' ) ); ?> }));
                    $('#coordinator_issue_remove_pdf').show();
                });
                frame.open();
            });
            $('#coordinator_issue_remove_pdf').on('click', function () {
                $('#coordinator_issue_pdf_id').val('');
                $('#coordinator_issue_pdf_current').text(<?php echo wp_json_encode( __( 'No PDF selected.', 'district-wordpress-plugin' ) ); ?>);
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    /**
     * Register REST-visible issue metadata.
     *
     * @return void
     */
    private function register_meta(): void {
        foreach ( array( self::PDF_META, self::YEAR_META, self::MONTH_META ) as $key ) {
            register_post_meta( self::POST_TYPE, $key, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint' ) );
        }
        register_post_meta( self::POST_TYPE, self::DATE_META, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field' ) );
        register_post_meta( self::POST_TYPE, self::EXTERNAL_ID_META, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field' ) );
    }

    /**
     * Validate an ISO calendar date.
     *
     * @param string $date Candidate date.
     * @return bool
     */
    private function valid_date( string $date ): bool {
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) ) {
            return false;
        }
        return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
    }
}
