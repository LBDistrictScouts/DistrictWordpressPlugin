<?php
namespace LBDistrictScouts\DistrictWordpressPlugin;

use RuntimeException;

class CoordinatorIssueMessageValidator {
    public function validate( string $body ): array {
        $message = json_decode( $body, true );
        if ( ! is_array( $message ) || JSON_ERROR_NONE !== json_last_error() ) {
            throw new RuntimeException( 'Coordinator Issue message must contain valid JSON.' );
        }
        $required = array( 'schema_version', 'external_id', 'title', 'issue_date', 'issue_year', 'issue_month', 'pdf_url' );
        $allowed = array_merge( $required, array( 'description', 'preview_image_url' ) );
        if ( array_diff( $required, array_keys( $message ) ) || array_diff( array_keys( $message ), $allowed ) ) {
            throw new RuntimeException( 'Coordinator Issue message does not match the required properties.' );
        }
        if ( '1.0' !== $message['schema_version'] || ! is_string( $message['external_id'] ) || ! preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $message['external_id'] ) ) {
            throw new RuntimeException( 'Coordinator Issue schema version or external ID is invalid.' );
        }
        if ( ! is_string( $message['title'] ) || '' === trim( $message['title'] ) || strlen( $message['title'] ) > 255 ) {
            throw new RuntimeException( 'Coordinator Issue title is invalid.' );
        }
        if ( ! is_int( $message['issue_year'] ) || $message['issue_year'] < 1900 || $message['issue_year'] > 9999 || ! is_int( $message['issue_month'] ) || $message['issue_month'] < 1 || $message['issue_month'] > 12 ) {
            throw new RuntimeException( 'Coordinator Issue year or month is invalid.' );
        }
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $message['issue_date'], $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
            throw new RuntimeException( 'Coordinator Issue date is invalid.' );
        }
        $url = wp_parse_url( (string) $message['pdf_url'] );
        if ( ! is_array( $url ) || 'https' !== ( $url['scheme'] ?? '' ) || empty( $url['host'] ) ) {
            throw new RuntimeException( 'Coordinator Issue PDF URL must be an HTTPS URL.' );
        }
        if ( isset( $message['description'] ) && ( ! is_string( $message['description'] ) || strlen( $message['description'] ) > 5000 ) ) {
            throw new RuntimeException( 'Coordinator Issue description is invalid.' );
        }
        if ( isset( $message['preview_image_url'] ) ) {
            $preview_url = is_string( $message['preview_image_url'] ) ? wp_parse_url( $message['preview_image_url'] ) : false;
            if ( ! is_array( $preview_url ) || 'https' !== ( $preview_url['scheme'] ?? '' ) || empty( $preview_url['host'] ) ) {
                throw new RuntimeException( 'Coordinator Issue preview image URL must be an HTTPS URL.' );
            }
        }
        return $message;
    }
}
