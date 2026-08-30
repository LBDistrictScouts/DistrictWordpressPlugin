<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use RuntimeException;

class CoordinatorIssueIngester {
    public function ingest( array $message ): int {
        $existing = get_posts( array( 'post_type' => CoordinatorIssue::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => CoordinatorIssue::EXTERNAL_ID_META, 'meta_value' => $message['external_id'] ) );
        if ( $existing ) {
            $post_id = (int) $existing[0];
            $this->publish( $post_id );
            return $post_id;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $temporary = download_url( $message['pdf_url'], 30 );
        if ( is_wp_error( $temporary ) ) {
            throw new RuntimeException( $temporary->get_error_message() );
        }
        $path = (string) wp_parse_url( $message['pdf_url'], PHP_URL_PATH );
        $filename = sanitize_file_name( basename( $path ?: 'coordinator-issue.pdf' ) );
        if ( ! str_ends_with( strtolower( $filename ), '.pdf' ) ) {
            $filename .= '.pdf';
        }
        $attachment_id = media_handle_sideload( array( 'name' => $filename, 'tmp_name' => $temporary ), 0, $message['title'] );
        if ( is_wp_error( $attachment_id ) ) {
            wp_delete_file( $temporary );
            throw new RuntimeException( $attachment_id->get_error_message() );
        }
        if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
            wp_delete_attachment( $attachment_id, true );
            throw new RuntimeException( 'Downloaded Coordinator Issue file is not a PDF.' );
        }
        $post_id = wp_insert_post(
            array(
                'post_type'    => CoordinatorIssue::POST_TYPE,
                'post_status'  => 'draft',
                'post_title'   => sanitize_text_field( $message['title'] ),
                'post_content' => isset( $message['description'] ) ? wp_kses_post( $message['description'] ) : '',
            ),
            true
        );
        if ( is_wp_error( $post_id ) ) {
            wp_delete_attachment( $attachment_id, true );
            throw new RuntimeException( $post_id->get_error_message() );
        }
        if ( ! empty( $message['preview_image_url'] ) ) {
            $preview_id = media_sideload_image( $message['preview_image_url'], (int) $post_id, $message['title'], 'id' );
            if ( is_wp_error( $preview_id ) ) {
                wp_delete_post( (int) $post_id, true );
                wp_delete_attachment( $attachment_id, true );
                throw new RuntimeException( $preview_id->get_error_message() );
            }
            set_post_thumbnail( (int) $post_id, (int) $preview_id );
        }
        foreach ( array( CoordinatorIssue::EXTERNAL_ID_META => sanitize_text_field( $message['external_id'] ), CoordinatorIssue::PDF_META => (int) $attachment_id, CoordinatorIssue::DATE_META => $message['issue_date'], CoordinatorIssue::YEAR_META => $message['issue_year'], CoordinatorIssue::MONTH_META => $message['issue_month'] ) as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
        $this->publish( (int) $post_id );
        return (int) $post_id;
    }

    private function publish( int $post_id ): void {
        $result = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true );
        if ( is_wp_error( $result ) ) {
            throw new RuntimeException( $result->get_error_message() );
        }
    }
}
